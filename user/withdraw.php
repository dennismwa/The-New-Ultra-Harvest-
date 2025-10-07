<?php
require_once '../config/database.php';
require_once '../config/mpesa.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data including active package tier
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT p.name FROM active_packages ap 
            JOIN packages p ON ap.package_id = p.id 
            WHERE ap.user_id = u.id AND ap.status = 'active' 
            ORDER BY ap.created_at DESC LIMIT 1) as current_package
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Withdrawal fee structure based on package tier (from document)
$withdrawal_fees = [
    'Seed' => 7,
    'Sprout' => 6,
    'Growth' => 5,
    'Harvest' => 5,
    'Golden Yield' => 4,
    'Elite' => 3
];

// Determine user's withdrawal fee
$user_fee_percentage = 7; // Default to highest if no active package
if ($user['current_package'] && isset($withdrawal_fees[$user['current_package']])) {
    $user_fee_percentage = $withdrawal_fees[$user['current_package']];
}

// Get system settings
$min_withdrawal = (float)getSystemSetting('min_withdrawal_amount', 100);
$max_withdrawal = (float)getSystemSetting('max_withdrawal_amount', 1000000);
$instant_threshold = (float)getSystemSetting('instant_withdrawal_threshold', 10000);

// Handle withdrawal request
if ($_POST && isset($_POST['make_withdrawal'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $amount = (float)$_POST['amount'];
        $phone = sanitize($_POST['phone']);

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10 && substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) == 9) {
            $phone = '254' . $phone;
        } elseif (strlen($phone) == 13 && substr($phone, 0, 4) === '+254') {
            $phone = substr($phone, 1);
        }
        
        // Calculate withdrawal fee
        $withdrawal_fee = ($amount * $user_fee_percentage) / 100;
        $amount_after_fee = $amount - $withdrawal_fee;
        
        // Validation
        if ($amount < $min_withdrawal) {
            $error = 'Minimum withdrawal amount is ' . formatMoney($min_withdrawal) . '.';
        } elseif ($amount > $max_withdrawal) {
            $error = 'Maximum withdrawal amount is ' . formatMoney($max_withdrawal) . '.';
        } elseif ($amount > $user['wallet_balance']) {
            $error = 'Insufficient wallet balance.';
        } elseif (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
            $error = 'Please enter a valid M-Pesa phone number (254XXXXXXXXX).';
        } else {
            try {
                $db->beginTransaction();
                
                // Deduct from wallet balance
                $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$amount, $user_id]);
                
                // Create withdrawal transaction
                $is_instant = $amount < $instant_threshold;
                $description = "M-Pesa withdrawal. Fee: " . formatMoney($withdrawal_fee) . " ({$user_fee_percentage}%)";
                
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, amount, phone_number, status, description, created_at) 
                    VALUES (?, 'withdrawal', ?, ?, 'pending', ?, NOW())
                ");
                $stmt->execute([$user_id, $amount, $phone, $description]);
                $transaction_id = $db->lastInsertId();
                
                // Process instant withdrawal if under threshold
                if ($is_instant) {
                    $mpesa_result = processMpesaWithdrawal($phone, $amount_after_fee, $transaction_id);
                    
                    if ($mpesa_result['success']) {
                        $stmt = $db->prepare("
                            UPDATE transactions 
                            SET status = 'completed', 
                                mpesa_request_id = ?,
                                description = CONCAT(description, ' - Completed'),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$mpesa_result['conversation_id'] ?? null, $transaction_id]);
                        
                        $db->commit();
                        $success = 'All Withdrawals will be processed in about 1 hour. You will receive ' . formatMoney($amount_after_fee) . ' after ' . $user_fee_percentage . '% fee.';
                        
                        sendNotification($user_id, 'Withdrawal Completed', 
                            "Your withdrawal of " . formatMoney($amount) . " has been processed. Amount sent: " . formatMoney($amount_after_fee), 
                            'success');
                    } else {
                        // Revert balance on failure
                        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$amount, $user_id]);
                        
                        $stmt = $db->prepare("
                            UPDATE transactions 
                            SET status = 'failed', 
                                description = CONCAT(description, ' - Failed: ', ?),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$mpesa_result['message'], $transaction_id]);
                        
                        $db->commit();
                        $error = 'Withdrawal failed: ' . $mpesa_result['message'];
                    }
                } else {
                    $db->commit();
                    $success = "All Withdrawals will be processed in about 1 hour. You will receive " . formatMoney($amount_after_fee) . " after {$user_fee_percentage}% fee.";
                    
                    sendNotification($user_id, 'Withdrawal Pending', 
                        "Your withdrawal of " . formatMoney($amount) . " is being processed.", 
                        'info');
                }
                
                // Refresh user balance
                $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Failed to process withdrawal request. Please try again.';
                error_log("Withdrawal error: " . $e->getMessage());
            }
        }
    }
}

// Get recent withdrawals
$stmt = $db->prepare("
    SELECT * FROM transactions
    WHERE user_id = ? AND type = 'withdrawal'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_withdrawals = $stmt->fetchAll();

function processMpesaWithdrawal($phone, $amount, $transaction_id) {
    try {
        $simulate_success = false;
        
        if ($simulate_success) {
            usleep(500000);
            $random = rand(1, 100);
            if ($random <= 95) {
                return [
                    'success' => true,
                    'conversation_id' => 'SIM-' . time() . '-' . rand(1000, 9999),
                    'transaction_id' => 'SIMB2C' . time(),
                    'message' => 'Withdrawal processed successfully (simulated)'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Simulated failure - M-Pesa service temporarily unavailable'
                ];
            }
        }
    } catch (Exception $e) {
        error_log("M-Pesa Withdrawal Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Payment system error: ' . $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds - Ultra Harvest Global</title>
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
        
        .mpesa-green { background: linear-gradient(45deg, #00A651, #00D157); }
        
        .amount-btn { transition: all 0.3s ease; }
        .amount-btn:hover { transform: scale(1.05); }
        .amount-btn.selected {
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: white;
        }
        
        .warning-card {
            background: linear-gradient(45deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1));
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-8">
                <a href="/user/dashboard.php" class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                        <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    </div>
                </a>
                
                <nav class="hidden md:flex space-x-6">
                    <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                    <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Trade</a>
                    <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">Network</a>
                    <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                    <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                </nav>
            </div>

            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-4 py-2">
                    <i class="fas fa-wallet text-emerald-400"></i>
                    <span class="text-sm text-gray-300">Balance:</span>
                    <span class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></span>
                </div>
                <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container mx-auto px-4 py-8">
    
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold mb-2">
            <i class="fas fa-arrow-up text-red-400 mr-3"></i>
            Withdraw Funds
        </h1>
        <p class="text-xl text-gray-300">Withdraw to your M-Pesa account</p>
    </div>

    <?php if ($error): ?>
    <div class="max-w-2xl mx-auto mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
            <span class="text-red-300"><?php echo $error; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="max-w-2xl mx-auto mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
            <span class="text-emerald-300"><?php echo $success; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user['wallet_balance'] < $min_withdrawal): ?>
    <div class="max-w-2xl mx-auto mb-8">
        <div class="warning-card rounded-xl p-8 text-center">
            <i class="fas fa-exclamation-triangle text-yellow-400 text-4xl mb-4"></i>
            <h3 class="text-xl font-bold text-white mb-4">Insufficient Balance</h3>
            <p class="text-gray-300 mb-6">
                Your current balance is <?php echo formatMoney($user['wallet_balance']); ?>. 
                The minimum withdrawal amount is <?php echo formatMoney($min_withdrawal); ?>.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="/user/deposit.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-plus mr-2"></i>Deposit Funds
                </a>
                <a href="/user/packages.php" class="px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-chart-line mr-2"></i>Invest & Earn
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>

    <div class="grid lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2">
            <div class="glass-card rounded-xl p-8">
                
                <div class="text-center mb-8">
                    <div class="mpesa-green w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-mobile-alt text-white text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">M-Pesa Withdrawal</h2>
                    <p class="text-gray-300">Withdraw directly to your M-Pesa account</p>
                </div>

                <!-- Withdrawal Fee Notice -->
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-info-circle text-yellow-400 mt-1"></i>
                        <div>
                            <h4 class="font-bold text-yellow-400 mb-1">Withdrawal Fee Information</h4>
                            <p class="text-sm text-gray-300">
                                Your current package tier: <strong><?php echo $user['current_package'] ?? 'None (Default)'; ?></strong><br>
                                Withdrawal fee: <strong><?php echo $user_fee_percentage; ?>%</strong><br>
                                <span class="text-xs text-gray-400 mt-2 block">This transaction will incur a {$user_fee_percentage}% fee. The fee is automatically deducted.</span>
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" class="space-y-6" id="withdrawalForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="make_withdrawal" value="1">

                    <!-- Available Balance -->
                    <div class="bg-gradient-to-r from-emerald-600/20 to-yellow-600/20 rounded-lg p-6">
                        <div class="text-center">
                            <p class="text-gray-300 mb-2">Available Balance</p>
                            <p class="text-4xl font-bold text-white mb-4"><?php echo formatMoney($user['wallet_balance']); ?></p>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-400">Minimum</p>
                                    <p class="text-emerald-400 font-bold"><?php echo formatMoney($min_withdrawal); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Maximum</p>
                                    <p class="text-red-400 font-bold"><?php echo formatMoney(min($max_withdrawal, $user['wallet_balance'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Amount Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-4">
                            <i class="fas fa-coins mr-2"></i>Select Amount (KSh)
                        </label>
                        <div class="grid grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                            <?php 
                            $balance = $user['wallet_balance'];
                            $quick_amounts = [];
                            
                            if ($balance >= 500) $quick_amounts[] = 500;
                            if ($balance >= 1000) $quick_amounts[] = 1000;
                            if ($balance >= 2000) $quick_amounts[] = 2000;
                            if ($balance >= 5000) $quick_amounts[] = 5000;
                            if ($balance >= 10000) $quick_amounts[] = 10000;
                            if ($balance >= 20000) $quick_amounts[] = 20000;
                            if ($balance >= 50000) $quick_amounts[] = 50000;
                            
                            if ($balance >= $min_withdrawal) {
                                $quick_amounts[] = floor($balance);
                            }
                            
                            foreach ($quick_amounts as $index => $amount): 
                            ?>
                            <button 
                                type="button" 
                                class="amount-btn px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white hover:border-red-500 transition"
                                data-amount="<?php echo $amount; ?>"
                            >
                                <?php echo $index === count($quick_amounts) - 1 && $amount == floor($balance) ? 'All' : number_format($amount); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Custom Amount Input -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-edit mr-2"></i>Or Enter Custom Amount
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">KSh</span>
                            <input 
                                type="number" 
                                name="amount" 
                                id="amount"
                                min="<?php echo $min_withdrawal; ?>"
                                max="<?php echo min($max_withdrawal, $user['wallet_balance']); ?>"
                                step="1"
                                class="w-full pl-12 pr-4 py-4 bg-gray-800 border border-gray-600 rounded-lg text-white text-xl font-bold focus:border-red-500 focus:outline-none"
                                placeholder="Enter amount"
                                required
                            >
                        </div>
                        <div class="flex justify-between text-sm text-gray-500 mt-2">
                            <span>Minimum: <?php echo formatMoney($min_withdrawal); ?></span>
                            <span>Available: <?php echo formatMoney($user['wallet_balance']); ?></span>
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-phone mr-2"></i>M-Pesa Phone Number
                        </label>
                        <input 
                            type="tel" 
                            name="phone" 
                            id="phone"
                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                            class="w-full px-4 py-4 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-red-500 focus:outline-none"
                            placeholder="0712345678 or 254712345678"
                            required
                        >
                        <p class="text-sm text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Funds will be sent to this M-Pesa number
                        </p>
                    </div>

                    <!-- Withdrawal Summary -->
                    <div class="bg-gray-800/50 rounded-lg p-6" id="withdrawal-summary" style="display: none;">
                        <h3 class="font-bold text-white mb-4">Withdrawal Summary</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Withdrawal Amount</span>
                                <span class="text-white font-bold" id="summary-amount">KSh 0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Withdrawal Fee (<?php echo $user_fee_percentage; ?>%)</span>
                                <span class="text-red-400" id="summary-fee">KSh 0</span>
                            </div>
                            <div class="border-t border-gray-700 pt-3">
                                <div class="flex justify-between">
                                    <span class="text-white font-medium">You Will Receive</span>
                                    <span class="text-emerald-400 font-bold text-xl" id="summary-total">KSh 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full py-4 bg-gradient-to-r from-red-500 to-red-600 text-white font-bold text-lg rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-[1.02] transition-all duration-300 shadow-lg"
                        id="withdraw-btn"
                        disabled
                    >
                        <i class="fas fa-arrow-up mr-2"></i>Request Withdrawal
                    </button>

                    <p class="text-xs text-gray-500 text-center leading-relaxed">
                        All Withdrawals will be processed in about 1 hour. 
                        By proceeding, you confirm that the M-Pesa number provided is correct and belongs to you.
                    </p>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">

            <!-- Withdrawal Fee Structure -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-percentage text-yellow-400 mr-2"></i>
                    Withdrawal Fees by Package
                </h3>
                <div class="space-y-2 text-sm">
                    <?php foreach ($withdrawal_fees as $package => $fee): ?>
                    <div class="flex items-center justify-between p-2 rounded <?php echo $user['current_package'] === $package ? 'bg-emerald-500/20' : 'bg-gray-800/30'; ?>">
                        <span class="text-gray-300"><?php echo $package; ?></span>
                        <span class="font-bold <?php echo $user['current_package'] === $package ? 'text-emerald-400' : 'text-yellow-400'; ?>"><?php echo $fee; ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Withdrawals -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-history text-red-400 mr-2"></i>
                    Recent Withdrawals
                </h3>
                <?php if (empty($recent_withdrawals)): ?>
                    <div class="text-center py-6">
                        <i class="fas fa-inbox text-3xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No withdrawals yet</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach (array_slice($recent_withdrawals, 0, 10) as $withdrawal): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-800/30 rounded-lg">
                            <div>
                                <p class="font-medium text-white"><?php echo formatMoney($withdrawal['amount']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo timeAgo($withdrawal['created_at']); ?></p>
                                <?php if ($withdrawal['phone_number']): ?>
                                    <p class="text-xs text-blue-400"><?php echo $withdrawal['phone_number']; ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-medium
                                <?php 
                                echo match($withdrawal['status']) {
                                    'completed' => 'bg-emerald-500/20 text-emerald-400',
                                    'pending' => 'bg-yellow-500/20 text-yellow-400',
                                    'failed' => 'bg-red-500/20 text-red-400',
                                    default => 'bg-gray-500/20 text-gray-400'
                                };
                                ?>">
                                <?php echo ucfirst($withdrawal['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Support -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-headset text-purple-400 mr-2"></i>
                    Need Help?
                </h3>
                <div class="space-y-3">
                    <a href="https://wa.me/254700000000" target="_blank" class="flex items-center text-green-400 hover:text-green-300 transition">
                        <i class="fab fa-whatsapp mr-2"></i>WhatsApp Support
                    </a>
                    <a href="/user/support.php" class="flex items-center text-blue-400 hover:text-blue-300 transition">
                        <i class="fas fa-ticket-alt mr-2"></i>Create Ticket
                    </a>
                    <a href="/help.php" class="flex items-center text-gray-400 hover:text-white transition">
                        <i class="fas fa-question-circle mr-2"></i>Help Center
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
    const minWithdrawal = <?php echo $min_withdrawal; ?>;
    const maxWithdrawal = <?php echo min($max_withdrawal, $user['wallet_balance']); ?>;
    const feePercentage = <?php echo $user_fee_percentage; ?>;

    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            const amount = this.getAttribute('data-amount');
            document.getElementById('amount').value = amount;
            updateSummary();
        });
    });

    document.getElementById('amount').addEventListener('input', function() {
        document.querySelectorAll('.amount-btn').forEach(btn => btn.classList.remove('selected'));
        updateSummary();
    });

    document.getElementById('phone').addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        this.value = value;
    });

    function updateSummary() {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const summaryElement = document.getElementById('withdrawal-summary');
        const submitBtn = document.getElementById('withdraw-btn');

        if (amount >= minWithdrawal && amount <= maxWithdrawal) {
            const fee = (amount * feePercentage) / 100;
            const afterFee = amount - fee;
            
            summaryElement.style.display = 'block';
            document.getElementById('summary-amount').textContent = 'KSh ' + amount.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-fee').textContent = 'KSh ' + fee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-total').textContent = 'KSh ' + afterFee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            summaryElement.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    document.getElementById('withdrawalForm').addEventListener('submit', function(e) {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const phone = document.getElementById('phone').value;
        const fee = (amount * feePercentage) / 100;
        const afterFee = amount - fee;

        if (!phone || phone.length < 9) {
            e.preventDefault();
            alert('Please enter a valid M-Pesa phone number');
            return false;
        }

        const confirmMessage = `Confirm Withdrawal\n\n` +
            `Amount: KSh ${amount.toLocaleString('en-KE', {minimumFractionDigits: 2})}\n` +
            `Fee (${feePercentage}%): KSh ${fee.toLocaleString('en-KE', {minimumFractionDigits: 2})}\n` +
            `You will receive: KSh ${afterFee.toLocaleString('en-KE', {minimumFractionDigits: 2})}\n` +
            `To: ${phone}\n\n` +
            `Proceed with withdrawal?`;

        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }

        const submitBtn = document.getElementById('withdraw-btn');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        submitBtn.disabled = true;

        return true;
    });

    updateSummary();
</script>
</body>
</html>