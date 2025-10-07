<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get all active packages
$stmt = $db->query("SELECT * FROM packages WHERE status = 'active' ORDER BY min_investment ASC");
$packages = $stmt->fetchAll();

// Handle package investment
if ($_POST && isset($_POST['invest_package'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $package_id = (int)$_POST['package_id'];
        $investment_amount = (float)$_POST['investment_amount'];
        
        // Get package details
        $stmt = $db->prepare("SELECT * FROM packages WHERE id = ? AND status = 'active'");
        $stmt->execute([$package_id]);
        $package = $stmt->fetch();
        
        if (!$package) {
            $error = 'Invalid package selected.';
        } elseif ($investment_amount < $package['min_investment']) {
            $error = 'Investment amount is below the minimum requirement.';
        } elseif ($package['max_investment'] && $investment_amount > $package['max_investment']) {
            $error = 'Investment amount exceeds the maximum limit.';
        } elseif ($investment_amount > $user['wallet_balance']) {
            $error = 'Insufficient wallet balance.';
        } else {
            // Calculate ROI and maturity date
            $expected_roi = ($investment_amount * $package['roi_percentage']) / 100;
            $maturity_date = date('Y-m-d H:i:s', strtotime('+' . $package['duration_hours'] . ' hours'));
            
            try {
                $db->beginTransaction();
                
                // Deduct from wallet balance
                $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                $stmt->execute([$investment_amount, $user_id]);
                
                // Create package investment transaction
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, amount, status, description) 
                    VALUES (?, 'package_investment', ?, 'completed', ?)
                ");
                $stmt->execute([$user_id, $investment_amount, "Investment in {$package['name']} package"]);
                
                // Create active package record
                $stmt = $db->prepare("
                    INSERT INTO active_packages (user_id, package_id, investment_amount, expected_roi, roi_percentage, duration_hours, maturity_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $package_id, $investment_amount, $expected_roi, $package['roi_percentage'], $package['duration_hours'], $maturity_date]);
                
                // Send notification
                sendNotification($user_id, 'Package Activated!', "Your {$package['name']} package has been activated successfully. Expected ROI: " . formatMoney($expected_roi), 'success');
                
                $db->commit();
                $success = "Package activated successfully! Your investment will mature on " . date('M j, Y g:i A', strtotime($maturity_date));
                
                // Refresh user balance
                $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to activate package. Please try again.';
            }
        }
    }
}

// Get user's active packages
$stmt = $db->prepare("
    SELECT ap.*, p.name as package_name, p.icon 
    FROM active_packages ap 
    JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'active' 
    ORDER BY ap.created_at DESC
");
$stmt->execute([$user_id]);
$active_packages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Packages - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .package-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            /* Reduced size - showing 3 packages before scroll */
            max-height: 450px;
        }
        
        .package-card:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(16, 185, 129, 0.5);
            transform: translateY(-5px);
        }
        
        .package-popular {
            position: relative;
            border-color: rgba(251, 191, 36, 0.5);
        }
        
        .package-popular::before {
            content: 'POPULAR';
            position: absolute;
            top: -10px;
            right: 20px;
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            color: black;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .roi-badge {
            background: linear-gradient(45deg, #10b981, #34d399);
        }
        
        .duration-badge {
            background: linear-gradient(45deg, #3b82f6, #60a5fa);
        }
        
        .countdown {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="/user/dashboard.php" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                    </a>
                    
                    <!-- Updated Navigation Order -->
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">HOME</a>
                        <a href="/user/packages.php" class="text-emerald-400 font-medium">TRADE</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">NETWORK</a>
                        <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">ACTIVE TRADES</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">HELP</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-4 py-2">
                        <i class="fas fa-wallet text-emerald-400"></i>
                        <span class="text-sm text-gray-300">Balance:</span>
                        <span class="font-bold text-white" id="wallet-balance"><?php echo formatMoney($user['wallet_balance']); ?></span>
                    </div>
                    <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                        <i class="fas fa-home text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl lg:text-5xl font-bold mb-4">
                Choose Your <span class="bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Trading Package</span>
            </h1>
            <!-- Updated text as per requirements -->
            <p class="text-xl text-gray-300 max-w-2xl mx-auto">
                Pick a plan, copy professional traders, and start earning with zero stress.
            </p>
        </div>

        <!-- Error/Success Messages -->
        <?php if (isset($error)): ?>
        <div class="max-w-2xl mx-auto mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="max-w-2xl mx-auto mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300"><?php echo $success; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Packages Section -->
        <?php if (!empty($active_packages)): ?>
        <section class="mb-12">
            <h2 class="text-2xl font-bold mb-6">Your Active Packages</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($active_packages as $active): ?>
                <div class="package-card rounded-xl p-6 border-emerald-500/30">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="text-3xl"><?php echo $active['icon']; ?></div>
                            <div>
                                <h3 class="font-bold text-white"><?php echo $active['package_name']; ?></h3>
                                <p class="text-sm text-emerald-400">Active Package</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-white"><?php echo formatMoney($active['investment_amount']); ?></p>
                            <p class="text-sm text-gray-400">Invested</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-400">Expected ROI</p>
                            <p class="font-bold text-emerald-400"><?php echo formatMoney($active['expected_roi']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">ROI Rate</p>
                            <p class="font-bold text-yellow-400"><?php echo $active['roi_percentage']; ?>%</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-800/50 rounded-lg p-3">
                        <p class="text-sm text-gray-400 mb-1">Matures in:</p>
                        <div class="countdown text-lg font-bold text-white" data-maturity="<?php echo $active['maturity_date']; ?>">
                            Calculating...
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Available Packages - Reduced size to show 3 before scrolling -->
        <section>
            <h2 class="text-2xl font-bold mb-6">Available Trading Packages</h2>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($packages as $index => $package): ?>
                <div class="package-card rounded-xl p-6 <?php echo $index === 1 ? 'package-popular' : ''; ?>">
                    <div class="text-center mb-6">
                        <div class="text-6xl mb-4"><?php echo $package['icon']; ?></div>
                        <h3 class="text-2xl font-bold text-white mb-2"><?php echo $package['name']; ?></h3>
                        <?php if ($package['description']): ?>
                        <p class="text-gray-400 text-sm"><?php echo $package['description']; ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4 mb-6">
                        <!-- ROI -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">ROI Percentage</span>
                            <span class="roi-badge text-white px-3 py-1 rounded-full font-bold"><?php echo $package['roi_percentage']; ?>%</span>
                        </div>

                        <!-- Duration -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Duration</span>
                            <span class="duration-badge text-white px-3 py-1 rounded-full font-bold">
                                <?php 
                                if ($package['duration_hours'] < 24) {
                                    echo $package['duration_hours'] . ' Hours';
                                } else {
                                    echo ($package['duration_hours'] / 24) . ' Days';
                                }
                                ?>
                            </span>
                        </div>

                        <!-- Minimum Investment -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Minimum</span>
                            <span class="text-white font-bold"><?php echo formatMoney($package['min_investment']); ?></span>
                        </div>

                        <?php if ($package['max_investment']): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Maximum</span>
                            <span class="text-white font-bold"><?php echo formatMoney($package['max_investment']); ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Potential Return -->
                        <div class="bg-gray-800/50 rounded-lg p-3">
                            <p class="text-sm text-gray-400">Example Return</p>
                            <p class="text-lg text-emerald-400 font-bold">
                                <?php echo formatMoney($package['min_investment'] + ($package['min_investment'] * $package['roi_percentage'] / 100)); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                From <?php echo formatMoney($package['min_investment']); ?> investment
                            </p>
                        </div>
                    </div>

                    <!-- Investment Form -->
                    <?php if ($user['wallet_balance'] >= $package['min_investment']): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                        <input type="hidden" name="invest_package" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Investment Amount</label>
                            <input 
                                type="number" 
                                name="investment_amount" 
                                min="<?php echo $package['min_investment']; ?>"
                                <?php if ($package['max_investment']): ?>max="<?php echo min($package['max_investment'], $user['wallet_balance']); ?>"<?php endif; ?>
                                step="0.01"
                                value="<?php echo $package['min_investment']; ?>"
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                required
                            >
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Min: <?php echo formatMoney($package['min_investment']); ?></span>
                                <span>Available: <?php echo formatMoney($user['wallet_balance']); ?></span>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            class="w-full py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold rounded-lg transition-all duration-300 transform hover:scale-105"
                        >
                            <i class="fas fa-chart-line mr-2"></i>Copy Trade Now
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-gray-400 mb-3">Insufficient Balance</p>
                        <a href="/user/deposit.php" class="inline-block px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white font-bold rounded-lg transition">
                            <i class="fas fa-plus mr-2"></i>Deposit Funds
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Package Comparison Table -->
            <div class="bg-gray-800/30 rounded-xl p-6 backdrop-blur-sm">
                <h3 class="text-xl font-bold text-white mb-6 text-center">Package Comparison</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-3 text-gray-400">Package</th>
                                <th class="text-center py-3 text-gray-400">ROI</th>
                                <th class="text-center py-3 text-gray-400">Duration</th>
                                <th class="text-center py-3 text-gray-400">Min Investment</th>
                                <th class="text-center py-3 text-gray-400">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $package): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                                <td class="py-4">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-2xl"><?php echo $package['icon']; ?></span>
                                        <span class="font-medium text-white"><?php echo $package['name']; ?></span>
                                    </div>
                                </td>
                                <td class="text-center py-4">
                                    <span class="roi-badge text-white px-2 py-1 rounded font-bold text-xs"><?php echo $package['roi_percentage']; ?>%</span>
                                </td>
                                <td class="text-center py-4 text-gray-300">
                                    <?php 
                                    if ($package['duration_hours'] < 24) {
                                        echo $package['duration_hours'] . 'H';
                                    } else {
                                        echo ($package['duration_hours'] / 24) . 'D';
                                    }
                                    ?>
                                </td>
                                <td class="text-center py-4 text-white font-medium">
                                    <?php echo formatMoney($package['min_investment']); ?>
                                </td>
                                <td class="text-center py-4">
                                    <?php if ($user['wallet_balance'] >= $package['min_investment']): ?>
                                    <button 
                                        onclick="scrollToPackage(<?php echo $package['id']; ?>)" 
                                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium transition"
                                    >
                                        Trade Now
                                    </button>
                                    <?php else: ?>
                                    <span class="text-gray-500 text-xs">Insufficient Balance</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Information Section -->
        <section class="mt-12 grid md:grid-cols-3 gap-6">
            <div class="bg-gray-800/30 rounded-xl p-6 backdrop-blur-sm text-center">
                <i class="fas fa-shield-alt text-4xl text-emerald-400 mb-4"></i>
                <h3 class="font-bold text-white mb-2">100% Secure</h3>
                <p class="text-gray-400 text-sm">All investments are secured with bank-level encryption and transparency.</p>
            </div>
            <div class="bg-gray-800/30 rounded-xl p-6 backdrop-blur-sm text-center">
                <i class="fas fa-clock text-4xl text-yellow-400 mb-4"></i>
                <h3 class="font-bold text-white mb-2">Guaranteed Returns</h3>
                <p class="text-gray-400 text-sm">Get your investment plus ROI exactly when your package matures.</p>
            </div>
            <div class="bg-gray-800/30 rounded-xl p-6 backdrop-blur-sm text-center">
                <i class="fas fa-headset text-4xl text-emerald-400 mb-4"></i>
                <h3 class="font-bold text-white mb-2">24/7 Support</h3>
                <p class="text-gray-400 text-sm">Our support team is always ready to help you with any questions.</p>
            </div>
        </section>
    </main><br><br>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden">
        <div class="grid grid-cols-4 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="/user/packages.php" class="flex flex-col items-center py-2 text-emerald-400">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Trade</span>
            </a>
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-list text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/profile.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-user text-xl mb-1"></i>
                <span class="text-xs">Profile</span>
            </a>
        </div>
    </div>

    <script>
        // Countdown timers for active packages
        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(element => {
                const maturityDate = new Date(element.getAttribute('data-maturity')).getTime();
                const now = new Date().getTime();
                const distance = maturityDate - now;

                if (distance > 0) {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    if (days > 0) {
                        element.innerHTML = `${days}d ${hours}h ${minutes}m`;
                    } else {
                        element.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
                    }
                } else {
                    element.innerHTML = 'Matured - Refresh page';
                    element.classList.add('text-emerald-400');
                }
            });
        }

        // Update countdowns every second
        updateCountdowns();
        setInterval(updateCountdowns, 1000);

        // Scroll to specific package
        function scrollToPackage(packageId) {
            const packageElement = document.querySelector(`input[value="${packageId}"]`).closest('.package-card');
            packageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            packageElement.style.boxShadow = '0 0 20px rgba(16, 185, 129, 0.5)';
            setTimeout(() => {
                packageElement.style.boxShadow = '';
            }, 3000);
        }

        // Real-time ROI calculation
        document.querySelectorAll('input[name="investment_amount"]').forEach(input => {
            const packageCard = input.closest('.package-card');
            const roiPercentage = parseFloat(packageCard.querySelector('.roi-badge').textContent);
            
            input.addEventListener('input', function() {
                const amount = parseFloat(this.value) || 0;
                const roi = (amount * roiPercentage) / 100;
                const total = amount + roi;
                
                // Update the example return display
                const exampleReturn = packageCard.querySelector('.bg-gray-800\\/50 .text-emerald-400');
                if (exampleReturn) {
                    exampleReturn.textContent = `KSh ${total.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                }
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                submitButton.disabled = true;
            });
        });
    </script>
</body>
</html>