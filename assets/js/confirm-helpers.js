/**
 * Helper functions to replace inline confirm() and alert() calls
 * This file should be included after notifications.js
 */

// Replace inline onclick confirm handlers
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete links and buttons with data-confirm attribute
    document.querySelectorAll('a[data-confirm], button[data-confirm]').forEach(element => {
        element.addEventListener('click', async function(e) {
            // Check if it's a form button
            if (this.tagName === 'BUTTON' && this.type === 'submit' && this.form) {
                e.preventDefault();
                const message = this.dataset.confirm;
                const title = this.dataset.confirmTitle || 'Confirm Action';
                const type = this.dataset.confirmType || 'danger';
                
                // Ensure Notify is available
                if (typeof Notify === 'undefined') {
                    Notify = window.Notify || new NotificationSystem();
                }
                
                const confirmed = await Notify.confirm(message, title, 'Confirm', 'Cancel', type);
                if (confirmed) {
                    // Set form action if not set
                    if (this.form && !this.form.action) {
                        this.form.action = window.location.href;
                    }
                    this.form.submit();
                }
            } else if (this.tagName === 'A' || this.href) {
                // It's a link
                e.preventDefault();
                const message = this.dataset.confirm;
                const title = this.dataset.confirmTitle || 'Confirm Action';
                const type = this.dataset.confirmType || 'danger';
                const href = this.href;
                
                // Ensure Notify is available
                if (typeof window.Notify === 'undefined') {
                    console.warn('Notification system not loaded, using fallback');
                    if (confirm(message) && href) {
                        window.location.href = href;
                    }
                    return;
                }
                
                const confirmed = await window.Notify.confirm(message, title, 'Confirm', 'Cancel', type);
                if (confirmed && href) {
                    window.location.href = href;
                }
            }
        });
    });
});

// Helper function for delete actions
window.deleteConfirm = async function(message, title, type = 'danger') {
    return await Notify.confirm(
        message || 'Are you sure you want to delete this item?',
        title || 'Delete Confirmation',
        'Delete',
        'Cancel',
        type
    );
};

// Helper function for form submissions
window.submitConfirm = async function(form, message, title, type = 'warning') {
    const confirmed = await Notify.confirm(message, title, 'Confirm', 'Cancel', type);
    if (confirmed) {
        form.submit();
    }
};

