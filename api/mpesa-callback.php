<?php
/**
 * Fixed M-Pesa STK Push Callback Handler
 * This file processes callbacks from M-Pesa after payment attempts
 * 
 * IMPORTANT: This file should be accessible via HTTPS only
 * URL: https://yourdomain.com/api/mpesa-callback.php
 */

require_once '../config/database.php';
require_once '../config/mpesa.php';

// Set content type and headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Create logs directory if it doesn't exist
$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Get callback data
$callback_data = file_get_contents('php://input');
$headers = getallheaders();

// Log all incoming requests for debugging
$log_entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => $headers,
    'raw_data' => $callback_data,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

file_put_contents($log_dir . '/mpesa_callbacks.log', json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Decode the JSON callback data
    if (empty($callback_data)) {
        throw new Exception('Empty callback data received');
    }
    
    $callback_json = json_decode($callback_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }
    
    // Validate callback structure
    if (!isset($callback_json['Body']['stkCallback'])) {
        throw new Exception('Invalid callback structure - missing stkCallback');
    }
    
    $callback = $callback_json['Body']['stkCallback'];
    $checkout_request_id = $callback['CheckoutRequestID'] ?? '';
    $result_code = $callback['ResultCode'] ?? '';
    $result_desc = $callback['ResultDesc'] ?? '';
    
    if (empty($checkout_request_id)) {
        throw new Exception('Missing CheckoutRequestID');
    }
    
    // Log the specific callback details
    error_log("M-Pesa Callback: CheckoutRequestID=$checkout_request_id, ResultCode=$result_code, ResultDesc=$result_desc");
    
    // Find the transaction in our database
    $stmt = $db->prepare("
        SELECT t.*, u.full_name, u.email, u.phone
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.mpesa_request_id = ? AND t.status = 'pending'
    ");
    $stmt->execute([$checkout_request_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        error_log("M-Pesa Callback: Transaction not found for CheckoutRequestID: $checkout_request_id");
        // Still return success to M-Pesa to avoid retries
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Transaction not found but acknowledged']);
        exit;
    }
    
    $db->beginTransaction();
    
    if ($result_code == '0') {
        // Payment was successful
        $mpesa_receipt = '';
        $transaction_date = '';
        $phone_number = '';
        $amount_paid = 0;
        
        // Extract callback metadata
        if (isset($callback['CallbackMetadata']['Item'])) {
            foreach ($callback['CallbackMetadata']['Item'] as $item) {
                switch ($item['Name']) {
                    case 'MpesaReceiptNumber':
                        $mpesa_receipt = $item['Value'] ?? '';
                        break;
                    case 'TransactionDate':
                        $transaction_date = $item['Value'] ?? '';
                        break;
                    case 'PhoneNumber':
                        $phone_number = $item['Value'] ?? '';
                        break;
                    case 'Amount':
                        $amount_paid = (float)($item['Value'] ?? 0);
                        break;
                }
            }
        }
        
        // Verify amount matches (allow small discrepancies due to rounding)
        if (abs($amount_paid - $transaction['amount']) > 0.01) {
            error_log("M-Pesa Callback: Amount mismatch. Expected: {$transaction['amount']}, Received: $amount_paid");
            // Log but still process
        }
        
        // Update transaction status
        $stmt = $db->prepare("
            UPDATE transactions 
            SET status = 'completed', 
                mpesa_receipt = ?, 
                description = CONCAT(COALESCE(description, ''), ' - M-Pesa Receipt: ', ?),
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$mpesa_receipt, $mpesa_receipt, $transaction['id']]);
        
        // Process based on transaction type
        if ($transaction['type'] === 'deposit') {
            // Credit user's wallet
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ?, 
                    total_deposited = total_deposited + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transaction['amount'], $transaction['amount'], $transaction['user_id']]);
            
            // Send success notification
            sendNotification(
                $transaction['user_id'],
                'Deposit Successful! 🎉',
                "Your deposit of " . formatMoney($transaction['amount']) . " has been credited to your wallet. M-Pesa Receipt: $mpesa_receipt. You can now start investing!",
                'success'
            );
            
            // CRITICAL FIX: Process referral commissions
            processReferralCommissions($transaction, $db);
            
            // Log successful deposit
            error_log("M-Pesa Deposit Success: User {$transaction['full_name']}, Amount: {$transaction['amount']}, Receipt: $mpesa_receipt");
        }
        
        $db->commit();
        
        // Send email notification if configured
        sendEmailNotification($transaction, $mpesa_receipt, 'success');
        
    } elseif (in_array($result_code, ['1032', '1037', '1'])) {
        // User cancelled or timeout
        $stmt = $db->prepare("
            UPDATE transactions 
            SET status = 'cancelled', 
                description = CONCAT(COALESCE(description, ''), ' - M-Pesa: ', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$result_desc, $transaction['id']]);
        
        // Send cancellation notification
        sendNotification(
            $transaction['user_id'],
            'Payment Cancelled',
            "Your payment of " . formatMoney($transaction['amount']) . " was cancelled. You can try again anytime.",
            'warning'
        );
        
        $db->commit();
        
        // Log cancellation
        error_log("M-Pesa Payment Cancelled: User {$transaction['full_name']}, Amount: {$transaction['amount']}, Reason: $result_desc");
        
    } else {
        // Payment failed
        $stmt = $db->prepare("
            UPDATE transactions 
            SET status = 'failed', 
                description = CONCAT(COALESCE(description, ''), ' - M-Pesa Error: ', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$result_desc, $transaction['id']]);
        
        // Send failure notification
        sendNotification(
            $transaction['user_id'],
            'Payment Failed ❌',
            "Your payment of " . formatMoney($transaction['amount']) . " could not be processed. Reason: $result_desc. Please try again or contact support.",
            'error'
        );
        
        $db->commit();
        
        // Log failed payment
        error_log("M-Pesa Payment Failed: User {$transaction['full_name']}, Amount: {$transaction['amount']}, Code: $result_code, Reason: $result_desc");
        
        // Send failure email notification
        sendEmailNotification($transaction, null, 'failed', $result_desc);
    }
    
    // Respond to M-Pesa with success
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Callback processed successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log the error
    error_log("M-Pesa Callback Error: " . $e->getMessage() . " - Data: " . $callback_data);
    
    // Respond with success to avoid M-Pesa retries, but log the error
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Error logged but acknowledged'
    ]);
}

/**
 * FIXED: Process referral commissions for deposits
 * This ensures commissions are ALWAYS processed and credited to wallet
 */
function processReferralCommissions($transaction, $db) {
    try {
        error_log("Starting referral commission processing for transaction ID: {$transaction['id']}");
        
        // Get referrer information
        $stmt = $db->prepare("SELECT id, referred_by, full_name FROM users WHERE id = ?");
        $stmt->execute([$transaction['user_id']]);
        $user_data = $stmt->fetch();
        
        if (!$user_data || !$user_data['referred_by']) {
            error_log("No referrer found for user {$transaction['user_id']} - skipping commission processing");
            return;
        }
        
        $referrer_id = $user_data['referred_by'];
        $deposit_amount = $transaction['amount'];
        
        error_log("Processing commissions - User ID: {$transaction['user_id']}, Referrer ID: $referrer_id, Deposit: $deposit_amount");
        
        // Level 1 commission
        $l1_rate = (float)getSystemSetting('referral_commission_l1', 10);
        $l1_commission = ($deposit_amount * $l1_rate) / 100;
        
        if ($l1_commission > 0) {
            error_log("Level 1 Commission: Rate=$l1_rate%, Amount=$l1_commission");
            
            // CRITICAL FIX: Credit Level 1 referrer WALLET BALANCE
            $stmt = $db->prepare("
                UPDATE users 
                SET referral_earnings = referral_earnings + ?, 
                    wallet_balance = wallet_balance + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$l1_commission, $l1_commission, $referrer_id]);
            
            if (!$result) {
                error_log("CRITICAL ERROR: Failed to credit L1 referrer wallet - Referrer ID: $referrer_id");
                throw new Exception("Failed to credit L1 referrer wallet");
            }
            
            error_log("SUCCESS: Credited L1 referrer ID $referrer_id with " . formatMoney($l1_commission));
            
            // Create commission transaction
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                VALUES (?, 'referral_commission', ?, 'completed', ?, NOW())
            ");
            $description = "Level 1 referral commission ({$l1_rate}%) from deposit by {$user_data['full_name']} (Receipt: {$transaction['mpesa_receipt']})";
            $stmt->execute([$referrer_id, $l1_commission, $description]);
            
            error_log("Created commission transaction for L1 referrer");
            
            // Notify Level 1 referrer
            sendNotification(
                $referrer_id,
                'Referral Commission Earned! 💰',
                "You earned " . formatMoney($l1_commission) . " ({$l1_rate}%) commission from {$user_data['full_name']}'s deposit. The amount has been added to your wallet!",
                'success'
            );
            
            // Check for Level 2 referrer
            $stmt = $db->prepare("SELECT id, referred_by, full_name FROM users WHERE id = ?");
            $stmt->execute([$referrer_id]);
            $l1_referrer_data = $stmt->fetch();
            
            if ($l1_referrer_data && $l1_referrer_data['referred_by']) {
                $l2_referrer_id = $l1_referrer_data['referred_by'];
                $l2_rate = (float)getSystemSetting('referral_commission_l2', 5);
                $l2_commission = ($deposit_amount * $l2_rate) / 100;
                
                error_log("Level 2 Commission: Rate=$l2_rate%, Amount=$l2_commission");
                
                if ($l2_commission > 0) {
                    // Credit Level 2 referrer
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET referral_earnings = referral_earnings + ?, 
                            wallet_balance = wallet_balance + ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([$l2_commission, $l2_commission, $l2_referrer_id]);
                    
                    if (!$result) {
                        error_log("WARNING: Failed to credit L2 referrer wallet - Referrer ID: $l2_referrer_id");
                    } else {
                        error_log("SUCCESS: Credited L2 referrer ID $l2_referrer_id with " . formatMoney($l2_commission));
                    }
                    
                    // Create L2 commission transaction
                    $stmt = $db->prepare("
                        INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                        VALUES (?, 'referral_commission', ?, 'completed', ?, NOW())
                    ");
                    $l2_description = "Level 2 referral commission ({$l2_rate}%) from deposit by {$user_data['full_name']} (Receipt: {$transaction['mpesa_receipt']})";
                    $stmt->execute([$l2_referrer_id, $l2_commission, $l2_description]);
                    
                    // Notify Level 2 referrer
                    sendNotification(
                        $l2_referrer_id,
                        'L2 Referral Commission! 🌟',
                        "You earned " . formatMoney($l2_commission) . " ({$l2_rate}%) Level 2 commission from an indirect referral's deposit.",
                        'success'
                    );
                    
                    error_log("Created commission transaction for L2 referrer");
                }
            }
        }
        
        // Log commission processing
        error_log("Referral commissions processed successfully: L1 User $referrer_id earned " . formatMoney($l1_commission));
        
    } catch (Exception $e) {
        error_log("CRITICAL ERROR in Referral Commission Processing: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        // Re-throw to fail the transaction
        throw new Exception("Referral commission processing failed: " . $e->getMessage());
    }
}

/**
 * Send email notification (if email system is configured)
 */
function sendEmailNotification($transaction, $receipt, $status, $error_message = null) {
    try {
        // This is a placeholder for email functionality
        // You can integrate with services like PHPMailer, SendGrid, etc.
        
        $to = $transaction['email'];
        $subject = $status === 'success' ? 'Payment Successful - Ultra Harvest' : 'Payment Failed - Ultra Harvest';
        
        if ($status === 'success') {
            $message = "
Dear {$transaction['full_name']},

Your payment has been processed successfully!

Details:
- Amount: " . formatMoney($transaction['amount']) . "
- M-Pesa Receipt: $receipt
- Date: " . date('Y-m-d H:i:s') . "

Your wallet has been credited and you can now start investing.

Thank you for choosing Ultra Harvest Global!

Best regards,
Ultra Harvest Team
            ";
        } else {
            $message = "
Dear {$transaction['full_name']},

Unfortunately, your payment could not be processed.

Details:
- Amount: " . formatMoney($transaction['amount']) . "
- Reason: $error_message
- Date: " . date('Y-m-d H:i:s') . "

Please try again or contact our support team for assistance.

Best regards,
Ultra Harvest Team
            ";
        }
        
        // Log email (replace with actual email sending)
        error_log("Email Notification: To: $to, Subject: $subject, Status: $status");
        
        // Uncomment and configure for actual email sending:
        // $headers = "From: " . SITE_EMAIL . "\r\n";
        // $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
        // $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        // mail($to, $subject, $message, $headers);
        
    } catch (Exception $e) {
        error_log("Email notification error: " . $e->getMessage());
    }
}

/**
 * Validate M-Pesa webhook security
 */
function validateMpesaWebhook($data) {
    // Basic validation - you can implement signature verification here
    $allowed_ips = [
        '196.201.214.200', // M-Pesa callback IPs
        '196.201.214.206',
        '196.201.213.44',
        '196.201.214.207',
        '196.201.214.208'
    ];
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // For development, allow localhost and common development IPs
    $development_ips = ['127.0.0.1', '::1', '192.168.', '10.0.', '172.16.'];
    
    foreach ($development_ips as $dev_ip) {
        if (strpos($client_ip, $dev_ip) === 0) {
            return true;
        }
    }
    
    // In production, you might want to validate IP addresses
    // return in_array($client_ip, $allowed_ips);
    
    return true; // Allow all for now - implement proper validation as needed
}

/**
 * Handle different types of M-Pesa callbacks
 */
function handleCallbackType($callback_data) {
    // This can be extended to handle different callback types
    // like C2B confirmations, B2C results, etc.
    
    if (isset($callback_data['Body']['stkCallback'])) {
        return 'STK_PUSH';
    } elseif (isset($callback_data['Body']['C2BPaymentConfirmationRequest'])) {
        return 'C2B_CONFIRMATION';
    } elseif (isset($callback_data['Body']['C2BPaymentValidationRequest'])) {
        return 'C2B_VALIDATION';
    }
    
    return 'UNKNOWN';
}

/**
 * Log successful callback processing
 */
function logSuccessfulCallback($transaction_id, $mpesa_receipt, $amount) {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => 'PAYMENT_SUCCESS',
        'transaction_id' => $transaction_id,
        'mpesa_receipt' => $mpesa_receipt,
        'amount' => $amount,
        'status' => 'completed'
    ];
    
    $log_file = dirname(__DIR__) . '/logs/payment_success.log';
    file_put_contents($log_file, json_encode($log_data) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Validate security (you can uncomment this in production if needed)
// if (!validateMpesaWebhook($callback_data)) {
//     http_response_code(403);
//     echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Unauthorized']);
//     exit;
// }

?>
