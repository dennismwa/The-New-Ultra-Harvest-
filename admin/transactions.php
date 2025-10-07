<?php
require_once '../config/database.php';
requireAdmin();

// Don't output anything before this point
ob_start(); // Start output buffering to prevent header issues

$error = '';
$success = '';

// Handle transaction actions
if ($_POST && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        
        switch ($action) {
            case 'approve_withdrawal':
                try {
                    $stmt = $db->prepare("
                        SELECT t.*, u.full_name, u.email 
                        FROM transactions t 
                        JOIN users u ON t.user_id = u.id 
                        WHERE t.id = ? AND t.type = 'withdrawal' AND t.status = 'pending'
                    ");
                    $stmt->execute([$transaction_id]);
                    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($transaction) {
                        $db->beginTransaction();
                        
                        // Update transaction status
                        $stmt = $db->prepare("
                            UPDATE transactions 
                            SET status = 'completed', processed_by = ?, admin_notes = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $admin_notes = "Approved by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i:s');
                        $stmt->execute([$_SESSION['user_id'], $admin_notes, $transaction_id]);
                        
                        // Send notification to user
                        sendNotification(
                            $transaction['user_id'],
                            'Withdrawal Approved! 🎉',
                            "Your withdrawal of " . formatMoney($transaction['amount']) . " has been approved and will be processed within 24 hours.",
                            'success'
                        );
                        
                        $db->commit();
                        $success = 'Withdrawal approved successfully.';
                    } else {
                        $error = 'Transaction not found or already processed.';
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Failed to approve withdrawal: ' . $e->getMessage();
                }
                break;
                
            case 'reject_withdrawal':
                $reason = trim($_POST['rejection_reason'] ?? '');
                
                if (empty($reason)) {
                    $error = 'Please provide a rejection reason.';
                    break;
                }
                
                try {
                    $stmt = $db->prepare("
                        SELECT t.*, u.full_name, u.email 
                        FROM transactions t 
                        JOIN users u ON t.user_id = u.id 
                        WHERE t.id = ? AND t.type = 'withdrawal' AND t.status = 'pending'
                    ");
                    $stmt->execute([$transaction_id]);
                    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($transaction) {
                        $db->beginTransaction();
                        
                        // Return funds to user wallet
                        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                        
                        // Update transaction status
                        $stmt = $db->prepare("
                            UPDATE transactions 
                            SET status = 'cancelled', processed_by = ?, admin_notes = ?, description = CONCAT(description, ' - Rejected: ', ?), updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $admin_notes = "Rejected by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i:s');
                        $stmt->execute([$_SESSION['user_id'], $admin_notes, $reason, $transaction_id]);
                        
                        // Send notification to user
                        sendNotification(
                            $transaction['user_id'],
                            'Withdrawal Rejected',
                            "Your withdrawal of " . formatMoney($transaction['amount']) . " has been rejected. Reason: $reason. Funds have been returned to your wallet.",
                            'warning'
                        );
                        
                        $db->commit();
                        $success = 'Withdrawal rejected and funds returned to user.';
                    } else {
                        $error = 'Transaction not found or already processed.';
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Failed to reject withdrawal: ' . $e->getMessage();
                }
                break;
                
            case 'approve_deposit':
                try {
                    $stmt = $db->prepare("
                        SELECT t.*, u.full_name, u.email 
                        FROM transactions t 
                        JOIN users u ON t.user_id = u.id 
                        WHERE t.id = ? AND t.type = 'deposit' AND t.status = 'pending'
                    ");
                    $stmt->execute([$transaction_id]);
                    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($transaction) {
                        $db->beginTransaction();
                        
                        // Credit user wallet
                        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, total_deposited = total_deposited + ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$transaction['amount'], $transaction['amount'], $transaction['user_id']]);
                        
                        // Update transaction status
                        $stmt = $db->prepare("
                            UPDATE transactions 
                            SET status = 'completed', processed_by = ?, admin_notes = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $admin_notes = "Manually approved by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i:s');
                        $stmt->execute([$_SESSION['user_id'], $admin_notes, $transaction_id]);
                        
                        // Send notification
                        sendNotification(
                            $transaction['user_id'],
                            'Deposit Confirmed! 🎉',
                            "Your deposit of " . formatMoney($transaction['amount']) . " has been confirmed and credited to your wallet.",
                            'success'
                        );
                        
                        $db->commit();
                        $success = 'Deposit approved successfully.';
                    } else {
                        $error = 'Transaction not found or already processed.';
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Failed to approve deposit: ' . $e->getMessage();
                }
                break;
                
            case 'update_transaction_status':
                $new_status = $_POST['new_status'] ?? '';
                $admin_notes = trim($_POST['admin_notes'] ?? '');
                
                if (!in_array($new_status, ['pending', 'completed', 'failed', 'cancelled'])) {
                    $error = 'Invalid status selected.';
                    break;
                }
                
                try {
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET status = ?, processed_by = ?, admin_notes = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $notes = $admin_notes ?: "Status updated by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i:s');
                    
                    if ($stmt->execute([$new_status, $_SESSION['user_id'], $notes, $transaction_id])) {
                        $success = 'Transaction status updated successfully.';
                    } else {
                        $error = 'Failed to update transaction status.';
                    }
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_conditions = ["1=1"];
$params = [];

if ($type_filter !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR t.mpesa_receipt LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get transactions
$transactions = [];
$total_records = 0;
$total_pages = 1;

try {
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_records = $result ? $result['total'] : 0;
    $total_pages = ceil($total_records / $limit);

    // Get transactions
    $sql = "
        SELECT t.id, t.user_id, t.type, t.amount, t.status, t.mpesa_receipt, t.mpesa_request_id, 
               t.phone_number, t.description, t.admin_notes, t.created_at, t.updated_at,
               u.full_name, u.email, u.phone,
               admin.full_name as processed_by_name
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN users admin ON t.processed_by = admin.id
        WHERE $where_clause
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load transactions: ' . $e->getMessage();
}

// Get summary statistics
$stats = [
    'total_transactions' => 0,
    'pending_transactions' => 0,
    'pending_deposits' => 0,
    'pending_withdrawals' => 0,
    'today_deposits' => 0,
    'today_withdrawals' => 0
];

try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
            SUM(CASE WHEN type = 'deposit' AND status = 'pending' THEN 1 ELSE 0 END) as pending_deposits,
            SUM(CASE WHEN type = 'withdrawal' AND status = 'pending' THEN 1 ELSE 0 END) as pending_withdrawals,
            COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' AND DATE(created_at) = CURDATE() THEN amount ELSE 0 END), 0) as today_deposits,
            COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' AND DATE(created_at) = CURDATE() THEN amount ELSE 0 END), 0) as today_withdrawals
        FROM transactions
    ";
    $stmt = $db->query($stats_sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    // Use default stats if query fails
}

ob_end_flush(); // End output buffering
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management - Ultra Harvest Admin</title>
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            z-index: 1000;
        }
        
        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
        
        .transaction-row:hover {
            background: rgba(255, 255, 255, 0.05);
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
                        <a href="/admin/transactions.php" class="text-emerald-400 font-medium">Transactions</a>
                        <a href="/admin/system-health.php" class="text-gray-300 hover:text-emerald-400 transition">System Health</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <?php if ($stats['pending_transactions'] > 0): ?>
                    <div class="flex items-center space-x-2 bg-yellow-600/20 border border-yellow-500/50 rounded-full px-3 py-1">
                        <i class="fas fa-clock text-yellow-400"></i>
                        <span class="text-yellow-300 text-sm font-medium"><?php echo $stats['pending_transactions']; ?> Pending</span>
                    </div>
                    <?php endif; ?>
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
                <h1 class="text-3xl font-bold text-white">Transaction Management</h1>
                <p class="text-gray-400">Monitor and manage all platform transactions</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-emerald-400"><?php echo number_format($stats['total_transactions']); ?></p>
                <p class="text-gray-400 text-sm">Total Transactions</p>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300"><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <section class="grid md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-yellow-400 text-2xl font-bold"><?php echo number_format($stats['pending_transactions']); ?></p>
                    <p class="text-gray-400 text-sm">Pending</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-blue-400 text-2xl font-bold"><?php echo number_format($stats['pending_deposits']); ?></p>
                    <p class="text-gray-400 text-sm">Pending Deposits</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-red-400 text-2xl font-bold"><?php echo number_format($stats['pending_withdrawals']); ?></p>
                    <p class="text-gray-400 text-sm">Pending Withdrawals</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-emerald-400 text-lg font-bold"><?php echo formatMoney($stats['today_deposits']); ?></p>
                    <p class="text-gray-400 text-sm">Today's Deposits</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-purple-400 text-lg font-bold"><?php echo formatMoney($stats['today_withdrawals']); ?></p>
                    <p class="text-gray-400 text-sm">Today's Withdrawals</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-white text-lg font-bold"><?php echo formatMoney($stats['today_deposits'] - $stats['today_withdrawals']); ?></p>
                    <p class="text-gray-400 text-sm">Net Today</p>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="glass-card rounded-xl p-6 mb-8">
            <form method="GET" class="grid md:grid-cols-4 gap-4">
                <!-- Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Transaction Type</label>
                    <select name="type" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                        <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="deposit" <?php echo $type_filter === 'deposit' ? 'selected' : ''; ?>>Deposits</option>
                        <option value="withdrawal" <?php echo $type_filter === 'withdrawal' ? 'selected' : ''; ?>>Withdrawals</option>
                        <option value="package_investment" <?php echo $type_filter === 'package_investment' ? 'selected' : ''; ?>>Investments</option>
                        <option value="roi_payment" <?php echo $type_filter === 'roi_payment' ? 'selected' : ''; ?>>ROI Payments</option>
                        <option value="referral_commission" <?php echo $type_filter === 'referral_commission' ? 'selected' : ''; ?>>Commissions</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="User name, email, receipt..."
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                    >
                </div>

                <!-- Submit -->
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium transition">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </section>

        <!-- Transactions Table -->
        <section class="glass-card rounded-xl overflow-hidden">
            <?php if (!empty($transactions)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="text-left p-4 text-gray-400 font-medium">User</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Type</th>
                                <th class="text-right p-4 text-gray-400 font-medium">Amount</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Status</th>
                                <th class="text-left p-4 text-gray-400 font-medium">Details</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Date</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr class="transaction-row border-b border-gray-800">
                                <!-- User Column -->
                                <td class="p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-sm font-bold text-white">
                                            <?php echo strtoupper(substr($transaction['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-white"><?php echo htmlspecialchars($transaction['full_name']); ?></p>
                                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($transaction['email']); ?></p>
                                            <?php if ($transaction['phone']): ?>
                                                <p class="text-xs text-blue-400"><?php echo htmlspecialchars($transaction['phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Type Column -->
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
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
                                        ?> mr-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                    </span>
                                </td>
                                
                                <!-- Amount Column -->
                                <td class="p-4 text-right">
                                    <p class="text-xl font-bold text-white"><?php echo formatMoney($transaction['amount']); ?></p>
                                </td>
                                
                                <!-- Status Column -->
                                <td class="p-4 text-center">
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
                                
                                <!-- Details Column -->
                                <td class="p-4">
                                    <div class="max-w-xs">
                                        <p class="text-white text-sm"><?php echo htmlspecialchars(substr($transaction['description'] ?? 'N/A', 0, 50)); ?></p>
                                        <?php if ($transaction['mpesa_receipt']): ?>
                                            <p class="text-green-400 text-xs mt-1">Receipt: <?php echo htmlspecialchars($transaction['mpesa_receipt']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($transaction['processed_by_name']): ?>
                                            <p class="text-blue-400 text-xs mt-1">By: <?php echo htmlspecialchars($transaction['processed_by_name']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Date Column -->
                                <td class="p-4 text-center text-gray-300 text-sm">
                                    <p><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></p>
                                </td>
                                
                                <!-- Actions Column -->
                                <td class="p-4 text-center">
                                    <?php if ($transaction['status'] === 'pending'): ?>
                                        <div class="flex items-center justify-center space-x-1">
                                            <?php if ($transaction['type'] === 'withdrawal'): ?>
                                                <button onclick="approveWithdrawal(<?php echo $transaction['id']; ?>)" 
                                                        class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs transition">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="rejectWithdrawal(<?php echo $transaction['id']; ?>)" 
                                                        class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs transition">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php elseif ($transaction['type'] === 'deposit'): ?>
                                                <button onclick="approveDeposit(<?php echo $transaction['id']; ?>)" 
                                                        class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs transition">
                                                    <i class="fas fa-check mr-1"></i>Approve
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="openStatusModal(<?php echo $transaction['id']; ?>, '<?php echo $transaction['status']; ?>')" 
                                                    class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs transition">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button onclick="openStatusModal(<?php echo $transaction['id']; ?>, '<?php echo $transaction['status']; ?>')" 
                                                class="px-2 py-1 bg-gray-600 hover:bg-gray-700 text-white rounded text-xs transition">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-6 border-t border-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> transactions
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?type=<?php echo htmlspecialchars($type_filter); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>" 
                                   class="px-3 py-2 bg-gray-800 text-gray-300 rounded hover:bg-gray-700 transition">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?type=<?php echo htmlspecialchars($type_filter); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                   class="px-3 py-2 rounded transition <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?type=<?php echo htmlspecialchars($type_filter); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>" 
                                   class="px-3 py-2 bg-gray-800 text-gray-300 rounded hover:bg-gray-700 transition">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-receipt text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No transactions found</h3>
                    <p class="text-gray-500">No transactions match your current filters</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="modal">
        <div class="glass-card rounded-xl p-6 max-w-md w-full m-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Reject Withdrawal</h3>
                <button type="button" onclick="closeModal('rejectionModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="reject_withdrawal">
                <input type="hidden" name="transaction_id" id="rejectTransactionId">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Rejection Reason *</label>
                    <textarea 
                        name="rejection_reason" 
                        rows="4"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-red-500 focus:outline-none"
                        placeholder="Explain why this withdrawal is being rejected..."
                        required
                    ></textarea>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                        Reject & Return Funds
                    </button>
                    <button type="button" onclick="closeModal('rejectionModal')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="glass-card rounded-xl p-6 max-w-md w-full m-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Update Transaction Status</h3>
                <button type="button" onclick="closeModal('statusModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_transaction_status">
                <input type="hidden" name="transaction_id" id="statusTransactionId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status *</label>
                        <select name="new_status" id="statusSelect" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Admin Notes</label>
                        <textarea 
                            name="admin_notes" 
                            rows="3"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Optional notes about this status change..."
                        ></textarea>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        Update Status
                    </button>
                    <button type="button" onclick="closeModal('statusModal')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Forms for Quick Actions -->
    <form id="approveWithdrawalForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="approve_withdrawal">
        <input type="hidden" name="transaction_id" id="approveWithdrawalId">
    </form>

    <form id="approveDepositForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="approve_deposit">
        <input type="hidden" name="transaction_id" id="approveDepositId">
    </form>

    <script>
        // Global variables
        let currentModal = null;

        // Quick action functions
        function approveWithdrawal(transactionId) {
            if (confirm('Are you sure you want to approve this withdrawal? This action cannot be undone.')) {
                document.getElementById('approveWithdrawalId').value = transactionId;
                document.getElementById('approveWithdrawalForm').submit();
            }
        }

        function rejectWithdrawal(transactionId) {
            document.getElementById('rejectTransactionId').value = transactionId;
            document.getElementById('rejectionModal').classList.add('show');
            currentModal = 'rejectionModal';
        }

        function approveDeposit(transactionId) {
            if (confirm('Are you sure you want to manually approve this deposit?')) {
                document.getElementById('approveDepositId').value = transactionId;
                document.getElementById('approveDepositForm').submit();
            }
        }

        function openStatusModal(transactionId, currentStatus) {
            document.getElementById('statusTransactionId').value = transactionId;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('statusModal').classList.add('show');
            currentModal = 'statusModal';
        }

        // Modal functions
        function closeModal(modalId = null) {
            const modal = modalId || currentModal;
            if (modal) {
                document.getElementById(modal).classList.remove('show');
                currentModal = null;
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Close modals when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });

            // Form validation
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const action = this.querySelector('input[name="action"]')?.value;
                    
                    if (action === 'reject_withdrawal') {
                        const reason = this.querySelector('textarea[name="rejection_reason"]').value.trim();
                        if (!reason) {
                            e.preventDefault();
                            alert('Please provide a rejection reason.');
                            return false;
                        }
                    }
                    
                    if (action === 'update_transaction_status') {
                        const status = this.querySelector('select[name="new_status"]').value;
                        if (status === 'failed' || status === 'cancelled') {
                            if (!confirm(`Are you sure you want to mark this transaction as ${status}?`)) {
                                e.preventDefault();
                                return false;
                            }
                        }
                    }
                });
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });

            // Enhanced search with debounce
            let searchTimeout;
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 3 || this.value.length === 0) {
                            this.form.submit();
                        }
                    }, 1000);
                });
            }

            // Update transaction status colors
            updateTransactionStatusColors();
        });

        // Auto-refresh for pending transactions
        <?php if ($stats['pending_transactions'] > 0): ?>
        setInterval(function() {
            if (!currentModal) {
                location.reload();
            }
        }, 30000); // Refresh every 30 seconds
        <?php endif; ?>

        // Transaction status color coding
        function updateTransactionStatusColors() {
            document.querySelectorAll('.transaction-row').forEach(row => {
                const statusBadge = row.querySelector('[class*="status-"]');
                if (statusBadge) {
                    const status = statusBadge.textContent.toLowerCase().trim();
                    switch(status) {
                        case 'pending':
                            row.style.borderLeft = '4px solid #f59e0b';
                            break;
                        case 'completed':
                            row.style.borderLeft = '4px solid #10b981';
                            break;
                        case 'failed':
                            row.style.borderLeft = '4px solid #ef4444';
                            break;
                        case 'cancelled':
                            row.style.borderLeft = '4px solid #6b7280';
                            break;
                    }
                }
            });
        }

        // Format money display
        function formatMoney(amount) {
            return new Intl.NumberFormat('en-KE', {
                style: 'currency',
                currency: 'KES',
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(amount);
        }

        // Copy transaction ID to clipboard
        function copyTransactionId(transactionId) {
            navigator.clipboard.writeText(transactionId).then(function() {
                // Show temporary success message
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            });
        }

        // Transaction details popup (for future implementation)
        function showTransactionDetails(transactionId) {
            // This could show a detailed modal with full transaction information
            console.log('Show details for transaction:', transactionId);
        }
    </script>
</body>
</html>