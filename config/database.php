<?php
/**
 * Database Configuration and Helper Functions
 * Ultra Harvest Global
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'theultra_DB');  // Your database name
define('DB_USER', 'theultra_theultra');   // Your database username
define('DB_PASS', 'PU7qh=43R0Bk7Jfb');   // Your database password

// Site Configuration
define('SITE_URL', 'https://ultraharvest.zurihub.co.ke');
define('SITE_NAME', 'Ultra Harvest Global');

// Security
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour

// Initialize database connection
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
// Set default timezone to Kenya (East Africa Time: UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Set MySQL timezone to match
try {
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    error_log("Failed to set MySQL timezone: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authentication Functions
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * CSRF Protection
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token has expired
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Input Sanitization
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Money Formatting
 */
function formatMoney($amount) {
    return 'KSh ' . number_format($amount, 2);
}

/**
 * Time Functions
 */
function timeAgo($datetime) {
    try {
        // Create DateTime objects
        if (is_string($datetime)) {
            $time = new DateTime($datetime);
        } else {
            $time = $datetime;
        }
        
        $now = new DateTime('now');
        
        // Calculate difference
        $diff = $now->diff($time);
        
        // Convert to total seconds for accurate comparison
        $totalSeconds = 0;
        $totalSeconds += $diff->y * 365 * 24 * 60 * 60;
        $totalSeconds += $diff->m * 30 * 24 * 60 * 60;
        $totalSeconds += $diff->d * 24 * 60 * 60;
        $totalSeconds += $diff->h * 60 * 60;
        $totalSeconds += $diff->i * 60;
        $totalSeconds += $diff->s;
        
        // Format output based on time difference
        if ($totalSeconds < 60) {
            return 'just now';
        } elseif ($totalSeconds < 3600) {
            return $diff->i . 'm ago';
        } elseif ($totalSeconds < 86400) {
            return $diff->h . 'h ago';
        } elseif ($totalSeconds < 604800) { // Less than 7 days
            return $diff->d . 'd ago';
        } elseif ($totalSeconds < 2592000) { // Less than 30 days
            $weeks = floor($diff->d / 7);
            return $weeks . 'w ago';
        } elseif ($totalSeconds < 31536000) { // Less than 365 days
            return $diff->m . 'mo ago';
        } else {
            return $diff->y . 'y ago';
        }
        
    } catch (Exception $e) {
        error_log("timeAgo error: " . $e->getMessage());
        return 'recently';
    }
}

/**
 * Notification System
 */
function sendNotification($user_id, $title, $message, $type = 'info') {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return false;
    }
}

function sendGlobalNotification($title, $message, $type = 'info') {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (title, message, type, is_global) 
            VALUES (?, ?, ?, 1)
        ");
        return $stmt->execute([$title, $message, $type]);
    } catch (Exception $e) {
        error_log("Failed to send global notification: " . $e->getMessage());
        return false;
    }
}

/**
 * System Settings
 */
function getSystemSetting($key, $default = null) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        error_log("Failed to get system setting: " . $e->getMessage());
        return $default;
    }
}

function updateSystemSetting($key, $value) {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        error_log("Failed to update system setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Referral Code Generation
 */
function generateReferralCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

/**
 * System Health Logging
 */
function logSystemHealth() {
    global $db;
    try {
        // Get statistics
        $stmt = $db->query("SELECT * FROM admin_stats_overview");
        $stats = $stmt->fetch();
        
        if ($stats) {
            $platform_liquidity = $stats['total_deposits'] - $stats['total_withdrawals'] - $stats['total_roi_paid'];
            $total_liabilities = $stats['total_user_balances'] + $stats['pending_roi_obligations'];
            $coverage_ratio = $total_liabilities > 0 ? $platform_liquidity / $total_liabilities : 1;
            
            $stmt = $db->prepare("
                INSERT INTO system_health_log (
                    total_deposits, total_withdrawals, total_roi_paid, 
                    pending_roi_obligations, user_wallet_balances, 
                    platform_liquidity, coverage_ratio, active_users, 
                    active_packages_count
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $stats['total_deposits'],
                $stats['total_withdrawals'],
                $stats['total_roi_paid'],
                $stats['pending_roi_obligations'],
                $stats['total_user_balances'],
                $platform_liquidity,
                $coverage_ratio,
                $stats['active_users'],
                $stats['active_packages']
            ]);
        }
    } catch (Exception $e) {
        error_log("Failed to log system health: " . $e->getMessage());
    }
}

/**
 * Database Schema Updates
 * Run this once to add missing fields
 */
function updateDatabaseSchema() {
    global $db;
    try {
        // Add mpesa_request_id to transactions table if it doesn't exist
        $db->exec("
            ALTER TABLE transactions 
            ADD COLUMN IF NOT EXISTS mpesa_request_id VARCHAR(100) DEFAULT NULL,
            ADD INDEX IF NOT EXISTS idx_mpesa_request_id (mpesa_request_id)
        ");
        
        echo "Database schema updated successfully.\n";
    } catch (Exception $e) {
        error_log("Database schema update failed: " . $e->getMessage());
        echo "Database schema update failed: " . $e->getMessage() . "\n";
    }
}

// Uncomment the line below to run the schema update once
// updateDatabaseSchema();

/**
 * Error Reporting for Development
 * Comment out in production
 */
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

/**
 * Get SEO meta tags for a specific page
 */
function getSEOMetaTags($page_key = 'homepage') {
    global $db;
    
    // Get global SEO settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'seo_%'");
    $seo_settings = [];
    while ($row = $stmt->fetch()) {
        $key = str_replace('seo_', '', $row['setting_key']);
        $seo_settings[$key] = $row['setting_value'];
    }
    
    // Page-specific overrides
    $page_title = $seo_settings["page_{$page_key}_title"] ?? $seo_settings['site_title'] ?? '';
    $page_description = $seo_settings["page_{$page_key}_description"] ?? $seo_settings['site_description'] ?? '';
    $page_keywords = $seo_settings["page_{$page_key}_keywords"] ?? $seo_settings['site_keywords'] ?? '';
    $page_canonical = $seo_settings["page_{$page_key}_canonical"] ?? ($seo_settings['canonical_url'] ?? SITE_URL);
    
    $meta_tags = [
        'title' => $page_title,
        'description' => $page_description,
        'keywords' => $page_keywords,
        'author' => $seo_settings['site_author'] ?? '',
        'canonical' => $page_canonical,
        'og_title' => $seo_settings["page_{$page_key}_og_title"] ?? $page_title,
        'og_description' => $seo_settings["page_{$page_key}_og_description"] ?? $page_description,
        'og_image' => $seo_settings['og_image'] ?? '',
        'twitter_handle' => $seo_settings['twitter_handle'] ?? '',
        'noindex' => ($seo_settings["page_{$page_key}_noindex"] ?? '0') === '1',
        'nofollow' => ($seo_settings["page_{$page_key}_nofollow"] ?? '0') === '1'
    ];
    
    return $meta_tags;
}

/**
 * Generate HTML meta tags
 */
function generateMetaTagsHTML($page_key = 'homepage') {
    $meta = getSEOMetaTags($page_key);
    $html = '';
    
    // Basic meta tags
    if ($meta['title']) {
        $html .= '<title>' . htmlspecialchars($meta['title']) . '</title>' . "\n";
    }
    
    if ($meta['description']) {
        $html .= '<meta name="description" content="' . htmlspecialchars($meta['description']) . '">' . "\n";
    }
    
    if ($meta['keywords']) {
        $html .= '<meta name="keywords" content="' . htmlspecialchars($meta['keywords']) . '">' . "\n";
    }
    
    if ($meta['author']) {
        $html .= '<meta name="author" content="' . htmlspecialchars($meta['author']) . '">' . "\n";
    }
    
    // Robots meta
    $robots = [];
    if ($meta['noindex']) $robots[] = 'noindex';
    if ($meta['nofollow']) $robots[] = 'nofollow';
    if (!empty($robots)) {
        $html .= '<meta name="robots" content="' . implode(', ', $robots) . '">' . "\n";
    }
    
    // Canonical URL
    if ($meta['canonical']) {
        $html .= '<link rel="canonical" href="' . htmlspecialchars($meta['canonical']) . '">' . "\n";
    }
    
    // Open Graph tags
    if ($meta['og_title']) {
        $html .= '<meta property="og:title" content="' . htmlspecialchars($meta['og_title']) . '">' . "\n";
    }
    
    if ($meta['og_description']) {
        $html .= '<meta property="og:description" content="' . htmlspecialchars($meta['og_description']) . '">' . "\n";
    }
    
    if ($meta['og_image']) {
        $html .= '<meta property="og:image" content="' . htmlspecialchars($meta['og_image']) . '">' . "\n";
    }
    
    $html .= '<meta property="og:type" content="website">' . "\n";
    $html .= '<meta property="og:url" content="' . htmlspecialchars($meta['canonical']) . '">' . "\n";
    
    // Twitter Card tags
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if ($meta['twitter_handle']) {
        $html .= '<meta name="twitter:site" content="' . htmlspecialchars($meta['twitter_handle']) . '">' . "\n";
    }
    
    if ($meta['og_title']) {
        $html .= '<meta name="twitter:title" content="' . htmlspecialchars($meta['og_title']) . '">' . "\n";
    }
    
    if ($meta['og_description']) {
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($meta['og_description']) . '">' . "\n";
    }
    
    if ($meta['og_image']) {
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($meta['og_image']) . '">' . "\n";
    }
    
    return $html;
}

/**
 * Generate Google Analytics tracking code
 */
function getGoogleAnalyticsCode() {
    $ga_id = getSystemSetting('seo_google_analytics_id', '');
    
    if (empty($ga_id)) {
        return '';
    }
    
    if (strpos($ga_id, 'G-') === 0) {
        // Google Analytics 4
        return "
<!-- Google Analytics 4 -->
<script async src=\"https://www.googletagmanager.com/gtag/js?id={$ga_id}\"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$ga_id}');
</script>
";
    } else {
        // Universal Analytics (legacy)
        return "
<!-- Universal Analytics -->
<script async src=\"https://www.google-analytics.com/analytics.js\"></script>
<script>
  window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;
  ga('create', '{$ga_id}', 'auto');
  ga('send', 'pageview');
</script>
";
    }
}

/**
 * Generate Facebook Pixel code
 */
function getFacebookPixelCode() {
    $pixel_id = getSystemSetting('seo_facebook_pixel', '');
    
    if (empty($pixel_id)) {
        return '';
    }
    
    return "
<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixel_id}');
fbq('track', 'PageView');
</script>
<noscript><img height=\"1\" width=\"1\" style=\"display:none\"
src=\"https://www.facebook.com/tr?id={$pixel_id}&ev=PageView&noscript=1\"
/></noscript>
<!-- End Facebook Pixel Code -->
";
}

/**
 * Generate Google Search Console verification code
 */
function getGoogleSearchConsoleCode() {
    $verification_code = getSystemSetting('seo_google_search_console', '');
    
    if (empty($verification_code)) {
        return '';
    }
    
    // If it's a full meta tag, return as is
    if (strpos($verification_code, '<meta') === 0) {
        return $verification_code . "\n";
    }
    
    // If it's just the verification code, wrap in meta tag
    if (strpos($verification_code, 'google-site-verification=') === 0) {
        return '<meta name="' . htmlspecialchars($verification_code) . '" />' . "\n";
    }
    
    // If it's just the code value, create the full meta tag
    return '<meta name="google-site-verification" content="' . htmlspecialchars($verification_code) . '" />' . "\n";
}

/**
 * Generate robots.txt content
 */
function generateRobotsTxt() {
    $robots_content = getSystemSetting('seo_robots_txt', '');
    
    if (empty($robots_content)) {
        // Default robots.txt
        $robots_content = "User-agent: *\n";
        $robots_content .= "Allow: /\n";
        $robots_content .= "Disallow: /admin/\n";
        $robots_content .= "Disallow: /user/\n";
        $robots_content .= "Disallow: /api/\n";
        $robots_content .= "Disallow: /config/\n";
        $robots_content .= "Disallow: /logs/\n\n";
        
        // Add sitemap if enabled
        if (getSystemSetting('seo_sitemap_enabled', '1') === '1') {
            $robots_content .= "Sitemap: " . SITE_URL . "sitemap.xml\n";
        }
    }
    
    return $robots_content;
}

/**
 * Generate XML sitemap
 */
function generateXMLSitemap() {
    if (getSystemSetting('seo_sitemap_enabled', '1') !== '1') {
        return false;
    }
    
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    $base_url = rtrim(SITE_URL, '/');
    $current_date = date('Y-m-d');
    
    // Define pages for sitemap
    $pages = [
        ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => '/login.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
        ['url' => '/register.php', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['url' => '/packages.php', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => '/about.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['url' => '/contact.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['url' => '/terms.php', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['url' => '/privacy.php', 'priority' => '0.3', 'changefreq' => 'yearly'],
    ];
    
    foreach ($pages as $page) {
        $sitemap .= "  <url>\n";
        $sitemap .= "    <loc>{$base_url}{$page['url']}</loc>\n";
        $sitemap .= "    <lastmod>{$current_date}</lastmod>\n";
        $sitemap .= "    <changefreq>{$page['changefreq']}</changefreq>\n";
        $sitemap .= "    <priority>{$page['priority']}</priority>\n";
        $sitemap .= "  </url>\n";
    }
    
    $sitemap .= '</urlset>';
    
    // Save sitemap to file
    $sitemap_path = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
    return file_put_contents($sitemap_path, $sitemap) !== false;
}

/**
 * Get all tracking codes
 */
function getAllTrackingCodes() {
    $codes = '';
    
    // Google Analytics
    $codes .= getGoogleAnalyticsCode();
    
    // Facebook Pixel
    $codes .= getFacebookPixelCode();
    
    // Google Search Console
    $codes .= getGoogleSearchConsoleCode();
    
    return $codes;
}

/**
 * Generate structured data for homepage
 */
function getStructuredData($page_key = 'homepage') {
    $seo_data = getSEOMetaTags($page_key);
    
    $structured_data = [
        "@context" => "https://schema.org",
        "@type" => "FinancialService",
        "name" => $seo_data['title'] ?: "Ultra Harvest Global",
        "description" => $seo_data['description'] ?: "Copy Forex Trades. Harvest Profits Fast.",
        "url" => $seo_data['canonical'] ?: SITE_URL,
        "logo" => $seo_data['og_image'] ?: SITE_URL . "ultra Harvest Logo.jpg",
        "sameAs" => [
            // Add social media profiles here when available
        ],
        "contactPoint" => [
            "@type" => "ContactPoint",
            "contactType" => "Customer Service",
            "availableLanguage" => "English"
        ]
    ];
    
    return '<script type="application/ld+json">' . json_encode($structured_data, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}

/**
 * Output complete SEO head content for a page
 */
function outputSEOHead($page_key = 'homepage') {
    echo generateMetaTagsHTML($page_key);
    echo getAllTrackingCodes();
    echo getStructuredData($page_key);
}

/**
 * Create/update robots.txt file
 */
function updateRobotsTxtFile() {
    $robots_content = generateRobotsTxt();
    $robots_path = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
    
    return file_put_contents($robots_path, $robots_content) !== false;
}

/**
 * SEO-friendly URL generator
 */
function generateSEOUrl($title, $id = null) {
    // Convert to lowercase and replace special characters
    $url = strtolower($title);
    $url = preg_replace('/[^a-z0-9\s-]/', '', $url);
    $url = preg_replace('/[\s-]+/', '-', $url);
    $url = trim($url, '-');
    
    // Add ID if provided
    if ($id) {
        $url = $id . '-' . $url;
    }
    
    return $url;
}

/**
 * Get page-specific meta data
 */
function getPageSEOData($page_key) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE ? OR setting_key LIKE 'seo_site_%'
        ");
        $stmt->execute(["seo_page_{$page_key}_%"]);
        
        $seo_data = [];
        while ($row = $stmt->fetch()) {
            $key = str_replace(['seo_', "page_{$page_key}_", 'site_'], '', $row['setting_key']);
            $seo_data[$key] = $row['setting_value'];
        }
        
        return $seo_data;
    } catch (Exception $e) {
        error_log("SEO: Failed to get page SEO data - " . $e->getMessage());
        return [];
    }
}
?>