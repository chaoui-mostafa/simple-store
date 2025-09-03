<nav class="bottom-nav md:hidden">
    <a href="index.php" class="nav-item text-secondary-light-dark hover:text-primary-color">
        <i class="fas fa-home nav-icon"></i>
        <span>Accueil</span>
    </a>
    <a href="cart.php" class="nav-item active relative text-primary-color hover:text-primary-hover">
        <i class="fas fa-shopping-cart nav-icon"></i>
        <?php if ($cartCount > 0): ?>
        <span class="absolute -top-1 right-3 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-xs"><?php echo $cartCount; ?></span>
        <?php endif; ?>
        <span>Panier</span>
    </a>
   
    
    <a href="orders.php" class="nav-item text-secondary-light-dark hover:text-primary-color">
        <i class="fas fa-receipt nav-icon"></i>
        <span>Commandes</span>
    </a>
     <a href="checkout.php" class="nav-item text-secondary-light-dark hover:text-primary-color">
           <i class="fas fa-check-circle nav-icon"></i>
           <span>Checkout</span>
       </a>
    <!-- Settings Dropdown Trigger -->
    <div class="nav-item relative group text-secondary-light-dark hover:text-primary-color" id="settings-trigger">
        <i class="fas fa-cog nav-icon"></i>
        <span>Paramètres</span>
        
        <!-- Dropdown Menu -->
        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 w-48 bg-card-bg rounded-lg shadow-xl border border-color opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
            <div class="py-2">
                <!-- Admin Login -->
                <a href="../admin/login.php" class="flex items-center px-4 py-2 text-sm text-light-dark hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-user-shield mr-3"></i>
                    Admin Login
                </a>
                
                <!-- Dark Mode Toggle -->
                <button onclick="toggleTheme()" class="flex items-center w-full px-4 py-2 text-sm text-light-dark hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-moon mr-3" id="mobile-theme-icon"></i>
                    <span id="mobile-theme-text">Mode Sombre</span>
                </button>
                
                <!-- Language Selector -->
                <div class="px-4 py-2 text-sm text-light-dark">
                    <span class="flex items-center">
                        <i class="fas fa-language mr-3"></i>
                        Langue
                    </span>
                    <div class="mt-1 ml-6 space-y-1">
                        <button class="block w-full text-left text-xs py-1 hover:text-blue-600 dark:hover:text-blue-400">Français</button>
                        <button class="block w-full text-left text-xs py-1 hover:text-blue-600 dark:hover:text-blue-400">English</button>
                        <button class="block w-full text-left text-xs py-1 hover:text-blue-600 dark:hover:text-blue-400">العربية</button>
                    </div>
                </div>
                
                <!-- Notifications -->
                <a href="#" class="flex items-center px-4 py-2 text-sm text-light-dark hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-bell mr-3"></i>
                    Notifications
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">3</span>
                </a>
                
                <!-- Help & Support -->
                <a href="#" class="flex items-center px-4 py-2 text-sm text-light-dark hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-question-circle mr-3"></i>
                    Aide
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
// Theme management for mobile
function updateMobileThemeIcon(theme) {
    const themeIcon = document.getElementById('mobile-theme-icon');
    const themeText = document.getElementById('mobile-theme-text');
    
    if (themeIcon && themeText) {
        if (theme === 'light') {
            themeIcon.className = 'fas fa-moon mr-3';
            themeText.textContent = 'Mode Sombre';
        } else {
            themeIcon.className = 'fas fa-sun mr-3';
            themeText.textContent = 'Mode Clair';
        }
    }
}

// Initialize mobile theme on load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    updateMobileThemeIcon(savedTheme);
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const settingsTrigger = document.getElementById('settings-trigger');
    const isClickInside = settingsTrigger.contains(event.target);
    
    if (!isClickInside) {
        const dropdown = settingsTrigger.querySelector('div');
        dropdown.classList.remove('opacity-100', 'visible');
        dropdown.classList.add('opacity-0', 'invisible');
    }
});

// Prevent dropdown from closing when clicking inside it
document.querySelectorAll('#settings-trigger div').forEach(element => {
    element.addEventListener('click', function(event) {
        event.stopPropagation();
    });
});
</script>

<style>
.bottom-nav {
    z-index: 1000;
    background: var(--bg-primary);
    border-top: 1px solid var(--border);
}

.nav-item {
    color: var(--text-secondary);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    font-size: 0.8rem;
    padding: 0.75rem;
    border-radius: 16px;
    transition: all 0.3s ease;
}

.nav-item:hover, .nav-item.active {
    color: var(--primary-color);
    background: rgba(59, 130, 246, 0.15);
    transform: translateY(-2px);
}

#settings-trigger {
    cursor: pointer;
}

#settings-trigger div {
    box-shadow: 0 10px 25px var(--shadow-hover);
    bottom: calc(100% + 10px);
    background: var(--card-bg);
    border: 1px solid var(--border);
}

#settings-trigger div:before {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    width: 12px;
    height: 12px;
    background: var(--card-bg);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    transform: translateX(-50%) rotate(45deg);
}

.nav-icon {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
}

.group:hover .group-hover\:opacity-100 {
    opacity: 1;
}

.group:hover .group-hover\:visible {
    visibility: visible;
}

/* Add these CSS custom properties if not already defined */
.bg-card-bg {
    background-color: var(--card-bg);
}

.border-color {
    border-color: var(--border);
}

.text-primary-color {
    color: var(--primary-color);
}

.text-primary-hover {
    color: var(--primary-hover);
}
</style>