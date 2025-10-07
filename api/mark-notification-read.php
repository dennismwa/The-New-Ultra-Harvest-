<?php
// File: api/mark-notification-read.php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
    exit;
}

$notification_id = (int)$input['notification_id'];

try {
    // Mark notification as read (only if it belongs to the user or is global)
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1, updated_at = NOW() 
        WHERE id = ? AND (user_id = ? OR is_global = 1)
    ");
    
    $result = $stmt->execute([$notification_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
    }
} catch (Exception $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

---

// File: api/mark-all-notifications-read.php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Mark all user's notifications as read
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1, updated_at = NOW() 
        WHERE (user_id = ? OR is_global = 1) AND is_read = 0
    ");
    
    $result = $stmt->execute([$user_id]);
    
    if ($result) {
        $affected_rows = $stmt->rowCount();
        echo json_encode([
            'success' => true, 
            'message' => 'All notifications marked as read',
            'affected_count' => $affected_rows
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
    }
} catch (Exception $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

---

// File: api/get-unread-count.php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

try {
    // Get unread notification count
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT n.id) as unread_count 
        FROM notifications n
        WHERE (n.user_id = ? OR n.is_global = 1) AND n.is_read = 0
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true, 
        'count' => (int)$result['unread_count']
    ]);
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

---

// File: api/get-notifications.php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

try {
    // Get notifications with pagination
    $stmt = $db->prepare("
        SELECT DISTINCT n.id, n.title, n.message, n.type, n.is_read, n.created_at
        FROM notifications n
        WHERE (n.user_id = ? OR n.is_global = 1) 
        ORDER BY n.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$user_id, $limit, $offset]);
    $notifications = $stmt->fetchAll();
    
    // Get total count
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT n.id) as total_count 
        FROM notifications n
        WHERE (n.user_id = ? OR n.is_global = 1)
    ");
    
    $stmt->execute([$user_id]);
    $total_count = $stmt->fetch()['total_count'];
    
    // Get unread count
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT n.id) as unread_count 
        FROM notifications n
        WHERE (n.user_id = ? OR n.is_global = 1) AND n.is_read = 0
    ");
    
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetch()['unread_count'];
    
    // Format notifications
    $formatted_notifications = array_map(function($notification) {
        return [
            'id' => (int)$notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'is_read' => (bool)$notification['is_read'],
            'created_at' => $notification['created_at'],
            'time_ago' => timeAgo($notification['created_at'])
        ];
    }, $notifications);
    
    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications,
        'pagination' => [
            'current_page' => $page,
            'total_count' => (int)$total_count,
            'unread_count' => (int)$unread_count,
            'has_more' => ($offset + $limit) < $total_count
        ]
    ]);
} catch (Exception $e) {
    error_log("Error getting notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}