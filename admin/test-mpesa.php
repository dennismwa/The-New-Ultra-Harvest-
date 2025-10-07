<?php
/**
 * Enhanced M-Pesa Integration Test File
 * Ultra Harvest Global - Complete Testing Suite
 */

require_once '../config/database.php';
require_once '../config/mpesa.php';

// Only allow admin access
requireAdmin();

$test_results = [];
$error = '';
$success = '';

if ($_POST) {
    $test_type = $_POST['test_type'] ?? '';
    
    switch ($test_type) {
        case 'connection':
            $mpesa = new MpesaIntegration();
            $result = $mpesa->testConnection();
            $test_results['connection'] = $result;
            
            if ($result['success']) {
                $success = 'M-Pesa connection test successful!';
            } else {
                $error = 'M-Pesa connection test failed: ' . $result['message'];
            }
            break;
            
        case 'stk_push':
            $phone = $_POST['test_phone'] ?? '';
            $amount = (float)($_POST['test_amount'] ?? 1);
            
            if (empty($phone) || $amount <= 0) {
                $error = 'Please provide valid phone number and amount';
            } else {
                $mpesa = new MpesaIntegration();
                
                // Create a test transaction
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, amount, status, description, phone_number) 
                    VALUES (?, 'deposit', ?, 'pending', 'Test M-Pesa payment', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $amount, $phone]);
                $test_transaction_id = $db->lastInsertId();
                
                $result = initiateMpesaPayment($phone, $amount, $test_transaction_id, 'Test Payment');
                $test_results['stk_push'] = $result;
                
                if ($result['success']) {
                    $success = 'STK Push sent successfully! Check your phone for the payment prompt. Transaction ID: ' . $test_transaction_id;
                    
                    // Store request ID for tracking
                    $stmt = $db->prepare("UPDATE transactions SET mpesa_request_id = ? WHERE id = ?");
                    $stmt->execute([$result['checkout_request_id'], $test_transaction_id]);
                } else {
                    $error = 'STK Push failed: ' . $result['message'];
                    
                    // Clean up the test transaction
                    $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
                    $stmt->execute([$test_transaction_id]);
                }
            }
            break;
            
        case 'b2c_payment':
            $phone = $_POST['test_phone'] ?? '';
            $amount = (float)($_POST['test_amount'] ?? 1);
            
            if (empty($phone) || $amount <= 0) {
                $error = 'Please provide valid phone number and amount';
            } else {
                $mpesa = new MpesaIntegration();
                
                // Create test withdrawal transaction
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, amount, status, description, phone_number) 
                    VALUES (?, 'withdrawal', ?, 'pending', 'Test B2C withdrawal', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $amount, $phone]);
                $test_transaction_id = $db->lastInsertId();
                
                $result = $mpesa->b2cPayment($phone, $amount, "TEST-$test_transaction_id", "Test withdrawal");
                $test_results['b2c_payment'] = $result;
                
                if ($result['success']) {
                    $success = 'B2C Payment initiated successfully! Transaction ID: ' . $test_transaction_id;
                    
                    // Update with conversation ID
                    if (isset($result['ConversationID'])) {
                        $stmt = $db->prepare("UPDATE transactions SET mpesa_request_id = ? WHERE id = ?");
                        $stmt->execute([$result['ConversationID'], $test_transaction_id]);
                    }
                } else {
                    $error = 'B2C Payment failed: ' . $result['message'];
                    
                    // Clean up
                    $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
                    $stmt->execute([$test_transaction_id]);
                }
            }
            break;
            
        case 'callback_test':
            // Simulate successful STK callback
            $test_callback = [
                'Body' => [
                    'stkCallback' => [
                        'MerchantRequestID' => 'test-merchant-' . time(),
                        'CheckoutRequestID' => 'test-checkout-' . time(),
                        'ResultCode' => 0,
                        'ResultDesc' => 'The service request is processed successfully.',
                        'CallbackMetadata' => [
                            'Item' => [
                                ['Name' => 'Amount', 'Value' => 1],
                                ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST' . time()],
                                ['Name' => 'TransactionDate', 'Value' => date('YmdHis')],
                                ['Name' => 'PhoneNumber', 'Value' => '254712345678']
                            ]
                        ]
                    ]
                ]
            ];
            
            $test_results['callback'] = $test_callback;
            $success = 'Callback test data generated successfully.';
            break;
            
        case 'config_check':
            $mpesa = new MpesaIntegration();
            $validation = $mpesa->validateConfiguration();
            $test_results['config_check'] = $validation;
            
            if ($validation['valid']) {
                $success = 'M-Pesa configuration is valid!';
            } else {
                $error = 'Configuration errors: ' . implode(', ', $validation['errors']);
            }
            break;
    }
}

// Get current M-Pesa settings
$settings = [
    'consumer_key' => getSystemSetting('mpesa_consumer_key', ''),
    'consumer_secret' => getSystemSetting('mpesa_consumer_secret', ''),
    'shortcode' => getSystemSetting('mpesa_shortcode', ''),
    'passkey' => getSystemSetting('mpesa_passkey', ''),
    'environment' => getSystemSetting('mpesa_environment', 'sandbox'),
    'initiator_name' => getSystemSetting('mpesa_initiator_name', ''),
    'security_credential' => getSystemSetting('mpesa_security_credential', '')
];

// Get recent test transactions
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE description LIKE '%test%' OR description LIKE '%Test%'
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute();
$test_transactions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Test Suite - Ultra Harvest Admin</title>
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
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-vial text-white"></i>
                        </div>
                        <div>
                            <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">M-Pesa Test Suite</span>
                            <p class="text-xs text-gray-400">Complete Testing Interface</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/admin/" class="text-gray-400 hover:text-white">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">M-Pesa Integration Testing</h1>
            <p class="text-gray-400">Comprehensive testing suite for M-Pesa integration</p>
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

        <!-- Configuration Status -->
        <section class="glass-card rounded-xl p-6 mb-8">
            <h2 class="text-xl font-bold text-white mb-4">Current M-Pesa Configuration</h2>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-gray-400 text-sm">Consumer Key</p>
                        <span class="status-indicator <?php echo $settings['consumer_key'] ? 'bg-emerald-500' : 'bg-red-500'; ?>"></span>
                    </div>
                    <p class="text-white font-mono text-sm break-all"><?php echo $settings['consumer_key'] ? substr($settings['consumer_key'], 0, 20) . '...' : 'Not Set'; ?></p>
                </div>
                
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-gray-400 text-sm">Consumer Secret</p>
                        <span class="status-indicator <?php echo $settings['consumer_secret'] ? 'bg-emerald-500' : 'bg-red-500'; ?>"></span>
                    </div>
                    <p class="text-white font-mono text-sm break-all"><?php echo $settings['consumer_secret'] ? '••••••••••••••••••••' : 'Not Set'; ?></p>
                </div>
                
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-gray-400 text-sm">Shortcode</p>
                        <span class="status-indicator <?php echo $settings['shortcode'] ? 'bg-emerald-500' : 'bg-red-500'; ?>"></span>
                    </div>
                    <p class="text-white font-mono text-sm"><?php echo $settings['shortcode'] ?: 'Not Set'; ?></p>
                </div>
                
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-gray-400 text-sm">Passkey</p>
                        <span class="status-indicator <?php echo $settings['passkey'] ? 'bg-emerald-500' : 'bg-red-500'; ?>"></span>
                    </div>
                    <p class="text-white font-mono text-sm"><?php echo $settings['passkey'] ? '••••••••••••••••••••' : 'Not Set'; ?></p>
                </div>
                
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-gray-400 text-sm">Environment</p>
                        <span class="status-indicator bg-blue-500"></span>
                    </div>
                    <p class="text-white font-mono text-sm uppercase"><?php echo $settings['environment']; ?></p>
                </div>
                
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-gray-400 text-sm">Initiator (B2C)</p>
                        <span class="status-indicator <?php echo $settings['initiator_name'] ? 'bg-emerald-500' : 'bg-yellow-500'; ?>"></span>
                    </div>
                    <p class="text-white font-mono text-sm"><?php echo $settings['initiator_name'] ?: 'Not Set'; ?></p>
                </div>
            </div>
            
            <div class="mt-4 p-4 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                <p class="text-sm text-blue-300"><i class="fas fa-info-circle mr-2"></i>Callback URL: <span class="font-mono"><?php echo SITE_URL; ?>api/mpesa-callback.php</span></p>
                <p class="text-sm text-blue-300 mt-1"><i class="fas fa-info-circle mr-2"></i>B2C Result URL: <span class="font-mono"><?php echo SITE_URL; ?>api/mpesa-b2c-result.php</span></p>
            </div>
        </section>

        <!-- Test Functions -->
        <div class="grid lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
            
            <!-- Configuration Check -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-clipboard-check text-purple-400 mr-2"></i>Configuration Check
                </h3>
                <p class="text-gray-400 text-sm mb-4">Validate all M-Pesa configuration settings</p>
                
                <form method="POST">
                    <input type="hidden" name="test_type" value="config_check">
                    <button type="submit" class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-check-double mr-2"></i>Check Configuration
                    </button>
                </form>
                
                <?php if (isset($test_results['config_check'])): ?>
                <div class="mt-4 p-3 bg-gray-800/50 rounded-lg">
                    <pre class="text-xs text-gray-300 overflow-auto max-h-40"><?php echo json_encode($test_results['config_check'], JSON_PRETTY_PRINT); ?></pre>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Connection Test -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-plug text-blue-400 mr-2"></i>Connection Test
                </h3>
                <p class="text-gray-400 text-sm mb-4">Test API connection and obtain access token</p>
                
                <form method="POST">
                    <input type="hidden" name="test_type" value="connection">
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-play mr-2"></i>Test Connection
                    </button>
                </form>
                
                <?php if (isset($test_results['connection'])): ?>
                <div class="mt-4 p-3 bg-gray-800/50 rounded-lg">
                    <pre class="text-xs text-gray-300 overflow-auto max-h-40"><?php echo json_encode($test_results['connection'], JSON_PRETTY_PRINT); ?></pre>
                </div>
                <?php endif; ?>
            </div>

            <!-- STK Push Test -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-mobile-alt text-green-400 mr-2"></i>STK Push Test
                </h3>
                <p class="text-gray-400 text-sm mb-4">Send test payment request (Deposit)</p>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="test_type" value="stk_push">
                    
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Phone Number</label>
                        <input 
                            type="text" 
                            name="test_phone" 
                            placeholder="254712345678"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Amount (KSh)</label>
                        <input 
                            type="number" 
                            name="test_amount" 
                            value="1"
                            min="1"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            required
                        >
                    </div>
                    
                    <button type="submit" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-paper-plane mr-2"></i>Send STK Push
                    </button>
                </form>
                
                <?php if (isset($test_results['stk_push'])): ?>
                <div class="mt-4 p-3 bg-gray-800/50 rounded-lg">
                    <pre class="text-xs text-gray-300 overflow-auto max-h-40"><?php echo json_encode($test_results['stk_push'], JSON_PRETTY_PRINT); ?></pre>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- B2C Payment Test -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-arrow-up text-red-400 mr-2"></i>B2C Payment Test
                </h3>
                <p class="text-gray-400 text-sm mb-4">Test withdrawal payment (requires B2C setup)</p>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="test_type" value="b2c_payment">
                    
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Phone Number</label>
                        <input 
                            type="text" 
                            name="test_phone" 
                            placeholder="254712345678"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-red-500 focus:outline-none"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Amount (KSh)</label>
                        <input 
                            type="number" 
                            name="test_amount" 
                            value="10"
                            min="10"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-red-500 focus:outline-none"
                            required
                        >
                    </div>
                    
                    <?php if (empty($settings['initiator_name'])): ?>
                    <div class="p-3 bg-yellow-500/20 border border-yellow-500/50 rounded text-xs text-yellow-300">
                        <i class="fas fa-exclamation-triangle mr-1"></i>B2C initiator not configured
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-money-bill-wave mr-2"></i>Test B2C Payment
                    </button>
                </form>
                
                <?php if (isset($test_results['b2c_payment'])): ?>
                <div class="mt-4 p-3 bg-gray-800/50 rounded-lg">
                    <pre class="text-xs text-gray-300 overflow-auto max-h-40"><?php echo json_encode($test_results['b2c_payment'], JSON_PRETTY_PRINT); ?></pre>
                </div>
                <?php endif; ?>
            </div>

            <!-- Callback Test -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-exchange-alt text-purple-400 mr-2"></i>Callback Test
                </h3>
                <p class="text-gray-400 text-sm mb-4">Generate sample callback data</p>
                
                <form method="POST">
                    <input type="hidden" name="test_type" value="callback_test">
                    <button type="submit" class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-code mr-2"></i>Generate Callback
                    </button>
                </form>
                
                <?php if (isset($test_results['callback'])): ?>
                <div class="mt-4 p-3 bg-gray-800/50 rounded-lg">
                    <pre class="text-xs text-gray-300 overflow-auto max-h-40"><?php echo json_encode($test_results['callback'], JSON_PRETTY_PRINT); ?></pre>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Test Transactions -->
        <section class="glass-card rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">Recent Test Transactions</h3>
            
            <?php if (!empty($test_transactions)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 text-gray-400">ID</th>
                                <th class="text-left py-2 text-gray-400">Type</th>
                                <th class="text-right py-2 text-gray-400">Amount</th>
                                <th class="text-center py-2 text-gray-400">Status</th>
                                <th class="text-left py-2 text-gray-400">Receipt</th>
                                <th class="text-left py-2 text-gray-400">Request ID</th>
                                <th class="text-left py-2 text-gray-400">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($test_transactions as $txn): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800/30">
                                <td class="py-2 text-white"><?php echo $txn['id']; ?></td>
                                <td class="py-2">
                                    <span class="px-2 py-1 rounded text-xs <?php echo $txn['type'] === 'deposit' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
                                        <?php echo ucfirst($txn['type']); ?>
                                    </span>
                                </td>
                                <td class="py-2 text-right text-white font-bold"><?php echo formatMoney($txn['amount']); ?></td>
                                <td class="py-2 text-center">
                                    <span class="px-2 py-1 rounded text-xs <?php 
                                    echo match($txn['status']) {
                                        'completed' => 'bg-green-500/20 text-green-400',
                                        'pending' => 'bg-yellow-500/20 text-yellow-400',
                                        'failed' => 'bg-red-500/20 text-red-400',
                                        default => 'bg-gray-500/20 text-gray-400'
                                    };
                                    ?>">
                                        <?php echo ucfirst($txn['status']); ?>
                                    </span>
                                </td>
                                <td class="py-2 text-gray-300 font-mono text-xs"><?php echo $txn['mpesa_receipt'] ?: 'N/A'; ?></td>
                                <td class="py-2 text-gray-400 font-mono text-xs"><?php echo $txn['mpesa_request_id'] ? substr($txn['mpesa_request_id'], 0, 20) . '...' : 'N/A'; ?></td>
                                <td class="py-2 text-gray-400 text-xs"><?php echo date('M j, H:i', strtotime($txn['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-center py-4">No test transactions found.</p>
            <?php endif; ?>
        </section>

        <!-- Testing Instructions -->
        <section class="glass-card rounded-xl p-6 mt-8">
            <h3 class="text-lg font-bold text-white mb-4">Testing Instructions & Checklist</h3>
            
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold text-emerald-400 mb-3">STK Push Testing</h4>
                    <ol class="text-sm text-gray-300 space-y-2">
                        <li>1. Run Configuration Check first</li>
                        <li>2. Test Connection to verify credentials</li>
                        <li>3. Use a real Kenyan mobile number you have access to</li>
                        <li>4. Start with small amounts (KSh 1-10)</li>
                        <li>5. Check your phone for STK prompt</li>
                        <li>6. Enter M-Pesa PIN to complete</li>
                        <li>7. Check transaction status in table below</li>
                        <li>8. Verify callback was received in logs</li>
                    </ol>
                </div>
                
                <div>
                    <h4 class="font-semibold text-red-400 mb-3">B2C Testing</h4>
                    <ol class="text-sm text-gray-300 space-y-2">
                        <li>1. Ensure B2C credentials are configured</li>
                        <li>2. Initiator Name must be set</li>
                        <li>3. Security Credential must be encrypted</li>
                        <li>4. Use test/production number accordingly</li>
                        <li>5. Check M-Pesa message on phone</li>
                        <li>6. Verify funds received in M-Pesa wallet</li>
                        <li>7. Check result callback in logs</li>
                    </ol>
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                <h4 class="font-semibold text-yellow-400 mb-2">Important Notes</h4>
                <ul class="text-xs text-gray-300 space-y-1">
                    <li>• <strong>Sandbox:</strong> Use for development. No real money transfer.</li>
                    <li>• <strong>Production:</strong> Real transactions. Use carefully!</li>
                    <li>• <strong>Callbacks:</strong> Must be accessible via HTTPS with valid SSL</li>
                    <li>• <strong>Phone Format:</strong> Always use 254XXXXXXXXX format</li>
                    <li>• <strong>Logs:</strong> Check /logs/ directory for detailed transaction logs</li>
                    <li>• <strong>B2C Setup:</strong> Requires additional credentials from Safaricom</li>
                </ul>
            </div>
        </section>
    </main>

    <script>
        // Auto-refresh test results every 10 seconds
        let autoRefresh = false;
        
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            if (autoRefresh) {
                setInterval(() => {
                    if (autoRefresh) {
                        location.reload();
                    }
                }, 10000);
            }
        }
        
        // Copy text helper
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            });
        }
    </script>
</body>
</html>