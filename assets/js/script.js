// assets/js/script.js

// Global variables
let autoSaveTimer;

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize date pickers
    initDatePickers();
    
    // Initialize search functionality
    initSearch();
    
    // Load dashboard data if on dashboard
    if (document.getElementById('dashboard-stats')) {
        loadDashboardData();
    }
});

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.dataset.tooltip;
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
}

function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Form validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
}

function validateForm(e) {
    const form = e.target;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            highlightField(input, 'This field is required');
        } else {
            removeHighlight(input);
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                isValid = false;
                highlightField(input, 'Please enter a valid email address');
            }
        }
        
        // Phone validation
        if (input.name === 'phone' && input.value) {
            const phoneRegex = /^[\d\s-()+]{10,}$/;
            if (!phoneRegex.test(input.value.replace(/\D/g, ''))) {
                isValid = false;
                highlightField(input, 'Please enter a valid phone number');
            }
        }
    });
    
    if (!isValid) {
        e.preventDefault();
    }
}

function highlightField(field, message) {
    field.classList.add('error');
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Add error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

function removeHighlight(field) {
    field.classList.remove('error');
    const error = field.parentNode.querySelector('.error-message');
    if (error) {
        error.remove();
    }
}

// Date pickers
function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Set default value to today if empty
        if (!input.value && input.classList.contains('default-today')) {
            const today = new Date().toISOString().split('T')[0];
            input.value = today;
        }
    });
}

// Search functionality
function initSearch() {
    const searchInput = document.getElementById('live-search');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(performSearch, 500));
    }
}

function performSearch(e) {
    const searchTerm = e.target.value;
    const table = document.querySelector('.data-table tbody');
    
    if (table) {
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Load dashboard data with AJAX
function loadDashboardData() {
    fetch('api/dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            updateDashboardStats(data);
        })
        .catch(error => console.error('Error loading dashboard data:', error));
}

function updateDashboardStats(data) {
    document.getElementById('total-members').textContent = data.total_members;
    document.getElementById('attendance-today').textContent = data.today_attendance;
    document.getElementById('monthly-donations').textContent = formatCurrency(data.monthly_donations);
    document.getElementById('upcoming-events').textContent = data.upcoming_events;
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Print functionality
function printSection(sectionId) {
    const printContent = document.getElementById(sectionId).innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Export to CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const rowData = [];
        const cols = row.querySelectorAll('td, th');
        cols.forEach(col => {
            rowData.push('"' + col.textContent.trim() + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Chart initialization (if using Chart.js)
function initCharts() {
    if (typeof Chart !== 'undefined') {
        // Attendance chart
        const attendanceCtx = document.getElementById('attendance-chart');
        if (attendanceCtx) {
            new Chart(attendanceCtx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Attendance',
                        data: [120, 135, 142, 158],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)'
                    }]
                }
            });
        }
        
        // Donations chart
        const donationsCtx = document.getElementById('donations-chart');
        if (donationsCtx) {
            new Chart(donationsCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr'],
                    datasets: [{
                        label: 'Donations',
                        data: [5000, 6200, 5800, 7100],
                        backgroundColor: '#27ae60'
                    }]
                }
            });
        }
    }
}

// Notification system
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Confirmation dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S for save
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const saveButton = document.querySelector('button[type="submit"]');
        if (saveButton) {
            saveButton.click();
        }
    }
    
    // Esc to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }
});

// Auto-save functionality
function enableAutoSave(formId, callback) {
    const form = document.getElementById(formId);
    if (form) {
        form.addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                const formData = new FormData(form);
                callback(formData);
                showNotification('Draft saved', 'info');
            }, 3000);
        });
    }
}

// Dark mode toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// Load user preferences
function loadUserPreferences() {
    const darkMode = localStorage.getItem('darkMode') === 'true';
    if (darkMode) {
        document.body.classList.add('dark-mode');
    }
}

// Call load preferences
loadUserPreferences();

// Export functions for use in HTML
window.printSection = printSection;
window.exportToCSV = exportToCSV;
window.showNotification = showNotification;
window.confirmAction = confirmAction;
window.toggleDarkMode = toggleDarkMode;