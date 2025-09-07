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

        /* Fix for text colors - ensure proper contrast in both modes */
        .text-light-dark {
            color: var(--text-primary);
        }
        
        .text-secondary-light-dark {
            color: var(--text-secondary);
        }

        .product-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 40px var(--shadow-color);
            overflow: hidden;
            transition: all 0.3s ease;
            margin: 1rem;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px var(--shadow-hover);
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

        .bottom-nav {
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
        
        .nav-item {
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
        
        .nav-item.active {
            color: var(--primary-color);
            background: rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }
        
        .nav-item:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
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
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .sticky-header {
            background: rgba(17, 24, 39, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .quantity-input {
            width: 100px;
            text-align: center;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 0.75rem;
            background: var(--input-bg);
            color: var(--text-primary);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .quantity-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 2rem;
            border-radius: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-checkout {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-checkout:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .stock-indicator {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
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

        /* Image Gallery Styles */
        .product-gallery {
            position: relative;
        }

        .main-image-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: var(--bg-primary);
        }

        .main-product-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            transition: transform 0.3s ease;
            cursor: zoom-in;
        }

        .main-product-image:hover {
            transform: scale(1.05);
        }

        .thumbnail-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .thumbnail {
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            min-width: 80px;
        }

        .thumbnail:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .thumbnail.active {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }

        .thumbnail img {
            width: 100%;
            height: 80px;
            object-fit: cover;
        }

        .image-overlay {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
        }

        .stock-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .stock-high {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }
        
        .stock-medium {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
        }
        
        .stock-low {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
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

        /* Desktop Navigation Styles */
        .desktop-nav {
            display: none;
            background: var(--bg-primary);
            box-shadow: 0 4px 20px var(--shadow-color);
            padding: 0.75rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border);
        }
        
        .desktop-nav-container {
            max-width: 1200px;
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
        
     
        .modern-section-title {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
        }
        
        .modern-section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-radius: 2px;
        }
        
        .modern-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px var(--shadow-color);
            transition: all 0.3s ease;
        }
        
        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px var(--shadow-hover);
        }
        
        /* Responsive Design */
        @media (min-width: 769px) {
            .desktop-nav {
                display: block;
            }
            
            .bottom-nav {
                display: none;
            }
            
            .floating-cart-btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .product-card {
                margin: 0.5rem;
                border-radius: 16px;
            }
            
            .main-product-image {
                height: 300px;
            }
            
            .thumbnail-container {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
            }
            
            .thumbnail img {
                height: 60px;
            }
            
            .btn {
                padding: 1rem 1.5rem;
                font-size: 0.9rem;
            }
            
            .quantity-input {
                width: 80px;
                padding: 0.5rem;
            }
            
            .product-card > .md\:flex {
                flex-direction: column;
            }
            
            .md\:w-1\/2 {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .thumbnail-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .main-product-image {
                height: 250px;
            }
            
            .product-card {
                margin: 0.25rem;
            }
            
            .btn {
                padding: 0.875rem 1.25rem;
                font-size: 0.85rem;
            }
            
            h1.text-4xl {
                font-size: 1.875rem;
            }
        }

        /* Utility Classes */
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hover-lift {
            transition: transform 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-3px);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        /* Mobile-specific fixes */
        @media (max-width: 640px) {
            body {
                padding-bottom: 80px; /* Space for bottom nav */
            }
            
            .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .px-4 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .py-8 {
                padding-top: 1.5rem;
                padding-bottom: 1.5rem;
            }
            
            .main-image-container {
                border-radius: 16px;
            }
            
            .thumbnail-container {
                grid-template-columns: repeat(4, 80px);
                overflow-x: auto;
                display: flex;
                flex-wrap: nowrap;
                gap: 0.5rem;
                padding-bottom: 1rem;
            }
            
            .thumbnail {
                flex: 0 0 auto;
            }
        }
        
        /* Prevent horizontal scrolling */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        /* Smooth scrolling for the whole page */
        html {
            scroll-behavior: smooth;
        }
        
        /* Fix for iOS Safari viewport */
        @supports (-webkit-touch-callout: none) {
            .min-h-screen {
                min-height: -webkit-fill-available;
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
                
                <div class="desktop-nav-menu">
                    <a href="index.php" class="desktop-nav-link">Accueil</a>
                    <a href="products.php" class="desktop-nav-link">Produits</a>
                    <a href="categories.php" class="desktop-nav-link">Catégories</a>
                    <a href="about.php" class="desktop-nav-link">À propos</a>
                    <a href="contact.php" class="desktop-nav-link">Contact</a>
                </div>
                
                <div class="desktop-nav-actions">
                    <div class="theme-toggle" id="theme-toggle">
                        <i class="fas fa-moon" id="theme-icon"></i>
                    </div>
                    
                    <a href="search.php" class="desktop-nav-icon">
                        <i class="fas fa-search"></i>
                    </a>
                    
                    <a href="profile.php" class="desktop-nav-icon">
                        <i class="fas fa-user"></i>
                    </a>
                    
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
        <main class="flex-grow container mx-auto px-4 py-8">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="notification success show animate-fade-in-up">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="notification error show animate-fade-in-up">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>

            <div class="product-card max-w-6xl mx-auto animate-fade-in-up">
                <div class="md:flex">
                    <!-- Product Image Gallery -->
                    <div class="md:w-1/2 p-4 md:p-8">
                        <div class="product-gallery">
                            <div class="main-image-container">
                                <?php
                                // Get the main image path from database - FIXED PATH
                                $mainImage = !empty($product['image']) ? "../assets/images/" . htmlspecialchars($product['image']) : '../assets/images/placeholder.jpg';
                                ?>
                                <img id="main-product-image" src="<?php echo $mainImage; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="main-product-image"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIyNSIgdmlld0JveD0iMCAwIDMwMCAyMjUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjI1IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0xMTIuNSA4NC41QzExMi41IDc4LjAyODQgMTE3Ljc4MiA3Mi43NSAxMjQuMjUgNzIuNzVDMTMwLjcxOCA3Mi43NSAxMzYgNzguMDI4NCAxMzYgODQuNUMxMzYgOTAuOTcxNiAxMzAuNzE4IDk2LjI1IDEyNC4yNSA5Ni4yNUMxMTcuNzgyIDk2LjI1IDExMi41IDkwLjk3MTYgMTEyLjUgODQuNVoiIGZpbGw9IiNEOEQ4RDgiLz4KPHBhdGggZD0iTTE4NSA5NEgxNjMuNUMxNjEuMDEzIDk4IDE1OSA5Ni4wMTM0IDE1OSA5OC41VjEzN0MxNTkgMTM5LjQ4NyAxNjEuMDEzIDE0MS41IDE2My41IDE0MS41SDE4NUMxODcuNDg3IDE0MS41IDE5MCAxMzkuNDg3IDE5MCAxMzdWOTguNUMxOTAgOTYuMDEzNCAxODcuNDg3IDk0IDE4NSA5NFoiIGZpbGw9IiNEOEQ4RDgiLz4KPC9zdmc+Cg=='">
                                
                                <div class="image-overlay">
                                    <span class="stock-badge <?php 
                                        if ($product['quantity'] > 10) echo 'stock-high';
                                        elseif ($product['quantity'] > 0) echo 'stock-medium';
                                        else echo 'stock-low';
                                    ?>">
                                        <?php echo $product['quantity']; ?> en stock
                                    </span>
                                </div>
                            </div>

                            <div class="thumbnail-container">
                                <!-- Main product image thumbnail -->
                                <div class="thumbnail active" onclick="changeImage('<?php echo $mainImage; ?>')">
                                    <img src="<?php echo $mainImage; ?>" alt="Image principale">
                                </div>
                                
                                <?php
                                // Display additional images from database if available - FIXED PATH
                                $additionalImages = $productController->getProductImages($product['id']);
                                
                                if (!empty($additionalImages)) {
                                    foreach ($additionalImages as $index => $image) {
                                        // CORRECTED PATH: Removed extra slash
                                        $fullImagePath = "../assets/images/product_images/" . htmlspecialchars($image['image_path']);
                                        echo '<div class="thumbnail" onclick="changeImage(\'' . $fullImagePath . '\')">';
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
                                        echo '<div class="thumbnail" onclick="changeImage(\'' . $imageUrl . '\')">';
                                        echo '<img src="' . $imageUrl . '" alt="Image exemple ' . ($index + 1) . '">';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="md:w-1/2 p-4 md:p-8">
                        <div class="mb-6 md:mb-8">
                            <h1 class="text-3xl md:text-4xl font-bold text-light-dark mb-4"><?php echo htmlspecialchars($product['name']); ?></h1>
                            
                            <div class="flex items-center justify-between mb-6">
                                <p class="text-2xl md:text-3xl font-bold text-gradient"><?php echo number_format($product['price'], 2); ?> DH</p>
                                <button class="p-3 md:p-4 rounded-full bg-gray-100 text-red-500 hover:bg-gray-200 transition-colors dark:bg-gray-700 dark:hover:bg-gray-600 hover-lift">
                                    <i class="fas fa-heart text-lg md:text-xl"></i>
                                </button>
                            </div>
                            
                            <!-- Stock indicator -->
                            <div class="mb-6">
                                <?php if ($product['quantity'] > 10): ?>
                                    <span class="stock-indicator in-stock">
                                        <i class="fas fa-check-circle mr-2"></i> En stock
                                    </span>
                                <?php elseif ($product['quantity'] > 0): ?>
                                    <span class="stock-indicator low-stock">
                                        <i class="fas fa-exclamation-circle mr-2"></i> Plus que <?php echo $product['quantity']; ?> disponible(s)
                                    </span>
                                <?php else: ?>
                                    <span class="stock-indicator out-of-stock">
                                        <i class="fas fa-times-circle mr-2"></i> Rupture de stock
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-secondary-light-dark leading-relaxed text-base md:text-lg mb-6"><?php echo htmlspecialchars($product['description']); ?></p>
                        </div>
                        
                        <!-- Add to Cart Form -->
                        <form action="" method="POST" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <div class="flex items-center space-x-4">
                                <label for="quantity" class="text-base md:text-lg font-medium text-light-dark">Quantité :</label>
                                <input type="number" id="quantity" name="quantity" min="1" 
                                       max="<?php echo $product['quantity']; ?>" value="1" 
                                       class="quantity-input focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <button type="submit" name="add_to_cart" 
                                        class="btn btn-primary w-full" 
                                        <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus mr-3"></i> Ajouter au panier
                                </button>
                                
                                <button type="submit" name="buy_now" 
                                        class="btn btn-checkout w-full"
                                        <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-bolt mr-3"></i> Acheter maintenant
                                </button>
                            </div>
                            
                            <?php if ($product['quantity'] <= 0): ?>
                                <p class="text-red-500 text-base md:text-lg text-center font-medium">Ce produit est actuellement en rupture de stock.</p>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Product features -->
                        <div class="mt-8 md:mt-10 pt-6 md:pt-8 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-xl md:text-2xl text-light-dark mb-4 md:mb-6">Caractéristiques :</h3>
                            <ul class="space-y-3 md:space-y-4">
                                <li class="flex items-center text-base md:text-lg">
                                    <i class="fas fa-check text-green-500 mr-3 md:mr-4 text-lg md:text-xl"></i>
                                    <span class="text-secondary-light-dark">Matériaux de qualité premium</span>
                                </li>
                                <li class="flex items-center text-base md:text-lg">
                                    <i class="fas fa-check text-green-500 mr-3 md:mr-4 text-lg md:text-xl"></i>
                                    <span class="text-secondary-light-dark">Garantie satisfait ou remboursé 7 jours</span>
                                </li>
                                <li class="flex items-center text-base md:text-lg">
                                    <i class="fas fa-check text-green-500 mr-3 md:mr-4 text-lg md:text-xl"></i>
                                    <span class="text-secondary-light-dark">Livraison gratuite au Maroc</span>
                                </li>
                                <li class="flex items-center text-base md:text-lg">
                                    <i class="fas fa-check text-green-500 mr-3 md:mr-4 text-lg md:text-xl"></i>
                                    <span class="text-secondary-light-dark">Paiement sécurisé</span>
                                </li>
                                <li class="flex items-center text-base md:text-lg">
                                    <i class="fas fa-check text-green-500 mr-3 md:mr-4 text-lg md:text-xl"></i>
                                    <span class="text-secondary-light-dark">Support client 24/7</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Floating Cart Button (Mobile) -->
        <?php include"../assets/part/floatingCart.php" ?>

        <!-- Bottom Navigation (Mobile) -->
        <?php include '../assets/part/nav-mobil.php' ?>
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
                    const max = parseInt(this.getAttribute('max'));
                    const value = parseInt(this.value);
                    
                    if (value > max) {
                        this.value = max;
                        showToast('Quantité maximale disponible : ' + max, 'warning');
                    } else if (value < 1) {
                        this.value = 1;
                    }
                });
            }

            // Image loading animation
            const productImage = document.querySelector('.main-product-image');
            if (productImage) {
                productImage.addEventListener('load', function() {
                    this.style.opacity = '1';
                    this.style.transition = 'opacity 0.5s ease';
                });
                
                productImage.addEventListener('error', function() {
                    this.style.opacity = '1';
                });
            }
            
            // Mobile-specific adjustments
            if (window.innerWidth <= 768) {
                // Make thumbnails horizontally scrollable on mobile
                const thumbnailContainer = document.querySelector('.thumbnail-container');
                if (thumbnailContainer) {
                    thumbnailContainer.style.overflowX = 'auto';
                    thumbnailContainer.style.flexWrap = 'nowrap';
                    thumbnailContainer.style.paddingBottom = '0.5rem';
                }
            }
        });

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            
            showToast(`Mode ${newTheme === 'light' ? 'clair' : 'sombre'} activé`, 'success');
        }

        function updateThemeIcon(theme) {
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) {
                themeIcon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }

        function changeImage(src) {
            const mainImage = document.getElementById('main-product-image');
            const thumbnails = document.querySelectorAll('.thumbnail');
            
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
        
        // Handle window resize for responsive adjustments
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                const thumbnailContainer = document.querySelector('.thumbnail-container');
                if (thumbnailContainer) {
                    thumbnailContainer.style.overflowX = 'auto';
                    thumbnailContainer.style.flexWrap = 'nowrap';
                }
            } else {
                const thumbnailContainer = document.querySelector('.thumbnail-container');
                if (thumbnailContainer) {
                    thumbnailContainer.style.overflowX = 'visible';
                    thumbnailContainer.style.flexWrap = 'wrap';
                }
            }
        });
    </script>
</body>
</html>