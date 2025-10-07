<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Create user_settings table if it doesn't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            email_notifications TINYINT(1) DEFAULT 1,
            sms_notifications TINYINT(1) DEFAULT 1,
            roi_notifications TINYINT(1) DEFAULT 1,
            transaction_notifications TINYINT(1) DEFAULT 1,
            referral_notifications TINYINT(1) DEFAULT 1,
            profile_visibility VARCHAR(20) DEFAULT 'private',
            show_referral_stats TINYINT(1) DEFAULT 0,
            show_trading_stats TINYINT(1) DEFAULT 0,
            two_factor_enabled TINYINT(1) DEFAULT 0,
            login_notifications TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    error_log("Settings table creation error: " . $e->getMessage());
}

// Handle settings updates
if ($_POST) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'update_notifications':
                    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                    $roi_notifications = isset($_POST['roi_notifications']) ? 1 : 0;
                    $transaction_notifications = isset($_POST['transaction_notifications']) ? 1 : 0;
                    $referral_notifications = isset($_POST['referral_notifications']) ? 1 : 0;
                    
                    // Update or insert settings
                    $stmt = $db->prepare("
                        INSERT INTO user_settings (user_id, email_notifications, sms_notifications, roi_notifications, transaction_notifications, referral_notifications)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        email_notifications = VALUES(email_notifications),
                        sms_notifications = VALUES(sms_notifications),
                        roi_notifications = VALUES(roi_notifications),
                        transaction_notifications = VALUES(transaction_notifications),
                        referral_notifications = VALUES(referral_notifications),
                        updated_at = NOW()
                    ");
                    
                    if ($stmt->execute([$user_id, $email_notifications, $sms_notifications, $roi_notifications, $transaction_notifications, $referral_notifications])) {
                        $success = 'Notification settings updated successfully.';
                    } else {
                        $error = 'Failed to update notification settings.';
                    }
                    break;
                    
                case 'update_privacy':
                    $profile_visibility = sanitize($_POST['profile_visibility'] ?? 'private');
                    $show_referral_stats = isset($_POST['show_referral_stats']) ? 1 : 0;
                    $show_trading_stats = isset($_POST['show_trading_stats']) ? 1 : 0;
                    
                    $stmt = $db->prepare("
                        INSERT INTO user_settings (user_id, profile_visibility, show_referral_stats, show_trading_stats)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        profile_visibility = VALUES(profile_visibility),
                        show_referral_stats = VALUES(show_referral_stats),
                        show_trading_stats = VALUES(show_trading_stats),
                        updated_at = NOW()
                    ");
                    
                    if ($stmt->execute([$user_id, $profile_visibility, $show_referral_stats, $show_trading_stats])) {
                        $success = 'Privacy settings updated successfully.';
                    } else {
                        $error = 'Failed to update privacy settings.';
                    }
                    break;
                    
                case 'update_security':
                    $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
                    $login_notifications = isset($_POST['login_notifications']) ? 1 : 0;
                    
                    $stmt = $db->prepare("
                        INSERT INTO user_settings (user_id, two_factor_enabled, login_notifications)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        two_factor_enabled = VALUES(two_factor_enabled),
                        login_notifications = VALUES(login_notifications),
                        updated_at = NOW()
                    ");
                    
                    if ($stmt->execute([$user_id, $two_factor_enabled, $login_notifications])) {
                        $success = 'Security settings updated successfully.';
                    } else {
                        $error = 'Failed to update security settings.';
                    }
                    break;
                    
                case 'submit_ticket':
                    $subject = sanitize($_POST['subject'] ?? '');
                    $message = sanitize($_POST['message'] ?? '');
                    $priority = sanitize($_POST['priority'] ?? 'medium');
                    
                    if (empty($subject) || empty($message)) {
                        $error = 'Please fill in all required fields.';
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO support_tickets (user_id, subject, message, priority) 
                            VALUES (?, ?, ?, ?)
                        ");
                        if ($stmt->execute([$user_id, $subject, $message, $priority])) {
                            $success = 'Support ticket submitted successfully. We will get back to you soon.';
                            
                            // Send notification
                            sendNotification($user_id, 'Support Ticket Created', "Your support ticket has been created. Ticket ID: " . $db->lastInsertId(), 'info');
                        } else {
                            $error = 'Failed to submit support ticket.';
                        }
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            $error = 'An error occurred while updating settings. Please try again.';
        }
    }
}

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user settings
$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch();

// Default settings if none exist
if (!$settings) {
    $settings = [
        'email_notifications' => 1,
        'sms_notifications' => 1,
        'roi_notifications' => 1,
        'transaction_notifications' => 1,
        'referral_notifications' => 1,
        'profile_visibility' => 'private',
        'show_referral_stats' => 0,
        'show_trading_stats' => 0,
        'two_factor_enabled' => 0,
        'login_notifications' => 1
    ];
}

// Get user's support tickets
$stmt = $db->prepare("
    SELECT st.*, u.full_name as responded_by_name 
    FROM support_tickets st
    LEFT JOIN users u ON st.responded_by = u.id
    WHERE st.user_id = ?
    ORDER BY st.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Ultra Harvest Global</title>
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
        
        .setting-section {
            display: none;
        }
        
        .setting-section.active {
            display: block;
        }
        
        .nav-item.active {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #374151;
            border-radius: 15px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .toggle-switch.active {
            background: #10b981;
        }
        
        .toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        
        .toggle-switch.active .toggle-slider {
            transform: translateX(30px);
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
                        <!--<div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-seedling text-white"></i>
                        </div>
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">
                            Ultra Harvest
                        </span>-->
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <a href="/user/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Support</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">Referrals</a>
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
                <i class="fas fa-cog text-blue-400 mr-3"></i>
                Account Settings
            </h1>
            <p class="text-xl text-gray-300">Manage your preferences and account settings</p>
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

        <div class="grid lg:grid-cols-4 gap-8">
            
            <!-- Settings Navigation -->
            <div class="lg:col-span-1">
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">Settings</h3>
                    <nav class="space-y-2">
                        <button onclick="showSection('notifications')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition active" data-section="notifications">
                            <i class="fas fa-bell mr-3"></i>Notifications
                        </button>
                        <button onclick="showSection('privacy')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="privacy">
                            <i class="fas fa-user-shield mr-3"></i>Privacy
                        </button>
                        <button onclick="showSection('security')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="security">
                            <i class="fas fa-lock mr-3"></i>Security
                        </button>
                        <button onclick="showSection('support')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="support">
                            <i class="fas fa-headset mr-3"></i>Support
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="lg:col-span-3">
                
                <!-- Notification Settings -->
                <div id="notifications-section" class="setting-section active">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Notification Preferences</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <div class="space-y-6">
                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">Email Notifications</h4>
                                        <p class="text-sm text-gray-400">Receive notifications via email</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['email_notifications'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'email_notifications')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="email_notifications" id="email_notifications" 
                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; ?> style="display: none;">
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">SMS Notifications</h4>
                                        <p class="text-sm text-gray-400">Receive notifications via SMS</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['sms_notifications'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'sms_notifications')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="sms_notifications" id="sms_notifications" 
                                           <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?> style="display: none;">
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">ROI Notifications</h4>
                                        <p class="text-sm text-gray-400">Get notified when packages mature</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['roi_notifications'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'roi_notifications')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="roi_notifications" id="roi_notifications" 
                                           <?php echo $settings['roi_notifications'] ? 'checked' : ''; ?> style="display: none;">
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">Transaction Notifications</h4>
                                        <p class="text-sm text-gray-400">Deposits, withdrawals, and payments</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['transaction_notifications'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'transaction_notifications')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="transaction_notifications" id="transaction_notifications" 
                                           <?php echo $settings['transaction_notifications'] ? 'checked' : ''; ?> style="display: none;">
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">Referral Notifications</h4>
                                        <p class="text-sm text-gray-400">New referrals and commission earned</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['referral_notifications'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'referral_notifications')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="referral_notifications" id="referral_notifications" 
                                           <?php echo $settings['referral_notifications'] ? 'checked' : ''; ?> style="display: none;">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                                    <i class="fas fa-save mr-2"></i>Save Notification Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Privacy Settings -->
                <div id="privacy-section" class="setting-section">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Privacy Settings</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_privacy">
                            
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-3">Profile Visibility</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="radio" name="profile_visibility" value="public" 
                                                   <?php echo $settings['profile_visibility'] === 'public' ? 'checked' : ''; ?>
                                                   class="text-emerald-600 focus:ring-emerald-500">
                                            <div>
                                                <span class="text-white font-medium">Public</span>
                                                <p class="text-sm text-gray-400">Your stats are visible to other users</p>
                                            </div>
                                        </label>
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="radio" name="profile_visibility" value="private" 
                                                   <?php echo $settings['profile_visibility'] === 'private' ? 'checked' : ''; ?>
                                                   class="text-emerald-600 focus:ring-emerald-500">
                                            <div>
                                                <span class="text-white font-medium">Private</span>
                                                <p class="text-sm text-gray-400">Keep your profile information private</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">Show Referral Stats</h4>
                                        <p class="text-sm text-gray-400">Display referral statistics publicly</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['show_referral_stats'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'show_referral_stats')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="show_referral_stats" id="show_referral_stats" 
                                           <?php echo $settings['show_referral_stats'] ? 'checked' : ''; ?> style="display: none;">
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">Show Trading Stats</h4>
                                        <p class="text-sm text-gray-400">Display trading statistics publicly</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['show_trading_stats'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'show_trading_stats')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="show_trading_stats" id="show_trading_stats" 
                                           <?php echo $settings['show_trading_stats'] ? 'checked' : ''; ?> style="display: none;">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                                    <i class="fas fa-save mr-2"></i>Save Privacy Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Settings -->
                <div id="security-section" class="setting-section">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Security Settings</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_security">
                            
                            <div class="space-y-6">
                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">Two-Factor Authentication</h4>
                                        <p class="text-sm text-gray-400">Add extra security to your account</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['two_factor_enabled'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'two_factor_enabled')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="two_factor_enabled" id="two_factor_enabled" 
                                           <?php echo $settings['two_factor_enabled'] ? 'checked' : ''; ?> style="display: none;">
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-white">Login Notifications</h4>
                                        <p class="text-sm text-gray-400">Get notified of new login attempts</p>
                                    </div>
                                    <div class="toggle-switch <?php echo $settings['login_notifications'] ? 'active' : ''; ?>" 
                                         onclick="toggleSwitch(this, 'login_notifications')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <input type="checkbox" name="login_notifications" id="login_notifications" 
                                           <?php echo $settings['login_notifications'] ? 'checked' : ''; ?> style="display: none;">
                                </div>

                                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                                    <h4 class="font-bold text-yellow-400 mb-2">Security Tips</h4>
                                    <ul class="text-sm text-gray-300 space-y-1">
                                        <li>• Use a strong, unique password</li>
                                        <li>• Enable two-factor authentication</li>
                                        <li>• Don't share your login credentials</li>
                                        <li>• Log out from public devices</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex space-x-3">
                                <button type="submit" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                                    <i class="fas fa-save mr-2"></i>Save Security Settings
                                </button>
                                <a href="/user/profile.php" class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                                    <i class="fas fa-key mr-2"></i>Change Password
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Support Section -->
                <div id="support-section" class="setting-section">
                    <div class="space-y-6">
                        <!-- Create Support Ticket -->
                        <div class="glass-card rounded-xl p-6">
                            <h3 class="text-xl font-bold text-white mb-6">Create Support Ticket</h3>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="submit_ticket">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Subject *</label>
                                        <input type="text" name="subject" 
                                               class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                               placeholder="Brief description of your issue" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
                                        <select name="priority" class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Message *</label>
                                        <textarea name="message" rows="6"
                                                  class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                                  placeholder="Please describe your issue in detail..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <button type="submit" class="px-6 py-3bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                                        <i class="fas fa-paper-plane mr-2"></i>Submit Ticket
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Support Tickets History -->
                        <div class="glass-card rounded-xl p-6">
                            <h3 class="text-xl font-bold text-white mb-6">Your Support Tickets</h3>
                            
                            <?php if (empty($tickets)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-ticket-alt text-gray-500 text-4xl mb-4"></i>
                                    <p class="text-gray-400">No support tickets found</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($tickets as $ticket): ?>
                                        <div class="p-4 bg-gray-800/50 rounded-lg">
                                            <div class="flex items-start justify-between mb-2">
                                                <h4 class="font-medium text-white"><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                                                <div class="flex items-center space-x-2">
                                                    <span class="px-2 py-1 text-xs rounded-full
                                                        <?php 
                                                        switch($ticket['priority']) {
                                                            case 'urgent': echo 'bg-red-500/20 text-red-300'; break;
                                                            case 'high': echo 'bg-orange-500/20 text-orange-300'; break;
                                                            case 'medium': echo 'bg-yellow-500/20 text-yellow-300'; break;
                                                            case 'low': echo 'bg-green-500/20 text-green-300'; break;
                                                        }
                                                        ?>">
                                                        <?php echo ucfirst($ticket['priority']); ?>
                                                    </span>
                                                    <span class="px-2 py-1 text-xs rounded-full
                                                        <?php 
                                                        switch($ticket['status']) {
                                                            case 'closed': echo 'bg-gray-500/20 text-gray-300'; break;
                                                            case 'resolved': echo 'bg-green-500/20 text-green-300'; break;
                                                            case 'in_progress': echo 'bg-blue-500/20 text-blue-300'; break;
                                                            default: echo 'bg-yellow-500/20 text-yellow-300'; break;
                                                        }
                                                        ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <p class="text-gray-300 text-sm mb-3"><?php echo htmlspecialchars(substr($ticket['message'], 0, 200)) . (strlen($ticket['message']) > 200 ? '...' : ''); ?></p>
                                            
                                            <div class="flex items-center justify-between text-xs text-gray-400">
                                                <div>
                                                    <i class="fas fa-calendar mr-1"></i>
                                                    Created: <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                                </div>
                                                <?php if ($ticket['responded_by']): ?>
                                                    <div>
                                                        <i class="fas fa-user mr-1"></i>
                                                        Handled by: <?php echo htmlspecialchars($ticket['responded_by_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($ticket['response']): ?>
                                                <div class="mt-3 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded">
                                                    <p class="text-emerald-300 text-sm font-medium mb-1">Response:</p>
                                                    <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($ticket['response']); ?></p>
                                                    <?php if ($ticket['responded_at']): ?>
                                                        <p class="text-xs text-gray-400 mt-2">
                                                            <i class="fas fa-clock mr-1"></i>
                                                            <?php echo date('M j, Y g:i A', strtotime($ticket['responded_at'])); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contact Information -->
                        <div class="glass-card rounded-xl p-6">
                            <h3 class="text-xl font-bold text-white mb-4">Other Ways to Reach Us</h3>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-800/50 rounded-lg">
                                    <h4 class="font-medium text-emerald-400 mb-2">
                                        <i class="fas fa-envelope mr-2"></i>Email Support
                                    </h4>
                                    <p class="text-gray-300 text-sm">support@ultraharvest.com</p>
                                    <p class="text-gray-400 text-xs">Response within 24 hours</p>
                                </div>
                                
                                <div class="p-4 bg-gray-800/50 rounded-lg">
                                    <h4 class="font-medium text-blue-400 mb-2">
                                        <i class="fas fa-comments mr-2"></i>Live Chat
                                    </h4>
                                    <p class="text-gray-300 text-sm">Available 24/7</p>
                                    <p class="text-gray-400 text-xs">Click the chat icon</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.setting-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Add active class to clicked nav item
            document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');
        }
        
        function toggleSwitch(element, inputName) {
            element.classList.toggle('active');
            const checkbox = document.getElementById(inputName);
            checkbox.checked = element.classList.contains('active');
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Show notifications section by default
            showSection('notifications');
        });
    </script>
</body>
</html>