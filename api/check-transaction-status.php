<?php
/**
 * Transaction Status Checker API
 * Allows users to check the status of their transactions
 */

require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'];
$transaction_id = (int)($_GET['id'] ?? 0);

if (!$transaction_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
    exit;
}

try {
    // Get transaction details
    $stmt = $db->prepare("
        SELECT id, type, amount, status, mpesa_receipt, mpesa_request_id, created_at, updated_at
        FROM transactions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    // If transaction is still pending and has M-Pesa request ID, query M-Pesa for status
    if ($transaction['status'] === 'pending' && !empty($transaction['mpesa_request_id'])) {
        // Check if it's been more than 5 minutes since creation
        $created_time = strtotime($transaction['created_at']);
        $current_time = time();
        $time_diff = $current_time - $created_time;
        
        if ($time_diff > 300) { // 5 minutes
            // Query M-Pesa for status
            require_once '../config/mpesa.php';
            $mpesa = new MpesaIntegration();
            $query_result = $mpesa->queryTransaction($transaction['mpesa_request_id']);
            
            if (isset($query_result['ResultCode'])) {
                if ($query_result['ResultCode'] == '0') {
                    // Payment was successful, but callback might have failed
                    // Update transaction status
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET status = 'completed', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$transaction_id]);
                    
                    // Update user wallet if it's a deposit
                    if ($transaction['type'] === 'deposit') {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET wallet_balance = wallet_balance + ?, 
                                total_deposited = total_deposited + ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$transaction['amount'], $transaction['amount'], $user_id]);
                        
                        // Send notification
                        sendNotification(
                            $user_id,
                            'Deposit Confirmed ',
                            "Your deposit of " . formatMoney($transaction['amount']) . " has been confirmed and credited to your wallet.",
                            'success'
                        );
                    }
                    
                    $transaction['status'] = 'completed';
                    
                } elseif (in_array($query_result['ResultCode'], ['1032', '1037', '1', '1001'])) {
                    // Payment was cancelled or failed
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET status = 'cancelled', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$transaction_id]);
                    
                    $transaction['status'] = 'cancelled';
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'transaction' => [
            'id' => $transaction['id'],
            'type' => $transaction['type'],
            'amount' => $transaction['amount'],
            'status' => $transaction['status'],
            'mpesa_receipt' => $transaction['mpesa_receipt'],
            'created_at' => $transaction['created_at'],
            'updated_at' => $transaction['updated_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Transaction status check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>