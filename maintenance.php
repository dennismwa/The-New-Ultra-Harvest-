<?php
// Don't require database for maintenance page
if (!defined('MAINTENANCE_MODE')) {
    define('MAINTENANCE_MODE', true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .maintenance-bg {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%);
        }
        
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen maintenance-bg flex items-center justify-center">
    <div class="text-center px-4">
        <div class="mb-8">
            <div class="w-24 h-24 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-6 pulse">
                <i class="fas fa-seedling text-white text-4xl"></i>
            </div>
            <h1 class="text-4xl lg:text-6xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent mb-4">
                Ultra Harvest Global
            </h1>
        </div>
        
        <div class="max-w-2xl mx-auto">
            <i class="fas fa-wrench text-6xl text-yellow-400 mb-6"></i>
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-6">Under Maintenance</h2>
            <p class="text-xl text-gray-300 mb-8 leading-relaxed">
                We're currently performing scheduled maintenance to improve your trading experience. 
                We'll be back online shortly.
            </p>
            
            <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700">
                <h3 class="text-xl font-bold text-white mb-4">What's Being Updated?</h3>
                <div class="grid md:grid-cols-2 gap-6 text-left">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-check text-emerald-400 mt-1"></i>
                        <div>
                            <h4 class="font-medium text-white">System Performance</h4>
                            <p class="text-gray-400 text-sm">Optimizing platform speed and reliability</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-check text-emerald-400 mt-1"></i>
                        <div>
                            <h4 class="font-medium text-white">Security Updates</h4>
                            <p class="text-gray-400 text-sm">Enhancing account and transaction security</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-check text-emerald-400 mt-1"></i>
                        <div>
                            <h4 class="font-medium text-white">New Features</h4>
                            <p class="text-gray-400 text-sm">Adding exciting new trading packages</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-check text-emerald-400 mt-1"></i>
                        <div>
                            <h4 class="font-medium text-white">Bug Fixes</h4>
                            <p class="text-gray-400 text-sm">Resolving minor issues for better UX</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8">
                <p class="text-gray-400 mb-6">Need immediate assistance?</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://wa.me/254700000000" target="_blank" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>WhatsApp Support
                    </a>
                    <a href="mailto:support@ultraharvest.com" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition flex items-center justify-center">
                        <i class="fas fa-envelope mr-2"></i>Email Us
                    </a>
                </div>
            </div>
            
            <div class="mt-12 text-center">
                <p class="text-gray-500 text-sm">
                    Follow us for real-time updates:
                </p>
                <div class="flex justify-center space-x-4 mt-4">
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition">
                        <i class="fab fa-twitter text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-600 transition">
                        <i class="fab fa-facebook text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-pink-400 transition">
                        <i class="fab fa-instagram text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 5 minutes to check if maintenance is over
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
        
        // Display current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            // Create time display if it doesn't exist
            let timeDisplay = document.getElementById('current-time');
            if (!timeDisplay) {
                timeDisplay = document.createElement('div');
                timeDisplay.id = 'current-time';
                timeDisplay.className = 'text-gray-500 text-sm mt-4';
                document.body.appendChild(timeDisplay);
            }
            
            timeDisplay.textContent = `Current Time: ${timeString}`;
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    </script>
</body>
</html>