<?php
require_once '../config/database.php';
requireAdmin();

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Financial Summary
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_withdrawals,
        COALESCE(SUM(CASE WHEN type = 'roi_payment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_roi_paid,
        COALESCE(SUM(CASE WHEN type = 'package_investment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_invested,
        COALESCE(SUM(CASE WHEN type = 'referral_commission' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_commissions,
        COUNT(DISTINCT user_id) as active_users_in_period
    FROM transactions 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$financial_summary = $stmt->fetch();

// Daily breakdown
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) as deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as withdrawals,
        COALESCE(SUM(CASE WHEN type = 'roi_payment' AND status = 'completed' THEN amount ELSE 0 END), 0) as roi_paid,
        COUNT(*) as transaction_count
    FROM transactions 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");
$stmt->execute([$start_date, $end_date]);
$daily_breakdown = $stmt->fetchAll();

// Top performing packages
$stmt = $db->prepare("
    SELECT 
        p.name, p.icon,
        COUNT(ap.id) as investment_count,
        COALESCE(SUM(ap.investment_amount), 0) as total_invested,
        COALESCE(SUM(ap.expected_roi), 0) as expected_roi,
        AVG(ap.investment_amount) as avg_investment
    FROM packages p
    LEFT JOIN active_packages ap ON p.id = ap.package_id
    LEFT JOIN transactions t ON ap.user_id = t.user_id AND t.type = 'package_investment'
    WHERE DATE(ap.created_at) BETWEEN ? AND ? OR ap.created_at IS NULL
    GROUP BY p.id
    ORDER BY total_invested DESC
");
$stmt->execute([$start_date, $end_date]);
$package_performance = $stmt->fetchAll();

// User registration stats
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_users
    FROM users 
    WHERE is_admin = 0 AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");
$stmt->execute([$start_date, $end_date]);
$user_registrations = $stmt->fetchAll();

// Calculate platform health
$platform_liquidity = $financial_summary['total_deposits'] - $financial_summary['total_withdrawals'] - $financial_summary['total_roi_paid'];
$net_profit = $platform_liquidity - $financial_summary['total_commissions'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        @media print {
            body { background: white !important; color: black !important; }
            .no-print { display: none !important; }
            .glass-card { background: white !important; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50 no-print">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="/admin/" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">

    <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">

</div>
                        <!--<div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-seedling text-white"></i>
                        </div>-->
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/admin/" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                        <a href="/admin/users.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                        <a href="/admin/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <a href="/admin/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-print mr-2"></i>Print Report
                    </button>
                    <a href="/admin/" class="text-gray-400 hover:text-white">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white mb-2">Reports & Analytics</h1>
            <p class="text-xl text-gray-300">Comprehensive platform performance analysis</p>
        </div>

        <!-- Date Range Filter -->
        <div class="glass-card rounded-xl p-6 mb-8 no-print">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Start Date</label>
                    <input 
                        type="date" 
                        name="start_date" 
                        value="<?php echo $start_date; ?>"
                        class="px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">End Date</label>
                    <input 
                        type="date" 
                        name="end_date" 
                        value="<?php echo $end_date; ?>"
                        class="px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white"
                    >
                </div>
                <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium transition">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </form>
        </div>

        <!-- Report Header -->
        <div class="glass-card rounded-xl p-6 mb-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-white mb-2">Financial Report</h2>
                <p class="text-gray-300">Period: <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></p>
                <p class="text-sm text-gray-500">Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Deposits</p>
                        <p class="text-2xl font-bold text-emerald-400"><?php echo formatMoney($financial_summary['total_deposits']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-down text-emerald-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Withdrawals</p>
                        <p class="text-2xl font-bold text-red-400"><?php echo formatMoney($financial_summary['total_withdrawals']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">ROI Paid</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo formatMoney($financial_summary['total_roi_paid']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Net Liquidity</p>
                        <p class="text-2xl font-bold <?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                            <?php echo formatMoney($platform_liquidity); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 <?php echo $platform_liquidity >= 0 ? 'bg-emerald-500/20' : 'bg-red-500/20'; ?> rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line <?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?> text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid lg:grid-cols-2 gap-8 mb-8">
            <!-- Daily Financial Trend -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Daily Financial Trend</h3>
                <div class="h-64">
                    <canvas id="dailyTrendChart"></canvas>
                </div>
            </div>

            <!-- User Registration Chart -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">User Registrations</h3>
                <div class="h-64">
                    <canvas id="userRegChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Package Performance -->
        <div class="glass-card rounded-xl p-6 mb-8">
            <h3 class="text-lg font-bold text-white mb-6">Package Performance</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 text-gray-400">Package</th>
                            <th class="text-center py-3 text-gray-400">Investments</th>
                            <th class="text-right py-3 text-gray-400">Total Invested</th>
                            <th class="text-right py-3 text-gray-400">Expected ROI</th>
                            <th class="text-right py-3 text-gray-400">Avg Investment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($package_performance as $package): ?>
                        <tr class="border-b border-gray-800">
                            <td class="py-3">
                                <div class="flex items-center space-x-3">
                                    <span class="text-2xl"><?php echo $package['icon']; ?></span>
                                    <span class="font-medium text-white"><?php echo $package['name']; ?></span>
                                </div>
                            </td>
                            <td class="text-center py-3 text-blue-400 font-bold"><?php echo number_format($package['investment_count']); ?></td>
                            <td class="text-right py-3 text-emerald-400 font-bold"><?php echo formatMoney($package['total_invested']); ?></td>
                            <td class="text-right py-3 text-yellow-400 font-bold"><?php echo formatMoney($package['expected_roi']); ?></td>
                            <td class="text-right py-3 text-gray-300"><?php echo formatMoney($package['avg_investment']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Breakdown Table -->
        <div class="glass-card rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-6">Daily Financial Breakdown</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 text-gray-400">Date</th>
                            <th class="text-right py-3 text-gray-400">Deposits</th>
                            <th class="text-right py-3 text-gray-400">Withdrawals</th>
                            <th class="text-right py-3 text-gray-400">ROI Paid</th>
                            <th class="text-right py-3 text-gray-400">Net Flow</th>
                            <th class="text-center py-3 text-gray-400">Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_breakdown as $day): ?>
                        <?php $net_flow = $day['deposits'] - $day['withdrawals'] - $day['roi_paid']; ?>
                        <tr class="border-b border-gray-800">
                            <td class="py-3 text-white font-medium"><?php echo date('M j, Y', strtotime($day['date'])); ?></td>
                            <td class="text-right py-3 text-emerald-400"><?php echo formatMoney($day['deposits']); ?></td>
                            <td class="text-right py-3 text-red-400"><?php echo formatMoney($day['withdrawals']); ?></td>
                            <td class="text-right py-3 text-yellow-400"><?php echo formatMoney($day['roi_paid']); ?></td>
                            <td class="text-right py-3 <?php echo $net_flow >= 0 ? 'text-emerald-400' : 'text-red-400'; ?> font-bold">
                                <?php echo formatMoney($net_flow); ?>
                            </td>
                            <td class="text-center py-3 text-gray-300"><?php echo number_format($day['transaction_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($daily_breakdown)): ?>
            <div class="text-center py-12">
                <i class="fas fa-chart-bar text-6xl text-gray-600 mb-4"></i>
                <p class="text-gray-400">No transaction data found for the selected period</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Daily Financial Trend Chart
        const dailyTrendCtx = document.getElementById('dailyTrendChart').getContext('2d');
        const dailyData = <?php echo json_encode(array_reverse($daily_breakdown)); ?>;
        
        new Chart(dailyTrendCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Deposits',
                    data: dailyData.map(item => parseFloat(item.deposits)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Withdrawals',
                    data: dailyData.map(item => parseFloat(item.withdrawals)),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'ROI Paid',
                    data: dailyData.map(item => parseFloat(item.roi_paid)),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ffffff' }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                    },
                    y: {
                        ticks: { 
                            color: '#9ca3af',
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        },
                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                    }
                }
            }
        });

        // User Registration Chart
        const userRegCtx = document.getElementById('userRegChart').getContext('2d');
        const userRegData = <?php echo json_encode(array_reverse($user_registrations)); ?>;
        
        new Chart(userRegCtx, {
            type: 'bar',
            data: {
                labels: userRegData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'New Users',
                    data: userRegData.map(item => parseInt(item.new_users)),
                    backgroundColor: 'rgba(147, 51, 234, 0.8)',
                    borderColor: '#9333ea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ffffff' }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                    },
                    y: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                    }
                }
            }
        });
    </script>
</body>
</html>