/**
 * Sidebar - Collapsible Navigation
 * Simple, bulletproof toggle implementation
 */

(function() {
    'use strict';
    
    const STORAGE_KEY = 'sidebarCollapsed';
    let sidebar = null;
    let sidebarToggle = null;
    let isCollapsed = false;
    let tooltip = null;
    let isToggling = false; // Prevent rapid clicks
    
    // Simple initialization
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        setTimeout(setupSidebar, 100);
        setTimeout(setupSidebar, 500);
        setTimeout(setupSidebar, 1000);
    }
    
    function setupSidebar() {
        sidebar = document.getElementById('sidebar');
        sidebarToggle = document.getElementById('sidebarToggle');
        
        if (!sidebar || !sidebarToggle) {
            return;
        }
        
        // Skip if already initialized
        if (window.sidebarToggleInitialized) {
            return;
        }
        window.sidebarToggleInitialized = true;
        
        console.log('Setting up sidebar toggle...');
        
        // Basic sidebar setup
        sidebar.style.overflowX = 'hidden';
        
        // Load saved state FIRST before setting up button
        loadState();
        
        // Setup button
        setupButton();
        
        // Setup other features
        setupActiveLinks();
        setupScrollHandling();
        setupTooltips();
        
        window.addEventListener('resize', handleResize);
        
        console.log('Sidebar toggle ready! Current state:', isCollapsed);
    }
    
    function setupButton() {
        if (!sidebarToggle) return;
        
        console.log('Setting up button:', sidebarToggle);
        
        // Make absolutely sure button is clickable and visible
        sidebarToggle.style.cssText = 'display: inline-flex !important; visibility: visible !important; opacity: 1 !important; pointer-events: auto !important; cursor: pointer !important;';
        
        // Direct onclick handler
        sidebarToggle.onclick = function(e) {
            console.log('=== BUTTON CLICKED ===');
            e = e || window.event;
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            // Prevent rapid clicking
            if (isToggling) {
                console.log('Already toggling, ignoring click');
                return false;
            }
            
            toggleSidebar();
            return false;
        };
        
        // Add event listener as backup
        sidebarToggle.addEventListener('click', function(e) {
            console.log('Button clicked via addEventListener');
            e.preventDefault();
            e.stopPropagation();
            
            if (isToggling) return false;
            toggleSidebar();
            return false;
        }, true);
        
        console.log('✅ Button setup complete!');
    }
    
    function loadState() {
        if (window.innerWidth < 993) {
            isCollapsed = false;
            expandSidebar(false);
            return;
        }
        
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            isCollapsed = saved === 'true';
            
            console.log('Loading state from localStorage:', isCollapsed);
            
            if (isCollapsed) {
                collapseSidebar(false);
            } else {
                expandSidebar(false);
            }
        } catch (e) {
            isCollapsed = false;
            expandSidebar(false);
        }
    }
    
    function toggleSidebar() {
        if (isToggling) {
            console.log('Already toggling, skipping');
            return;
        }
        
        isToggling = true;
        
        console.log('=== TOGGLE FUNCTION CALLED ===');
        console.log('Current collapsed state BEFORE toggle:', isCollapsed);
        console.log('Sidebar current width:', sidebar ? sidebar.offsetWidth : 'N/A');
        
        if (window.innerWidth < 993) {
            console.log('Mobile - no toggle');
            isToggling = false;
            return;
        }
        
        // Check current actual state from DOM
        const currentlyCollapsed = sidebar && sidebar.classList.contains('sidebar-collapsed');
        console.log('Currently collapsed (from DOM):', currentlyCollapsed);
        console.log('isCollapsed variable:', isCollapsed);
        
        // Toggle based on actual DOM state if variables are out of sync
        if (currentlyCollapsed !== isCollapsed) {
            console.log('State mismatch detected! Syncing...');
            isCollapsed = currentlyCollapsed;
        }
        
        // Toggle the state
        isCollapsed = !isCollapsed;
        console.log('New collapsed state:', isCollapsed);
        
        // Apply the change
        if (isCollapsed) {
            collapseSidebar(true);
        } else {
            expandSidebar(true);
        }
        
        // Save state
        try {
            localStorage.setItem(STORAGE_KEY, isCollapsed.toString());
            console.log('State saved to localStorage:', isCollapsed);
        } catch (e) {
            console.error('Save error:', e);
        }
        
        // Allow toggling again after a short delay
        setTimeout(function() {
            isToggling = false;
        }, 300);
    }
    
    function collapseSidebar(save) {
        if (!sidebar) {
            console.error('Sidebar not available');
            isToggling = false;
            return;
        }
        
        console.log('=== COLLAPSING SIDEBAR ===');
        
        // Add classes
        document.body.classList.add('sidebar-collapsed');
        sidebar.classList.add('sidebar-collapsed');
        isCollapsed = true;
        
        // FORCE width with inline styles - use !important via setProperty
        sidebar.style.setProperty('width', '80px', 'important');
        sidebar.style.setProperty('min-width', '80px', 'important');
        sidebar.style.setProperty('max-width', '80px', 'important');
        
        // Update body padding
        if (window.innerWidth >= 993) {
            document.body.style.setProperty('padding-left', '80px', 'important');
        }
        
        // Update icon - show right chevron to expand
        updateIcon('chevron-right');
        
        // Force a reflow
        void sidebar.offsetWidth;
        
        console.log('Sidebar collapsed. Actual width:', sidebar.offsetWidth, 'px');
        console.log('Computed width:', window.getComputedStyle(sidebar).width);
    }
    
    function expandSidebar(save) {
        if (!sidebar) {
            console.error('Sidebar not available');
            isToggling = false;
            return;
        }
        
        console.log('=== EXPANDING SIDEBAR ===');
        
        // Remove classes
        document.body.classList.remove('sidebar-collapsed');
        sidebar.classList.remove('sidebar-collapsed');
        isCollapsed = false;
        
        // FORCE width with inline styles - use !important via setProperty
        sidebar.style.setProperty('width', '280px', 'important');
        sidebar.style.setProperty('min-width', '280px', 'important');
        sidebar.style.setProperty('max-width', '280px', 'important');
        
        // Update body padding
        if (window.innerWidth >= 993) {
            document.body.style.setProperty('padding-left', '280px', 'important');
        }
        
        // Update icon - show left chevron to collapse
        updateIcon('chevron-left');
        
        // Force a reflow
        void sidebar.offsetWidth;
        
        console.log('Sidebar expanded. Actual width:', sidebar.offsetWidth, 'px');
        console.log('Computed width:', window.getComputedStyle(sidebar).width);
    }
    
    function updateIcon(direction) {
        if (!sidebarToggle) return;
        
        const icon = sidebarToggle.querySelector('i');
        if (icon) {
            if (direction === 'chevron-right') {
                icon.className = 'bi bi-chevron-right';
                console.log('Icon changed to chevron-right (expand)');
            } else {
                icon.className = 'bi bi-chevron-left';
                console.log('Icon changed to chevron-left (collapse)');
            }
        }
    }
    
    function handleResize() {
        if (window.innerWidth < 993) {
            isCollapsed = false;
            expandSidebar(false);
            document.body.style.paddingLeft = '';
        } else {
            loadState();
        }
    }
    
    function setupTooltips() {
        const links = document.querySelectorAll('.sidebar-link');
        
        links.forEach(link => {
            const title = link.getAttribute('title') || link.querySelector('.sidebar-text')?.textContent?.trim() || '';
            if (!title) return;
            
            link.addEventListener('mouseenter', function(e) {
                const collapsed = sidebar && sidebar.classList.contains('sidebar-collapsed');
                if (collapsed && window.innerWidth >= 993) {
                    showTooltip(e.currentTarget, title);
                }
            });
            
            link.addEventListener('mouseleave', function() {
                hideTooltip();
            });
        });
    }
    
    function showTooltip(element, text) {
        if (!text) return;
        hideTooltip();
        
        tooltip = document.createElement('div');
        tooltip.className = 'sidebar-tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);
        
        void tooltip.offsetWidth;
        
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        const left = rect.left + rect.width + 12;
        const top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
        const maxTop = window.innerHeight - tooltipRect.height - 10;
        const finalTop = Math.max(10, Math.min(top, maxTop));
        
        tooltip.style.left = left + 'px';
        tooltip.style.top = finalTop + 'px';
        tooltip.style.display = 'block';
        
        const arrow = document.createElement('div');
        arrow.className = 'tooltip-arrow';
        tooltip.appendChild(arrow);
    }
    
    function hideTooltip() {
        if (tooltip) {
            tooltip.remove();
            tooltip = null;
        }
    }
    
    function setupActiveLinks() {
        const currentPath = window.location.pathname.toLowerCase();
        const currentHref = window.location.href.toLowerCase();
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        
        sidebarLinks.forEach(link => {
            link.classList.remove('active');
            
            const pageCategory = link.getAttribute('data-page');
            const linkHref = link.getAttribute('href') || '';
            
            let linkPath = linkHref.toLowerCase();
            try {
                const url = new URL(linkHref, window.location.origin);
                linkPath = url.pathname.toLowerCase();
            } catch (e) {
                linkPath = linkPath.split('?')[0].split('#')[0];
                if (linkPath.includes('://')) {
                    linkPath = linkPath.split('://')[1].split('/').slice(1).join('/');
                }
            }
            
            const hrefMatches = linkHref && (
                currentHref.toLowerCase().includes(linkPath) || 
                currentPath.includes(linkPath) ||
                currentPath.endsWith(linkPath) ||
                linkPath.includes(currentPath)
            );
            
            if (hrefMatches || (pageCategory && currentPath.includes(pageCategory))) {
                link.classList.add('active');
            }
        });
    }
    
    function setupScrollHandling() {
        if (!sidebar) return;
        
        const SCROLL_KEY = 'sidebarScrollPosition';
        const saved = localStorage.getItem(SCROLL_KEY);
        
        if (saved !== null && window.innerWidth > 992) {
            requestAnimationFrame(() => {
                try {
                    const pos = parseInt(saved, 10);
                    if (!isNaN(pos) && pos >= 0) {
                        sidebar.scrollTop = pos;
                    }
                } catch (e) {}
            });
        }
        
        let timeout;
        sidebar.addEventListener('scroll', function() {
            if (window.innerWidth <= 992) return;
            
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                try {
                    localStorage.setItem(SCROLL_KEY, sidebar.scrollTop.toString());
                } catch (e) {}
            }, 150);
        }, { passive: true });
        
        window.addEventListener('beforeunload', function() {
            if (window.innerWidth > 992 && sidebar) {
                try {
                    localStorage.setItem(SCROLL_KEY, sidebar.scrollTop.toString());
                } catch (e) {}
            }
        });
    }
    
    // Start
    init();
    
    // Expose toggle function globally
    window.toggleSidebarNow = toggleSidebar;
    window.toggleSidebarManually = toggleSidebar;
    
    console.log('Sidebar script loaded. Toggle function available at window.toggleSidebarNow()');

})();
