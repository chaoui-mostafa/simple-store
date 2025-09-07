<?php
session_start();
require_once '../../config/db.php';
require_once '../../controllers/AdminController.php';
require_once '../../controllers/OrderController.php';
// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}


$db = new Database();
$pdo = $db->connect();
$adminController = new AdminController();
$orderController = new OrderController();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    if (isset($_POST['delete_order'])) {
        $orderController->deleteOrder($_POST['id']);
    } elseif (isset($_POST['update_order'])) {
        $orderController->updateOrder($_POST['id'], $_POST);
    } elseif (isset($_POST['update_status'])) {
        $orderId = $_POST['order_id'];
        $newStatus = $_POST['status'];
        
        // Update order status
        if ($orderController->updateOrderStatus($orderId, $newStatus)) {
            $_SESSION['success'] = "Order status updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update order status.";
        }
        
        header("Location: orders.php");
        exit();
    } elseif (isset($_POST['update_customer_info'])) {
        // Handle customer info update
        $orderId = $_POST['order_id'];
        $customerData = [
            'customer_name' => $_POST['customer_name'],
            'customer_email' => $_POST['customer_email'],
            'customer_phone' => $_POST['customer_phone'],
            'customer_whatsapp' => $_POST['customer_whatsapp'] ?? '',
            'customer_address' => $_POST['customer_address'],
            'customer_city' => $_POST['customer_city'] ?? '',
            'customer_state' => $_POST['customer_state'] ?? '',
            'customer_zipcode' => $_POST['customer_zipcode'] ?? '',
            'customer_country' => $_POST['customer_country'] ?? '',
            'customer_notes' => $_POST['customer_notes'] ?? ''
        ];
        
        if ($orderController->updateCustomerInfo($orderId, $customerData)) {
            $_SESSION['success'] = "Customer information updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update customer information.";
        }
        
        header("Location: orders.php");
        exit();
    }
}

// Get filter parameters
$phoneFilter = $_GET['phone'] ?? '';
$minOrdersFilter = $_GET['min_orders'] ?? '';
$codeFilter = $_GET['code'] ?? ''; // New filter for order code

// Pagination parameters
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 25; // Items per page

// Get orders with optional filters and pagination
$orders = $orderController->getAllOrders($phoneFilter, $minOrdersFilter, $codeFilter, $currentPage, $perPage);

// Get total count for pagination
$totalOrders = $orderController->countAllOrders($phoneFilter, $minOrdersFilter, $codeFilter);
$totalPages = ceil($totalOrders / $perPage);

// Get status counts for statistics
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

// WhatsApp number for customer contact
$whatsappNumber = '+212724893110';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Admin Panel</title>
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

        /* Custom scrollbar */
        .custom-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Notification styles */
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
        
        .order-details {
            display: none;
        }

        .order-details.active {
            display: table-row;
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
        
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            padding: 1rem;
        }
        
        .page-item {
            margin: 0 0.25rem;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: white;
            color: #4B5563;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: #3B82F6;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }
        
        .page-link.active {
            background: linear-gradient(135deg, #3B82F6 0%, #2563eb 100%);
            color: white;
        }
        
        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-info {
            text-align: center;
            margin-top: 1rem;
            color: #6B7280;
            font-size: 0.875rem;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #E5E7EB;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6B7280;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
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
        <?php include 'assets/slide.php'; ?>
        
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
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-xs"><?php echo count($orders); ?></span>
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
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Commandes</h1>
                    <span class="bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full">
                        Commandes Total: <?php echo $totalOrders; ?>
                    </span>
                </div>

                <!-- Order Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 stats-grid">
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.1s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo $statusCounts['pending']; ?></h3>
                                <p class="text-blue-100">En Attente</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.2s; background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-cog text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo $statusCounts['processing']; ?></h3>
                                <p class="text-green-100">En Traitement</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.3s; background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-check-circle text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo $statusCounts['completed']; ?></h3>
                                <p class="text-purple-100">Terminées</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.4s; background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-times-circle text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo $statusCounts['cancelled']; ?></h3>
                                <p class="text-red-100">Annulées</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter section -->
                <div class="filter-section mb-6">
                    <h3 class="text-lg font-semibold mb-4">Filtrer les Commandes</h3>
                  <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div>
        <label class="form-label">Code Commande</label>
        <input type="text" name="code" value="<?php echo htmlspecialchars($codeFilter); ?>" 
               placeholder="Rechercher par code..." class="form-input">
    </div>

    <div>
        <label class="form-label">Numéro de Téléphone</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($phoneFilter); ?>" 
               placeholder="Rechercher par téléphone..." class="form-input">
    </div>

    <div>
        <label class="form-label">Nombre Minimum de Commandes</label>
        <input type="number" name="min_orders" value="<?php echo htmlspecialchars($minOrdersFilter); ?>" 
               min="1" placeholder="Min commandes..." class="form-input">
    </div>

    <div class="flex items-end">
        <button type="submit" class="btn btn-primary px-6 py-3 text-white rounded-xl font-medium mr-2">
            <i class="fas fa-filter mr-2"></i> Appliquer
        </button>
        <a href="orders.php" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
            <i class="fas fa-times mr-2"></i> Effacer
        </a>
    </div>
</form>

                </div>

                <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-shopping-cart text-4xl mb-3"></i>
                            <p class="text-lg">Aucune commande trouvée.</p>
                            <p class="text-sm">Les commandes apparaîtront ici lorsque les clients effectueront des achats.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto custom-scroll">
                            <table class="w-full data-table">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Code Commande</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Produit</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Qté</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Client</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Total</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Statut</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Date</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order):
                                        $orderTotal = $order['quantity'] * $order['product_price'];
                                        $status = $order['status'] ?? 'pending';
                                    ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50" data-order-id="<?php echo $order['id']; ?>">
                                            <td class="py-3 px-4 font-mono"><?php echo $order['order_code']; ?></td>
                                            <td class="py-3 px-4">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 w-10 h-10 bg-gray-200 rounded-md overflow-hidden">
                                                        <?php
                                                        $productImage = "../../assets/images/" . $order['product_image'];
                                                        if (file_exists($productImage) && !empty($order['product_image'])): ?>
                                                            <img src="<?php echo $productImage; ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                                <i class="fas fa-image"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($order['product_name']); ?></p>
                                                        <p class="text-sm text-gray-500"><?php echo number_format($order['product_price'], 2); ?> DH </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4"><?php echo $order['quantity']; ?></td>
                                            <td class="py-3 px-4">
                                                <p class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_email']); ?></p>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                            </td>
                                            <td class="py-3 px-4 font-semibold"><?php echo number_format($orderTotal, 2); ?> DH </td>
                                            <td class="py-3 px-4">
                                                <span class="status-badge status-<?php echo $status; ?>">
                                                    <?php 
                                                    $statusLabels = [
                                                        'pending' => 'En Attente',
                                                        'processing' => 'En Traitement',
                                                        'completed' => 'Terminée',
                                                        'cancelled' => 'Annulée'
                                                    ];
                                                    echo $statusLabels[$status] ?? ucfirst($status); 
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                            <td class="py-3 px-4">
                                                <div class="action-buttons">
                                                    <button onclick="toggleOrderDetails(<?php echo $order['id']; ?>)"
                                                        class="btn-icon bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors" title="Voir les détails">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="openEditModal(<?php echo $order['id']; ?>)"
                                                        class="btn-icon bg-purple-100 text-purple-600 hover:bg-purple-200 transition-colors" title="Modifier les informations">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <!-- WhatsApp Contact (if customer WhatsApp exists, use it; else use phone) -->
                                                    <?php
                                                        $contactNumber = !empty($order['customer_whatsapp']) ? $order['customer_whatsapp'] : $order['customer_phone'];
                                                        $contactNumber = preg_replace('/\D/', '', $contactNumber);
                                                    ?>
         <a href="https://wa.me/<?php echo $contactNumber; ?>?text=<?php echo urlencode("Bonjour " . $order['customer_name'] . ",\nMerci pour votre commande (Code: " . $order['order_code'] . ") sur Monster Store.\nNous allons traiter votre commande et vous contacterons bientôt pour la livraison.\nSi vous avez des questions, n'hésitez pas à répondre à ce message."); ?>"
   target="_blank"
   class="btn-icon bg-green-100 text-green-600 hover:bg-green-200 transition-colors"
   title="Contacter sur WhatsApp">
   <i class="fab fa-whatsapp"></i>
</a>


                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                                        <button type="submit" name="delete_order"
                                                            class="btn-icon bg-red-100 text-red-600 hover:bg-red-200 transition-colors"
                                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')" title="Supprimer la commande">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id="details-<?php echo $order['id']; ?>" class="order-details">
                                            <td colspan="8" class="px-4 py-4 bg-gray-50">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div>
                                                        <h3 class="font-semibold text-lg mb-3">Informations Client</h3>
                                                        <div class="space-y-2">
                                                            <p><strong>Nom:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                                            <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                                            <?php if (!empty($order['customer_whatsapp'])): ?>
                                                                <p><strong>WhatsApp:</strong> <?php echo htmlspecialchars($order['customer_whatsapp']); ?></p>
                                                            <?php endif; ?>
                                                            <p><strong>Adresse:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                                                            <?php if (!empty($order['customer_city'])): ?>
                                                                <p><strong>Ville:</strong> <?php echo htmlspecialchars($order['customer_city']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($order['customer_state'])): ?>
                                                                <p><strong>Région:</strong> <?php echo htmlspecialchars($order['customer_state']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($order['customer_zipcode'])): ?>
                                                                <p><strong>Code Postal:</strong> <?php echo htmlspecialchars($order['customer_zipcode']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($order['customer_country'])): ?>
                                                                <p><strong>Pays:</strong> <?php echo htmlspecialchars($order['customer_country']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($order['customer_notes'])): ?>
                                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($order['customer_notes']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h3 class="font-semibold text-lg mb-3">Détails de la Commande</h3>
                                                        <div class="space-y-2">
                                                            <p><strong>ID Commande:</strong> #<?php echo $order['id']; ?></p>
                                                            <p><strong>Code Commande:</strong> <?php echo $order['order_code']; ?></p>
                                                            <p><strong>Produit:</strong> <?php echo htmlspecialchars($order['product_name']); ?></p>
                                                            <p><strong>Quantité:</strong> <?php echo $order['quantity']; ?></p>
                                                            <p><strong>Prix Unitaire:</strong> <?php echo number_format($order['product_price'], 2); ?> DH </p>
                                                            <p><strong>Total:</strong> <?php echo number_format($orderTotal, 2); ?> DH </p>
                                                            <p><strong>Date de Commande:</strong> <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?></p>
                                                            
                                                            <!-- Status Update Form -->
                                                            <form method="POST" class="mt-4">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                <div class="flex items-center">
                                                                    <label class="mr-2 font-medium">Mettre à jour le statut:</label>
                                                                    <select name="status" class="border rounded p-2 mr-2 form-input">
                                                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>En Attente</option>
                                                                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>En Traitement</option>
                                                                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                                                                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                                                    </select>
                                                                    <button type="submit" name="update_status" class="btn btn-primary px-4 py-2 text-white rounded-xl">
                                                                        Mettre à jour
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            // Build query string for pagination links
                            $queryParams = [];
                            if (!empty($phoneFilter)) $queryParams['phone'] = $phoneFilter;
                            if (!empty($minOrdersFilter)) $queryParams['min_orders'] = $minOrdersFilter;
                            if (!empty($codeFilter)) $queryParams['code'] = $codeFilter;
                            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                            ?>
                            
                            <!-- First page -->
                            <div class="page-item">
                                <a href="?page=1<?php echo $queryString; ?>" class="page-link <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </div>
                            
                            <!-- Previous page -->
                            <div class="page-item">
                                <a href="?page=<?php echo max(1, $currentPage - 1); ?><?php echo $queryString; ?>" class="page-link <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </div>
                            
                            <!-- Page numbers -->
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, $endPage - 4);
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <div class="page-item">
                                    <a href="?page=<?php echo $i; ?><?php echo $queryString; ?>" class="page-link <?php echo $currentPage == $i ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </div>
                            <?php endfor; ?>
                            
                            <!-- Next page -->
                            <div class="page-item">
                                <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?><?php echo $queryString; ?>" class="page-link <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </div>
                            
                            <!-- Last page -->
                            <div class="page-item">
                                <a href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>" class="page-link <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="page-info">
                            Affichage de <?php echo (($currentPage - 1) * $perPage) + 1; ?> à <?php echo min($currentPage * $perPage, $totalOrders); ?> sur <?php echo $totalOrders; ?> commandes
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-xl font-semibold">Modifier les informations client</h2>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" id="customerForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="order_id" id="editOrderId">
                    <input type="hidden" name="update_customer_info" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nom complet</label>
                            <input type="text" name="customer_name" id="editCustomerName" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="customer_email" id="editCustomerEmail" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="customer_phone" id="editCustomerPhone" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">WhatsApp</label>
                            <input type="text" name="customer_whatsapp" id="editCustomerWhatsapp" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Adresse</label>
                            <textarea name="customer_address" id="editCustomerAddress" class="form-input" rows="2" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Ville</label>
                            <input type="text" name="customer_city" id="editCustomerCity" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Région</label>
                            <input type="text" name="customer_state" id="editCustomerState" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Code postal</label>
                            <input type="text" name="customer_zipcode" id="editCustomerZipcode" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Pays</label>
                            <input type="text" name="customer_country" id="editCustomerCountry" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="customer_notes" id="editCustomerNotes" class="form-input" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle mobile menu
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Show notifications
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 5000);
            });
        });

        function toggleOrderDetails(orderId) {
            const detailsRow = document.getElementById('details-' + orderId);
            detailsRow.classList.toggle('active');
        }

        // Edit modal functions
        function openEditModal(orderId) {
            // Get the details row
            const detailsRow = document.getElementById('details-' + orderId);
            if (!detailsRow) {
                console.error('Details row not found for order ID:', orderId);
                return;
            }
            
            // Extract customer data from the details row
            const customerInfoDiv = detailsRow.querySelector('div:first-child > div:last-child');
            if (!customerInfoDiv) {
                console.error('Customer info div not found');
                return;
            }
            
            // Helper function to extract text content
            const extractValue = (text, label) => {
                const regex = new RegExp(label + ':\\s*(.*)');
                const match = text.match(regex);
                return match ? match[1].trim() : '';
            };
            
            // Get all paragraph elements
            const paragraphs = customerInfoDiv.querySelectorAll('p');
            
            // Extract values from paragraphs
            let customerData = {
                name: '',
                email: '',
                phone: '',
                whatsapp: '',
                address: '',
                city: '',
                state: '',
                zipcode: '',
                country: '',
                notes: ''
            };
            
            paragraphs.forEach(p => {
                const text = p.textContent;
                if (text.includes('Nom:')) customerData.name = extractValue(text, 'Nom');
                else if (text.includes('Email:')) customerData.email = extractValue(text, 'Email');
                else if (text.includes('Téléphone:')) customerData.phone = extractValue(text, 'Téléphone');
                else if (text.includes('WhatsApp:')) customerData.whatsapp = extractValue(text, 'WhatsApp');
                else if (text.includes('Adresse:')) customerData.address = extractValue(text, 'Adresse');
                else if (text.includes('Ville:')) customerData.city = extractValue(text, 'Ville');
                else if (text.includes('Région:')) customerData.state = extractValue(text, 'Région');
                else if (text.includes('Code Postal:')) customerData.zipcode = extractValue(text, 'Code Postal');
                else if (text.includes('Pays:')) customerData.country = extractValue(text, 'Pays');
                else if (text.includes('Notes:')) customerData.notes = extractValue(text, 'Notes');
            });
            
            // Fill the form with extracted data
            document.getElementById('editOrderId').value = orderId;
            document.getElementById('editCustomerName').value = customerData.name;
            document.getElementById('editCustomerEmail').value = customerData.email;
            document.getElementById('editCustomerPhone').value = customerData.phone;
            document.getElementById('editCustomerWhatsapp').value = customerData.whatsapp;
            document.getElementById('editCustomerAddress').value = customerData.address;
            document.getElementById('editCustomerCity').value = customerData.city;
            document.getElementById('editCustomerState').value = customerData.state;
            document.getElementById('editCustomerZipcode').value = customerData.zipcode;
            document.getElementById('editCustomerCountry').value = customerData.country;
            document.getElementById('editCustomerNotes').value = customerData.notes;
            
            // Show the modal
            document.getElementById('editCustomerModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editCustomerModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('editCustomerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>

</html>