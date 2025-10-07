<?php
/**
 * M-Pesa B2C Result Handler
 * Handles callbacks for withdrawal/B2C payment results
 */

require_once '../config/database.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Create logs directory
$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Get callback data
$callback_data = file_get_contents('php://input');
$headers = getallheaders();

// Log incoming request
$log_entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'type' => 'B2C_RESULT',
    'headers' => $headers,
    'raw_data' => $callback_data,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

file_put_contents($log_dir . '/mpesa_b2c_results.log', json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

try {
    if (empty($callback_data)) {
        throw new Exception('Empty callback data received');
    }
    
    $callback_json = json_decode($callback_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }
    
    // Extract result data
    $result = $callback_json['Result'] ?? null;
    
    if (!$result) {
        throw new Exception('Invalid B2C result structure');
    }
    
    $result_type = $result['ResultType'] ?? '';
    $result_code = $result['ResultCode'] ?? '';
    $result_desc = $result['ResultDesc'] ?? '';
    $conversation_id = $result['ConversationID'] ?? '';
    $originator_conversation_id = $result['OriginatorConversationID'] ?? '';
    $transaction_id = $result['TransactionID'] ?? '';
    
    error_log("B2C Result: ConversationID=$conversation_id, ResultCode=$result_code, ResultDesc=$result_desc");
    
    // Find transaction by conversation ID or originator conversation ID
    $stmt = $db->prepare("
        SELECT t.*, u.full_name, u.email, u.phone
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE (t.mpesa_request_id = ? OR t.mpesa_request_id = ?) 
        AND t.type = 'withdrawal'
        AND t.status IN ('pending', 'processing')
    ");
    $stmt->execute([$conversation_id, $originator_conversation_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        error_log("B2C Result: Transaction not found for ConversationID: $conversation_id");
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Transaction not found but acknowledged']);
        exit;
    }
    
    $db->beginTransaction();
    
    if ($result_code == '0') {
        // Payment successful
        $receipt_number = '';
        $transaction_amount = 0;
        $transaction_completed_time = '';
        $receiver_party_public_name = '';
        
        // Extract result parameters
        if (isset($result['ResultParameters']['ResultParameter'])) {
            foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                switch ($param['Key']) {
                    case 'TransactionReceipt':
                        $receipt_number = $param['Value'] ?? '';
                        break;
                    case 'TransactionAmount':
                        $transaction_amount = (float)($param['Value'] ?? 0);
                        break;
                    case 'TransactionCompletedDateTime':
                        $transaction_completed_time = $param['Value'] ?? '';
                        break;
                    case 'ReceiverPartyPublicName':
                        $receiver_party_public_name = $param['Value'] ?? '';
                        break;
                }
            }
        }
        
        // Update transaction status
        $stmt = $db->prepare("
            UPDATE transactions 
            SET status = 'completed',
                mpesa_receipt = ?,
                description = CONCAT(description, ' - M-Pesa Receipt: ', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$receipt_number, $receipt_number, $transaction['id']]);
        
        // Send success notification
        sendNotification(
            $transaction['user_id'],
            'Withdrawal Completed! 💰',
            "Your withdrawal of " . formatMoney($transaction['amount']) . " has been sent to your M-Pesa successfully. Receipt: $receipt_number",
            'success'
        );
        
        $db->commit();
        
        error_log("B2C Payment Success: User {$transaction['full_name']}, Amount: {$transaction['amount']}, Receipt: $receipt_number");
        
    } else {
        // Payment failed
        $stmt = $db->prepare("
            UPDATE transactions 
            SET status = 'failed',
                description = CONCAT(description, ' - Failed: ', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$result_desc, $transaction['id']]);
        
        // Refund user wallet
        $stmt = $db->prepare("
            UPDATE users 
            SET wallet_balance = wallet_balance + ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transaction['amount'], $transaction['user_id']]);
        
        // Send failure notification
        sendNotification(
            $transaction['user_id'],
            'Withdrawal Failed',
            "Your withdrawal of " . formatMoney($transaction['amount']) . " could not be processed. Reason: $result_desc. Funds have been returned to your wallet.",
            'error'
        );
        
        $db->commit();
        
        error_log("B2C Payment Failed: User {$transaction['full_name']}, Amount: {$transaction['amount']}, Reason: $result_desc");
    }
    
    // Respond to M-Pesa
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Result processed successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("B2C Result Error: " . $e->getMessage());
    
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Error logged but acknowledged'
    ]);
}
?>