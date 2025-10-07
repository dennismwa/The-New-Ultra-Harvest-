<?php
/**
 * M-Pesa B2C Timeout Handler
 * Handles timeout callbacks for withdrawal/B2C payments
 */

require_once '../config/database.php';

header('Content-Type: application/json');

// Create logs directory
$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Get callback data
$callback_data = file_get_contents('php://input');

// Log timeout
$log_entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'type' => 'B2C_TIMEOUT',
    'raw_data' => $callback_data
];

file_put_contents($log_dir . '/mpesa_b2c_timeouts.log', json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

try {
    $callback_json = json_decode($callback_data, true);
    
    if ($callback_json) {
        $result = $callback_json['Result'] ?? null;
        
        if ($result) {
            $conversation_id = $result['ConversationID'] ?? '';
            $result_desc = $result['ResultDesc'] ?? 'Transaction timeout';
            
            // Find and update transaction
            $stmt = $db->prepare("
                SELECT t.*, u.full_name
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE t.mpesa_request_id = ?
                AND t.type = 'withdrawal'
                AND t.status = 'pending'
            ");
            $stmt->execute([$conversation_id]);
            $transaction = $stmt->fetch();
            
            if ($transaction) {
                $db->beginTransaction();
                
                // Mark as failed
                $stmt = $db->prepare("
                    UPDATE transactions
                    SET status = 'failed',
                        description = CONCAT(description, ' - Timeout: ', ?),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$result_desc, $transaction['id']]);
                
                // Refund wallet
                $stmt = $db->prepare("
                    UPDATE users
                    SET wallet_balance = wallet_balance + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                
                // Notify user
                sendNotification(
                    $transaction['user_id'],
                    'Withdrawal Timeout',
                    "Your withdrawal of " . formatMoney($transaction['amount']) . " timed out. Funds have been returned to your wallet. Please try again.",
                    'warning'
                );
                
                $db->commit();
                
                error_log("B2C Timeout: User {$transaction['full_name']}, Amount: {$transaction['amount']}");
            }
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Timeout acknowledged'
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("B2C Timeout Error: " . $e->getMessage());
    
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Error logged'
    ]);
}
?>