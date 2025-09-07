<nav class="bottom-nav fixed bottom-0 left-0 right-0 md:hidden flex justify-around items-center py-2 px-1">
    <!-- Home -->
    <a href="index.php" class="nav-item text-secondary-light-dark hover:text-primary-color">
        <i class="fas fa-home nav-icon text-sm"></i>
        <span class="text-xs">Accueil</span>
    </a>

    <!-- Cart -->
    <!-- <a href="cart.php" class="nav-item active relative text-primary-color hover:text-primary-hover">
        <i class="fas fa-shopping-cart nav-icon text-sm"></i>
        <?php if ($cartCount > 0): ?>
        <span class="absolute -top-1 right-3 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-[10px]"><?php echo $cartCount; ?></span>
        <?php endif; ?>
        <span class="text-xs">Panier</span>
    </a> -->

    <!-- Orders -->
    <a href="orders.php" class="nav-item text-secondary-light-dark hover:text-primary-color">
        <i class="fas fa-receipt nav-icon text-sm"></i>
        <span class="text-xs">Commandes</span>
    </a>

    <!-- Checkout -->
    <a href="checkout.php" class="nav-item text-secondary-light-dark hover:text-primary-color">
        <i class="fas fa-check-circle nav-icon text-sm"></i>
        <span class="text-xs">Checkout</span>
    </a>

    <!-- Settings -->
    <div class="nav-item relative group text-secondary-light-dark hover:text-primary-color" id="settings-trigger">
        <i class="fas fa-cog nav-icon text-sm"></i>
        <span class="text-xs">Paramètres</span>

        <!-- Dropdown -->
        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 w-40 bg-card-bg rounded-lg shadow-xl border border-color opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
            <div class="py-2">
                <a href="admin/" class="flex items-center px-3 py-2 text-xs text-light-dark hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-user-shield mr-2 text-sm"></i> Admin
                </a>
                <button onclick="toggleTheme()" class="flex items-center w-full px-3 py-2 text-xs text-light-dark hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-moon mr-2 text-sm" id="mobile-theme-icon"></i>
                    <span id="mobile-theme-text">Mode Sombre</span>
                </button>
                <a href="#" class="flex items-center px-3 py-2 text-xs text-light-dark hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-question-circle mr-2 text-sm"></i> Aide
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
.bottom-nav {
    z-index: 1000;
    background: var(--bg-primary);
    border-top: 1px solid var(--border);
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    font-size: 0.75rem; /* smaller text */
    padding: 0.5rem;
    border-radius: 12px;
    transition: all 0.2s ease;
}

.nav-item:hover,
.nav-item.active {
    color: var(--primary-color);
    background: rgba(59, 130, 246, 0.1);
    transform: translateY(-2px);
}

.nav-icon {
    font-size: 1rem; /* smaller icons */
    margin-bottom: 2px;
}
</style>
