<?php
require_once '../config/database.php';
requireAdmin();

// Get comprehensive system health data
$stmt = $db->query("SELECT * FROM admin_stats_overview");
$stats = $stmt->fetch();

// Calculate key metrics
$platform_liquidity = $stats['total_deposits'] - $stats['total_withdrawals'] - $stats['total_roi_paid'];
$total_liabilities = $stats['total_user_balances'] + $stats['pending_roi_obligations'];
$coverage_ratio = $total_liabilities > 0 ? $platform_liquidity / $total_liabilities : 1;

// Get recent system health history
$stmt = $db->query("
    SELECT * FROM system_health_log 
    ORDER BY created_at DESC 
    LIMIT 30
");
$health_history = $stmt->fetchAll();

// Get maturity schedule for next 7 days
$stmt = $db->query("
    SELECT 
        DATE(maturity_date) as maturity_date,
        COUNT(*) as package_count,
        SUM(investment_amount) as total_investment,
        SUM(expected_roi) as total_roi_due
    FROM active_packages 
    WHERE status = 'active' 
    AND maturity_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(maturity_date)
    ORDER BY maturity_date ASC
");
$maturity_schedule = $stmt->fetchAll();

// Get top performing packages
$stmt = $db->query("
    SELECT p.name, p.icon, p.roi_percentage,
           COUNT(ap.id) as total_investments,
           SUM(ap.investment_amount) as total_invested,
           AVG(ap.investment_amount) as avg_investment
    FROM packages p
    JOIN active_packages ap ON p.id = ap.package_id
    GROUP BY p.id
    ORDER BY total_invested DESC
    LIMIT 5
");
$top_packages = $stmt->fetchAll();

// Get recent large transactions
$stmt = $db->query("
    SELECT t.*, u.full_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.amount >= 10000
    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY t.amount DESC
    LIMIT 10
");
$large_transactions = $stmt->fetchAll();

// Calculate risk metrics
$risk_level = 'low';
$risk_factors = [];

if ($coverage_ratio < 0.8) {
    $risk_level = 'critical';
    $risk_factors[] = 'Coverage ratio below 80%';
} elseif ($coverage_ratio < 1.0) {
    $risk_level = 'high';
    $risk_factors[] = 'Coverage ratio below 100%';
} elseif ($coverage_ratio < 1.2) {
    $risk_level = 'medium';
    $risk_factors[] = 'Coverage ratio below 120%';
}

// Check for high ROI obligations
$roi_due_7d = 0;
foreach ($maturity_schedule as $day) {
    $roi_due_7d += $day['total_roi_due'];
}

if ($roi_due_7d > $platform_liquidity * 0.5) {
    if ($risk_level === 'low') $risk_level = 'medium';
    $risk_factors[] = 'High ROI obligations in next 7 days';
}

// Get user growth metrics
$stmt = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_users
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND is_admin = 0
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
");
$user_growth = $stmt->fetchAll();

// Force log current system health
logSystemHealth();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Monitor - Ultra Harvest Admin</title>
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
        
        .risk-critical { 
            background: linear-gradient(45deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            border-color: #ef4444;
        }
        
        .risk-high { 
            background: linear-gradient(45deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.2));
            border-color: #f59e0b;
        }
        
        .risk-medium { 
            background: linear-gradient(45deg, rgba(34, 197, 94, 0.2), rgba(21, 128, 61, 0.2));
            border-color: #22c55e;
        }
        
        .risk-low { 
            background: linear-gradient(45deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border-color: #10b981;
        }
        
        .metric-card {
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">

    <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">

</div>
                        <!--<div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-seedling text-white"></i>
                        </div>-->
                        <div>
                            <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                            <p class="text-xs text-gray-400">Admin Panel</p>
                        </div>
                    </div>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/admin/" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                        <a href="/admin/users.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                        <a href="/admin/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <a href="/admin/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                        <a href="/admin/system-health.php" class="text-emerald-400 font-medium">System Health</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 px-3 py-1 rounded-full risk-<?php echo $risk_level; ?>">
                        <div class="w-3 h-3 rounded-full <?php 
                        echo match($risk_level) {
                            'critical' => 'bg-red-500 pulse-animation',
                            'high' => 'bg-yellow-500 pulse-animation',
                            'medium' => 'bg-green-500',
                            default => 'bg-emerald-500'
                        };
                        ?>"></div>
                        <span class="text-sm font-medium text-white">System <?php echo ucfirst($risk_level); ?> Risk</span>
                    </div>
                    <a href="/logout.php" class="text-red-400 hover:text-red-300">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">System Health Monitor</h1>
                <p class="text-gray-400">Real-time platform financial health and risk assessment</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold <?php echo $coverage_ratio >= 1 ? 'text-emerald-400' : 'text-red-400'; ?>">
                    <?php echo number_format($coverage_ratio * 100, 1); ?>%
                </p>
                <p class="text-gray-400 text-sm">Coverage Ratio</p>
            </div>
        </div>

        <!-- Risk Assessment Alert -->
        <?php if (!empty($risk_factors)): ?>
        <section class="mb-8">
            <div class="risk-<?php echo $risk_level; ?> rounded-xl p-6 border-2">
                <div class="flex items-center mb-4">
                    <i class="fas fa-exclamation-triangle text-2xl <?php 
                    echo match($risk_level) {
                        'critical' => 'text-red-400',
                        'high' => 'text-yellow-400',
                        'medium' => 'text-green-400',
                        default => 'text-emerald-400'
                    };
                    ?> mr-3"></i>
                    <h2 class="text-xl font-bold text-white"><?php echo ucfirst($risk_level); ?> Risk Level Detected</h2>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <h3 class="font-semibold text-white mb-2">Risk Factors:</h3>
                        <ul class="space-y-1">
                            <?php foreach ($risk_factors as $factor): ?>
                            <li class="text-gray-200 text-sm">• <?php echo $factor; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white mb-2">Recommended Actions:</h3>
                        <ul class="space-y-1 text-sm text-gray-200">
                            <?php if ($coverage_ratio < 1.0): ?>
                            <li>• Temporarily pause new high-ROI packages</li>
                            <li>• Monitor withdrawal requests closely</li>
                            <li>• Consider adjusting ROI rates</li>
                            <?php endif; ?>
                            <li>• Increase user acquisition to boost deposits</li>
                            <li>• Review and optimize package performance</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Key Financial Metrics -->
        <section class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6 metric-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Platform Liquidity</p>
                        <p class="text-2xl font-bold <?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                            <?php echo formatMoney($platform_liquidity); ?>
                        </p>
                        <p class="text-xs text-gray-500">Available for payouts</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-emerald-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6 metric-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Liabilities</p>
                        <p class="text-2xl font-bold text-red-400"><?php echo formatMoney($total_liabilities); ?></p>
                        <p class="text-xs text-gray-500">User balances + ROI due</p>
                    </div>
                    <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6 metric-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Coverage Ratio</p>
                        <p class="text-2xl font-bold <?php echo $coverage_ratio >= 1.2 ? 'text-emerald-400' : ($coverage_ratio >= 1 ? 'text-yellow-400' : 'text-red-400'); ?>">
                            <?php echo number_format($coverage_ratio * 100, 1); ?>%
                        </p>
                        <p class="text-xs text-gray-500">
                            <?php 
                            if ($coverage_ratio >= 1.2) echo 'Healthy';
                            elseif ($coverage_ratio >= 1) echo 'Warning';
                            else echo 'Critical';
                            ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 <?php echo $coverage_ratio >= 1.2 ? 'bg-emerald-500/20' : ($coverage_ratio >= 1 ? 'bg-yellow-500/20' : 'bg-red-500/20'); ?> rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-pie <?php echo $coverage_ratio >= 1.2 ? 'text-emerald-400' : ($coverage_ratio >= 1 ? 'text-yellow-400' : 'text-red-400'); ?> text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6 metric-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Active Packages</p>
                        <p class="text-2xl font-bold text-blue-400"><?php echo number_format($stats['active_packages']); ?></p>
                        <p class="text-xs text-gray-500">Currently running</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-box text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="grid lg:grid-cols-2 gap-8 mb-8">
            <!-- Coverage Ratio Trend -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Coverage Ratio Trend (30 Days)</h3>
                <div class="h-64">
                    <canvas id="coverageChart"></canvas>
                </div>
            </div>

            <!-- User Growth -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Daily User Registration</h3>
                <div class="h-64">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>
        </section>

        <!-- ROI Maturity Schedule -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-6">ROI Payment Schedule (Next 7 Days)</h3>
                <?php if (empty($maturity_schedule)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-check text-4xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400">No packages maturing in the next 7 days</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="text-left py-3 text-gray-400">Date</th>
                                    <th class="text-center py-3 text-gray-400">Packages</th>
                                    <th class="text-right py-3 text-gray-400">Investment</th>
                                    <th class="text-right py-3 text-gray-400">ROI Due</th>
                                    <th class="text-right py-3 text-gray-400">Total Payout</th>
                                    <th class="text-center py-3 text-gray-400">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maturity_schedule as $day): ?>
                                <tr class="border-b border-gray-800">
                                    <td class="py-4 text-white font-medium">
                                        <?php 
                                        $date = new DateTime($day['maturity_date']);
                                        echo $date->format('M j, Y');
                                        if ($date->format('Y-m-d') === date('Y-m-d')) {
                                            echo ' <span class="text-yellow-400 text-sm">(Today)</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="py-4 text-center text-blue-400 font-bold">
                                        <?php echo number_format($day['package_count']); ?>
                                    </td>
                                    <td class="py-4 text-right text-gray-300">
                                        <?php echo formatMoney($day['total_investment']); ?>
                                    </td>
                                    <td class="py-4 text-right text-yellow-400 font-bold">
                                        <?php echo formatMoney($day['total_roi_due']); ?>
                                    </td>
                                    <td class="py-4 text-right text-white font-bold">
                                        <?php echo formatMoney($day['total_investment'] + $day['total_roi_due']); ?>
                                    </td>
                                    <td class="py-4 text-center">
                                        <?php 
                                        $total_payout = $day['total_investment'] + $day['total_roi_due'];
                                        if ($total_payout <= $platform_liquidity) {
                                            echo '<span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 rounded text-xs">✓ Covered</span>';
                                        } else {
                                            echo '<span class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs">⚠ Risk</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Additional Metrics -->
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Top Performing Packages -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Top Performing Packages</h3>
                <?php if (empty($top_packages)): ?>
                    <p class="text-gray-400 text-center py-8">No package data available</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($top_packages as $package): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <span class="text-2xl"><?php echo $package['icon']; ?></span>
                                <div>
                                    <p class="font-medium text-white"><?php echo $package['name']; ?></p>
                                    <p class="text-sm text-gray-400"><?php echo $package['roi_percentage']; ?>% ROI</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-emerald-400 font-bold"><?php echo formatMoney($package['total_invested']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo number_format($package['total_investments']); ?> investments</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Large Transactions (24h) -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Large Transactions (24h)</h3>
                <?php if (empty($large_transactions)): ?>
                    <p class="text-gray-400 text-center py-8">No large transactions today</p>
                <?php else: ?>
                    <div class="space-y-3 max-h-80 overflow-y-auto">
                        <?php foreach ($large_transactions as $transaction): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                            <div>
                                <p class="font-medium text-white"><?php echo htmlspecialchars($transaction['full_name']); ?></p>
                                <p class="text-sm text-gray-400"><?php echo ucfirst($transaction['type']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-white"><?php echo formatMoney($transaction['amount']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo timeAgo($transaction['created_at']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- System Status -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">System Status</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Database</span>
                        <span class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                            <span class="text-emerald-400 text-sm">Online</span>
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">M-Pesa Integration</span>
                        <span class="flex items-center space-x-2">
                            <?php
                            $mpesa_status = !empty(getSystemSetting('mpesa_consumer_key'));
                            if ($mpesa_status): ?>
                                <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                                <span class="text-emerald-400 text-sm">Configured</span>
                            <?php else: ?>
                                <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                                <span class="text-yellow-400 text-sm">Not Configured</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">ROI Processing</span>
                        <span class="flex items-center space-x-2">
                            <?php
                            $roi_enabled = getSystemSetting('auto_roi_processing', '1') === '1';
                            if ($roi_enabled): ?>
                                <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                                <span class="text-emerald-400 text-sm">Automated</span>
                            <?php else: ?>
                                <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                                <span class="text-red-400 text-sm">Manual</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Notifications</span>
                        <span class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                            <span class="text-emerald-400 text-sm">Active</span>
                        </span>
                    </div>

                    <!-- Quick Actions -->
                    <div class="pt-4 border-t border-gray-700">
                        <h4 class="text-white font-medium mb-3">Quick Actions</h4>
                        <div class="space-y-2">
                            <a href="/admin/settings.php" class="block w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm text-center transition">
                                <i class="fas fa-cog mr-1"></i>System Settings
                            </a>
                            <button onclick="forceRoiProcessing()" class="w-full px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-sm transition">
                                <i class="fas fa-coins mr-1"></i>Force ROI Processing
                            </button>
                            <a href="/admin/reports.php" class="block w-full px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm text-center transition">
                                <i class="fas fa-chart-bar mr-1"></i>Generate Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Coverage Ratio Chart
        const coverageCtx = document.getElementById('coverageChart').getContext('2d');
        const healthHistory = <?php echo json_encode(array_reverse($health_history)); ?>;
        
        new Chart(coverageCtx, {
            type: 'line',
            data: {
                labels: healthHistory.map(item => {
                    const date = new Date(item.created_at);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Coverage Ratio (%)',
                    data: healthHistory.map(item => (item.coverage_ratio * 100).toFixed(1)),
                    borderColor: function(context) {
                        const value = context.parsed.y;
                        if (value >= 120) return '#10b981';
                        if (value >= 100) return '#f59e0b';
                        return '#ef4444';
                    },
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
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
                                return value + '%';
                            }
                        },
                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                    }
                }
            }
        });

        // User Growth Chart
        const userCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowth = <?php echo json_encode(array_reverse($user_growth)); ?>;
        
        new Chart(userCtx, {
            type: 'bar',
            data: {
                labels: userGrowth.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'New Users',
                    data: userGrowth.map(item => item.new_users),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
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

        // Real-time updates
        function updateSystemHealth() {
            fetch('/api/system-health-update.php')
                .then(response => response.json())
                .then(data => {
                    // Update metrics without full page reload
                    if (data.coverage_ratio !== undefined) {
                        // Update coverage ratio display
                        document.querySelector('.coverage-ratio').textContent = (data.coverage_ratio * 100).toFixed(1) + '%';
                    }
                })
                .catch(error => console.error('Health update failed:', error));
        }

        // Force ROI processing
        function forceRoiProcessing() {
            if (confirm('Are you sure you want to force ROI processing? This will process all matured packages immediately.')) {
                const button = event.target;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';
                button.disabled = true;
                
                fetch('/api/process-roi.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ force: true })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`ROI processing completed. Processed ${data.processed_count} packages.`);
                        location.reload();
                    } else {
                        alert('ROI processing failed: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('ROI processing failed: ' + error.message);
                })
                .finally(() => {
                    button.innerHTML = '<i class="fas fa-coins mr-1"></i>Force ROI Processing';
                    button.disabled = false;
                });
            }
        }

        // Export system health data
        function exportHealthData() {
            const data = {
                timestamp: new Date().toISOString(),
                coverage_ratio: <?php echo $coverage_ratio; ?>,
                platform_liquidity: <?php echo $platform_liquidity; ?>,
                total_liabilities: <?php echo $total_liabilities; ?>,
                risk_level: '<?php echo $risk_level; ?>',
                active_packages: <?php echo $stats['active_packages']; ?>,
                total_users: <?php echo $stats['total_users']; ?>
            };
            
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `system-health-${new Date().toISOString().split('T')[0]}.json`;
            link.click();
        }

        // Auto-refresh every 60 seconds
        setInterval(updateSystemHealth, 60000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportHealthData();
            }
        });

        // Initialize tooltips and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects for metric cards
            document.querySelectorAll('.metric-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.3)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.boxShadow = '';
                });
            });

            // Initialize real-time clock
            updateClock();
            setInterval(updateClock, 1000);
        });

        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            // Update any clock elements if they exist
            const clockElement = document.getElementById('system-clock');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }

        // Warning system for critical metrics
        function checkCriticalMetrics() {
            const coverageRatio = <?php echo $coverage_ratio; ?>;
            const riskLevel = '<?php echo $risk_level; ?>';
            
            if (riskLevel === 'critical') {
                // Show persistent warning
                showCriticalAlert();
            }
        }

        function showCriticalAlert() {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-20 right-4 bg-red-600 text-white p-4 rounded-lg shadow-lg z-50';
            alertDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span>Critical system health detected!</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-red-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (document.body.contains(alertDiv)) {
                    document.body.removeChild(alertDiv);
                }
            }, 10000);
        }

        // Initialize critical checks
        checkCriticalMetrics();
    </script>
</body>
</html>