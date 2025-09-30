<?php
session_start();
require_once '../../config/db.php';
require_once '../../controllers/AdminController.php';
require_once '../../controllers/ProductController.php';
require_once '../../controllers/OrderController.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$pdo = $db->connect();
$adminController = new AdminController();
$productController = new ProductController($pdo);
$orderController = new OrderController($pdo);

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gérer les soumissions de formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Token CSRF invalide');
    }
    
    if (isset($_POST['add_product'])) {
        $adminController->addProduct($_POST, $_FILES);
    } elseif (isset($_POST['edit_product'])) {
        $adminController->updateProduct($_POST['id'], $_POST, $_FILES);
    } elseif (isset($_POST['delete_product'])) {
        $adminController->deleteProduct($_POST['id']);
    } elseif (isset($_POST['add_admin'])) {
        $adminController->createAdmin($_POST['admin_username'], $_POST['admin_password']);
    } elseif (isset($_POST['delete_admin'])) {
        $adminController->deleteAdmin($_POST['admin_id']);
    } elseif (isset($_POST['change_password'])) {
        $adminController->changePassword(
            $_POST['admin_id'], 
            $_POST['current_password'], 
            $_POST['new_password']
        );
    } elseif (isset($_POST['update_status'])) {
        $orderId = $_POST['order_id'];
        $newStatus = $_POST['status'];
        
        // Mettre à jour le statut de la commande
        if ($orderController->updateOrderStatus($orderId, $newStatus)) {
            $_SESSION['success'] = "Statut de la commande mis à jour avec succès !";
        } else {
            $_SESSION['error'] = "Échec de la mise à jour du statut de la commande.";
        }
    }
    
    // Actualiser la page pour afficher les mises à jour
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$products = $productController->getAllProducts();
$orders = $orderController->getAllOrders();
$admins = $adminController->getAllAdmins();
$adminCount = count($admins);

// Obtenir les compteurs de statut pour les statistiques
$statusCounts = [
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($orders as $order) {
    $status = $order['status'] ?? 'pending';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panneau d'Administration - Monster store</title>
    <link rel="icon" href="../../assets/images/logo/logo.jpg" type="image/x-icon">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #3B82F6;
            --secondary-color: #10B981;
            --accent-color: #8B5CF6;
            --danger-color: #EF4444;
            --warning-color: #F59E0B;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        
        .sidebar {
            width: 280px;
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #1e40af 0%, #3B82F6 100%);
            color: white;
        }
        
        .main-content {
            margin-left: 280px;
            transition: all 0.3s ease;
        }
        
        /* Mobile First Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
                height: 100vh;
                top: 0;
                left: 0;
                width: 85%;
                max-width: 300px;
            }
            
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 50px rgba(0,0,0,0.3);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            /* Overlay for mobile */
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 40;
            }
            
            .overlay.active {
                display: block;
            }
        }
        
        .nav-link {
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            margin: 0.25rem 0.5rem;
        }
        
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
        }
        
        .nav-link:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Mobile Optimized Stats Cards */
        .stats-card {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 1.5rem;
        }
        
        .stats-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Mobile Tabs */
        .tab-button {
            transition: all 0.3s ease;
            position: relative;
            padding: 1rem 1.5rem;
            font-size: 0.9rem;
        }
        
        .tab-button.active {
            color: var(--primary-color);
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        /* Mobile Product Cards */
        .product-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            background: white;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Mobile Order Cards */
        .order-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .order-card-body {
            display: grid;
            gap: 0.75rem;
        }
        
        .order-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-card-label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .order-card-value {
            color: #6b7280;
            text-align: right;
            font-size: 0.875rem;
        }
        
        .modal {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 100;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            transform: scale(0.9);
            transition: all 0.3s ease;
            margin: 1rem;
            width: calc(100% - 2rem);
        }
        
        .modal.active .modal-content {
            transform: scale(1);
        }
        
        .btn {
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #2563eb 100%);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #059669 100%);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 12px;
            color: white;
            z-index: 1000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            max-width: calc(100% - 40px);
        }
        
        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
            border: 1px solid #93C5FD;
        }
        
        .status-completed {
            background-color: #D1FAE5;
            color: #065F46;
            border: 1px solid #34D399;
        }
        
        .status-cancelled {
            background-color: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FCA5A5;
        }
        
        /* Mobile Image Improvements */
        .product-image-container {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            height: 200px;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: flex-end;
            padding: 16px;
            color: white;
        }
        
        .product-card:hover .image-overlay {
            opacity: 1;
        }
        
        /* Enhanced Mobile Responsive */
        @media (max-width: 640px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .order-table {
                display: none;
            }
            
            .order-cards {
                display: block;
            }
            
            .modal-content {
                margin: 0.5rem;
                width: calc(100% - 1rem);
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .stats-card {
                padding: 1.25rem;
            }
            
            .stats-card .flex {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-card .ml-4 {
                margin-left: 0;
                margin-top: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .tab-button {
                padding: 0.75rem 1rem;
                font-size: 0.8rem;
            }
            
            .order-table {
                display: none;
            }
            
            .order-cards {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .order-cards {
                display: none;
            }
            
            .order-table {
                display: table;
            }
        }
        
        @media (min-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (min-width: 1280px) {
            .product-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        /* Mobile Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        .animate-slide-in-right {
            animation: slideInRight 0.5s ease-out forwards;
        }
        
        /* Mobile Form Styles */
        .form-input {
            border: 1px solid #D1D5DB;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px; /* Better for mobile */
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        /* Stock Badge Mobile */
        .stock-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .stock-high {
            background-color: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .stock-medium {
            background-color: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }
        
        .stock-low {
            background-color: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FECACA;
        }
        
        /* Mobile Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Mobile Header Improvements */
        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .mobile-menu-btn {
            padding: 0.5rem;
            border-radius: 8px;
            background: #f3f4f6;
        }
        
        /* Touch Friendly Sizes */
        @media (max-width: 768px) {
            .btn {
                min-height: 44px;
                min-width: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .tab-button {
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        /* Swipe gestures for mobile */
        .swipe-area {
            touch-action: pan-y;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Overlay -->
    <div id="overlay" class="overlay" onclick="closeSidebar()"></div>

    <!-- Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification gradient-bg show animate-slide-in-right">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-white text-lg mr-3"></i>
                <span class="text-white font-medium"><?php echo $_SESSION['success']; ?></span>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification bg-red-500 show animate-slide-in-right">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-white text-lg mr-3"></i>
                <span class="text-white font-medium"><?php echo $_SESSION['error']; ?></span>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include 'assets/slide.php'; ?>
        
        <!-- Main content -->
        <div class="main-content w-full min-h-screen">
            <!-- Mobile Header -->
            <header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center glass-effect lg:hidden">
            
                
                <h1 class="text-xl font-bold text-gray-800">Tableau de Bord</h1>
                
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                        <span class="text-white font-bold"><?php echo substr($_SESSION['admin_username'], 0, 1); ?></span>
                    </div>
                </div>
            </header>

            <!-- Desktop Header -->
            <header class="bg-white shadow-sm py-4 px-6 justify-between items-center glass-effect hidden lg:flex">
                <div></div> <!-- Spacer for alignment -->
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200 transition-colors">
                            <i class="fas fa-bell text-gray-600"></i>
                            <?php if (count($orders) > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                                    <?php echo count($orders); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                            <span class="text-white font-bold"><?php echo substr($_SESSION['admin_username'], 0, 1); ?></span>
                        </div>
                        <span class="text-gray-700 font-medium"><?php echo $_SESSION['admin_username']; ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Main content area -->
            <main class="p-4 md:p-6 swipe-area">
                <!-- Stats overview - Mobile Optimized -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8 stats-grid">
                    <div class="stats-card rounded-2xl p-4 md:p-6 text-white animate-fade-in-up" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl md:text-3xl font-bold"><?php echo count($products); ?></h3>
                                <p class="text-blue-100 text-sm md:text-base">Produits Total</p>
                            </div>
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-box text-xl md:text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-4 md:p-6 text-white animate-fade-in-up" style="animation-delay: 0.2s; background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl md:text-3xl font-bold"><?php echo count($orders); ?></h3>
                                <p class="text-green-100 text-sm md:text-base">Commandes Total</p>
                            </div>
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-shopping-cart text-xl md:text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-4 md:p-6 text-white animate-fade-in-up" style="animation-delay: 0.3s; background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl md:text-3xl font-bold"><?php echo $adminCount; ?></h3>
                                <p class="text-purple-100 text-sm md:text-base">Administrateurs</p>
                            </div>
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-users text-xl md:text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs navigation - Mobile Optimized -->
                <div class="bg-white rounded-2xl shadow-md mb-6 glass-effect overflow-x-auto">
                    <div class="border-b border-gray-200">
                        <nav class="flex min-w-max">
                            <button data-tab="products" class="tab-button py-4 px-4 md:px-6 font-medium text-gray-600 whitespace-nowrap active">
                                <i class="fas fa-box mr-2"></i> <span class="hidden sm:inline">Produits</span>
                                <span class="sm:hidden">Prod</span>
                            </button>
                            <button data-tab="orders" class="tab-button py-4 px-4 md:px-6 font-medium text-gray-600 whitespace-nowrap">
                                <i class="fas fa-shopping-cart mr-2"></i> <span class="hidden sm:inline">Commandes</span>
                                <span class="sm:hidden">Cmd</span>
                            </button>
                            <button data-tab="admins" class="tab-button py-4 px-4 md:px-6 font-medium text-gray-600 whitespace-nowrap">
                                <i class="fas fa-users mr-2"></i> <span class="hidden sm:inline">Admins</span>
                                <span class="sm:hidden">Adm</span>
                            </button>
                        </nav>
                    </div>
                </div>
                
                <!-- Products Tab -->
                <div id="products" class="tab-content active">
                    <!-- Add Product Button -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-2xl font-bold text-gray-800">Gestion des Produits</h2>
                        <button onclick="openModal('addProductModal')" 
                                class="btn btn-primary px-6 py-3 text-white rounded-xl font-medium w-full sm:w-auto">
                            <i class="fas fa-plus mr-2"></i> Ajouter un Produit
                        </button>
                    </div>
                    
                    <!-- Products List -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6 product-grid">
                        <?php if (empty($products)): ?>
                            <div class="col-span-full text-center py-12">
                                <div class="bg-white rounded-2xl p-8 shadow-md">
                                    <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun produit trouvé</h3>
                                    <p class="text-gray-500 mb-4">Commencez par ajouter votre premier produit</p>
                                    <button onclick="openModal('addProductModal')" 
                                            class="btn btn-primary px-6 py-3 text-white rounded-xl font-medium w-full sm:w-auto">
                                        <i class="fas fa-plus mr-2"></i> Ajouter un produit
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($products as $product): 
                                // Déterminer la classe du badge de stock
                                $stockClass = 'stock-high';
                                $stockIcon = 'check';
                                if ($product['quantity'] <= 5) {
                                    $stockClass = 'stock-low';
                                    $stockIcon = 'exclamation-triangle';
                                } elseif ($product['quantity'] <= 15) {
                                    $stockClass = 'stock-medium';
                                    $stockIcon = 'exclamation-circle';
                                }
                            ?>
                                <div class="product-card bg-white animate-fade-in-up">
                                    <div class="product-image-container">
                                        <img src="../../assets/images/<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             class="product-image"
                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjNmNGY2Ii8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iOWNhMGFiIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5JbWFnZSBub24gZGlzcG9uaWJsZTwvdGV4dD48L3N2Zz4='">
                                        <div class="image-overlay">
                                            <div>
                                                <span class="stock-badge <?php echo $stockClass; ?>">
                                                    <i class="fas fa-<?php echo $stockIcon; ?> mr-1"></i>
                                                    <?php echo $product['quantity']; ?> en stock
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-4">
                                        <h3 class="font-semibold text-lg mb-1 text-gray-800 truncate"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo htmlspecialchars($product['description']); ?></p>
                                        
                                        <div class="flex justify-between items-center mb-4">
                                            <span class="text-2xl font-bold text-blue-600"><?php echo number_format($product['price'], 2); ?> DH</span>
                                            <span class="text-sm text-gray-500 hidden sm:block">ID: <?php echo $product['id']; ?></span>
                                        </div>
                                        
                                        <div class="action-buttons">
                                            <button onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                    class="flex-1 bg-blue-100 text-blue-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-blue-200 transition-colors flex items-center justify-center">
                                                <i class="fas fa-edit mr-1"></i> <span class="hidden sm:inline">Modifier</span>
                                                <span class="sm:hidden">Modif</span>
                                            </button>
                                            <button onclick="confirmDelete(<?php echo $product['id']; ?>)" 
                                                    class="flex-1 bg-red-100 text-red-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors flex items-center justify-center">
                                                <i class="fas fa-trash mr-1"></i> <span class="hidden sm:inline">Supprimer</span>
                                                <span class="sm:hidden">Supp</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Orders Tab -->
                <div id="orders" class="tab-content">
                    <div class="bg-white rounded-2xl shadow-md p-4 md:p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">Commandes Récentes</h2>
                        
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-shopping-cart text-4xl text-gray-400 mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucune commande pour le moment</h3>
                                <p class="text-gray-500">Les commandes apparaîtront ici lorsque les clients effectueront des achats</p>
                            </div>
                        <?php else: ?>
                            <!-- Desktop Table View -->
                            <div class="overflow-x-auto rounded-2xl hidden md:block">
                                <table class="w-full order-table">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-4 px-6 text-left text-sm font-semibold text-gray-600">Code Commande</th>
                                            <th class="py-4 px-6 text-left text-sm font-semibold text-gray-600">Produit</th>
                                            <th class="py-4 px-6 text-left text-sm font-semibold text-gray-600">Qté</th>
                                            <th class="py-4 px-6 text-left text-sm font-semibold text-gray-600">Client</th>
                                            <th class="py-4 px-6 text-left text-sm font-semibold text-gray-600">Total</th>
                                            <th class="py-4 px-6 text-left text-sm font-semibold text-gray-600">Statut</th>
                                            <th class="py-4 px-6 text-left text-sm font-semibold text-gray-600">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recentOrders = array_slice($orders, 0, 5);
                                        foreach ($recentOrders as $order): 
                                            $quantity = $order['quantity'] ?? 0;
                                            $price = $order['product_price'] ?? 0;
                                            $total = $quantity * $price;
                                            $productName = htmlspecialchars($order['product_name'] ?? 'Produit Inconnu');
                                            $customerName = htmlspecialchars($order['customer_name'] ?? '');
                                            $customerEmail = htmlspecialchars($order['customer_email'] ?? '');
                                            $image = $order['product_image'] ?? 'placeholder.png';
                                            $status = $order['status'] ?? 'pending';
                                        ?>
                                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                                <td class="py-4 px-6 font-mono text-sm text-blue-600"><?php echo $order['order_code'] ?? 'N/A'; ?></td>
                                                <td class="py-4 px-6">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-gray-200 rounded-md overflow-hidden mr-3">
                                                            <img src="../../assets/images/<?php echo $image; ?>" alt="<?php echo $productName; ?>" class="w-full h-full object-cover">
                                                        </div>
                                                        <span class="text-sm font-medium truncate max-w-xs"><?php echo $productName; ?></span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-6 text-center">
                                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-medium">
                                                        <?php echo $quantity; ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <div>
                                                        <p class="text-sm font-medium truncate"><?php echo $customerName; ?></p>
                                                        <p class="text-xs text-gray-500 truncate"><?php echo $customerEmail; ?></p>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-6 font-semibold text-green-600"><?php echo number_format($total, 2); ?> DH</td>
                                                <td class="py-4 px-6">
                                                    <span class="status-badge status-<?php echo $status; ?>">
                                                        <i class="fas fa-circle text-xs mr-1"></i>
                                                        <?php 
                                                        $statusLabels = [
                                                            'pending' => 'En attente',
                                                            'processing' => 'En traitement',
                                                            'completed' => 'Terminée',
                                                            'cancelled' => 'Annulée'
                                                        ];
                                                        echo $statusLabels[$status] ?? ucfirst($status); 
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6 text-sm text-gray-500"><?php echo isset($order['created_at']) ? date('d/m/Y', strtotime($order['created_at'])) : ''; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Mobile Cards View -->
                            <div class="order-cards md:hidden space-y-4">
                                <?php 
                                $recentOrders = array_slice($orders, 0, 5);
                                foreach ($recentOrders as $order): 
                                    $quantity = $order['quantity'] ?? 0;
                                    $price = $order['product_price'] ?? 0;
                                    $total = $quantity * $price;
                                    $productName = htmlspecialchars($order['product_name'] ?? 'Produit Inconnu');
                                    $customerName = htmlspecialchars($order['customer_name'] ?? '');
                                    $status = $order['status'] ?? 'pending';
                                ?>
                                    <div class="order-card animate-fade-in-up">
                                        <div class="order-card-header">
                                            <div>
                                                <span class="font-bold text-lg text-blue-600"><?php echo $order['order_code'] ?? 'N/A'; ?></span>
                                                <span class="status-badge status-<?php echo $status; ?> ml-2">
                                                    <i class="fas fa-circle text-xs mr-1"></i>
                                                    <?php 
                                                    $statusLabels = [
                                                        'pending' => 'En attente',
                                                        'processing' => 'En traitement',
                                                        'completed' => 'Terminée',
                                                        'cancelled' => 'Annulée'
                                                    ];
                                                    echo $statusLabels[$status] ?? ucfirst($status); 
                                                    ?>
                                                </span>
                                            </div>
                                            <span class="font-bold text-green-600"><?php echo number_format($total, 2); ?> DH</span>
                                        </div>
                                        
                                        <div class="order-card-body">
                                            <div class="order-card-row">
                                                <span class="order-card-label">Produit</span>
                                                <span class="order-card-value truncate"><?php echo $productName; ?></span>
                                            </div>
                                            <div class="order-card-row">
                                                <span class="order-card-label">Quantité</span>
                                                <span class="order-card-value"><?php echo $quantity; ?></span>
                                            </div>
                                            <div class="order-card-row">
                                                <span class="order-card-label">Client</span>
                                                <span class="order-card-value truncate"><?php echo $customerName; ?></span>
                                            </div>
                                            <div class="order-card-row">
                                                <span class="order-card-label">Date</span>
                                                <span class="order-card-value"><?php echo isset($order['created_at']) ? date('d/m/Y', strtotime($order['created_at'])) : ''; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-6 text-center">
                                <a href="orders.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                                    Voir toutes les commandes <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
              
            </main>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="modal-content bg-white rounded-2xl w-full max-w-2xl">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">Ajouter un Nouveau Produit</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="form-label">Nom du Produit</label>
                        <input type="text" name="name" required class="form-input">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" required rows="3" class="form-input"></textarea>
                    </div>
                    
                    <div>
                        <label class="form-label">Prix (DH)</label>
                        <input type="number" name="price" step="0.01" required class="form-input">
                    </div>
                    
                    <div>
                        <label class="form-label">Quantité</label>
                        <input type="number" name="quantity" min="0" required class="form-input">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Image du Produit</label>
                        <input type="file" name="image" accept="image/*" required class="form-input">
                        <p class="text-xs text-gray-500 mt-2">Formats supportés: JPG, PNG, GIF. Taille max: 2MB</p>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addProductModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors order-2 sm:order-1">
                        Annuler
                    </button>
                    <button type="submit" name="add_product" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors order-1 sm:order-2">
                        <i class="fas fa-plus mr-2"></i> Ajouter le Produit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="modal-content bg-white rounded-2xl w-full max-w-2xl">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">Modifier le Produit</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="editProductId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="form-label">Nom du Produit</label>
                        <input type="text" name="name" id="editProductName" required class="form-input">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editProductDescription" required rows="3" class="form-input"></textarea>
                    </div>
                    
                    <div>
                        <label class="form-label">Prix (DH)</label>
                        <input type="number" name="price" id="editProductPrice" step="0.01" required class="form-input">
                    </div>
                    
                    <div>
                        <label class="form-label">Quantité</label>
                        <input type="number" name="quantity" id="editProductQuantity" min="0" required class="form-input">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Image Actuelle</label>
                        <div class="mt-2">
                            <img id="editProductImagePreview" src="" alt="Image actuelle" class="w-32 h-32 object-cover rounded-xl border border-gray-300">
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Nouvelle Image (Optionnel)</label>
                        <input type="file" name="image" accept="image/*" class="form-input">
                        <p class="text-xs text-gray-500 mt-2">Laissez vide pour conserver l'image actuelle</p>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 pt-4">
                    <button type="button" onclick="closeModal('editProductModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors order-2 sm:order-1">
                        Annuler
                    </button>
                    <button type="submit" name="edit_product" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors order-1 sm:order-2">
                        <i class="fas fa-save mr-2"></i> Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="modal-content bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">Confirmer la Suppression</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-6">Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="deleteId">
                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3">
                        <button type="button" onclick="closeModal('deleteConfirmModal')" 
                                class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors order-2 sm:order-1">
                            Annuler
                        </button>
                        <button type="submit" name="delete_product" 
                                class="px-6 py-3 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition-colors order-1 sm:order-2">
                            <i class="fas fa-trash mr-2"></i> Supprimer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar functions
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
        }

        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function openEditProductModal(product) {
            document.getElementById('editProductId').value = product.id;
            document.getElementById('editProductName').value = product.name;
            document.getElementById('editProductDescription').value = product.description;
            document.getElementById('editProductPrice').value = product.price;
            document.getElementById('editProductQuantity').value = product.quantity;
            document.getElementById('editProductImagePreview').src = '../../assets/images/' + product.image;
            openModal('editProductModal');
        }

        function openPasswordModal(adminId) {
            document.getElementById('adminId').value = adminId;
            openModal('passwordModal');
        }

        function confirmDelete(productId) {
            document.getElementById('deleteId').value = productId;
            document.getElementById('deleteForm').querySelector('button[type="submit"]').name = 'delete_product';
            openModal('deleteConfirmModal');
        }

        function confirmAdminDelete(adminId) {
            document.getElementById('deleteId').value = adminId;
            document.getElementById('deleteForm').querySelector('button[type="submit"]').name = 'delete_admin';
            openModal('deleteConfirmModal');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    closeModal(modal.id);
                });
                closeSidebar();
            }
        });

        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            // Set first tab as active by default
            if (tabButtons.length > 0) {
                tabButtons[0].classList.add('active');
            }
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Update active button
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show active tab
                    tabContents.forEach(content => content.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    toggleSidebar();
                });
            }
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    toggleSidebar();
                });
            }

            // Auto-hide notifications
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }, 5000);
            });

            // Image validation
            const imageInputs = document.querySelectorAll('input[type="file"]');
            imageInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        if (file.size > 2097152) {
                            alert('Fichier trop volumineux. La taille maximale est de 2MB');
                            this.value = '';
                            return;
                        }
                        
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Seuls les fichiers JPG, PNG et GIF sont autorisés');
                            this.value = '';
                            return;
                        }
                    }
                });
            });

            // Touch gestures for mobile
            let startX = 0;
            let endX = 0;

            document.addEventListener('touchstart', function(e) {
                startX = e.changedTouches[0].screenX;
            });

            document.addEventListener('touchend', function(e) {
                endX = e.changedTouches[0].screenX;
                handleSwipe();
            });

            function handleSwipe() {
                const diff = startX - endX;
                const sidebar = document.querySelector('.sidebar');
                
                // Swipe right to open sidebar
                if (diff < -50 && startX < 50 && !sidebar.classList.contains('active')) {
                    toggleSidebar();
                }
                // Swipe left to close sidebar
                else if (diff > 50 && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            }

            // Prevent body scroll when sidebar is open
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        const sidebar = document.querySelector('.sidebar');
                        if (sidebar.classList.contains('active')) {
                            document.body.style.overflow = 'hidden';
                        } else {
                            document.body.style.overflow = 'auto';
                        }
                    }
                });
            });

            observer.observe(document.querySelector('.sidebar'), {
                attributes: true,
                attributeFilter: ['class']
            });
        });

        // Smooth scrolling for better UX
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

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>