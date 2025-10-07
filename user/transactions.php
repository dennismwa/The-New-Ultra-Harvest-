<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get filter and pagination parameters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filter conditions
$where_conditions = ["t.user_id = ?"];
$params = [$user_id];

if ($filter !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM transactions t WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get transactions
$sql = "
    SELECT t.*, 
           CASE 
               WHEN t.type = 'package_investment' THEN p.name
               ELSE NULL
           END as package_name
    FROM transactions t
    LEFT JOIN active_packages ap ON t.id = ap.id AND t.type = 'package_investment'
    LEFT JOIN packages p ON ap.package_id = p.id
    WHERE $where_clause
    ORDER BY t.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_withdrawals,
        COALESCE(SUM(CASE WHEN type = 'roi_payment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_roi,
        COALESCE(SUM(CASE WHEN type = 'package_investment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_invested
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
    <title>Transaction History - Ultra Harvest Global</title>
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
        
        .filter-btn.active {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
        }
        
        .transaction-card {
            transition: all 0.3s ease;
        }
        
        .transaction-card:hover {
            transform: translateX(5px);
            background: rgba(255, 255, 255, 0.08);
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
                        <a href="/user/transactions.php" class="text-emerald-400 font-medium">Transactions</a>
                        <a href="/user/transactions.php" class="text-emerald-400 font-medium">Transactions</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Support</a>
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
                <i class="fas fa-receipt text-emerald-400 mr-3"></i>
                Transaction History
            </h1>
            <p class="text-xl text-gray-300">Complete record of your financial activities</p>
        </div>

        <!-- Summary Statistics -->
        <section class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Transactions</p>
                        <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_transactions']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-list text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
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

            <div class="glass-card rounded-xl p-6">
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

            <div class="glass-card rounded-xl p-6">
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
        </section>

        <!-- Filter Tabs -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex flex-wrap gap-3">
                    <a href="?filter=all" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'all' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-list mr-2"></i>All Transactions
                    </a>
                    <a href="?filter=deposit" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'deposit' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-arrow-down mr-2"></i>Deposits
                    </a>
                    <a href="?filter=withdrawal" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'withdrawal' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-arrow-up mr-2"></i>Withdrawals
                    </a>
                    <a href="?filter=package_investment" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'package_investment' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-chart-line mr-2"></i>Investments
                    </a>
                    <a href="?filter=roi_payment" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'roi_payment' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-coins mr-2"></i>ROI Payments
                    </a>
                </div>
            </div>
        </section>

        <!-- Transactions List -->
        <section class="glass-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white">
                    <?php 
                    $filter_names = [
                        'all' => 'All Transactions',
                        'deposit' => 'Deposits',
                        'withdrawal' => 'Withdrawals',
                        'package_investment' => 'Package Investments',
                        'roi_payment' => 'ROI Payments'
                    ];
                    echo $filter_names[$filter] ?? 'Transactions';
                    ?>
                    <span class="text-gray-400 text-base font-normal ml-2">(<?php echo number_format($total_records); ?> total)</span>
                </h2>
            </div>

            <?php if (empty($transactions)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No transactions found</h3>
                    <p class="text-gray-500 mb-6">You haven't made any transactions yet</p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="/user/deposit.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-plus mr-2"></i>Make Deposit
                        </a>
                        <a href="/user/packages.php" class="px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-chart-line mr-2"></i>View Packages
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-4 text-gray-400">Type</th>
                                <th class="text-right py-4 text-gray-400">Amount</th>
                                <th class="text-center py-4 text-gray-400">Status</th>
                                <th class="text-left py-4 text-gray-400">Description</th>
                                <th class="text-right py-4 text-gray-400">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800/30 transition">
                                <td class="py-4">
                                    <div class="flex items-center space-x-3">
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
                                        <span class="font-medium text-white capitalize">
                                            <?php echo str_replace('_', ' ', $transaction['type']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-4 text-right">
                                    <span class="text-xl font-bold text-white"><?php echo formatMoney($transaction['amount']); ?></span>
                                    <?php if ($transaction['phone_number']): ?>
                                        <div class="text-xs text-gray-400"><?php echo $transaction['phone_number']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($transaction['status']) {
                                            'completed' => 'bg-emerald-500/20 text-emerald-400',
                                            'pending' => 'bg-yellow-500/20 text-yellow-400',
                                            'failed' => 'bg-red-500/20 text-red-400',
                                            'cancelled' => 'bg-gray-500/20 text-gray-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="py-4">
                                    <div class="max-w-xs">
                                        <p class="text-white text-sm"><?php echo htmlspecialchars($transaction['description'] ?? 'N/A'); ?></p>
                                        <?php if ($transaction['package_name']): ?>
                                            <p class="text-emerald-400 text-xs mt-1">Package: <?php echo $transaction['package_name']; ?></p>
                                        <?php endif; ?>
                                        <?php if ($transaction['mpesa_receipt']): ?>
                                            <p class="text-blue-400 text-xs mt-1">Receipt: <?php echo $transaction['mpesa_receipt']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 text-right">
                                    <p class="text-white text-sm"><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></p>
                                    <p class="text-gray-400 text-xs"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden space-y-4">
                    <?php foreach ($transactions as $transaction): ?>
                    <div class="transaction-card bg-gray-800/50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-3">
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
                                    <p class="font-medium text-white capitalize"><?php echo str_replace('_', ' ', $transaction['type']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo timeAgo($transaction['created_at']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-white"><?php echo formatMoney($transaction['amount']); ?></p>
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php 
                                    echo match($transaction['status']) {
                                        'completed' => 'bg-emerald-500/20 text-emerald-400',
                                        'pending' => 'bg-yellow-500/20 text-yellow-400',
                                        'failed' => 'bg-red-500/20 text-red-400',
                                        'cancelled' => 'bg-gray-500/20 text-gray-400',
                                        default => 'bg-gray-500/20 text-gray-400'
                                    };
                                    ?>">
                                    <?php echo ucfirst($transaction['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($transaction['description']): ?>
                            <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($transaction['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($transaction['phone_number']): ?>
                            <p class="text-blue-400 text-xs mt-2">Phone: <?php echo $transaction['phone_number']; ?></p>
                        <?php endif; ?>
                        <?php if ($transaction['mpesa_receipt']): ?>
                            <p class="text-green-400 text-xs mt-1">Receipt: <?php echo $transaction['mpesa_receipt']; ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-center mt-8 space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page-1; ?>" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" 
                           class="px-4 py-2 rounded-lg transition <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page+1; ?>" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Export Options -->
        <section class="text-center mt-8">
            <div class="glass-card rounded-xl p-6 inline-block">
                <h3 class="text-lg font-bold text-white mb-4">Export Transactions</h3>
                <div class="flex flex-wrap gap-3 justify-center">
                    <a href="/user/export.php?type=pdf&filter=<?php echo $filter; ?>" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </a>
                    <a href="/user/export.php?type=csv&filter=<?php echo $filter; ?>" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-file-csv mr-2"></i>Export CSV
                    </a>
                </div>
            </div>
        </section>
    </main><br><br><br>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden">
        <div class="grid grid-cols-4 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="/user/packages.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Trade</span>
            </a>
            <a href="/user/transactions.php" class="flex flex-col items-center py-2 text-emerald-400">
                <i class="fas fa-receipt text-xl mb-1"></i>
                <span class="text-xs">History</span>
            </a>
            <a href="/user/profile.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-user text-xl mb-1"></i>
                <span class="text-xs">Profile</span>
            </a>
        </div>
    </div>
</body>
</html>