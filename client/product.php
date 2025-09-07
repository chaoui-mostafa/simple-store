<?php
session_start();
require_once '../config/db.php';
require_once '../controllers/ProductController.php';
require_once '../controllers/CartController.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$pdo = $db->connect();

// Redirect if no product ID
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$productController = new ProductController($pdo);
$cartController = new CartController();
$product = $productController->getProductById($_GET['id']);

if (!$product) {
    header('Location: index.php');
    exit();
}

// Get cart count for badge
$cartCount = $cartController->getCartCount();

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $prodId = (int)$_POST['product_id'];
        $quantity = max(1, (int)$_POST['quantity']);

        if ($cartController->addToCart($prodId, $quantity)) {
            $_SESSION['success_msg'] = "Produit ajouté au panier avec succès!";
            $cartCount = $cartController->getCartCount();
            header("Location: product.php?id={$prodId}");
            exit();
        } else {
            $_SESSION['error_msg'] = "Échec de l'ajout du produit au panier.";
        }
    }
    
    // Handle buy now
    if (isset($_POST['buy_now'])) {
        $prodId = (int)$_POST['product_id'];
        $quantity = max(1, (int)$_POST['quantity']);

        if ($cartController->addToCart($prodId, $quantity)) {
            header("Location: checkout.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Monster Store</title>
    <link rel="icon" href="../assets/images/logo/logo.jpg" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #3B82F6;
            --primary-hover: #2563EB;
            --success-color: #10B981;
            --error-color: #EF4444;
            --light: #F3F4F6;
            --border: #D1D5DB;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --card-bg: #ffffff;
            --input-bg: #f8fafc;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --primary-color: #60A5FA;
            --primary-hover: #3B82F6;
            --success-color: #34D399;
            --error-color: #F87171;
            --text-primary: #F9FAFB;
            --text-secondary: #D1D5DB;
            --bg-primary: #111827;
            --bg-secondary: #1F2937;
            --card-bg: #1F2937;
            --input-bg: #374151;
            --border: #4B5563;
            --shadow-color: rgba(0, 0, 0, 0.2);
            --shadow-hover: rgba(0, 0, 0, 0.3);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            min-height: 100vh;
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
        }

        .product-container {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        @media (min-width: 1024px) {
            .product-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .product-image-container {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px var(--shadow-color);
            background: var(--bg-primary);
        }

        .product-main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-thumbnails {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .product-thumbnail {
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .product-thumbnail.active {
            border-color: var(--primary-color);
        }

        .product-thumbnail img {
            width: 100%;
            height: 80px;
            object-fit: cover;
        }

        .product-details {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 30px var(--shadow-color);
        }

        .product-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .product-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .product-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .stock-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .in-stock {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            color: #065F46;
        }

        .low-stock {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            color: #92400E;
        }

        .out-of-stock {
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            color: #B91C1C;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--input-bg);
            border: 1px solid var(--border);
            font-size: 1.2rem;
            cursor: pointer;
        }

        .quantity-input {
            width: 60px;
            height: 40px;
            text-align: center;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text-primary);
            font-weight: 600;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 640px) {
            .action-buttons {
                grid-template-columns: 1fr 1fr;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .product-features {
            border-top: 1px solid var(--border);
            padding-top: 2rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: var(--text-secondary);
        }

        .feature-icon {
            color: var(--success-color);
            margin-right: 0.75rem;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 15px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: linear-gradient(135deg, var(--success-color), #059669);
        }
        
        .notification.error {
            background: linear-gradient(135deg, var(--error-color), #DC2626);
        }

        /* Desktop Navigation */
        .desktop-nav {
            background: var(--bg-primary);
            box-shadow: 0 4px 20px var(--shadow-color);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border);
        }
        
        .desktop-nav-container {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .desktop-nav-logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .desktop-nav-logo img {
            height: 40px;
            margin-right: 10px;
            border-radius: 8px;
        }
        
        .desktop-nav-menu {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .desktop-nav-link {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
            padding: 0.5rem 0;
        }
        
        .desktop-nav-link:hover {
            color: var(--primary-color);
        }
        
        .desktop-nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }
        
        .desktop-nav-link:hover::after {
            width: 100%;
        }
        
        .desktop-nav-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .desktop-nav-icon {
            position: relative;
            color: var(--text-primary);
            transition: color 0.3s ease;
            cursor: pointer;
        }
        
        .desktop-nav-icon:hover {
            color: var(--primary-color);
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, var(--error-color), #DC2626);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .theme-toggle {
            background: var(--input-bg);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: rotate(180deg);
            border-color: var(--primary-color);
        }

        /* Mobile Navigation */
        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            padding: 1rem;
            box-shadow: 0 -10px 30px var(--shadow-color);
            z-index: 100;
            display: flex;
            justify-content: space-around;
            border-top: 1px solid var(--border);
        }
        
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.8rem;
            padding: 0.75rem;
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .mobile-nav-item.active {
            color: var(--primary-color);
            background: rgba(59, 130, 246, 0.15);
        }
        
        .mobile-nav-item:hover {
            color: var(--primary-color);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .product-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }
            
            .product-main-image {
                height: 300px;
            }
            
            .product-thumbnails {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .product-title {
                font-size: 1.75rem;
            }
            
            .product-price {
                font-size: 2rem;
            }
            
            .desktop-nav {
                padding: 1rem;
            }
            
            .desktop-nav-menu {
                gap: 1rem;
            }
        }

        @media (max-width: 640px) {
            .product-thumbnails {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .product-main-image {
                height: 250px;
            }
            
            .desktop-nav {
                flex-direction: column;
                gap: 1rem;
            }
            
            .desktop-nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="antialiased">
    <div class="min-h-screen flex flex-col">
        <!-- Desktop Navigation -->
        <nav class="desktop-nav">
            <div class="desktop-nav-container">
                <a href="index.php" class="desktop-nav-logo">
                    <img src="../assets/images/logo/logo.jpg" alt="Monster Store Logo">
                    Monster Store
                </a>
                
                
                <div class="desktop-nav-actions">
                    <div class="theme-toggle" id="theme-toggle">
                        <i class="fas fa-moon" id="theme-icon"></i>
                    </div>
                    
                    <a href="cart.php" class="desktop-nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow">
            <div class="product-container">
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="notification success show animate-fade-in">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="notification error show animate-fade-in">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                    </div>
                <?php endif; ?>

                <div class="product-grid animate-fade-in">
                    <!-- Product Images -->
                    <div class="product-images">
                        <div class="product-image-container">
                            <?php
                            // Get the main image path from database
                            $mainImage = !empty($product['image']) ? "../assets/images/" . htmlspecialchars($product['image']) : '../assets/images/placeholder.jpg';
                            ?>
                            <img id="main-product-image" src="<?php echo $mainImage; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-main-image"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIyNSIgdmlld0JveD0iMCAwIDMwMCAyMjUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjI1IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0xMTIuNSA4NC41QzExMi41IDc4LjAyODQgMTE3Ljc4MiA3Mi43NSAxMjQuMjUgNzIuNzVDMTMwLjcxOCA3Mi43NSAxMzYgNzguMDI4NCAxMzYgODQuNUMxMzYgOTAuOTcxNiAxMzAuNzE4IDk2LjI1IDEyNC4yNSA5Ni4yNUMxMTcuNzgyIDk2LjI1IDExMi41IDkwLjk3MTYgMTEyLjUgODQuNVoiIGZpbGw9IiNEOEQ4RDgiLz4KPHBhdGggZD0iTTE4NSA5NEgxNjMuNUMxNjEuMDEzIDk4IDE1OSA5Ni4wMTM0IDE1OSA5OC41VjEzN0MxNTkgMTM5LjQ4NyAxNjEuMDEzIDE0MS41IDE2My41IDE0MS41SDE4NUMxODcuNDg3IDE0MS41IDE5MCAxMzkuNDg3IDE5MCAxMzdWOTguNUMxOTAgOTYuMDEzNCAxODcuNDg3IDk0IDE4NSA5NFoiIGZpbGw9IiNEOEQ4RDgiLz4KPC9zdmc+Cg=='">
                            
                            <div class="absolute top-4 right-4">
                                <span class="stock-badge <?php 
                                    if ($product['quantity'] > 10) echo 'in-stock';
                                    elseif ($product['quantity'] > 0) echo 'low-stock';
                                    else echo 'out-of-stock';
                                ?>">
                                    <?php echo $product['quantity']; ?> en stock
                                </span>
                            </div>
                        </div>

                        <div class="product-thumbnails">
                            <!-- Main product image thumbnail -->
                            <div class="product-thumbnail active" onclick="changeImage('<?php echo $mainImage; ?>')">
                                <img src="<?php echo $mainImage; ?>" alt="Image principale">
                            </div>
                            
                            <?php
                            // Display additional images from database if available
                            $additionalImages = $productController->getProductImages($product['id']);
                            
                            if (!empty($additionalImages)) {
                                foreach ($additionalImages as $index => $image) {
                                    $fullImagePath = "../assets/images/product_images/" . htmlspecialchars($image['image_path']);
                                    echo '<div class="product-thumbnail" onclick="changeImage(\'' . $fullImagePath . '\')">';
                                    echo '<img src="' . $fullImagePath . '" alt="Image produit ' . ($index + 1) . '" onerror="this.parentElement.style.display=\'none\'">';
                                    echo '</div>';
                                }
                            } else {
                                // Fallback to sample images if no additional images in database
                                $sampleImages = [
                                    'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80',
                                    'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80',
                                    'https://images.unsplash.com/photo-1556306535-0f09a537f0a3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80'
                                ];
                                
                                foreach ($sampleImages as $index => $imageUrl) {
                                    echo '<div class="product-thumbnail" onclick="changeImage(\'' . $imageUrl . '\')">';
                                    echo '<img src="' . $imageUrl . '" alt="Image exemple ' . ($index + 1) . '">';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="product-details">
                        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                        
                        <div class="product-price"><?php echo number_format($product['price'], 2); ?> DH</div>
                        
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        
                        <!-- Add to Cart Form -->
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <div class="quantity-selector">
                                <label for="quantity" class="mr-3 font-medium">Quantité :</label>
                                <button type="button" class="quantity-btn rounded-l-md" onclick="decrementQuantity()">-</button>
                                <input type="number" id="quantity" name="quantity" min="1" 
                                       max="<?php echo $product['quantity']; ?>" value="1" 
                                       class="quantity-input">
                                <button type="button" class="quantity-btn rounded-r-md" onclick="incrementQuantity()">+</button>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="add_to_cart" 
                                        class="btn btn-primary" 
                                        <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus mr-3"></i> Ajouter au panier
                                </button>
                                
                                <button type="submit" name="buy_now" 
                                        class="btn btn-secondary"
                                        <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-bolt mr-3"></i> Acheter maintenant
                                </button>
                            </div>
                            
                            <?php if ($product['quantity'] <= 0): ?>
                                <p class="text-center text-red-500 font-medium">Ce produit est actuellement en rupture de stock.</p>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Product features -->
                        <div class="product-features">
                            <h3 class="font-semibold text-xl mb-4">Caractéristiques :</h3>
                            <ul class="feature-list">
                                <li class="feature-item">
                                    <i class="fas fa-check feature-icon"></i>
                                    <span>Matériaux de qualité premium</span>
                                </li>
                                <li class="feature-item">
                                    <i class="fas fa-check feature-icon"></i>
                                    <span>Garantie satisfait ou remboursé 7 jours</span>
                                </li>
                                <li class="feature-item">
                                    <i class="fas fa-check feature-icon"></i>
                                    <span>Livraison gratuite au Maroc</span>
                                </li>
                                <li class="feature-item">
                                    <i class="fas fa-check feature-icon"></i>
                                    <span>Paiement sécurisé</span>
                                </li>
                                <li class="feature-item">
                                    <i class="fas fa-check feature-icon"></i>
                                    <span>Support client 24/7</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Mobile Navigation -->
  <?php include '../assets/part/nav-mobil.php'; ?>
  <?php include '../assets/part/floatingCart.php'; ?>


    </div>

    <script>
        // Theme management
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
            
            // Auto-hide notifications
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notif => {
                setTimeout(() => {
                    notif.classList.remove('show');
                    setTimeout(() => notif.remove(), 300);
                }, 4000);
            });

            // Quantity validation
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantityInput.addEventListener('change', function() {
                    validateQuantity();
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

        function changeImage(src) {
            const mainImage = document.getElementById('main-product-image');
            const thumbnails = document.querySelectorAll('.product-thumbnail');
            
            // Update main image with fade effect
            mainImage.style.opacity = '0';
            setTimeout(() => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
            }, 200);
            
            // Update active thumbnail
            thumbnails.forEach(thumb => thumb.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }

        function incrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            const max = parseInt(quantityInput.getAttribute('max'));
            let value = parseInt(quantityInput.value);
            
            if (value < max) {
                quantityInput.value = value + 1;
            } else {
                showToast('Quantité maximale disponible : ' + max, 'warning');
            }
        }

        function decrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            let value = parseInt(quantityInput.value);
            
            if (value > 1) {
                quantityInput.value = value - 1;
            }
        }

        function validateQuantity() {
            const quantityInput = document.getElementById('quantity');
            const max = parseInt(quantityInput.getAttribute('max'));
            let value = parseInt(quantityInput.value);
            
            if (isNaN(value) || value < 1) {
                quantityInput.value = 1;
            } else if (value > max) {
                quantityInput.value = max;
                showToast('Quantité maximale disponible : ' + max, 'warning');
            }
        }

        function showToast(message, type = 'info') {
            // Toast notification implementation
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white font-medium shadow-lg transform transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
            }`;
            toast.textContent = message;
            toast.style.transform = 'translateX(100%)';
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>