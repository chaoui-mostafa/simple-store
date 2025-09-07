<?php
session_start();
require_once '../config/db.php';
require_once '../controllers/ProductController.php';
require_once '../controllers/CartController.php';

// Check current theme
$currentTheme = 'light';
if (isset($_COOKIE['theme'])) {
    $currentTheme = $_COOKIE['theme'];
} elseif (isset($_SESSION['theme'])) {
    $currentTheme = $_SESSION['theme'];
}

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
<html lang="fr" class="<?php echo $currentTheme === 'dark' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monster Store</title>
    <link rel="icon" href="../assets/images/logo/logo.jpg" type="image/x-icon">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
    :root {
        --bg-primary: #ffffff;
        --bg-secondary: #f8f9fa;
        --text-primary: #1F2937;
        --text-secondary: #6B7280;
        --border-color: #e5e7eb;
        --card-bg: #ffffff;
    }

    .dark {
        --bg-primary: #111827;
        --bg-secondary: #1f2937;
        --text-primary: #f9fafb;
        --text-secondary: #d1d5db;
        --border-color: #374151;
        --card-bg: #1f2937;
    }

    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        transition: background-color 0.3s, color 0.3s;
    }
    
    /* Base styles for all devices */
    .product-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        padding: 1rem 0;
    }
    
    .product-item {
        display: flex;
        flex-direction: column;
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
    }
    
    .product-image-container {
        position: relative;
        width: 100%;
        overflow: hidden;
        background-color: var(--bg-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .product-info {
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    
    .product-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }
    
    .product-description {
        color: var(--text-secondary);
        margin-bottom: 1rem;
        flex-grow: 1;
    }
    
    .product-price {
        font-size: 1.25rem;
        font-weight: 700;
        color: #3B82F6;
        margin-bottom: 1.25rem;
    }
    
    .product-actions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .quantity-selector {
        display: flex;
        align-items: center;
        border-radius: 10px;
        overflow: hidden;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        border: none;
    }
    
    .quantity-btn {
        width: 40px;
        height: 40px;
        background: transparent;
        color: white;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .quantity-input {
        width: 50px;
        height: 40px;
        text-align: center;
        font-size: 1rem;
        font-weight: 600;
        border: none;
        background: transparent;
        color: white;
    }
    
    .quantity-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    /* Add to Cart Button */
    .add-to-cart-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .add-to-cart-btn:hover {
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        transform: translateY(-2px);
    }
    
    .view-details-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .view-details-btn:hover {
        background: var(--border-color);
    }
    
    /* Stock badges */
    .stock-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        color: white;
    }
    
    .stock-high {
        background-color: #10B981;
    }
    
    .stock-medium {
        background-color: #F59E0B;
    }
    
    .stock-low {
        background-color: #EF4444;
    }
    
    /* Notification styles */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 1000;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        animation: slideIn 0.3s ease;
    }
    
    .notification.success {
        background: #10B981;
    }
    
    .notification.error {
        background: #EF4444;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* Mobile Responsive Product Cards */
    @media (max-width: 768px) {
        .product-list {
            grid-template-columns: 1fr !important;
            gap: 1rem;
            padding: 0.5rem;
        }

        .product-item {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 0.5rem;
        }

        .product-image-container {
            height: 200px;
            position: relative;
        }

        .product-info {
            padding: 1rem;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .product-description {
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #3b82f6;
        }

        .product-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border: none;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 600;
            color: #fff;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .quantity-input {
            width: 50px;
            height: 40px;
            border: none;
            text-align: center;
            font-size: 1rem;
            font-weight: 600;
            background: transparent;
            color: #fff;
        }

        /* Add to Cart Button */
        .add-to-cart-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            min-height: 44px;
            transition: all 0.3s ease;
        }

        .add-to-cart-btn:hover {
            background: #2563eb;
        }

        /* View Details Button */
        .view-details-btn {
            width: 100%;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-details-btn:hover {
            background: var(--border-color);
        }
    }

    /* Announcement slider styles */
    .announcement-slider {
        width: 100%;
        max-width: 1000px;
        position: relative;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        margin: 20px auto;
    }
    
    .slides-container {
        display: flex;
        transition: transform 0.5s ease;
        height: 300px;
    }
    
    .slide {
        min-width: 100%;
        display: flex;
        align-items: center;
        padding: 40px;
        background-size: cover;
        background-position: center;
    }
    
    .slide-1 {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .slide-2 {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    .slide-3 {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .slide-content {
        max-width: 500px;
        color: white;
    }
    
    .slide h2 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .slide p {
        font-size: 1.1rem;
        margin-bottom: 25px;
        opacity: 0.9;
    }
    
    .btn {
        display: inline-block;
        padding: 12px 28px;
        background: white;
        color: #3b82f6;
        font-weight: 600;
        border-radius: 50px;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }
    
    .slider-nav {
        position: absolute;
        bottom: 20px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        gap: 10px;
        z-index: 10;
    }
    
    .slider-arrows {
        position: absolute;
        top: 50%;
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 0 20px;
        transform: translateY(-50%);
        z-index: 10;
    }
    
    .arrow {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(5px);
    }
    
    .arrow:hover {
        background: rgba(255, 255, 255, 0.5);
    }
    
    @media (max-width: 768px) {
        .slide {
            padding: 30px;
            text-align: center;
            justify-content: center;
        }
        
        .slide h2 {
            font-size: 1.7rem;
        }
        
        .slide-content {
            max-width: 100%;
        }
        
        .arrow {
            width: 44px;
            height: 44px;
            font-size: 1.2rem;
        }
        
        .slides-container {
            height: 250px;
        }
    }
    </style>
    
</head>
<body>
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
    <?php 
    // Pass the current theme to the navigation
    $GLOBALS['currentTheme'] = $currentTheme;
    include '../assets/part/home/nav.php'; 
    ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container mx-auto px-4 py-8">
            <!-- Announcement Slider -->
            <div class="announcement-slider">
                <div class="slides-container">
                    <!-- Slide 1 - Your Hero Section -->
                    <div class="slide slide-1">
                        <div class="slide-content">
                            <h2>كولكسيون الصيف 2025</h2>
                            <p>شوفو آخر صيحات الموضة و ربحو تخفيض -30% فحوايج مختارين.</p>
                            <a href="#products" class="btn">شري دابا</a>
                        </div>
                    </div>
                    
                    <!-- Slide 2 -->
                    <div class="slide slide-2">
                        <div class="slide-content">
                            <h2>Nouveautés Exclusives</h2>
                            <p>Soyez le premier à découvrir notre nouvelle collection limitée. Livraison gratuite à partir de 50€ d'achat.</p>
                            <a href="#new" class="btn">Découvrir</a>
                        </div>
                    </div>
                    
                    <!-- Slide 3 -->
                    <div class="slide slide-3">
                        <div class="slide-content">
                            <h2>Soldes d'Été</h2>
                            <p>Profitez de nos soldes exceptionnelles avec jusqu'à -50% sur une sélection de produits.</p>
                            <a href="#sales" class="btn">Voir les offres</a>
                        </div>
                    </div>
                </div>
                
                <div class="slider-nav">
                    <div class="nav-dot active" data-slide="0"></div>
                    <div class="nav-dot" data-slide="1"></div>
                    <div class="nav-dot" data-slide="2"></div>
                </div>
                
                <div class="slider-arrows">
                    <div class="arrow prev"><i class="fas fa-chevron-left"></i></div>
                    <div class="arrow next"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>

            <h2 class="text-2xl font-bold mb-6 mt-8 text--800 dark:text" id="products">Nos Produits</h2>
            
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
                                     onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIyNSIgdmlld0JveD0iMCAwIDMwMCAyMjUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjI1IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0xMTIuNSA4NC41QzExMi41IDc4LjAyODQgMTE3Ljc4MiA3Mi43NSAxMjQuMjUgNzIuNzVDMTMwLjcxOCA3Mi43NSAxMzYgNzguMDI4NCAxMzYgODQuNUMxMzYgOTAuOTcxNiAxMzAuNzE4IDk2LjI1IDEyNC4yNSA5Ni4yNUMxMTcuNzgyIDk2LjI1IDExMi41IDkwLjk3MTYgMTEyLjUgODQuNVoiIGZpbGw9IiNEOEQ4RDgiLz4KPHBhdGggZD0iTTE4NSA5NEgxNjMuNUMxNjEuMDEzIDk0IDE1OSA5Ni4wMTM0IDE5IDk4LjVWMTM3QzE1OSAxMzkuNDg3IDE2MS4wMTMgMTQxLjUgMTYzLjUgMTQ1LjVIMTg1QzE4Ny40ODcgMTQxLjUgMTkwIDEzOS40ODcgMTkwIDEzN1Y5OC41QzE5MCA5Ni4wMTM0IDE4Ny40ODcgOTQgMTg1IDk0WiIgZmlsbD0iI0Q4RDhEOCIvPgo8L3N2Zz4K'">
                                
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
                                        Voir détails
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
    <?php 
    // Pass the current theme to the mobile navigation
    $GLOBALS['currentTheme'] = $currentTheme;
    include '../assets/part/nav-mobil.php'; 
    ?>
    
    <?php include "../assets/part/floatingCart.php"; ?>

    <!-- Include Footer -->
    <?php 
    // Pass the current theme to the footer
    $GLOBALS['currentTheme'] = $currentTheme;
    include '../assets/part/footer.php'; 
    ?>

    <!-- JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        // Theme management
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme from saved preference
            const savedTheme = localStorage.getItem('theme') || '<?php echo $currentTheme; ?>';
            const html = document.documentElement;
            
            if (savedTheme === 'dark') {
            html.classList.add('dark');
            } else {
            html.classList.remove('dark');
            }
            
            // Update theme icon
            updateThemeIcon(savedTheme);
            
            // Theme toggle button functionality
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const html = document.documentElement;
                const isDark = html.classList.contains('dark');
                const newTheme = isDark ? 'light' : 'dark';
                
                if (isDark) {
                html.classList.remove('dark');
                } else {
                html.classList.add('dark');
                }
                
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
                
                // Save to server
                fetch('set_theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'theme=' + newTheme
                }).then(() => {
                location.reload(); // Refresh page after theme switch
                });
            });
            }
            
            function updateThemeIcon(theme) {
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) {
                themeIcon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
            }
            
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
            
            // Announcement slider functionality
            const slidesContainer = document.querySelector('.slides-container');
            const slides = document.querySelectorAll('.slide');
            const dots = document.querySelectorAll('.nav-dot');
            const prevBtn = document.querySelector('.arrow.prev');
            const nextBtn = document.querySelector('.arrow.next');
            
            if (slidesContainer && slides.length > 0) {
                let currentSlide = 0;
                const slideCount = slides.length;
                
                // Function to update slider position
                function updateSlider() {
                    slidesContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
                    
                    // Update active dot
                    dots.forEach((dot, index) => {
                        if (index === currentSlide) {
                            dot.classList.add('active');
                        } else {
                            dot.classList.remove('active');
                        }
                    });
                }
                
                // Next slide function
                function nextSlide() {
                    currentSlide = (currentSlide + 1) % slideCount;
                    updateSlider();
                }
                
                // Previous slide function
                function prevSlide() {
                    currentSlide = (currentSlide - 1 + slideCount) % slideCount;
                    updateSlider();
                }
                
                // Event listeners for arrows
                if (nextBtn) nextBtn.addEventListener('click', nextSlide);
                if (prevBtn) prevBtn.addEventListener('click', prevSlide);
                
                // Event listeners for dots
                dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => {
                        currentSlide = index;
                        updateSlider();
                    });
                });
                
                // Auto slide every 5 seconds
                setInterval(nextSlide, 5000);
            }
        });
    </script>
</body>
</html>