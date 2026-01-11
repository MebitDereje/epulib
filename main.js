/**
 * Main JavaScript file for Ethiopian Police University Library Management System
 * Handles client-side validation, UI interactions, and AJAX requests
 */

// Global configuration
const CONFIG = {
    baseUrl: window.location.origin,
    ajaxTimeout: 30000,
    debounceDelay: 300
};

// Utility functions
const Utils = {
    /**
     * Debounce function to limit function calls
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Show loading spinner
     */
    showLoading: function(element) {
        if (element) {
            element.innerHTML = '<div class="loading"></div>';
        }
    },

    /**
     * Show alert message
     */
    showAlert: function(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${this.getAlertIcon(type)}"></i>
            ${message}
        `;
        
        // Insert at top of main content
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }
    },

    /**
     * Get appropriate icon for alert type
     */
    getAlertIcon: function(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },

    /**
     * Format date for display
     */
    formatDate: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    },

    /**
     * Sanitize HTML to prevent XSS
     */
    sanitizeHtml: function(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }
};

// Form validation
const FormValidator = {
    /**
     * Validate required fields
     */
    validateRequired: function(field) {
        const value = field.value.trim();
        if (!value) {
            this.showFieldError(field, 'This field is required');
            return false;
        }
        this.clearFieldError(field);
        return true;
    },

    /**
     * Validate email format
     */
    validateEmail: function(field) {
        const email = field.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.showFieldError(field, 'Please enter a valid email address');
            return false;
        }
        this.clearFieldError(field);
        return true;
    },

    /**
     * Validate phone number
     */
    validatePhone: function(field) {
        const phone = field.value.trim();
        const phoneRegex = /^[\+]?[0-9\-\(\)\s]+$/;
        
        if (phone && !phoneRegex.test(phone)) {
            this.showFieldError(field, 'Please enter a valid phone number');
            return false;
        }
        this.clearFieldError(field);
        return true;
    },

    /**
     * Validate ISBN format
     */
    validateISBN: function(field) {
        const isbn = field.value.trim().replace(/[-\s]/g, '');
        const isbn10Regex = /^[0-9]{9}[0-9X]$/;
        const isbn13Regex = /^[0-9]{13}$/;
        
        if (isbn && !isbn10Regex.test(isbn) && !isbn13Regex.test(isbn)) {
            this.showFieldError(field, 'Please enter a valid ISBN (10 or 13 digits)');
            return false;
        }
        this.clearFieldError(field);
        return true;
    },

    /**
     * Validate password strength
     */
    validatePassword: function(field) {
        const password = field.value;
        
        if (password.length < 6) {
            this.showFieldError(field, 'Password must be at least 6 characters long');
            return false;
        }
        
        this.clearFieldError(field);
        return true;
    },

    /**
     * Show field error
     */
    showFieldError: function(field, message) {
        field.classList.add('error');
        
        // Remove existing error message
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Add new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    },

    /**
     * Clear field error
     */
    clearFieldError: function(field) {
        field.classList.remove('error');
        const errorMessage = field.parentNode.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    },

    /**
     * Validate entire form
     */
    validateForm: function(form) {
        let isValid = true;
        const fields = form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            const fieldType = field.type;
            const isRequired = field.hasAttribute('required');
            
            // Check required fields
            if (isRequired && !this.validateRequired(field)) {
                isValid = false;
            }
            
            // Type-specific validation
            if (field.value.trim()) {
                switch (fieldType) {
                    case 'email':
                        if (!this.validateEmail(field)) isValid = false;
                        break;
                    case 'tel':
                        if (!this.validatePhone(field)) isValid = false;
                        break;
                    case 'password':
                        if (!this.validatePassword(field)) isValid = false;
                        break;
                }
                
                // Custom validation based on field name or class
                if (field.name === 'isbn' || field.classList.contains('isbn')) {
                    if (!this.validateISBN(field)) isValid = false;
                }
            }
        });
        
        return isValid;
    }
};

// AJAX helper
const Ajax = {
    /**
     * Make AJAX request
     */
    request: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: CONFIG.ajaxTimeout
        };
        
        const config = { ...defaults, ...options };
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('AJAX request failed:', error);
                Utils.showAlert('Request failed. Please try again.', 'error');
                throw error;
            });
    },

    /**
     * GET request
     */
    get: function(url, params = {}) {
        const urlParams = new URLSearchParams(params);
        const fullUrl = urlParams.toString() ? `${url}?${urlParams}` : url;
        return this.request(fullUrl);
    },

    /**
     * POST request
     */
    post: function(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
};

// Search functionality
const Search = {
    /**
     * Initialize search functionality
     */
    init: function() {
        const searchInputs = document.querySelectorAll('.search-input');
        searchInputs.forEach(input => {
            input.addEventListener('input', Utils.debounce(this.handleSearch.bind(this), CONFIG.debounceDelay));
        });
    },

    /**
     * Handle search input
     */
    handleSearch: function(event) {
        const query = event.target.value.trim();
        const searchType = event.target.dataset.searchType || 'books';
        
        if (query.length >= 2) {
            this.performSearch(query, searchType);
        } else {
            this.clearResults();
        }
    },

    /**
     * Perform search
     */
    performSearch: function(query, type) {
        const resultsContainer = document.getElementById('search-results');
        if (resultsContainer) {
            Utils.showLoading(resultsContainer);
            
            Ajax.get(`api/search.php`, { q: query, type: type })
                .then(data => {
                    this.displayResults(data.results, resultsContainer);
                })
                .catch(error => {
                    resultsContainer.innerHTML = '<p>Search failed. Please try again.</p>';
                });
        }
    },

    /**
     * Display search results
     */
    displayResults: function(results, container) {
        if (results.length === 0) {
            container.innerHTML = '<p>No results found.</p>';
            return;
        }
        
        const html = results.map(result => {
            return `
                <div class="search-result-item">
                    <h4>${Utils.sanitizeHtml(result.title)}</h4>
                    <p>${Utils.sanitizeHtml(result.description)}</p>
                    <div class="result-actions">
                        <a href="${result.url}" class="btn btn-sm btn-primary">View Details</a>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = html;
    },

    /**
     * Clear search results
     */
    clearResults: function() {
        const resultsContainer = document.getElementById('search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }
    }
};

// Dashboard functionality
const Dashboard = {
    /**
     * Initialize dashboard
     */
    init: function() {
        this.loadStatistics();
        this.setupAutoRefresh();
    },

    /**
     * Load dashboard statistics
     */
    loadStatistics: function() {
        const statElements = {
            'total-books': 'api/stats.php?type=total_books',
            'borrowed-books': 'api/stats.php?type=borrowed_books',
            'total-users': 'api/stats.php?type=total_users',
            'pending-fines': 'api/stats.php?type=pending_fines'
        };

        Object.entries(statElements).forEach(([elementId, url]) => {
            const element = document.getElementById(elementId);
            if (element) {
                Ajax.get(url)
                    .then(data => {
                        element.textContent = data.count || data.amount || '0';
                    })
                    .catch(error => {
                        element.textContent = 'Error';
                    });
            }
        });
    },

    /**
     * Setup auto-refresh for dashboard
     */
    setupAutoRefresh: function() {
        // Refresh statistics every 5 minutes
        setInterval(() => {
            this.loadStatistics();
        }, 300000);
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!FormValidator.validateForm(form)) {
                event.preventDefault();
                Utils.showAlert('Please correct the errors in the form', 'error');
            }
        });
    });

    // Initialize search functionality
    Search.init();

    // Initialize dashboard if on dashboard page
    if (document.querySelector('.dashboard-stats')) {
        Dashboard.init();
    }

    // Initialize tooltips and other UI enhancements
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            // Tooltip functionality can be added here
        });
    });

    // Handle responsive navigation
    const navToggle = document.querySelector('.nav-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }
        }, 5000);
    });
});

// Export for use in other scripts
window.LibrarySystem = {
    Utils,
    FormValidator,
    Ajax,
    Search,
    Dashboard
};