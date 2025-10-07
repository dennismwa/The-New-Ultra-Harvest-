<?php
// File: api/notifications.php
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_notifications':
            $limit = min(20, (int)($_GET['limit'] ?? 10));
            $offset = (int)($_GET['offset'] ?? 0);
            
            $stmt = $db->prepare("
                SELECT id, title, message, type, is_read, created_at 
                FROM notifications 
                WHERE (user_id = ? OR is_global = 1) 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user_id, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format timestamps
            foreach ($notifications as &$notification) {
                $notification['time_ago'] = timeAgo($notification['created_at']);
                $notification['formatted_date'] = date('M j, Y g:i A', strtotime($notification['created_at']));
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        case 'get_unread_count':
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE (user_id = ? OR is_global = 1) AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'count' => (int)$result['count']
            ]);
            break;
            
        case 'mark_as_read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            
            if ($notification_id) {
                // Mark specific notification as read
                $stmt = $db->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE id = ? AND (user_id = ? OR is_global = 1)
                ");
                $stmt->execute([$notification_id, $user_id]);
            } else {
                // Mark all notifications as read
                $stmt = $db->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE (user_id = ? OR is_global = 1) AND is_read = 0
                ");
                $stmt->execute([$user_id]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_notification':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            
            if (!$notification_id) {
                throw new Exception('Notification ID required');
            }
            
            $stmt = $db->prepare("
                DELETE FROM notifications 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'get_notification_settings':
            $stmt = $db->prepare("
                SELECT email_notifications, sms_notifications, roi_notifications, 
                       transaction_notifications, referral_notifications 
                FROM user_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Default settings if none exist
            if (!$settings) {
                $settings = [
                    'email_notifications' => 1,
                    'sms_notifications' => 1,
                    'roi_notifications' => 1,
                    'transaction_notifications' => 1,
                    'referral_notifications' => 1
                ];
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>