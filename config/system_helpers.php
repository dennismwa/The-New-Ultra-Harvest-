<?php
/**
 * ULTRA HARVEST GLOBAL - SYSTEM HELPER FUNCTIONS
 * Additional helper functions for the new features
 */

/**
 * Process Withdrawal with Fees
 * Automatically calculates and applies withdrawal fees based on user's package tier
 */
function processWithdrawalWithFees($db, $transaction_id, $user_id, $amount) {
    try {
        // Get user's current package tier
        $stmt = $db->prepare("
            SELECT p.name 
            FROM active_packages ap 
            JOIN packages p ON ap.package_id = p.id 
            WHERE ap.user_id = ? AND ap.status = 'active' 
            ORDER BY ap.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $package = $stmt->fetch();
        
        // Withdrawal fee structure
        $withdrawal_fees = [
            'Seed' => 7,
            'Sprout' => 6,
            'Growth' => 5,
            'Harvest' => 5,
            'Golden Yield' => 4,
            'Elite' => 3
        ];
        
        // Default to highest fee if no active package
        $fee_percentage = 7;
        if ($package && isset($withdrawal_fees[$package['name']])) {
            $fee_percentage = $withdrawal_fees[$package['name']];
        }
        
        // Call stored procedure to process fees
        $stmt = $db->prepare("CALL process_withdrawal_fees(?, ?, ?)");
        $stmt->execute([$transaction_id, $amount, $fee_percentage]);
        
        return [
            'success' => true,
            'fee_percentage' => $fee_percentage,
            'message' => 'Withdrawal processed successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Withdrawal fee processing error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to process withdrawal fees'
        ];
    }
}

/**
 * Check if user can activate new package
 * Prevents activation if user has matured packages not yet withdrawn
 */
function canActivatePackage($db, $user_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as matured_count
        FROM active_packages
        WHERE user_id = ? 
        AND status = 'completed'
        AND withdrawn_at IS NULL
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    return $result['matured_count'] == 0;
}

/**
 * Get matured packages requiring withdrawal
 */
function getMaturedPackagesRequiringWithdrawal($db, $user_id) {
    $stmt = $db->prepare("
        SELECT ap.*, p.name as package_name, p.icon
        FROM active_packages ap
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.user_id = ? 
        AND ap.status = 'completed'
        AND ap.withdrawn_at IS NULL
        ORDER BY ap.completed_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Mark package as withdrawn
 */
function markPackageAsWithdrawn($db, $package_id, $user_id) {
    $stmt = $db->prepare("
        UPDATE active_packages 
        SET withdrawn_at = NOW(), can_reinvest = 1 
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$package_id, $user_id]);
}

/**
 * Get Admin Wallet Balance
 */
function getAdminWalletBalance($db) {
    $stmt = $db->query("SELECT * FROM admin_wallet LIMIT 1");
    return $stmt->fetch();
}

/**
 * Admin Wallet Withdrawal
 * Allow admin to withdraw from admin wallet
 */
function adminWalletWithdraw($db, $admin_id, $amount, $description = '') {
    try {
        $db->beginTransaction();
        
        // Check admin wallet balance
        $wallet = getAdminWalletBalance($db);
        if ($wallet['balance'] < $amount) {
            throw new Exception('Insufficient admin wallet balance');
        }
        
        // Deduct from admin wallet
        $stmt = $db->prepare("
            UPDATE admin_wallet 
            SET balance = balance - ?
        ");
        $stmt->execute([$amount]);
        
        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO admin_wallet_transactions (type, amount, description, admin_id)
            VALUES ('withdrawal', ?, ?, ?)
        ");
        $stmt->execute([$amount, $description, $admin_id]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Withdrawal successful'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Admin Wallet Injection
 * Allow admin to inject money to business float
 */
function adminWalletInject($db, $admin_id, $amount, $description = '') {
    try {
        $db->beginTransaction();
        
        // Add to admin wallet
        $stmt = $db->prepare("
            UPDATE admin_wallet 
            SET balance = balance + ?
        ");
        $stmt->execute([$amount]);
        
        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO admin_wallet_transactions (type, amount, description, admin_id)
            VALUES ('injection', ?, ?, ?)
        ");
        $stmt->execute([$amount, $description, $admin_id]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Injection successful'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get Coverage Ratio
 */
function getCoverageRatio($db) {
    $stmt = $db->query("SELECT * FROM coverage_ratio_view");
    return $stmt->fetch();
}

/**
 * Get User Growth Statistics
 */
function getUserGrowthStats($db, $days = 30) {
    $stmt = $db->prepare("
        SELECT * FROM user_growth_stats 
        ORDER BY date DESC 
        LIMIT ?
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

/**
 * Update Daily User Growth Stats
 * Should be called by cron job daily
 */
function updateDailyUserGrowthStats($db) {
    try {
        $stmt = $db->query("CALL update_user_growth_stats()");
        return ['success' => true, 'message' => 'User growth stats updated'];
    } catch (Exception $e) {
        error_log("User growth stats update error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get Withdrawal Fee Statistics
 */
function getWithdrawalFeeStats($db, $days = 30) {
    $stmt = $db->prepare("
        SELECT * FROM withdrawal_fee_stats 
        ORDER BY date DESC 
        LIMIT ?
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

/**
 * Get Today's Withdrawal Fee Stats
 */
function getTodayWithdrawalFeeStats($db) {
    $stmt = $db->query("
        SELECT * FROM withdrawal_fee_stats 
        WHERE date = CURDATE()
    ");
    return $stmt->fetch();
}

/**
 * Calculate Package Maturity Date Correctly
 * Fixes the time calculation issue
 */
function calculatePackageMaturity($package_duration_hours) {
    // Use exact hours for calculation
    return date('Y-m-d H:i:s', strtotime("+{$package_duration_hours} hours"));
}

/**
 * Get Package Display Duration
 */
function getPackageDisplayDuration($hours) {
    if ($hours < 24) {
        return $hours . ' hours';
    } elseif ($hours == 24) {
        return '1 day (24 hours)';
    } elseif ($hours == 48) {
        return '2 days (48 hours)';
    } elseif ($hours == 72) {
        return '3 days (72 hours)';
    } elseif ($hours == 168) {
        return '7 days (1 week)';
    } else {
        $days = floor($hours / 24);
        return $days . ' days (' . $hours . ' hours)';
    }
}

/**
 * Check Referral Eligibility
 * Ensures referral code is valid and not self-referral
 */
function validateReferralCode($db, $referral_code, $new_user_email) {
    if (empty($referral_code)) {
        return ['valid' => false, 'message' => 'No referral code provided'];
    }
    
    $stmt = $db->prepare("
        SELECT id, email, full_name 
        FROM users 
        WHERE referral_code = ? AND status = 'active'
    ");
    $stmt->execute([$referral_code]);
    $referrer = $stmt->fetch();
    
    if (!$referrer) {
        return ['valid' => false, 'message' => 'Invalid referral code'];
    }
    
    if ($referrer['email'] === $new_user_email) {
        return ['valid' => false, 'message' => 'Cannot refer yourself'];
    }
    
    return [
        'valid' => true,
        'referrer_id' => $referrer['id'],
        'referrer_name' => $referrer['full_name']
    ];
}

/**
 * Calculate Net Position (Withdrawal fees + Platform fees)
 */
function calculateNetPosition($db) {
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(withdrawal_fee), 0) as total_withdrawal_fees,
            COALESCE(SUM(platform_fee), 0) as total_platform_fees,
            COALESCE(SUM(withdrawal_fee + platform_fee), 0) as net_position
        FROM transactions
        WHERE type = 'withdrawal' AND status = 'completed'
    ");
    return $stmt->fetch();
}

/**
 * Get Package-wise Withdrawal Fee Breakdown
 */
function getPackageWiseWithdrawalFees($db, $start_date = null, $end_date = null) {
    $sql = "
        SELECT 
            p.name as package_name,
            COUNT(t.id) as withdrawal_count,
            COALESCE(SUM(t.amount), 0) as total_amount,
            COALESCE(SUM(t.withdrawal_fee), 0) as total_fees
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN active_packages ap ON ap.user_id = u.id AND ap.status = 'active'
        LEFT JOIN packages p ON ap.package_id = p.id
        WHERE t.type = 'withdrawal' AND t.status = 'completed'
    ";
    
    if ($start_date && $end_date) {
        $sql .= " AND DATE(t.created_at) BETWEEN ? AND ?";
        $stmt = $db->prepare($sql . " GROUP BY p.name");
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt = $db->query($sql . " GROUP BY p.name");
    }
    
    return $stmt->fetchAll();
}

/**
 * Auto-complete matured packages
 * Should be run by cron job
 */
function autoCompleteMaturedPackages($db) {
    try {
        $db->beginTransaction();
        
        // Find matured packages
        $stmt = $db->query("
            SELECT ap.*, u.id as user_id
            FROM active_packages ap
            JOIN users u ON ap.user_id = u.id
            WHERE ap.status = 'active' 
            AND ap.maturity_date <= NOW()
        ");
        $matured_packages = $stmt->fetchAll();
        
        foreach ($matured_packages as $package) {
            // Calculate total return (investment + ROI)
            $total_return = $package['investment_amount'] + $package['expected_roi'];
            
            // Credit user wallet
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ? 
                WHERE id = ?
            ");
            $stmt->execute([$total_return, $package['user_id']]);
            
            // Mark package as completed
            $stmt = $db->prepare("
                UPDATE active_packages 
                SET status = 'completed', completed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$package['id']]);
            
            // Create ROI payment transaction
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description)
                VALUES (?, 'roi_payment', ?, 'completed', ?)
            ");
            $stmt->execute([
                $package['user_id'],
                $package['expected_roi'],
                "ROI payment from package #{$package['id']}"
            ]);
            
            // Send notification
            sendNotification(
                $package['user_id'],
                'Package Matured!',
                "Your package has matured. " . formatMoney($total_return) . " has been credited to your wallet.",
                'success'
            );
        }
        
        $db->commit();
        return [
            'success' => true,
            'packages_completed' => count($matured_packages),
            'message' => count($matured_packages) . ' packages completed'
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Auto-complete packages error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get withdrawal processing time based on amount
 */
function getWithdrawalProcessingTime($amount) {
    $threshold = (float)getSystemSetting('instant_withdrawal_threshold', 10000);
    
    if ($amount < $threshold) {
        return 'About 1 hour'; // As per client requirement
    } else {
        return 'About 1 hour'; // All withdrawals now same time as per document
    }
}

/**
 * Validate package activation
 * Returns error message if activation should be blocked
 */
function validatePackageActivation($db, $user_id, $package_id, $amount) {
    // Check for matured packages
    if (!canActivatePackage($db, $user_id)) {
        $matured = getMaturedPackagesRequiringWithdrawal($db, $user_id);
        return [
            'can_activate' => false,
            'message' => 'You have ' . count($matured) . ' matured package(s) that must be withdrawn before activating a new package.',
            'matured_packages' => $matured
        ];
    }
    
    // Check wallet balance
    $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user['wallet_balance'] < $amount) {
        return [
            'can_activate' => false,
            'message' => 'Insufficient wallet balance. Please deposit funds first.',
            'required' => $amount,
            'available' => $user['wallet_balance']
        ];
    }
    
    // Check package limits
    $stmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch();
    
    if (!$package) {
        return [
            'can_activate' => false,
            'message' => 'Invalid package selected.'
        ];
    }
    
    if ($amount < $package['min_investment']) {
        return [
            'can_activate' => false,
            'message' => 'Amount below minimum investment for this package.',
            'minimum' => $package['min_investment']
        ];
    }
    
    if ($package['max_investment'] && $amount > $package['max_investment']) {
        return [
            'can_activate' => false,
            'message' => 'Amount exceeds maximum investment for this package.',
            'maximum' => $package['max_investment']
        ];
    }
    
    return [
        'can_activate' => true,
        'message' => 'Package can be activated',
        'package' => $package
    ];
}

// Include this file in your config/database.php after other helper functions
?>