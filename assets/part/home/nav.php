<?php
// Check if user is admin for admin link
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>

<!-- Header -->
<header class="sticky-header">
    <div class="container">
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center mr-3">
                    <span class="text font-bold text-xl">M</span>
                </div>
                <h1 class="text-xl font-bold dark:text">Monster Store</h1>
            </div>
            
            <div class="hidden md:flex items-center space-x-6">
                <a href="index.php" class="text-gray-700 hover:text-blue-600 transition-colors">Accueil</a>
                <a href="checkout.php" class="text-gray-700 hover:text-blue-600 transition-colors">Checkout</a>
                <!-- <a href="categories.php" class="text-gray-700 hover:text-blue-600 transition-colors">Catégories</a> -->
                <a href="orders.php" class="text-gray-700 hover:text-blue-600 transition-colors">Mes Commandes</a>
                
            </div>
            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="theme-toggle">
                    <i id="theme-icon" class="fas fa-moon"></i>
                </button>
                
                <a href="cart.php" class="relative">
                    <i class="fas fa-shopping-cart text-xl dark:text-black-300"></i>
                    <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="hidden md:block">
                    <i class="fas fa-user-circle text-xl dark:text-black-300"></i>
                </a>
                <?php else: ?>
                <a href="login.php" class="hidden md:block">
                    <i class="fas fa-sign-in-alt text-xl dark:text-black-300"></i>
                </a>
                <?php endif; ?>
                 -->
                <!-- <button class="md:hidden p-2 rounded-full hover:bg-black-100 dark:hover:bg-black-700" id="mobile-menu-button">
                    <i class="fas fa-bars text-black-700 dark:text-black-300"></i>
                </button> -->
            </div>
        </div>
        
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="search-input" placeholder="Rechercher des produits..." class="search-input">
        </div>
    </div>
</header>

<!-- Categories -->
<div class="container">
    <div class="category-scroll">
        <a href="#" class="category-item active" data-category="all">Tous</a>
        <a href="#" class="category-item" data-category="new">Nouveautés</a>
        <a href="#" class="category-item" data-category="popular">Populaires</a>
        <a href="#" class="category-item" data-category="clothing">Vêtements</a>
        <a href="#" class="category-item" data-category="electronics">Électronique</a>
        <a href="#" class="category-item" data-category="home">Maison</a>
        <a href="#" class="category-item" data-category="sports">Sport</a>
    </div>
</div>