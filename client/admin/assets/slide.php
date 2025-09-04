<?php 
    $current_page = basename($_SERVER['PHP_SELF']); 
?>

<!-- Sidebar -->
<div class="sidebar shadow-xl fixed top-0 left-0 h-full w-64 bg-gradient-to-b from-blue-800 to-blue-900">
    <!-- Header -->
    <div class="p-6 border-b border-blue-400/20">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center glass-effect">
                <span class="text-white font-bold text-lg">
                    <?php echo substr($_SESSION['admin_username'], 0, 1); ?>
                </span>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">Panneau Admin</h1>
                <p class="text-blue-100 text-sm">Bienvenue, <?php echo $_SESSION['admin_username']; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="mt-6 p-2 space-y-1">
        <a href="index.php" 
           class="flex items-center py-3 px-4 rounded-lg transition-all duration-200 
           <?php echo ($current_page == 'index.php') ? 'text-white bg-white/15' : 'text-blue-100 hover:text-white hover:bg-white/10'; ?>">
            <i class="fas fa-home mr-3"></i> Home
        </a>
        
        <a href="../index.php" 
           class="flex items-center py-3 px-4 rounded-lg transition-all duration-200 
           <?php echo ($current_page == '../index.php') ? 'text-white bg-white/15' : 'text-blue-100 hover:text-white hover:bg-white/10'; ?>">
            <i class="fas fa-store mr-3"></i> Retour à la boutique
        </a>
        
        <a href="products.php" 
           class="flex items-center py-3 px-4 rounded-lg transition-all duration-200 
           <?php echo ($current_page == 'products.php') ? 'text-white bg-white/15' : 'text-blue-100 hover:text-white hover:bg-white/10'; ?>">
            <i class="fas fa-box mr-3"></i> Produits
        </a>
        
        <a href="orders.php" 
           class="flex items-center py-3 px-4 rounded-lg transition-all duration-200 
           <?php echo ($current_page == 'orders.php') ? 'text-white bg-white/15' : 'text-blue-100 hover:text-white hover:bg-white/10'; ?>">
            <i class="fas fa-shopping-cart mr-3"></i> Commandes
        </a>
        
        <a href="admins.php" 
           class="flex items-center py-3 px-4 rounded-lg transition-all duration-200 
           <?php echo ($current_page == 'admins.php') ? 'text-white bg-white/15' : 'text-blue-100 hover:text-white hover:bg-white/10'; ?>">
            <i class="fas fa-users mr-3"></i> Administrateurs
        </a>
        
        <a href="logout.php" 
           class="flex items-center py-3 px-4 rounded-lg transition-all duration-200 
           <?php echo ($current_page == 'logout.php') ? 'text-white bg-white/15' : 'text-blue-100 hover:text-white hover:bg-white/10'; ?>">
            <i class="fas fa-sign-out-alt mr-3"></i> Déconnexion
        </a>
    </nav>
    
    <!-- Footer -->
    <div class="absolute bottom-0 w-full p-4 border-t border-blue-400/20">
        <div class="text-center text-blue-200 text-sm">
            <p>Monster Store Admin v1.0 by eTwin Technology</p>
        </div>
    </div>
</div>
