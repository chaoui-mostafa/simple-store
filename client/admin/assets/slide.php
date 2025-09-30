<?php 
    $current_page = basename($_SERVER['PHP_SELF']); 
    
    // Connexion à la base de données pour récupérer les données réelles
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=your_database", "username", "password");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Récupérer le nombre de produits
        $stmt_products = $pdo->prepare("SELECT COUNT(*) as total FROM products");
        $stmt_products->execute();
        $products_count = $stmt_products->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Récupérer le nombre de commandes en attente
        $stmt_orders = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
        $stmt_orders->execute();
        $orders_count = $stmt_orders->fetch(PDO::FETCH_ASSOC)['total'];
        
    } catch(PDOException $e) {
        // En cas d'erreur, utiliser des valeurs par défaut
        $products_count = 24;
        $orders_count = 12;
    }
    
    function getTotalCount($pdo, $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    $totalOrders = getTotalCount($pdo, 'orders');
    $totalProducts = getTotalCount($pdo, 'products');
    require_once '../../controllers/SettingsController.php';
    $settingsController = new SettingsController();
    $sidebar_stats = $settingsController->getSidebarStats();
    $store_settings = $settingsController->getStoreSettings();
?>

<!-- Mobile Menu Toggle Button -->
<!-- Sidebar Toggle Button (Mobile) -->
<button id="sidebarToggle" class="md:hidden fixed top-2 left-2 z-50 p-2 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-xl hover:shadow-2xl transition-all duration-300 group touch-manipulation
    border-2 border-white/20
    active:scale-95
    " style="min-width:36px; min-height:36px; width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
    <i class="fas fa-bars text-lg group-hover:scale-110 transition-transform"></i>
</button>

<!-- Overlay for mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden transition-opacity duration-300 touch-none"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-80 bg-gradient-to-br from-slate-900 via-blue-900 to-purple-900 transform -translate-x-full md:translate-x-0 transition-all duration-500 z-50 shadow-2xl">
    
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.15) 1px, transparent 0); background-size: 20px 20px;"></div>
    </div>
    
    <!-- Close button for mobile -->
    <button id="sidebarClose" class="md:hidden absolute top-6 right-6 p-3 text-white/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-200 group touch-manipulation">
        <i class="fas fa-times text-xl group-hover:rotate-90 transition-transform"></i>
    </button>
    
    <!-- Header -->
    <div class="relative p-6 md:p-8 border-b border-white/10">
        <div class="flex items-center space-x-4">
            <div class="relative">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center shadow-2xl group hover:scale-105 transition-transform duration-300">
                    <span class="text-white font-bold text-xl md:text-2xl">
                        <?php echo substr($_SESSION['admin_username'] ?? 'A', 0, 1); ?>
                    </span>
                    <div class="absolute -bottom-1 -right-1 w-3 h-3 md:w-4 md:h-4 bg-green-400 rounded-full border-2 border-slate-900"></div>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl md:text-2xl font-bold text-white mb-1 truncate"><?php echo htmlspecialchars($store_settings['store_name'] ?? 'Monster Store'); ?></h1>
                <p class="text-blue-200/80 text-sm font-medium truncate">Bienvenue, <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></p>
                <div class="flex items-center mt-1 space-x-2">
                    <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded-full text-xs font-medium border border-green-500/30">
                        <i class="fas fa-circle text-[6px] md:text-[8px] mr-1"></i> En ligne
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="relative mt-6 md:mt-8 p-4 space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto custom-scrollbar">
        <!-- Dashboard -->
        <a href="index.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 relative overflow-hidden touch-manipulation
           <?php echo ($current_page == 'index.php') ? 
           'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
           'text-blue-100/90 hover:text-white hover:bg-white/5 active:bg-white/10 active:scale-[0.98]'; ?>">
            <div class="relative">
                <i class="fas fa-grid-2 text-lg md:text-xl w-8 <?php echo ($current_page == 'index.php') ? 'text-cyan-400' : 'text-blue-400/80 group-hover:text-cyan-400'; ?>"></i>
            </div>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base">Tableau de bord</span>
            <?php if ($current_page == 'index.php'): ?>
                <div class="absolute right-4 w-2 h-2 bg-cyan-400 rounded-full animate-pulse"></div>
            <?php endif; ?>
        </a>

        <!-- Boutique -->
        <a href="../index.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 touch-manipulation
           <?php echo ($current_page == '../index.php') ? 
           'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
           'text-blue-100/90 hover:text-white hover:bg-white/5 active:bg-white/10 active:scale-[0.98]'; ?>">
            <i class="fas fa-store text-lg md:text-xl w-8 <?php echo ($current_page == '../index.php') ? 'text-emerald-400' : 'text-emerald-400/80 group-hover:text-emerald-400'; ?>"></i>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base">Boutique</span>
        </a>

        <!-- Produits -->
        <a href="products.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 touch-manipulation
           <?php echo ($current_page == 'products.php') ? 
           'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
           'text-blue-100/90 hover:text-white hover:bg-white/5 active:bg-white/10 active:scale-[0.98]'; ?>">
            <i class="fas fa-cube text-lg md:text-xl w-8 <?php echo ($current_page == 'products.php') ? 'text-amber-400' : 'text-amber-400/80 group-hover:text-amber-400'; ?>"></i>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base">Produits</span>
            <span class="ml-auto px-2 py-1 bg-amber-500/20 text-amber-400 rounded-full text-xs font-medium border border-amber-500/30 min-w-[2rem] flex items-center justify-center">
                <?php echo $sidebar_stats['products_count'] ?? $totalProducts; ?>
            </span>
        </a>

        <!-- Commandes -->
        <a href="orders.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 touch-manipulation
           <?php echo ($current_page == 'orders.php') ? 
           'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
           'text-blue-100/90 hover:text-white hover:bg-white/5 active:bg-white/10 active:scale-[0.98]'; ?>">
            <i class="fas fa-shopping-bag text-lg md:text-xl w-8 <?php echo ($current_page == 'orders.php') ? 'text-violet-400' : 'text-violet-400/80 group-hover:text-violet-400'; ?>"></i>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base">Commandes</span>
            <span class="ml-auto px-2 py-1 bg-violet-500/20 text-violet-400 rounded-full text-xs font-medium border border-violet-500/30 min-w-[2rem] flex items-center justify-center">
                <?php echo $sidebar_stats['orders_count'] ?? $totalOrders; ?>
            </span>
        </a>

        <!-- Administrateurs -->
        <a href="admins.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 touch-manipulation
           <?php echo ($current_page == 'admins.php') ? 
           'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
           'text-blue-100/90 hover:text-white hover:bg-white/5 active:bg-white/10 active:scale-[0.98]'; ?>">
            <i class="fas fa-users-cog text-lg md:text-xl w-8 <?php echo ($current_page == 'admins.php') ? 'text-rose-400' : 'text-rose-400/80 group-hover:text-rose-400'; ?>"></i>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base">Administrateurs</span>
        </a>

        <!-- Statistiques -->
        <a href="analytics.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 touch-manipulation
           <?php echo ($current_page == 'analytics.php') ? 
           'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
           'text-blue-100/90 hover:text-white hover:bg-white/5 active:bg-white/10 active:scale-[0.98]'; ?>">
            <i class="fas fa-chart-pie text-lg md:text-xl w-8 <?php echo ($current_page == 'analytics.php') ? 'text-teal-400' : 'text-teal-400/80 group-hover:text-teal-400'; ?>"></i>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base">Analytiques</span>
        </a>

        <!-- Paramètres -->
        <a href="settings.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 touch-manipulation
           <?php echo ($current_page == 'settings.php') ? 
           'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
           'text-blue-100/90 hover:text-white hover:bg-white/5 active:bg-white/10 active:scale-[0.98]'; ?>">
            <i class="fas fa-cogs text-lg md:text-xl w-8 <?php echo ($current_page == 'settings.php') ? 'text-slate-400' : 'text-slate-400/80 group-hover:text-slate-400'; ?>"></i>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base">Paramètres</span>
        </a>

        <!-- Séparateur -->
        <div class="border-t border-white/10 my-4"></div>

        <!-- Déconnexion -->
        <a href="logout.php" 
           class="group flex items-center py-3 md:py-4 px-4 md:px-6 rounded-2xl transition-all duration-300 bg-gradient-to-r from-rose-500/10 to-red-500/10 hover:from-rose-500/20 hover:to-red-500/20 border border-rose-500/20 hover:border-rose-500/30 active:scale-[0.98] touch-manipulation">
            <i class="fas fa-sign-out-alt text-lg md:text-xl w-8 text-rose-400/80 group-hover:text-rose-400 group-hover:animate-pulse"></i>
            <span class="ml-3 md:ml-4 font-semibold text-sm md:text-base text-rose-200 group-hover:text-white">Déconnexion</span>
        </a>
    </nav>
    
    <!-- Footer -->
    <div class="absolute bottom-0 w-full p-4 border-t border-white/10 bg-black/20 backdrop-blur-lg">
        <div class="text-center">
            <div class="flex items-center justify-center space-x-2 mb-2">
                <div class="w-6 h-6 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center">
                    <i class="fas fa-dragon text-white text-xs"></i>
                </div>
                <span class="text-white font-semibold text-base">Monster Store</span>
            </div>
            <p class="text-blue-200/60 text-xs">Admin v2.0 • eTwin Technology</p>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: linear-gradient(to bottom, #3b82f6, #8b5cf6);
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(to bottom, #2563eb, #7c3aed);
}

/* Animations */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
}

.floating {
    animation: float 3s ease-in-out infinite;
}

/* Glass effect amélioré */
.glass-effect {
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
}

/* Hover effects avancés */
.hover-lift {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

/* Gradient text */
.gradient-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Pulse animation */
.pulse-slow {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Correction pour le body adaptatif */
body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
    min-height: 100vh;
}

/* Correction pour le contenu principal */
.main-content {
    margin-left: 0;
    transition: margin-left 0.5s ease;
    min-height: 100vh;
    width: 100%;
}

/* Sur les écrans moyens et grands */
@media (min-width: 768px) {
    .main-content {
        margin-left: 320px; /* Largeur de la sidebar */
    }
}

/* Correction pour éviter que la sidebar cache le contenu */
@media (max-width: 767px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding-top: 0;
    }
    
    /* S'assurer que le contenu n'est pas caché par la sidebar */
    #sidebar {
        z-index: 9999;
        width: 85%;
        max-width: 300px;
    }
    
    /* Overlay pour mobile amélioré */
    #sidebarOverlay {
        z-index: 9998;
    }
}

/* Amélioration de la responsivité pour très petits écrans */
@media (max-width: 480px) {
    #sidebar {
        width: 90%;
        max-width: 280px;
    }
    
    .main-content {
        padding-top: 0;
    }
    
    /* Boutons mobiles optimisés */
    #sidebarToggle {
        top: 1rem;
        left: 1rem;
        padding: 0.75rem;
        min-width: 3rem;
        min-height: 3rem;
    }
    
    #sidebarClose {
        top: 1rem;
        right: 1rem;
        padding: 0.5rem;
        min-width: 2.5rem;
        min-height: 2.5rem;
    }
    
    /* Navigation items optimisés pour mobile */
    .sidebar nav a {
        padding: 0.75rem 1rem !important;
        min-height: 3.5rem;
    }
    
    .sidebar nav a span {
        font-size: 0.875rem;
    }
    
    .sidebar nav a i {
        font-size: 1.125rem;
    }
}

/* Correction pour les sections comme les produits */
.products-section, .main-section {
    position: relative;
    z-index: 1;
    width: 100%;
}

/* Assurer que le contenu principal reste visible */
.content-wrapper {
    position: relative;
    z-index: 10;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
}

/* Touch optimization */
.touch-manipulation {
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

.touch-manipulation:active {
    transform: scale(0.95);
    transition: transform 0.1s ease;
}

/* Prevent text selection on buttons */
button, a {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}

/* Mobile-specific optimizations */
@media (max-width: 767px) {
    /* Larger touch targets */
    .sidebar nav a {
        min-height: 3.5rem;
    }
    
    /* Better spacing for mobile */
    .sidebar .relative.p-6 {
        padding: 1rem;
    }
    
    /* Optimize font sizes for mobile */
    .sidebar h1 {
        font-size: 1.25rem;
    }
    
    .sidebar p {
        font-size: 0.875rem;
    }
}

/* Very small screens */
@media (max-width: 360px) {
    #sidebar {
        width: 95%;
        max-width: 260px;
    }
    
    .sidebar nav a {
        padding: 0.5rem 0.75rem !important;
        min-height: 3rem;
    }
    
    .sidebar nav a span {
        font-size: 0.8rem;
    }
    
    .sidebar nav a i {
        font-size: 1rem;
    }
}

/* Safe area insets for notch phones */
@supports(padding: max(0px)) {
    .main-content {
        padding-left: max(0px, env(safe-area-inset-left));
        padding-right: max(0px, env(safe-area-inset-right));
    }
    
    #sidebarToggle {
        left: max(1rem, env(safe-area-inset-left));
    }
}
</style>

<!-- JavaScript for responsive sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.querySelector('.main-content');
    
    // Animation d'entrée de la sidebar
    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('shadow-3xl');
        sidebarOverlay.classList.remove('hidden');
        setTimeout(() => sidebarOverlay.classList.remove('opacity-0'), 10);
        document.body.style.overflow = 'hidden';
        
        // Ajuster le contenu principal sur mobile
        if (window.innerWidth < 768) {
            if (mainContent) {
                mainContent.style.transform = 'translateX(20px)';
                mainContent.style.opacity = '0.8';
            }
        }
        
        // Animation des éléments de la sidebar
        const navItems = document.querySelectorAll('.sidebar nav a');
        navItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                item.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, index * 50 + 150);
        });
    }
    
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('shadow-3xl');
        sidebarOverlay.classList.add('opacity-0');
        setTimeout(() => {
            sidebarOverlay.classList.add('hidden');
            // Réinitialiser le contenu principal
            if (mainContent) {
                mainContent.style.transform = '';
                mainContent.style.opacity = '';
            }
        }, 300);
        document.body.style.overflow = '';
    }
    
    // Toggle sidebar on button click
    sidebarToggle.addEventListener('click', openSidebar);
    sidebarToggle.addEventListener('touchstart', function(e) {
        e.preventDefault();
        openSidebar();
    });
    
    // Close sidebar on close button click
    sidebarClose.addEventListener('click', closeSidebar);
    sidebarClose.addEventListener('touchstart', function(e) {
        e.preventDefault();
        closeSidebar();
    });
    
    // Close sidebar on overlay click
    sidebarOverlay.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('touchstart', function(e) {
        e.preventDefault();
        closeSidebar();
    });
    
    // Close sidebar when a navigation link is clicked (on mobile)
    const navLinks = document.querySelectorAll('.sidebar nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                setTimeout(closeSidebar, 300);
            }
        });
        
        // Touch optimization
        link.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        link.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });
    
    // Close sidebar on Escape key press
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
            closeSidebar();
        }
    });
    
    // Adjust sidebar behavior on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
            document.body.style.overflow = '';
            // Réinitialiser le contenu principal
            if (mainContent) {
                mainContent.style.transform = '';
                mainContent.style.opacity = '';
            }
        } else {
            if (sidebar.classList.contains('-translate-x-full')) {
                // Si la sidebar est fermée sur mobile, s'assurer que le contenu est visible
                if (mainContent) {
                    mainContent.style.transform = '';
                    mainContent.style.opacity = '';
                }
            }
        }
    });

    // Add hover effects to navigation items (desktop only)
    if (window.innerWidth >= 768) {
        navLinks.forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(8px) scale(1.02)';
            });
            
            link.addEventListener('mouseleave', function() {
                if (!this.classList.contains('bg-gradient-to-r')) {
                    this.style.transform = 'translateX(0) scale(1)';
                }
            });
        });
    }

    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.getBoundingClientRect().left - radius}px`;
        circle.style.top = `${event.clientY - button.getBoundingClientRect().top - radius}px`;
        circle.classList.add('ripple');
        
        const ripple = button.getElementsByClassName('ripple')[0];
        if (ripple) ripple.remove();
        
        button.appendChild(circle);
    }
    
    const buttons = document.querySelectorAll('#sidebarToggle, #sidebarClose');
    buttons.forEach(button => {
        button.addEventListener('click', createRipple);
    });
});

// Add ripple effect styles
const style = document.createElement('style');
style.textContent = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    #sidebarToggle, #sidebarClose {
        position: relative;
        overflow: hidden;
    }
    
    /* Mobile touch improvements */
    @media (max-width: 767px) {
        #sidebarToggle, #sidebarClose {
            min-width: 44px;
            min-height: 44px;
        }
        
        .sidebar nav a {
            min-height: 44px;
        }
    }
`;
document.head.appendChild(style);
</script>