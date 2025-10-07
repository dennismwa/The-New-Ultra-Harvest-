<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get active packages with statistics
$stmt = $db->prepare("
    SELECT ap.*, p.name as package_name, p.icon, p.roi_percentage as package_roi
    FROM active_packages ap 
    JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'active' 
    ORDER BY ap.created_at DESC
");
$stmt->execute([$user_id]);
$active_trades = $stmt->fetchAll();

// Get completed packages
$stmt = $db->prepare("
    SELECT ap.*, p.name as package_name, p.icon
    FROM active_packages ap 
    JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'completed' 
    ORDER BY ap.completed_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$completed_trades = $stmt->fetchAll();

// Calculate statistics
$total_investment = 0;
$total_earnings = 0;
$all_time_profits = 0;

foreach ($active_trades as $trade) {
    $total_investment += $trade['investment_amount'];
    $total_earnings += $trade['expected_roi'];
}

// Get completed stats
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(investment_amount), 0) as completed_investment,
        COALESCE(SUM(expected_roi), 0) as completed_earnings
    FROM active_packages 
    WHERE user_id = ? AND status = 'completed'
");
$stmt->execute([$user_id]);
$completed_stats = $stmt->fetch();

$all_time_profits = $completed_stats['completed_earnings'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Trades - Ultra Harvest Global</title>
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
        
        .countdown {
            font-family: 'Courier New', monospace;
        }
        
        .progress-bar {
            transition: width 0.3s ease;
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
                        <a href="/user/active-trades.php" class="text-emerald-400 font-medium">Active Trades</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-4 py-2">
                        <i class="fas fa-wallet text-emerald-400"></i>
                        <span class="text-sm text-gray-300">Balance:</span>
                        <span class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></span>
                    </div>
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
                <i class="fas fa-briefcase text-emerald-400 mr-3"></i>
                Active Trades
            </h1>
            <p class="text-xl text-gray-300">Monitor your active trading packages</p>
        </div>

        <!-- Statistics Overview - As per client request -->
        <section class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Active Trades</p>
                        <p class="text-3xl font-bold text-white"><?php echo count($active_trades); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-briefcase text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Completed</p>
                        <p class="text-3xl font-bold text-emerald-400"><?php echo count($completed_trades); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-emerald-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">All Time Profits</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo formatMoney($all_time_profits); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-trophy text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Investment</p>
                        <p class="text-2xl font-bold text-purple-400"><?php echo formatMoney($total_investment); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Active Trades Section -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-2xl font-bold text-white mb-6">
                    <i class="fas fa-chart-line text-emerald-400 mr-2"></i>
                    Your Active Trades
                </h2>

                <?php if (empty($active_trades)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-briefcase text-6xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-400 mb-2">No Active Trades</h3>
                        <p class="text-gray-500 mb-6">Start trading by activating a package</p>
                        <a href="/user/packages.php" class="px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition inline-block">
                            <i class="fas fa-chart-line mr-2"></i>Activate Trade Now
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($active_trades as $trade): ?>
                        <div class="bg-gray-800/50 rounded-xl p-6 border border-emerald-500/30">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="text-3xl"><?php echo $trade['icon']; ?></div>
                                    <div>
                                        <h3 class="font-bold text-white"><?php echo $trade['package_name']; ?></h3>
                                        <p class="text-sm text-emerald-400">Active</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-white"><?php echo formatMoney($trade['investment_amount']); ?></p>
                                    <p class="text-sm text-gray-400">Invested</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-sm text-gray-400">Expected ROI</p>
                                    <p class="font-bold text-emerald-400"><?php echo formatMoney($trade['expected_roi']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">ROI Rate</p>
                                    <p class="font-bold text-yellow-400"><?php echo $trade['roi_percentage']; ?>%</p>
                                </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <?php
                            $start_time = strtotime($trade['created_at']);
                            $end_time = strtotime($trade['maturity_date']);
                            $current_time = time();
                            $total_duration = $end_time - $start_time;
                            $elapsed = $current_time - $start_time;
                            $progress = min(100, max(0, ($elapsed / $total_duration) * 100));
                            ?>
                            <div class="mb-4">
                                <div class="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Progress</span>
                                    <span><?php echo number_format($progress, 1); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="progress-bar bg-gradient-to-r from-emerald-500 to-yellow-500 h-2 rounded-full" 
                                         style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-3">
                                <p class="text-sm text-gray-400 mb-1">Matures in:</p>
                                <div class="countdown text-lg font-bold text-white" data-maturity="<?php echo $trade['maturity_date']; ?>">
                                    Calculating...
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Completed Trades Section -->
        <?php if (!empty($completed_trades)): ?>
        <section>
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-2xl font-bold text-white mb-6">
                    <i class="fas fa-check-circle text-green-400 mr-2"></i>
                    Completed Trades
                </h2>

                <div class="space-y-4">
                    <?php foreach ($completed_trades as $trade): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-800/30 rounded-lg">
                        <div class="flex items-center space-x-4">
                            <div class="text-2xl"><?php echo $trade['icon']; ?></div>
                            <div>
                                <h4 class="font-bold text-white"><?php echo $trade['package_name']; ?></h4>
                                <p class="text-sm text-gray-400">
                                    Completed <?php echo timeAgo($trade['completed_at']); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-400">Invested</p>
                            <p class="font-bold text-white"><?php echo formatMoney($trade['investment_amount']); ?></p>
                            <p class="text-sm text-emerald-400">
                                +<?php echo formatMoney($trade['expected_roi']); ?> ROI
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
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
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-emerald-400">
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
        // Countdown timers for active packages
        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(element => {
                const maturityDate = new Date(element.getAttribute('data-maturity')).getTime();
                const now = new Date().getTime();
                const distance = maturityDate - now;

                if (distance > 0) {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    if (days > 0) {
                        element.innerHTML = `${days}d ${hours}h ${minutes}m`;
                    } else {
                        element.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
                    }
                } else {
                    element.innerHTML = 'Matured - Refresh page';
                    element.classList.add('text-emerald-400');
                }
            });
        }

        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    </script>
</body>
</html>