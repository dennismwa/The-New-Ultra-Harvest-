<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle profile update
if ($_POST && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        
        if (empty($full_name) || empty($email) || empty($phone)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $user_id]);
                $success = 'Profile updated successfully!';
            } catch (Exception $e) {
                $error = 'Failed to update profile. Email may already be in use.';
            }
        }
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();
            
            if (password_verify($current_password, $user_data['password'])) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                $success = 'Password changed successfully!';
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
}

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get transaction history for this user
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Get transaction statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_withdrawals,
        COALESCE(SUM(CASE WHEN type = 'roi_payment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_roi
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile & History - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .tab-btn {
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="/user/dashboard.php" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Trade</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">Network</a>
                        <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                        <i class="fas fa-home text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">
                <i class="fas fa-user-circle text-blue-400 mr-3"></i>
                Profile & History
            </h1>
            <p class="text-xl text-gray-300">Manage your account and view transaction history</p>
        </div>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg max-w-2xl mx-auto">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg max-w-2xl mx-auto">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300"><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="flex justify-center mb-8">
            <div class="glass-card rounded-xl p-2 inline-flex space-x-2">
                <button onclick="showTab('profile')" class="tab-btn px-6 py-3 rounded-lg font-medium active" data-tab="profile">
                    <i class="fas fa-user mr-2"></i>Profile
                </button>
                <button onclick="showTab('history')" class="tab-btn px-6 py-3 rounded-lg font-medium" data-tab="history">
                    <i class="fas fa-history mr-2"></i>History
                </button>
                <button onclick="showTab('security')" class="tab-btn px-6 py-3 rounded-lg font-medium" data-tab="security">
                    <i class="fas fa-lock mr-2"></i>Security
                </button>
            </div>
        </div>

        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content active">
            <div class="max-w-2xl mx-auto">
                <div class="glass-card rounded-xl p-8">
                    <h2 class="text-2xl font-bold text-white mb-6">Personal Information</h2>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                   required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                   required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                   required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Referral Code</label>
                            <div class="flex items-center space-x-2">
                                <input type="text" value="<?php echo htmlspecialchars($user['referral_code']); ?>"
                                       class="flex-1 px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white"
                                       readonly>
                                <button type="button" onclick="copyReferralCode()" class="px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Member Since</label>
                            <input type="text" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>"
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-gray-400"
                                   readonly>
                        </div>
                        
                        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-save mr-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div id="history-tab" class="tab-content">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-2xl font-bold text-white mb-6">Transaction History</h2>
                
                <!-- Transaction Statistics -->
                <div class="grid md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gray-800/50 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Total Deposits</p>
                                <p class="text-2xl font-bold text-emerald-400"><?php echo formatMoney($stats['total_deposits']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-arrow-down text-emerald-400 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800/50 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Total Withdrawn</p>
                                <p class="text-2xl font-bold text-red-400"><?php echo formatMoney($stats['total_withdrawals']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-arrow-up text-red-400 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800/50 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Total ROI Earned</p>
                                <p class="text-2xl font-bold text-yellow-400"><?php echo formatMoney($stats['total_roi']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-coins text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions List -->
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-receipt text-6xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-400 mb-2">No transactions yet</h3>
                        <p class="text-gray-500">Your transaction history will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($transactions as $transaction): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-800/30 rounded-lg hover:bg-gray-800/50 transition">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center
                                    <?php 
                                    echo match($transaction['type']) {
                                        'deposit' => 'bg-emerald-500/20 text-emerald-400',
                                        'withdrawal' => 'bg-red-500/20 text-red-400',
                                        'roi_payment' => 'bg-yellow-500/20 text-yellow-400',
                                        'package_investment' => 'bg-blue-500/20 text-blue-400',
                                        'referral_commission' => 'bg-purple-500/20 text-purple-400',
                                        default => 'bg-gray-500/20 text-gray-400'
                                    };
                                    ?>">
                                    <i class="fas <?php 
                                    echo match($transaction['type']) {
                                        'deposit' => 'fa-arrow-down',
                                        'withdrawal' => 'fa-arrow-up',
                                        'roi_payment' => 'fa-coins',
                                        'package_investment' => 'fa-chart-line',
                                        'referral_commission' => 'fa-users',
                                        default => 'fa-exchange-alt'
                                    };
                                    ?>"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white capitalize">
                                        <?php echo str_replace('_', ' ', $transaction['type']); ?>
                                    </p>
                                    <p class="text-sm text-gray-400"><?php echo timeAgo($transaction['created_at']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-white"><?php echo formatMoney($transaction['amount']); ?></p>
                                <p class="text-sm <?php 
                                echo match($transaction['status']) {
                                    'completed' => 'text-emerald-400',
                                    'pending' => 'text-yellow-400',
                                    'failed' => 'text-red-400',
                                    default => 'text-gray-400'
                                };
                                ?>"><?php echo ucfirst($transaction['status']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-6 text-center">
                        <a href="/user/transactions.php" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition inline-block">
                            <i class="fas fa-list mr-2"></i>View All Transactions
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="tab-content">
            <div class="max-w-2xl mx-auto">
                <div class="glass-card rounded-xl p-8">
                    <h2 class="text-2xl font-bold text-white mb-6">Change Password</h2>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Current Password</label>
                            <input type="password" name="current_password"
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                   required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">New Password</label>
                            <input type="password" name="new_password" minlength="6"
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                   required>
                            <p class="text-sm text-gray-500 mt-1">Minimum 6 characters</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="6"
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                   required>
                        </div>
                        
                        <button type="submit" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </button>
                    </form>
                </div>

                <!-- Account Settings Link -->
                <div class="glass-card rounded-xl p-6 mt-6">
                    <h3 class="text-lg font-bold text-white mb-4">More Settings</h3>
                    <a href="/user/settings.php" class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-cog text-gray-400"></i>
                            <span class="text-white">Advanced Settings</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden z-40">
        <div class="grid grid-cols-5 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="/user/packages.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Trade</span>
            </a>
            <a href="/user/referrals.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-users text-xl mb-1"></i>
                <span class="text-xs">Network</span>
            </a>
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-briefcase text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/support.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-headset text-xl mb-1"></i>
                <span class="text-xs">Help</span>
            </a>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        }

        function copyReferralCode() {
            const code = '<?php echo $user['referral_code']; ?>';
            navigator.clipboard.writeText(code).then(function() {
                alert('Referral code copied to clipboard!');
            }).catch(function() {
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Referral code copied to clipboard!');
            });
        }
    </script>
</body>
</html>