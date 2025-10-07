<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle deposit request
if ($_POST && isset($_POST['make_deposit'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $amount = (float)$_POST['amount'];
        $phone = sanitize($_POST['phone']);
        
        // Remove any non-numeric characters from phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Format phone number to 254XXXXXXXXX
        if (strlen($phone) == 10 && substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) == 9) {
            $phone = '254' . $phone;
        } elseif (strlen($phone) == 13 && substr($phone, 0, 4) === '+254') {
            $phone = substr($phone, 1);
        }
        
        // Validation
        if ($amount < 100) {
            $error = 'Minimum deposit amount is KSh 100.';
        } elseif ($amount > 1000000) {
            $error = 'Maximum deposit amount is KSh 1,000,000.';
        } elseif (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
            $error = 'Please enter a valid M-Pesa phone number (e.g., 0712345678 or 254712345678).';
        } else {
            $digit4 = substr($phone, 3, 1);
            $digit5 = substr($phone, 4, 1);
            
            $is_valid = false;
            
            if ($digit4 === '7' && in_array($digit5, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'])) {
                $is_valid = true;
            } elseif ($digit4 === '1' && $digit5 === '1') {
                $is_valid = true;
            } elseif ($digit4 === '1' && $digit5 === '0') {
                $is_valid = true;
            }
            
            if (!$is_valid) {
                $error = 'Please enter a valid Kenyan mobile number (Safaricom: 07xx, Airtel: 11xx, Telkom: 10xx).';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Create pending deposit transaction FIRST
                    $stmt = $db->prepare("
                        INSERT INTO transactions (user_id, type, amount, phone_number, status, description) 
                        VALUES (?, 'deposit', ?, ?, 'pending', 'M-Pesa deposit request')
                    ");
                    $stmt->execute([$user_id, $amount, $phone]);
                    $transaction_id = $db->lastInsertId();
                    
                    // NOW initiate M-Pesa STK Push
                    require_once '../config/mpesa.php';
                    $mpesa_result = initiateMpesaPayment($phone, $amount, $transaction_id, 'UltraHarvest');
                    
                    if ($mpesa_result['success']) {
                        $stmt = $db->prepare("
                            UPDATE transactions 
                            SET mpesa_request_id = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$mpesa_result['checkout_request_id'], $transaction_id]);
                        
                        $db->commit();
                        $success = 'M-Pesa payment request sent to ' . $phone . '. Please enter your M-Pesa PIN to complete the payment.';
                        
                        sendNotification($user_id, 'Deposit Initiated', "M-Pesa payment request for " . formatMoney($amount) . " sent to your phone.", 'info');
                    } else {
                        $db->rollBack();
                        $error = $mpesa_result['message'] ?? 'Failed to initiate M-Pesa payment. Please try again.';
                        error_log("M-Pesa STK Push Failed - User: $user_id, Amount: $amount, Phone: $phone, Error: " . ($mpesa_result['message'] ?? 'Unknown error'));
                    }
                    
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Failed to process deposit request. Please try again.';
                    error_log("Deposit error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get recent deposits
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? AND type = 'deposit' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_deposits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Funds - Ultra Harvest Global</title>
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
        
        .amount-btn {
            transition: all 0.3s ease;
        }
        
        .amount-btn:hover {
            transform: scale(1.05);
        }
        
        .amount-btn.selected {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
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
        
        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">
                <i class="fas fa-plus-circle text-emerald-400 mr-3"></i>
                Deposit Funds
            </h1>
            <p class="text-xl text-gray-300">Add money to your wallet using M-Pesa</p>
        </div>

        <!-- Error/Success Messages -->
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

        <div class="grid lg:grid-cols-3 gap-8">
            
            <!-- Main Deposit Form -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-xl p-8">
                    
                    <!-- M-Pesa Header -->
                    <div class="text-center mb-8">
                        <div class="mpesa-green w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-mobile-alt text-white text-3xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2">M-Pesa Deposit</h2>
                        <p class="text-gray-300">Safe, secure and instant deposits via M-Pesa</p>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="make_deposit" value="1">

                        <!-- Quick Amount Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-4">
                                <i class="fas fa-coins mr-2"></i>Select Amount (KSh)
                            </label>
                            <div class="grid grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                                <?php 
                                $quick_amounts = [500, 1000, 2000, 5000, 10000, 20000, 50000, 100000];
                                foreach ($quick_amounts as $amount): 
                                ?>
                                <button 
                                    type="button" 
                                    class="amount-btn px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white hover:border-emerald-500 transition"
                                    data-amount="<?php echo $amount; ?>"
                                >
                                    <?php echo number_format($amount); ?>
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
                                    min="100"
                                    max="1000000"
                                    step="1"
                                    class="w-full pl-12 pr-4 py-4 bg-gray-800 border border-gray-600 rounded-lg text-white text-xl font-bold focus:border-emerald-500 focus:outline-none"
                                    placeholder="Enter amount"
                                    required
                                >
                            </div>
                            <div class="flex justify-between text-sm text-gray-500 mt-2">
                                <span>Minimum: KSh 100</span>
                                <span>Maximum: KSh 1,000,000</span>
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
                                class="w-full px-4 py-4 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                placeholder="0712345678 or 254712345678"
                                required
                            >
                            <p class="text-sm text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Enter your M-Pesa registered phone number (e.g., 0712345678)
                            </p>
                        </div>

                        <!-- Deposit Summary -->
                        <div class="bg-gray-800/50 rounded-lg p-6" id="deposit-summary" style="display: none;">
                            <h3 class="font-bold text-white mb-4">Deposit Summary</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Amount</span>
                                    <span class="text-white font-bold" id="summary-amount">KSh 0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Processing Fee</span>
                                    <span class="text-emerald-400">FREE</span>
                                </div>
                                <div class="border-t border-gray-700 pt-3">
                                    <div class="flex justify-between">
                                        <span class="text-white font-medium">Total to Pay</span>
                                        <span class="text-emerald-400 font-bold text-xl" id="summary-total">KSh 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="w-full py-4 mpesa-green text-white font-bold text-lg rounded-lg hover:opacity-90 transform hover:scale-[1.02] transition-all duration-300 shadow-lg"
                            id="deposit-btn"
                            disabled
                        >
                            <i class="fas fa-mobile-alt mr-2"></i>Send M-Pesa Request
                        </button>

                        <!-- Terms -->
                        <p class="text-xs text-gray-500 text-center leading-relaxed">
                            By proceeding, you agree to our terms of service. 
                            Deposits are processed instantly upon M-Pesa confirmation. 
                            No additional fees are charged by Ultra Harvest.
                        </p>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                
                <!-- How it Works -->
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-question-circle text-emerald-400 mr-2"></i>
                        How it Works
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <span class="text-white text-xs font-bold">1</span>
                            </div>
                            <div>
                                <p class="font-medium text-white">Enter Amount</p>
                                <p class="text-sm text-gray-400">Choose or enter your deposit amount</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <span class="text-white text-xs font-bold">2</span>
                            </div>
                            <div>
                                <p class="font-medium text-white">Confirm Phone</p>
                                <p class="text-sm text-gray-400">Verify your M-Pesa phone number</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <span class="text-white text-xs font-bold">3</span>
                            </div>
                            <div>
                                <p class="font-medium text-white">Complete Payment</p>
                                <p class="text-sm text-gray-400">Enter M-Pesa PIN on your phone</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <span class="text-white text-xs font-bold">4</span>
                            </div>
                            <div>
                                <p class="font-medium text-white">Instant Credit</p>
                                <p class="text-sm text-gray-400">Funds appear in your wallet immediately</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Deposits -->
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-history text-yellow-400 mr-2"></i>
                        Recent Deposits
                    </h3>
                    <?php if (empty($recent_deposits)): ?>
                        <div class="text-center py-6">
                            <i class="fas fa-inbox text-3xl text-gray-600 mb-3"></i>
                            <p class="text-gray-400">No deposits yet</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php foreach (array_slice($recent_deposits, 0, 5) as $deposit): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-800/30 rounded-lg">
                                <div>
                                    <p class="font-medium text-white"><?php echo formatMoney($deposit['amount']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo timeAgo($deposit['created_at']); ?></p>
                                </div>
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php 
                                    echo match($deposit['status']) {
                                        'completed' => 'bg-emerald-500/20 text-emerald-400',
                                        'pending' => 'bg-yellow-500/20 text-yellow-400',
                                        'failed' => 'bg-red-500/20 text-red-400',
                                        default => 'bg-gray-500/20 text-gray-400'
                                    };
                                    ?>">
                                    <?php echo ucfirst($deposit['status']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Support -->
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-headset text-blue-400 mr-2"></i>
                        Need Help?
                    </h3>
                    <div class="space-y-3">
                        <a href="https://wa.me/254700000000" target="_blank" class="flex items-center text-green-400 hover:text-green-300">
                            <i class="fab fa-whatsapp mr-2"></i>WhatsApp Support
                        </a>
                        <a href="/user/support.php" class="flex items-center text-blue-400 hover:text-blue-300">
                            <i class="fas fa-ticket-alt mr-2"></i>Create Ticket
                        </a>
                        <a href="/help.php" class="flex items-center text-gray-400 hover:text-white">
                            <i class="fas fa-question-circle mr-2"></i>Help Center
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Quick amount selection
        document.querySelectorAll('.amount-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                const amount = this.getAttribute('data-amount');
                document.getElementById('amount').value = amount;
                updateSummary();
            });
        });

        // Amount input changes
        document.getElementById('amount').addEventListener('input', function() {
            document.querySelectorAll('.amount-btn').forEach(btn => btn.classList.remove('selected'));
            updateSummary();
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            this.value = value;
        });

        // Update deposit summary
        function updateSummary() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const summaryElement = document.getElementById('deposit-summary');
            const submitBtn = document.getElementById('deposit-btn');

            if (amount >= 100) {
                summaryElement.style.display = 'block';
                document.getElementById('summary-amount').textContent = 'KSh ' + amount.toLocaleString();
                document.getElementById('summary-total').textContent = 'KSh ' + amount.toLocaleString();
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                summaryElement.style.display = 'none';
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        // Form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.getElementById('deposit-btn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            submitBtn.disabled = true;
        });

        // Initialize
        updateSummary();
    </script>
</body>
</html>