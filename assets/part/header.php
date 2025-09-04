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
});
</script>

<style>
.bg-input-bg {
    background-color: var(--input-bg);
}
.border-color {
    border-color: var(--border);
}
.text-primary-color {
    color: var(--primary-color);
}
.text-secondary-light-dark {
    color: var(--text-secondary);
}
.text-light-dark {
    color: var(--text-primary);
}
.bg-card-bg {
    background-color: var(--card-bg);
}
</style>
