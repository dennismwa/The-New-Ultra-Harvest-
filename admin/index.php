<?php
require_once '../config/database.php';
requireAdmin();

// Set default timezone (adjust to your timezone - Kenya is EAT: UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Also set MySQL timezone to match
try {
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    error_log("Failed to set MySQL timezone: " . $e->getMessage());
}

// Get dashboard statistics
$stmt = $db->query("SELECT * FROM admin_stats_overview");
$stats = $stmt->fetch();

// Calculate system health metrics
$platform_liquidity = $stats['total_deposits'] - $stats['total_withdrawals'] - $stats['total_roi_paid'];
$total_liabilities = $stats['total_user_balances'] + $stats['pending_roi_obligations'];
$coverage_ratio = $total_liabilities > 0 ? $platform_liquidity / $total_liabilities : 1;

// Get recent transactions
$stmt = $db->query("
    SELECT t.*, u.full_name, u.email 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.created_at DESC 
    LIMIT 10
");
$recent_transactions = $stmt->fetchAll();

// Get system health alerts
$alerts = [];
if ($coverage_ratio < 1) {
    $alerts[] = ['type' => 'critical', 'message' => 'System cannot cover all liabilities! Coverage ratio: ' . number_format($coverage_ratio * 100, 2) . '%'];
} elseif ($coverage_ratio < 1.2) {
    $alerts[] = ['type' => 'warning', 'message' => 'Low coverage ratio: ' . number_format($coverage_ratio * 100, 2) . '%'];
}

// Get pending withdrawals count
$stmt = $db->query("SELECT COUNT(*) as pending_withdrawals FROM transactions WHERE type = 'withdrawal' AND status = 'pending'");
$pending_withdrawals = $stmt->fetch()['pending_withdrawals'];

if ($pending_withdrawals > 0) {
    $alerts[] = ['type' => 'info', 'message' => "$pending_withdrawals withdrawal requests pending approval"];
}

// Get monthly revenue data for chart
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as deposits,
        SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawals
    FROM transactions 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$monthly_data = $stmt->fetchAll();

// Log system health
logSystemHealth();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .status-critical { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .status-warning { color: #f59e0b; background: rgba(245, 158, 11, 0.1); }
        .status-info { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .status-success { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        
        .metric-card {
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-8">
                <!-- Small Logo Left -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                        <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div>
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                        <p class="text-xs text-gray-400">Global - Admin</p>
                    </div>
                </div>
                
                <!-- Center Navigation -->
                <nav class="hidden md:flex space-x-6">
                    <a href="/admin/" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                    <a href="/admin/users.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                    <a href="/admin/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                    <a href="/admin/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                    <a href="/admin/tickets.php" class="text-gray-300 hover:text-emerald-400 transition">Support</a>
                    <a href="/admin/system-health.php" class="text-gray-300 hover:text-emerald-400 transition">System Health</a>
                    <a href="/admin/settings.php" class="text-gray-300 hover:text-emerald-400 transition">Settings</a>
                </nav>
            </div>

            <!-- Right side content (keep existing user menu, etc.) -->
            <div class="flex items-center space-x-4">
                <!-- Keep existing right-side content from each page -->
            </div>
        </div>
    </div>
</header>
    <main class="container mx-auto px-4 py-8">
        
        <!-- Alerts Section -->
        <?php if (!empty($alerts)): ?>
        <section class="mb-8">
            <div class="space-y-3">
                <?php foreach ($alerts as $alert): ?>
                <div class="status-<?php echo $alert['type']; ?> border border-current rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas <?php 
                        echo match($alert['type']) {
                            'critical' => 'fa-exclamation-triangle',
                            'warning' => 'fa-exclamation-circle',
                            'info' => 'fa-info-circle',
                            default => 'fa-check-circle'
                        };
                        ?> mr-3"></i>
                        <span class="font-medium"><?php echo $alert['message']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Key Metrics -->
        <section class="mb-8">
            <h1 class="text-3xl font-bold mb-6">Dashboard Overview</h1>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Users -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Users</p>
                            <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_users']); ?></p>
                            <p class="text-emerald-400 text-sm">
                                <i class="fas fa-user-check mr-1"></i><?php echo number_format($stats['active_users']); ?> Active
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-emerald-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Deposits -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Deposits</p>
                            <p class="text-3xl font-bold text-white"><?php echo formatMoney($stats['total_deposits']); ?></p>
                            <p class="text-blue-400 text-sm">
                                <i class="fas fa-arrow-down mr-1"></i>Platform Income
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-down text-blue-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Withdrawals -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Withdrawals</p>
                            <p class="text-3xl font-bold text-white"><?php echo formatMoney($stats['total_withdrawals']); ?></p>
                            <p class="text-red-400 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>Platform Outflow
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-up text-red-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Platform Profit/Loss -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Net Position</p>
                            <p class="text-3xl font-bold <?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                                <?php echo formatMoney($platform_liquidity); ?>
                            </p>
                            <p class="<?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?> text-sm">
                                <i class="fas <?php echo $platform_liquidity >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i>
                                <?php echo $platform_liquidity >= 0 ? 'Profit' : 'Loss'; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 <?php echo $platform_liquidity >= 0 ? 'bg-emerald-500/20' : 'bg-red-500/20'; ?> rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line <?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?> text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- System Health & Financial Overview -->
        <section class="mb-8">
            <div class="grid lg:grid-cols-2 gap-8">
                
                <!-- System Health -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-6">System Health Monitor</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                            <div>
                                <p class="text-gray-400">Platform Liquidity</p>
                                <p class="font-bold text-white"><?php echo formatMoney($platform_liquidity); ?></p>
                            </div>
                            <div class="text-right">
                                <div class="w-4 h-4 rounded-full <?php echo $platform_liquidity > 0 ? 'bg-emerald-500' : 'bg-red-500'; ?>"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                            <div>
                                <p class="text-gray-400">Total User Balances</p>
                                <p class="font-bold text-white"><?php echo formatMoney($stats['total_user_balances']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-400">Liability</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                            <div>
                                <p class="text-gray-400">Pending ROI Obligations</p>
                                <p class="font-bold text-white"><?php echo formatMoney($stats['pending_roi_obligations']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-400">Future Payout</p>
                            </div>
                        </div>

                        <!-- Coverage Ratio -->
                        <div class="bg-gray-800/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-gray-400">Coverage Ratio</p>
                                <p class="font-bold <?php 
                                echo $coverage_ratio >= 1.2 ? 'text-emerald-400' : 
                                     ($coverage_ratio >= 1 ? 'text-yellow-400' : 'text-red-400'); 
                                ?>">
                                    <?php echo number_format($coverage_ratio * 100, 1); ?>%
                                </p>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="<?php 
                                echo $coverage_ratio >= 1.2 ? 'bg-emerald-500' : 
                                     ($coverage_ratio >= 1 ? 'bg-yellow-500' : 'bg-red-500'); 
                                ?> h-2 rounded-full transition-all" 
                                style="width: <?php echo min(100, $coverage_ratio * 100); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php 
                                if ($coverage_ratio >= 1.2) echo 'Healthy - System can cover all liabilities';
                                elseif ($coverage_ratio >= 1) echo 'Warning - Low coverage ratio';
                                else echo 'Critical - Cannot cover all liabilities';
                                ?>
                            </p>
                        </div>

                        <a href="/admin/system-health.php" class="block w-full text-center py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-chart-area mr-2"></i>View Detailed Health Report
                        </a>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-6">Revenue Trend (Last 12 Months)</h2>
                    <div class="h-80">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- Recent Activity & Quick Actions -->
        <section class="grid lg:grid-cols-3 gap-8">
            
            <!-- Recent Transactions -->
            <div class="lg:col-span-2 glass-card rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">Recent Transactions</h2>
                    <a href="/admin/transactions.php" class="text-emerald-400 hover:text-emerald-300 text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 text-gray-400">User</th>
                                <th class="text-center py-2 text-gray-400">Type</th>
                                <th class="text-right py-2 text-gray-400">Amount</th>
                                <th class="text-center py-2 text-gray-400">Status</th>
                                <th class="text-center py-2 text-gray-400">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <tr class="border-b border-gray-800">
                                <td class="py-3">
                                    <div>
                                        <p class="font-medium text-white"><?php echo htmlspecialchars($transaction['full_name']); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($transaction['email']); ?></p>
                                    </div>
                                </td>
                                <td class="text-center py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        <?php 
                                        echo match($transaction['type']) {
                                            'deposit' => 'bg-blue-500/20 text-blue-400',
                                            'withdrawal' => 'bg-red-500/20 text-red-400',
                                            'roi_payment' => 'bg-yellow-500/20 text-yellow-400',
                                            'package_investment' => 'bg-emerald-500/20 text-emerald-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                    </span>
                                </td>
                                <td class="text-right py-3 font-medium text-white">
                                    <?php echo formatMoney($transaction['amount']); ?>
                                </td>
                                <td class="text-center py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        <?php 
                                        echo match($transaction['status']) {
                                            'completed' => 'bg-emerald-500/20 text-emerald-400',
                                            'pending' => 'bg-yellow-500/20 text-yellow-400',
                                            'failed' => 'bg-red-500/20 text-red-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center py-3 text-gray-400 text-xs">
                                    <?php echo timeAgo($transaction['created_at']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">Quick Actions</h2>
                
                <div class="space-y-3">
                    <a href="/admin/users.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-users text-emerald-400"></i>
                            <span class="text-white">Manage Users</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/packages.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-box text-yellow-400"></i>
                            <span class="text-white">Manage Packages</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/transactions.php?filter=pending" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-clock text-blue-400"></i>
                            <div>
                                <span class="text-white block">Pending Approvals</span>
                                <?php if ($pending_withdrawals > 0): ?>
                                <span class="text-xs text-blue-400"><?php echo $pending_withdrawals; ?> withdrawals</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/reports.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-chart-bar text-purple-400"></i>
                            <span class="text-white">Generate Reports</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/settings.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-cog text-gray-400"></i>
                            <span class="text-white">System Settings</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>
                </div>

                <!-- Package Status Overview -->
                <div class="mt-6 pt-6 border-t border-gray-700">
                    <h3 class="font-semibold text-white mb-4">Active Packages</h3>
                    <div class="text-center">
                        <p class="text-3xl font-bold text-emerald-400"><?php echo number_format($stats['active_packages']); ?></p>
                        <p class="text-sm text-gray-400">Currently Running</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Deposits',
                    data: monthlyData.map(item => parseFloat(item.deposits)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Withdrawals',
                    data: monthlyData.map(item => parseFloat(item.withdrawals)),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#9ca3af'
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#9ca3af',
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    }
                }
            }
        });

        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);

        // Real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            // You can add a clock element if needed
        }

        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>