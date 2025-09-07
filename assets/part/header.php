<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

// Get cart count
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

// Handle search functionality
$searchResults = [];
$searchQuery = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    
    if (!empty($searchQuery)) {
        try {
            $db = new Database();
            $conn = $db->connect();
            
            // Prepare SQL search query
            $stmt = $conn->prepare("
                SELECT * FROM products 
                WHERE name LIKE :search 
                   OR description LIKE :search 
                   OR category LIKE :search
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            
            $searchParam = "%$searchQuery%";
            $stmt->bindParam(':search', $searchParam);
            $stmt->execute();
            
            $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Search error: " . $e->getMessage());
        }
    }
}

// Check current theme
$currentTheme = 'light';
if (isset($_COOKIE['theme'])) {
    $currentTheme = $_COOKIE['theme'];
} elseif (isset($_SESSION['theme'])) {
    $currentTheme = $_SESSION['theme'];
}
?>

<header class="sticky-header">
    <div class="container">
        <div class="flex items-center justify-between py-4">
            <!-- Logo + Title -->
            <div class="flex items-center">
                <a href="index.php" class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center mr-3 overflow-hidden">
                        <img src="../../assets/images/logo/logo.jpg" 
                             alt="Logo" 
                             class="w-full h-full object-cover" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <span class="text-white font-bold text-xl hidden">M</span>
                    </div>
                </a>
                <h1 class="text-xl font-bold text-light-dark">Monster Store</h1>
            </div>
            
            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="index.php" class="text-secondary-light-dark hover:text-primary-color transition-colors">Accueil</a>
                <a href="checkout.php" class="text-secondary-light-dark hover:text-primary-color transition-colors">Checkout</a>
                <a href="orders.php" class="text-secondary-light-dark hover:text-primary-color transition-colors">Mes Commandes</a>
                
                <!-- Theme Toggle -->
                <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas <?php echo $currentTheme === 'dark' ? 'fa-sun' : 'fa-moon'; ?> text-xl text-secondary-light-dark"></i>
                </button>
            </div>
            
            <!-- Cart -->
            <div class="flex items-center gap-4">
                <a href="cart.php" class="relative p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-shopping-cart text-xl text-secondary-light-dark"></i>
                    <?php if ($cartCount > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        <?php echo $cartCount > 9 ? '9+' : $cartCount; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <!-- Search Form -->
        <form method="GET" action="search.php" class="search-container relative mb-4">
            <i class="fas fa-search search-icon absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-light-dark"></i>
            <input 
                type="text" 
                name="search" 
                placeholder="Rechercher des produits..." 
                class="search-input pl-10 pr-4 py-3 w-full border border-color rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-input-bg text-light-dark"
                value="<?php echo htmlspecialchars($searchQuery); ?>"
            >
            <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 bg-blue-600 text-white p-1 rounded-md hover:bg-blue-700">
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>
        
        <!-- Search Results -->
        <?php if (basename($_SERVER['PHP_SELF']) === 'search.php' && !empty($searchResults)): ?>
        <div class="search-results bg-card-bg border border-color rounded-lg shadow-lg mt-2 absolute left-4 right-4 z-50 max-h-96 overflow-y-auto">
            <div class="p-4">
                <h3 class="font-semibold text-light-dark mb-3">Résultats de recherche (<?php echo count($searchResults); ?>)</h3>
                <div class="space-y-3">
                    <?php foreach ($searchResults as $product): ?>
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <?php if (!empty($product['image'])): ?>
                        <img src="../assets/images/<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="w-12 h-12 object-cover rounded-md mr-3">
                        <?php else: ?>
                        <div class="w-12 h-12 bg-gray-200 dark:bg-gray-600 rounded-md flex items-center justify-center mr-3">
                            <i class="fas fa-image text-gray-400"></i>
                        </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <h4 class="font-medium text-light-dark"><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p class="text-sm text-secondary-light-dark"><?php echo number_format($product['price'], 2); ?> DH</p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    
    if (searchInput) {
        // Focus search input on '/' key press
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                searchInput.focus();
            }
        });
        
        // Clear search when pressing Escape
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.blur();
            }
        });

        // Add search hint
        searchInput.setAttribute('title', 'Appuyez sur / pour rechercher');
    }
    
    // Theme toggle functionality
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            const themeIcon = themeToggle.querySelector('i');
            
            if (isDark) {
                html.classList.remove('dark');
                document.body.classList.remove('dark');
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                setTheme('light');
            } else {
                html.classList.add('dark');
                document.body.classList.add('dark');
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                setTheme('dark');
            }
        });
    }
    
    // Set theme preference
    function setTheme(theme) {
        // Save to session
        fetch('set_theme.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'theme=' + theme
        });
        
        // Save to cookie
        document.cookie = 'theme=' + theme + '; path=/; max-age=' + (365 * 24 * 60 * 60);
    }
    
    // Initialize theme
    function initTheme() {
        const savedTheme = getCookie('theme') || 'light';
        const html = document.documentElement;
        const themeToggle = document.getElementById('theme-toggle');
        
        if (savedTheme === 'dark') {
            html.classList.add('dark');
            document.body.classList.add('dark');
            if (themeToggle) {
                const themeIcon = themeToggle.querySelector('i');
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
        }
    }
    
    // Get cookie value
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    }
    
    // Initialize theme on page load
    initTheme();
});
</script>

<style>
.bg-input-bg {
    background-color: var(--input-bg, #f9fafb);
}
.border-color {
    border-color: var(--border, #e5e7eb);
}
.text-primary-color {
    color: var(--primary-color, #3b82f6);
}
.text-secondary-light-dark {
    color: var(--text-secondary, #6b7280);
}
.text-light-dark {
    color: var(--text-primary, #1f2937);
}
.bg-card-bg {
    background-color: var(--card-bg, #ffffff);
}

/* Dark mode styles */
.dark .bg-input-bg {
    background-color: #374151;
}
.dark .border-color {
    border-color: #4b5563;
}
.dark .text-secondary-light-dark {
    color: #d1d5db;
}
.dark .text-light-dark {
    color: #f9fafb;
}
.dark .bg-card-bg {
    background-color: #1f2937;
}
.dark .hover\:bg-gray-100:hover {
    background-color: #374151;
}
</style>