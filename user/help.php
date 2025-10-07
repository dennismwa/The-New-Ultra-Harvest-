<?php
require_once 'config/database.php';

// Get search query if provided
$search_query = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? 'all');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Ultra Harvest Global</title>
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
        
        .help-section {
            scroll-margin-top: 100px;
        }
        
        .step-number {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .category-card {
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .faq-item {
            transition: all 0.3s ease;
        }
        
        .search-highlight {
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            color: black;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="/" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">
                            Ultra Harvest
                        </span>
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                        <a href="/register.php" class="text-gray-300 hover:text-emerald-400 transition">Register</a>
                        <a href="/login.php" class="text-gray-300 hover:text-emerald-400 transition">Login</a>
                        <a href="/help.php" class="text-emerald-400 font-medium">Help</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <?php if (isLoggedIn()): ?>
                        <a href="/user/dashboard.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            Dashboard
                        </a>
                        <a href="/user/support.php" class="text-gray-400 hover:text-white" title="Support">
                            <i class="fas fa-headset text-xl"></i>
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            Get Started
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="text-center mb-12">
            <h1 class="text-5xl font-bold mb-4">
                How can we <span class="bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">help you?</span>
            </h1>
            <p class="text-xl text-gray-300 max-w-2xl mx-auto mb-8">
                Find answers to your questions, learn how to use our platform, and get the most out of Ultra Harvest
            </p>
            
            <!-- Search Bar -->
            <div class="max-w-2xl mx-auto">
                <form method="GET" class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                           class="w-full px-6 py-4 bg-gray-800 border border-gray-600 rounded-full text-white text-lg focus:border-emerald-500 focus:outline-none pr-16"
                           placeholder="Search for help articles, guides, and FAQs...">
                    <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-full transition">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Quick Categories -->
        <section class="mb-16">
            <h2 class="text-3xl font-bold text-center mb-8">Browse by Category</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <a href="#getting-started" class="category-card glass-card rounded-xl p-6 text-center hover:border-emerald-500/50">
                    <div class="w-16 h-16 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-rocket text-emerald-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Getting Started</h3>
                    <p class="text-gray-400 text-sm">Learn the basics of Ultra Harvest</p>
                </a>
                
                <a href="#deposits" class="category-card glass-card rounded-xl p-6 text-center hover:border-emerald-500/50">
                    <div class="w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-arrow-down text-blue-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Deposits</h3>
                    <p class="text-gray-400 text-sm">How to add funds to your account</p>
                </a>
                
                <a href="#packages" class="category-card glass-card rounded-xl p-6 text-center hover:border-emerald-500/50">
                    <div class="w-16 h-16 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-line text-yellow-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Trading Packages</h3>
                    <p class="text-gray-400 text-sm">Understanding investment packages</p>
                </a>
                
                <a href="#withdrawals" class="category-card glass-card rounded-xl p-6 text-center hover:border-emerald-500/50">
                    <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-arrow-up text-red-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Withdrawals</h3>
                    <p class="text-gray-400 text-sm">How to withdraw your funds</p>
                </a>
            </div>
        </section>

        <!-- Getting Started Section -->
        <section id="getting-started" class="help-section mb-16">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-3xl font-bold text-white mb-8 flex items-center">
                    <i class="fas fa-rocket text-emerald-400 mr-4"></i>
                    Getting Started
                </h2>
                
                <div class="grid lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">Welcome to Ultra Harvest</h3>
                        <p class="text-gray-300 mb-6">Ultra Harvest is a trading platform that allows you to invest in various packages and earn guaranteed returns. Here's how to get started:</p>
                        
                        <div class="space-y-4">
                            <div class="flex items-start space-x-4">
                                <div class="step-number">1</div>
                                <div>
                                    <h4 class="font-bold text-white">Create Account</h4>
                                    <p class="text-gray-300 text-sm">Register with your email, phone number, and create a secure password.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">2</div>
                                <div>
                                    <h4 class="font-bold text-white">Verify Information</h4>
                                    <p class="text-gray-300 text-sm">Complete your profile with accurate information for security purposes.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">3</div>
                                <div>
                                    <h4 class="font-bold text-white">Make First Deposit</h4>
                                    <p class="text-gray-300 text-sm">Add funds to your wallet using M-Pesa (minimum KSh 100).</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">4</div>
                                <div>
                                    <h4 class="font-bold text-white">Choose Package</h4>
                                    <p class="text-gray-300 text-sm">Select a trading package that fits your investment goals.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">Quick Tips</h3>
                        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-lg p-6">
                            <ul class="space-y-3 text-gray-300">
                                <li class="flex items-start space-x-2">
                                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                                    <span>Start with smaller amounts to familiarize yourself with the platform</span>
                                </li>
                                <li class="flex items-start space-x-2">
                                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                                    <span>Keep your login credentials secure and don't share them</span>
                                </li>
                                <li class="flex items-start space-x-2">
                                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                                    <span>Use your referral code to earn commissions from friends</span>
                                </li>
                                <li class="flex items-start space-x-2">
                                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                                    <span>Contact support if you need any assistance</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Deposits Section -->
        <section id="deposits" class="help-section mb-16">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-3xl font-bold text-white mb-8 flex items-center">
                    <i class="fas fa-arrow-down text-blue-400 mr-4"></i>
                    Making Deposits
                </h2>
                
                <div class="grid lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">How to Deposit via M-Pesa</h3>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex items-start space-x-4">
                                <div class="step-number">1</div>
                                <div>
                                    <h4 class="font-bold text-white">Go to Deposit Page</h4>
                                    <p class="text-gray-300 text-sm">Navigate to the deposit section in your dashboard.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">2</div>
                                <div>
                                    <h4 class="font-bold text-white">Enter Amount</h4>
                                    <p class="text-gray-300 text-sm">Choose or enter the amount you want to deposit (min KSh 100).</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">3</div>
                                <div>
                                    <h4 class="font-bold text-white">Confirm Phone Number</h4>
                                    <p class="text-gray-300 text-sm">Ensure your M-Pesa phone number is correct (254XXXXXXXXX format).</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">4</div>
                                <div>
                                    <h4 class="font-bold text-white">Complete Payment</h4>
                                    <p class="text-gray-300 text-sm">Check your phone for M-Pesa prompt and enter your PIN.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">5</div>
                                <div>
                                    <h4 class="font-bold text-white">Instant Credit</h4>
                                    <p class="text-gray-300 text-sm">Your wallet will be credited automatically upon payment confirmation.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">Deposit Information</h3>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-emerald-400 mb-2">Minimum Deposit</h4>
                                <p class="text-gray-300">KSh 100</p>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-emerald-400 mb-2">Maximum Deposit</h4>
                                <p class="text-gray-300">KSh 1,000,000</p>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-emerald-400 mb-2">Processing Time</h4>
                                <p class="text-gray-300">Instant (upon M-Pesa confirmation)</p>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-emerald-400 mb-2">Fees</h4>
                                <p class="text-gray-300">No fees charged by Ultra Harvest</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                            <h4 class="font-bold text-yellow-400 mb-2">Important Notes:</h4>
                            <ul class="text-gray-300 text-sm space-y-1">
                                <li>• Ensure you have sufficient M-Pesa balance</li>
                                <li>• Double-check your phone number before submitting</li>
                                <li>• Contact support if payment doesn't reflect within 10 minutes</li>
                                <li>• Keep your M-Pesa transaction ID for reference</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trading Packages Section -->
        <section id="packages" class="help-section mb-16">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-3xl font-bold text-white mb-8 flex items-center">
                    <i class="fas fa-chart-line text-yellow-400 mr-4"></i>
                    Trading Packages
                </h2>
                
                <div class="grid lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">How Trading Packages Work</h3>
                        <p class="text-gray-300 mb-6">Trading packages are investment plans where you commit funds for a specific duration and receive guaranteed returns.</p>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-white mb-2">🌱 Seed Package</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-400">Minimum:</span>
                                        <span class="text-white ml-2">KSh 300</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">ROI:</span>
                                        <span class="text-emerald-400 ml-2">10%</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">Duration:</span>
                                        <span class="text-white ml-2">24 hours</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">Best for:</span>
                                        <span class="text-white ml-2">Beginners</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-white mb-2">🌿 Sprout Package</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-400">Minimum:</span>
                                        <span class="text-white ml-2">KSh 30,000</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">ROI:</span>
                                        <span class="text-emerald-400 ml-2">12%</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">Duration:</span>
                                        <span class="text-white ml-2">24 hours</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">Best for:</span>
                                        <span class="text-white ml-2">Intermediate</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-white mb-2">🌳 Growth Package</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-400">Minimum:</span>
                                        <span class="text-white ml-2">KSh 50,000</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">ROI:</span>
                                        <span class="text-emerald-400 ml-2">14%</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">Duration:</span>
                                        <span class="text-white ml-2">24 hours</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">Best for:</span>
                                        <span class="text-white ml-2">Advanced</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">Package Investment Process</h3>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex items-start space-x-4">
                                <div class="step-number">1</div>
                                <div>
                                    <h4 class="font-bold text-white">Ensure Sufficient Balance</h4>
                                    <p class="text-gray-300 text-sm">Make sure your wallet has enough funds for the package minimum.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">2</div>
                                <div>
                                    <h4 class="font-bold text-white">Select Package</h4>
                                    <p class="text-gray-300 text-sm">Choose a package that matches your investment amount and goals.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">3</div>
                                <div>
                                    <h4 class="font-bold text-white">Enter Investment Amount</h4>
                                    <p class="text-gray-300 text-sm">Specify how much you want to invest (within package limits).</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">4</div>
                                <div>
                                    <h4 class="font-bold text-white">Confirm Investment</h4>
                                    <p class="text-gray-300 text-sm">Review details and confirm to activate the package.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">5</div>
                                <div>
                                    <h4 class="font-bold text-white">Wait for Maturity</h4>
                                    <p class="text-gray-300 text-sm">Your investment will mature automatically and profits will be credited.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-lg p-4">
                            <h4 class="font-bold text-emerald-400 mb-2">Key Benefits:</h4>
                            <ul class="text-gray-300 text-sm space-y-1">
                                <li>• Guaranteed returns on all packages</li>
                                <li>• Automatic profit calculation and crediting</li>
                                <li>• Multiple packages can be active simultaneously</li>
                                <li>• No hidden fees or charges</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Withdrawals Section -->
        <section id="withdrawals" class="help-section mb-16">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-3xl font-bold text-white mb-8 flex items-center">
                    <i class="fas fa-arrow-up text-red-400 mr-4"></i>
                    Withdrawals
                </h2>
                
                <div class="grid lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">How to Withdraw Funds</h3>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex items-start space-x-4">
                                <div class="step-number">1</div>
                                <div>
                                    <h4 class="font-bold text-white">Go to Withdrawal Page</h4>
                                    <p class="text-gray-300 text-sm">Navigate to the withdrawal section in your dashboard.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">2</div>
                                <div>
                                    <h4 class="font-bold text-white">Enter Amount</h4>
                                    <p class="text-gray-300 text-sm">Specify how much you want to withdraw (min KSh 100).</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">3</div>
                                <div>
                                    <h4 class="font-bold text-white">Confirm M-Pesa Number</h4>
                                    <p class="text-gray-300 text-sm">Ensure your M-Pesa number is correct for receiving funds.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">4</div>
                                <div>
                                    <h4 class="font-bold text-white">Submit Request</h4>
                                    <p class="text-gray-300 text-sm">Submit your withdrawal request for processing.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">5</div>
                                <div>
                                    <h4 class="font-bold text-white">Receive Funds</h4>
                                    <p class="text-gray-300 text-sm">Funds will be sent to your M-Pesa within 24 hours.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">Withdrawal Information</h3>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-red-400 mb-2">Minimum Withdrawal</h4>
                                <p class="text-gray-300">KSh 100</p>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-red-400 mb-2">Maximum Withdrawal</h4>
                                <p class="text-gray-300">KSh 1,000,000 per request</p>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-red-400 mb-2">Processing Time</h4>
                                <p class="text-gray-300">Within 24 hours (usually 2-6 hours)</p>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-bold text-red-400 mb-2">Fees</h4>
                                <p class="text-gray-300">No fees charged by Ultra Harvest</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                            <h4 class="font-bold text-red-400 mb-2">Important Notes:</h4>
                            <ul class="text-gray-300 text-sm space-y-1">
                                <li>• Withdrawals are processed during business hours</li>
                                <li>• Ensure your M-Pesa number is active and correct</li>
                                <li>• You cannot cancel a withdrawal once submitted</li>
                                <li>• Contact support if you don't receive funds within 24 hours</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Referral Program Section -->
        <section id="referrals" class="help-section mb-16">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-3xl font-bold text-white mb-8 flex items-center">
                    <i class="fas fa-users text-purple-400 mr-4"></i>
                    Referral Program
                </h2>
                
                <div class="grid lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">How It Works</h3>
                        <p class="text-gray-300 mb-6">Earn commissions by referring friends and family to Ultra Harvest. You get paid when they make deposits or earn ROI.</p>
                        
                        <div class="space-y-4">
                            <div class="bg-gradient-to-r from-emerald-600/20 to-emerald-800/20 rounded-lg p-4">
                                <h4 class="font-bold text-emerald-400 mb-2">Level 1 - Direct Referrals</h4>
                                <p class="text-gray-300 text-sm mb-2">Earn <strong>10% commission</strong> on:</p>
                                <ul class="text-gray-300 text-sm space-y-1 ml-4">
                                    <li>• Deposits made by users you directly refer</li>
                                    <li>• ROI payments earned by your direct referrals</li>
                                </ul>
                            </div>
                            
                            <div class="bg-gradient-to-r from-purple-600/20 to-purple-800/20 rounded-lg p-4">
                                <h4 class="font-bold text-purple-400 mb-2">Level 2 - Indirect Referrals</h4>
                                <p class="text-gray-300 text-sm mb-2">Earn <strong>5% commission</strong> on:</p>
                                <ul class="text-gray-300 text-sm space-y-1 ml-4">
                                    <li>• Deposits made by referrals of your referrals</li>
                                    <li>• ROI payments earned by second-level referrals</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-bold text-white mb-4">Getting Started with Referrals</h3>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex items-start space-x-4">
                                <div class="step-number">1</div>
                                <div>
                                    <h4 class="font-bold text-white">Find Your Code</h4>
                                    <p class="text-gray-300 text-sm">Go to your dashboard to find your unique referral code.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">2</div>
                                <div>
                                    <h4 class="font-bold text-white">Share Your Code</h4>
                                    <p class="text-gray-300 text-sm">Share via WhatsApp, social media, or word of mouth.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">3</div>
                                <div>
                                    <h4 class="font-bold text-white">They Register</h4>
                                    <p class="text-gray-300 text-sm">When someone uses your code to register, they become your referral.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="step-number">4</div>
                                <div>
                                    <h4 class="font-bold text-white">Earn Commissions</h4>
                                    <p class="text-gray-300 text-sm">Get paid automatically when they deposit or earn ROI.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                            <h4 class="font-bold text-yellow-400 mb-2">Tips for Success:</h4>
                            <ul class="text-gray-300 text-sm space-y-1">
                                <li>• Share your own success story</li>
                                <li>• Explain how the platform works</li>
                                <li>• Help your referrals get started</li>
                                <li>• Stay active and engaged yourself</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="mb-16">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-3xl font-bold text-white mb-8 text-center">Frequently Asked Questions</h2>
                
                <div class="max-w-4xl mx-auto space-y-4">
                    <div class="faq-item">
                        <div class="bg-gray-800/50 rounded-lg p-6">
                            <h3 class="font-bold text-white mb-3 cursor-pointer flex items-center justify-between" onclick="toggleFaq(this)">
                                Is Ultra Harvest safe and legitimate?
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </h3>
                            <div class="faq-content hidden">
                                <p class="text-gray-300">Yes, Ultra Harvest is a registered trading platform that uses bank-level security measures. We maintain adequate reserves to honor all withdrawals and have been operating successfully since our launch.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="bg-gray-800/50 rounded-lg p-6">
                            <h3 class="font-bold text-white mb-3 cursor-pointer flex items-center justify-between" onclick="toggleFaq(this)">
                                How are profits generated?
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </h3>
                            <div class="faq-content hidden">
                                <p class="text-gray-300">Ultra Harvest generates profits through diversified trading activities including forex trading, cryptocurrency arbitrage, and agricultural commodity trading. Our experienced trading team ensures consistent returns.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="bg-gray-800/50 rounded-lg p-6">
                            <h3 class="font-bold text-white mb-3 cursor-pointer flex items-center justify-between" onclick="toggleFaq(this)">
                                Can I have multiple active packages?
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </h3>
                            <div class="faq-content hidden">
                                <p class="text-gray-300">Yes, you can have multiple packages running simultaneously. There's no limit to the number of packages you can activate, as long as you have sufficient wallet balance.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="bg-gray-800/50 rounded-lg p-6">
                            <h3 class="font-bold text-white mb-3 cursor-pointer flex items-center justify-between" onclick="toggleFaq(this)">
                                What happens if I forget my password?
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </h3>
                            <div class="faq-content hidden">
                                <p class="text-gray-300">You can reset your password using the "Forgot Password" link on the login page. You'll receive a reset link via email. If you don't receive it, check your spam folder or contact support.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="bg-gray-800/50 rounded-lg p-6">
                            <h3 class="font-bold text-white mb-3 cursor-pointer flex items-center justify-between" onclick="toggleFaq(this)">
                                Are there any hidden fees?
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </h3>
                            <div class="faq-content hidden">
                                <p class="text-gray-300">No, Ultra Harvest does not charge any hidden fees. We don't charge for deposits, withdrawals, or package activations. The only fees you might encounter are from your mobile money provider.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="bg-gray-800/50 rounded-lg p-6">
                            <h3 class="font-bold text-white mb-3 cursor-pointer flex items-center justify-between" onclick="toggleFaq(this)">
                                How can I contact customer support?
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </h3>
                            <div class="faq-content hidden">
                                <p class="text-gray-300">You can contact our support team through multiple channels: create a support ticket in your dashboard, WhatsApp us at +254700000000, email us at support@ultraharvest.com, or use the live chat feature on our website.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Support Section -->
        <section class="text-center">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-3xl font-bold text-white mb-4">Still Need Help?</h2>
                <p class="text-xl text-gray-300 mb-8">Our support team is here to assist you 24/7</p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <?php if (isLoggedIn()): ?>
                        <a href="/user/support.php" class="px-8 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition text-lg">
                            <i class="fas fa-ticket-alt mr-2"></i>Create Support Ticket
                        </a>
                    <?php endif; ?>
                    
                    <a href="https://wa.me/254700000000" target="_blank" class="px-8 py-4 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition text-lg">
                        <i class="fab fa-whatsapp mr-2"></i>WhatsApp Support
                    </a>
                    
                    <a href="mailto:support@ultraharvest.com" class="px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition text-lg">
                        <i class="fas fa-envelope mr-2"></i>Email Support
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800/50 border-t border-gray-700 mt-16">
        <div class="container mx-auto px-4 py-8">
            <div class="text-center text-gray-400">
                <p>&copy; 2025 Ultra Harvest Global. All rights reserved.</p>
                <p class="mt-2">Growing Wealth Together 🌱</p>
            </div>
        </div>
    </footer>

    <script>
        // Toggle FAQ items
        function toggleFaq(element) {
            const content = element.nextElementSibling;
            const icon = element.querySelector('i');
            
            content.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
            
            // Close other FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                if (item !== element.closest('.faq-item')) {
                    const otherContent = item.querySelector('.faq-content');
                    const otherIcon = item.querySelector('i');
                    otherContent.classList.add('hidden');
                    otherIcon.classList.remove('rotate-180');
                }
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Search functionality
        document.querySelector('form').addEventListener('submit', function(e) {
            const searchQuery = document.querySelector('input[name="search"]').value.trim();
            if (!searchQuery) {
                e.preventDefault();
                return;
            }
            
            // Highlight search terms on the page
            if (searchQuery.length > 2) {
                highlightSearchTerms(searchQuery);
            }
        });

        function highlightSearchTerms(query) {
            const content = document.querySelector('main');
            const regex = new RegExp(`(${query})`, 'gi');
            
            // Remove existing highlights
            content.innerHTML = content.innerHTML.replace(/<span class="search-highlight">(.*?)<\/span>/gi, '$1');
            
            // Add new highlights
            content.innerHTML = content.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
        }

        // Auto-expand search results if query exists
        <?php if ($search_query): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Expand all FAQ items if there's a search query
            document.querySelectorAll('.faq-content').forEach(content => {
                content.classList.remove('hidden');
            });
            document.querySelectorAll('.faq-item i').forEach(icon => {
                icon.classList.add('rotate-180');
            });
            
            // Highlight search terms
            highlightSearchTerms('<?php echo addslashes($search_query); ?>');
        });
        <?php endif; ?>

        // Add loading animation to external links
        document.querySelectorAll('a[target="_blank"]').forEach(link => {
            link.addEventListener('click', function() {
                this.innerHTML += ' <i class="fas fa-spinner fa-spin ml-1"></i>';
            });
        });
    </script>
</body>
</html>