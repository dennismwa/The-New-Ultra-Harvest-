<?php
/**
 * Urgent Tickets Count API
 * admin/api/urgent-tickets-count.php
 */

require_once '../../config/database.php';
requireAdmin();

// Set content type
header('Content-Type: application/json');

try {
    // Get current count of urgent tickets
    $stmt = $db->query("
        SELECT COUNT(*) as urgent_count 
        FROM support_tickets 
        WHERE priority = 'urgent' 
        AND status NOT IN ('resolved', 'closed')
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'urgent_count' => (int)($result['urgent_count'] ?? 0),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'urgent_count' => 0,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

echo json_encode($response);