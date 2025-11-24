/**
 * Initialize Bootstrap Dropdowns
 * Ensures dropdown menus work properly
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Initialize all Bootstrap dropdowns
    const dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    
    dropdownElements.forEach(function(element) {
        // Ensure Bootstrap Dropdown is initialized
        if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
            try {
                new bootstrap.Dropdown(element, {
                    boundary: 'viewport',
                    popperConfig: {
                        modifiers: [
                            {
                                name: 'offset',
                                options: {
                                    offset: [0, 8]
                                }
                            }
                        ]
                    }
                });
            } catch (e) {
                console.warn('Failed to initialize dropdown:', e);
            }
        }
    });
    
    // Handle dropdown toggle manually if Bootstrap isn't loaded
    dropdownElements.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                const isOpen = dropdownMenu.style.display === 'block';
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu !== dropdownMenu) {
                        menu.style.display = 'none';
                    }
                });
                
                // Toggle current dropdown
                if (isOpen) {
                    dropdownMenu.style.display = 'none';
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    dropdownMenu.style.display = 'block';
                    this.setAttribute('aria-expanded', 'true');
                }
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(btn => {
                btn.setAttribute('aria-expanded', 'false');
            });
        }
    });
});

