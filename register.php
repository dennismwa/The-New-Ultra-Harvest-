<?php
require_once 'config/database.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

if ($_POST) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $referral_code = sanitize($_POST['referral_code'] ?? '');
        
        // Validation
        if (empty($email) || empty($password) || empty($full_name) || empty($phone)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email address already registered.';
            } else {
                // Check referral code if provided
                $referrer_id = null;
                if (!empty($referral_code)) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
                    $stmt->execute([$referral_code]);
                    $referrer = $stmt->fetch();
                    if (!$referrer) {
                        $error = 'Invalid referral code.';
                    } else {
                        $referrer_id = $referrer['id'];
                    }
                }
                
                if (empty($error)) {
                    // Generate unique referral code
                    do {
                        $user_referral_code = generateReferralCode();
                        $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
                        $stmt->execute([$user_referral_code]);
                    } while ($stmt->fetch());
                    
                    // Create user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO users (email, password, full_name, phone, referral_code, referred_by) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$email, $hashed_password, $full_name, $phone, $user_referral_code, $referrer_id])) {
                        $user_id = $db->lastInsertId();
                        
                        // Send welcome notification
                        sendNotification($user_id, 'Welcome to Ultra Harvest!', 'Your account has been created successfully. Start trading now!', 'success');
                        
                        $success = 'Account created successfully! You can now login.';
                        
                        // Auto-login after registration
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['email'] = $email;
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['is_admin'] = 0;
                        
                        header('Location: /user/dashboard.php');
                        exit;
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { 
            font-family: 'Poppins', sans-serif; 
        }
        
        body {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }
        
        .hero-bg {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%);
            min-height: 100vh;
            position: relative;
        }
        
        .hero-bg::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%2310b981" fill-opacity="0.05" points="0,0 1000,300 1000,1000 0,700"/></svg>'),
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%23fbbf24" fill-opacity="0.05" points="1000,0 0,400 0,1000 1000,600"/></svg>');
            background-size: cover;
            z-index: -1;
        }
        
        .form-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .main-content {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body class="bg-gray-900 text-white hero-bg">
    
    <!-- Header -->
    <header class="py-4 relative z-10">
    <div class="container mx-auto px-4">
        <div class="flex justify-center items-center md:justify-between">
            <!-- Centered Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                    <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </div>
                <div>
                   <!-- <h1 class="text-2xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</h1>
                    <p class="text-sm text-gray-300">Global</p>-->
                </div>
            </div>
            
            <div class="hidden md:flex space-x-6">
                <a href="/" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                <a href="/#packages" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                <a href="/contact.php" class="text-gray-300 hover:text-emerald-400 transition">Contact</a>
                <a href="/login.php" class="text-emerald-400 hover:text-emerald-300 transition">Login</a>
            </div>
        </div>
    </div>
</header>

    <!-- Main Content -->
    <main class="main-content py-8">
        <div class="container mx-auto px-4">
            <div class="max-w-md mx-auto">
                
                <!-- Registration Card -->
                <div class="form-card rounded-3xl p-8 shadow-2xl">
                    <div class="text-center mb-6">
                        <h2 class="text-3xl font-bold mb-2">
                            Join <span class="bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                        </h2>
                        <p class="text-gray-300">Start your wealth journey today</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                                <span class="text-red-300"><?php echo $error; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="mb-6 p-4 bg-green-500/20 border border-green-500/50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                <span class="text-green-300"><?php echo $success; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Full Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-user mr-2"></i>Full Name
                            </label>
                            <input 
                                type="text" 
                                name="full_name" 
                                value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors" 
                                placeholder="Enter your full name"
                                required
                            >
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email Address
                            </label>
                            <input 
                                type="email" 
                                name="email" 
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors" 
                                placeholder="Enter your email"
                                required
                            >
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-phone mr-2"></i>Phone Number
                            </label>
                            <input 
                                type="tel" 
                                name="phone" 
                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors" 
                                placeholder="254XXXXXXXXX"
                                required
                            >
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>Password
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password"
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 pr-12 transition-colors" 
                                    placeholder="Create a strong password"
                                    required
                                    minlength="6"
                                >
                                <button type="button" onclick="togglePassword('password')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white transition-colors">
                                    <i class="fas fa-eye" id="password-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>Confirm Password
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    name="confirm_password" 
                                    id="confirm_password"
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 pr-12 transition-colors" 
                                    placeholder="Confirm your password"
                                    required
                                >
                                <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white transition-colors">
                                    <i class="fas fa-eye" id="confirm_password-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Referral Code (Optional) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-gift mr-2"></i>Referral Code (Optional)
                            </label>
                            <input 
                                type="text" 
                                name="referral_code" 
                                value="<?php echo htmlspecialchars($_POST['referral_code'] ?? $_GET['ref'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 transition-colors" 
                                placeholder="Enter referral code if you have one"
                            >
                            <p class="text-xs text-gray-400 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>Get bonus rewards with a referral code
                            </p>
                        </div>

                        <!-- Terms Agreement -->
                        <div class="flex items-start space-x-3">
                            <input 
                                type="checkbox" 
                                id="terms" 
                                required
                                class="mt-1 w-4 h-4 text-emerald-600 bg-white/10 border-white/20 rounded focus:ring-emerald-500 focus:ring-2"
                            >
                            <label for="terms" class="text-sm text-gray-300 leading-relaxed">
                                I agree to the <a href="/terms.php" target="_blank" class="text-emerald-400 hover:text-emerald-300">Terms & Conditions</a> 
                                and <a href="/privacy.php" target="_blank" class="text-emerald-400 hover:text-emerald-300">Privacy Policy</a>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="w-full py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-[1.02] transition-all duration-300 shadow-lg hover:shadow-xl"
                        >
                            <i class="fas fa-user-plus mr-2"></i>Create My Account
                        </button>

                        <!-- Login Link -->
                        <div class="text-center pt-4 border-t border-white/20">
                            <p class="text-gray-300">
                                Already have an account? 
                                <a href="/login.php" class="text-emerald-400 hover:text-emerald-300 font-medium">Login here</a>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Additional Info -->
                <div class="mt-6 text-center">
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-shield-alt text-emerald-400 text-xl mb-2"></i>
                            <span class="text-gray-300">Secure</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <i class="fas fa-clock text-yellow-400 text-xl mb-2"></i>
                            <span class="text-gray-300">Fast Setup</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <i class="fas fa-chart-line text-emerald-400 text-xl mb-2"></i>
                            <span class="text-gray-300">Start Trading</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 text-center relative z-10">
        <p class="text-gray-400">
            © <?php echo date('Y'); ?> Ultra Harvest Global. Growing Wealth Together.
        </p>
    </footer>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            if (password.length < 6) {
                this.style.borderColor = '#ef4444';
            } else if (password.length < 8) {
                this.style.borderColor = '#f59e0b';
            } else {
                this.style.borderColor = '#10b981';
            }
        });

        // Real-time password confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#ef4444';
            } else if (confirmPassword && password === confirmPassword) {
                this.style.borderColor = '#10b981';
            }
        });

        // Phone number formatting
        document.querySelector('input[name="phone"]').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = '254' + value.substring(1);
            }
            if (!value.startsWith('254') && value.length > 0) {
                value = '254' + value;
            }
            this.value = value;
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });

        // Smooth scroll to top when page loads (helpful for mobile)
        window.addEventListener('load', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Ensure form fields are accessible on mobile
        document.addEventListener('DOMContentLoaded', function() {
            // Add touch-friendly behavior for mobile devices
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    // Small delay to ensure keyboard is up before scrolling
                    setTimeout(() => {
                        this.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center',
                            inline: 'nearest'
                        });
                    }, 300);
                });
            });
        });
    </script>
</body>
</html>