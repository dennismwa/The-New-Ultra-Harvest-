<?php
require_once '../config/database.php';
requireAdmin();

$error = '';
$success = '';

// Handle ticket actions
if ($_POST && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        
        switch ($action) {
            case 'update_status':
                $new_status = $_POST['status'] ?? '';
                $admin_response = trim($_POST['admin_response'] ?? '');
                
                if (!in_array($new_status, ['open', 'in_progress', 'resolved', 'closed'])) {
                    $error = 'Invalid status selected.';
                    break;
                }
                
                try {
                    $db->beginTransaction();
                    
                    // Update ticket status
                    $stmt = $db->prepare("
                        UPDATE support_tickets 
                        SET status = ?, updated_at = NOW(), admin_id = ?, last_response_by = 'admin'
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_status, $_SESSION['user_id'], $ticket_id]);
                    
                    // Add response if provided
                    if ($admin_response) {
                        $stmt = $db->prepare("
                            INSERT INTO ticket_responses (ticket_id, user_id, response_text, is_admin, created_at) 
                            VALUES (?, ?, ?, 1, NOW())
                        ");
                        $stmt->execute([$ticket_id, $_SESSION['user_id'], $admin_response]);
                    }
                    
                    // Get ticket details for notification
                    $stmt = $db->prepare("
                        SELECT t.*, u.full_name, u.email 
                        FROM support_tickets t 
                        JOIN users u ON t.user_id = u.id 
                        WHERE t.id = ?
                    ");
                    $stmt->execute([$ticket_id]);
                    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($ticket) {
                        // Send notification to user
                        $status_message = match($new_status) {
                            'in_progress' => 'Your support ticket is now being worked on by our team.',
                            'resolved' => 'Your support ticket has been resolved. Please check the response.',
                            'closed' => 'Your support ticket has been closed.',
                            default => 'Your support ticket status has been updated.'
                        };
                        
                        sendNotification(
                            $ticket['user_id'],
                            'Ticket Update - #' . $ticket['ticket_number'],
                            $status_message,
                            'info'
                        );
                    }
                    
                    $db->commit();
                    $success = 'Ticket updated successfully.';
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Failed to update ticket: ' . $e->getMessage();
                }
                break;
                
            case 'add_response':
                $response_text = trim($_POST['response_text'] ?? '');
                
                if (empty($response_text)) {
                    $error = 'Please provide a response.';
                    break;
                }
                
                try {
                    $db->beginTransaction();
                    
                    // Add response
                    $stmt = $db->prepare("
                        INSERT INTO ticket_responses (ticket_id, user_id, response_text, is_admin, created_at) 
                        VALUES (?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([$ticket_id, $_SESSION['user_id'], $response_text]);
                    
                    // Update ticket
                    $stmt = $db->prepare("
                        UPDATE support_tickets 
                        SET status = CASE WHEN status = 'closed' THEN 'in_progress' ELSE status END,
                            updated_at = NOW(), admin_id = ?, last_response_by = 'admin'
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $ticket_id]);
                    
                    // Get ticket details for notification
                    $stmt = $db->prepare("
                        SELECT user_id, ticket_number 
                        FROM support_tickets 
                        WHERE id = ?
                    ");
                    $stmt->execute([$ticket_id]);
                    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($ticket) {
                        sendNotification(
                            $ticket['user_id'],
                            'New Response - Ticket #' . $ticket['ticket_number'],
                            'An admin has responded to your support ticket.',
                            'info'
                        );
                    }
                    
                    $db->commit();
                    $success = 'Response added successfully.';
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Failed to add response: ' . $e->getMessage();
                }
                break;
                
            case 'assign_ticket':
                $assign_to = intval($_POST['assign_to'] ?? 0);
                
                try {
                    $stmt = $db->prepare("
                        UPDATE support_tickets 
                        SET admin_id = ?, status = CASE WHEN status = 'open' THEN 'in_progress' ELSE status END, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$assign_to, $ticket_id]);
                    
                    $success = 'Ticket assigned successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to assign ticket: ' . $e->getMessage();
                }
                break;

            case 'create_ticket':
                $user_id = intval($_POST['user_id'] ?? 0);
                $subject = trim($_POST['subject'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $priority = $_POST['priority'] ?? 'medium';
                $category = $_POST['category'] ?? 'general';
                
                if ($user_id && $subject && $message) {
                    try {
                        // Generate ticket number
                        $stmt = $db->query("SELECT MAX(id) as max_id FROM support_tickets");
                        $result = $stmt->fetch();
                        $next_id = ($result['max_id'] ?? 0) + 1;
                        $ticket_number = 'TKT' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
                        
                        $stmt = $db->prepare("
                            INSERT INTO support_tickets (user_id, ticket_number, subject, message, priority, category, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
                        ");
                        
                        if ($stmt->execute([$user_id, $ticket_number, $subject, $message, $priority, $category])) {
                            sendNotification(
                                $user_id,
                                'Support Ticket Created',
                                "Your support ticket #{$ticket_number} has been created and will be reviewed by our team.",
                                'info'
                            );
                            $success = 'Ticket created successfully.';
                        } else {
                            $error = 'Failed to create ticket.';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to create ticket: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Please fill in all required fields.';
                }
                break;

            case 'delete_ticket':
                try {
                    $stmt = $db->prepare("DELETE FROM support_tickets WHERE id = ?");
                    if ($stmt->execute([$ticket_id])) {
                        $success = 'Ticket deleted successfully.';
                    } else {
                        $error = 'Failed to delete ticket.';
                    }
                } catch (Exception $e) {
                    $error = 'Failed to delete ticket: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_conditions = ["1=1"];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

if ($category_filter !== 'all') {
    $where_conditions[] = "t.category = ?";
    $params[] = $category_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR t.subject LIKE ? OR t.ticket_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get tickets
$tickets = [];
$total_records = 0;
$total_pages = 1;

try {
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM support_tickets t 
        JOIN users u ON t.user_id = u.id 
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_records = $result ? $result['total'] : 0;
    $total_pages = ceil($total_records / $limit);

    // Get tickets
    $sql = "
        SELECT t.*, u.full_name, u.email, u.phone,
               admin.full_name as admin_name,
               (SELECT COUNT(*) FROM ticket_responses WHERE ticket_id = t.id) as response_count
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN users admin ON t.admin_id = admin.id
        WHERE $where_clause
        ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            t.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load tickets: ' . $e->getMessage();
}

// Get statistics
$stats = [
    'total_tickets' => 0,
    'open_tickets' => 0,
    'in_progress_tickets' => 0,
    'urgent_tickets' => 0,
    'unassigned_tickets' => 0,
    'avg_response_time' => 0
];

try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN priority = 'urgent' AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as urgent_tickets,
            SUM(CASE WHEN admin_id IS NULL AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as unassigned_tickets
        FROM support_tickets
    ";
    $stmt = $db->query($stats_sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = array_merge($stats, $result);
    }
} catch (Exception $e) {
    // Use default stats
}

// Get admin users for assignment
$admins = [];
try {
    $stmt = $db->query("SELECT id, full_name FROM users WHERE is_admin = 1 ORDER BY full_name");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Continue without admin list
}

// Get users for ticket creation
$users = [];
try {
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE is_admin = 0 ORDER BY full_name LIMIT 100");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Continue without user list
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - Ultra Harvest Admin</title>
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
        
        .ticket-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .priority-urgent { border-left: 4px solid #ef4444; }
        .priority-high { border-left: 4px solid #f97316; }
        .priority-medium { border-left: 4px solid #eab308; }
        .priority-low { border-left: 4px solid #22c55e; }
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
                       <!-- <div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
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
                        <a href="/admin/tickets.php" class="text-emerald-400 font-medium">Support Tickets</a>
                        <a href="/admin/system-health.php" class="text-gray-300 hover:text-emerald-400 transition">System Health</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <?php if ($stats['urgent_tickets'] > 0): ?>
                    <div class="flex items-center space-x-2 bg-red-600/20 border border-red-500/50 rounded-full px-3 py-1">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                        <span class="text-red-300 text-sm font-medium"><?php echo $stats['urgent_tickets']; ?> Urgent</span>
                    </div>
                    <?php endif; ?>
                    <button onclick="openCreateModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-plus mr-2"></i>New Ticket
                    </button>
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
                <h1 class="text-3xl font-bold text-white">Support Tickets</h1>
                <p class="text-gray-400">Manage customer support requests and inquiries</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-emerald-400"><?php echo number_format($stats['total_tickets']); ?></p>
                <p class="text-gray-400 text-sm">Total Tickets</p>
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
                    <p class="text-blue-400 text-2xl font-bold"><?php echo number_format($stats['open_tickets']); ?></p>
                    <p class="text-gray-400 text-sm">Open</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-yellow-400 text-2xl font-bold"><?php echo number_format($stats['in_progress_tickets']); ?></p>
                    <p class="text-gray-400 text-sm">In Progress</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-red-400 text-2xl font-bold"><?php echo number_format($stats['urgent_tickets']); ?></p>
                    <p class="text-gray-400 text-sm">Urgent</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-purple-400 text-2xl font-bold"><?php echo number_format($stats['unassigned_tickets']); ?></p>
                    <p class="text-gray-400 text-sm">Unassigned</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-emerald-400 text-2xl font-bold"><?php echo number_format($stats['total_tickets']); ?></p>
                    <p class="text-gray-400 text-sm">Total</p>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="glass-card rounded-xl p-6 mb-8">
            <form method="GET" class="grid md:grid-cols-5 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                        <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                        <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Category</label>
                    <select name="category" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                        <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <option value="technical" <?php echo $category_filter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                        <option value="billing" <?php echo $category_filter === 'billing' ? 'selected' : ''; ?>>Billing</option>
                        <option value="account" <?php echo $category_filter === 'account' ? 'selected' : ''; ?>>Account</option>
                        <option value="general" <?php echo $category_filter === 'general' ? 'selected' : ''; ?>>General</option>
                    </select>
                </div>

                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Ticket #, user, subject..."
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

        <!-- Tickets Table -->
        <section class="glass-card rounded-xl overflow-hidden">
            <?php if (!empty($tickets)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="text-left p-4 text-gray-400 font-medium">Ticket</th>
                                <th class="text-left p-4 text-gray-400 font-medium">User</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Priority</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Status</th>
                                <th class="text-left p-4 text-gray-400 font-medium">Subject</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Assigned To</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Responses</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Created</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                            <tr class="ticket-row border-b border-gray-800 priority-<?php echo $ticket['priority']; ?>">
                                <!-- Ticket Column -->
                                <td class="p-4">
                                    <div>
                                        <p class="font-bold text-emerald-400">#<?php echo htmlspecialchars($ticket['ticket_number'] ?? 'TKT' . str_pad($ticket['id'], 6, '0', STR_PAD_LEFT)); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo ucfirst($ticket['category']); ?></p>
                                    </div>
                                </td>
                                
                                <!-- User Column -->
                                <td class="p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-xs font-bold text-white">
                                            <?php echo strtoupper(substr($ticket['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-white text-sm"><?php echo htmlspecialchars($ticket['full_name']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($ticket['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Priority Column -->
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($ticket['priority']) {
                                            'urgent' => 'bg-red-500/20 text-red-400',
                                            'high' => 'bg-orange-500/20 text-orange-400',
                                            'medium' => 'bg-yellow-500/20 text-yellow-400',
                                            'low' => 'bg-green-500/20 text-green-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </td>
                                
                                <!-- Status Column -->
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($ticket['status']) {
                                            'open' => 'bg-blue-500/20 text-blue-400',
                                            'in_progress' => 'bg-yellow-500/20 text-yellow-400',
                                            'resolved' => 'bg-emerald-500/20 text-emerald-400',
                                            'closed' => 'bg-gray-500/20 text-gray-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                </td>
                                
                                <!-- Subject Column -->
                                <td class="p-4">
                                    <div class="max-w-xs">
                                        <p class="text-white text-sm font-medium"><?php echo htmlspecialchars(substr($ticket['subject'], 0, 40)) . (strlen($ticket['subject']) > 40 ? '...' : ''); ?></p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars(substr($ticket['message'], 0, 60)) . '...'; ?></p>
                                    </div>
                                </td>
                                
                                <!-- Assigned To Column -->
                                <td class="p-4 text-center">
                                    <?php if ($ticket['admin_name']): ?>
                                        <span class="text-sm text-blue-400"><?php echo htmlspecialchars($ticket['admin_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Responses Column -->
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 bg-purple-500/20 text-purple-400 rounded-full text-xs font-medium">
                                        <?php echo $ticket['response_count']; ?>
                                    </span>
                                </td>
                                
                                <!-- Created Column -->
                                <td class="p-4 text-center text-gray-300 text-sm">
                                    <p><?php echo date('M j', strtotime($ticket['created_at'])); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($ticket['created_at'])); ?></p>
                                </td>
                                
                                <!-- Actions Column -->
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <button onclick="viewTicket(<?php echo $ticket['id']; ?>)" 
                                                class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs transition">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="updateTicket(<?php echo $ticket['id']; ?>, '<?php echo $ticket['status']; ?>')" 
                                                class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs transition">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="addResponse(<?php echo $ticket['id']; ?>)"
                                                class="px-2 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-xs transition">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                        <?php if (!$ticket['admin_id']): ?>
                                        <button onclick="assignTicket(<?php echo $ticket['id']; ?>)"
                                                class="px-2 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs transition">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="deleteTicket(<?php echo $ticket['id']; ?>)"
                                                class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> tickets
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&priority=<?php echo htmlspecialchars($priority_filter); ?>&category=<?php echo htmlspecialchars($category_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>" 
                                   class="px-3 py-2 bg-gray-800 text-gray-300 rounded hover:bg-gray-700 transition">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&priority=<?php echo htmlspecialchars($priority_filter); ?>&category=<?php echo htmlspecialchars($category_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                   class="px-3 py-2 rounded transition <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&priority=<?php echo htmlspecialchars($priority_filter); ?>&category=<?php echo htmlspecialchars($category_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>" 
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
                    <i class="fas fa-ticket-alt text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No tickets found</h3>
                    <p class="text-gray-500">No tickets match your current filters</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Create Ticket Modal -->
    <div id="createTicketModal" class="modal">
        <div class="glass-card rounded-xl p-6 max-w-md w-full m-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Create New Ticket</h3>
                <button type="button" onclick="closeModal('createTicketModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create_ticket">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">User *</label>
                        <select name="user_id" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']) . ' (' . htmlspecialchars($user['email']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Subject *</label>
                        <input 
                            type="text" 
                            name="subject" 
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Brief description of the issue"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Priority *</label>
                        <select name="priority" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Category *</label>
                        <select name="category" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                            <option value="general" selected>General</option>
                            <option value="technical">Technical</option>
                            <option value="billing">Billing</option>
                            <option value="account">Account</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Message *</label>
                        <textarea 
                            name="message" 
                            rows="4"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Detailed description of the issue..."
                            required
                        ></textarea>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-plus mr-2"></i>Create Ticket
                    </button>
                    <button type="button" onclick="closeModal('createTicketModal')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Ticket Modal -->
    <div id="viewTicketModal" class="modal">
        <div class="glass-card rounded-xl p-0 max-w-4xl w-full m-4 max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-700">
                <h3 class="text-xl font-bold text-white">Ticket Details</h3>
                <button type="button" onclick="closeModal('viewTicketModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="ticketContent" class="overflow-y-auto max-h-[70vh]">
                <!-- Ticket content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Update Ticket Modal -->
    <div id="updateTicketModal" class="modal">
        <div class="glass-card rounded-xl p-6 max-w-md w-full m-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Update Ticket</h3>
                <button type="button" onclick="closeModal('updateTicketModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="ticket_id" id="updateTicketId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status *</label>
                        <select name="status" id="statusSelect" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none" required>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Admin Response</label>
                        <textarea 
                            name="admin_response" 
                            rows="4"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Optional response to the user..."
                        ></textarea>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        Update Ticket
                    </button>
                    <button type="button" onclick="closeModal('updateTicketModal')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Ticket Modal -->
    <div id="assignTicketModal" class="modal">
        <div class="glass-card rounded-xl p-6 max-w-md w-full m-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Assign Ticket</h3>
                <button type="button" onclick="closeModal('assignTicketModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="assign_ticket">
                <input type="hidden" name="ticket_id" id="assignTicketId">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Assign To *</label>
                    <select name="assign_to" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none" required>
                        <option value="">Select Admin</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>"><?php echo htmlspecialchars($admin['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition">
                        Assign Ticket
                    </button>
                    <button type="button" onclick="closeModal('assignTicketModal')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Response Modal -->
    <div id="responseModal" class="modal">
        <div class="glass-card rounded-xl p-6 max-w-lg w-full m-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Add Response</h3>
                <button type="button" onclick="closeModal('responseModal')" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_response">
                <input type="hidden" name="ticket_id" id="responseTicketId">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Response *</label>
                    <textarea 
                        name="response_text" 
                        rows="6"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                        placeholder="Write your response to the user..."
                        required
                    ></textarea>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        Send Response
                    </button>
                    <button type="button" onclick="closeModal('responseModal')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
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
        function openCreateModal() {
            document.getElementById('createTicketModal').classList.add('show');
            currentModal = 'createTicketModal';
        }

        function viewTicket(ticketId) {
            // Load ticket details via AJAX
            fetch(`/admin/api/ticket-details.php?id=${ticketId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('ticketContent').innerHTML = html;
                    document.getElementById('viewTicketModal').classList.add('show');
                    currentModal = 'viewTicketModal';
                })
                .catch(error => {
                    console.error('Error loading ticket details:', error);
                    alert('Failed to load ticket details');
                });
        }

        function updateTicket(ticketId, currentStatus) {
            document.getElementById('updateTicketId').value = ticketId;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('updateTicketModal').classList.add('show');
            currentModal = 'updateTicketModal';
        }

        function assignTicket(ticketId) {
            document.getElementById('assignTicketId').value = ticketId;
            document.getElementById('assignTicketModal').classList.add('show');
            currentModal = 'assignTicketModal';
        }

        function addResponse(ticketId) {
            document.getElementById('responseTicketId').value = ticketId;
            document.getElementById('responseModal').classList.add('show');
            currentModal = 'responseModal';
        }

        function deleteTicket(ticketId) {
            if (confirm('Are you sure you want to delete this ticket? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete_ticket">
                    <input type="hidden" name="ticket_id" value="${ticketId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

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

            // Form validation
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const action = this.querySelector('input[name="action"]')?.value;
                    
                    if (action === 'update_status') {
                        const status = this.querySelector('select[name="status"]').value;
                        if (status === 'closed' || status === 'resolved') {
                            if (!confirm(`Are you sure you want to mark this ticket as ${status}?`)) {
                                e.preventDefault();
                                return false;
                            }
                        }
                    }
                });
            });
        });

        // Auto-refresh for high priority tickets
        <?php if ($stats['urgent_tickets'] > 0): ?>
        setInterval(function() {
            if (!currentModal) {
                // Check for new urgent tickets
                fetch('/admin/api/urgent-tickets-count.php')
                    .then(response => response.json())
                    .then(data => {
                        const currentUrgent = <?php echo $stats['urgent_tickets']; ?>;
                        if (data.urgent_count > currentUrgent) {
                            // Show notification for new urgent tickets
                            showNotification('New urgent ticket received!', 'warning');
                        }
                    })
                    .catch(error => console.error('Failed to check urgent tickets:', error));
            }
        }, 60000); // Check every minute
        <?php endif; ?>

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/50 text-emerald-300' :
                type === 'warning' ? 'bg-yellow-500/20 border border-yellow-500/50 text-yellow-300' :
                type === 'error' ? 'bg-red-500/20 border border-red-500/50 text-red-300' :
                'bg-blue-500/20 border border-blue-500/50 text-blue-300'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' :
                        type === 'warning' ? 'fa-exclamation-triangle' :
                        type === 'error' ? 'fa-times-circle' :
                        'fa-info-circle'
                    } mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Quick status change
        function quickStatusChange(ticketId, newStatus) {
            if (confirm(`Change ticket status to ${newStatus}?`)) {
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                formData.append('action', 'update_status');
                formData.append('ticket_id', ticketId);
                formData.append('status', newStatus);

                fetch('tickets.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error updating ticket:', error);
                    alert('Failed to update ticket status');
                });
            }
        }

        // Priority badge animation
        function animatePriorityBadges() {
            document.querySelectorAll('.ticket-row').forEach(row => {
                if (row.classList.contains('priority-urgent')) {
                    const badge = row.querySelector('.bg-red-500\\/20');
                    if (badge) {
                        badge.style.animation = 'pulse 2s infinite';
                    }
                }
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            animatePriorityBadges();
        });

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>