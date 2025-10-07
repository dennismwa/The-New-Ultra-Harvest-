<?php
require_once '../config/database.php';
requireAdmin();

$error = '';
$success = '';

// Handle user actions
if ($_POST && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = intval($_POST['user_id'] ?? 0);
        
        switch ($action) {
            case 'update_status':
                $status = $_POST['status'] ?? '';
                if (in_array($status, ['active', 'suspended', 'banned']) && $user_id) {
                    try {
                        $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND is_admin = 0");
                        if ($stmt->execute([$status, $user_id])) {
                            $success = 'User status updated successfully.';
                            
                            // Send notification to user
                            $message = match($status) {
                                'suspended' => 'Your account has been suspended. Please contact support for assistance.',
                                'banned' => 'Your account has been banned due to policy violations.',
                                'active' => 'Your account has been reactivated. Welcome back!'
                            };
                            sendNotification($user_id, 'Account Status Update', $message, $status === 'active' ? 'success' : 'warning');
                        } else {
                            $error = 'Failed to update user status.';
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid status or user ID.';
                }
                break;
                
            case 'adjust_balance':
                $amount = floatval($_POST['amount'] ?? 0);
                $type = $_POST['balance_type'] ?? 'credit'; // credit or debit
                $description = trim($_POST['description'] ?? '');
                
                if ($amount > 0 && $user_id && in_array($type, ['credit', 'debit'])) {
                    try {
                        $db->beginTransaction();
                        
                        if ($type === 'credit') {
                            $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$amount, $user_id]);
                            
                            // Create transaction record
                            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, status, description, processed_by, created_at) VALUES (?, 'deposit', ?, 'completed', ?, ?, NOW())");
                            $stmt->execute([$user_id, $amount, "Admin credit: " . $description, $_SESSION['user_id']]);
                            
                            sendNotification($user_id, 'Account Credited', formatMoney($amount) . " has been credited to your account. " . $description, 'success');
                        } else {
                            // Check if user has sufficient balance
                            $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $user = $stmt->fetch();
                            
                            if ($user && $user['wallet_balance'] >= $amount) {
                                $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ?, updated_at = NOW() WHERE id = ?");
                                $stmt->execute([$amount, $user_id]);
                                
                                // Create transaction record
                                $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, status, description, processed_by, created_at) VALUES (?, 'withdrawal', ?, 'completed', ?, ?, NOW())");
                                $stmt->execute([$user_id, $amount, "Admin debit: " . $description, $_SESSION['user_id']]);
                                
                                sendNotification($user_id, 'Account Debited', formatMoney($amount) . " has been debited from your account. " . $description, 'warning');
                            } else {
                                throw new Exception('Insufficient balance for debit');
                            }
                        }
                        
                        $db->commit();
                        $success = 'Balance adjusted successfully.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Failed to adjust balance: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid amount or user data.';
                }
                break;
                
            case 'send_notification':
                $title = trim($_POST['notification_title'] ?? '');
                $message = trim($_POST['notification_message'] ?? '');
                $type = $_POST['notification_type'] ?? 'info';
                
                if ($title && $message && $user_id) {
                    try {
                        sendNotification($user_id, $title, $message, $type);
                        $success = 'Notification sent successfully.';
                    } catch (Exception $e) {
                        $error = 'Failed to send notification: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Please fill in all notification fields.';
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions with proper table aliases
$where_conditions = ["u.is_admin = 0"];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$users = [];
$total_records = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_records = $result ? $result['total'] : 0;
    $total_pages = ceil($total_records / $limit);

    // Get users with transaction statistics
    $sql = "
        SELECT u.id, u.email, u.full_name, u.phone, u.wallet_balance, u.referral_code, 
               u.referred_by, u.referral_earnings, u.status, u.created_at, u.updated_at,
               COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_deposited,
               COALESCE(SUM(CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_withdrawn,
               COUNT(DISTINCT ref.id) as total_referrals
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id
        LEFT JOIN users ref ON u.id = ref.referred_by
        WHERE $where_clause
        GROUP BY u.id, u.email, u.full_name, u.phone, u.wallet_balance, u.referral_code, 
                 u.referred_by, u.referral_earnings, u.status, u.created_at, u.updated_at
        ORDER BY u.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load users: ' . $e->getMessage();
}

// Get summary statistics
$stats = ['total_users' => 0, 'active_users' => 0, 'suspended_users' => 0, 'banned_users' => 0, 'new_users_30d' => 0];
try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
            SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
        FROM users 
        WHERE is_admin = 0
    ";
    $stmt = $db->query($stats_sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    // Use default stats if query fails
}

// Helper function to format money
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Ultra Harvest Admin</title>
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
        
        .user-row {
            transition: all 0.3s ease;
        }
        
        .user-row:hover {
            background: rgba(255, 255, 255, 0.05);
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
    overflow-y: auto; /* ADD THIS */
    padding: 1rem; /* ADD THIS */
}

.modal.show {
    display: flex !important;
    align-items: flex-start; /* CHANGE from center to flex-start */
    justify-content: center;
    padding-top: 2rem; /* ADD THIS */
    padding-bottom: 2rem; /* ADD THIS */
}

/* ADD THIS NEW CLASS */
.modal-content {
    max-height: calc(100vh - 4rem);
    overflow-y: auto;
    margin: auto;
}

/* ADD CUSTOM SCROLLBAR STYLES */
.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: rgba(16, 185, 129, 0.5);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: rgba(16, 185, 129, 0.7);
}

/* ADD MOBILE RESPONSIVENESS */
@media (max-width: 640px) {
    .modal {
        padding: 0.5rem;
    }
    
    .modal.show {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    
    .modal-content {
        max-height: calc(100vh - 2rem);
        width: 100%;
    }
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
                        <a href="/admin/users.php" class="text-emerald-400 font-medium">Users</a>
                        <a href="/admin/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <a href="/admin/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                        <a href="/admin/system-health.php" class="text-gray-300 hover:text-emerald-400 transition">System Health</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/" target="_blank" class="text-gray-400 hover:text-white">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
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
                <h1 class="text-3xl font-bold text-white">User Management</h1>
                <p class="text-gray-400">Manage user accounts and activities</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-emerald-400"><?php echo number_format($stats['total_users']); ?></p>
                <p class="text-gray-400 text-sm">Total Users</p>
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
        <section class="grid md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-emerald-400 text-3xl font-bold"><?php echo number_format($stats['active_users']); ?></p>
                    <p class="text-gray-400 text-sm">Active Users</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-yellow-400 text-3xl font-bold"><?php echo number_format($stats['suspended_users']); ?></p>
                    <p class="text-gray-400 text-sm">Suspended</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-red-400 text-3xl font-bold"><?php echo number_format($stats['banned_users']); ?></p>
                    <p class="text-gray-400 text-sm">Banned</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-blue-400 text-3xl font-bold"><?php echo number_format($stats['new_users_30d']); ?></p>
                    <p class="text-gray-400 text-sm">New (30d)</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-purple-400 text-3xl font-bold"><?php echo number_format($stats['total_users']); ?></p>
                    <p class="text-gray-400 text-sm">Total Users</p>
                </div>
            </div>
        </section>

        <!-- Filters and Search -->
        <section class="glass-card rounded-xl p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Status Filter -->
                <div class="flex flex-wrap gap-2">
                    <a href="?status=all&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'all' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        All Users
                    </a>
                    <a href="?status=active&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'active' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        Active
                    </a>
                    <a href="?status=suspended&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'suspended' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        Suspended
                    </a>
                    <a href="?status=banned&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'banned' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        Banned
                    </a>
                </div>

                <!-- Search -->
                <form method="GET" class="flex items-center space-x-3">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search users..." 
                            class="pl-10 pr-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                        >
                    </div>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        Search
                    </button>
                    <?php if ($search): ?>
                    <a href="?status=<?php echo htmlspecialchars($status_filter); ?>" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Clear
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- Users Table -->
        <section class="glass-card rounded-xl overflow-hidden">
            <?php if (!empty($users)): ?>
                <!-- Desktop Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="text-left p-4 text-gray-400 font-medium">User</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Status</th>
                                <th class="text-right p-4 text-gray-400 font-medium">Balance</th>
                                <th class="text-right p-4 text-gray-400 font-medium">Deposited</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Referrals</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Joined</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="user-row border-b border-gray-800">
                                <td class="p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-full flex items-center justify-center">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-white"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                                            <?php if ($user['phone']): ?>
                                                <p class="text-xs text-blue-400"><?php echo htmlspecialchars($user['phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($user['status']) {
                                            'active' => 'bg-emerald-500/20 text-emerald-400',
                                            'suspended' => 'bg-yellow-500/20 text-yellow-400',
                                            'banned' => 'bg-red-500/20 text-red-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <p class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></p>
                                </td>
                                <td class="p-4 text-right">
                                    <p class="text-emerald-400 font-medium"><?php echo formatMoney($user['total_deposited']); ?></p>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="text-purple-400 font-medium"><?php echo number_format($user['total_referrals']); ?></span>
                                </td>
                                <td class="p-4 text-center text-gray-300 text-sm">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button onclick="openUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo $user['status']; ?>', <?php echo $user['wallet_balance']; ?>)" 
                                                class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs transition">
                                            <i class="fas fa-edit mr-1"></i>Manage
                                        </button>
                                        <button onclick="openNotificationModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" 
                                                class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs transition">
                                            <i class="fas fa-bell mr-1"></i>Notify
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="lg:hidden p-4">
                    <div class="space-y-4">
                        <?php foreach ($users as $user): ?>
                        <div class="bg-gray-800/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-full flex items-center justify-center">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-white"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php 
                                    echo match($user['status']) {
                                        'active' => 'bg-emerald-500/20 text-emerald-400',
                                        'suspended' => 'bg-yellow-500/20 text-yellow-400',
                                        'banned' => 'bg-red-500/20 text-red-400',
                                        default => 'bg-gray-500/20 text-gray-400'
                                    };
                                    ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-sm mb-3">
                                <div>
                                    <p class="text-gray-400">Balance</p>
                                    <p class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Deposited</p>
                                    <p class="font-bold text-emerald-400"><?php echo formatMoney($user['total_deposited']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Referrals</p>
                                    <p class="font-bold text-purple-400"><?php echo number_format($user['total_referrals']); ?></p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="openUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo $user['status']; ?>', <?php echo $user['wallet_balance']; ?>)" 
                                        class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm transition">
                                    <i class="fas fa-edit mr-1"></i>Manage
                                </button>
                                <button onclick="openNotificationModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" 
                                        class="flex-1 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm transition">
                                    <i class="fas fa-bell mr-1"></i>Notify
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-6 border-t border-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> users
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>" 
                                   class="px-3 py-2 bg-gray-800 text-gray-300 rounded hover:bg-gray-700 transition">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                   class="px-3 py-2 rounded transition <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>" 
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
                    <i class="fas fa-users text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No users found</h3>
                    <p class="text-gray-500">No users match your current filters</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- User Management Modal -->
    <div id="userModal" class="modal">
    <div class="glass-card rounded-xl modal-content max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Manage User</h3>
                <button type="button" onclick="closeModal('userModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <h4 class="font-medium text-white" id="modalUserName">User Name</h4>
                <p class="text-sm text-gray-400">Current Balance: <span id="modalUserBalance">KSh 0.00</span></p>
            </div></div>

            <!-- Status Update -->
            <form method="POST" class="mb-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="user_id" id="modal_user_id">
                
                <h4 class="font-medium text-white mb-3">Update Status</h4>
                <div class="space-y-2 mb-4">
                    <label class="flex items-center space-x-2">
                        <input type="radio" name="status" value="active" class="text-emerald-600" id="status_active">
                        <span class="text-emerald-400">Active</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="radio" name="status" value="suspended" class="text-yellow-600" id="status_suspended">
                        <span class="text-yellow-400">Suspended</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="radio" name="status" value="banned" class="text-red-600" id="status_banned">
                        <span class="text-red-400">Banned</span>
                    </label>
                </div>
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                    Update Status
                </button>
            </form>

            <!-- Balance Adjustment -->
            <form method="POST" class="border-t border-gray-700 pt-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="user_id" id="balance_user_id">
                <input type="hidden" name="action" value="adjust_balance">
                
                <h4 class="font-medium text-white mb-3">Adjust Balance</h4>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Amount (KSh)</label>
                        <input 
                            type="number" 
                            name="amount" 
                            min="1" 
                            step="0.01"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            required
                        >
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center space-x-2 p-3 bg-gray-800 rounded cursor-pointer">
                            <input type="radio" name="balance_type" value="credit" class="text-emerald-600" required>
                            <span class="text-emerald-400">Credit (+)</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 bg-gray-800 rounded cursor-pointer">
                            <input type="radio" name="balance_type" value="debit" class="text-red-600" required>
                            <span class="text-red-400">Debit (-)</span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Description</label>
                        <input 
                            type="text" 
                            name="description" 
                            placeholder="Reason for adjustment"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            required
                        >
                    </div>
                </div>
                
                <button type="submit" class="mt-4 w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition">
                    Adjust Balance
                </button>
            </form>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notificationModal" class="modal">
    <div class="glass-card rounded-xl modal-content max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Send Notification</h3>
                <button type="button" onclick="closeModal('notificationModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <h4 class="font-medium text-white" id="notificationUserName">User Name</h4>
            </div></div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="send_notification">
                <input type="hidden" name="user_id" id="notification_user_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Title *</label>
                        <input 
                            type="text" 
                            name="notification_title" 
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Notification title"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Message *</label>
                        <textarea 
                            name="notification_message" 
                            rows="4"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Enter your message..."
                            required
                        ></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Type</label>
                        <select name="notification_type" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-paper-plane mr-2"></i>Send Notification
                    </button>
                    <button type="button" onclick="closeModal('notificationModal')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let currentModal = null;
        
        // Modal functions
        function openUserModal(userId, userName, userStatus, userBalance) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('balance_user_id').value = userId;
            document.getElementById('modalUserName').textContent = userName;
            document.getElementById('modalUserBalance').textContent = 'KSh ' + parseFloat(userBalance).toLocaleString();
            
            // Set current status
            document.getElementById('status_' + userStatus).checked = true;
            document.body.style.overflow = 'hidden';
            
            document.getElementById('userModal').classList.add('show');
            currentModal = 'userModal';
        }

        function openNotificationModal(userId, userName) {
            document.getElementById('notification_user_id').value = userId;
            document.getElementById('notificationUserName').textContent = userName;
            document.body.style.overflow = 'hidden';
            
            document.getElementById('notificationModal').classList.add('show');
            currentModal = 'notificationModal';
        }

        function closeModal(modalId = null) {
            const modal = modalId || currentModal;
            if (modal) {
                document.getElementById(modal).classList.remove('show');
                document.body.style.overflow = '';
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

            // Form validation for balance adjustment
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const action = this.querySelector('input[name="action"]')?.value;
                    const status = this.querySelector('input[name="status"]:checked')?.value;
                    const balanceType = this.querySelector('input[name="balance_type"]:checked')?.value;
                    
                    if (action === 'update_status' && (status === 'suspended' || status === 'banned')) {
                        if (!confirm(`Are you sure you want to ${status} this user?`)) {
                            e.preventDefault();
                        }
                    }
                    
                    if (action === 'adjust_balance' && balanceType === 'debit') {
                        const amount = this.querySelector('input[name="amount"]').value;
                        if (!confirm(`Are you sure you want to debit KSh ${amount} from this user's account?`)) {
                            e.preventDefault();
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
        });

        // Format numbers
        function formatMoney(amount) {
            return 'KSh ' + parseFloat(amount).toLocaleString();
        }

        // Bulk actions (for future implementation)
        function bulkUpdateStatus(status) {
            const checkedUsers = document.querySelectorAll('input[name="selected_users[]"]:checked');
            if (checkedUsers.length === 0) {
                alert('Please select users to update.');
                return;
            }
            
            if (confirm(`Are you sure you want to ${status} ${checkedUsers.length} user(s)?`)) {
                // Implementation for bulk status update
                console.log('Bulk update:', status, checkedUsers.length, 'users');
            }
        }

        // Export functionality
        function exportUsers() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('export', 'csv');
            window.location.href = currentUrl.toString();
        }

        // Real-time user count updates
        function updateUserCounts() {
            // This could fetch updated counts via AJAX without full page reload
            fetch('/admin/api/user-counts.php')
                .then(response => response.json())
                .then(data => {
                    // Update the statistics cards
                    document.querySelector('.active-users-count').textContent = data.active_users;
                    document.querySelector('.total-users-count').textContent = data.total_users;
                })
                .catch(error => console.error('Failed to update user counts:', error));
        }

        // Auto-refresh every 60 seconds
        setInterval(function() {
            if (!currentModal) {
                location.reload();
            }
        }, 60000);
    </script>
</body>
</html>