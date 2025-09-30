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

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            min-height: 100vh;
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
            padding-bottom: 80px; /* Space for mobile nav */
        }

        /* Mobile-first responsive design */
        .product-container {
            max-width: 1280px;
            margin: 1rem auto;
            padding: 0 1rem;
        }

        @media (min-width: 768px) {
            .product-container {
                margin: 2rem auto;
                padding: 0 2rem;
            }
            body {
                padding-bottom: 0;
            }
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 1024px) {
            .product-grid {
                grid-template-columns: 1fr 1fr;
                gap: 3rem;
            }
        }

        /* Product Images - Mobile Optimized */
        .product-image-container {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px var(--shadow-color);
            background: var(--bg-primary);
            touch-action: pan-y;
        }

        .product-main-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        @media (min-width: 640px) {
            .product-main-image {
                height: 400px;
            }
        }

        @media (min-width: 1024px) {
            .product-main-image {
                height: 500px;
            }
        }

        .product-thumbnails {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .product-thumbnails::-webkit-scrollbar {
            display: none;
        }

        .product-thumbnail {
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            flex-shrink: 0;
            min-width: 80px;
        }

        .product-thumbnail.active {
            border-color: var(--primary-color);
        }

        .product-thumbnail img {
            width: 100%;
            height: 80px;
            object-fit: cover;
        }

        /* Product Details - Mobile Optimized */
        .product-details {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px var(--shadow-color);
            position: sticky;
            top: 1rem;
        }

        @media (min-width: 768px) {
            .product-details {
                padding: 2rem;
            }
        }

        .product-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
            line-height: 1.3;
        }

        @media (min-width: 768px) {
            .product-title {
                font-size: 2rem;
            }
        }

        .product-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        @media (min-width: 768px) {
            .product-price {
                font-size: 2.5rem;
            }
        }

        .product-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        /* Stock Badge */
        .stock-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 0.875rem;
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

        /* Quantity Selector - Touch Friendly */
        .quantity-selector {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 0.5rem;
        }

        .quantity-btn {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--input-bg);
            border: 1px solid var(--border);
            font-size: 1.2rem;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
            touch-action: manipulation;
        }

        .quantity-btn:active {
            transform: scale(0.95);
            background: var(--primary-color);
            color: white;
        }

        .quantity-input {
            width: 70px;
            height: 44px;
            text-align: center;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text-primary);
            font-weight: 600;
            border-radius: 8px;
            font-size: 1rem;
            -webkit-appearance: none;
            -moz-appearance: textfield;
        }

        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Action Buttons - Mobile Optimized */
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
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-size: 1rem;
            min-height: 54px;
            touch-action: manipulation;
        }

        .btn:active {
            transform: scale(0.98);
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
            transform: none !important;
        }

        /* Product Features */
        .product-features {
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .feature-icon {
            color: var(--success-color);
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }

        /* Notifications */
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
            max-width: calc(100vw - 40px);
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
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border);
        }
        
        @media (min-width: 768px) {
            .desktop-nav {
                padding: 1rem 2rem;
            }
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
            font-size: 1.25rem;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        @media (min-width: 768px) {
            .desktop-nav-logo {
                font-size: 1.5rem;
            }
        }
        
        .desktop-nav-logo img {
            height: 35px;
            margin-right: 10px;
            border-radius: 8px;
        }
        
        .desktop-nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        @media (min-width: 768px) {
            .desktop-nav-actions {
                gap: 1.5rem;
            }
        }
        
        .desktop-nav-icon {
            position: relative;
            color: var(--text-primary);
            transition: color 0.3s ease;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .desktop-nav-icon:hover {
            color: var(--primary-color);
        }
        
        .cart-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: linear-gradient(135deg, var(--error-color), #DC2626);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .theme-toggle {
            background: var(--input-bg);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
            touch-action: manipulation;
        }

        .theme-toggle:active {
            transform: scale(0.95);
        }

        /* Mobile Navigation */
        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            padding: 0.75rem;
            box-shadow: 0 -10px 30px var(--shadow-color);
            z-index: 100;
            display: flex;
            justify-content: space-around;
            border-top: 1px solid var(--border);
            backdrop-filter: blur(10px);
        }
        
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.75rem;
            padding: 0.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            min-width: 60px;
            touch-action: manipulation;
        }
        
        .mobile-nav-item.active {
            color: var(--primary-color);
            background: rgba(59, 130, 246, 0.15);
        }
        
        .mobile-nav-item:active {
            transform: scale(0.95);
        }

        .mobile-nav-icon {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        /* Image Zoom for Mobile */
        .image-zoom-container {
            position: relative;
            overflow: hidden;
            cursor: zoom-in;
        }

        .image-zoom-container.zoomed {
            cursor: zoom-out;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .image-zoom-container.zoomed .product-main-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            cursor: zoom-out;
        }

        /* Smooth scrolling */
        .smooth-scroll {
            scroll-behavior: smooth;
        }

        /* Loading states */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 80px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 99;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
        }

        .back-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .back-to-top:active {
            transform: scale(0.95);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .animate-slide-in {
            animation: slideIn 0.3s ease-out forwards;
        }

        /* Prevent horizontal scroll on mobile */
        body {
            overflow-x: hidden;
            width: 100%;
        }

        /* Improved touch targets */
        @media (max-width: 768px) {
            .btn, .quantity-btn, .theme-toggle, .mobile-nav-item {
                min-height: 44px; /* Minimum touch target size */
            }
        }

        /* Performance optimizations */
        .will-change-transform {
            will-change: transform;
        }

        .will-change-opacity {
            will-change: opacity;
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
                    <span class="hidden sm:inline">Monster Store</span>
                    <span class="sm:hidden">MS</span>
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

        <!-- Back to Top Button -->
        <div class="back-to-top" id="back-to-top" onclick="scrollToTop()">
            <i class="fas fa-chevron-up"></i>
        </div>

        <!-- Main Content -->
        <main class="flex-grow">
            <div class="product-container">
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="notification success show animate-slide-in">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="notification error show animate-slide-in">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                    </div>
                <?php endif; ?>

                <div class="product-grid animate-fade-in">
                    <!-- Product Images -->
                    <div class="product-images">
                        <div class="product-image-container image-zoom-container" id="image-zoom-container">
                            <?php
                            $mainImage = !empty($product['image']) ? "../assets/images/" . htmlspecialchars($product['image']) : '../assets/images/placeholder.jpg';
                            ?>
                            <img id="main-product-image" src="<?php echo $mainImage; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-main-image will-change-transform"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIyNSIgdmlld0JveD0iMCAwIDMwMCAyMjUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjI1IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0xMTIuNSA4NC41QzExMi41IDc4LjAyODQgMTE3Ljc4MiA3Mi43NSAxMjQuMjUgNzIuNzVDMTMwLjcxOCA3Mi43NSAxMzYgNzguMDI4NCAxMzYgODQuNUMxMzYgOTAuOTcxNiAxMzAuNzE4IDk2LjI1IDEyNC4yNSA5Ni4yNUMxMTcuNzgyIDk2LjI1IDExMi41IDkwLjk3MTYgMTEyLjUgODQuNVoiIGZpbGw9IiNEOEQ4RDgiLz4KPHBhdGggZD0iTTE4NSA5NEgxNjMuNUMxNjEuMDEzIDk4IDE1OSA5Ni4wMTM0IDE1OSA5OC41VjEzN0MxNTkgMTM5LjQ4NyAxNjEuMDEzIDE0MS41IDE2My41IDE0MS41SDE4NUMxODcuNDg3IDE0MS41IDE5MCAxMzkuNDg3IDE5MCAxMzdWOTguNUMxOTAgOTYuMDEzNCAxODcuNDg3IDk0IDE4NSA5NFoiIGZpbGw9IiNEOEQ4RDgiLz4KPC9zdmc+Cg=='">
                            
                            <div class="absolute top-3 right-3">
                                <span class="stock-badge <?php 
                                    if ($product['quantity'] > 10) echo 'in-stock';
                                    elseif ($product['quantity'] > 0) echo 'low-stock';
                                    else echo 'out-of-stock';
                                ?>">
                                    <i class="fas fa-<?php 
                                        if ($product['quantity'] > 10) echo 'check';
                                        elseif ($product['quantity'] > 0) echo 'exclamation-triangle';
                                        else echo 'times';
                                    ?> mr-1"></i>
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
                            $additionalImages = $productController->getProductImages($product['id']);
                            
                            if (!empty($additionalImages)) {
                                foreach ($additionalImages as $index => $image) {
                                    $fullImagePath = "../assets/images/product_images/" . htmlspecialchars($image['image_path']);
                                    echo '<div class="product-thumbnail" onclick="changeImage(\'' . $fullImagePath . '\')">';
                                    echo '<img src="' . $fullImagePath . '" alt="Image produit ' . ($index + 1) . '" onerror="this.parentElement.style.display=\'none\'">';
                                    echo '</div>';
                                }
                            } else {
                                $sampleImages = [
                                    'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80',
                                    'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80',
                                    'https://images.unsplash.com/photo-1556306535-0f09a537f0a3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80'
                                ];
                                
                                foreach ($sampleImages as $index => $imageUrl) {
                                    echo '<div class="product-thumbnail" onclick="changeImage(\'' . $imageUrl . '\')">';
                                    echo '<img src="' . $imageUrl . '" alt="Image exemple ' . ($index + 1) . '" loading="lazy">';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="product-details">
                        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                        
                        <div class="product-price">
                            <i class="fas fa-tag mr-2"></i>
                            <?php echo number_format($product['price'], 2); ?> DH
                        </div>
                        
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        
                        <!-- Add to Cart Form -->
                        <form action="" method="POST" id="cart-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <div class="quantity-selector">
                                <label for="quantity" class="font-medium text-sm sm:text-base">
                                    <i class="fas fa-cubes mr-2"></i>Quantité :
                                </label>
                                <button type="button" class="quantity-btn" onclick="decrementQuantity()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="quantity" name="quantity" min="1" 
                                       max="<?php echo $product['quantity']; ?>" value="1" 
                                       class="quantity-input">
                                <button type="button" class="quantity-btn" onclick="incrementQuantity()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="add_to_cart" 
                                        class="btn btn-primary will-change-transform" 
                                        <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus mr-3"></i> 
                                    <span class="hidden sm:inline">Ajouter au panier</span>
                                    <span class="sm:hidden">Panier</span>
                                </button>
                                
                                <button type="submit" name="buy_now" 
                                        class="btn btn-secondary will-change-transform"
                                        <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-bolt mr-3"></i> 
                                    <span class="hidden sm:inline">Acheter maintenant</span>
                                    <span class="sm:hidden">Acheter</span>
                                </button>
                            </div>
                            
                            <?php if ($product['quantity'] <= 0): ?>
                                <p class="text-center text-red-500 font-medium text-sm">
                                    <i class="fas fa-times-circle mr-2"></i>
                                    Ce produit est actuellement en rupture de stock.
                                </p>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Product features -->
                        <div class="product-features">
                            <h3 class="font-semibold text-lg mb-3">
                                <i class="fas fa-star mr-2 text-yellow-500"></i>
                                Caractéristiques :
                            </h3>
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
        // Enhanced mobile support with smooth scrolling
        document.addEventListener('DOMContentLoaded', function() {
            initializeTheme();
            setupEventListeners();
            setupSmoothScrolling();
            setupImageZoom();
            setupBackToTop();
        });

        function initializeTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcon(savedTheme);
        }

        function setupEventListeners() {
            // Theme toggle
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
                themeToggle.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    toggleTheme();
                }, { passive: false });
            }
            
            // Notifications auto-hide
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notif => {
                setTimeout(() => {
                    notif.classList.remove('show');
                    setTimeout(() => notif.remove(), 300);
                }, 4000);
            });

            // Quantity input validation
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantityInput.addEventListener('change', validateQuantity);
                quantityInput.addEventListener('blur', validateQuantity);
            }

            // Form submission loading state
            const cartForm = document.getElementById('cart-form');
            if (cartForm) {
                cartForm.addEventListener('submit', function(e) {
                    const buttons = this.querySelectorAll('button[type="submit"]');
                    buttons.forEach(btn => {
                        btn.classList.add('loading');
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Chargement...';
                    });
                });
            }
        }

        function setupSmoothScrolling() {
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Prevent rubber-band scrolling on iOS
            document.body.addEventListener('touchmove', function(e) {
                if (e.target.classList.contains('product-thumbnails')) {
                    return;
                }
                if (document.body.scrollHeight <= window.innerHeight) {
                    e.preventDefault();
                }
            }, { passive: false });
        }

        function setupImageZoom() {
            const zoomContainer = document.getElementById('image-zoom-container');
            const mainImage = document.getElementById('main-product-image');
            
            if (zoomContainer && mainImage) {
                let isZoomed = false;
                
                zoomContainer.addEventListener('click', function() {
                    if (window.innerWidth < 768) { // Only on mobile
                        isZoomed = !isZoomed;
                        if (isZoomed) {
                            zoomContainer.classList.add('zoomed');
                            document.body.style.overflow = 'hidden';
                        } else {
                            zoomContainer.classList.remove('zoomed');
                            document.body.style.overflow = '';
                        }
                    }
                });

                // Close zoom on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && isZoomed) {
                        zoomContainer.classList.remove('zoomed');
                        document.body.style.overflow = '';
                        isZoomed = false;
                    }
                });
            }
        }

        function setupBackToTop() {
            const backToTop = document.getElementById('back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });
        }

        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

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
            mainImage.style.opacity = '0.5';
            setTimeout(() => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
            }, 150);
            
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
            const toast = document.createElement('div');
            toast.className = `fixed top-4 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-lg text-white font-medium shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
            }`;
            toast.textContent = message;
            toast.style.opacity = '0';
            toast.style.transform = 'translate(-50%, -20px)';
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translate(-50%, 0)';
            }, 100);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translate(-50%, -20px)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                window.scrollTo(0, 0);
            }, 100);
        });

        // Prevent zoom on double tap (iOS)
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
</body>
</html>