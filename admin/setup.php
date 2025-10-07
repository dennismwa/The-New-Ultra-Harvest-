<?php
/**
 * Database Setup Script for Ticket System
 * Run this script once to create missing tables and update existing ones
 * admin/setup.php
 */

require_once '../config/database.php';
requireAdmin();

$errors = [];
$success = [];

try {
    // Start transaction
    $db->beginTransaction();

    // Check if ticket_responses table exists
    $stmt = $db->query("SHOW TABLES LIKE 'ticket_responses'");
    if ($stmt->rowCount() == 0) {
        // Create ticket_responses table
        $sql = "
        CREATE TABLE `ticket_responses` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `ticket_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `response_text` text NOT NULL,
          `is_admin` tinyint(1) DEFAULT 0,
          `created_at` timestamp NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `ticket_id` (`ticket_id`),
          KEY `user_id` (`user_id`),
          KEY `idx_ticket_responses_ticket` (`ticket_id`, `created_at`),
          CONSTRAINT `ticket_responses_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
          CONSTRAINT `ticket_responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        
        $db->exec($sql);
        $success[] = "Created ticket_responses table";
    } else {
        $success[] = "ticket_responses table already exists";
    }

    // Check and add missing columns to support_tickets table
    $columns_to_add = [
        'ticket_number' => "ADD COLUMN `ticket_number` varchar(20) DEFAULT NULL AFTER `id`",
        'category' => "ADD COLUMN `category` enum('technical','billing','account','general') DEFAULT 'general' AFTER `priority`",
        'admin_id' => "ADD COLUMN `admin_id` int(11) DEFAULT NULL AFTER `responded_by`",
        'last_response_by' => "ADD COLUMN `last_response_by` enum('user','admin') DEFAULT 'user' AFTER `admin_id`"
    ];

    foreach ($columns_to_add as $column => $sql_fragment) {
        // Check if column exists
        $stmt = $db->query("SHOW COLUMNS FROM support_tickets LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE support_tickets $sql_fragment");
            $success[] = "Added column '$column' to support_tickets table";
        } else {
            $success[] = "Column '$column' already exists in support_tickets table";
        }
    }

    // Make ticket_number unique if it exists but isn't unique
    try {
        // First, populate ticket_number for existing tickets
        $db->exec("UPDATE support_tickets SET ticket_number = CONCAT('TKT', LPAD(id, 6, '0')) WHERE ticket_number IS NULL OR ticket_number = ''");
        
        // Then add unique constraint if it doesn't exist
        $stmt = $db->query("SHOW INDEX FROM support_tickets WHERE Key_name = 'ticket_number'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE support_tickets ADD UNIQUE INDEX `ticket_number` (`ticket_number`)");
            $success[] = "Added unique index for ticket_number";
        }
    } catch (Exception $e) {
        // Unique constraint might already exist or there might be duplicate values
        $success[] = "ticket_number constraint handling: " . $e->getMessage();
    }

    // Add missing indexes
    $indexes_to_add = [
        'idx_tickets_status_priority' => "ADD INDEX `idx_tickets_status_priority` (`status`, `priority`)",
        'idx_tickets_category' => "ADD INDEX `idx_tickets_category` (`category`)"
    ];

    foreach ($indexes_to_add as $index_name => $sql_fragment) {
        try {
            $stmt = $db->query("SHOW INDEX FROM support_tickets WHERE Key_name = '$index_name'");
            if ($stmt->rowCount() == 0) {
                $db->exec("ALTER TABLE support_tickets $sql_fragment");
                $success[] = "Added index '$index_name'";
            } else {
                $success[] = "Index '$index_name' already exists";
            }
        } catch (Exception $e) {
            $errors[] = "Failed to add index '$index_name': " . $e->getMessage();
        }
    }

    // Add foreign key constraint for admin_id if it doesn't exist
    try {
        $stmt = $db->query("
            SELECT * FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = 'support_tickets' 
            AND COLUMN_NAME = 'admin_id' 
            AND REFERENCED_TABLE_NAME = 'users'
        ");
        
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE support_tickets ADD CONSTRAINT `support_tickets_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
            $success[] = "Added foreign key constraint for admin_id";
        } else {
            $success[] = "Foreign key constraint for admin_id already exists";
        }
    } catch (Exception $e) {
        $errors[] = "Failed to add foreign key constraint for admin_id: " . $e->getMessage();
    }

    // Create directory for API files if it doesn't exist
    $api_dir = __DIR__ . '/api';
    if (!is_dir($api_dir)) {
        mkdir($api_dir, 0755, true);
        $success[] = "Created admin/api directory";
    } else {
        $success[] = "admin/api directory already exists";
    }

    // Ensure logs directory exists
    $logs_dir = dirname(__DIR__) . '/logs';
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
        $success[] = "Created logs directory";
    } else {
        $success[] = "logs directory already exists";
    }

    // Create some sample admin users if none exist
    $stmt = $db->query("SELECT COUNT(*) as admin_count FROM users WHERE is_admin = 1");
    $result = $stmt->fetch();
    
    if ($result['admin_count'] == 0) {
        // Create default admin user
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $referral_code = 'ADMIN' . str_pad(1, 3, '0', STR_PAD_LEFT);
        
        $stmt = $db->prepare("
            INSERT INTO users (email, password, full_name, phone, referral_code, is_admin, email_verified, status) 
            VALUES (?, ?, ?, ?, ?, 1, 1, 'active')
        ");
        $stmt->execute([
            'admin@ultraharvest.com',
            $password,
            'System Administrator',
            '+254700000000',
            $referral_code
        ]);
        
        $success[] = "Created default admin user (admin@ultraharvest.com / admin123)";
    }

    // Commit transaction
    $db->commit();
    $success[] = "All database updates completed successfully!";

} catch (Exception $e) {
    // Rollback on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $errors[] = "Setup failed: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Ultra Harvest Admin</title>
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
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-seedling text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">
                    Ultra Harvest Database Setup
                </h1>
                <p class="text-gray-400 mt-2">Setting up ticket management system</p>
            </div>

            <?php if (!empty($success)): ?>
            <div class="glass-card rounded-xl p-6 mb-6">
                <h2 class="text-xl font-bold text-emerald-400 mb-4">
                    <i class="fas fa-check-circle mr-2"></i>Success
                </h2>
                <ul class="space-y-2">
                    <?php foreach ($success as $message): ?>
                    <li class="flex items-start space-x-2">
                        <i class="fas fa-check text-emerald-400 mt-1 text-sm"></i>
                        <span class="text-gray-300"><?php echo htmlspecialchars($message); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="glass-card rounded-xl p-6 mb-6">
                <h2 class="text-xl font-bold text-red-400 mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Errors
                </h2>
                <ul class="space-y-2">
                    <?php foreach ($errors as $error): ?>
                    <li class="flex items-start space-x-2">
                        <i class="fas fa-times text-red-400 mt-1 text-sm"></i>
                        <span class="text-gray-300"><?php echo htmlspecialchars($error); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-4">Setup Complete</h2>
                <p class="text-gray-300 mb-6">
                    The ticket management system has been set up. You can now:
                </p>
                
                <div class="grid md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <h3 class="font-semibold text-emerald-400 mb-2">
                            <i class="fas fa-ticket-alt mr-2"></i>Manage Tickets
                        </h3>
                        <p class="text-sm text-gray-400">Create, view, and respond to support tickets</p>
                    </div>
                    
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <h3 class="font-semibold text-blue-400 mb-2">
                            <i class="fas fa-users mr-2"></i>Assign Tickets
                        </h3>
                        <p class="text-sm text-gray-400">Assign tickets to admin users for resolution</p>
                    </div>
                    
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <h3 class="font-semibold text-yellow-400 mb-2">
                            <i class="fas fa-comments mr-2"></i>Response System
                        </h3>
                        <p class="text-sm text-gray-400">Add responses and track conversations</p>
                    </div>
                    
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <h3 class="font-semibold text-purple-400 mb-2">
                            <i class="fas fa-chart-line mr-2"></i>Priority Management
                        </h3>
                        <p class="text-sm text-gray-400">Organize tickets by priority and status</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="/admin/" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-home mr-2"></i>Go to Dashboard
                    </a>
                    <a href="/admin/tickets.php" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-ticket-alt mr-2"></i>View Tickets
                    </a>
                    <a href="/admin/users.php" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-users mr-2"></i>Manage Users
                    </a>
                </div>
            </div>

            <div class="text-center mt-8">
                <p class="text-gray-500 text-sm">
                    Ultra Harvest Global - Ticket Management System Setup
                </p>
            </div>
        </div>
    </div>

</body>
</html>