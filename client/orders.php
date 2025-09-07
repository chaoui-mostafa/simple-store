<?php
session_start();

require_once '../config/db.php';
require_once '../controllers/OrderController.php';

// Initialize session_id if not set
if (!isset($_SESSION['session_id'])) {
    $_SESSION['session_id'] = session_id();
}

$sessionId = $_SESSION['session_id'];
$orderController = new OrderController();

// Ensure status column exists in database
$orderController->ensureStatusColumnExists();

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 5;
$offset = ($page - 1) * $perPage;

// Get orders with pagination
$ordersData = $orderController->getUserOrdersWithPagination($sessionId, $perPage, $offset);
$orders = $ordersData['orders'];
$totalOrders = $ordersData['total'];
$totalPages = ceil($totalOrders / $perPage);

// Handle status updates if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $orderId = $_POST['order_id'];
        $newStatus = $_POST['new_status'];
        
        // Update order status in database
        $success = $orderController->updateOrderStatus($orderId, $newStatus);
        
        if ($success) {
            $_SESSION['success'] = "Statut de la commande mis à jour avec succès !";
            // Refresh the page to show updated status
            header("Location: orders.php?page=" . $page);
            exit();
        } else {
            $_SESSION['error'] = "Échec de la mise à jour du statut de la commande.";
        }
    }
    
    // Handle user info update
    if (isset($_POST['update_user_info'])) {
        $userId = $_SESSION['user_id'] ?? null;
        $name = $_POST['customer_name'];
        $email = $_POST['customer_email'];
        $phone = $_POST['customer_phone'];
        $address = $_POST['customer_address'];
        $city = $_POST['customer_city'];
        $state = $_POST['customer_state'];
        $zipcode = $_POST['customer_zipcode'];
        $country = $_POST['customer_country'];
        
        // Update user information in database
        $success = $orderController->updateUserInfo($userId, $name, $email, $phone, $address, $city, $state, $zipcode, $country);
        
        if ($success) {
            $_SESSION['success'] = "Informations mises à jour avec succès !";
            header("Location: orders.php?page=" . $page);
            exit();
        } else {
            $_SESSION['error'] = "Échec de la mise à jour des informations.";
        }
    }
}

// Get user information if available
$userInfo = [];
if (isset($_SESSION['user_id'])) {
    $userInfo = $orderController->getUserInfo($_SESSION['user_id']);
}

// Handle AJAX request for order details
if (isset($_GET['ajax']) && $_GET['ajax'] == 'order_details' && isset($_GET['order_id'])) {
    $orderId = $_GET['order_id'];
    $orderDetails = $orderController->getOrderDetails($orderId);
    
    if ($orderDetails) {
        echo json_encode(['success' => true, 'order' => $orderDetails]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes - Monster Store</title>
      <link rel="icon" href="../assets/images/logo/logo.jpg" type="image/x-icon">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #3B82F6;
            --primary-hover: #2563EB;
            --success-color: #10B981;
            --error-color: #EF4444;
            --warning-color: #F59E0B;
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
        
        /* Mobile-first orders layout */
        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            width: 100%;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px var(--shadow-color);
            overflow: hidden;
            transition: all 0.3s ease;
            padding: 1.5rem;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px var(--shadow-hover);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
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
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-border);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            margin-right: 1rem;
        }
        
        /* Header styles */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--bg-primary);
            box-shadow: 0 2px 10px var(--shadow-color);
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
            color: var(--primary-color);
        }
        
        .nav-icon {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
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
            color: var(--primary-color);
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
            color: var(--primary-color);
            font-size: 1.25rem;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-border);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        /* Mobile styles */
        @media (max-width: 768px) {
            .main-content {
                padding-bottom: 80px;
            }
            
            .card {
                padding: 1rem;
            }
            
            .order-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-top: 1rem;
            }
            
            .order-actions button {
                flex: 1;
                min-width: 120px;
            }
        }
        
        /* Desktop styles */
        @media (min-width: 1024px) {
            .orders-container {
                max-width: 1000px;
                margin: 0 auto;
            }
            
            .bottom-nav {
                display: none;
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
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        
        /* Animation for elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        /* Toast notifications */
        .toast {
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
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: var(--success-color);
        }
        
        .toast.error {
            background: var(--error-color);
        }
        
        /* Order action buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        
        .btn-secondary {
            background: #acb45aff;
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: #6090f0ff;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        /* Animation for new elements */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-slide-in-up {
            animation: slideInUp 0.6s ease-out forwards;
        }

        /* Theme toggle */
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

        /* Fix for paragraph display in both modes */
        p, span, label, h1, h2, h3, h4, h5, h6 {
            color: var(--text-primary);
        }
    </style>
</head>
<body class="antialiased">
    <!-- Toast notifications -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="toast success" id="success-notification">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="toast error" id="error-notification">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
    </div>
    <?php endif; ?>

    <!-- Header -->
<!-- Header -->
<header class="sticky-header">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-3 md:py-4">
            
            <!-- Logo + Title -->
            <div class="flex items-center">
                <a href="index.php" class="mr-2 md:mr-3">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-blue-600 flex items-center justify-center overflow-hidden">
                        <img src="../assets/images/logo/logo.jpg" 
                             alt="Logo" 
                             class="w-full h-full object-cover"
                             onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                        <span class="text-white font-bold text-lg md:text-xl hidden">M</span>
                    </div>
                </a>
                <h1 class="text-sm sm:text-base md:text-xl font-bold dark:text">Mes Commandes</h1>
            </div>
            
            <!-- Actions -->
            <div class="flex items-center gap-2 sm:gap-4">
                <button id="theme-toggle" class="theme-toggle p-1 sm:p-2 rounded">
                    <i id="theme-icon" class="fas fa-moon text-sm sm:text-base"></i>
                </button>
                
                <a href="index.php" class="text-blue-600 hover:text-blue-800 text-xs sm:text-sm flex items-center dark:text-blue-400 dark:hover:text-blue-300">
                    <i class="fas fa-home mr-1 sm:mr-2 text-xs sm:text-sm"></i> boutique
                </a>
            </div>
        </div>
    </div>
</header>



    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="orders-container">
                <?php if (empty($orders)): ?>
                    <div class="card empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-700 mb-2 dark:text">Aucune commande pour le moment</h2>
                        <p class="text-gray-500 mb-6 dark:text-gray-400">Vous n'avez pas encore passé de commandes. Commencez vos achats pour voir vos commandes ici !</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Commencer les achats
                        </a>
                    </div>
                <?php else: ?>
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text">Votre Historique de Commandes</h2>
                        <span class="bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full dark:bg-blue-900 dark:text-blue-200">
                            <?php echo $totalOrders; ?> commande(s)
                        </span>
                    </div>
                    
                    <?php foreach ($orders as $index => $order): 
                        // Use actual status from database if available, otherwise use a default
                        $status = !empty($order['status']) ? $order['status'] : 'pending';
                    ?>
                    <div class="card animate-slide-in-up" style="animation-delay: <?php echo $index * 0.1; ?>s">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-lg flex items-center dark:text">
                                    <i class="fas fa-receipt mr-2 text-blue-500"></i>
                                    Commande #<?php echo $order['order_code'] ?? $order['id']; ?>
                                </h3>
                                <p class="text-gray-500 text-sm flex items-center mt-1 dark:text-gray-400">
                                    <i class="fas fa-calendar-alt mr-2"></i>
                                    Passée le <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $status; ?> mt-2 md:mt-0">
                                <?php 
                                $statusIcons = [
                                    'pending' => 'fas fa-clock',
                                    'processing' => 'fas fa-cog',
                                    'completed' => 'fas fa-check-circle',
                                    'cancelled' => 'fas fa-times-circle'
                                ];
                                $statusLabels = [
                                    'pending' => 'En attente',
                                    'processing' => 'En traitement',
                                    'completed' => 'Terminée',
                                    'cancelled' => 'Annulée'
                                ];
                                ?>
                                <i class="<?php echo $statusIcons[$status] ?? 'fas fa-clock'; ?>"></i>
                                <?php echo $statusLabels[$status] ?? ucfirst($status); ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center border-t border-gray-100 pt-4 dark:border-gray-700">
                            <?php if (!empty($order['product_image'])): ?>
                            <img src="../assets/images/<?php echo htmlspecialchars($order['product_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($order['product_name']); ?>" 
                                 class="order-item-image">
                            <?php else: ?>
                            <div class="order-item-image bg-gray-200 rounded-lg flex items-center justify-center dark:bg-gray-600">
                                <i class="fas fa-image text-gray-400 dark:text-gray-300"></i>
                            </div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <h4 class="font-medium dark:text"><?php echo htmlspecialchars($order['product_name']); ?></h4>
                                <div class="grid grid-cols-2 gap-4 mt-2">
                                    <div>
                                        <p class="text-sm text-gray-600 flex items-center dark:text-gray-400">
                                            <i class="fas fa-box mr-2"></i> Quantité
                                        </p>
                                        <p class="font-medium dark:text"><?php echo $order['quantity']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 flex items-center dark:text-gray-400">
                                            <i class="fas fa-tag mr-2"></i> Prix
                                        </p>
                                        <p class="font-medium dark:text"><?php echo number_format($order['product_price'], 2); ?> DH </p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <p class="text-sm text-gray-600 flex items-center dark:text-gray-400">
                                        <i class="fas fa-receipt mr-2"></i> Total
                                    </p>
                                    <p class="font-semibold text-lg text-blue-600 dark:text-blue-400">
                                        <?php echo number_format($order['product_price'] * $order['quantity'], 2); ?> DH 
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col md:flex-row md:justify-between md:items-center mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <div>
                                <p class="text-sm text-gray-600 flex items-center dark:text-gray-400">
                                    <i class="fas fa-hashtag mr-2"></i> ID Commande: <?php echo $order['order_code'] ?? $order['id']; ?>
                                </p>
                            </div>
                            <div class="order-actions mt-3 md:mt-0">
                                <button class="btn btn-secondary view-details" data-order-id="<?php echo $order['id']; ?>">
                                    <i class="fas fa-eye"></i> Détails
                                </button>
                                <a href="download_invoice.php?order_id=<?php echo $order['id']; ?>&lang=fr" class="btn btn-success">
                                    <i class="fas fa-download"></i> Facture
                                </a>
                                <!-- <button class="btn btn-primary reorder-btn" data-product-id="<?php echo $order['product_id']; ?>">
                                    <i class="fas fa-redo"></i> Commander à nouveau
                                </button> -->
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <div class="flex justify-between items-center mt-8">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Affichage de <?php echo count($orders); ?> sur <?php echo $totalOrders; ?> commandes
                        </p>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                    <i class="fas fa-chevron-left mr-1"></i> Précédent
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-400 cursor-not-allowed dark:border-gray-600 dark:text-gray-500">
                                    <i class="fas fa-chevron-left mr-1"></i> Précédent
                                </span>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="px-3 py-1 border border-gray-300 rounded text-sm <?php echo $i == $page ? 'bg-blue-50 text-blue-600 dark:bg-blue-900 dark:text-blue-200' : 'hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                    Suivant <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-400 cursor-not-allowed dark:border-gray-600 dark:text-gray-500">
                                    Suivant <i class="fas fa-chevron-right ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-2/3 max-h-screen overflow-y-auto dark:bg-gray-800">
            <div class="p-6">
                <div class="flex justify-between items-center border-b pb-4 dark:border-gray-700">
                    <h3 class="text-xl font-semibold dark:text">Détails de la commande</h3>
                    <button class="close-modal text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modalContent" class="py-4">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
    <!-- Desktop Footer -->
     <?php include '../assets/part/footer.php'  ?>
    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav md:hidden">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>Accueil</span>
        </a>
        <a href="cart.php" class="nav-item">
            <i class="fas fa-shopping-cart nav-icon"></i>
            <span>Panier</span>
        </a>
        <a href="checkout.php" class="nav-item">
           <i class="fas fa-check-circle nav-icon"></i>
           <span>Checkout</span>
       </a>
        <a href="orders.php" class="nav-item active">
            <i class="fas fa-receipt nav-icon"></i>
            <span>Commandes</span>
        </a>
    </nav>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme management
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcon(savedTheme);
            
            // Theme toggle functionality
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
            
            // Show toast notifications
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            });
            
            // Modal functionality
            const modal = document.getElementById('orderDetailsModal');
            const viewButtons = document.querySelectorAll('.view-details');
            const closeModal = document.querySelector('.close-modal');
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    loadOrderDetails(orderId);
                    modal.classList.remove('hidden');
                });
            });
            
            closeModal.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });
            
            // Reorder functionality
            const reorderButtons = document.querySelectorAll('.reorder-btn');
            reorderButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    // Add to cart logic would go here
                    alert('Produit ajouté au panier pour recommander !');
                });
            });
            
            // Function to load order details via AJAX
            function loadOrderDetails(orderId) {
                // Show loading state
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                        <p class="mt-2 dark:text-gray-300">Chargement des détails de la commande...</p>
                    </div>
                `;
                
                // Fetch order details from server
                fetch(`orders.php?ajax=order_details&order_id=${orderId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(orderData => {
                        if (orderData.success) {
                            displayOrderDetails(orderData.order);
                        } else {
                            document.getElementById('modalContent').innerHTML = `
                                <div class="text-center py-4 text-red-500">
                                    <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                                    <p>Erreur: ${orderData.message || 'Échec du chargement des détails de la commande'}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching order details:', error);
                        document.getElementById('modalContent').innerHTML = `
                            <div class="text-center py-4 text-red-500">
                                <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                                <p>Erreur lors du chargement des détails. Veuillez réessayer.</p>
                            </div>
                        `;
                    });
            }
            
            // Function to display order details in modal
            function displayOrderDetails(order) {
                // Format the HTML for order details
                const orderDetailsHtml = `
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-700 dark:text-gray-300">Informations de la commande</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Commande #: ${order.order_code || order.id}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Passée le: ${new Date(order.created_at).toLocaleDateString('fr-FR')}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Statut: <span class="status-badge status-${order.status}">${order.status}</span></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-700 dark:text-gray-300">Informations client</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Nom: ${order.customer_name}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Email: ${order.customer_email}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Téléphone: ${order.customer_phone}</p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-700 dark:text-gray-300">Adresse de livraison</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${order.customer_address}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${order.customer_city}, ${order.customer_state} ${order.customer_zipcode}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${order.customer_country}</p>
                        </div>
                        
                        <div class="mt-4">
                            <h4 class="font-medium text-gray-700 mb-2 dark:text-gray-300">Articles de la commande</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center border-b pb-2 dark:border-gray-700">
                                    <div class="flex items-center">
                                        ${order.product_image ? 
                                            `<img src="../assets/images/${order.product_image}" alt="${order.product_name}" class="w-10 h-10 rounded-md object-cover mr-3">` : 
                                            `<div class="w-10 h-10 bg-gray-200 rounded-md flex items-center justify-center mr-3 dark:bg-gray-600">
                                                <i class="fas fa-image text-gray-400 dark:text-gray-300"></i>
                                            </div>`
                                        }
                                        <div>
                                            <p class="font-medium dark:text">${order.product_name}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Quantité: ${order.quantity}</p>
                                        </div>
                                    </div>
                                    <p class="font-medium dark:text">${(order.product_price * order.quantity).toFixed(2)} DH </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between items-center mb-4">
                                <p class="font-semibold dark:text">Total de la commande:</p>
                                <p class="font-semibold text-lg text-blue-600 dark:text-blue-400">${(order.product_price * order.quantity).toFixed(2)} DH </p>
                            </div>
                            <a href="download_invoice.php?order_id=${order.id}&lang=fr" class="block w-full py-2 bg-blue-600 text rounded-lg font-medium hover:bg-blue-700 transition-colors text-center">
                                <i class="fas fa-download mr-2"></i> Télécharger la facture
                            </a>
                        </div>
                    </div>
                `;
                
                document.getElementById('modalContent').innerHTML = orderDetailsHtml;
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
        });
    </script>
</body>
</html>