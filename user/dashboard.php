<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("
    SELECT u.*, 
           COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_deposited,
           COALESCE(SUM(CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_withdrawn,
           COALESCE(SUM(CASE WHEN t.type = 'roi_payment' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_roi_earned
    FROM users u 
    LEFT JOIN transactions t ON u.id = t.user_id 
    WHERE u.id = ? 
    GROUP BY u.id
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get active package
$stmt = $db->prepare("
    SELECT ap.*, p.name as package_name, p.icon 
    FROM active_packages ap 
    JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'active' 
    ORDER BY ap.created_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$active_package = $stmt->fetch();

// Get recent user activity (for live feed)
$stmt = $db->query("
    SELECT u.full_name, t.type, t.amount, t.created_at,
           CASE 
               WHEN t.type = 'deposit' THEN 'deposited'
               WHEN t.type = 'withdrawal' THEN 'withdrew'
               WHEN t.type = 'roi_payment' THEN 'completed'
               WHEN t.type = 'package_investment' THEN 'activated'
           END as action_text
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'completed' AND t.type IN ('deposit', 'withdrawal', 'roi_payment', 'package_investment')
    ORDER BY t.created_at DESC 
    LIMIT 20
");
$live_activity = $stmt->fetchAll();

// Get notifications
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE (user_id = ? OR is_global = 1) AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #10b981 0%, #fbbf24 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Forex chart background */
        .forex-bg {
            background-image: url('/Trading.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
        
        .forex-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.85) 0%, rgba(251, 191, 36, 0.85) 100%);
        }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .live-feed {
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .pulse-dot {
            animation: pulse-dot 2s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .status-green { color: #10b981; }
        .status-yellow { color: #f59e0b; }
        .status-red { color: #ef4444; }
        
        .notification-dropdown {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 640px) {
            .notification-dropdown {
                position: fixed !important;
                top: 70px !important;
                left: 16px !important;
                right: 16px !important;
                width: auto !important;
                margin: 0 !important;
                max-height: calc(100vh - 100px) !important;
            }
        }
        
        /* Live activity ticker styles */
        .ticker-wrap {
            width: 100%;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.3);
            padding: 12px 0;
            border-radius: 8px;
        }
        
        .ticker {
            display: flex;
            animation: ticker 30s linear infinite;
            white-space: nowrap;
        }
        
        .ticker:hover {
            animation-play-state: paused;
        }
        
        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        .ticker-item {
            display: inline-flex;
            align-items: center;
            padding: 0 30px;
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo & Navigation -->
                <div class="flex items-center space-x-8">
                    <a href="/user/dashboard.php" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                    </a>
                    
                    <!-- Updated Navigation Order: HOME > TRADE > NETWORK > ACTIVE TRADES > HELP -->
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-emerald-400 font-medium">HOME</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">TRADE</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">NETWORK</a>
                        <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">ACTIVE TRADES</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">HELP</a>
                    </nav>
                </div>

                <!-- User Info & Actions -->
                <div class="flex items-center space-x-4">
                    <!-- Wallet Balance -->
                    <div class="hidden lg:flex items-center space-x-4 bg-gray-700/50 rounded-full px-4 py-2">
                        <i class="fas fa-wallet text-emerald-400"></i>
                        <span class="text-sm text-gray-300">Balance:</span>
                        <span class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></span>
                    </div>

                    <!-- Notifications Bell -->
                    <div class="notification-container relative">
                        <button id="notificationBell" class="notification-bell relative p-2 text-gray-400 hover:text-white transition-colors duration-200">
                            <i class="fas fa-bell text-xl"></i>
                            <span id="notificationBadge" class="notification-badge absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-xs flex items-center justify-center text-white font-bold <?php echo count($notifications) > 0 ? '' : 'hidden'; ?>">
                                <?php echo count($notifications) > 99 ? '99+' : count($notifications); ?>
                            </span>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div id="notificationDropdown" class="notification-dropdown absolute right-0 top-full mt-2 w-80 md:w-80 sm:w-screen sm:right-0 sm:left-0 sm:mx-4 sm:mt-2 bg-gray-800 rounded-xl shadow-2xl border border-gray-700 hidden z-50 max-h-96 overflow-y-auto">
                            <div class="p-4 border-b border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-white">Notifications</h3>
                                    <button id="closeNotifications" class="text-gray-400 hover:text-white md:hidden">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                <?php if (empty($notifications)): ?>
                                    <div class="p-8 text-center">
                                        <i class="fas fa-bell-slash text-3xl text-gray-600 mb-3"></i>
                                        <p class="text-gray-400">No notifications</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                    <div class="p-4 border-b border-gray-700 last:border-b-0 <?php echo !$notification['is_read'] ? 'bg-gray-700/30' : ''; ?>">
                                        <div class="flex items-start space-x-3">
                                            <i class="fas <?php 
                                            echo match($notification['type']) {
                                                'success' => 'fa-check-circle text-emerald-400',
                                                'warning' => 'fa-exclamation-triangle text-yellow-400',
                                                'error' => 'fa-exclamation-circle text-red-400',
                                                default => 'fa-info-circle text-blue-400'
                                            };
                                            ?> mt-1"></i>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-medium text-white"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                <p class="text-sm text-gray-300 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <p class="text-xs text-gray-500 mt-2"><?php echo timeAgo($notification['created_at']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 border-t border-gray-700">
                                <a href="/user/notifications.php" class="text-emerald-400 hover:text-emerald-300 text-sm flex items-center justify-center">
                                    View All Notifications <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="relative group">
                        <button class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-3 py-2 hover:bg-gray-600/50 transition">
                            <div class="w-8 h-8 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="hidden md:block text-sm"><?php echo htmlspecialchars($user['full_name']); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 top-full mt-2 w-48 bg-gray-800 rounded-lg shadow-xl border border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                            <div class="py-2">
                                <a href="/user/profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="fas fa-user mr-2"></i>Profile & History
                                </a>
                                <a href="/user/settings.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                                <a href="/user/notifications.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="fas fa-bell mr-2"></i>All Notifications
                                </a>
                                <div class="border-t border-gray-700"></div>
                                <a href="/logout.php" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button class="md:hidden p-2 text-gray-400 hover:text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        
        <!-- Hero Section with Forex Background - 20% smaller welcome message -->
        <section class="forex-bg rounded-2xl p-6 lg:p-8 mb-8 relative overflow-hidden">
            <div class="relative z-10">
                <div class="grid lg:grid-cols-3 gap-6 items-center">
                    <!-- Balance Info -->
                    <div class="lg:col-span-2">
                        <!-- Welcome message 20% smaller -->
                        <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">
                            Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>
                        </h1>
                        <div class="grid md:grid-cols-3 gap-4 mt-6">
                            <div class="text-center md:text-left">
                                <p class="text-white/80 text-sm">Wallet Balance</p>
                                <p class="text-2xl lg:text-3xl font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></p>
                            </div>
                            <div class="text-center md:text-left">
                                <p class="text-white/80 text-sm">ROI Earned</p>
                                <p class="text-xl lg:text-2xl font-bold text-white"><?php echo formatMoney($user['total_roi_earned']); ?></p>
                            </div>
                            <?php if ($active_package): ?>
                            <div class="text-center md:text-left">
                                <p class="text-white/80 text-sm">Active Package</p>
                                <p class="text-lg font-bold text-white">
                                    <?php echo $active_package['icon']; ?> <?php echo $active_package['package_name']; ?>
                                </p>
                                <p class="text-sm text-white/70">ROI: <?php echo $active_package['roi_percentage']; ?>%</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Primary Actions - Updated button colors -->
                    <div class="flex flex-col space-y-3">
                        <!-- Deposit Funds - RED -->
                        <a href="/user/deposit.php" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold text-center transition">
                            <i class="fas fa-plus mr-2"></i>Deposit Funds
                        </a>
                        <!-- Withdraw Funds - GREEN -->
                        <a href="/user/withdraw.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold text-center transition">
                            <i class="fas fa-arrow-up mr-2"></i>Withdraw Funds
                        </a>
                        <!-- ACTIVATE TRADE NOW - HOT ORANGE -->
                        <a href="/user/packages.php" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded-lg font-semibold text-center transition pulse-glow">
                            <i class="fas fa-rocket mr-2"></i>ACTIVATE TRADE NOW
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Live Activity Feed (Ticker) - Above fold -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-4">
                <div class="flex items-center mb-3">
                    <div class="w-3 h-3 bg-emerald-500 rounded-full pulse-dot mr-3"></div>
                    <h2 class="text-lg font-bold text-white">Live Activity Feed</h2>
                </div>
                
                <div class="ticker-wrap">
                    <div class="ticker">
                        <?php 
                        // Duplicate array for seamless loop
                        $activities = array_merge($live_activity, $live_activity);
                        foreach ($activities as $activity): 
                        ?>
                        <div class="ticker-item">
                            <div class="flex items-center space-x-2">
                                <div class="w-6 h-6 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center text-xs font-bold">
                                    <?php echo strtoupper(substr($activity['full_name'], 0, 1)); ?>
                                </div>
                                <span class="text-white font-medium"><?php echo htmlspecialchars(explode(' ', $activity['full_name'])[0]); ?></span>
                                <span class="text-gray-300"><?php echo $activity['action_text']; ?></span>
                                <span class="text-emerald-400 font-bold"><?php echo formatMoney($activity['amount']); ?></span>
                                <?php if ($activity['type'] === 'package_investment'): ?>
                                <i class="fas fa-chart-line text-yellow-400"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- TradingView Forex Chart Widget -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-4">Market Chart - Live Trading Data</h2>
                <!-- TradingView Widget BEGIN -->
                <div class="tradingview-widget-container" style="height:400px;">
                    <div id="tradingview_widget" style="height:100%;"></div>
                </div>
                <!-- TradingView Widget END -->
            </div>
        </section>

        <!-- Rest of dashboard continues... -->
        <!-- (I'll continue in the next part to stay within limits) -->
        
    </main>

    <script>
        // Notification dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const bell = document.getElementById('notificationBell');
            const dropdown = document.getElementById('notificationDropdown');
            const closeBtn = document.getElementById('closeNotifications');

            bell.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });

            closeBtn?.addEventListener('click', function() {
                dropdown.classList.add('hidden');
            });

            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    dropdown.classList.add('hidden');
                }
            });
        });

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
    
    <!-- TradingView Widget Script -->
    <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
    <script type="text/javascript">
        new TradingView.widget({
            "width": "100%",
            "height": 400,
            "symbol": "FX:BTCUSD",
            "interval": "1",
            "timezone": "Africa/Nairobi",
            "theme": "dark",
            "style": "1",
            "locale": "en",
            "toolbar_bg": "#f1f3f6",
            "enable_publishing": false,
            "hide_side_toolbar": false,
            "allow_symbol_change": true,
            "container_id": "tradingview_widget"
        });
    </script>
</body>
</html>