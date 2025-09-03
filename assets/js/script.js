document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
    
    // Theme toggle functionality
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }
    
    // Show notifications
    const successNotification = document.getElementById('success-notification');
    const errorNotification = document.getElementById('error-notification');
    
    if (successNotification) {
        successNotification.classList.add('show');
        setTimeout(() => {
            successNotification.classList.remove('show');
            setTimeout(() => successNotification.remove(), 300);
        }, 3000);
    }
    
    if (errorNotification) {
        errorNotification.classList.add('show');
        setTimeout(() => {
            errorNotification.classList.remove('show');
            setTimeout(() => errorNotification.remove(), 300);
        }, 3000);
    }
    
    // Mobile menu toggle
    // const mobileMenuButton = document.getElementById('mobile-menu-button');
    // if (mobileMenuButton) {
    //     mobileMenuButton.addEventListener('click', function() {
    //         alert('Menu mobile s\'ouvrirait ici. Implémentez un menu latéral pour la vue mobile.');
    //     });
    // }
    
    // Category filtering
    const categoryItems = document.querySelectorAll('.category-item');
    categoryItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all categories
            categoryItems.forEach(cat => cat.classList.remove('active'));
            // Add active class to clicked category
            this.classList.add('active');
            
            // Filter products by category
            const category = this.getAttribute('data-category');
            filterProductsByCategory(category);
        });
    });
    
    // Prevent form submission when pressing enter on quantity input
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    });
    
    // Tab functionality for admin panel
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    if (tabButtons.length > 0) {
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-tab');
                
                // Update active button
                tabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                // Show active tab
                tabPanes.forEach(pane => pane.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });
    }
    
    // Form validation
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', function(e) {
            let isValid = true;
            const inputs = this.querySelectorAll('input[required], textarea[required]');
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = 'red';
                } else {
                    input.style.borderColor = '';
                }
            });
            
            // Email validation
            const emailInput = this.querySelector('input[type="email"]');
            if (emailInput && emailInput.value) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailInput.value)) {
                    isValid = false;
                    emailInput.style.borderColor = 'red';
                    alert('Please enter a valid email address');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    }
    
    // Image preview for admin product forms
    const imageInputs = document.querySelectorAll('input[type="file"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Check file size (max 2MB)
                if (file.size > 2097152) {
                    alert('File too large. Maximum size is 2MB');
                    this.value = '';
                    return;
                }
                
                // Check file type
                const allowedTypes = ['image/jpeg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPG and PNG files are allowed');
                    this.value = '';
                    return;
                }
            }
        });
    });
    
    // Live search functionality
    const searchForm = searchInput ? searchInput.closest('form') : null;
    
    if (searchInput && searchForm) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchForm.submit();
            }, 800);
        });
    }
});

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
}

function updateThemeIcon(theme) {
    const themeIcon = document.getElementById('theme-icon');
    if (themeIcon) {
        themeIcon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

function handleSearch() {
    const searchTerm = this.value.toLowerCase();
    const productItems = document.querySelectorAll('.product-item');
    
    productItems.forEach(item => {
        const title = item.querySelector('.product-title').textContent.toLowerCase();
        const description = item.querySelector('.product-description').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function filterProductsByCategory(category) {
    const productItems = document.querySelectorAll('.product-item');
    
    productItems.forEach(item => {
        if (category === 'all') {
            item.style.display = 'flex';
        } else {
            // This is a simple example - you would need to add data-category attributes to your products
            // For a real implementation, you would fetch products by category from the server
            const productCategory = item.getAttribute('data-category') || '';
            if (productCategory.includes(category)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        }
    });
}

// Quantity buttons functionality
function incrementQuantity(button) {
    const input = button.parentElement.querySelector('.quantity-input');
    const max = parseInt(input.getAttribute('max'));
    let value = parseInt(input.value);
    
    if (isNaN(value)) value = 0;
    if (value < max) {
        input.value = value + 1;
    }
}

function decrementQuantity(button) {
    const input = button.parentElement.querySelector('.quantity-input');
    const min = parseInt(input.getAttribute('min'));
    let value = parseInt(input.value);
    
    if (isNaN(value)) value = 1;
    if (value > min) {
        input.value = value - 1;
    }
}