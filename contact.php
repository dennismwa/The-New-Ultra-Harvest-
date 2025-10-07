<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .hero-bg {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%2310b981" fill-opacity="0.05" points="0,0 1000,300 1000,1000 0,700"/></svg>'),
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%23fbbf24" fill-opacity="0.05" points="1000,0 0,400 0,1000 1000,600"/></svg>');
            background-size: cover;
        }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .contact-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .contact-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .floating-element {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .pulse-glow {
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.3); }
            50% { box-shadow: 0 0 30px rgba(16, 185, 129, 0.6); }
        }
        
        .contact-form input:focus, .contact-form textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <div class="hero-bg">
        
        <!-- Header -->
        <header class="py-6 relative z-10">
            <div class="container mx-auto px-4">
                <div class="flex justify-between items-center">
                    <a href="/" class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra_harvest_logo.jpg" alt="Ultra Harvest Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</h1>
                            <p class="text-sm text-gray-300">Global</p>
                        </div>
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                        <a href="/#packages" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                        <a href="/about.php" class="text-gray-300 hover:text-emerald-400 transition">About</a>
                        <a href="/contact.php" class="text-emerald-400 font-medium">Contact</a>
                        <a href="/login.php" class="bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg transition">Login</a>
                    </nav>

                    <!-- Mobile Menu Button -->
                    <button class="md:hidden text-white" onclick="toggleMobileMenu()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>

                <!-- Mobile Menu -->
                <div id="mobileMenu" class="md:hidden mt-4 space-y-3 hidden">
                    <a href="/" class="block py-2 text-gray-300 hover:text-emerald-400 transition">Home</a>
                    <a href="/#packages" class="block py-2 text-gray-300 hover:text-emerald-400 transition">Packages</a>
                    <a href="/about.php" class="block py-2 text-gray-300 hover:text-emerald-400 transition">About</a>
                    <a href="/contact.php" class="block py-2 text-emerald-400 font-medium">Contact</a>
                    <a href="/login.php" class="block py-2 bg-emerald-600 hover:bg-emerald-700 px-4 rounded-lg transition text-center">Login</a>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="py-20 relative z-10">
            <div class="container mx-auto px-4 text-center">
                <div class="floating-element">
                    <i class="fas fa-envelope text-6xl text-emerald-400 mb-8"></i>
                </div>
                <h1 class="text-5xl md:text-6xl font-bold mb-6">
                    Get in <span class="bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Touch</span>
                </h1>
                <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto">
                    We're here to help you grow your wealth. Reach out to our dedicated support team for any questions, 
                    assistance, or partnership opportunities.
                </p>
                <div class="flex flex-wrap justify-center gap-4">
                    <div class="flex items-center space-x-2 bg-emerald-600/20 px-4 py-2 rounded-full">
                        <i class="fas fa-clock text-emerald-400"></i>
                        <span class="text-sm">24/7 Support</span>
                    </div>
                    <div class="flex items-center space-x-2 bg-yellow-600/20 px-4 py-2 rounded-full">
                        <i class="fas fa-bolt text-yellow-400"></i>
                        <span class="text-sm">Quick Response</span>
                    </div>
                    <div class="flex items-center space-x-2 bg-blue-600/20 px-4 py-2 rounded-full">
                        <i class="fas fa-shield-alt text-blue-400"></i>
                        <span class="text-sm">Secure Communication</span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Contact Methods -->
    <section class="py-16 bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
                
                <!-- Email Support -->
                <div class="contact-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-envelope text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Email Support</h3>
                    <p class="text-gray-300 text-sm mb-4">Get detailed help via email</p>
                    <a href="mailto:support@ultraharvest.global" class="text-emerald-400 hover:text-emerald-300 font-medium">
                        support@ultraharvest.global
                    </a>
                    <div class="mt-4 text-xs text-gray-400">
                        <i class="fas fa-clock mr-1"></i>Response within 2 hours
                    </div>
                </div>

                <!-- WhatsApp Support -->
                <div class="contact-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-green-500 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fab fa-whatsapp text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">WhatsApp</h3>
                    <p class="text-gray-300 text-sm mb-4">Instant messaging support</p>
                    <a href="https://wa.me/254700000000" target="_blank" class="text-green-400 hover:text-green-300 font-medium">
                        +254 700 000 000
                    </a>
                    <div class="mt-4 text-xs text-gray-400">
                        <i class="fas fa-clock mr-1"></i>24/7 Available
                    </div>
                </div>

                <!-- Phone Support -->
                <div class="contact-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-phone text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Phone Support</h3>
                    <p class="text-gray-300 text-sm mb-4">Speak directly with our team</p>
                    <a href="tel:+254700000000" class="text-blue-400 hover:text-blue-300 font-medium">
                        +254 700 000 000
                    </a>
                    <div class="mt-4 text-xs text-gray-400">
                        <i class="fas fa-clock mr-1"></i>Mon-Fri 8AM-8PM
                    </div>
                </div>

                <!-- Live Chat -->
                <div class="contact-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4 pulse-glow">
                        <i class="fas fa-comments text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Live Chat</h3>
                    <p class="text-gray-300 text-sm mb-4">Chat with us in real-time</p>
                    <button onclick="openLiveChat()" class="text-purple-400 hover:text-purple-300 font-medium">
                        Start Chat Now
                    </button>
                    <div class="mt-4 text-xs text-gray-400">
                        <i class="fas fa-circle text-green-400 mr-1"></i>Online Now
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Form & Info -->
    <section class="py-16 bg-gray-800">
        <div class="container mx-auto px-4">
            <div class="grid lg:grid-cols-2 gap-12">
                
                <!-- Contact Form -->
                <div class="glass-card rounded-2xl p-8">
                    <h2 class="text-3xl font-bold mb-6">Send us a Message</h2>
                    <p class="text-gray-300 mb-8">Fill out the form below and we'll get back to you as soon as possible.</p>
                    
                    <form class="contact-form space-y-6" id="contactForm">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">First Name *</label>
                                <input 
                                    type="text" 
                                    name="first_name" 
                                    required
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400"
                                    placeholder="Enter your first name"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Last Name *</label>
                                <input 
                                    type="text" 
                                    name="last_name" 
                                    required
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400"
                                    placeholder="Enter your last name"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Email Address *</label>
                            <input 
                                type="email" 
                                name="email" 
                                required
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400"
                                placeholder="your.email@example.com"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Phone Number</label>
                            <input 
                                type="tel" 
                                name="phone" 
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400"
                                placeholder="+254 700 000 000"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Subject *</label>
                            <select 
                                name="subject" 
                                required
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white"
                            >
                                <option value="">Select a subject</option>
                                <option value="account_support">Account Support</option>
                                <option value="investment_inquiry">Investment Inquiry</option>
                                <option value="withdrawal_issue">Withdrawal Issue</option>
                                <option value="technical_support">Technical Support</option>
                                <option value="partnership">Partnership Opportunity</option>
                                <option value="complaint">Complaint</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Message *</label>
                            <textarea 
                                name="message" 
                                rows="6" 
                                required
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 resize-vertical"
                                placeholder="Please describe your inquiry in detail..."
                            ></textarea>
                        </div>

                        <div class="flex items-start space-x-3">
                            <input 
                                type="checkbox" 
                                id="privacy_agree" 
                                required
                                class="mt-1 w-4 h-4 text-emerald-600 bg-white/10 border-white/20 rounded focus:ring-emerald-500"
                            >
                            <label for="privacy_agree" class="text-sm text-gray-300">
                                I agree to the <a href="/privacy.php" target="_blank" class="text-emerald-400 hover:text-emerald-300">Privacy Policy</a> 
                                and consent to having this website store my submitted information.
                            </label>
                        </div>

                        <button 
                            type="submit" 
                            class="w-full py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-[1.02] transition-all duration-300 shadow-lg hover:shadow-xl"
                        >
                            <i class="fas fa-paper-plane mr-2"></i>Send Message
                        </button>
                    </form>
                </div>

                <!-- Company Information -->
                <div class="space-y-8">
                    <!-- Office Information -->
                    <div class="glass-card rounded-2xl p-8">
                        <h3 class="text-2xl font-bold mb-6">Visit Our Office</h3>
                        
                        <div class="space-y-6">
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-emerald-600/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-map-marker-alt text-emerald-400"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-white mb-1">Headquarters</h4>
                                    <p class="text-gray-300">
                                        Westlands Office Park<br>
                                        Waiyaki Way, Westlands<br>
                                        Nairobi, Kenya
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-blue-600/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-clock text-blue-400"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-white mb-1">Business Hours</h4>
                                    <p class="text-gray-300">
                                        Monday - Friday: 8:00 AM - 6:00 PM<br>
                                        Saturday: 9:00 AM - 1:00 PM<br>
                                        Sunday: Closed
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-purple-600/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-globe text-purple-400"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-white mb-1">Online Support</h4>
                                    <p class="text-gray-300">
                                        24/7 Support Available<br>
                                        Response Time: 1-2 hours<br>
                                        Emergency Support Available
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Quick Links -->
                    <div class="glass-card rounded-2xl p-8">
                        <h3 class="text-2xl font-bold mb-6">Quick Help</h3>
                        <p class="text-gray-300 mb-6">Looking for answers? Check out these common topics:</p>
                        
                        <div class="space-y-3">
                            <a href="/faq.php#deposits" class="flex items-center justify-between p-3 bg-white/5 rounded-lg hover:bg-white/10 transition group">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-arrow-down text-emerald-400"></i>
                                    <span>How to Make Deposits</span>
                                </div>
                                <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                            </a>
                            
                            <a href="/faq.php#withdrawals" class="flex items-center justify-between p-3 bg-white/5 rounded-lg hover:bg-white/10 transition group">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-arrow-up text-red-400"></i>
                                    <span>Withdrawal Process</span>
                                </div>
                                <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                            </a>
                            
                            <a href="/faq.php#packages" class="flex items-center justify-between p-3 bg-white/5 rounded-lg hover:bg-white/10 transition group">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-box text-blue-400"></i>
                                    <span>Investment Packages</span>
                                </div>
                                <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                            </a>
                            
                            <a href="/faq.php#security" class="flex items-center justify-between p-3 bg-white/5 rounded-lg hover:bg-white/10 transition group">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-shield-alt text-purple-400"></i>
                                    <span>Account Security</span>
                                </div>
                                <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="glass-card rounded-2xl p-8 border-red-500/20">
                        <h3 class="text-2xl font-bold mb-4 text-red-400">Emergency Support</h3>
                        <p class="text-gray-300 mb-4">For urgent account issues or security concerns:</p>
                        <div class="flex items-center space-x-4">
                            <a href="tel:+254700000000" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-medium text-center transition">
                                <i class="fas fa-phone mr-2"></i>Emergency Hotline
                            </a>
                            <a href="https://wa.me/254700000000?text=URGENT:" target="_blank" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-medium text-center transition">
                                <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="py-16 bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Find Us on the Map</h2>
                <p class="text-gray-300">Visit our office for in-person consultations and support</p>
            </div>
            
            <div class="glass-card rounded-2xl p-2 overflow-hidden">
                <div class="w-full h-96 bg-gray-800 rounded-xl flex items-center justify-center">
                    <div class="text-center">
                        <i class="fas fa-map-marked-alt text-6xl text-emerald-400 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Interactive Map</h3>
                        <p class="text-gray-300 mb-4">Westlands Office Park, Nairobi</p>
                        <button onclick="openMap()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg transition">
                            <i class="fas fa-external-link-alt mr-2"></i>Open in Google Maps
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-16 border-t border-white/10 bg-gray-800">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
                
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra_harvest_logo.jpg" alt="Ultra Harvest Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                        <div>
                            <h3 class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</h3>
                            <p class="text-xs text-gray-400">Global</p>
                        </div>
                    </div>
                    <p class="text-gray-300 mb-4">Growing wealth together through smart investments and innovative trading solutions.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-emerald-600 transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-emerald-600 transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-emerald-600 transition">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-emerald-600 transition">
                            <i class="fab fa-telegram"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="/" class="text-gray-300 hover:text-emerald-400 transition">Home</a></li>
                        <li><a href="/#packages" class="text-gray-300 hover:text-emerald-400 transition">Investment Packages</a></li>
                        <li><a href="/about.php" class="text-gray-300 hover:text-emerald-400 transition">About Us</a></li>
                        <li><a href="/faq.php" class="text-gray-300 hover:text-emerald-400 transition">FAQ</a></li>
                        <li><a href="/terms.php" class="text-gray-300 hover:text-emerald-400 transition">Terms & Conditions</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="/contact.php" class="text-gray-300 hover:text-emerald-400 transition">Contact Us</a></li>
                        <li><a href="/help.php" class="text-gray-300 hover:text-emerald-400 transition">Help Center</a></li>
                        <li><a href="/user/tickets.php" class="text-gray-300 hover:text-emerald-400 transition">Support Tickets</a></li>
                        <li><a href="/privacy.php" class="text-gray-300 hover:text-emerald-400 transition">Privacy Policy</a></li>
                        <li><a href="/security.php" class="text-gray-300 hover:text-emerald-400 transition">Security</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Contact Info</h4>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-envelope text-emerald-400"></i>
                            <span class="text-gray-300">support@ultraharvest.global</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-phone text-emerald-400"></i>
                            <span class="text-gray-300">+254 700 000 000</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i class="fab fa-whatsapp text-emerald-400"></i>
                            <span class="text-gray-300">+254 700 000 000</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-map-marker-alt text-emerald-400"></i>
                            <span class="text-gray-300">Westlands, Nairobi</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Footer -->
            <div class="border-t border-white/10 pt-8 text-center">
                <p class="text-gray-400">
                    © 2024 Ultra Harvest Global. All rights reserved. Growing Wealth Together.
                </p>
                <div class="flex justify-center space-x-6 mt-4 text-sm">
                    <a href="/terms.php" class="text-gray-400 hover:text-emerald-400 transition">Terms of Service</a>
                    <a href="/privacy.php" class="text-gray-400 hover:text-emerald-400 transition">Privacy Policy</a>
                    <a href="/cookie-policy.php" class="text-gray-400 hover:text-emerald-400 transition">Cookie Policy</a>
                    <a href="/disclaimer.php" class="text-gray-400 hover:text-emerald-400 transition">Disclaimer</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Success/Error Modal -->
    <div id="messageModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="glass-card rounded-xl p-8 max-w-md w-full mx-4">
            <div class="text-center">
                <div id="modalIcon" class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center">
                    <i id="modalIconClass" class="text-2xl"></i>
                </div>
                <h3 id="modalTitle" class="text-xl font-bold mb-2"></h3>
                <p id="modalMessage" class="text-gray-300 mb-6"></p>
                <button onclick="closeModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Live Chat Widget -->
    <div id="liveChatWidget" class="fixed bottom-6 right-6 z-40">
        <button onclick="toggleLiveChat()" class="w-14 h-14 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full shadow-lg hover:shadow-xl flex items-center justify-center text-white hover:scale-110 transition-all duration-300 pulse-glow">
            <i class="fas fa-comments text-xl"></i>
        </button>
        
        <!-- Chat Window -->
        <div id="chatWindow" class="absolute bottom-16 right-0 w-80 h-96 glass-card rounded-xl overflow-hidden hidden">
            <div class="bg-emerald-600 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-headset text-white"></i>
                        </div>
                        <div>
                            <h4 class="text-white font-medium">Support Team</h4>
                            <p class="text-emerald-100 text-xs">Online now</p>
                        </div>
                    </div>
                    <button onclick="toggleLiveChat()" class="text-white hover:text-emerald-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-4 h-64 overflow-y-auto">
                <div class="space-y-3">
                    <div class="bg-emerald-100 text-emerald-800 p-3 rounded-lg rounded-bl-sm">
                        <p class="text-sm">Hello! How can I help you today?</p>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t border-white/10">
                <div class="flex space-x-2">
                    <input type="text" placeholder="Type your message..." class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm placeholder-gray-400">
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg transition">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

   <script>
        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        // Contact Form Submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            submitBtn.disabled = true;
            
            // Simulate form submission
            setTimeout(() => {
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Show success message
                showModal('success', 'Message Sent!', 'Thank you for contacting us. We\'ll get back to you within 2 hours.');
                
                // Reset form
                e.target.reset();
            }, 2000);
        });

        // Modal Functions
        function showModal(type, title, message) {
            const modal = document.getElementById('messageModal');
            const icon = document.getElementById('modalIcon');
            const iconClass = document.getElementById('modalIconClass');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            
            if (type === 'success') {
                icon.className = 'w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center bg-emerald-500/20';
                iconClass.className = 'fas fa-check text-2xl text-emerald-400';
            } else {
                icon.className = 'w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center bg-red-500/20';
                iconClass.className = 'fas fa-times text-2xl text-red-400';
            }
            
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('messageModal').classList.add('hidden');
        }

        // Live Chat Functions
        function openLiveChat() {
            // In a real implementation, this would open your live chat system
            showModal('success', 'Live Chat', 'Connecting you to our support team...');
        }

        function toggleLiveChat() {
            const chatWindow = document.getElementById('chatWindow');
            chatWindow.classList.toggle('hidden');
        }

        // External Links
        function openMap() {
            window.open('https://maps.google.com/?q=Westlands+Office+Park+Nairobi', '_blank');
        }

        // Phone number formatting
        const phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.startsWith('0')) {
                    value = '+254' + value.substring(1);
                }
                if (!value.startsWith('+254') && value.length > 0) {
                    value = '+254' + value;
                }
                this.value = value;
            });
        }

        // Form validation enhancements
        document.querySelectorAll('input[required], textarea[required], select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#10b981';
                }
            });
        });

        // Email validation
        const emailInput = document.querySelector('input[type="email"]');
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value && !emailRegex.test(this.value)) {
                    this.style.borderColor = '#ef4444';
                } else if (this.value) {
                    this.style.borderColor = '#10b981';
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

        // Auto-hide mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('mobileMenu');
            const menuButton = e.target.closest('button');
            
            if (!menu.contains(e.target) && !menuButton) {
                menu.classList.add('hidden');
            }
        });

        // Floating animation trigger
        function triggerFloatingAnimation() {
            const elements = document.querySelectorAll('.floating-element');
            elements.forEach(el => {
                el.style.animationDelay = Math.random() * 2 + 's';
            });
        }

        // Initialize animations on load
        document.addEventListener('DOMContentLoaded', function() {
            triggerFloatingAnimation();
            
            // Add intersection observer for scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Observe contact cards for scroll animations
            document.querySelectorAll('.contact-card, .glass-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                observer.observe(card);
            });
        });

        // Copy contact info to clipboard
        function copyToClipboard(text, element) {
            navigator.clipboard.writeText(text).then(function() {
                const originalText = element.textContent;
                element.textContent = 'Copied!';
                element.style.color = '#10b981';
                
                setTimeout(() => {
                    element.textContent = originalText;
                    element.style.color = '';
                }, 2000);
            });
        }

        // Add click handlers for contact info
        document.querySelectorAll('.contact-card a, footer a[href^="mailto:"], footer a[href^="tel:"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    copyToClipboard(this.textContent, this);
                }
            });
        });

        // Emergency contact confirmation
        document.querySelectorAll('a[href*="URGENT"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('This will open emergency contact. Continue only if this is urgent.')) {
                    window.open(this.href, '_blank');
                }
            });
        });

        // Real-time character count for message textarea
        const messageTextarea = document.querySelector('textarea[name="message"]');
        if (messageTextarea) {
            const charCounter = document.createElement('div');
            charCounter.className = 'text-xs text-gray-400 mt-1 text-right';
            charCounter.textContent = '0/1000 characters';
            messageTextarea.parentNode.appendChild(charCounter);
            
            messageTextarea.addEventListener('input', function() {
                const length = this.value.length;
                charCounter.textContent = `${length}/1000 characters`;
                
                if (length > 800) {
                    charCounter.style.color = '#f59e0b';
                }
                if (length > 950) {
                    charCounter.style.color = '#ef4444';
                }
                if (length <= 800) {
                    charCounter.style.color = '#9ca3af';
                }
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals/chat
            if (e.key === 'Escape') {
                closeModal();
                document.getElementById('chatWindow').classList.add('hidden');
            }
            
            // Ctrl/Cmd + Enter to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const activeForm = document.querySelector('form:focus-within');
                if (activeForm) {
                    activeForm.querySelector('button[type="submit"]').click();
                }
            }
        });
    </script>
</body>
</html>