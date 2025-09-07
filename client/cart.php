<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';
require_once '../controllers/CartController.php';
require_once '../controllers/ProductController.php';

// Établir la connexion à la base de données
$db = new Database();
$pdo = $db->connect();

// Passer la connexion aux contrôleurs
$cartController = new CartController();
$productController = new ProductController($pdo);

$cartItems = $cartController->getCartItems();
$cartTotal = $cartController->getCartTotal();
$cartCount = $cartController->getCartCount();

// Gérer les actions du panier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        $product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
        $quantity = filter_var($_POST['quantity'], FILTER_SANITIZE_NUMBER_INT);
        
        if ($cartController->updateCartItem($product_id, $quantity)) {
            $_SESSION['success'] = 'Panier mis à jour avec succès';
            header('Location: cart.php');
            exit();
        } else {
            $_SESSION['error'] = 'Échec de la mise à jour du panier';
        }
    } elseif (isset($_POST['remove_from_cart'])) {
        $product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
        
        if ($cartController->removeFromCart($product_id)) {
            $_SESSION['success'] = 'Article retiré du panier';
            header('Location: cart.php');
            exit();
        } else {
            $_SESSION['error'] = 'Échec de la suppression de l\'article du panier';
        }
    } elseif (isset($_POST['clear_cart'])) {
        if ($cartController->clearCart()) {
            $_SESSION['success'] = 'Panier vidé avec succès';
            header('Location: cart.php');
            exit();
        } else {
            $_SESSION['error'] = 'Échec du vidage du panier';
        }
    }
}

// Afficher les messages de succès/erreur
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier d'achat - Monster Store</title>
      <link rel="icon" href="../assets/images/logo/logo.jpg" type="image/x-icon">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary: #3B82F6;
            --primary-hover: #2563EB;
            --secondary: #6366F1;
            --accent: #8B5CF6;
            --success: #10B981;
            --warning: #F59E0B;
            --error: #EF4444;
            --gray-light: #F3F4F6;
            --gray-border: #D1D5DB;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --card-bg: #ffffff;
            --input-bg: #f8fafc;
            --footer-bg: #ffffff;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --primary: #60A5FA;
            --primary-hover: #3B82F6;
            --success: #34D399;
            --error: #F87171;
            --text-primary: #F9FAFB;
            --text-secondary: #D1D5DB;
            --bg-primary: #111827;
            --bg-secondary: #1F2937;
            --card-bg: #1F2937;
            --input-bg: #374151;
            --footer-bg: #1F2937;
            --gray-border: #4B5563;
            --shadow-color: rgba(0, 0, 0, 0.2);
            --shadow-hover: rgba(0, 0, 0, 0.3);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            min-height: 100vh;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Mobile-first cart display */
        .cart-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }
        
        .cart-item {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px var(--shadow-color);
            transition: all 0.3s ease;
            display: flex;
            margin-bottom: 1rem;
            padding: 1rem;
        }
        
        .cart-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px var(--shadow-hover);
        }
        
        .cart-item-image-container {
            width: 100px;
            height: 100px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        
        .cart-item-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .cart-item-info {
            padding: 0 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .cart-item-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-camp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .cart-item-price {
            font-weight: bold;
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .cart-item-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            border: 1px solid var(--gray-border);
            border-radius: 8px;
            overflow: hidden;
            width: fit-content;
        }
        
        .quantity-btn {
            background: var(--input-bg);
            border: none;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        .quantity-input {
            width: 36px;
            height: 28px;
            text-align: center;
            border: none;
            border-left: 1px solid var(--gray-border);
            border-right: 1px solid var(--gray-border);
            -moz-appearance: textfield;
            background: var(--card-bg);
            color: var(--text-primary);
        }
        
        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .update-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .update-btn:hover {
            background: var(--primary-hover);
        }
        
        .remove-btn {
            background: var(--card-bg);
            color: var(--error);
            border: 1px solid var(--error);
            border-radius: 8px;
            padding: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .remove-btn:hover {
            background: var(--error);
            color: white;
        }
        
        .stock-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 0.25rem;
        }
        
        .stock-high {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .stock-medium {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .stock-low {
            background-color: #FEE2E2;
            color: #B91C1C;
        }
        
        /* Header styles */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--bg-primary);
            box-shadow: 0 2px 10px var(--shadow-color);
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--error);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Bottom navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            display: flex;
            justify-content: space-around;
            padding: 0.75rem 0;
            box-shadow: 0 -2px 10px var(--shadow-color);
            z-index: 100;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.75rem;
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-icon {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        
        /* Notification styles */
        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: var(--success);
        }
        
        .notification.error {
            background: var(--error);
        }
        
        /* Order summary */
        .order-summary {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px var(--shadow-color);
            margin-top: 1.5rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: between;
            margin-bottom: 0.75rem;
        }
        
        .summary-total {
            display: flex;
            justify-content: between;
            font-weight: bold;
            font-size: 1.2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-border);
        }
        
        .checkout-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: block;
            width: 100%;
            text-align: center;
            transition: background 0.2s;
            margin-top: 1.5rem;
        }
        
        .checkout-btn:hover {
            background: var(--primary-hover);
        }
        
        .continue-shopping {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        /* Desktop Footer */
        .desktop-footer {
            background: var(--footer-bg);
            border-top: 1px solid var(--gray-border);
            padding: 3rem 0 1.5rem;
            margin-top: 2rem;
        }
        
        .footer-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .footer-column h3 {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column ul li {
            margin-bottom: 0.5rem;
        }
        
        .footer-column a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-column a:hover {
            color: var(--primary);
        }
        
        .footer-features {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 2rem;
            padding: 1.5rem 0;
            border-top: 1px solid var(--gray-border);
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }
        
        .feature-icon {
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-border);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        /* Desktop styles */
        @media (min-width: 768px) {
            .cart-container {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 1.5rem;
            }
            
            .cart-list {
                gap: 1.5rem;
            }
            
            .cart-item {
                padding: 1.5rem;
            }
            
            .cart-item-image-container {
                width: 120px;
                height: 120px;
            }
            
            .order-summary {
                margin-top: 0;
                position: sticky;
                top: 6rem;
                height: fit-content;
            }
            
            .bottom-nav {
                display: none;
            }
        }
        
        @media (min-width: 1024px) {
            .cart-item-image-container {
                width: 140px;
                height: 140px;
            }
        }
        
        /* Utilities */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .main-content {
            padding-bottom: 80px; /* Space for bottom nav */
        }
        
        /* Responsive footer columns */
        @media (max-width: 1024px) {
            .footer-section {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 640px) {
            .footer-section {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .footer-features {
                flex-direction: column;
                gap: 1rem;
            }
            
            .desktop-footer {
                display: none;
            }
        }
        
        /* Empty cart styles */
        .empty-cart {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-cart-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        
        .empty-cart-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-cart-text {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        .shop-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
            text-decoration: none;
        }
        
        .shop-btn:hover {
            background: var(--primary-hover);
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
            border: 1px solid var(--gray-border);
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

 <!-- Header -->
<header class="sticky-header">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-3 md:py-4">
            <!-- Logo + Title -->
            <div class="flex items-center">
                <a href="index.php" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-blue-600 flex items-center justify-center mr-2 sm:mr-3 overflow-hidden">
                    <img src="../assets/images/logo/logo.jpg" 
                         alt="Logo" 
                         class="w-full h-full object-cover" 
                         onerror="this.style.display='none'; this.parentElement.querySelector('span').style.display='flex';">
                    <span class="text-white font-bold text-sm sm:text-xl hidden">M</span>
                </a>
                <h1 class="text-sm sm:text-base md:text-xl font-bold dark:text">Monster Store</h1>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 sm:gap-4">
                <!-- Theme toggle إذا بغيت تعود تفعيل -->
                <!--
                <button id="theme-toggle" class="theme-toggle p-1 sm:p-2 rounded">
                    <i id="theme-icon" class="fas fa-moon text-xs sm:text-sm"></i>
                </button>
                -->

                <a href="index.php" class="text-blue-600 hover:text-blue-800 flex items-center text-xs sm:text-sm dark:text-blue-400 dark:hover:text-blue-300">
                    <i class="fas fa-arrow-left mr-1 sm:mr-2 text-xs sm:text-sm"></i> Continuer les achats
                </a>
            </div>
        </div>
    </div>
</header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <h2 class="text-xl font-bold mb-4 dark:text">Votre Panier</h2>
            
            <?php if (empty($cartItems)): ?>
                <!-- Empty cart -->
                <div class="empty-cart">
                    <div class="empty-cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="empty-cart-title dark:text">Votre panier est vide</h3>
                    <p class="empty-cart-text">Parcourez nos produits et ajoutez des articles à votre panier</p>
                    <a href="index.php" class="shop-btn">
                        <i class="fas fa-store"></i> Commencer mes achats
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-container">
                    <!-- Cart items -->
                    <div class="cart-list">
                        <?php foreach ($cartItems as $item): 
                            $stockBadgeClass = '';
                            if ($item['stock_quantity'] > 10) {
                                $stockBadgeClass = 'stock-high';
                            } elseif ($item['stock_quantity'] > 0) {
                                $stockBadgeClass = 'stock-medium';
                            } else {
                                $stockBadgeClass = 'stock-low';
                            }
                        ?>
                            <div class="cart-item">
                                <div class="cart-item-image-container">
                                    <img src="../assets/images/<?php echo htmlspecialchars($item['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="cart-item-image"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIyNSIgdmlld0JveD0iMCAwIDMwMCAyMjUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjI1IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0xMTIuNSA4NC41QzExMi41IDc4LjAyODQgMTE3Ljc4MiA3Mi43NSAxMjQuMjUgNzIuNzVDMTMwLjcxOCA3Mi43NSAxMzYgNzguMDI4NCAxMzYgODQuNUMxMzYgOTAuOTcxNiAxMzAuNzE4IDk2LjI1IDEyNC4yNSA5Ni4yNUMxMTcuNzgyIDk2LjI1IDExMi41IDkwLjk3MTYgMTEyLjUgODQuNVoiIGZpbGw9IiNEOEQ4RDgiLz4KPHBhdGggZD0iTTE4NSA5NEgxNjMuNUMxNjEuMDEzIDk4IDE1OSA5Ni4wMTM0IDE1OSA5OC41VjEzN0MxNTkgMTM5LjQ4NyAxNjEuMDEzIDE0MS41IDE2My41IDE0MS41SDE4NUMxODcuNDg3IDE0MS41IDE5MCAxMzkuNDg3IDE5MCAxMzdWOTguNUMxOTAgOTYuMDEzNCAxODcuNDg3IDk0IDE4NSA5NFoiIGZpbGw9IiNEOEQ4RDgiLz4KPC9zdmc+Cg=='">
                                </div>
                                
                                <div class="cart-item-info">
                                    <div>
                                        <h3 class="cart-item-title dark:text"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <p class="cart-item-price"><?php echo number_format($item['price'], 2); ?> DH</p>
                                        
                                        <div class="stock-badge <?php echo $stockBadgeClass; ?>">
                                            <?php if ($item['stock_quantity'] > 10): ?>
                                                <i class="fas fa-check-circle mr-1"></i> En stock
                                            <?php elseif ($item['stock_quantity'] > 0): ?>
                                                <i class="fas fa-exclamation-circle mr-1"></i> Plus que <?php echo $item['stock_quantity']; ?> disponibles
                                            <?php else: ?>
                                                <i class="fas fa-times-circle mr-1"></i> Rupture de stock
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="cart-item-actions">
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <div class="quantity-selector">
                                                <button type="button" class="quantity-btn minus" onclick="decrementQuantity(this)">-</button>
                                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                                       min="1" max="<?php echo $item['stock_quantity']; ?>" class="quantity-input">
                                                <button type="button" class="quantity-btn plus" onclick="incrementQuantity(this)">+</button>
                                            </div>
                                            <button type="submit" name="update_cart" class="update-btn" title="Mettre à jour">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <button type="submit" name="remove_from_cart" class="remove-btn" title="Retirer l'article">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Clear cart button -->
                        <form method="POST" class="text-right mt-4">
                            <button type="submit" name="clear_cart" 
                                    class="text-red-600 hover:text-red-800 text-sm flex items-center justify-end transition-colors dark:text-red-400 dark:hover:text-red-300"
                                    onclick="return confirm('Êtes-vous sûr de vouloir vider votre panier ?')">
                                <i class="fas fa-trash mr-2"></i> Vider le panier
                            </button>
                        </form>
                    </div>
                    
                    <!-- Order summary -->
                    <div class="order-summary">
                        <h3 class="text-lg font-bold mb-4 dark:text">Résumé de la commande</h3>
                        
                        <div class="space-y-3">
                            <div class="summary-item dark:text" style="display: flex; justify-content: space-between; gap: 2rem;">
                                <span>Sous-total (<?php echo $cartCount; ?> articles)</span>
                                <span class="font-semibold"><?php echo number_format($cartTotal, 2); ?> DH</span>
                            </div>
                            
                            <div class="summary-item text-green-600" style="margin-bottom: 1rem;">
                                <span>Livraison Gratuite</span>
                            </div>
                            <div class="summary-total dark:text" style="display: flex; justify-content: space-between; gap: 2rem;">
                                <span>Total</span>
                                <span><?php echo number_format($cartTotal, 2); ?> DH</span>
                            </div>
                        </div>
                        
                        <a href="checkout.php" class="checkout-btn">
                            <i class="fas fa-lock mr-2"></i> Procéder au paiement
                        </a>
                        
                        <a href="index.php" class="continue-shopping">
                            <i class="fas fa-arrow-left mr-2"></i> Continuer mes achats
                        </a>
                        
                        <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-shield-alt mr-1"></i> Paiement sécurisé
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Desktop Footer -->
    <?php include '../assets/part/footer.php'  ?>
    

    <!-- Bottom Navigation (Mobile) -->
    <?php include '../assets/part/nav-mobil.php'; ?>
    
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
            
            // Show notifications
            const successNotification = document.getElementById('success-notification');
            const errorNotification = document.getElementById('error-notification');
            
            if (successNotification) {
                successNotification.classList.add('show');
                setTimeout(() => {
                    successNotification.classList.remove('show');
                    setTimeout(() => successNotification.remove(), 300);
                }, 3000);
            }
            
            if (errorNotification) {
                errorNotification.classList.add('show');
                setTimeout(() => {
                    errorNotification.classList.remove('show');
                    setTimeout(() => errorNotification.remove(), 300);
                }, 3000);
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
        
        // Quantity buttons functionality
        function incrementQuantity(button) {
            const input = button.parentElement.querySelector('.quantity-input');
            const max = parseInt(input.getAttribute('max'));
            let value = parseInt(input.value);
            
            if (isNaN(value)) value = 0;
            if (value < max) {
                input.value = value + 1;
            }
        }
        
        function decrementQuantity(button) {
            const input = button.parentElement.querySelector('.quantity-input');
            const min = parseInt(input.getAttribute('min'));
            let value = parseInt(input.value);
            
            if (isNaN(value)) value = 1;
            if (value > min) {
                input.value = value - 1;
            }
        }
        
        // Prevent form submission when pressing enter on quantity input
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>