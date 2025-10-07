// Notification System JavaScript
class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.isOpen = false;
        this.isLoading = false;
        this.lastFetchTime = 0;
        this.pollInterval = null;
        
        this.init();
    }
    
    init() {
        this.createNotificationDropdown();
        this.bindEvents();
        this.loadNotifications();
        this.startPolling();
    }
    
    createNotificationDropdown() {
        // Find the notification bell
        const bellContainer = document.querySelector('.notification-bell-container');
        if (!bellContainer) {
            console.warn('Notification bell container not found');
            return;
        }
        
        // Create the dropdown HTML
        const dropdownHTML = `
            <div class="notification-dropdown hidden absolute right-0 top-full mt-2 w-96 bg-gray-800 rounded-lg shadow-xl border border-gray-700 z-50 max-h-96 overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-700">
                    <h3 class="font-semibold text-white">Notifications</h3>
                    <div class="flex items-center space-x-2">
                        <button id="markAllRead" class="text-xs text-emerald-400 hover:text-emerald-300 transition">
                            Mark all read
                        </button>
                        <button id="notificationSettings" class="text-gray-400 hover:text-white transition">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Notifications List -->
                <div id="notificationsList" class="max-h-80 overflow-y-auto">
                    <!-- Notifications will be loaded here -->
                </div>
                
                <!-- Footer -->
                <div class="p-3 border-t border-gray-700 text-center">
                    <a href="/user/notifications.php" class="text-sm text-emerald-400 hover:text-emerald-300 transition">
                        View All Notifications
                    </a>
                </div>
            </div>
        `;
        
        bellContainer.insertAdjacentHTML('beforeend', dropdownHTML);
    }
    
    bindEvents() {
        // Bell click event
        const bellButton = document.querySelector('.notification-bell');
        if (bellButton) {
            bellButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
        }
        
        // Mark all as read
        document.addEventListener('click', (e) => {
            if (e.target.id === 'markAllRead') {
                this.markAllAsRead();
            }
        });
        
        // Settings button
        document.addEventListener('click', (e) => {
            if (e.target.id === 'notificationSettings' || e.target.closest('#notificationSettings')) {
                window.location.href = '/user/settings.php#notifications';
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.querySelector('.notification-dropdown');
            const bellContainer = document.querySelector('.notification-bell-container');
            
            if (dropdown && bellContainer && 
                !bellContainer.contains(e.target) && 
                !dropdown.contains(e.target)) {
                this.closeDropdown();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeDropdown();
            }
        });
    }
    
    async loadNotifications() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        try {
            const [notificationsResponse, countResponse] = await Promise.all([
                fetch('/api/notifications.php?action=get_notifications&limit=10'),
                fetch('/api/notifications.php?action=get_unread_count')
            ]);
            
            if (notificationsResponse.ok && countResponse.ok) {
                const notificationsData = await notificationsResponse.json();
                const countData = await countResponse.json();
                
                if (notificationsData.success) {
                    this.notifications = notificationsData.notifications;
                    this.renderNotifications();
                }
                
                if (countData.success) {
                    this.unreadCount = countData.count;
                    this.updateBadge();
                }
                
                this.lastFetchTime = Date.now();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        } finally {
            this.isLoading = false;
        }
    }
    
    renderNotifications() {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        
        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="p-8 text-center">
                    <i class="fas fa-bell-slash text-4xl text-gray-600 mb-3"></i>
                    <p class="text-gray-400">No notifications yet</p>
                    <p class="text-sm text-gray-500">You'll see updates here when they arrive</p>
                </div>
            `;
            return;
        }
        
        const notificationsHTML = this.notifications.map(notification => `
            <div class="notification-item p-4 border-b border-gray-700 last:border-b-0 ${!notification.is_read ? 'bg-emerald-500/5 border-l-2 border-l-emerald-500' : ''} hover:bg-gray-700/30 transition cursor-pointer"
                 data-notification-id="${notification.id}">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-1">
                        <i class="fas ${this.getNotificationIcon(notification.type)} ${this.getNotificationColor(notification.type)}"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <h4 class="font-medium text-white text-sm truncate">${this.escapeHtml(notification.title)}</h4>
                            ${!notification.is_read ? '<div class="w-2 h-2 bg-emerald-400 rounded-full flex-shrink-0"></div>' : ''}
                        </div>
                        <p class="text-sm text-gray-300 leading-relaxed">${this.escapeHtml(notification.message)}</p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-gray-500">${notification.time_ago}</span>
                            <div class="flex items-center space-x-2">
                                ${!notification.is_read ? `
                                    <button class="mark-read-btn text-xs text-emerald-400 hover:text-emerald-300 transition" data-id="${notification.id}">
                                        Mark read
                                    </button>
                                ` : ''}
                                <button class="delete-btn text-xs text-red-400 hover:text-red-300 transition" data-id="${notification.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = notificationsHTML;
        
        // Bind notification actions
        this.bindNotificationActions();
    }
    
    bindNotificationActions() {
        // Mark individual notification as read
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const notificationId = parseInt(btn.dataset.id);
                this.markAsRead(notificationId);
            });
        });
        
        // Delete notification
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const notificationId = parseInt(btn.dataset.id);
                this.deleteNotification(notificationId);
            });
        });
        
        // Click notification to mark as read
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const notificationId = parseInt(item.dataset.notificationId);
                const notification = this.notifications.find(n => n.id === notificationId);
                
                if (notification && !notification.is_read) {
                    this.markAsRead(notificationId);
                }
            });
        });
    }
    
    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('notification_id', notificationId);
            
            const response = await fetch('/api/notifications.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // Update local state
                    const notification = this.notifications.find(n => n.id === notificationId);
                    if (notification) {
                        notification.is_read = 1;
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                        this.updateBadge();
                        this.renderNotifications();
                    }
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            
            const response = await fetch('/api/notifications.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // Update local state
                    this.notifications.forEach(n => n.is_read = 1);
                    this.unreadCount = 0;
                    this.updateBadge();
                    this.renderNotifications();
                    
                    this.showToast('All notifications marked as read', 'success');
                }
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            this.showToast('Failed to mark notifications as read', 'error');
        }
    }
    
    async deleteNotification(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'delete_notification');
            formData.append('notification_id', notificationId);
            
            const response = await fetch('/api/notifications.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // Update local state
                    const notification = this.notifications.find(n => n.id === notificationId);
                    if (notification && !notification.is_read) {
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                        this.updateBadge();
                    }
                    
                    this.notifications = this.notifications.filter(n => n.id !== notificationId);
                    this.renderNotifications();
                    
                    this.showToast('Notification deleted', 'success');
                }
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
            this.showToast('Failed to delete notification', 'error');
        }
    }
    
    updateBadge() {
        const badge = document.querySelector('.notification-badge');
        if (!badge) return;
        
        if (this.unreadCount > 0) {
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
    
    toggleDropdown() {
        if (this.isOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }
    
    openDropdown() {
        const dropdown = document.querySelector('.notification-dropdown');
        if (!dropdown) return;
        
        dropdown.classList.remove('hidden');
        this.isOpen = true;
        
        // Refresh notifications when opening
        this.loadNotifications();
    }
    
    closeDropdown() {
        const dropdown = document.querySelector('.notification-dropdown');
        if (!dropdown) return;
        
        dropdown.classList.add('hidden');
        this.isOpen = false;
    }
    
    startPolling() {
        // Poll for new notifications every 30 seconds
        this.pollInterval = setInterval(() => {
            if (!this.isOpen) {
                this.loadNotifications();
            }
        }, 30000);
    }
    
    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }
    
    getNotificationIcon(type) {
        switch (type) {
            case 'success': return 'fa-check-circle';
            case 'warning': return 'fa-exclamation-triangle';
            case 'error': return 'fa-exclamation-circle';
            case 'info': return 'fa-info-circle';
            default: return 'fa-bell';
        }
    }
    
    getNotificationColor(type) {
        switch (type) {
            case 'success': return 'text-emerald-400';
            case 'warning': return 'text-yellow-400';
            case 'error': return 'text-red-400';
            case 'info': return 'text-blue-400';
            default: return 'text-gray-400';
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showToast(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full ${
            type === 'success' ? 'bg-emerald-600 text-white' :
            type === 'error' ? 'bg-red-600 text-white' :
            type === 'warning' ? 'bg-yellow-600 text-black' :
            'bg-blue-600 text-white'
        }`;
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${this.getNotificationIcon(type)} mr-2"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }
    
    // Method to add new notification (for real-time updates)
    addNotification(notification) {
        this.notifications.unshift(notification);
        this.unreadCount++;
        this.updateBadge();
        
        if (this.isOpen) {
            this.renderNotifications();
        }
        
        // Show toast for new notification
        this.showToast(notification.title, notification.type || 'info');
    }
    
    // Cleanup method
    destroy() {
        this.stopPolling();
        // Remove event listeners if needed
    }
}

// Initialize the notification system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on a user page and logged in
    if (window.location.pathname.startsWith('/user/') || 
        document.querySelector('.notification-bell-container')) {
        window.notificationSystem = new NotificationSystem();
    }
});

// Export for potential use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationSystem;
}