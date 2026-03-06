/**
 * StudyFlow - Main JavaScript
 */

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    initApp();
});

/**
 * Initialize Application
 */
function initApp() {
    // Initialize components
    initMobileMenu();
    initFormValidation();
    initAlertDismiss();
    initClipboard();
    initFileUpload();
}

/**
 * Mobile Menu Toggle
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }
}

/**
 * Form Validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            // Password confirmation check
            const password = form.querySelector('#password');
            const confirmPassword = form.querySelector('#confirm_password');
            
            if (password && confirmPassword) {
                if (password.value !== confirmPassword.value) {
                    isValid = false;
                    confirmPassword.classList.add('error');
                    showAlert('Passwords do not match', 'error');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Auto-dismiss alerts
 */
function initAlertDismiss() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.className = 'alert-close';
        closeBtn.style.cssText = 'position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 20px; cursor: pointer; opacity: 0.5;';
        
        alert.style.position = 'relative';
        alert.style.paddingRight = '40px';
        alert.appendChild(closeBtn);
        
        closeBtn.addEventListener('click', () => {
            alert.remove();
        });
        
        // Auto dismiss success alerts after 5 seconds
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.3s';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
    });
}

/**
 * Clipboard functionality
 */
function initClipboard() {
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard!');
        }).catch(err => {
            console.error('Could not copy text: ', err);
        });
    };
}

/**
 * File Upload Enhancement
 */
function initFileUpload() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        const wrapper = input.closest('.file-upload-area');
        if (!wrapper) return;
        
        // Drag and drop
        wrapper.addEventListener('dragover', (e) => {
            e.preventDefault();
            wrapper.classList.add('dragover');
        });
        
        wrapper.addEventListener('dragleave', () => {
            wrapper.classList.remove('dragover');
        });
        
        wrapper.addEventListener('drop', (e) => {
            e.preventDefault();
            wrapper.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateFileLabel(input);
            }
        });
        
        // File selection
        input.addEventListener('change', () => {
            updateFileLabel(input);
        });
    });
}

/**
 * Update file input label
 */
function updateFileLabel(input) {
    const wrapper = input.closest('.file-upload-area');
    let label = wrapper.querySelector('.file-name');
    
    if (!label) {
        label = document.createElement('p');
        label.className = 'file-name';
        label.style.cssText = 'margin-top: 10px; font-weight: 500; color: #1a73e8;';
        wrapper.appendChild(label);
    }
    
    if (input.files.length > 0) {
        label.textContent = input.files[0].name;
    }
}

/**
 * Show notification toast
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 24px;
        background: ${type === 'success' ? '#1e8e3e' : '#d93025'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/**
 * Show alert message
 */
function showAlert(message, type = 'error') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const form = document.querySelector('form');
    if (form) {
        form.insertBefore(alertDiv, form.firstChild);
    }
}

/**
 * Session-based Auth Management
 */
const Auth = {
    USER_KEY: 'studyflow_user',
    
    /**
     * Store user data
     */
    setUser(user) {
        if (user) {
            localStorage.setItem(this.USER_KEY, JSON.stringify(user));
        }
    },
    
    /**
     * Get stored user
     */
    getUser() {
        const user = localStorage.getItem(this.USER_KEY);
        return user ? JSON.parse(user) : null;
    },
    
    /**
     * Clear user data
     */
    clearUser() {
        localStorage.removeItem(this.USER_KEY);
    },
    
    /**
     * Login via API
     */
    async login(email, password) {
        try {
            const response = await fetch('/website/api/auth.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ email, password })
            });
            
            const data = await response.json();
            
            if (data.success && data.user) {
                this.setUser(data.user);
            }
            
            return data;
        } catch (error) {
            console.error('Login error:', error);
            return { error: 'Login failed' };
        }
    },
    
    /**
     * Logout
     */
    logout() {
        this.clearUser();
        window.location.href = '/website/auth/logout.php';
    }
};

/**
 * API Helper Functions (Session-based)
 */
const API = {
    /**
     * Get request headers
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json'
        };
    },
    
    /**
     * Make authenticated request
     */
    async request(url, options = {}) {
        const defaultOptions = {
            headers: this.getHeaders(),
            credentials: 'include'
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        try {
            const response = await fetch(url, mergedOptions);
            return response;
        } catch (error) {
            console.error('API request error:', error);
            throw error;
        }
    },
    
    /**
     * Get classes
     */
    async getClasses() {
        try {
            const response = await this.request('/website/api/get_classes.php');
            return await response.json();
        } catch (error) {
            console.error('Error fetching classes:', error);
            return { error: 'Failed to fetch classes' };
        }
    },
    
    /**
     * Get assignments
     */
    async getAssignments(classId = null) {
        try {
            let url = '/website/api/get_assignments.php';
            if (classId) {
                url += `?class_id=${classId}`;
            }
            const response = await this.request(url);
            return await response.json();
        } catch (error) {
            console.error('Error fetching assignments:', error);
            return { error: 'Failed to fetch assignments' };
        }
    },
    
    /**
     * Submit work
     */
    async submitWork(assignmentId, textContent) {
        try {
            const response = await this.request('/website/api/submit_work.php', {
                method: 'POST',
                body: JSON.stringify({
                    assignment_id: assignmentId,
                    text_content: textContent
                })
            });
            return await response.json();
        } catch (error) {
            console.error('Error submitting work:', error);
            return { error: 'Failed to submit work' };
        }
    }
};

/**
 * Utility Functions
 */
const Utils = {
    /**
     * Format date
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    },
    
    /**
     * Check if date is past
     */
    isPastDue(dateString) {
        return new Date(dateString) < new Date();
    },
    
    /**
     * Truncate text
     */
    truncate(text, length = 100) {
        if (text.length <= length) return text;
        return text.substring(0, length) + '...';
    },
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .file-upload-area.dragover {
        border-color: #1a73e8;
        background-color: #e8f0fe;
    }
    
    input.error,
    textarea.error,
    select.error {
        border-color: #d93025 !important;
    }
`;
document.head.appendChild(style);

// Export for global access
window.Auth = Auth;
window.API = API;
window.Utils = Utils;
window.showNotification = showNotification;
