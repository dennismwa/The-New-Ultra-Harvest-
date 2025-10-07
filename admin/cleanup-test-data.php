<?php
require_once '../config/database.php';
requireAdmin();

$success = '';
$error = '';

// Security: Only allow this on local/development
$allowed_ips = ['127.0.0.1', '::1']; // Add your IP if needed
$is_allowed = in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips) || isset($_GET['confirm_cleanup']);

if ($_POST && isset($_POST['cleanup_confirm'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $cleanup_password = $_POST['cleanup_password'] ?? '';
        
        // Set a cleanup password for extra security
        if ($cleanup_password !== 'CLEANUP2025') {
            $error = 'Invalid cleanup password';
        } else {
            try {
                $db->beginTransaction();
                
                // Get counts before cleanup
                $stmt = $db->query("SELECT COUNT(*) as count FROM transactions");
                $txn_count = $stmt->fetch()['count'];
                
                $stmt = $db->query("SELECT COUNT(*) as count FROM notifications");
                $notif_count = $stmt->fetch()['count'];
                
                $stmt = $db->query("SELECT COUNT(*) as count FROM active_packages");
                $pkg_count = $stmt->fetch()['count'];
                
                // Delete all transactions
                $db->exec("DELETE FROM transactions WHERE id > 0");
                $db->exec("ALTER TABLE transactions AUTO_INCREMENT = 1");
                
                // Delete all notifications
                $db->exec("DELETE FROM notifications WHERE id > 0");
                $db->exec("ALTER TABLE notifications AUTO_INCREMENT = 1");
                
                // Delete all active packages
                $db->exec("DELETE FROM active_packages WHERE id > 0");
                $db->exec("ALTER TABLE active_packages AUTO_INCREMENT = 1");
                
                // Reset user balances (non-admin users only)
                $db->exec("
                    UPDATE users SET 
                        wallet_balance = 0, 
                        total_deposited = 0, 
                        total_withdrawn = 0, 
                        referral_earnings = 0,
                        updated_at = NOW() 
                    WHERE is_admin = 0
                ");
                
                // Optional: Reset admin balances too
                if (isset($_POST['reset_admin_wallets'])) {
                    $db->exec("
                        UPDATE users SET 
                            wallet_balance = 0, 
                            total_deposited = 0, 
                            total_withdrawn = 0, 
                            referral_earnings = 0,
                            updated_at = NOW() 
                        WHERE is_admin = 1
                    ");
                }
                
                $db->commit();
                
                $success = "✅ Cleanup Successful!<br>
                    - Deleted $txn_count transactions<br>
                    - Deleted $notif_count notifications<br>
                    - Deleted $pkg_count active packages<br>
                    - Reset all user wallet balances<br>
                    - Reset all auto-increment IDs";
                
                // Log the cleanup
                error_log("ADMIN CLEANUP: " . $_SESSION['full_name'] . " cleaned up test data at " . date('Y-m-d H:i:s'));
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Cleanup failed: ' . $e->getMessage();
                error_log("Cleanup error: " . $e->getMessage());
            }
        }
    }
}

// Get current counts
$counts = [];
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM transactions");
    $counts['transactions'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM notifications");
    $counts['notifications'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM active_packages");
    $counts['packages'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count, SUM(wallet_balance) as total_balance FROM users WHERE is_admin = 0");
    $user_stats = $stmt->fetch();
    $counts['users'] = $user_stats['count'];
    $counts['total_balance'] = $user_stats['total_balance'] ?? 0;
} catch (Exception $e) {
    $error = 'Failed to get counts: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Test Data - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                        <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div>
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                        <p class="text-xs text-gray-400">Data Cleanup</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/admin/" class="text-gray-400 hover:text-white">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-4xl">
        
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-red-400 mb-2">
                <i class="fas fa-exclamation-triangle mr-3"></i>
                Cleanup Test Data
            </h1>
            <p class="text-xl text-gray-300">⚠️ This action is IRREVERSIBLE!</p>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-6 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
            <div class="flex items-center mb-2">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300 font-bold">Cleanup Successful!</span>
            </div>
            <div class="text-emerald-300 text-sm mt-2">
                <?php echo $success; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Current Stats -->
        <div class="bg-gray-800/50 backdrop-blur-md border border-gray-700 rounded-xl p-6 mb-8">
            <h2 class="text-2xl font-bold text-white mb-4">Current Database Status</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                    <p class="text-gray-400 text-sm">Transactions</p>
                    <p class="text-3xl font-bold text-white"><?php echo number_format($counts['transactions'] ?? 0); ?></p>
                </div>
                <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                    <p class="text-gray-400 text-sm">Notifications</p>
                    <p class="text-3xl font-bold text-white"><?php echo number_format($counts['notifications'] ?? 0); ?></p>
                </div>
                <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                    <p class="text-gray-400 text-sm">Active Packages</p>
                    <p class="text-3xl font-bold text-white"><?php echo number_format($counts['packages'] ?? 0); ?></p>
                </div>
            </div>
            <div class="mt-4 bg-yellow-500/20 border border-yellow-500/50 rounded-lg p-4">
                <p class="text-yellow-300 text-sm">
                    <i class="fas fa-users mr-2"></i>
                    <strong><?php echo number_format($counts['users'] ?? 0); ?></strong> users with total balance: 
                    <strong><?php echo formatMoney($counts['total_balance'] ?? 0); ?></strong>
                </p>
            </div>
        </div>

        <!-- Cleanup Form -->
        <div class="bg-red-900/20 border border-red-500/50 rounded-xl p-8">
            <h3 class="text-2xl font-bold text-red-400 mb-4">
                <i class="fas fa-trash-alt mr-2"></i>
                Cleanup Actions
            </h3>
            
            <div class="bg-yellow-500/20 border border-yellow-500/50 rounded-lg p-4 mb-6">
                <h4 class="font-bold text-yellow-400 mb-2">⚠️ Warning - This will:</h4>
                <ul class="text-sm text-gray-300 space-y-1">
                    <li>✓ Delete ALL transactions (deposits, withdrawals, investments, ROI payments)</li>
                    <li>✓ Delete ALL notifications</li>
                    <li>✓ Delete ALL active packages</li>
                    <li>✓ Reset ALL user wallet balances to zero</li>
                    <li>✓ Reset ALL auto-increment IDs to 1</li>
                    <li>✓ This action CANNOT be undone!</li>
                </ul>
            </div>

            <form method="POST" onsubmit="return confirm('⚠️ ARE YOU ABSOLUTELY SURE?\n\nThis will DELETE ALL test data permanently!\n\nType YES in the confirmation box to proceed.');">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="cleanup_confirm" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Cleanup Password *</label>
                        <input 
                            type="text" 
                            name="cleanup_password" 
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-red-500 focus:outline-none"
                            placeholder="Enter cleanup password"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-1">Password: <code class="bg-gray-700 px-2 py-1 rounded">CLEANUP2025</code></p>
                    </div>
                    
                    <div>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="reset_admin_wallets"
                                class="w-5 h-5 text-red-600 bg-gray-800 border-gray-600 rounded focus:ring-red-500 focus:ring-2"
                            >
                            <div>
                                <span class="text-white font-medium">Also reset admin wallets</span>
                                <p class="text-sm text-gray-400">Reset balances for admin accounts too</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-trash-alt mr-2"></i>Delete All Test Data
                    </button>
                    <a href="/admin/" class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Instructions -->
        <div class="mt-8 bg-blue-900/20 border border-blue-500/50 rounded-xl p-6">
            <h3 class="text-lg font-bold text-blue-400 mb-3">
                <i class="fas fa-info-circle mr-2"></i>
                After Cleanup
            </h3>
            <ul class="text-sm text-gray-300 space-y-2">
                <li>1. All transaction IDs will restart from #1</li>
                <li>2. All user balances will be KSh 0.00</li>
                <li>3. System will be ready for production use</li>
                <li>4. Delete this cleanup file after use for security</li>
            </ul>
        </div>

    </main>

</body>
</html>