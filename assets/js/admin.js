/**
 * Admin Dashboard JavaScript
 * Handles sidebar interactions and dynamic functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar functionality
    initSidebar();
    
    // Initialize group dropdown
    initGroupDropdown();
    
    // Initialize any data tables
    initDataTables();
});

/**
 * Sidebar Navigation
 */
function initSidebar() {
    const menuItems = document.querySelectorAll('.menu-item');
    
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Handle logout specially
            if (this.dataset.page === 'logout') {
                return; // Let the link work normally
            }
            
            // Prevent default for demo links
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
                
                // Update active state
                menuItems.forEach(mi => mi.classList.remove('active'));
                this.classList.add('active');
                
                // Show notification for demo
                showNotification('Coming soon!', 'This feature is under development.', 'info');
            }
        });
    });
}

/**
 * Group/Class Dropdown Handler
 */
function initGroupDropdown() {
    const dropdownItems = document.querySelectorAll('.group-dropdown .dropdown-item');
    const dropdownButton = document.querySelector('.group-dropdown .dropdown-toggle');
    
    dropdownItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const selectedText = this.textContent;
            dropdownButton.innerHTML = `<i class="bi bi-building"></i> ${selectedText}`;
            
            // Trigger data refresh (for demo)
            showNotification('Group Changed', `Now viewing: ${selectedText}`, 'success');
        });
    });
}

/**
 * Data Table Enhancements
 */
function initDataTables() {
    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    
    tableRows.forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking buttons
            if (e.target.closest('button')) return;
            
            // Highlight row
            tableRows.forEach(r => r.classList.remove('table-active'));
            this.classList.add('table-active');
        });
    });
}

/**
 * Toast Notification System
 */
function showNotification(title, message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `toast-notification toast-${type}`;
    notification.innerHTML = `
        <div class="toast-content">
            <i class="bi ${getIcon(type)}"></i>
            <div>
                <strong>${title}</strong>
                <p>${message}</p>
            </div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 15px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        min-width: 300px;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function getIcon(type) {
    const icons = {
        success: 'bi-check-circle-fill text-success',
        error: 'bi-exclamation-circle-fill text-danger',
        warning: 'bi-exclamation-triangle-fill text-warning',
        info: 'bi-info-circle-fill text-info'
    };
    return icons[type] || icons.info;
}

// Add animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .toast-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .toast-content i {
        font-size: 1.25rem;
    }
    .toast-content strong {
        display: block;
        color: #1f2937;
    }
    .toast-content p {
        margin: 0;
        font-size: 0.875rem;
        color: #6b7280;
    }
    .toast-close {
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        padding: 0;
        margin-left: auto;
    }
    .toast-close:hover {
        color: #6b7280;
    }
`;
document.head.appendChild(style);

// Export functions for global use
window.showNotification = showNotification;
