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
    <link rel="icon" href="../assets/images/logo/logo.jpg" type="image/x-icon">

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
       <!-- Announcement Slider -->
<div class="announcement-slider">
    <div class="slides-container">
        <!-- Slide 1 - Your Hero Section -->
      <div class="slide slide-1">
    <div class="slide-content">
        <h2>كولكسيون الصيف 2025 </h2>
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

<style>
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
    
    .nav-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .nav-dot.active {
        background: white;
        transform: scale(1.2);
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
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
        
        .slides-container {
            height: 250px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const slidesContainer = document.querySelector('.slides-container');
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.nav-dot');
        const prevBtn = document.querySelector('.arrow.prev');
        const nextBtn = document.querySelector('.arrow.next');
        
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
        nextBtn.addEventListener('click', nextSlide);
        prevBtn.addEventListener('click', prevSlide);
        
        // Event listeners for dots
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentSlide = index;
                updateSlider();
            });
        });
        
        // Auto slide every 5 seconds
        setInterval(nextSlide, 5000);
    });
</script>

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