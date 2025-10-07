<?php
/**
 * Enhanced ROI Processing Script
 * This script should be run via cron job every hour to process matured packages
 * Cron: 0 * * * * /usr/bin/php /path/to/your/api/process-roi.php
 * Or via web interface for manual processing
 */

require_once '../config/database.php';

// Allow both CLI and web access
$is_cli = php_sapi_name() === 'cli';
$is_web = !$is_cli && isset($_POST['force']) && isAdmin();

if (!$is_cli && !$is_web) {
    http_response_code(403);
    die('Access denied');
}

// Check if auto ROI processing is enabled (skip check for manual processing)
if (!$is_web && getSystemSetting('auto_roi_processing', '1') != '1') {
    echo "Auto ROI processing is disabled.\n";
    exit;
}

// Set time limit and memory limit
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

try {
    echo "Starting ROI processing at " . date('Y-m-d H:i:s') . "\n";
    
    // Get all matured packages
    $stmt = $db->prepare("
        SELECT ap.*, u.email, u.full_name, u.phone, p.name as package_name
        FROM active_packages ap
        JOIN users u ON ap.user_id = u.id
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.status = 'active' 
        AND ap.maturity_date <= NOW()
        ORDER BY ap.maturity_date ASC
        LIMIT 100
    ");
    $stmt->execute();
    $matured_packages = $stmt->fetchAll();

    $processed_count = 0;
    $failed_count = 0;
    $total_roi_paid = 0;
    $total_investment_returned = 0;
    $errors = [];

    echo "Found " . count($matured_packages) . " matured packages to process.\n";

    foreach ($matured_packages as $package) {
        $db->beginTransaction();
        
        try {
            $user_id = $package['user_id'];
            $package_id = $package['id'];
            $investment_amount = $package['investment_amount'];
            $roi_amount = $package['expected_roi'];
            $total_return = $investment_amount + $roi_amount;

            echo "Processing package ID {$package_id} for user {$package['full_name']} - Investment: " . formatMoney($investment_amount) . ", ROI: " . formatMoney($roi_amount) . "\n";

            // Update user wallet balance (return investment + ROI)
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ?,
                    total_roi_earned = total_roi_earned + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$total_return, $roi_amount, $user_id]);

            // Create ROI payment transaction
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                VALUES (?, 'roi_payment', ?, 'completed', ?, NOW())
            ");
            $description = "ROI payment for {$package['package_name']} package - Investment: " . formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount);
            $stmt->execute([$user_id, $total_return, $description]);

            // Mark package as completed
            $stmt = $db->prepare("
                UPDATE active_packages 
                SET status = 'completed', completed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$package_id]);

            // Send notification to user
            sendNotification(
                $user_id,
                'Package Completed! 🎉',
                "Your {$package['package_name']} package has matured successfully! " . formatMoney($total_return) . " has been credited to your wallet (Investment: " . formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount) . "). Start a new investment to continue earning!",
                'success'
            );

            // Process referral commissions if user was referred
            $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $referrer = $stmt->fetch();

            if ($referrer && $referrer['referred_by']) {
                processReferralCommissions($user_id, $referrer['referred_by'], $roi_amount, $db);
            }

            $db->commit();
            $processed_count++;
            $total_roi_paid += $roi_amount;
            $total_investment_returned += $investment_amount;

            echo "✓ Successfully processed package ID {$package_id}\n";

        } catch (Exception $e) {
            $db->rollBack();
            $failed_count++;
            $error_msg = "Error processing package ID {$package_id}: " . $e->getMessage();
            echo "✗ $error_msg\n";
            $errors[] = $error_msg;
            
            // Log error for admin review
            error_log("ROI Processing Error - Package ID {$package_id}: " . $e->getMessage());
            
            // Try to notify admin about the failure
            try {
                sendNotification(
                    1, // Assuming admin user ID is 1
                    'ROI Processing Error',
                    "Failed to process package ID {$package_id} for user {$package['full_name']}. Error: {$e->getMessage()}",
                    'error'
                );
            } catch (Exception $notif_error) {
                echo "Failed to send error notification: " . $notif_error->getMessage() . "\n";
            }
        }
    }

    // Log system health after processing
    try {
        logSystemHealth();
        echo "System health logged successfully.\n";
    } catch (Exception $e) {
        echo "Failed to log system health: " . $e->getMessage() . "\n";
    }

    // Generate summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "ROI PROCESSING SUMMARY\n";
    echo str_repeat("=", 50) . "\n";
    echo "Processed: {$processed_count} packages\n";
    echo "Failed: {$failed_count} packages\n";
    echo "Total ROI Paid: " . formatMoney($total_roi_paid) . "\n";
    echo "Total Investment Returned: " . formatMoney($total_investment_returned) . "\n";
    echo "Total Amount Credited: " . formatMoney($total_roi_paid + $total_investment_returned) . "\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
    if (!empty($errors)) {
        echo "\nERRORS:\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    }
    
    // Send summary notification to admin if there were significant activities
    if ($processed_count > 0 || $failed_count > 0) {
        try {
            $summary_message = "ROI Processing Summary:\n\n";
            $summary_message .= "✓ Processed: {$processed_count} packages\n";
            $summary_message .= "✗ Failed: {$failed_count} packages\n";
            $summary_message .= "💰 Total Paid: " . formatMoney($total_roi_paid + $total_investment_returned) . "\n";
            
            if ($failed_count > 0) {
                $summary_message .= "\n⚠️ There were {$failed_count} failures. Please check the logs.";
            }
            
            sendNotification(
                1, // Admin user ID
                'ROI Processing Complete',
                $summary_message,
                $failed_count > 0 ? 'warning' : 'success'
            );
        } catch (Exception $e) {
            echo "Failed to send summary notification: " . $e->getMessage() . "\n";
        }
    }

    // Return JSON response for web requests
    if ($is_web) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'processed_count' => $processed_count,
            'failed_count' => $failed_count,
            'total_roi_paid' => $total_roi_paid,
            'total_investment_returned' => $total_investment_returned,
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    $error_message = "Fatal error in ROI processing: " . $e->getMessage();
    echo $error_message . "\n";
    error_log("Fatal ROI Processing Error: " . $e->getMessage());
    
    // Try to notify admin about fatal error
    try {
        sendNotification(
            1,
            'Critical ROI Processing Error',
            "ROI processing script encountered a fatal error: {$e->getMessage()}. Please investigate immediately.",
            'error'
        );
    } catch (Exception $notif_error) {
        echo "Failed to send critical error notification: " . $notif_error->getMessage() . "\n";
    }
    
    if ($is_web) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $error_message
        ]);
    }
    
    exit(1);
}

/**
 * Process referral commissions for ROI payments
 */
function processReferralCommissions($user_id, $referrer_id, $roi_amount, $db) {
    try {
        echo "Processing referral commissions for ROI payment...\n";
        
        // Level 1 commission (direct referrer)
        $l1_rate = (float)getSystemSetting('referral_commission_l1', 10);
        $l1_commission = ($roi_amount * $l1_rate) / 100;

        if ($l1_commission > 0) {
            // Credit referrer
            $stmt = $db->prepare("
                UPDATE users 
                SET referral_earnings = referral_earnings + ?, 
                    wallet_balance = wallet_balance + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$l1_commission, $l1_commission, $referrer_id]);

            // Create commission transaction
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                VALUES (?, 'referral_commission', ?, 'completed', ?, NOW())
            ");
            $description = "Level 1 referral commission ({$l1_rate}%) from ROI payment";
            $stmt->execute([$referrer_id, $l1_commission, $description]);

            // Send notification
            sendNotification(
                $referrer_id,
                'Referral Commission Earned! 💰',
                "You earned " . formatMoney($l1_commission) . " ({$l1_rate}%) commission from your referral's ROI payment. Keep building your network!",
                'success'
            );

            echo "Level 1 commission processed: " . formatMoney($l1_commission) . " to user $referrer_id\n";

            // Check for Level 2 referrer
            $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$referrer_id]);
            $l2_referrer = $stmt->fetch();

            if ($l2_referrer && $l2_referrer['referred_by']) {
                $l2_rate = (float)getSystemSetting('referral_commission_l2', 5);
                $l2_commission = ($roi_amount * $l2_rate) / 100;

                if ($l2_commission > 0) {
                    // Credit L2 referrer
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET referral_earnings = referral_earnings + ?, 
                            wallet_balance = wallet_balance + ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$l2_commission, $l2_commission, $l2_referrer['referred_by']]);

                    // Create L2 commission transaction
                    $stmt = $db->prepare("
                        INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                        VALUES (?, 'referral_commission', ?, 'completed', ?, NOW())
                    ");
                    $l2_description = "Level 2 referral commission ({$l2_rate}%) from ROI payment";
                    $stmt->execute([$l2_referrer['referred_by'], $l2_commission, $l2_description]);

                    // Send L2 notification
                    sendNotification(
                        $l2_referrer['referred_by'],
                        'L2 Referral Commission! 🌟',
                        "You earned " . formatMoney($l2_commission) . " ({$l2_rate}%) Level 2 commission from an indirect referral's ROI.",
                        'success'
                    );

                    echo "Level 2 commission processed: " . formatMoney($l2_commission) . " to user {$l2_referrer['referred_by']}\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "Error processing referral commissions: " . $e->getMessage() . "\n";
        error_log("Referral Commission Processing Error: " . $e->getMessage());
        // Don't fail the main ROI processing for referral errors
    }
}

/**
 * Send email notification for ROI completion (optional)
 */
function sendROICompletionEmail($user_email, $user_name, $package_name, $investment, $roi, $total) {
    try {
        $subject = "Package Completed - Ultra Harvest Global";
        $message = "
Dear $user_name,

Congratulations! Your $package_name package has completed successfully.

Package Details:
- Initial Investment: " . formatMoney($investment) . "
- ROI Earned: " . formatMoney($roi) . "
- Total Credited: " . formatMoney($total) . "

Your wallet has been credited with the full amount. You can now:
1. Withdraw your earnings
2. Reinvest in a new package
3. Share your referral link to earn commissions

Thank you for choosing Ultra Harvest Global!

Best regards,
Ultra Harvest Team
        ";
        
        // Log email (replace with actual email sending)
        error_log("ROI Completion Email: To: $user_email, Subject: $subject");
        
        // Uncomment for actual email sending:
        // $headers = "From: " . SITE_EMAIL . "\r\n";
        // $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
        // $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        // mail($user_email, $subject, $message, $headers);
        
    } catch (Exception $e) {
        error_log("ROI completion email error: " . $e->getMessage());
    }
}

/**
 * Validate system before processing ROI
 */
function validateSystemHealth() {
    global $db;
    
    try {
        // Check database connection
        $db->query("SELECT 1");
        
        // Check if there are pending transactions that might affect liquidity
        $stmt = $db->query("SELECT COUNT(*) as pending FROM transactions WHERE status = 'pending' AND type = 'withdrawal'");
        $pending_withdrawals = $stmt->fetch()['pending'];
        
        if ($pending_withdrawals > 50) {
            throw new Exception("Too many pending withdrawals ($pending_withdrawals). Manual review required.");
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception("System health check failed: " . $e->getMessage());
    }
}

// Run system health check before processing
try {
    validateSystemHealth();
    echo "System health check passed.\n";
} catch (Exception $e) {
    echo "System health check failed: " . $e->getMessage() . "\n";
    
    if ($is_web) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'System health check failed: ' . $e->getMessage()
        ]);
    }
    
    exit(1);
}
?>