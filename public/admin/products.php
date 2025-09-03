<?php
session_start();
require_once '../../config/db.php';
require_once '../../controllers/AdminController.php';
require_once '../../controllers/ProductController.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$pdo = $db->connect();
$adminController = new AdminController();
$productController = new ProductController($pdo);

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
    } elseif (isset($_POST['add_product_images'])) {
        // Ajouter des images supplémentaires au produit
        $productId = $_POST['product_id'];
        $adminController->addProductImages($productId, $_FILES);
    } elseif (isset($_POST['delete_product_image'])) {
        // Supprimer une image de produit
        $imageId = $_POST['image_id'];
        $adminController->deleteProductImage($imageId);
    }
    
    // Actualiser la page pour afficher les mises à jour
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$products = $adminController->getAllProducts();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - StyleShop</title>
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
            z-index: 100;
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
        
        /* Styles pour les images supplémentaires */
        .additional-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .additional-image {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            height: 80px;
        }
        
        .additional-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-actions {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
            padding: 4px;
            border-radius: 0 0 0 8px;
        }
        
        .image-actions button {
            color: white;
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 4px;
        }
        
        .image-actions button:hover {
            color: #EF4444;
        }
        
        /* Correction pour les onglets */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Search bar */
        .search-container {
            position: relative;
            max-width: 400px;
        }
        
        .search-input {
            padding-left: 40px;
            border-radius: 50px;
            height: 48px;
        }
        
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
        }
        
        /* Loading animation */
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3B82F6;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #D1D5DB;
            margin-bottom: 1rem;
        }
        
        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* Table styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .data-table th {
            background-color: #F9FAFB;
            font-weight: 600;
            color: #374151;
        }
        
        .data-table tr:hover {
            background-color: #F9FAFB;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
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
                <a href="products.php" class="nav-link active block py-3 px-4 text-white bg-white/15 transition-all duration-200">
                    <i class="fas fa-box mr-3"></i> Produits
                </a>
                <a href="orders.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i> Commandes
                </a>
                <a href="gallery.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-images mr-3"></i> Galerie
                </a>
                <a href="admins.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
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
                                <h3 class="text-3xl font-bold"><?php echo count($products); ?></h3>
                                <p class="text-green-100">Produits Actifs</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.3s; background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-tags text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo count($products); ?></h3>
                                <p class="text-purple-100">Catégories</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter and search section -->
                <div class="filter-section mb-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" placeholder="Rechercher un produit..." class="search-input form-input">
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <select class="form-input w-auto">
                                <option>Toutes les catégories</option>
                                <option>Vêtements</option>
                                <option>Accessoires</option>
                                <option>Chaussures</option>
                            </select>
                            
                            <select class="form-input w-auto">
                                <option>Tous les statuts</option>
                                <option>En stock</option>
                                <option>Faible stock</option>
                                <option>Rupture</option>
                            </select>
                            
                            <button onclick="openModal('addProductModal')" 
                                    class="btn btn-primary px-6 py-3 text-white rounded-xl font-medium">
                                <i class="fas fa-plus mr-2"></i> Ajouter un Produit
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Products Tab -->
                <div id="products">
                    <!-- Products List -->
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 p-6 product-grid">
                            <?php if (empty($products)): ?>
                                <div class="col-span-full">
                                    <div class="empty-state bg-white rounded-2xl p-8">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-box-open"></i>
                                        </div>
                                        <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun produit trouvé</h3>
                                        <p class="text-gray-500 mb-6">Commencez par ajouter votre premier produit</p>
                                        <button onclick="openModal('addProductModal')" 
                                                class="btn btn-primary px-6 py-3 text-white">
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
                                    
                                    // Récupérer les images supplémentaires du produit
                                    $additionalImages = $adminController->getProductImages($product['id']);
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
                                            
                                            <!-- Images supplémentaires -->
                                            <?php if (!empty($additionalImages)): ?>
                                            <div class="additional-images mb-4">
                                                <?php foreach ($additionalImages as $image): ?>
                                                    <div class="additional-image">
    <img src="/../../assets/images/product_images<?php echo htmlspecialchars($image['image_path']); ?>" 
         alt="Image supplémentaire">
    <div class="image-actions">
        <button onclick="confirmImageDelete(<?php echo $image['id']; ?>)" title="Supprimer cette image">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="flex space-x-2">
                                                <button onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                        class="flex-1 bg-blue-100 text-blue-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-blue-200 transition-colors">
                                                    <i class="fas fa-edit mr-1"></i> Modifier
                                                </button>
                                                <button onclick="openAddImagesModal(<?php echo $product['id']; ?>)" 
                                                        class="flex-1 bg-green-100 text-green-600 py-2 px-3 rounded-lg text-sm font-medium hover:bg-green-200 transition-colors">
                                                    <i class="fas fa-images mr-1"></i> Images
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
                        <label class="form-label">Image principale du Produit</label>
                        <input type="file" name="image" accept="image/*" required class="form-input">
                        <p class="text-xs text-gray-500 mt-2">Formats supportés: JPG, PNG, GIF. Taille max: 2MB</p>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Images supplémentaires (optionnel)</label>
                        <input type="file" name="additional_images[]" accept="image/*" multiple class="form-input">
                        <p class="text-xs text-gray-500 mt-2">Vous pouvez sélectionner plusieurs images. Formats supportés: JPG, PNG, GIF. Taille max: 2MB par image</p>
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

    <!-- Add Images Modal -->
    <div id="addImagesModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="modal-content bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">Ajouter des Images au Produit</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="product_id" id="addImagesProductId">
                
                <div>
                    <label class="form-label">Images supplémentaires</label>
                    <input type="file" name="product_images[]" accept="image/*" multiple required class="form-input">
                    <p class="text-xs text-gray-500 mt-2">Vous pouvez sélectionner plusieurs images. Formats supportés: JPG, PNG, GIF. Taille max: 2MB par image</p>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addImagesModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="add_product_images" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i> Ajouter les Images
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

    <!-- Delete Image Confirmation Modal -->
    <div id="deleteImageConfirmModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="modal-content bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">Confirmer la Suppression</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-6">Êtes-vous sûr de vouloir supprimer cette image ? Cette action est irréversible.</p>
                <form method="POST" id="deleteImageForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="image_id" id="deleteImageId">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('deleteImageConfirmModal')" 
                                class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="delete_product_image" 
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

        function openAddImagesModal(productId) {
            document.getElementById('addImagesProductId').value = productId;
            openModal('addImagesModal');
        }

        function confirmDelete(productId) {
            document.getElementById('deleteId').value = productId;
            document.getElementById('deleteForm').querySelector('button[type="submit"]').name = 'delete_product';
            openModal('deleteConfirmModal');
        }

        function confirmImageDelete(imageId) {
            document.getElementById('deleteImageId').value = imageId;
            openModal('deleteImageConfirmModal');
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

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
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
                    const files = this.files;
                    if (files.length > 0) {
                        for (let i = 0; i < files.length; i++) {
                            const file = files[i];
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
                    }
                });
            });

            // Search functionality
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    const productCards = document.querySelectorAll('.product-card');
                    
                    productCards.forEach(card => {
                        const productName = card.querySelector('h3').textContent.toLowerCase();
                        const productDescription = card.querySelector('p').textContent.toLowerCase();
                        
                        if (productName.includes(searchTerm) || productDescription.includes(searchTerm)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>