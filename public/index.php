<?php
session_start();
require_once '../config/db.php';
require_once '../controllers/ProductController.php';
require_once '../controllers/CartController.php';
$db = new Database();
$pdo = $db->connect();

$productController = new ProductController($pdo);
$cartController = new CartController();
$products = $productController->getAllProducts();
$cartCount = $cartController->getCartCount();

// Handle add to cart requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
    $quantity = isset($_POST['quantity']) ? filter_var($_POST['quantity'], FILTER_SANITIZE_NUMBER_INT) : 1;
    
    if ($cartController->addToCart($product_id, $quantity)) {
        $_SESSION['success'] = 'Produit ajouté au panier !';
        $cartCount = $cartController->getCartCount(); // Update counter
        header('Location: index.php');
        exit();
    } else {
        $_SESSION['error'] = 'Échec de l\'ajout au panier';
    }
}

// Display success/error messages
$successMessage = '';
$errorMessage = '';
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Filter out products with 0 stock
$availableProducts = array_filter($products, function($product) {
    return $product['quantity'] > 0;
});
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monster Store</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
        /* Add CSS to hide products with 0 stock */
        .product-item[data-stock="0"] {
            display: none !important;
        }
        
        /* Responsive improvements */
        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .product-item {
            display: flex;
            flex-direction: column;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            /* background: white; */
        }
        
        @media (max-width: 640px) {
            .product-list {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .add-to-cart-btn span {
                display: inline !important;
            }
        }
    </style>
</head>
<body class="antialiased">
    <!-- Notification Messages -->
    <?php if ($successMessage): ?>
    <div class="notification success" id="success-notification">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($successMessage); ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
    <div class="notification error" id="error-notification">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($errorMessage); ?></span>
    </div>
    <?php endif; ?>

    <!-- Include Navigation -->
    <?php include '../assets/part/home/nav.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Hero Section -->
            <div class="hero-pattern">
                <div class="max-w-md mx-auto md:mx-0">
                    <h2 class="text-2xl font-bold mb-3">Collection Été 2023</h2>
                    <p class="mb-5">Découvrez les dernières tendances et profitez de -30% sur des articles sélectionnés.</p>
                    <a href="#products" class="bg text-blue-600 font-semibold py-2 px-6 rounded-full hover:bg-blue-50 transition-colors inline-block dark:bg-gray-800 dark:text dark:hover:bg-gray-700">Acheter maintenant</a>
                </div>
            </div>

            <h2 class="text-xl font-bold mb-4 dark:text" id="products">Nos Produits</h2>
            
            <?php if (empty($availableProducts)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400">Aucun produit disponible pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="product-list">
                    <?php foreach ($availableProducts as $index => $product): 
                        $stockBadgeClass = '';
                        if ($product['quantity'] > 10) {
                            $stockBadgeClass = 'stock-high';
                        } elseif ($product['quantity'] > 0) {
                            $stockBadgeClass = 'stock-medium';
                        } else {
                            $stockBadgeClass = 'stock-low';
                        }
                    ?>
                        <div class="product-item" data-category="<?php echo htmlspecialchars($product['category'] ?? 'all'); ?>" data-stock="<?php echo $product['quantity']; ?>">
                            <div class="product-image-container">
                                <img src="../assets/images/<?php echo htmlspecialchars($product['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="product-image"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIyNSIgdmlld0JveD0iMCAwIDMwMCAyMjUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjI1IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0xMTIuNSA4NC41QzExMi41IDc4LjAyODQgMTE3Ljc4MiA3Mi43NSAxMjQuMjUgNzIuNzVDMTMwLjcxOCA3Mi43NSAxMzYgNzguMDI4NCAxMzYgODQuNUMxMzYgOTAuOTcxNiAxMzAuNzE4IDk2LjI1IDEyNC4yNSA5Ni4yNUMxMTcuNzgyIDk2LjI1IDExMi41IDkwLjk3MTYgMTEyLjUgODQuNVoiIGZpbGw9IiNEOEQ4RDgiLz4KPHBhdGggZD0iTTE4NSA5NEgxNjMuNUMxNjEuMDEzIDk0IDE1OSA5Ni4wMTM0IDE1OSA5OC41VjEzN0MxNTkgMTM5LjQ4NyAxNjEuMDEzIDE0MS41IDE2My41IDE0MS41SDE4NUMxODcuNDg3IDE0MS41IDE5MCAxMzkuNDg3IDE5MCAxMzdWOTguNUMxOTAgOTYuMDEzNCAxODcuNDg3IDk0IDE4NSA5NFoiIGZpbGw9IiNEOEQ4RDgiLz4KPC9zdmc+Cg=='">
                                
                                <?php if ($product['quantity'] > 0): ?>
                                <div class="stock-badge <?php echo $stockBadgeClass; ?>">
                                    <?php echo $product['quantity']; ?> en stock
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <div>
                                    <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                    <p class="product-price"><?php echo number_format($product['price'], 2); ?> DH</p>
                                </div>
                                
                                <div class="product-actions">
                                    <?php if ($product['quantity'] > 0): ?>
                                        <form method="POST" class="flex gap-2 w-full">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <div class="quantity-selector">
                                                <button type="button" class="quantity-btn minus" onclick="decrementQuantity(this)">-</button>
                                                <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['quantity']; ?>" class="quantity-input">
                                                <button type="button" class="quantity-btn plus" onclick="incrementQuantity(this)">+</button>
                                            </div>
                                            <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                                                <i class="fas fa-cart-plus"></i>
                                                <span>Ajouter</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="add-to-cart-btn bg-gray-400 cursor-not-allowed" disabled>
                                            <i class="fas fa-times"></i>
                                            Rupture
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="view-details-btn">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <!-- Mobile Navigation -->
    <?php include '../assets/part/nav-mobil.php'; ?>

    <!-- Include Footer -->
    <?php include '../assets/part/footer.php'; ?>

    <!-- JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        // Quantity adjustment functions
        function incrementQuantity(button) {
            const input = button.parentElement.querySelector('.quantity-input');
            const max = parseInt(input.getAttribute('max'));
            let value = parseInt(input.value) || 1;
            
            if (value < max) {
                input.value = value + 1;
            }
        }
        
        function decrementQuantity(button) {
            const input = button.parentElement.querySelector('.quantity-input');
            let value = parseInt(input.value) || 1;
            
            if (value > 1) {
                input.value = value - 1;
            }
        }
        
        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>