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
$adminController = new AdminController($pdo);
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
    <title>Panneau d'Administration - StyleShop</title>
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
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
                height: 100vh;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
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
        
        .stats-card {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .tab-button {
            transition: all 0.3s ease;
            position: relative;
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
        
        .product-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .modal {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            transform: scale(0.9);
            transition: all 0.3s ease;
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
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-completed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .status-cancelled {
            background-color: #FEE2E2;
            color: #B91C1C;
        }
        
        /* Améliorations pour l'affichage des images */
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
        
        /* Améliorations responsive */
        @media (max-width: 640px) {
            .product-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .order-table {
                font-size: 14px;
            }
            
            .order-table th, 
            .order-table td {
                padding: 8px 12px;
            }
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
        
        /* Animation pour les nouveaux éléments */
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
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        /* Style pour les formulaires */
        .form-input {
            border: 1px solid #D1D5DB;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            width: 100%;
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
        
        /* Badge pour le stock */
        .stock-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification gradient-bg show">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-white text-lg mr-3"></i>
                <span class="text-white font-medium"><?php echo $_SESSION['success']; ?></span>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification bg-red-500 show">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-white text-lg mr-3"></i>
                <span class="text-white font-medium"><?php echo $_SESSION['error']; ?></span>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="flex">
        <!-- Sidebar -->
        <div class="sidebar shadow-xl fixed h-full">
            <div class="p-6 border-b border-blue-400/20">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center glass-effect">
                        <span class="text-white font-bold text-lg"><?php echo substr($_SESSION['admin_username'], 0, 1); ?></span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">Panneau Admin</h1>
                        <p class="text-blue-100 text-sm">Bienvenue, <?php echo $_SESSION['admin_username']; ?></p>
                    </div>
                </div>
            </div>
            
            <nav class="mt-6 p-2">
                <a href="../index.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white hover:bg-white/10 transition-all duration-200">
                    <i class="fas fa-store mr-3"></i> Retour à la boutique
                </a>
                <a href="#" data-tab="products" class="nav-link tab-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-box mr-3"></i> Produits
                </a>
                <a href="#" data-tab="orders" class="nav-link tab-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i> Commandes
                </a>
                <a href="orders.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white hover:bg-white/10 transition-all duration-200">
                    <i class="fas fa-list mr-3"></i> Toutes les commandes
                </a>
                <a href="#" data-tab="admins" class="nav-link tab-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-users mr-3"></i> Administrateurs
                </a>
                <a href="logout.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white hover:bg-white/10 transition-all duration-200">
                    <i class="fas fa-sign-out-alt mr-3"></i> Déconnexion
                </a>
            </nav>
            
            <div class="absolute bottom-0 w-full p-4 border-t border-blue-400/20">
                <div class="text-center text-blue-200 text-sm">
                    <p>StyleShop Admin v1.0</p>
                </div>
            </div>
        </div>
        
        <!-- Main content -->
        <div class="main-content w-full min-h-screen">
            <!-- Top bar -->
            <header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center glass-effect">
                <button id="menu-toggle" class="md:hidden text-gray-600">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
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
                        <span class="text-gray-700 font-medium hidden md:block"><?php echo $_SESSION['admin_username']; ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Main content area -->
            <main class="p-6">
                <!-- Stats overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 stats-grid">
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.1s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-box text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo count($products); ?></h3>
                                <p class="text-blue-100">Produits Total</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.2s; background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-shopping-cart text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo count($orders); ?></h3>
                                <p class="text-green-100">Commandes Total</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.3s; background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo $adminCount; ?></h3>
                                <p class="text-purple-100">Administrateurs</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs navigation -->
                <div class="bg-white rounded-2xl shadow-md mb-6 glass-effect">
                    <div class="border-b border-gray-200">
                        <nav class="flex">
                            <button data-tab="products" class="tab-button py-4 px-6 font-medium text-gray-600 relative">
                                <i class="fas fa-box mr-2"></i> Produits
                            </button>
                            <button data-tab="orders" class="tab-button py-4 px-6 font-medium text-gray-600">
                                <i class="fas fa-shopping-cart mr-2"></i> Commandes
                            </button>
                            <button data-tab="admins" class="tab-button py-4 px-6 font-medium text-gray-600">
                                <i class="fas fa-users mr-2"></i> Admins
                            </button>
                        </nav>
                    </div>
                </div>
                
                <!-- Products Tab -->
                <div id="products" class="tab-content active">
                    <!-- Add Product Button -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Gestion des Produits</h2>
                        <button onclick="openModal('addProductModal')" 
                                class="btn btn-primary px-6 py-3 text-white rounded-xl font-medium">
                            <i class="fas fa-plus mr-2"></i> Ajouter un Produit
                        </button>
                    </div>
                    
                    <!-- Products List -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 product-grid">
                        <?php if (empty($products)): ?>
                            <div class="col-span-full text-center py-12">
                                <div class="bg-white rounded-2xl p-8 shadow-md">
                                    <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun produit trouvé</h3>
                                    <p class="text-gray-500">Commencez par ajouter votre premier produit</p>
                                    <button onclick="openModal('addProductModal')" 
                                            class="btn btn-primary mt-4 px-6 py-2 text-white">
                                        <i class="fas fa-plus mr-2"></i> Ajouter un produit
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($products as $product): 
                                // Déterminer la classe du badge de stock
                                $stockClass = 'stock-high';
                                if ($product['quantity'] <= 5) {
                                    $stockClass = 'stock-low';
                                } elseif ($product['quantity'] <= 15) {
                                    $stockClass = 'stock-medium';
                                }
                            ?>
                                <div class="product-card bg-white animate-fade-in-up">
                                    <div class="product-image-container">
                                        <img src="../../assets/images/<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             class="product-image">
                                        <div class="image-overlay">
                                            <div>
                                                <span class="stock-badge <?php echo $stockClass; ?>">
                                                    <?php echo $product['quantity']; ?> en stock
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-4">
                                        <h3 class="font-semibold text-lg mb-1 text-gray-800 truncate"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo htmlspecialchars($product['description']); ?></p>
                                        
                                        <div class="flex justify-between items-center mb-4">
                                            <span class="text-2xl font-bold text-blue-600"><?php echo number_format($product['price'], 2); ?> €</span>
                                            <span class="text-sm text-gray-500">ID: <?php echo $product['id']; ?></span>
                                        </div>
                                        
                                        <div class="flex space-x-2">
                                            <button onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                    class="flex-1 bg-blue-100 text-blue-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-blue-200 transition-colors">
                                                <i class="fas fa-edit mr-1"></i> Modifier
                                            </button>
                                            <button onclick="confirmDelete(<?php echo $product['id']; ?>)" 
                                                    class="flex-1 bg-red-100 text-red-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors">
                                                <i class="fas fa-trash mr-1"></i> Supprimer
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
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">Commandes Récentes</h2>
                        
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-shopping-cart text-4xl text-gray-400 mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucune commande pour le moment</h3>
                                <p class="text-gray-500">Les commandes apparaîtront ici lorsque les clients effectueront des achats</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto rounded-2xl">
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
                                                <td class="py-4 px-6 font-semibold text-green-600"><?php echo number_format($total, 2); ?> €</td>
                                                <td class="py-4 px-6">
                                                    <span class="status-badge status-<?php echo $status; ?>">
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
                            <div class="mt-6 text-center">
                                <a href="orders.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                                    Voir toutes les commandes <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Admins Tab -->
              
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($admins as $admin): ?>
                            <div class="bg-white rounded-2xl shadow-md p-6">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                                        <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($admin['username']); ?></h3>
                                        <p class="text-sm text-gray-500">ID: <?php echo $admin['id']; ?></p>
                                    </div>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Créé le:</span>
                                        <span class="text-gray-700"><?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Statut:</span>
                                        <span class="<?php echo $admin['id'] == 1 ? 'text-blue-600' : 'text-green-600'; ?> font-medium">
                                            <?php echo $admin['id'] == 1 ? 'Admin Principal' : 'Actif'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <?php if ($admin['id'] != 1): ?>
                                        <button onclick="openPasswordModal(<?php echo $admin['id']; ?>)" 
                                                class="flex-1 bg-blue-100 text-blue-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-blue-200 transition-colors">
                                            <i class="fas fa-key mr-1"></i> Mot de passe
                                        </button>
                                        <button onclick="confirmAdminDelete(<?php echo $admin['id']; ?>)" 
                                                class="flex-1 bg-red-100 text-red-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors">
                                            <i class="fas fa-trash mr-1"></i> Supprimer
                                        </button>
                                    <?php else: ?>
                                        <span class="flex-1 text-center text-gray-500 text-sm py-2">Protégé</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                        <label class="form-label">Prix (€)</label>
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
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addProductModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="add_product" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors">
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
                        <label class="form-label">Prix (€)</label>
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
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('editProductModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="edit_product" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors">
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
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('deleteConfirmModal')" 
                                class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="delete_product" 
                                class="px-6 py-3 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash mr-2"></i> Supprimer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Auto-hide notifications
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
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
    </script>
</body>
</html>