/**
 * Custom Notification System
 * Replaces browser's default confirm() and alert() with beautiful themed notifications
 */

class NotificationSystem {
    constructor() {
        this.init();
    }

    init() {
        // Create notification container if it doesn't exist
        if (!document.getElementById('notification-container')) {
            const container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'notification-container';
            document.body.appendChild(container);
        }

        // Create modal overlay for confirm dialogs
        if (!document.getElementById('confirm-modal-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'confirm-modal-overlay';
            overlay.className = 'confirm-modal-overlay';
            document.body.appendChild(overlay);
        }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - success, error, warning, info
     * @param {number} duration - Duration in milliseconds (default: 3000)
     */
    toast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} animate-slide-in-right`;
        
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };

        notification.innerHTML = `
            <div class="notification-content">
                <i class="bi ${icons[type] || icons.info} notification-icon"></i>
                <span class="notification-message">${this.escapeHtml(message)}</span>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="bi bi-x"></i>
            </button>
        `;

        container.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            notification.classList.add('animate-fade-out');
            setTimeout(() => notification.remove(), 300);
        }, duration);

        return notification;
    }

    /**
     * Show success notification
     */
    success(message, duration = 3000) {
        return this.toast(message, 'success', duration);
    }

    /**
     * Show error notification
     */
    error(message, duration = 4000) {
        return this.toast(message, 'error', duration);
    }

    /**
     * Show warning notification
     */
    warning(message, duration = 3500) {
        return this.toast(message, 'warning', duration);
    }

    /**
     * Show info notification
     */
    info(message, duration = 3000) {
        return this.toast(message, 'info', duration);
    }

    /**
     * Show confirmation dialog (replaces confirm())
     * @param {string} message - The confirmation message
     * @param {string} title - Optional title
     * @param {string} confirmText - Confirm button text
     * @param {string} cancelText - Cancel button text
     * @param {string} type - danger, warning, info
     * @returns {Promise<boolean>} - Returns true if confirmed, false if cancelled
     */
    confirm(message, title = 'Confirm Action', confirmText = 'Confirm', cancelText = 'Cancel', type = 'warning') {
        return new Promise((resolve) => {
            const overlay = document.getElementById('confirm-modal-overlay');
            const modal = document.createElement('div');
            modal.className = 'confirm-modal animate-scale-in';
            modal.id = 'confirm-modal';

            const icons = {
                danger: 'bi-exclamation-triangle-fill',
                warning: 'bi-exclamation-triangle-fill',
                info: 'bi-info-circle-fill'
            };

            const colors = {
                danger: 'danger',
                warning: 'warning',
                info: 'info'
            };

            modal.innerHTML = `
                <div class="confirm-modal-content">
                    <div class="confirm-modal-header">
                        <div class="confirm-modal-icon confirm-modal-icon-${colors[type] || 'warning'}">
                            <i class="bi ${icons[type] || icons.warning}"></i>
                        </div>
                        <h4 class="confirm-modal-title">${this.escapeHtml(title)}</h4>
                    </div>
                    <div class="confirm-modal-body">
                        <p class="confirm-modal-message">${this.escapeHtml(message)}</p>
                    </div>
                    <div class="confirm-modal-footer">
                        <button class="btn btn-secondary confirm-modal-cancel" type="button">
                            <i class="bi bi-x-circle me-2"></i>${this.escapeHtml(cancelText)}
                        </button>
                        <button class="btn btn-${colors[type] || 'warning'} confirm-modal-confirm" type="button">
                            <i class="bi bi-check-circle me-2"></i>${this.escapeHtml(confirmText)}
                        </button>
                    </div>
                </div>
            `;

            overlay.appendChild(modal);
            overlay.classList.add('active');

            // Handle confirm
            modal.querySelector('.confirm-modal-confirm').addEventListener('click', () => {
                this.closeConfirm();
                resolve(true);
            });

            // Handle cancel
            modal.querySelector('.confirm-modal-cancel').addEventListener('click', () => {
                this.closeConfirm();
                resolve(false);
            });

            // Handle overlay click (only if clicking directly on overlay, not on modal)
            const overlayClickHandler = (e) => {
                if (e.target === overlay) {
                    this.closeConfirm();
                    resolve(false);
                    overlay.removeEventListener('click', overlayClickHandler);
                }
            };
            overlay.addEventListener('click', overlayClickHandler);

            // Handle ESC key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    this.closeConfirm();
                    resolve(false);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }

    /**
     * Close confirmation modal
     */
    closeConfirm() {
        const overlay = document.getElementById('confirm-modal-overlay');
        const modal = document.getElementById('confirm-modal');
        if (modal) {
            modal.classList.add('animate-scale-out');
            setTimeout(() => {
                overlay.classList.remove('active');
                if (modal.parentElement) {
                    modal.remove();
                }
            }, 200);
        }
    }

    /**
     * Show alert dialog (replaces alert())
     * @param {string} message - The alert message
     * @param {string} title - Optional title
     * @param {string} type - success, error, warning, info
     */
    async alert(message, title = 'Alert', type = 'info') {
        return new Promise((resolve) => {
            const overlay = document.getElementById('confirm-modal-overlay');
            const modal = document.createElement('div');
            modal.className = 'confirm-modal animate-scale-in';
            modal.id = 'alert-modal';

            const icons = {
                success: 'bi-check-circle-fill',
                error: 'bi-x-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                info: 'bi-info-circle-fill'
            };

            const colors = {
                success: 'success',
                error: 'danger',
                warning: 'warning',
                info: 'info'
            };

            modal.innerHTML = `
                <div class="confirm-modal-content">
                    <div class="confirm-modal-header">
                        <div class="confirm-modal-icon confirm-modal-icon-${colors[type] || 'info'}">
                            <i class="bi ${icons[type] || icons.info}"></i>
                        </div>
                        <h4 class="confirm-modal-title">${this.escapeHtml(title)}</h4>
                    </div>
                    <div class="confirm-modal-body">
                        <p class="confirm-modal-message">${this.escapeHtml(message)}</p>
                    </div>
                    <div class="confirm-modal-footer">
                        <button class="btn btn-${colors[type] || 'info'} confirm-modal-ok" type="button">
                            <i class="bi bi-check-circle me-2"></i>OK
                        </button>
                    </div>
                </div>
            `;

            overlay.appendChild(modal);
            overlay.classList.add('active');

            // Handle OK
            modal.querySelector('.confirm-modal-ok').addEventListener('click', () => {
                this.closeAlert();
                resolve();
            });

            // Handle overlay click (only if clicking directly on overlay, not on modal)
            const overlayClickHandler = (e) => {
                if (e.target === overlay) {
                    this.closeAlert();
                    resolve();
                    overlay.removeEventListener('click', overlayClickHandler);
                }
            };
            overlay.addEventListener('click', overlayClickHandler);

            // Handle ESC key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    this.closeAlert();
                    resolve();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }

    /**
     * Close alert modal
     */
    closeAlert() {
        const overlay = document.getElementById('confirm-modal-overlay');
        const modal = document.getElementById('alert-modal');
        if (modal) {
            modal.classList.add('animate-scale-out');
            setTimeout(() => {
                overlay.classList.remove('active');
                if (modal.parentElement) {
                    modal.remove();
                }
            }, 200);
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize notification system immediately
// This ensures Notify is available even if scripts load in different order
(function() {
    'use strict';
    try {
        const notifyInstance = new NotificationSystem();
        window.Notify = notifyInstance;
        // Also make it available as a const for backward compatibility
        if (typeof window.Notify === 'undefined') {
            window.Notify = notifyInstance;
        }
    } catch (e) {
        console.error('Failed to initialize notification system:', e);
        // Fallback: initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    const notifyInstance = new NotificationSystem();
                    window.Notify = notifyInstance;
                } catch (err) {
                    console.error('Failed to initialize notification system on DOMContentLoaded:', err);
                }
            });
        } else {
            try {
                const notifyInstance = new NotificationSystem();
                window.Notify = notifyInstance;
            } catch (err) {
                console.error('Failed to initialize notification system:', err);
            }
        }
    }
})();

// Create a global reference for easier access (will be set after initialization)
// Use window.Notify directly to ensure it's always available

// Global functions for backward compatibility
window.customConfirm = function(message, title, confirmText, cancelText, type) {
    if (typeof Notify === 'undefined') {
        Notify = window.Notify || new NotificationSystem();
    }
    return Notify.confirm(message, title, confirmText, cancelText, type);
};

window.customAlert = function(message, title, type) {
    if (typeof Notify === 'undefined') {
        Notify = window.Notify || new NotificationSystem();
    }
    return Notify.alert(message, title, type);
};

// Replace default confirm and alert (optional - can be enabled if needed)
// window.confirm = function(message) {
//     return Notify.confirm(message, 'Confirm', 'Confirm', 'Cancel', 'warning');
// };

// window.alert = function(message) {
//     return Notify.alert(message, 'Alert', 'info');
// };

