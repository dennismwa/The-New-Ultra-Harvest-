<?php
require_once '../config/database.php';
requireAdmin();

$error = '';
$success = '';

// Handle SEO settings update
if ($_POST && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'update_global_seo':
                    $settings = [
                        'site_title' => sanitize($_POST['site_title'] ?? ''),
                        'site_description' => sanitize($_POST['site_description'] ?? ''),
                        'site_keywords' => sanitize($_POST['site_keywords'] ?? ''),
                        'site_author' => sanitize($_POST['site_author'] ?? ''),
                        'og_image' => sanitize($_POST['og_image'] ?? ''),
                        'twitter_handle' => sanitize($_POST['twitter_handle'] ?? ''),
                        'google_analytics_id' => sanitize($_POST['google_analytics_id'] ?? ''),
                        'google_search_console' => sanitize($_POST['google_search_console'] ?? ''),
                        'facebook_pixel' => sanitize($_POST['facebook_pixel'] ?? ''),
                        'canonical_url' => sanitize($_POST['canonical_url'] ?? ''),
                        'robots_txt' => $_POST['robots_txt'] ?? '',
                        'sitemap_enabled' => isset($_POST['sitemap_enabled']) ? '1' : '0'
                    ];
                    
                    foreach ($settings as $key => $value) {
                        updateSystemSetting('seo_' . $key, $value);
                    }
                    
                    $success = 'Global SEO settings updated successfully.';
                    break;
                    
                case 'update_page_seo':
                    $page_key = sanitize($_POST['page_key'] ?? '');
                    $page_settings = [
                        'title' => sanitize($_POST['page_title'] ?? ''),
                        'description' => sanitize($_POST['page_description'] ?? ''),
                        'keywords' => sanitize($_POST['page_keywords'] ?? ''),
                        'og_title' => sanitize($_POST['page_og_title'] ?? ''),
                        'og_description' => sanitize($_POST['page_og_description'] ?? ''),
                        'canonical' => sanitize($_POST['page_canonical'] ?? ''),
                        'noindex' => isset($_POST['page_noindex']) ? '1' : '0',
                        'nofollow' => isset($_POST['page_nofollow']) ? '1' : '0'
                    ];
                    
                    foreach ($page_settings as $setting_key => $value) {
                        updateSystemSetting("seo_page_{$page_key}_{$setting_key}", $value);
                    }
                    
                    $success = 'Page SEO settings updated successfully.';
                    break;
                    
                case 'generate_sitemap':
                    generateSitemap();
                    $success = 'Sitemap generated successfully.';
                    break;
                    
                default:
                    $error = 'Invalid action specified.';
            }
        } catch (Exception $e) {
            $error = 'Failed to update SEO settings: ' . $e->getMessage();
        }
    }
}

// Get current SEO settings
$seo_settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'seo_%'");
while ($row = $stmt->fetch()) {
    $key = str_replace('seo_', '', $row['setting_key']);
    $seo_settings[$key] = $row['setting_value'];
}

// Define pages for SEO configuration
$seo_pages = [
    'homepage' => ['name' => 'Homepage', 'url' => '/', 'priority' => 'High'],
    'login' => ['name' => 'Login Page', 'url' => '/login.php', 'priority' => 'Medium'],
    'register' => ['name' => 'Registration', 'url' => '/register.php', 'priority' => 'High'],
    'packages' => ['name' => 'Packages', 'url' => '/packages.php', 'priority' => 'High'],
    'about' => ['name' => 'About Us', 'url' => '/about.php', 'priority' => 'Medium'],
    'contact' => ['name' => 'Contact', 'url' => '/contact.php', 'priority' => 'Medium'],
    'terms' => ['name' => 'Terms & Conditions', 'url' => '/terms.php', 'priority' => 'Low'],
    'privacy' => ['name' => 'Privacy Policy', 'url' => '/privacy.php', 'priority' => 'Low']
];

// SEO Analysis Functions
function analyzeSEO() {
    global $db, $seo_settings;
    
    $issues = [];
    $suggestions = [];
    $score = 100;
    
    // Check essential settings
    if (empty($seo_settings['site_title'])) {
        $issues[] = 'Missing site title';
        $score -= 15;
    } elseif (strlen($seo_settings['site_title']) > 60) {
        $issues[] = 'Site title too long (over 60 characters)';
        $score -= 5;
    }
    
    if (empty($seo_settings['site_description'])) {
        $issues[] = 'Missing meta description';
        $score -= 15;
    } elseif (strlen($seo_settings['site_description']) > 160) {
        $issues[] = 'Meta description too long (over 160 characters)';
        $score -= 5;
    }
    
    if (empty($seo_settings['site_keywords'])) {
        $suggestions[] = 'Add relevant keywords to improve search visibility';
        $score -= 5;
    }
    
    if (empty($seo_settings['google_analytics_id'])) {
        $suggestions[] = 'Set up Google Analytics to track website performance';
        $score -= 10;
    }
    
    if (empty($seo_settings['google_search_console'])) {
        $suggestions[] = 'Verify your site with Google Search Console';
        $score -= 5;
    }
    
    if (empty($seo_settings['og_image'])) {
        $suggestions[] = 'Add Open Graph image for better social media sharing';
        $score -= 5;
    }
    
    if (empty($seo_settings['canonical_url'])) {
        $suggestions[] = 'Set canonical URL to prevent duplicate content issues';
        $score -= 5;
    }
    
    // Check robots.txt
    if (empty($seo_settings['robots_txt'])) {
        $suggestions[] = 'Configure robots.txt to guide search engine crawling';
        $score -= 5;
    }
    
    // Check sitemap
    if (($seo_settings['sitemap_enabled'] ?? '0') === '0') {
        $suggestions[] = 'Enable sitemap generation for better indexing';
        $score -= 5;
    }
    
    return [
        'score' => max(0, $score),
        'issues' => $issues,
        'suggestions' => $suggestions
    ];
}

function generateSitemap() {
    global $seo_pages;
    
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    $base_url = SITE_URL;
    $current_date = date('Y-m-d');
    
    foreach ($seo_pages as $page_key => $page_info) {
        $priority = match($page_info['priority']) {
            'High' => '1.0',
            'Medium' => '0.7',
            'Low' => '0.5',
            default => '0.5'
        };
        
        $changefreq = match($page_info['priority']) {
            'High' => 'daily',
            'Medium' => 'weekly',
            'Low' => 'monthly',
            default => 'monthly'
        };
        
        $sitemap .= "  <url>\n";
        $sitemap .= "    <loc>{$base_url}{$page_info['url']}</loc>\n";
        $sitemap .= "    <lastmod>{$current_date}</lastmod>\n";
        $sitemap .= "    <changefreq>{$changefreq}</changefreq>\n";
        $sitemap .= "    <priority>{$priority}</priority>\n";
        $sitemap .= "  </url>\n";
    }
    
    $sitemap .= '</urlset>';
    
    // Save sitemap
    file_put_contents('../sitemap.xml', $sitemap);
}

$seo_analysis = analyzeSEO();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Settings - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .seo-section {
            display: none;
        }
        
        .seo-section.active {
            display: block;
        }
        
        .nav-item.active {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
        }
        
        .seo-score-circle {
            position: relative;
            width: 120px;
            height: 120px;
        }
        
        .character-count {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .character-count.warning {
            color: #f59e0b;
        }
        
        .character-count.error {
            color: #ef4444;
        }
        
        .character-count.good {
            color: #10b981;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                        <div>
                            <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                            <p class="text-xs text-gray-400">Admin Panel</p>
                        </div>
                    </div>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/admin/" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                        <a href="/admin/users.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                        <a href="/admin/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <a href="/admin/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                        <a href="/admin/system-health.php" class="text-gray-300 hover:text-emerald-400 transition">System Health</a>
                        <a href="/admin/settings.php" class="text-gray-300 hover:text-emerald-400 transition">Settings</a>
                        <a href="/admin/seo-settings.php" class="text-emerald-400 font-medium">SEO</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <button onclick="generateSitemap()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-sitemap mr-2"></i>Generate Sitemap
                    </button>
                    <a href="/logout.php" class="text-red-400 hover:text-red-300">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">SEO Settings & Optimization</h1>
            <p class="text-gray-400">Optimize your website for search engines and improve visibility</p>
        </div>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300"><?php echo $success; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- SEO Score Overview -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">SEO Health Overview</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <!-- SEO Score -->
                    <div class="text-center">
                        <div class="seo-score-circle mx-auto mb-4">
                            <canvas id="seoScoreChart" width="120" height="120"></canvas>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Overall SEO Score</h3>
                        <p class="text-gray-400">Based on key optimization factors</p>
                    </div>
                    
                    <!-- Issues -->
                    <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                            <h3 class="font-semibold text-red-400">Issues Found</h3>
                        </div>
                        <?php if (!empty($seo_analysis['issues'])): ?>
                            <ul class="space-y-2 text-sm text-gray-300">
                                <?php foreach ($seo_analysis['issues'] as $issue): ?>
                                    <li class="flex items-start">
                                        <i class="fas fa-times text-red-400 mr-2 mt-1 text-xs"></i>
                                        <?php echo htmlspecialchars($issue); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-sm text-gray-400">No critical issues found!</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Suggestions -->
                    <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-lightbulb text-yellow-400 mr-2"></i>
                            <h3 class="font-semibold text-yellow-400">Suggestions</h3>
                        </div>
                        <?php if (!empty($seo_analysis['suggestions'])): ?>
                            <ul class="space-y-2 text-sm text-gray-300">
                                <?php foreach (array_slice($seo_analysis['suggestions'], 0, 4) as $suggestion): ?>
                                    <li class="flex items-start">
                                        <i class="fas fa-arrow-right text-yellow-400 mr-2 mt-1 text-xs"></i>
                                        <?php echo htmlspecialchars($suggestion); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-sm text-gray-400">All good! Keep monitoring.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid lg:grid-cols-4 gap-8">
            
            <!-- SEO Navigation -->
            <div class="lg:col-span-1">
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">SEO Settings</h3>
                    <nav class="space-y-2">
                        <button onclick="showSection('global')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="global">
                            <i class="fas fa-globe mr-3"></i>Global Settings
                        </button>
                        <button onclick="showSection('pages')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="pages">
                            <i class="fas fa-file-alt mr-3"></i>Page Settings
                        </button>
                        <button onclick="showSection('analytics')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="analytics">
                            <i class="fas fa-chart-bar mr-3"></i>Analytics
                        </button>
                        <button onclick="showSection('technical')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="technical">
                            <i class="fas fa-code mr-3"></i>Technical SEO
                        </button>
                        <button onclick="showSection('social')" class="nav-item w-full text-left px-4 py-3 rounded-lg transition" data-section="social">
                            <i class="fas fa-share-alt mr-3"></i>Social Media
                        </button>
                    </nav>
                </div>
            </div>

            <!-- SEO Content -->
            <div class="lg:col-span-3">
                
                <!-- Global SEO Settings -->
                <div id="global-section" class="seo-section active">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Global SEO Settings</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_global_seo">
                            
                            <div class="space-y-6">
                                <!-- Site Title -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Site Title *</label>
                                    <input 
                                        type="text" 
                                        name="site_title" 
                                        value="<?php echo htmlspecialchars($seo_settings['site_title'] ?? ''); ?>"
                                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                        placeholder="Ultra Harvest Global - Copy Forex Trades. Harvest Profits Fast."
                                        maxlength="60"
                                        oninput="updateCharCount(this, 60, 'title-count')"
                                        required
                                    >
                                    <div id="title-count" class="character-count"></div>
                                </div>

                                <!-- Meta Description -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Meta Description *</label>
                                    <textarea 
                                        name="site_description" 
                                        rows="3"
                                        maxlength="160"
                                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                        placeholder="Choose a package, press Copy, and let your money grow with Ultra Harvest Global. Safe, secure forex trading with guaranteed returns."
                                        oninput="updateCharCount(this, 160, 'desc-count')"
                                        required
                                    ><?php echo htmlspecialchars($seo_settings['site_description'] ?? ''); ?></textarea>
                                    <div id="desc-count" class="character-count"></div>
                                </div>

                                <!-- Keywords -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Keywords</label>
                                    <input 
                                        type="text" 
                                        name="site_keywords" 
                                        value="<?php echo htmlspecialchars($seo_settings['site_keywords'] ?? ''); ?>"
                                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                        placeholder="forex trading, copy trading, investment, passive income, Kenya"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Separate keywords with commas</p>
                                </div>

                                <!-- Author -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Site Author</label>
                                    <input 
                                        type="text" 
                                        name="site_author" 
                                        value="<?php echo htmlspecialchars($seo_settings['site_author'] ?? ''); ?>"
                                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                        placeholder="Ultra Harvest Global"
                                    >
                                </div>

                                <!-- Canonical URL -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Canonical URL</label>
                                    <input 
                                        type="url" 
                                        name="canonical_url" 
                                        value="<?php echo htmlspecialchars($seo_settings['canonical_url'] ?? SITE_URL); ?>"
                                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                                        placeholder="https://ultraharvest.zurihub.co.ke"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Preferred domain for search engines</p>
                                </div>

                                <!-- Sitemap -->
                                <div class="flex items-center space-x-3">
                                    <input 
                                        type="checkbox" 
                                        name="sitemap_enabled"
                                        id="sitemap_enabled"
                                        <?php echo ($seo_settings['sitemap_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>
                                        class="w-5 h-5 text-emerald-600 bg-gray-800 border-gray-600 rounded focus:ring-emerald-500 focus:ring-2"
                                    >
                                    <label for="sitemap_enabled" class="text-white font-medium">Enable automatic sitemap generation</label>
                                </div>
                            </div>
                            
                            <button type="submit" class="mt-6 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                                <i class="fas fa-save mr-2"></i>Save Global Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Page Settings -->
                <div id="pages-section" class="seo-section">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Page-Specific SEO</h3>
                        
                        <div class="grid gap-6">
                            <?php foreach ($seo_pages as $page_key => $page_info): ?>
                            <div class="bg-gray-800/50 rounded-lg p-4 border border-gray-700">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h4 class="font-semibold text-white"><?php echo $page_info['name']; ?></h4>
                                        <p class="text-sm text-gray-400"><?php echo $page_info['url']; ?></p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php 
                                        echo match($page_info['priority']) {
                                            'High' => 'bg-red-500/20 text-red-400',
                                            'Medium' => 'bg-yellow-500/20 text-yellow-400',
                                            'Low' => 'bg-green-500/20 text-green-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                            <?php echo $page_info['priority']; ?> Priority
                                        </span>
                                        <button onclick="togglePageSEO('<?php echo $page_key; ?>')" class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="page-seo-<?php echo $page_key; ?>" class="page-seo-form" style="display: none;">
                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="update_page_seo">
                                        <input type="hidden" name="page_key" value="<?php echo $page_key; ?>">
                                        
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm text-gray-300 mb-1">Page Title</label>
                                                <input 
                                                    type="text" 
                                                    name="page_title" 
                                                    value="<?php echo htmlspecialchars($seo_settings["page_{$page_key}_title"] ?? ''); ?>"
                                                    class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white text-sm focus:border-emerald-500 focus:outline-none"
                                                    maxlength="60"
                                                >
                                            </div>
                                            <div>
                                                <label class="block text-sm text-gray-300 mb-1">Canonical URL</label>
                                                <input 
                                                    type="url" 
                                                    name="page_canonical" 
                                                    value="<?php echo htmlspecialchars($seo_settings["page_{$page_key}_canonical"] ?? ''); ?>"
                                                    class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white text-sm focus:border-emerald-500 focus:outline-none"
                                                >
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm text-gray-300 mb-1">Meta Description</label>
                                            <textarea 
                                                name="page_description" 
                                                rows="2"
                                                maxlength="160"
                                                class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white text-sm focus:border-emerald-500 focus:outline-none"
                                            ><?php echo htmlspecialchars($seo_settings["page_{$page_key}_description"] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="flex items-center space-x-4">
                                            <label class="flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    name="page_noindex"
                                                    <?php echo ($seo_settings["page_{$page_key}_noindex"] ?? '0') === '1' ? 'checked' : ''; ?>
                                                    class="w-4 h-4 text-emerald-600 bg-gray-800 border-gray-600 rounded focus:ring-emerald-500 focus:ring-2 mr-2"
                                                >
                                                <span class="text-sm text-gray-300">No Index</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    name="page_nofollow"
                                                    <?php echo ($seo_settings["page_{$page_key}_nofollow"] ?? '0') === '1' ? 'checked' : ''; ?>
                                                    class="w-4 h-4 text-emerald-600 bg-gray-800 border-gray-600 rounded focus:ring-emerald-500 focus:ring-2 mr-2"
                                                >
                                                <span class="text-sm text-gray-300">No Follow</span>
                                            </label>
                                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm transition">
                                                Save Page SEO
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Analytics Settings -->
                <div id="analytics-section" class="seo-section">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Analytics & Tracking</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_global_seo">
                            
                            <div class="space-y-6">
                                <!-- Google Analytics -->
                                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fab fa-google text-blue-400 text-2xl mr-3"></i>
                                        <h4 class="text-lg font-semibold text-white">Google Analytics</h4>
                                    </div>
                                    
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-300 mb-2">Analytics Tracking ID</label>
                                            <input 
                                                type="text" 
                                                name="google_analytics_id" 
                                                value="<?php echo htmlspecialchars($seo_settings['google_analytics_id'] ?? ''); ?>"
                                                class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none"
                                                placeholder="G-XXXXXXXXXX or UA-XXXXXXXXX-X"
                                            >
                                            <p class="text-xs text-gray-500 mt-1">Get this from your Google Analytics dashboard</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-300 mb-2">Search Console Code</label>
                                            <input 
                                                type="text" 
                                                name="google_search_console" 
                                                value="<?php echo htmlspecialchars($seo_settings['google_search_console'] ?? ''); ?>"
                                                class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none"
                                                placeholder="google-site-verification=..."
                                            >
                                            <p class="text-xs text-gray-500 mt-1">HTML tag verification code from Search Console</p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 p-4 bg-gray-800/50 rounded-lg">
                                        <h5 class="font-medium text-white mb-2">Setup Instructions:</h5>
                                        <ul class="text-sm text-gray-300 space-y-1">
                                            <li>1. Create a Google Analytics account at analytics.google.com</li>
                                            <li>2. Add your website and get the tracking ID</li>
                                            <li>3. Verify your site in Google Search Console</li>
                                            <li>4. Enter the codes above to enable tracking</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Facebook Pixel -->
                                <div class="bg-blue-600/10 border border-blue-600/30 rounded-lg p-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fab fa-facebook text-blue-500 text-2xl mr-3"></i>
                                        <h4 class="text-lg font-semibold text-white">Facebook Pixel</h4>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Facebook Pixel ID</label>
                                        <input 
                                            type="text" 
                                            name="facebook_pixel" 
                                            value="<?php echo htmlspecialchars($seo_settings['facebook_pixel'] ?? ''); ?>"
                                            class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none"
                                            placeholder="123456789012345"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">For Facebook ad tracking and conversions</p>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="mt-6 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                                <i class="fas fa-save mr-2"></i>Save Analytics Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Technical SEO -->
                <div id="technical-section" class="seo-section">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Technical SEO</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_global_seo">
                            
                            <div class="space-y-6">
                                <!-- Robots.txt -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Robots.txt Content</label>
                                    <textarea 
                                        name="robots_txt" 
                                        rows="10"
                                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none font-mono text-sm"
                                        placeholder="User-agent: *
Allow: /
Disallow: /admin/
Disallow: /user/
Disallow: /api/

Sitemap: <?php echo SITE_URL; ?>sitemap.xml"
                                    ><?php echo htmlspecialchars($seo_settings['robots_txt'] ?? ''); ?></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Controls how search engines crawl your site</p>
                                </div>

                                <!-- Current Technical Status -->
                                <div class="bg-gray-800/50 rounded-lg p-4">
                                    <h4 class="font-semibold text-white mb-3">Technical SEO Status</h4>
                                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-300">SSL Certificate:</span>
                                            <span class="text-emerald-400">
                                                <i class="fas fa-check mr-1"></i>Enabled
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-300">Sitemap:</span>
                                            <span class="<?php echo file_exists('../sitemap.xml') ? 'text-emerald-400' : 'text-red-400'; ?>">
                                                <i class="fas <?php echo file_exists('../sitemap.xml') ? 'fa-check' : 'fa-times'; ?> mr-1"></i>
                                                <?php echo file_exists('../sitemap.xml') ? 'Generated' : 'Not Found'; ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-300">Robots.txt:</span>
                                            <span class="<?php echo !empty($seo_settings['robots_txt']) ? 'text-emerald-400' : 'text-yellow-400'; ?>">
                                                <i class="fas <?php echo !empty($seo_settings['robots_txt']) ? 'fa-check' : 'fa-exclamation-triangle'; ?> mr-1"></i>
                                                <?php echo !empty($seo_settings['robots_txt']) ? 'Configured' : 'Needs Setup'; ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-300">Mobile Friendly:</span>
                                            <span class="text-emerald-400">
                                                <i class="fas fa-check mr-1"></i>Responsive
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="mt-6 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                                <i class="fas fa-save mr-2"></i>Save Technical Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Social Media SEO -->
                <div id="social-section" class="seo-section">
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-xl font-bold text-white mb-6">Social Media SEO</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_global_seo">
                            
                            <div class="space-y-6">
                                <!-- Open Graph -->
                                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fab fa-facebook text-blue-500 text-2xl mr-3"></i>
                                        <h4 class="text-lg font-semibold text-white">Open Graph (Facebook)</h4>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-300 mb-2">OG Image URL</label>
                                            <input 
                                                type="url" 
                                                name="og_image" 
                                                value="<?php echo htmlspecialchars($seo_settings['og_image'] ?? ''); ?>"
                                                class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none"
                                                placeholder="https://ultraharvest.zurihub.co.ke/images/og-image.jpg"
                                            >
                                            <p class="text-xs text-gray-500 mt-1">Recommended: 1200x630px image for social sharing</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Twitter -->
                                <div class="bg-sky-500/10 border border-sky-500/30 rounded-lg p-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fab fa-twitter text-sky-400 text-2xl mr-3"></i>
                                        <h4 class="text-lg font-semibold text-white">Twitter Cards</h4>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Twitter Handle</label>
                                        <input 
                                            type="text" 
                                            name="twitter_handle" 
                                            value="<?php echo htmlspecialchars($seo_settings['twitter_handle'] ?? ''); ?>"
                                            class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-sky-500 focus:outline-none"
                                            placeholder="@ultraharvest"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">Your Twitter username (with @)</p>
                                    </div>
                                </div>

                                <!-- Social Media Preview -->
                                <div class="bg-gray-800/50 rounded-lg p-6">
                                    <h4 class="font-semibold text-white mb-4">Social Media Preview</h4>
                                    <div class="border border-gray-600 rounded-lg p-4 bg-gray-800">
                                        <?php if (!empty($seo_settings['og_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($seo_settings['og_image']); ?>" alt="OG Image" class="w-full h-32 object-cover rounded mb-3">
                                        <?php else: ?>
                                        <div class="w-full h-32 bg-gray-700 rounded mb-3 flex items-center justify-center">
                                            <i class="fas fa-image text-gray-500 text-2xl"></i>
                                        </div>
                                        <?php endif; ?>
                                        <h5 class="font-semibold text-white"><?php echo htmlspecialchars($seo_settings['site_title'] ?? 'Ultra Harvest Global'); ?></h5>
                                        <p class="text-gray-400 text-sm mt-1"><?php echo htmlspecialchars($seo_settings['site_description'] ?? 'Copy Forex Trades. Harvest Profits Fast.'); ?></p>
                                        <p class="text-gray-500 text-xs mt-2"><?php echo htmlspecialchars($seo_settings['canonical_url'] ?? SITE_URL); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="mt-6 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                                <i class="fas fa-save mr-2"></i>Save Social Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Global variables
        let currentSection = 'global';
        
        // Show/hide SEO sections
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.seo-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Highlight active nav item
            document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');
            
            currentSection = sectionName;
        }

        // Toggle page SEO forms
        function togglePageSEO(pageKey) {
            const form = document.getElementById(`page-seo-${pageKey}`);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        // Update character count
        function updateCharCount(input, maxLength, countId) {
            const current = input.value.length;
            const counter = document.getElementById(countId);
            counter.textContent = `${current}/${maxLength} characters`;
            
            if (current > maxLength * 0.9) {
                counter.className = 'character-count error';
            } else if (current > maxLength * 0.7) {
                counter.className = 'character-count warning';
            } else {
                counter.className = 'character-count good';
            }
        }

        // Generate sitemap
        function generateSitemap() {
            if (confirm('Generate new sitemap? This will overwrite the existing sitemap.')) {
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                formData.append('action', 'generate_sitemap');

                fetch('seo-settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    alert('Sitemap generated successfully!');
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to generate sitemap');
                });
            }
        }

        // SEO Score Chart
        function createSEOScoreChart() {
            const ctx = document.getElementById('seoScoreChart').getContext('2d');
            const score = <?php echo $seo_analysis['score']; ?>;
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [score, 100 - score],
                        backgroundColor: [
                            score >= 80 ? '#10b981' : score >= 60 ? '#f59e0b' : '#ef4444',
                            'rgba(75, 85, 99, 0.3)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    beforeDraw: function(chart) {
                        const ctx = chart.ctx;
                        const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                        const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                        
                        ctx.save();
                        ctx.font = 'bold 24px Poppins';
                        ctx.fillStyle = '#ffffff';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(score, centerX, centerY - 5);
                        
                        ctx.font = '12px Poppins';
                        ctx.fillStyle = '#9ca3af';
                        ctx.fillText('/100', centerX, centerY + 15);
                        ctx.restore();
                    }
                }]
            });
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Show first section by default
            showSection('global');
            
            // Create SEO score chart
            createSEOScoreChart();
            
            // Initialize character counters
            const titleInput = document.querySelector('input[name="site_title"]');
            const descInput = document.querySelector('textarea[name="site_description"]');
            
            if (titleInput) {
                updateCharCount(titleInput, 60, 'title-count');
            }
            
            if (descInput) {
                updateCharCount(descInput, 160, 'desc-count');
            }
            
            // URL hash support
            if (window.location.hash) {
                const section = window.location.hash.substring(1);
                if (['global', 'pages', 'analytics', 'technical', 'social'].includes(section)) {
                    showSection(section);
                }
            }
        });

        // Update URL hash when section changes
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                const section = this.getAttribute('data-section');
                window.location.hash = section;
            });
        });

        // Form auto-save functionality
        function autoSaveSettings() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        const section = form.closest('.seo-section').id.replace('-section', '');
                        const key = `seo_draft_${section}_${this.name}`;
                        localStorage.setItem(key, this.type === 'checkbox' ? this.checked : this.value);
                    });
                });
            });
        }

        // Real-time SEO analysis
        function updateSEOAnalysis() {
            const title = document.querySelector('input[name="site_title"]')?.value || '';
            const description = document.querySelector('textarea[name="site_description"]')?.value || '';
            
            let score = 100;
            const issues = [];
            
            if (!title) {
                issues.push('Missing site title');
                score -= 15;
            } else if (title.length > 60) {
                issues.push('Site title too long');
                score -= 5;
            }
            
            if (!description) {
                issues.push('Missing meta description');
                score -= 15;
            } else if (description.length > 160) {
                issues.push('Meta description too long');
                score -= 5;
            }
            
            // Update score display if needed
            // This could trigger a chart update in real-time
        }

        // Initialize auto-save
        autoSaveSettings();
    </script>
</body>
</html>