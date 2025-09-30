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
            $_SESSION['success'] = "Statut de la commande mis à jour avec succès!";
        } else {
            $_SESSION['error'] = "Échec de la mise à jour du statut.";
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
            $_SESSION['success'] = "Informations client mises à jour avec succès!";
        } else {
            $_SESSION['error'] = "Échec de la mise à jour des informations client.";
        }
        
        header("Location: orders.php");
        exit();
    }
}

// Get filter parameters
$phoneFilter = $_GET['phone'] ?? '';
$minOrdersFilter = $_GET['min_orders'] ?? '';
$codeFilter = $_GET['code'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Pagination parameters - FIXED: 30 rows per page
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 30;

// Get orders with optional filters and pagination
$orders = $orderController->getAllOrders($phoneFilter, $minOrdersFilter, $codeFilter, $statusFilter, $currentPage, $perPage);

// Get total count for pagination
$totalOrders = $orderController->countAllOrders($phoneFilter, $minOrdersFilter, $codeFilter, $statusFilter);
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

// Get unique customers for phone display - CORRECTION: Utilisation directe de PDO
$customers = [];
try {
    $sql = "SELECT 
                customer_phone,
                customer_name,
                customer_whatsapp,
                customer_email,
                COUNT(*) as order_count,
                MAX(created_at) as last_order_date
            FROM orders 
            GROUP BY customer_phone, customer_name, customer_whatsapp, customer_email
            ORDER BY last_order_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting customers: " . $e->getMessage());
    $customers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Monster Store</title>
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
            overflow-x: hidden;
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
            width: calc(100% - 280px);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
                height: 100vh;
                top: 0;
                left: 0;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
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
            height: 6px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
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
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
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
            font-size: 14px;
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
            font-size: 14px;
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
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #E5E7EB;
        }
        
        /* Table styles - RESPONSIVE */
        .table-responsive {
            overflow-x: auto;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        .data-table th, .data-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #F3F4F6;
        }
        
        .data-table th {
            background-color: #F9FAFB;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            position: sticky;
            top: 0;
        }
        
        .data-table tr:hover {
            background-color: #F9FAFB;
        }
        
        /* Mobile card view */
        .order-card {
            display: none;
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #E5E7EB;
        }
        
        .order-card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #F3F4F6;
        }
        
        .order-card-body {
            display: grid;
            gap: 1rem;
        }
        
        .order-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        
        .order-card-label {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .order-card-value {
            color: #6B7280;
            text-align: right;
            font-size: 14px;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn {
            transition: all 0.3s ease;
            border-radius: 12px;
            font-weight: 500;
            padding: 12px 20px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #2563eb 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #059669 100%);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #D1D5DB;
            color: #374151;
        }
        
        .btn-outline:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
        }
        
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            padding: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .page-item {
            margin: 0 0.125rem;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            border-radius: 10px;
            background: white;
            color: #4B5563;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            font-size: 14px;
            padding: 0 12px;
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
            transform: none !important;
        }
        
        .page-info {
            text-align: center;
            margin-top: 1rem;
            color: #6B7280;
            font-size: 14px;
            width: 100%;
        }

        /* Modal styles - FIXED CORRECTLY */
        .modal {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .modal.active .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #E5E7EB;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-card {
                padding: 1rem;
            }
            
            .filter-section {
                padding: 1rem;
            }
            
            .data-table {
                display: none;
            }
            
            .order-card {
                display: block;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 13px;
            }
            
            .btn-icon {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
            
            .modal-content {
                width: 95%;
                max-width: 95vw;
                margin: 1rem;
            }
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1rem;
            }
            
            .modal-footer {
                flex-direction: column-reverse;
            }
            
            .modal-footer .btn {
                width: 100%;
                justify-content: center;
            }
            
            .pagination {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .page-link {
                min-width: 36px;
                height: 36px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .order-card {
                padding: 1rem;
            }
            
            .order-card-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .order-card-value {
                text-align: left;
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
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
        
        @media (min-width: 769px) {
            .order-card {
                display: none;
            }
            
            .data-table {
                display: table;
            }
        }
        
        /* Additional mobile optimizations */
        @media (max-width: 360px) {
            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .form-input {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .modal-content {
                margin: 0.5rem;
            }
        }
        
        /* Status filter chips */
        .filter-chips {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .filter-chip {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #E5E7EB;
            background: white;
        }
        
        .filter-chip.active {
            background: #3B82F6;
            color: white;
            border-color: #3B82F6;
        }
        
        .filter-chip:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
            color: #6B7280;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #D1D5DB;
            margin-bottom: 1rem;
        }
        
        /* Product image styles */
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #F3F4F6;
        }
        
        /* WhatsApp button animation */
        .whatsapp-btn {
            transition: all 0.3s ease;
        }
        
        .whatsapp-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }
        
        /* Phone display styles */
        .phone-display {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: monospace;
            font-weight: 600;
        }
        
        .phone-icon {
            color: #10B981;
        }
        
        .whatsapp-indicator {
            color: #25D366;
            font-size: 12px;
        }
        
        /* Customer orders modal */
        .customer-orders-modal .modal-content {
            max-width: 800px;
        }
        
        .customer-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .customer-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .customer-stat {
            text-align: center;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.75rem;
            backdrop-filter: blur(10px);
        }
        
        .customer-stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .customer-stat-label {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        
        /* Phone list styles */
        .phone-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .phone-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        
        .phone-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .phone-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .phone-card-title {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .phone-card-body {
            display: grid;
            gap: 0.75rem;
        }
        
        .phone-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .phone-card-label {
            font-weight: 500;
            color: #6B7280;
            font-size: 0.875rem;
        }
        
        .phone-card-value {
            font-weight: 600;
            text-align: right;
        }
        
        /* Tabs for phone/order view */
        .tabs {
            display: flex;
            border-bottom: 1px solid #E5E7EB;
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .tab.active {
            border-bottom-color: #3B82F6;
            color: #3B82F6;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
              
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200 transition-colors">
                            <i class="fas fa-bell text-gray-600"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-xs"><?php echo count($orders); ?></span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-content: center">
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
                        Total: <?php echo $totalOrders; ?>
                    </span>
                </div>

                <!-- Tabs for Phone/Order view -->
                <div class="tabs mb-6">
                    <div class="tab active" data-tab="orders">Commandes</div>
                    <div class="tab" data-tab="phones">Téléphones Clients</div>
                </div>

                <!-- Orders Tab Content -->
                <div class="tab-content active" id="orders-tab">
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
                            <input type="hidden" name="tab" value="orders">
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
                                <label class="form-label">Min Commandes</label>
                                <input type="number" name="min_orders" value="<?php echo htmlspecialchars($minOrdersFilter); ?>" 
                                       min="1" placeholder="Min commandes..." class="form-input">
                            </div>

                            <div class="flex items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-1">
                                    <i class="fas fa-filter mr-2"></i> Appliquer
                                </button>
                                <a href="orders.php?tab=orders" class="btn btn-outline px-4">
                                    <i class="fas fa-times mr-2"></i> Effacer
                                </a>
                            </div>
                        </form>
                        
                        <!-- Status Filter Chips -->
                        <div class="filter-chips">
                            <a href="?tab=orders&status=" class="filter-chip <?php echo empty($statusFilter) ? 'active' : ''; ?>">
                                Tous (<?php echo $totalOrders; ?>)
                            </a>
                            <a href="?tab=orders&status=pending" class="filter-chip <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                                En Attente (<?php echo $statusCounts['pending']; ?>)
                            </a>
                            <a href="?tab=orders&status=processing" class="filter-chip <?php echo $statusFilter === 'processing' ? 'active' : ''; ?>">
                                En Traitement (<?php echo $statusCounts['processing']; ?>)
                            </a>
                            <a href="?tab=orders&status=completed" class="filter-chip <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                                Terminées (<?php echo $statusCounts['completed']; ?>)
                            </a>
                            <a href="?tab=orders&status=cancelled" class="filter-chip <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">
                                Annulées (<?php echo $statusCounts['cancelled']; ?>)
                            </a>
                        </div>
                    </div>

                    <!-- Orders List -->
                    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                        <?php if (empty($orders)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucune commande trouvée</h3>
                                <p class="text-gray-500 mb-6">Les commandes apparaîtront ici lorsque les clients effectueront des achats.</p>
                            </div>
                        <?php else: ?>
                            <!-- Desktop Table View -->
                            <div class="table-responsive custom-scroll">
                                <table class="w-full data-table">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Code</th>
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Produit</th>
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Qté</th>
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Client</th>
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Total</th>
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Statut</th>
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Date</th>
                                            <th class="py-4 px-4 text-left text-sm font-semibold text-gray-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order):
                                            $productPrice = isset($order['product_price']) ? $order['product_price'] : 0;
                                            $quantity = isset($order['quantity']) ? $order['quantity'] : 0;
                                            $orderTotal = $quantity * $productPrice;
                                            $status = $order['status'] ?? 'pending';
                                        ?>
                                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors" data-order-id="<?php echo $order['id']; ?>">
                                                <td class="py-4 px-4 font-mono text-sm"><?php echo $order['order_code']; ?></td>
                                                <td class="py-4 px-4">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0">
                                                            <?php
                                                            $productImage = "../../assets/images/" . $order['product_image'];
                                                            if (file_exists($productImage) && !empty($order['product_image'])): ?>
                                                                <img src="<?php echo $productImage; ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>" class="product-image">
                                                            <?php else: ?>
                                                                <div class="product-image flex items-center justify-center text-gray-400 bg-gray-100">
                                                                    <i class="fas fa-image"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="ml-3">
                                                            <p class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($order['product_name'] ?? ''); ?></p>
                                                            <p class="text-xs text-gray-500"><?php echo number_format((float)$productPrice, 2); ?> DH</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 font-medium"><?php echo $quantity; ?></td>
                                                <td class="py-4 px-4">
                                                    <p class="font-medium text-sm"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                                    <div class="phone-display">
                                                        <i class="fas fa-phone phone-icon"></i>
                                                        <span><?php echo formatPhoneNumber($order['customer_phone']); ?></span>
                                                        <?php if (!empty($order['customer_whatsapp'])): ?>
                                                            <i class="fab fa-whatsapp whatsapp-indicator" title="WhatsApp disponible"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 font-semibold text-blue-600"><?php echo number_format($orderTotal, 2); ?> DH</td>
                                                <td class="py-4 px-4">
                                                    <span class="status-badge status-<?php echo $status; ?>">
                                                        <i class="fas fa-circle text-xs"></i>
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
                                                <td class="py-4 px-4 text-sm text-gray-600"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                                <td class="py-4 px-4">
                                                    <div class="action-buttons">
                                                        <button onclick="toggleOrderDetails(<?php echo $order['id']; ?>)"
                                                            class="btn-icon bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors" title="Voir les détails">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button onclick="openEditModal(<?php echo $order['id']; ?>, <?php echo htmlspecialchars(json_encode($order), ENT_QUOTES, 'UTF-8'); ?>)"
                                                            class="btn-icon bg-purple-100 text-purple-600 hover:bg-purple-200 transition-colors" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php
                                                            $contactNumber = !empty($order['customer_whatsapp']) ? $order['customer_whatsapp'] : $order['customer_phone'];
                                                            $contactNumber = preg_replace('/\D/', '', $contactNumber);
                                                        ?>
                                                        <a href="https://wa.me/+212<?php echo $contactNumber; ?>?text=<?php echo urlencode("Bonjour " . $order['customer_name'] . ",\nMerci pour votre commande (Code: " . $order['order_code'] . ") sur Monster Store."); ?>"
                                                           target="_blank"
                                                           class="btn-icon bg-green-100 text-green-600 hover:bg-green-200 transition-colors whatsapp-btn"
                                                           title="WhatsApp">
                                                           <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                                            <button type="submit" name="delete_order"
                                                                class="btn-icon bg-red-100 text-red-600 hover:bg-red-200 transition-colors"
                                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')" title="Supprimer">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr id="details-<?php echo $order['id']; ?>" class="order-details">
                                                <td colspan="8" class="px-4 py-6 bg-gray-50">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                        <div>
                                                            <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
                                                                <i class="fas fa-user text-blue-500"></i>
                                                                Informations Client
                                                            </h3>
                                                            <div class="space-y-3">
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
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
                                                                <i class="fas fa-shopping-bag text-green-500"></i>
                                                                Détails Commande
                                                            </h3>
                                                            <div class="space-y-3">
                                                                <p><strong>Code:</strong> <?php echo $order['order_code']; ?></p>
                                                                <p><strong>Produit:</strong> <?php echo htmlspecialchars($order['product_name'] ?? ''); ?></p>
                                                                <p><strong>Quantité:</strong> <?php echo $quantity; ?></p>
                                                                <p><strong>Total:</strong> <span class="font-bold text-blue-600"><?php echo number_format($orderTotal, 2); ?> DH</span></p>
                                                                <p><strong>Date:</strong> <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?></p>
                                                                
                                                                <form method="POST" class="mt-4">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                                                                        <label class="font-medium whitespace-nowrap">Statut:</label>
                                                                        <select name="status" class="form-input flex-1">
                                                                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>En Attente</option>
                                                                            <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>En Traitement</option>
                                                                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                                                                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                                                        </select>
                                                                        <button type="submit" name="update_status" class="btn btn-primary whitespace-nowrap">
                                                                            <i class="fas fa-sync-alt mr-2"></i>Mettre à jour
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
                            
                            <!-- Mobile Card View -->
                            <div class="order-cards md:hidden p-4">
                                <?php foreach ($orders as $order):
                                    $productPrice = isset($order['product_price']) ? $order['product_price'] : 0;
                                    $quantity = isset($order['quantity']) ? $order['quantity'] : 0;
                                    $orderTotal = $quantity * $productPrice;
                                    $status = $order['status'] ?? 'pending';
                                ?>
                                    <div class="order-card">
                                        <div class="order-card-header">
                                            <div>
                                                <span class="font-bold text-lg"><?php echo $order['order_code']; ?></span>
                                                <span class="status-badge status-<?php echo $status; ?> ml-2">
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
                                            </div>
                                            <span class="font-bold text-blue-600"><?php echo number_format($orderTotal, 2); ?> DH</span>
                                        </div>
                                        
                                        <div class="order-card-body">
                                            <div class="order-card-row">
                                                <span class="order-card-label">Produit</span>
                                                <span class="order-card-value"><?php echo htmlspecialchars($order['product_name'] ?? ''); ?></span>
                                            </div>
                                            <div class="order-card-row">
                                                <span class="order-card-label">Quantité</span>
                                                <span class="order-card-value"><?php echo $quantity; ?></span>
                                            </div>
                                            <div class="order-card-row">
                                                <span class="order-card-label">Client</span>
                                                <span class="order-card-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                            </div>
                                            <div class="order-card-row">
                                                <span class="order-card-label">Téléphone</span>
                                                <span class="order-card-value phone-display">
                                                    <i class="fas fa-phone phone-icon"></i>
                                                    <?php echo formatPhoneNumber($order['customer_phone']); ?>
                                                    <?php if (!empty($order['customer_whatsapp'])): ?>
                                                        <i class="fab fa-whatsapp whatsapp-indicator" title="WhatsApp disponible"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="order-card-row">
                                                <span class="order-card-label">Date</span>
                                                <span class="order-card-value"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></span>
                                            </div>
                                            
                                            <div class="order-card-row !flex-col !items-stretch gap-3 mt-3">
                                                <div class="action-buttons">
                                                    <button onclick="toggleOrderDetails(<?php echo $order['id']; ?>)"
                                                        class="btn btn-outline flex-1">
                                                        <i class="fas fa-eye mr-2"></i> Détails
                                                    </button>
                                                    <?php
                                                    $orderData = $order;
                                                    foreach ($orderData as $key => $value) {
                                                        if (is_string($value)) {
                                                            $orderData[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                                                        }
                                                    }
                                                    ?>
                                                    <button onclick="openEditModal(<?php echo $order['id']; ?>, <?php echo htmlspecialchars(json_encode($orderData), ENT_QUOTES, 'UTF-8'); ?>)"
                                                        class="btn-icon bg-purple-100 text-purple-600 hover:bg-purple-200 transition-colors" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                                <div class="action-buttons">
                                                    <?php
                                                        $contactNumber = !empty($order['customer_whatsapp']) ? $order['customer_whatsapp'] : $order['customer_phone'];
                                                        $contactNumber = preg_replace('/\D/', '', $contactNumber);
                                                    ?>
                                                    <a href="https://wa.me/+212<?php echo $contactNumber; ?>?text=<?php echo urlencode("Bonjour " . $order['customer_name'] . ",\nMerci pour votre commande (Code: " . $order['order_code'] . ") sur Monster Store."); ?>"
                                                       target="_blank"
                                                       class="btn btn-success flex-1">
                                                       <i class="fab fa-whatsapp mr-2"></i> WhatsApp
                                                    </a>
                                                    <form method="POST" class="flex-1">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                                        <button type="submit" name="delete_order"
                                                            class="btn btn-danger w-full"
                                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')">
                                                            <i class="fas fa-trash mr-2"></i> Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination - CORRECTION: Now shows 30 rows per page -->
                            <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php
                                $queryParams = [];
                                if (!empty($phoneFilter)) $queryParams['phone'] = $phoneFilter;
                                if (!empty($minOrdersFilter)) $queryParams['min_orders'] = $minOrdersFilter;
                                if (!empty($codeFilter)) $queryParams['code'] = $codeFilter;
                                if (!empty($statusFilter)) $queryParams['status'] = $statusFilter;
                                $queryParams['tab'] = 'orders';
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
                                Affichage de <?php echo (($currentPage - 1) * $perPage) + 1; ?> à <?php echo min($currentPage * $perPage, $totalOrders); ?> sur <?php echo $totalOrders; ?> commandes (<?php echo $perPage; ?> par page)
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Phones Tab Content -->
                <div class="tab-content" id="phones-tab">
                    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                        <?php if (empty($customers)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun client trouvé</h3>
                                <p class="text-gray-500 mb-6">Les clients apparaîtront ici lorsqu'ils effectueront des commandes.</p>
                            </div>
                        <?php else: ?>
                            <div class="phone-list p-6">
                                <?php foreach ($customers as $customer): 
                                    // CORRECTION: Récupération correcte des commandes du client
                                    $customerOrders = [];
                                    $totalSpent = 0;
                                    $statusCountsCustomer = [
                                        'pending' => 0,
                                        'processing' => 0,
                                        'completed' => 0,
                                        'cancelled' => 0
                                    ];
                                    $lastOrderDate = null;
                                    
                                    try {
                                        $sql = "SELECT o.*, p.price as product_price, p.name as product_name 
                                                FROM orders o 
                                                LEFT JOIN products p ON o.product_id = p.id 
                                                WHERE o.customer_phone = ? 
                                                ORDER BY o.created_at DESC";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute([$customer['customer_phone']]);
                                        $customerOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($customerOrders as $order) {
                                            $productPrice = isset($order['product_price']) ? $order['product_price'] : 0;
                                            $quantity = isset($order['quantity']) ? $order['quantity'] : 0;
                                            $totalSpent += $quantity * $productPrice;
                                            $status = $order['status'] ?? 'pending';
                                            if (isset($statusCountsCustomer[$status])) {
                                                $statusCountsCustomer[$status]++;
                                            }
                                            // Find the latest order date
                                            if (isset($order['created_at'])) {
                                                if ($lastOrderDate === null || strtotime($order['created_at']) > strtotime($lastOrderDate)) {
                                                    $lastOrderDate = $order['created_at'];
                                                }
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error getting customer orders: " . $e->getMessage());
                                    }
                                ?>
                                    <div class="phone-card">
                                        <div class="phone-card-header">
                                            <h3 class="phone-card-title">
                                                <?php echo htmlspecialchars($customer['customer_name']); ?>
                                            </h3>
                                            <button onclick="openCustomerOrdersModal('<?php echo $customer['customer_phone']; ?>')" 
                                                    class="btn btn-primary btn-sm">
                                                <i class="fas fa-list mr-1"></i> Voir commandes
                                            </button>
                                        </div>
                                        <div class="phone-card-body">
                                            <div class="phone-card-row">
                                                <span class="phone-card-label">Téléphone</span>
                                                <span class="phone-card-value phone-display">
                                                    <i class="fas fa-phone phone-icon"></i>
                                                    <?php echo formatPhoneNumber($customer['customer_phone']); ?>
                                                </span>
                                            </div>

                                            <?php if (!empty($customer['customer_whatsapp'])): ?>
                                            <div class="phone-card-row">
                                                <span class="phone-card-label">WhatsApp</span>
                                                <span class="phone-card-value phone-display">
                                                    <i class="fab fa-whatsapp whatsapp-indicator"></i>
                                                    <?php echo formatPhoneNumber($customer['customer_whatsapp']); ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>

                                            <div class="phone-card-row">
                                                <span class="phone-card-label">Total commandes</span>
                                                <span class="phone-card-value"><?php echo count($customerOrders); ?></span>
                                            </div>
                                            <div class="phone-card-row">
                                                <span class="phone-card-label">Montant total</span>
                                                <span class="phone-card-value font-bold text-blue-600">
                                                    <?php echo number_format($totalSpent, 2); ?> DH
                                                </span>
                                            </div>
                                            <div class="phone-card-row">
                                                <span class="phone-card-label">Dernière commande</span>
                                                <span class="phone-card-value">
                                                    <?php echo $lastOrderDate ? date('d/m/Y', strtotime($lastOrderDate)) : '-'; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="action-buttons mt-3">
                                            <a href="tel:<?php echo $customer['customer_phone']; ?>" 
                                               class="btn btn-outline flex-1">
                                                <i class="fas fa-phone mr-2"></i> Appeler
                                            </a>
                                            <?php
                                                $contactNumber = !empty($customer['customer_whatsapp']) ? $customer['customer_whatsapp'] : $customer['customer_phone'];
                                                $contactNumber = preg_replace('/\D/', '', $contactNumber);
                                            ?>
                                            <a href="https://wa.me/+212<?php echo $contactNumber; ?>?text=<?php echo urlencode("Bonjour " . $customer['customer_name'] . ",\nMerci pour votre confiance en Monster Store."); ?>"
                                               target="_blank"
                                               class="btn btn-success flex-1">
                                               <i class="fab fa-whatsapp mr-2"></i> WhatsApp
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Modifier les informations client</h2>
                <button class="modal-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
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
                            <label class="form-label">Code Postal</label>
                            <input type="text" name="customer_zipcode" id="editCustomerZipcode" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pays</label>
                            <input type="text" name="customer_country" id="editCustomerCountry" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea name="customer_notes" id="editCustomerNotes" class="form-input" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer Orders Modal -->
    <div id="customerOrdersModal" class="modal customer-orders-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Commandes du client</h2>
                <button class="modal-close" onclick="closeCustomerOrdersModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="customerInfoContainer"></div>
                <div id="customerOrdersContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCustomerOrdersModal()">Fermer</button>
            </div>
        </div>
    </div>

     <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show active tab content
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === tabName + '-tab') {
                            content.classList.add('active');
                        }
                    });
                    
                    // Update URL without reloading
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabName);
                    window.history.replaceState({}, '', url);
                });
            });
            
            // Check if there's a tab parameter in URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'orders';
            
            // Activate the correct tab
            const targetTab = document.querySelector(`.tab[data-tab="${activeTab}"]`);
            if (targetTab) {
                targetTab.click();
            }
        });

        // Modal functions
        function openEditModal(orderId, orderData = null) {
            console.log('Opening modal for order ID:', orderId);
            console.log('Order data:', orderData);
            
            try {
                if (orderData && typeof orderData === 'object') {
                    document.getElementById('editOrderId').value = orderId;
                    document.getElementById('editCustomerName').value = orderData.customer_name || '';
                    document.getElementById('editCustomerEmail').value = orderData.customer_email || '';
                    document.getElementById('editCustomerPhone').value = orderData.customer_phone || '';
                    document.getElementById('editCustomerWhatsapp').value = orderData.customer_whatsapp || '';
                    document.getElementById('editCustomerAddress').value = orderData.customer_address || '';
                    document.getElementById('editCustomerCity').value = orderData.customer_city || '';
                    if (document.getElementById('editCustomerState')) {
                        document.getElementById('editCustomerState').value = orderData.customer_state || '';
                    }
                    if (document.getElementById('editCustomerZipcode')) {
                        document.getElementById('editCustomerZipcode').value = orderData.customer_zipcode || '';
                    }
                    if (document.getElementById('editCustomerCountry')) {
                        document.getElementById('editCustomerCountry').value = orderData.customer_country || '';
                    }
                    document.getElementById('editCustomerNotes').value = orderData.customer_notes || '';
                }
                
                const modal = document.getElementById('editCustomerModal');
                if (modal) {
                    modal.classList.add('active');
                    console.log('Modal classList after adding active:', modal.classList);
                    document.body.style.overflow = 'hidden';
                } else {
                    console.error('Modal element not found');
                }
            } catch (error) {
                console.error('Error in openEditModal:', error);
            }
        }

        function closeEditModal() {
            document.getElementById('editCustomerModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function toggleOrderDetails(orderId) {
            const detailsRow = document.getElementById('details-' + orderId);
            if (detailsRow) {
                detailsRow.classList.toggle('active');
            }
        }

        // Customer Orders Modal functions
        function openCustomerOrdersModal(phone) {
            // Show loading state
            document.getElementById('customerInfoContainer').innerHTML = '<div class="text-center py-4"><div class="loading-spinner"></div><p>Chargement...</p></div>';
            document.getElementById('customerOrdersContainer').innerHTML = '';
            
            // Fetch customer orders via AJAX
            fetch(`get_customer_orders.php?phone=${encodeURIComponent(phone)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCustomerOrders(data.customer, data.orders);
                    } else {
                        document.getElementById('customerInfoContainer').innerHTML = '<div class="text-center text-red-500 py-4">Erreur lors du chargement des données</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('customerInfoContainer').innerHTML = '<div class="text-center text-red-500 py-4">Erreur lors du chargement des données</div>';
                });
            
            document.getElementById('customerOrdersModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCustomerOrdersModal() {
            document.getElementById('customerOrdersModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function displayCustomerOrders(customer, orders) {
            // Calculate stats
            let totalSpent = 0;
            let statusCounts = {
                'pending': 0,
                'processing': 0,
                'completed': 0,
                'cancelled': 0
            };
            
            orders.forEach(order => {
                totalSpent += order.quantity * order.product_price;
                if (statusCounts.hasOwnProperty(order.status)) {
                    statusCounts[order.status]++;
                }
            });
            
            // Display customer info
            const customerInfoHTML = `
                <div class="customer-info-card">
                    <h3 class="text-xl font-bold mb-2">${customer.customer_name}</h3>
                    <div class="phone-display text-white mb-2">
                        <i class="fas fa-phone"></i>
                        <span>${formatPhoneNumberDisplay(customer.customer_phone)}</span>
                        ${customer.customer_whatsapp ? '<i class="fab fa-whatsapp ml-2" title="WhatsApp disponible"></i>' : ''}
                    </div>
                    <div class="customer-stats">
                        <div class="customer-stat">
                            <div class="customer-stat-value">${orders.length}</div>
                            <div class="customer-stat-label">Commandes</div>
                        </div>
                        <div class="customer-stat">
                            <div class="customer-stat-value">${totalSpent.toFixed(2)} DH</div>
                            <div class="customer-stat-label">Total Dépensé</div>
                        </div>
                        <div class="customer-stat">
                            <div class="customer-stat-value">${statusCounts.completed}</div>
                            <div class="customer-stat-label">Terminées</div>
                        </div>
                        <div class="customer-stat">
                            <div class="customer-stat-value">${statusCounts.pending}</div>
                            <div class="customer-stat-label">En Attente</div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('customerInfoContainer').innerHTML = customerInfoHTML;
            
            // Display orders
            if (orders.length === 0) {
                document.getElementById('customerOrdersContainer').innerHTML = '<div class="empty-state"><p>Aucune commande trouvée</p></div>';
                return;
            }
            
            let ordersHTML = '<div class="space-y-4">';
            orders.forEach(order => {
                const orderTotal = order.quantity * order.product_price;
                const statusLabels = {
                    'pending': 'En Attente',
                    'processing': 'En Traitement',
                    'completed': 'Terminée',
                    'cancelled': 'Annulée'
                };
                
                ordersHTML += `
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-mono font-bold">${order.order_code}</span>
                            <span class="status-badge status-${order.status}">${statusLabels[order.status] || order.status}</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                            <div>
                                <strong>Produit:</strong> ${order.product_name}
                            </div>
                            <div>
                                <strong>Quantité:</strong> ${order.quantity}
                            </div>
                            <div>
                                <strong>Prix unitaire:</strong> ${parseFloat(order.product_price).toFixed(2)} DH
                            </div>
                            <div>
                                <strong>Total:</strong> <span class="font-bold text-blue-600">${orderTotal.toFixed(2)} DH</span>
                            </div>
                            <div>
                                <strong>Date:</strong> ${new Date(order.created_at).toLocaleDateString('fr-FR')}
                            </div>
                        </div>
                    </div>
                `;
            });
            ordersHTML += '</div>';
            
            document.getElementById('customerOrdersContainer').innerHTML = ordersHTML;
        }

        function formatPhoneNumberDisplay(phone) {
            // Format phone number for display
            const cleaned = phone.replace(/\D/g, '');
            if (cleaned.length === 10) {
                return cleaned.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
            }
            return phone;
        }

        // Toggle mobile menu
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Auto-hide notifications
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        });

        // Close modals when clicking outside
        document.getElementById('editCustomerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        document.getElementById('customerOrdersModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCustomerOrdersModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
                closeCustomerOrdersModal();
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to format phone numbers
function formatPhoneNumber($phone) {
    $cleaned = preg_replace('/\D/', '', $phone);
    if (strlen($cleaned) === 10) {
        return preg_replace('/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', '$1 $2 $3 $4 $5', $cleaned);
    }
    return $phone;
}
?>