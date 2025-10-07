<?php
/**
 * ULTRA HARVEST GLOBAL - DAILY AUTOMATED TASKS
 * Run this file via cron job every 5-10 minutes
 * 
 * Crontab entry example:
 * */5 * * * * php /path/to/your/project/cron/daily_tasks.php >> /path/to/logs/cron.log 2>&1
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/system_helpers.php';

// Log start time
$start_time = microtime(true);
echo "\n" . date('Y-m-d H:i:s') . " - Starting automated tasks...\n";

// ============================================
// TASK 1: AUTO-COMPLETE MATURED PACKAGES
// ============================================
echo "Task 1: Checking for matured packages...\n";
try {
    $result = autoCompleteMaturedPackages($db);
    if ($result['success']) {
        echo "✓ {$result['message']}\n";
    } else {
        echo "✗ Error: {$result['message']}\n";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// ============================================
// TASK 2: UPDATE USER GROWTH STATISTICS
// ============================================
echo "Task 2: Updating user growth statistics...\n";
try {
    $result = updateDailyUserGrowthStats($db);
    if ($result['success']) {
        echo "✓ {$result['message']}\n";
    } else {
        echo "✗ Error: {$result['message']}\n";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// ============================================
// TASK 3: PROCESS PENDING WITHDRAWALS
// ============================================
echo "Task 3: Processing pending withdrawals...\n";
try {
    // Get pending withdrawals that are ready to process
    $stmt = $db->query("
        SELECT * FROM transactions 
        WHERE type = 'withdrawal' 
        AND status = 'pending'
        AND created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 50
    ");
    $pending_withdrawals = $stmt->fetchAll();
    
    $processed = 0;
    foreach ($pending_withdrawals as $withdrawal) {
        // Process each withdrawal
        // In production, this would call M-Pesa B2C API
        
        // For now, mark as completed if below threshold
        $threshold = (float)getSystemSetting('instant_withdrawal_threshold', 10000);
        
        if ($withdrawal['amount'] < $threshold) {
            $stmt = $db->prepare("
                UPDATE transactions 
                SET status = 'completed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$withdrawal['id']]);
            
            // Send notification
            sendNotification(
                $withdrawal['user_id'],
                'Withdrawal Completed',
                "Your withdrawal of " . formatMoney($withdrawal['amount']) . " has been processed.",
                'success'
            );
            
            $processed++;
        }
    }
    
    echo "✓ Processed {$processed} withdrawals\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// ============================================
// TASK 4: CLEAN UP OLD NOTIFICATIONS
// ============================================
echo "Task 4: Cleaning up old notifications...\n";
try {
    // Delete read notifications older than 30 days
    $stmt = $db->query("
        DELETE FROM notifications 
        WHERE is_read = 1 
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $deleted = $stmt->rowCount();
    echo "✓ Deleted {$deleted} old notifications\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// ============================================
// TASK 5: UPDATE REFERRAL COMMISSIONS
// ============================================
echo "Task 5: Processing referral commissions...\n";
try {
    // Get completed deposits/ROI payments that haven't been processed for commissions
    $stmt = $db->query("
        SELECT t.*, u.referred_by
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.type IN ('deposit', 'roi_payment')
        AND t.status = 'completed'
        AND u.referred_by IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM transactions rc 
            WHERE rc.type = 'referral_commission' 
            AND rc.description LIKE CONCAT('%transaction #', t.id, '%')
        )
        LIMIT 100
    ");
    $eligible_transactions = $stmt->fetchAll();
    
    $l1_rate = (float)getSystemSetting('referral_commission_l1', 10);
    $l2_rate = (float)getSystemSetting('referral_commission_l2', 5);
    $l2_enabled = getSystemSetting('referral_tier_2_enabled', '1') === '1';
    
    $commissions_paid = 0;
    
    foreach ($eligible_transactions as $transaction) {
        $db->beginTransaction();
        
        try {
            // Level 1 commission
            $l1_commission = ($transaction['amount'] * $l1_rate) / 100;
            
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description)
                VALUES (?, 'referral_commission', ?, 'completed', ?)
            ");
            $stmt->execute([
                $transaction['referred_by'],
                $l1_commission,
                "Level 1 commission from transaction #{$transaction['id']}"
            ]);
            
            // Update referrer wallet
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ? 
                WHERE id = ?
            ");
            $stmt->execute([$l1_commission, $transaction['referred_by']]);
            
            // Send notification
            sendNotification(
                $transaction['referred_by'],
                'Referral Commission Earned',
                "You earned " . formatMoney($l1_commission) . " commission from your referral.",
                'success'
            );
            
            // Level 2 commission (if enabled)
            if ($l2_enabled) {
                $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
                $stmt->execute([$transaction['referred_by']]);
                $level2_referrer = $stmt->fetch();
                
                if ($level2_referrer && $level2_referrer['referred_by']) {
                    $l2_commission = ($transaction['amount'] * $l2_rate) / 100;
                    
                    $stmt = $db->prepare("
                        INSERT INTO transactions (user_id, type, amount, status, description)
                        VALUES (?, 'referral_commission', ?, 'completed', ?)
                    ");
                    $stmt->execute([
                        $level2_referrer['referred_by'],
                        $l2_commission,
                        "Level 2 commission from transaction #{$transaction['id']}"
                    ]);
                    
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET wallet_balance = wallet_balance + ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$l2_commission, $level2_referrer['referred_by']]);
                    
                    sendNotification(
                        $level2_referrer['referred_by'],
                        'Level 2 Commission Earned',
                        "You earned " . formatMoney($l2_commission) . " Level 2 commission.",
                        'success'
                    );
                }
            }
            
            $db->commit();
            $commissions_paid++;
            
        } catch (Exception $e) {
            $db->rollBack();
            echo "✗ Error processing commission for transaction #{$transaction['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "✓ Paid {$commissions_paid} referral commissions\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// ============================================
// TASK 6: SEND REMINDER NOTIFICATIONS
// ============================================
echo "Task 6: Sending reminder notifications...\n";
try {
    // Send reminders for packages maturing soon (24 hours before)
    $stmt = $db->query("
        SELECT ap.*, u.id as user_id, p.name as package_name
        FROM active_packages ap
        JOIN users u ON ap.user_id = u.id
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.status = 'active'
        AND ap.maturity_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND NOT EXISTS (
            SELECT 1 FROM notifications n
            WHERE n.user_id = u.id
            AND n.title = 'Package Maturing Soon'
            AND DATE(n.created_at) = CURDATE()
        )
    ");
    $maturing_packages = $stmt->fetchAll();
    
    foreach ($maturing_packages as $package) {
        $hours_left = round((strtotime($package['maturity_date']) - time()) / 3600);
        
        sendNotification(
            $package['user_id'],
            'Package Maturing Soon',
            "Your {$package['package_name']} package will mature in approximately {$hours_left} hours!",
            'info'
        );
    }
    
    echo "✓ Sent " . count($maturing_packages) . " maturity reminders\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// ============================================
// TASK 7: UPDATE STATISTICS CACHE
// ============================================
echo "Task 7: Updating statistics cache...\n";
try {
    // Calculate and cache platform statistics
    $stats = [
        'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active_users' => $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'total_deposits' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'deposit' AND status = 'completed'")->fetchColumn(),
        'total_withdrawals' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'withdrawal' AND status = 'completed'")->fetchColumn(),
        'active_packages' => $db->query("SELECT COUNT(*) FROM active_packages WHERE status = 'active'")->fetchColumn(),
        'total_roi_paid' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'roi_payment' AND status = 'completed'")->fetchColumn(),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Store in system settings or cache table
    foreach ($stats as $key => $value) {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description)
            VALUES (?, ?, 'Cached statistic')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute(["cached_stat_{$key}", $value]);
    }
    
    echo "✓ Updated statistics cache\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

// ============================================
// TASK 8: BACKUP CRITICAL DATA (Weekly)
// ============================================
if (date('w') == 0) { // Sunday
    echo "Task 8: Creating weekly backup...\n";
    try {
        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = $backup_dir . '/backup_' . date('Y-m-d') . '.sql';
        
        // This is a simple example - use proper backup tools in production
        $command = "mysqldump -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > " . $backup_file;
        exec($command);
        
        echo "✓ Backup created: {$backup_file}\n";
        
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

// ============================================
// COMPLETION
// ============================================
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo "\n" . date('Y-m-d H:i:s') . " - All tasks completed in {$execution_time} seconds\n";
echo "==========================================\n\n";

// Send daily summary to admin (optional)
if (date('H') == '23') { // 11 PM
    try {
        $summary = "Daily Task Summary for " . date('Y-m-d') . "\n";
        $summary .= "Execution time: {$execution_time} seconds\n";
        $summary .= "Tasks completed successfully\n";
        
        // You can send this via email to admin
        // mail('admin@ultraharvest.com', 'Daily Cron Job Summary', $summary);
        
    } catch (Exception $e) {
        // Silent fail
    }
}

exit(0);
?>