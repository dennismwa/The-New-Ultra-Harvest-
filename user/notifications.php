<?php
// File: user/notifications.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle mark as read action
if ($_POST && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'mark_read' && isset($_POST['notification_id'])) {
            $notification_id = (int)$_POST['notification_id'];
            $stmt = $db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND (user_id = ? OR is_global = 1)
            ");
            $stmt->execute([$notification_id, $user_id]);
        } elseif ($action === 'mark_all_read') {
            $stmt = $db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE (user_id = ? OR is_global = 1) AND is_read = 0
            ");
            $stmt->execute([$user_id]);
        }
    }
}

// Get filter and pagination
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filter conditions - Fixed to avoid duplicates
$where_conditions = ["(user_id = ? OR is_global = 1)"];
$params = [$user_id];

if ($filter === 'unread') {
    $where_conditions[] = "is_read = 0";
} elseif ($filter !== 'all') {
    $where_conditions[] = "type = ?";
    $params[] = $filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count - Using subquery to count unique notifications
$count_sql = "
    SELECT COUNT(*) as total FROM (
        SELECT MIN(id) as min_id
        FROM notifications 
        WHERE $where_clause
        GROUP BY title, message, type, DATE(created_at), user_id
    ) as unique_notifications
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get notifications - Using GROUP BY to eliminate duplicates
$sql = "
    SELECT id, title, message, type, is_read, created_at, user_id, is_global
    FROM notifications
    WHERE $where_clause
    GROUP BY title, message, type, DATE(created_at), user_id
    HAVING id = MIN(id)
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get unread count - Simplified query
$stmt = $db->prepare("
    SELECT COUNT(*) as unread_count 
    FROM notifications
    WHERE (user_id = ? OR is_global = 1) AND is_read = 0
");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Ultra Harvest Global</title>
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
        
        .notification-item {
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .notification-item.unread {
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid #10b981;
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
                        </div>-->
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">
                            Ultra Harvest
                        </span>
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <a href="/user/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">Referrals</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">
                <i class="fas fa-bell text-emerald-400 mr-3"></i>
                Notifications
            </h1>
            <p class="text-xl text-gray-300">Stay updated with your account activities</p>
        </div>

        <!-- Filter Tabs -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex flex-wrap gap-3 mb-4">
                    <a href="?filter=all" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'all' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-list mr-2"></i>All Notifications
                    </a>
                    <a href="?filter=unread" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'unread' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-envelope mr-2"></i>Unread <?php echo $unread_count > 0 ? "($unread_count)" : ''; ?>
                    </a>
                    <a href="?filter=success" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'success' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-check-circle mr-2"></i>Success
                    </a>
                    <a href="?filter=info" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'info' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-info-circle mr-2"></i>Info
                    </a>
                    <a href="?filter=warning" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'warning' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Warnings
                    </a>
                </div>

                <?php if ($unread_count > 0): ?>
                <div class="flex justify-end">
                    <form method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-check-double mr-2"></i>Mark All as Read
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Notifications List -->
        <section class="glass-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white">
                    <?php 
                    $filter_names = [
                        'all' => 'All Notifications',
                        'unread' => 'Unread Notifications',
                        'success' => 'Success Notifications',
                        'info' => 'Info Notifications',
                        'warning' => 'Warning Notifications',
                        'error' => 'Error Notifications'
                    ];
                    echo $filter_names[$filter] ?? 'Notifications';
                    ?>
                    <span class="text-gray-400 text-base font-normal ml-2">(<?php echo number_format($total_records); ?> total)</span>
                </h2>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-bell-slash text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No notifications found</h3>
                    <p class="text-gray-500">
                        <?php if ($filter === 'unread'): ?>
                            You're all caught up! No unread notifications.
                        <?php else: ?>
                            You haven't received any notifications yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item p-4 rounded-lg <?php echo $notification['is_read'] ? 'bg-gray-800/30' : 'unread bg-gray-800/50'; ?>" data-notification-id="<?php echo $notification['id']; ?>">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-4 flex-1">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="fas <?php 
                                    echo match($notification['type']) {
                                        'success' => 'fa-check-circle text-emerald-400',
                                        'warning' => 'fa-exclamation-triangle text-yellow-400',
                                        'error' => 'fa-exclamation-circle text-red-400',
                                        default => 'fa-info-circle text-blue-400'
                                    };
                                    ?> text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <h3 class="font-semibold text-white"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-300 mb-3 leading-relaxed"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                                        <span>
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php echo timeAgo($notification['created_at']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </span>
                                        <span class="px-2 py-1 rounded text-xs font-medium <?php 
                                        echo match($notification['type']) {
                                            'success' => 'bg-emerald-500/20 text-emerald-400',
                                            'warning' => 'bg-yellow-500/20 text-yellow-400',
                                            'error' => 'bg-red-500/20 text-red-400',
                                            default => 'bg-blue-500/20 text-blue-400'
                                        };
                                        ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 ml-4">
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="p-2 text-gray-400 hover:text-emerald-400 transition" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="p-2 text-gray-600" title="Already read">
                                        <i class="fas fa-check-double"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
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

        <!-- Notification Settings -->
        <section class="mt-8">
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-4">Notification Preferences</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a href="/user/settings.php#notifications" class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-envelope text-blue-400"></i>
                            <span class="text-white">Email Notifications</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                    <a href="/user/settings.php#notifications" class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-mobile-alt text-green-400"></i>
                            <span class="text-white">SMS Notifications</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                    <a href="/user/settings.php#notifications" class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-bell text-yellow-400"></i>
                            <span class="text-white">Push Notifications</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                </div>
            </div>
        </section>
    </main>

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
            <a href="/user/transactions.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-receipt text-xl mb-1"></i>
                <span class="text-xs">History</span>
            </a>
            <a href="/user/profile.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-user text-xl mb-1"></i>
                <span class="text-xs">Profile</span>
            </a>
        </div>
    </div>

    <script>
        // Auto-refresh page every 60 seconds to show new notifications
        setInterval(function() {
            // Only refresh if user is on the 'all' or 'unread' filter
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
            if (currentFilter === 'all' || currentFilter === 'unread') {
                // Check for new notifications without full page reload
                fetch('/api/get-unread-count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update page title with unread count
                        const unreadCount = data.count;
                        if (unreadCount > 0) {
                            document.title = `(${unreadCount}) Notifications - Ultra Harvest Global`;
                        } else {
                            document.title = 'Notifications - Ultra Harvest Global';
                        }
                        
                        // Update filter badge if on unread filter
                        if (currentFilter === 'unread') {
                            const unreadFilterBtn = document.querySelector('a[href="?filter=unread"]');
                            if (unreadFilterBtn) {
                                const btnText = unreadFilterBtn.innerHTML;
                                const newText = btnText.replace(/\(\d+\)/, unreadCount > 0 ? `(${unreadCount})` : '');
                                if (unreadCount > 0 && !btnText.includes('(')) {
                                    unreadFilterBtn.innerHTML = newText + ` (${unreadCount})`;
                                } else {
                                    unreadFilterBtn.innerHTML = newText;
                                }
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking for new notifications:', error);
                });
            }
        }, 60000);

        // Mark notification as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't trigger if clicking on the mark-as-read button
                if (e.target.closest('form')) {
                    return;
                }
                
                const notificationId = this.getAttribute('data-notification-id');
                const isUnread = this.classList.contains('unread');
                
                if (isUnread) {
                    fetch('/api/mark-notification-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            notification_id: parseInt(notificationId)
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.remove('unread');
                            this.classList.add('bg-gray-800/30');
                            this.classList.remove('bg-gray-800/50');
                            
                            // Remove the unread dot
                            const unreadDot = this.querySelector('.w-2.h-2.bg-emerald-400');
                            if (unreadDot) {
                                unreadDot.remove();
                            }
                            
                            // Replace mark-as-read button with read icon
                            const markReadForm = this.querySelector('form');
                            if (markReadForm) {
                                markReadForm.innerHTML = '<span class="p-2 text-gray-600" title="Already read"><i class="fas fa-check-double"></i></span>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error marking notification as read:', error);
                    });
                }
            });
        });

        // Show success message after form submission
        <?php if (isset($_POST['action'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const successMsg = document.createElement('div');
            successMsg.className = 'fixed top-4 right-4 bg-emerald-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
            successMsg.innerHTML = '<i class="fas fa-check mr-2"></i>Notifications updated successfully';
            document.body.appendChild(successMsg);
            
            setTimeout(() => {
                successMsg.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                successMsg.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(successMsg);
                }, 300);
            }, 3000);
        });
        <?php endif; ?>
    </script>
</body>
</html>