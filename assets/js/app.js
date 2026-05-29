/**
 * WaMark - Main JavaScript
 * Handles theme toggle, sidebar, notifications, AJAX utilities
 */

(function() {
    'use strict';

    // ============================================
    // Theme Management
    // ============================================
    const THEME_KEY = 'wm_theme';

    function getTheme() {
        return document.cookie.split('; ').find(r => r.startsWith(THEME_KEY + '='))?.split('=')[1] || 
               document.documentElement.getAttribute('data-theme') || 'light';
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.body.setAttribute('data-theme', theme);
        document.cookie = `${THEME_KEY}=${theme};path=/;max-age=31536000;SameSite=Lax`;
    }

    window.toggleTheme = function() {
        const current = getTheme();
        const newTheme = current === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    };

    // Apply saved theme on load
    setTheme(getTheme());

    // ============================================
    // Sidebar Management
    // ============================================
    window.toggleSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }
    };

    // Close sidebar on overlay click (mobile)
    document.addEventListener('click', function(e) {
        if (e.target.id === 'sidebarOverlay') {
            toggleSidebar();
        }
    });

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });

    // ============================================
    // AJAX Utilities
    // ============================================
    window.WaMark = {
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

        /**
         * Make AJAX request
         */
        ajax: function(url, options = {}) {
            const defaults = {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            };

            const config = { ...defaults, ...options };
            
            if (config.data && !(config.data instanceof FormData)) {
                config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                if (typeof config.data === 'object') {
                    config.body = new URLSearchParams(config.data).toString();
                } else {
                    config.body = config.data;
                }
                delete config.data;
            }

            return fetch(url, config).then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            });
        },

        /**
         * Show toast notification
         */
        toast: function(message, type = 'success', duration = 4000) {
            const container = document.querySelector('.flash-container') || document.querySelector('.app-content');
            if (!container) return;

            const alertClass = type === 'error' ? 'danger' : type;
            const icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle');
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${alertClass} alert-dismissible fade show`;
            alert.style.animation = 'fadeIn 0.3s ease';
            alert.innerHTML = `<i class="bi bi-${icon}"></i> ${message} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            
            container.insertBefore(alert, container.firstChild);

            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, duration);
        },

        /**
         * Confirm dialog
         */
        confirm: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },

        /**
         * Format number
         */
        formatNumber: function(num) {
            if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
            if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
            return num.toLocaleString();
        },

        /**
         * Copy to clipboard
         */
        copyToClipboard: function(text) {
            navigator.clipboard.writeText(text).then(() => {
                this.toast('Copied to clipboard!', 'success', 2000);
            }).catch(() => {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
                this.toast('Copied!', 'success', 2000);
            });
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    };

    // ============================================
    // Notifications Loader
    // ============================================
    function loadNotifications() {
        const list = document.getElementById('notificationList');
        if (!list) return;

        // Simple placeholder - in production, this would AJAX load
        list.innerHTML = `
            <div class="p-3 text-center text-muted small">
                <i class="bi bi-bell-slash"></i><br>
                No new notifications
            </div>
        `;
    }

    // Load on dropdown open
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
        el.addEventListener('shown.bs.dropdown', function() {
            if (this.querySelector('.bi-bell')) loadNotifications();
        });
    });

    // ============================================
    // Auto-dismiss alerts after 5 seconds
    // ============================================
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // ============================================
    // Form Enhancements
    // ============================================

    // Auto-resize textareas
    document.querySelectorAll('textarea[data-autoresize]').forEach(textarea => {
        textarea.style.overflow = 'hidden';
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });

    // Prevent double form submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('[type="submit"]');
            if (btn && !btn.dataset.allowMultiple) {
                btn.disabled = true;
                setTimeout(() => btn.disabled = false, 3000);
            }
        });
    });

    // ============================================
    // Table Select All Checkbox
    // ============================================
    document.querySelectorAll('#selectAll').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const table = this.closest('table');
            if (table) {
                table.querySelectorAll('tbody input[type="checkbox"]').forEach(cb => {
                    cb.checked = this.checked;
                });
            }
        });
    });

    // ============================================
    // Keyboard Shortcuts
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+K or Cmd+K for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) searchInput.focus();
        }
    });

})();
