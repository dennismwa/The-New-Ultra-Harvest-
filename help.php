<?php
require_once 'config/database.php';

// Check if user is logged in for personalized experience
$is_logged_in = isLoggedIn();
$user = null;

if ($is_logged_in) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Get FAQ categories and items
$faqs = [
    'Getting Started' => [
        [
            'question' => 'How do I create an account?',
            'answer' => 'Click the "Register" button on our homepage, fill in your details including full name, email, phone number, and create a secure password. You\'ll receive a confirmation email to verify your account.'
        ],
        [
            'question' => 'What documents do I need to register?',
            'answer' => 'You only need a valid phone number and email address to get started. No additional documentation is required for basic account creation.'
        ],
        [
            'question' => 'How do I make my first deposit?',
            'answer' => 'After logging in, go to the Deposit section, enter your desired amount (minimum KSh 100), provide your M-Pesa phone number, and follow the prompts to complete the payment.'
        ],
        [
            'question' => 'What is the minimum amount to start trading?',
            'answer' => 'The minimum deposit is KSh 100, and our most basic trading package (Seed) starts from KSh 500, making it accessible for everyone to begin their investment journey.'
        ]
    ],
    'Trading & Packages' => [
        [
            'question' => 'How do trading packages work?',
            'answer' => 'Choose a package, invest your desired amount, and our automated trading system works for you. Each package has a specific ROI percentage and duration. When the package matures, you receive your initial investment plus the guaranteed returns.'
        ],
        [
            'question' => 'What are the different package types?',
            'answer' => 'We offer four main packages: Seed (basic), Sprout (intermediate), Growth (advanced), and Harvest (premium). Each has different ROI rates, durations, and minimum investment requirements.'
        ],
        [
            'question' => 'How is ROI calculated and paid?',
            'answer' => 'ROI is calculated as a percentage of your investment amount. For example, if you invest KSh 1,000 in a 10% ROI package, you\'ll receive KSh 1,100 when it matures (your KSh 1,000 + KSh 100 profit).'
        ],
        [
            'question' => 'Can I invest in multiple packages?',
            'answer' => 'Yes! You can invest in multiple packages simultaneously. There\'s no limit to how many active packages you can have, as long as you have sufficient wallet balance.'
        ]
    ],
    'Deposits & Withdrawals' => [
        [
            'question' => 'How do I deposit money?',
            'answer' => 'Use M-Pesa to deposit funds instantly. Go to Deposit, enter amount, provide your M-Pesa number, and complete the STK push payment. Funds are credited immediately upon confirmation.'
        ],
        [
            'question' => 'What are the deposit limits?',
            'answer' => 'Minimum deposit: KSh 100. Maximum deposit: KSh 1,000,000 per transaction. There are no daily limits, so you can make multiple deposits if needed.'
        ],
        [
            'question' => 'How long do withdrawals take?',
            'answer' => 'Withdrawal requests are typically processed within 2-24 hours. You\'ll receive the funds directly to your M-Pesa account once approved by our team.'
        ],
        [
            'question' => 'Are there withdrawal fees?',
            'answer' => 'Ultra Harvest Global does not charge any withdrawal fees. You receive the full amount you request. However, your mobile operator may charge standard M-Pesa transaction fees.'
        ]
    ],
    'Referral Program' => [
        [
            'question' => 'How does the referral program work?',
            'answer' => 'Share your unique referral code with friends. When they register and make deposits, you earn commissions: 10% on direct referrals (Level 1) and 5% on indirect referrals (Level 2).'
        ],
        [
            'question' => 'When do I receive referral commissions?',
            'answer' => 'Commissions are credited to your wallet immediately when your referrals make deposits or receive ROI payments. You can then withdraw or reinvest these earnings.'
        ],
        [
            'question' => 'Is there a limit to referral earnings?',
            'answer' => 'No limits! The more people you refer and the more they invest, the more you earn. Some of our top referrers earn thousands of shillings monthly in passive commissions.'
        ]
    ],
    'Security & Account' => [
        [
            'question' => 'How secure is my money?',
            'answer' => 'We use bank-level encryption, secure servers, and strict financial protocols. Your funds are protected, and all transactions are monitored for security.'
        ],
        [
            'question' => 'What if I forget my password?',
            'answer' => 'Click "Forgot Password" on the login page, enter your email, and you\'ll receive reset instructions. For additional help, contact our support team.'
        ],
        [
            'question' => 'Can I change my phone number?',
            'answer' => 'Yes, you can update your phone number in your profile settings. For security, you may need to verify the change via email or contact support.'
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Ultra Harvest Global</title>
    <meta name="description" content="Get help with Ultra Harvest Global - FAQs, guides, and support for forex trading and investments">
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
        
        .hero-bg {
            background: linear-gradient(135deg, 
                rgba(16, 185, 129, 0.1) 0%, 
                rgba(251, 191, 36, 0.1) 50%, 
                rgba(16, 185, 129, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .search-box {
            transition: all 0.3s ease;
        }
        
        .search-box:focus-within {
            transform: scale(1.02);
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
        }
        
        .faq-item {
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .faq-item.active .faq-answer {
            max-height: 500px;
        }
        
        .faq-item.active .faq-toggle {
            transform: rotate(45deg);
        }
        
        .category-card {
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .quick-link {
            transition: all 0.3s ease;
        }
        
        .quick-link:hover {
            transform: translateX(5px);
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
                        <?php if ($is_logged_in): ?>
                            <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                            <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <?php endif; ?>
                        <a href="/help.php" class="text-emerald-400 font-medium">Help</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                        <div class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-4 py-2">
                            <i class="fas fa-wallet text-emerald-400"></i>
                            <span class="text-sm text-gray-300">Balance:</span>
                            <span class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></span>
                        </div>
                        <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                            <i class="fas fa-user-circle text-xl"></i>
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="text-gray-300 hover:text-emerald-400 transition">Login</a>
                        <a href="/register.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-bg py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl lg:text-6xl font-bold mb-6">
                How can we <span class="bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">help you?</span>
            </h1>
            <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">
                Find answers to your questions, learn how to use our platform, and get the support you need
            </p>
            
            <!-- Search Box -->
            <div class="max-w-2xl mx-auto">
                <div class="search-box glass-card rounded-full p-2">
                    <div class="flex items-center">
                        <div class="pl-4 pr-2">
                            <i class="fas fa-search text-gray-400 text-xl"></i>
                        </div>
                        <input 
                            type="text" 
                            id="searchInput"
                            placeholder="Search for help articles, FAQs, guides..."
                            class="flex-1 py-4 bg-transparent text-white placeholder-gray-400 focus:outline-none text-lg"
                        >
                        <button class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-full font-medium transition">
                            Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Help Categories -->
    <section class="py-16 bg-gray-800">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Quick Help Categories</h2>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="category-card glass-card rounded-xl p-6 text-center cursor-pointer" onclick="scrollToSection('getting-started')">
                    <div class="w-16 h-16 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-rocket text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Getting Started</h3>
                    <p class="text-gray-400">Learn the basics of creating an account and making your first investment</p>
                </div>

                <div class="category-card glass-card rounded-xl p-6 text-center cursor-pointer" onclick="scrollToSection('trading-packages')">
                    <div class="w-16 h-16 bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-line text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Trading & Packages</h3>
                    <p class="text-gray-400">Understand our trading packages and how to maximize your returns</p>
                </div>

                <div class="category-card glass-card rounded-xl p-6 text-center cursor-pointer" onclick="scrollToSection('deposits-withdrawals')">
                    <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exchange-alt text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Payments</h3>
                    <p class="text-gray-400">Everything about deposits, withdrawals, and M-Pesa transactions</p>
                </div>

                <div class="category-card glass-card rounded-xl p-6 text-center cursor-pointer" onclick="scrollToSection('referral-program')">
                    <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Referrals</h3>
                    <p class="text-gray-400">Learn how to earn commissions by referring friends and family</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Sections -->
    <section class="py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Frequently Asked Questions</h2>
            
            <div class="max-w-4xl mx-auto space-y-8">
                <?php foreach ($faqs as $category => $questions): ?>
                <div id="<?php echo strtolower(str_replace(['&', ' '], ['', '-'], $category)); ?>" class="glass-card rounded-xl p-6">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center">
                        <i class="fas <?php 
                        echo match($category) {
                            'Getting Started' => 'fa-rocket',
                            'Trading & Packages' => 'fa-chart-line',
                            'Deposits & Withdrawals' => 'fa-exchange-alt',
                            'Referral Program' => 'fa-users',
                            'Security & Account' => 'fa-shield-alt',
                            default => 'fa-question-circle'
                        };
                        ?> text-emerald-400 mr-3"></i>
                        <?php echo $category; ?>
                    </h3>
                    
                    <div class="space-y-4">
                        <?php foreach ($questions as $index => $faq): ?>
                        <div class="faq-item bg-gray-800/50 rounded-lg">
                            <button class="w-full p-4 text-left flex items-center justify-between" onclick="toggleFAQ(this)">
                                <span class="font-medium text-white pr-4"><?php echo htmlspecialchars($faq['question']); ?></span>
                                <i class="fas fa-plus faq-toggle text-emerald-400 flex-shrink-0 transition-transform"></i>
                            </button>
                            <div class="faq-answer">
                                <div class="px-4 pb-4 text-gray-300 leading-relaxed">
                                    <?php echo htmlspecialchars($faq['answer']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Quick Links & Contact -->
    <section class="py-16 bg-gray-800">
        <div class="container mx-auto px-4">
            <div class="grid lg:grid-cols-2 gap-12">
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-2xl font-bold text-white mb-8">Quick Links</h3>
                    <div class="space-y-4">
                        <?php if ($is_logged_in): ?>
                            <a href="/user/dashboard.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                                <i class="fas fa-tachometer-alt text-emerald-400 mr-4 text-xl"></i>
                                <div>
                                    <h4 class="font-medium text-white">Dashboard</h4>
                                    <p class="text-sm text-gray-400">View your account overview and recent activity</p>
                                </div>
                            </a>
                            <a href="/user/packages.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                                <i class="fas fa-chart-line text-yellow-400 mr-4 text-xl"></i>
                                <div>
                                    <h4 class="font-medium text-white">Trading Packages</h4>
                                    <p class="text-sm text-gray-400">Explore and invest in our trading packages</p>
                                </div>
                            </a>
                            <a href="/user/deposit.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                                <i class="fas fa-plus text-emerald-400 mr-4 text-xl"></i>
                                <div>
                                    <h4 class="font-medium text-white">Deposit Funds</h4>
                                    <p class="text-sm text-gray-400">Add money to your wallet via M-Pesa</p>
                                </div>
                            </a>
                            <a href="/user/referrals.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                                <i class="fas fa-users text-purple-400 mr-4 text-xl"></i>
                                <div>
                                    <h4 class="font-medium text-white">Referral Program</h4>
                                    <p class="text-sm text-gray-400">Earn commissions by inviting friends</p>
                                </div>
                            </a>
                        <?php else: ?>
                            <a href="/register.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                                <i class="fas fa-user-plus text-emerald-400 mr-4 text-xl"></i>
                                <div>
                                    <h4 class="font-medium text-white">Create Account</h4>
                                    <p class="text-sm text-gray-400">Sign up and start your investment journey</p>
                                </div>
                            </a>
                            <a href="/login.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                                <i class="fas fa-sign-in-alt text-blue-400 mr-4 text-xl"></i>
                                <div>
                                    <h4 class="font-medium text-white">Login</h4>
                                    <p class="text-sm text-gray-400">Access your existing account</p>
                                </div>
                            </a>
                        <?php endif; ?>
                        <a href="/terms.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                            <i class="fas fa-file-contract text-gray-400 mr-4 text-xl"></i>
                            <div>
                                <h4 class="font-medium text-white">Terms & Conditions</h4>
                                <p class="text-sm text-gray-400">Read our terms of service</p>
                            </div>
                        </a>
                        <a href="/privacy.php" class="quick-link flex items-center p-4 glass-card rounded-lg hover:bg-gray-700/50 transition">
                            <i class="fas fa-shield-alt text-gray-400 mr-4 text-xl"></i>
                            <div>
                                <h4 class="font-medium text-white">Privacy Policy</h4>
                                <p class="text-sm text-gray-400">Learn how we protect your data</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Contact Support -->
                <div>
                    <h3 class="text-2xl font-bold text-white mb-8">Need More Help?</h3>
                    
                    <div class="glass-card rounded-xl p-6 mb-6">
                        <h4 class="text-xl font-bold text-white mb-4">Contact Our Support Team</h4>
                        <p class="text-gray-300 mb-6">
                            Can't find what you're looking for? Our friendly support team is here to help you 24/7.
                        </p>
                        
                        <div class="space-y-4">
                            <div class="flex items-center p-4 bg-gray-800/50 rounded-lg">
                                <i class="fab fa-whatsapp text-green-400 text-2xl mr-4"></i>
                                <div class="flex-1">
                                    <h5 class="font-medium text-white">WhatsApp Support</h5>
                                    <p class="text-sm text-gray-400">Instant responses, 24/7 availability</p>
                                </div>
                                <a href="https://wa.me/254700000000" target="_blank" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                                    Chat Now
                                </a>
                            </div>

                            <div class="flex items-center p-4 bg-gray-800/50 rounded-lg">
                                <i class="fas fa-envelope text-blue-400 text-2xl mr-4"></i>
                                <div class="flex-1">
                                    <h5 class="font-medium text-white">Email Support</h5>
                                    <p class="text-sm text-gray-400">support@ultraharvest.com</p>
                                </div>
                                <a href="mailto:support@ultraharvest.com" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                                    Send Email
                                </a>
                            </div>

                            <?php if ($is_logged_in): ?>
                            <div class="flex items-center p-4 bg-gray-800/50 rounded-lg">
                                <i class="fas fa-ticket-alt text-purple-400 text-2xl mr-4"></i>
                                <div class="flex-1">
                                    <h5 class="font-medium text-white">Support Ticket</h5>
                                    <p class="text-sm text-gray-400">Create a detailed support request</p>
                                </div>
                                <a href="/user/support.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition">
                                    Create Ticket
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Response Times -->
                    <div class="bg-emerald-600/20 border border-emerald-600/30 rounded-lg p-4">
                        <h5 class="font-bold text-emerald-400 mb-2">Our Response Times</h5>
                        <div class="space-y-2 text-sm text-gray-300">
                            <div class="flex justify-between">
                                <span>WhatsApp:</span>
                                <span class="text-emerald-400">Instant - 5 minutes</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Email:</span>
                                <span class="text-yellow-400">Within 2-4 hours</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Support Tickets:</span>
                                <span class="text-blue-400">Within 24 hours</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Guides -->
    <section class="py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Popular Guides</h2>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="glass-card rounded-xl p-6">
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-play text-emerald-400 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Getting Started Guide</h3>
                    <p class="text-gray-400 mb-4">Step-by-step walkthrough for new users to make their first investment</p>
                    <a href="#getting-started" class="text-emerald-400 hover:text-emerald-300 font-medium">Read Guide →</a>
                </div>

                <div class="glass-card rounded-xl p-6">
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-chart-bar text-yellow-400 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Maximizing Returns</h3>
                    <p class="text-gray-400 mb-4">Learn strategies to optimize your trading package investments</p>
                    <a href="#trading-packages" class="text-yellow-400 hover:text-yellow-300 font-medium">Read Guide →</a>
                </div>

                <div class="glass-card rounded-xl p-6">
                    <div class="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-network-wired text-purple-400 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Referral Success</h3>
                    <p class="text-gray-400 mb-4">Tips and strategies to build a profitable referral network</p>
                    <a href="#referral-program" class="text-purple-400 hover:text-purple-300 font-medium">Read Guide →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 bg-gray-900 border-t border-gray-700">
        <div class="container mx-auto px-4">
            <div class="grid lg:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-seedling text-white"></i>
                        </div>
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest Global</span>
</div>
<p class="text-gray-400 text-lg font-medium mb-4">Growing Wealth Together</p>
<p class="text-gray-500">Your trusted partner in forex trading and wealth creation.</p>
</div>
            <div class="lg:text-center">
                <h3 class="font-semibold text-lg mb-4 text-white">Quick Links</h3>
                <div class="space-y-2">
                    <a href="/terms.php" class="block text-gray-400 hover:text-emerald-400 transition">Terms & Conditions</a>
                    <a href="/privacy.php" class="block text-gray-400 hover:text-emerald-400 transition">Privacy Policy</a>
                    <a href="/help.php" class="block text-gray-400 hover:text-emerald-400 transition">Help Center</a>
                    <?php if ($is_logged_in): ?>
                        <a href="/user/settings.php" class="block text-gray-400 hover:text-emerald-400 transition">Account Settings</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="lg:text-right">
                <h3 class="font-semibold text-lg mb-4 text-white">Connect With Us</h3>
                <div class="flex lg:justify-end space-x-4 mb-4">
                    <a href="#" class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                        <i class="fab fa-facebook-f text-white"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                        <i class="fab fa-twitter text-white"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                        <i class="fab fa-instagram text-white"></i>
                    </a>
                    <a href="https://wa.me/254700000000" class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                        <i class="fab fa-whatsapp text-white"></i>
                    </a>
                </div>
                <p class="text-gray-500 text-sm">
                    © <?php echo date('Y'); ?> Ultra Harvest Global. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button id="backToTop" class="fixed bottom-6 right-6 w-12 h-12 bg-emerald-600 hover:bg-emerald-700 text-white rounded-full shadow-lg transition-all duration-300 transform scale-0 z-50">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Search Results Modal -->
<div id="searchModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-gray-800 rounded-xl p-6 max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Search Results</h3>
            <button onclick="closeSearchModal()" class="text-gray-400 hover:text-white transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div id="searchResults" class="overflow-y-auto max-h-96">
            <!-- Search results will be populated here -->
        </div>
    </div>
</div>

<script>
    // FAQ Toggle functionality
    function toggleFAQ(button) {
        const faqItem = button.closest('.faq-item');
        const isActive = faqItem.classList.contains('active');
        
        // Close all other FAQs in the same section
        const section = faqItem.closest('.glass-card');
        section.querySelectorAll('.faq-item.active').forEach(item => {
            if (item !== faqItem) {
                item.classList.remove('active');
            }
        });
        
        // Toggle current FAQ
        faqItem.classList.toggle('active', !isActive);
    }

    // Smooth scroll to sections
    function scrollToSection(sectionId) {
        const element = document.getElementById(sectionId);
        if (element) {
            element.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start',
                inline: 'nearest'
            });
            
            // Highlight the section briefly
            element.style.boxShadow = '0 0 20px rgba(16, 185, 129, 0.5)';
            setTimeout(() => {
                element.style.boxShadow = '';
            }, 3000);
        }
    }

    // Search functionality
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const allFAQs = [];

    // Collect all FAQs for searching
    document.querySelectorAll('.faq-item').forEach(item => {
        const question = item.querySelector('button span').textContent;
        const answer = item.querySelector('.faq-answer .px-4').textContent;
        allFAQs.push({ question, answer, element: item });
    });

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(this.value);
        }, 300);
    });

    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch(this.value);
        }
    });

    function performSearch(query) {
        if (!query || query.length < 2) {
            closeSearchModal();
            return;
        }

        const results = allFAQs.filter(faq => 
            faq.question.toLowerCase().includes(query.toLowerCase()) ||
            faq.answer.toLowerCase().includes(query.toLowerCase())
        );

        displaySearchResults(results, query);
    }

    function displaySearchResults(results, query) {
        const modal = document.getElementById('searchModal');
        const resultsContainer = document.getElementById('searchResults');
        
        if (results.length === 0) {
            resultsContainer.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-search text-4xl text-gray-600 mb-4"></i>
                    <h4 class="text-lg font-bold text-gray-400 mb-2">No results found</h4>
                    <p class="text-gray-500">Try different keywords or contact our support team</p>
                    <div class="mt-4">
                        <a href="https://wa.me/254700000000" target="_blank" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                            <i class="fab fa-whatsapp mr-2"></i>Ask Support
                        </a>
                    </div>
                </div>
            `;
        } else {
            resultsContainer.innerHTML = results.map(result => `
                <div class="p-4 border-b border-gray-700 last:border-b-0 cursor-pointer hover:bg-gray-700/50 transition" onclick="goToFAQ(this, '${result.question}')">
                    <h4 class="font-medium text-white mb-2">${highlightSearchTerm(result.question, query)}</h4>
                    <p class="text-sm text-gray-400">${highlightSearchTerm(result.answer.substring(0, 150) + '...', query)}</p>
                </div>
            `).join('');
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function highlightSearchTerm(text, term) {
        const regex = new RegExp(`(${term})`, 'gi');
        return text.replace(regex, '<mark class="bg-emerald-500/30 text-emerald-300">$1</mark>');
    }

    function goToFAQ(element, question) {
        closeSearchModal();
        
        // Find and open the FAQ
        const faqElement = allFAQs.find(faq => faq.question === question)?.element;
        if (faqElement) {
            faqElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            faqElement.classList.add('active');
            
            // Highlight the FAQ briefly
            faqElement.style.background = 'rgba(16, 185, 129, 0.1)';
            setTimeout(() => {
                faqElement.style.background = '';
            }, 3000);
        }
    }

    function closeSearchModal() {
        const modal = document.getElementById('searchModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Back to top functionality
    const backToTopButton = document.getElementById('backToTop');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTopButton.classList.remove('scale-0');
            backToTopButton.classList.add('scale-100');
        } else {
            backToTopButton.classList.add('scale-0');
            backToTopButton.classList.remove('scale-100');
        }
    });

    backToTopButton.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Close search modal when clicking outside
    document.getElementById('searchModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeSearchModal();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSearchModal();
        }
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
        }
    });

    // Auto-expand FAQ if URL has hash
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash) {
            const targetSection = document.querySelector(window.location.hash);
            if (targetSection) {
                setTimeout(() => {
                    targetSection.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            }
        }
        
        // Add keyboard shortcut hint
        const searchBox = document.querySelector('.search-box');
        if (searchBox) {
            searchBox.setAttribute('title', 'Ctrl + K to focus search');
        }
    });

    // Analytics tracking for help interactions
    function trackHelpInteraction(action, details) {
        // This would integrate with your analytics service
        console.log('Help interaction:', action, details);
    }

    // Track FAQ opens
    document.querySelectorAll('.faq-item button').forEach(button => {
        button.addEventListener('click', function() {
            const question = this.querySelector('span').textContent;
            trackHelpInteraction('faq_opened', { question });
        });
    });

    // Track category clicks
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', function() {
            const category = this.querySelector('h3').textContent;
            trackHelpInteraction('category_clicked', { category });
        });
    });

    // Mobile menu for help categories
    function createMobileHelpMenu() {
        const isMobile = window.innerWidth < 768;
        const categoriesSection = document.querySelector('.grid.md\\:grid-cols-2.lg\\:grid-cols-4');
        
        if (isMobile && categoriesSection) {
            categoriesSection.classList.add('grid-cols-1');
        }
    }

    window.addEventListener('resize', createMobileHelpMenu);
    createMobileHelpMenu();

    // Lazy loading for better performance
    const observerOptions = {
        root: null,
        rootMargin: '50px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
            }
        });
    }, observerOptions);

    // Observe FAQ sections for animations
    document.querySelectorAll('.glass-card').forEach(card => {
        observer.observe(card);
    });

    // Add CSS for fade-in animation
    const style = document.createElement('style');
    style.textContent = `
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        mark {
            background-color: rgba(16, 185, 129, 0.3) !important;
            color: rgb(110, 231, 183) !important;
            padding: 1px 2px;
            border-radius: 2px;
        }
    `;
    document.head.appendChild(style);
</script>
</body>
</html>