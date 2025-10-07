<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get referral statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_referrals,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_referrals,
        COALESCE(SUM(total_deposited), 0) as referrals_deposited
    FROM users 
    WHERE referred_by = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Get commission statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_commissions,
        COALESCE(SUM(amount), 0) as total_earned
    FROM transactions 
    WHERE user_id = ? AND type = 'referral_commission' AND status = 'completed'
");
$stmt->execute([$user_id]);
$commission_stats = $stmt->fetch();

// Get referrals list with detailed info
$stmt = $db->prepare("
    SELECT u.*, 
           COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_deposited,
           COALESCE(SUM(CASE WHEN t.type = 'referral_commission' AND t.user_id = ? AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as commission_earned
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    WHERE u.referred_by = ?
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$referrals = $stmt->fetchAll();

// Get recent commission transactions
$stmt = $db->prepare("
    SELECT t.*, u.full_name as referral_name
    FROM transactions t
    LEFT JOIN users u ON u.referred_by = t.user_id
    WHERE t.user_id = ? AND t.type = 'referral_commission'
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_commissions = $stmt->fetchAll();

// Get commission rates
$l1_rate = getSystemSetting('referral_commission_l1', 10);
$l2_rate = getSystemSetting('referral_commission_l2', 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network - Ultra Harvest Global</title>
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
        
        .referral-card {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(251, 191, 36, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .copy-animation {
            animation: copySuccess 0.3s ease-out;
        }
        
        @keyframes copySuccess {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .level-badge {
            background: linear-gradient(45deg, #8b5cf6, #a78bfa);
        }
        
        .share-btn {
            transition: all 0.3s ease;
        }
        
        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
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
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Trade</a>
                        <a href="/user/referrals.php" class="text-emerald-400 font-medium">Network</a>
                        <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-4 py-2">
                        <i class="fas fa-users text-purple-400"></i>
                        <span class="text-sm text-gray-300">Referrals:</span>
                        <span class="font-bold text-white"><?php echo number_format($stats['total_referrals']); ?></span>
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
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">
                <i class="fas fa-users text-purple-400 mr-3"></i>
                Network (Referrals)
            </h1>
            <p class="text-xl text-gray-300">Invite friends and earn commission on their activities</p>
        </div>

        <!-- REORDERED AS PER CLIENT: Dashboard first, then stats, then how it works, then referral code -->
        
        <!-- 1. DASHBOARD - Statistics Overview FIRST -->
        <section class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Referrals</p>
                        <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_referrals']); ?></p>
                        <p class="text-emerald-400 text-sm">
                            <?php echo number_format($stats['recent_referrals']); ?> this month
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Commission Earned</p>
                        <p class="text-2xl font-bold text-emerald-400"><?php echo formatMoney($commission_stats['total_earned']); ?></p>
                        <p class="text-gray-400 text-sm">
                            <?php echo number_format($commission_stats['total_commissions']); ?> payments
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-emerald-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Referrals Deposited</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo formatMoney($stats['referrals_deposited']); ?></p>
                        <p class="text-gray-400 text-sm">Total volume</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Commission Rate</p>
                        <p class="text-3xl font-bold text-blue-400"><?php echo $l1_rate; ?>%</p>
                        <p class="text-gray-400 text-sm">Level 1 rate</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-percentage text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. How Referral Program Works -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-8">
                <h2 class="text-2xl font-bold text-white mb-6 text-center">How Referral Program Works</h2>
                
                <div class="grid md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-share-alt text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">1. Share Your Code</h3>
                        <p class="text-gray-400">Share your unique referral code or link with friends and family</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-16 h-16 bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-plus text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">2. They Register</h3>
                        <p class="text-gray-400">When someone registers using your code, they become your referral</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-coins text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">3. Earn Commission</h3>
                        <p class="text-gray-400">Get <?php echo $l1_rate; ?>% commission on their deposits and ROI payments</p>
                    </div>
                </div>

                <!-- Commission Structure -->
                <div class="mt-8 grid md:grid-cols-2 gap-6">
                    <div class="bg-gradient-to-r from-emerald-600/20 to-emerald-800/20 rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <span class="level-badge text-white px-3 py-1 rounded-full text-sm font-bold mr-3">Level 1</span>
                            <h4 class="text-lg font-bold text-white">Direct Referrals</h4>
                        </div>
                        <p class="text-emerald-400 text-2xl font-bold mb-2"><?php echo $l1_rate; ?>% Commission</p>
                        <p class="text-gray-400 text-sm">Earn <?php echo $l1_rate; ?>% on deposits and ROI payments from users you directly refer</p>
                    </div>
                    
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-800/20 rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <span class="level-badge text-white px-3 py-1 rounded-full text-sm font-bold mr-3">Level 2</span>
                            <h4 class="text-lg font-bold text-white">Indirect Referrals</h4>
                        </div>
                        <p class="text-purple-400 text-2xl font-bold mb-2"><?php echo $l2_rate; ?>% Commission</p>
                        <p class="text-gray-400 text-sm">Earn <?php echo $l2_rate; ?>% on deposits and ROI payments from referrals of your referrals</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Your Referral Code Section LAST -->
        <section class="mb-8">
            <div class="referral-card rounded-xl p-8 text-center">
                <div class="mb-6">
                    <i class="fas fa-gift text-6xl text-yellow-400 mb-4"></i>
                    <h2 class="text-3xl font-bold text-white mb-2">Your Referral Code</h2>
                    <p class="text-gray-300">Share this code and earn <?php echo $l1_rate; ?>% commission on referral deposits</p>
                </div>
                
                <div class="max-w-md mx-auto mb-6">
                    <div class="flex items-center bg-gray-800/50 rounded-lg p-4">
                        <code class="flex-1 text-2xl font-bold text-emerald-400 font-mono tracking-wider"><?php echo $user['referral_code']; ?></code>
                        <button onclick="copyReferralCode()" class="ml-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                            <i class="fas fa-copy mr-1"></i>Copy
                        </button>
                    </div>
                </div>

                <!-- Referral Link -->
                <div class="max-w-lg mx-auto mb-6">
                    <p class="text-gray-400 text-sm mb-2">Or share your referral link:</p>
                    <div class="flex items-center bg-gray-800/50 rounded-lg p-3">
                        <input 
                            type="text" 
                            id="referral-link"
                            value="<?php echo SITE_URL; ?>/register.php?ref=<?php echo $user['referral_code']; ?>"
                            class="flex-1 bg-transparent text-gray-300 text-sm outline-none"
                            readonly
                        >
                        <button onclick="copyReferralLink()" class="ml-2 px-3 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-sm transition">
                            <i class="fas fa-copy mr-1"></i>Copy Link
                        </button>
                    </div>
                </div>

                <!-- Share Buttons -->
                <div class="flex flex-wrap gap-3 justify-center">
                    <button onclick="shareWhatsApp()" class="share-btn px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                        <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                    </button>
                    <button onclick="shareTelegram()" class="share-btn px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                        <i class="fab fa-telegram mr-2"></i>Telegram
                    </button>
                    <button onclick="shareTwitter()" class="share-btn px-6 py-3 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-medium transition">
                        <i class="fab fa-twitter mr-2"></i>Twitter
                    </button>
                    <button onclick="shareFacebook()" class="share-btn px-6 py-3 bg-blue-800 hover:bg-blue-900 text-white rounded-lg font-medium transition">
                        <i class="fab fa-facebook mr-2"></i>Facebook
                    </button>
                </div>
            </div>
        </section>

        <!-- Your Referrals (Referees) List -->
        <section>
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">Your Referrals (<?php echo count($referrals); ?>)</h2>
                </div>

                <?php if (empty($referrals)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-user-friends text-6xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-400 mb-2">No referrals yet</h3>
                        <p class="text-gray-500 mb-6">Start sharing your referral code to earn commissions</p>
                        <button onclick="copyReferralCode()" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-copy mr-2"></i>Copy Referral Code
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="text-left py-3 text-gray-400">User</th>
                                    <th class="text-center py-3 text-gray-400">Status</th>
                                    <th class="text-center py-3 text-gray-400">Joined</th>
                                    <th class="text-right py-3 text-gray-400">Deposited</th>
                                    <th class="text-right py-3 text-gray-400">Your Commission</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referrals as $referral): ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-800/30 transition">
                                    <td class="py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                                                <?php echo strtoupper(substr($referral['full_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <p class="font-medium text-white"><?php echo htmlspecialchars($referral['full_name']); ?></p>
                                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($referral['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 text-center">
                                        <span class="px-2 py-1 rounded text-xs font-medium <?php echo $referral['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'; ?>">
                                            <?php echo ucfirst($referral['status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 text-center text-gray-300"><?php echo date('M j, Y', strtotime($referral['created_at'])); ?></td>
                                    <td class="py-4 text-right">
                                        <span class="font-bold text-yellow-400"><?php echo formatMoney($referral['total_deposited']); ?></span>
                                    </td>
                                    <td class="py-4 text-right">
                                        <span class="font-bold text-emerald-400"><?php echo formatMoney($referral['commission_earned']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden space-y-4">
                        <?php foreach ($referrals as $referral): ?>
                        <div class="bg-gray-800/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-sm font-bold text-white">
                                        <?php echo strtoupper(substr($referral['full_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-white"><?php echo htmlspecialchars($referral['full_name']); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo timeAgo($referral['created_at']); ?></p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 rounded text-xs font-medium <?php echo $referral['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'; ?>">
                                    <?php echo ucfirst($referral['status']); ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-400">Deposited</p>
                                    <p class="font-bold text-yellow-400"><?php echo formatMoney($referral['total_deposited']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Your Commission</p>
                                    <p class="font-bold text-emerald-400"><?php echo formatMoney($referral['commission_earned']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden z-40">
        <div class="grid grid-cols-5 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="/user/packages.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Trade</span>
            </a>
            <a href="/user/referrals.php" class="flex flex-col items-center py-2 text-purple-400">
                <i class="fas fa-users text-xl mb-1"></i>
                <span class="text-xs">Network</span>
            </a>
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-briefcase text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/support.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-headset text-xl mb-1"></i>
                <span class="text-xs">Help</span>
            </a>
        </div>
    </div>

    <script>
        const referralCode = '<?php echo $user['referral_code']; ?>';
        const referralLink = '<?php echo SITE_URL; ?>/register.php?ref=<?php echo $user['referral_code']; ?>';
        const shareMessage = `Join Ultra Harvest Global with my referral code ${referralCode} and start earning guaranteed returns on your investments! 🌱💰`;

        function copyReferralCode() {
            navigator.clipboard.writeText(referralCode).then(function() {
                showCopySuccess('Referral code copied!');
            }).catch(function() {
                fallbackCopy(referralCode);
            });
        }

        function copyReferralLink() {
            navigator.clipboard.writeText(referralLink).then(function() {
                showCopySuccess('Referral link copied!');
            }).catch(function() {
                fallbackCopy(referralLink);
            });
        }

        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showCopySuccess('Copied to clipboard!');
        }

        function showCopySuccess(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'fixed top-4 right-4 bg-emerald-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 copy-animation';
            successDiv.innerHTML = `<i class="fas fa-check mr-2"></i>${message}`;
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                document.body.removeChild(successDiv);
            }, 3000);
        }

        function shareWhatsApp() {
            const url = `https://wa.me/?text=${encodeURIComponent(shareMessage + ' ' + referralLink)}`;
            window.open(url, '_blank');
        }

        function shareTelegram() {
            const url = `https://t.me/share/url?url=${encodeURIComponent(referralLink)}&text=${encodeURIComponent(shareMessage)}`;
            window.open(url, '_blank');
        }

        function shareTwitter() {
            const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareMessage)}&url=${encodeURIComponent(referralLink)}`;
            window.open(url, '_blank');
        }

        function shareFacebook() {
            const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralLink)}`;
            window.open(url, '_blank');
        }
    </script>
</body>
</html>