<?php
/**
 * Process Scheduled Withdrawals
 * Run this via cron job every hour: 0 * * * * /usr/bin/php /path/to/cron/process-scheduled-withdrawals.php
 */

require_once '../config/database.php';
require_once '../config/mpesa.php';

// Get pending withdrawals that are ready to be processed
$delay_hours = (int)getSystemSetting('large_withdrawal_delay_hours', 1);

$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE type = 'withdrawal' 
    AND status = 'pending' 
    AND created_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY created_at ASC
    LIMIT 50
");
$stmt->execute([$delay_hours]);
$pending_withdrawals = $stmt->fetchAll();

$processed = 0;
$failed = 0;

foreach ($pending_withdrawals as $withdrawal) {
    try {
        // Process withdrawal via M-Pesa B2C
        $mpesa = new MpesaIntegration();
        $result = $mpesa->b2cPayment(
            $withdrawal['phone_number'],
            $withdrawal['amount'],
            "UH-WD-{$withdrawal['id']}",
            "Scheduled Withdrawal"
        );
        
        if ($result['success']) {
            // Update transaction
            $stmt = $db->prepare("
                UPDATE transactions 
                SET status = 'completed',
                    mpesa_request_id = ?,
                    description = CONCAT(description, ' - Processed automatically'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['ConversationID'] ?? null, $withdrawal['id']]);
            
            // Notify user
            sendNotification(
                $withdrawal['user_id'],
                'Withdrawal Completed! 💰',
                "Your scheduled withdrawal of " . formatMoney($withdrawal['amount']) . " has been processed.",
                'success'
            );
            
            $processed++;
            echo "✓ Processed withdrawal #{$withdrawal['id']} - " . formatMoney($withdrawal['amount']) . "\n";
        } else {
            // Mark as failed and refund
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
            
            $stmt = $db->prepare("
                UPDATE transactions 
                SET status = 'failed',
                    description = CONCAT(description, ' - Auto-process failed: ', ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['message'], $withdrawal['id']]);
            
            $db->commit();
            
            // Notify user
            sendNotification(
                $withdrawal['user_id'],
                'Withdrawal Failed - Balance Restored',
                "Your withdrawal of " . formatMoney($withdrawal['amount']) . " could not be processed. Funds have been returned to your wallet.",
                'error'
            );
            
            $failed++;
            echo "✗ Failed withdrawal #{$withdrawal['id']} - {$result['message']}\n";
        }
        
    } catch (Exception $e) {
        echo "ERROR processing withdrawal #{$withdrawal['id']}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=== Summary ===\n";
echo "Processed: $processed\n";
echo "Failed: $failed\n";
echo "Total: " . count($pending_withdrawals) . "\n";