<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit();
}
require_once '../../controllers/SettingsController.php';
$settingsController = new SettingsController();
$sidebar_stats = $settingsController->getSidebarStats();
$store_settings = $settingsController->getStoreSettings();

// analytic.php - Analytics script with fixed Chart.js visualizations for store2026 database
// Load DB credentials from .env if available
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $value;
    }
}

$host = $_ENV['DB_HOST'] ?? $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname = $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? 'store2026';
$username = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to fetch total number of records from a table
function getTotalCount($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Function to fetch total revenue from orders
function getTotalRevenue($pdo) {
    $stmt = $pdo->prepare("
        SELECT SUM(o.quantity * p.price) AS total_revenue
        FROM orders o
        JOIN products p ON o.product_id = p.id
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
}

// Function to fetch top selling products
function getTopSellingProducts($pdo, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT p.name, p.price, SUM(o.quantity) AS total_sold, 
               SUM(o.quantity * p.price) AS revenue
        FROM orders o
        JOIN products p ON o.product_id = p.id
        GROUP BY p.id, p.name, p.price
        ORDER BY total_sold DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch order status distribution
function getOrderStatusDistribution($pdo) {
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS count
        FROM orders
        GROUP BY status
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch payment method distribution
function getPaymentMethodDistribution($pdo) {
    $stmt = $pdo->prepare("
        SELECT payment_method, COUNT(*) AS count
        FROM orders
        GROUP BY payment_method
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch daily revenue for last 30 days
function getDailyRevenue($pdo) {
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, 
               COUNT(*) as order_count,
               COALESCE(SUM(o.quantity * p.price), 0) as revenue
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch analytics data
$totalAdmins = getTotalCount($pdo, 'admins');
$totalProducts = getTotalCount($pdo, 'products');
$totalOrders = getTotalCount($pdo, 'orders');
$totalCartItems = getTotalCount($pdo, 'cart') + getTotalCount($pdo, 'cart_items');
$totalProductImages = getTotalCount($pdo, 'product_images');
$totalRevenue = getTotalRevenue($pdo);
$topProducts = getTopSellingProducts($pdo, 5);
$orderStatuses = getOrderStatusDistribution($pdo);
$paymentMethods = getPaymentMethodDistribution($pdo);
$dailyRevenue = getDailyRevenue($pdo);

// Calculate average order value
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Prepare data for database overview chart
$overviewData = [
    'Admins' => $totalAdmins,
    'Products' => $totalProducts,
    'Orders' => $totalOrders,
    'Cart Items' => $totalCartItems,
    'Product Images' => $totalProductImages
];

// Connexion à la base de données pour récupérer les données de la sidebar
try {
    // Récupérer le nombre de produits
    $stmt_products = $pdo->prepare("SELECT COUNT(*) as total FROM products");
    $stmt_products->execute();
    $products_count = $stmt_products->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer le nombre de commandes en attente
    $stmt_orders = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $stmt_orders->execute();
    $orders_count = $stmt_orders->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch(PDOException $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $products_count = 0;
    $orders_count = 0;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytiques - Monster Store Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray: #6b7280;
            --border: #e5e7eb;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
            --radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 30px;
            margin-bottom: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="rgba(255,255,255,0.1)"><circle cx="50" cy="50" r="2"/></svg>') repeat;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .header h1 i {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 50%;
            backdrop-filter: blur(10px);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            height: 380px;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
            width: 100%;
        }

        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .chart-container canvas {
            max-width: 100%;
            max-height: 100%;
            width: 100% !important;
            height: 280px !important;
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }

        .chart-title i {
            color: var(--primary);
            font-size: 1.1em;
        }

        /* Table Styles */
        .table-container {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            border: 1px solid var(--border);
            width: 100%;
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        tr:hover {
            background: #f8fafc;
            transform: scale(1.01);
            transition: var(--transition);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { 
            background: linear-gradient(135deg, #fef3c7, #f59e0b);
            color: #92400e;
        }
        .status-completed { 
            background: linear-gradient(135deg, #d1fae5, #10b981);
            color: #065f46;
        }
        .status-processing { 
            background: linear-gradient(135deg, #fef3c7, #f59e0b);
            color: #92400e;
        }

        .no-data {
            text-align: center;
            padding: 40px 30px;
            color: var(--gray);
            font-style: italic;
        }

        .no-data i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.5;
            color: var(--primary);
        }

        /* Main Content Area */
        .main-content {
            width: 100%;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #1e40af 0%, #3B82F6 100%);
            color: white;
        }
        
        /* Mobile First Design */
        @media (min-width: 768px) {
            .main-content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }
        }

        @media (max-width: 767px) {
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
                padding-top: 0;
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 25px 20px;
                margin-bottom: 20px;
            }

            .header h1 {
                font-size: 1.7rem;
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .chart-container {
                height: 320px;
                padding: 20px;
            }

            .chart-container canvas {
                height: 220px !important;
            }
            
            .header h1 i {
                padding: 10px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }
            
            .stat-icon {
                width: 55px;
                height: 55px;
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 12px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .chart-container {
                height: 280px;
                padding: 18px;
            }

            .chart-container canvas {
                height: 190px !important;
            }
            
            .header {
                padding: 20px 15px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .header p {
                font-size: 1rem;
            }
            
            th, td {
                padding: 12px 8px;
            }
            
            .table-container {
                padding: 20px;
            }
        }

        @media (max-width: 360px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 18px 12px;
            }
            
            .chart-container {
                padding: 15px;
                height: 250px;
            }
            
            .chart-container canvas {
                height: 170px !important;
            }
            
            .stats-grid {
                gap: 12px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
        }

        /* Sidebar Custom Styles */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, #3b82f6, #8b5cf6);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, #2563eb, #7c3aed);
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* Glass effect amélioré */
        .glass-effect {
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
        }

        /* Hover effects avancés */
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Pulse animation */
        .pulse-slow {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Correction pour le body adaptatif */
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* Correction pour le contenu principal */
        .main-content {
            margin-left: 0;
            transition: margin-left 0.5s ease;
            min-height: 100vh;
            width: 100%;
        }

        /* Sur les écrans moyens et grands */
        @media (min-width: 768px) {
            .main-content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }
        }

        /* Correction pour éviter que la sidebar cache le contenu */
        @media (max-width: 767px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            /* S'assurer que le contenu n'est pas caché par la sidebar */
            #sidebar {
                z-index: 9999;
            }
            
            /* Overlay pour mobile amélioré */
            #sidebarOverlay {
                z-index: 9998;
            }
        }

        /* Amélioration de la responsivité pour très petits écrans */
        @media (max-width: 480px) {
            #sidebar {
                width: 100%;
            }
            
            .main-content {
                padding-top: 0;
            }
        }

        /* Correction pour les sections comme les produits */
        .products-section, .main-section {
            position: relative;
            z-index: 1;
            width: 100%;
        }

        /* Assurer que le contenu principal reste visible */
        .content-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
        }
        
        /* Fix pour les petits écrans */
        @media (max-width: 320px) {
            .container {
                padding: 8px;
            }
            
            .header {
                padding: 15px 10px;
            }
            
            .chart-container {
                padding: 12px;
                height: 230px;
            }
            
            .chart-container canvas {
                height: 150px !important;
            }
            
            .stat-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Toggle Button -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-2xl hover:shadow-3xl transition-all duration-300 group">
        <i class="fas fa-bars text-lg group-hover:scale-110 transition-transform"></i>
    </button>

    <!-- Overlay for mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden transition-opacity duration-300"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-80 bg-gradient-to-br from-slate-900 via-blue-900 to-purple-900 transform -translate-x-full md:translate-x-0 transition-all duration-500 z-50 shadow-2xl">
        
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.15) 1px, transparent 0); background-size: 20px 20px;"></div>
        </div>
        
        <!-- Close button for mobile -->
        <button id="sidebarClose" class="md:hidden absolute top-6 right-6 p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-200 group">
            <i class="fas fa-times text-lg group-hover:rotate-90 transition-transform"></i>
        </button>
        
        <!-- Header -->
        <div class="relative p-8 border-b border-white/10">
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center shadow-2xl group hover:scale-105 transition-transform duration-300">
                        <span class="text-white font-bold text-2xl">
                            <?php echo substr($_SESSION['admin_username'], 0, 1); ?>
                        </span>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-slate-900"></div>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-white mb-1 truncate"><?php echo htmlspecialchars($store_settings['store_name'] ?? 'Monster Store'); ?></h1>
                    <p class="text-blue-200/80 text-sm font-medium truncate">Bienvenue, <?php echo $_SESSION['admin_username']; ?></p>
                    <div class="flex items-center mt-1 space-x-2">
                        <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded-full text-xs font-medium border border-green-500/30">
                            <i class="fas fa-circle text-[8px] mr-1"></i> En ligne
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="relative mt-8 p-4 space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto custom-scrollbar">
            <!-- Dashboard -->
            <a href="index.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300 relative overflow-hidden
               <?php echo ($current_page == 'index.php') ? 
               'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
               'text-blue-100/90 hover:text-white hover:bg-white/5 hover:scale-[1.02]'; ?>">
                <div class="relative">
                    <i class="fas fa-grid-2 text-xl w-8 <?php echo ($current_page == 'index.php') ? 'text-cyan-400' : 'text-blue-400/80 group-hover:text-cyan-400'; ?>"></i>
                </div>
                <span class="ml-4 font-semibold">Tableau de bord</span>
                <?php if ($current_page == 'index.php'): ?>
                    <div class="absolute right-4 w-2 h-2 bg-cyan-400 rounded-full animate-pulse"></div>
                <?php endif; ?>
            </a>

            <!-- Boutique -->
            <a href="../index.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300
               <?php echo ($current_page == '../index.php') ? 
               'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
               'text-blue-100/90 hover:text-white hover:bg-white/5 hover:scale-[1.02]'; ?>">
                <i class="fas fa-store text-xl w-8 <?php echo ($current_page == '../index.php') ? 'text-emerald-400' : 'text-emerald-400/80 group-hover:text-emerald-400'; ?>"></i>
                <span class="ml-4 font-semibold">Boutique</span>
            </a>

            <!-- Produits -->
            <a href="products.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300
               <?php echo ($current_page == 'products.php') ? 
               'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
               'text-blue-100/90 hover:text-white hover:bg-white/5 hover:scale-[1.02]'; ?>">
                <i class="fas fa-cube text-xl w-8 <?php echo ($current_page == 'products.php') ? 'text-amber-400' : 'text-amber-400/80 group-hover:text-amber-400'; ?>"></i>
                <span class="ml-4 font-semibold">Produits</span>
                <span class="ml-auto px-2 py-1 bg-amber-500/20 text-amber-400 rounded-full text-xs font-medium border border-amber-500/30">
                    <?php echo $products_count; ?>
                </span>
            </a>

            <!-- Commandes -->
            <a href="orders.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300
               <?php echo ($current_page == 'orders.php') ? 
               'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
               'text-blue-100/90 hover:text-white hover:bg-white/5 hover:scale-[1.02]'; ?>">
                <i class="fas fa-shopping-bag text-xl w-8 <?php echo ($current_page == 'orders.php') ? 'text-violet-400' : 'text-violet-400/80 group-hover:text-violet-400'; ?>"></i>
                <span class="ml-4 font-semibold">Commandes</span>
                <span class="ml-auto px-2 py-1 bg-violet-500/20 text-violet-400 rounded-full text-xs font-medium border border-violet-500/30">
                    <?php echo $orders_count; ?>
                </span>
            </a>

            <!-- Administrateurs -->
            <a href="admins.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300
               <?php echo ($current_page == 'admins.php') ? 
               'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
               'text-blue-100/90 hover:text-white hover:bg-white/5 hover:scale-[1.02]'; ?>">
                <i class="fas fa-users-cog text-xl w-8 <?php echo ($current_page == 'admins.php') ? 'text-rose-400' : 'text-rose-400/80 group-hover:text-rose-400'; ?>"></i>
                <span class="ml-4 font-semibold">Administrateurs</span>
            </a>

            <!-- Statistiques -->
            <a href="analytics.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300
               <?php echo ($current_page == 'analytics.php') ? 
               'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
               'text-blue-100/90 hover:text-white hover:bg-white/5 hover:scale-[1.02]'; ?>">
                <i class="fas fa-chart-pie text-xl w-8 <?php echo ($current_page == 'analytics.php') ? 'text-teal-400' : 'text-teal-400/80 group-hover:text-teal-400'; ?>"></i>
                <span class="ml-4 font-semibold">Analytiques</span>
            </a>

            <!-- Paramètres -->
            <a href="settings.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300
               <?php echo ($current_page == 'settings.php') ? 
               'text-white bg-gradient-to-r from-blue-500/20 to-purple-500/20 shadow-lg border border-white/10' : 
               'text-blue-100/90 hover:text-white hover:bg-white/5 hover:scale-[1.02]'; ?>">
                <i class="fas fa-cogs text-xl w-8 <?php echo ($current_page == 'settings.php') ? 'text-slate-400' : 'text-slate-400/80 group-hover:text-slate-400'; ?>"></i>
                <span class="ml-4 font-semibold">Paramètres</span>
            </a>

            <!-- Séparateur -->
            <div class="border-t border-white/10 my-4"></div>

            <!-- Déconnexion -->
            <a href="logout.php" 
               class="group flex items-center py-4 px-6 rounded-2xl transition-all duration-300 bg-gradient-to-r from-rose-500/10 to-red-500/10 hover:from-rose-500/20 hover:to-red-500/20 border border-rose-500/20 hover:border-rose-500/30">
                <i class="fas fa-sign-out-alt text-xl w-8 text-rose-400/80 group-hover:text-rose-400 group-hover:animate-pulse"></i>
                <span class="ml-4 font-semibold text-rose-200 group-hover:text-white">Déconnexion</span>
            </a>
        </nav>
        
        <!-- Footer -->
        <div class="absolute bottom-0 w-full p-4 border-t border-white/10 bg-black/20 backdrop-blur-lg">
            <div class="text-center">
                <div class="flex items-center justify-center space-x-2 mb-2">
                    <div class="w-6 h-6 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center">
                        <i class="fas fa-dragon text-white text-xs"></i>
                    </div>
                    <span class="text-white font-semibold text-base">Monster Store</span>
                </div>
                <p class="text-blue-200/60 text-xs">Admin v2.0 • eTwin Technology</p>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-chart-network"></i> Tableau de Bord Analytique</h1>
                <p>Statistiques en temps réel de votre boutique Monster Store</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                    <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
                    <div class="stat-label">Commandes Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-trend-up"></i></div>
                    <div class="stat-value"><?php echo number_format($totalRevenue, 2, ',', ' '); ?> €</div>
                    <div class="stat-label">Chiffre d'Affaires</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-cubes"></i></div>
                    <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
                    <div class="stat-label">Produits en Stock</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-basket-shopping"></i></div>
                    <div class="stat-value"><?php echo number_format($avgOrderValue, 2, ',', ' '); ?> €</div>
                    <div class="stat-label">Panier Moyen</div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <!-- Database Overview Chart -->
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-database"></i> Aperçu de la Base de Données</div>
                    <canvas id="overviewChart"></canvas>
                </div>

                <!-- Revenue Chart -->
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-chart-line"></i> Évolution du Revenue (30 jours)</div>
                    <canvas id="revenueChart"></canvas>
                </div>

                <!-- Order Status Chart -->
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-chart-pie"></i> Statut des Commandes</div>
                    <canvas id="statusChart"></canvas>
                </div>

                <!-- Payment Methods Chart -->
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-credit-card"></i> Méthodes de Paiement</div>
                    <canvas id="paymentChart"></canvas>
                </div>

                <!-- Top Products Chart -->
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-trophy"></i> Top 5 Produits les Plus Vendus</div>
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>

            <!-- Top Products Table -->
            <div class="table-container">
                <div class="chart-title"><i class="fas fa-list-ol"></i> Détail des Produits les Plus Vendus</div>
                <?php if (!empty($topProducts)): ?>
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-cube"></i> Produit</th>
                            <th><i class="fas fa-tag"></i> Prix Unitaire</th>
                            <th><i class="fas fa-chart-bar"></i> Quantité Vendue</th>
                            <th><i class="fas fa-money-bill-wave"></i> Revenue Généré</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($topProducts as $product): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                            <td><?php echo number_format($product['price'], 2, ',', ' '); ?> €</td>
                            <td><span class="status-badge status-completed"><?php echo $product['total_sold']; ?> unités</span></td>
                            <td><strong><?php echo number_format($product['revenue'], 2, ',', ' '); ?> €</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>Aucune donnée de vente disponible pour le moment</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Database Overview Table -->
            <div class="table-container">
                <div class="chart-title"><i class="fas fa-server"></i> Aperçu de la Base de Données</div>
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-table"></i> Table</th>
                            <th><i class="fas fa-hashtag"></i> Nombre d'Enregistrements</th>
                            <th><i class="fas fa-info-circle"></i> Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-user-shield"></i> Administrateurs</strong></td>
                            <td><?php echo $totalAdmins; ?></td>
                            <td>Utilisateurs administrateurs du système</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-box"></i> Produits</strong></td>
                            <td><?php echo $totalProducts; ?></td>
                            <td>Produits disponibles dans la boutique</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-receipt"></i> Commandes</strong></td>
                            <td><?php echo $totalOrders; ?></td>
                            <td>Commandes passées par les clients</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-images"></i> Images Produits</strong></td>
                            <td><?php echo $totalProductImages; ?></td>
                            <td>Images associées aux produits</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-shopping-cart"></i> Articles Panier</strong></td>
                            <td><?php echo $totalCartItems; ?></td>
                            <td>Articles dans les paniers actifs</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Chart initialization with modern design
        document.addEventListener('DOMContentLoaded', function() {
            // Database Overview Chart
            const overviewCtx = document.getElementById('overviewChart').getContext('2d');
            const overviewData = <?php echo json_encode(array_values($overviewData)); ?>;
            if (overviewData.some(val => val > 0)) {
                new Chart(overviewCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_keys($overviewData)); ?>,
                        datasets: [{
                            label: 'Nombre d\'Enregistrements',
                            data: overviewData,
                            backgroundColor: [
                                'rgba(102, 126, 234, 0.8)',
                                'rgba(242, 142, 43, 0.8)',
                                'rgba(225, 87, 89, 0.8)',
                                'rgba(118, 183, 178, 0.8)',
                                'rgba(89, 161, 79, 0.8)'
                            ],
                            borderColor: [
                                'rgb(102, 126, 234)',
                                'rgb(242, 142, 43)',
                                'rgb(225, 87, 89)',
                                'rgb(118, 183, 178)',
                                'rgb(89, 161, 79)'
                            ],
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { 
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: { 
                                display: false 
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: 'rgba(102, 126, 234, 0.8)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        return `${context.label}: ${context.raw}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueLabels = <?php echo json_encode(array_map(function($d) { 
                return date('d/m', strtotime($d['date'])); 
            }, $dailyRevenue)); ?>;
            const revenueData = <?php echo json_encode(array_column($dailyRevenue, 'revenue')); ?>;
            if (revenueData.length > 0) {
                new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: revenueLabels,
                        datasets: [{
                            label: 'Revenue (€)',
                            data: revenueData,
                            borderColor: 'rgb(102, 126, 234)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: 'rgb(102, 126, 234)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value + ' €';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Order Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusLabelsRaw = <?php echo json_encode(array_column($orderStatuses, 'status')); ?>;
            const statusLabels = statusLabelsRaw.map(function(status) {
                return status.charAt(0).toUpperCase() + status.slice(1);
            });
            const statusData = <?php echo json_encode(array_column($orderStatuses, 'count')); ?>;
            if (statusData.length > 0) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusData,
                            backgroundColor: [
                                'rgba(78, 205, 196, 0.8)',
                                'rgba(255, 107, 107, 0.8)',
                                'rgba(69, 183, 209, 0.8)',
                                'rgba(150, 201, 61, 0.8)',
                                'rgba(247, 183, 49, 0.8)'
                            ],
                            borderColor: [
                                'rgb(78, 205, 196)',
                                'rgb(255, 107, 107)',
                                'rgb(69, 183, 209)',
                                'rgb(150, 201, 61)',
                                'rgb(247, 183, 49)'
                            ],
                            borderWidth: 2,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            }
                        }
                    }
                });
            }

            // Payment Methods Chart
            const paymentCtx = document.getElementById('paymentChart').getContext('2d');
            const paymentLabelsRaw = <?php echo json_encode(array_column($paymentMethods, 'payment_method')); ?>;
            const paymentLabels = paymentLabelsRaw.map(function(method) {
                return method.charAt(0).toUpperCase() + method.slice(1);
            });
            const paymentData = <?php echo json_encode(array_column($paymentMethods, 'count')); ?>;
            if (paymentData.length > 0) {
                new Chart(paymentCtx, {
                    type: 'pie',
                    data: {
                        labels: paymentLabels,
                        datasets: [{
                            data: paymentData,
                            backgroundColor: [
                                'rgba(255, 159, 67, 0.8)',
                                'rgba(106, 176, 76, 0.8)',
                                'rgba(30, 144, 255, 0.8)',
                                'rgba(232, 67, 147, 0.8)'
                            ],
                            borderColor: [
                                'rgb(255, 159, 67)',
                                'rgb(106, 176, 76)',
                                'rgb(30, 144, 255)',
                                'rgb(232, 67, 147)'
                            ],
                            borderWidth: 2,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            }
                        }
                    }
                });
            }

            // Top Products Chart
            const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
            const topProducts = <?php echo json_encode($topProducts); ?>;
            const productLabels = topProducts.map(p => p.name.length > 20 ? p.name.substring(0, 20) + '...' : p.name);
            const productData = topProducts.map(p => p.total_sold);
            if (productData.length > 0) {
                new Chart(topProductsCtx, {
                    type: 'bar',
                    data: {
                        labels: productLabels,
                        datasets: [{
                            label: 'Unités Vendues',
                            data: productData,
                            backgroundColor: 'rgba(78, 121, 167, 0.8)',
                            borderColor: 'rgb(78, 121, 167)',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { 
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        });

        // JavaScript for responsive sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainContent = document.querySelector('.main-content');
            
            // Animation d'entrée de la sidebar
            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('shadow-3xl');
                sidebarOverlay.classList.remove('hidden');
                setTimeout(() => sidebarOverlay.classList.remove('opacity-0'), 10);
                document.body.style.overflow = 'hidden';
                
                // Ajuster le contenu principal sur mobile
                if (window.innerWidth < 768) {
                    if (mainContent) {
                        mainContent.style.transform = 'translateX(80px)';
                        mainContent.style.opacity = '0.7';
                    }
                }
                
                // Animation des éléments de la sidebar
                const navItems = document.querySelectorAll('.sidebar nav a');
                navItems.forEach((item, index) => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        item.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        item.style.opacity = '1';
                        item.style.transform = 'translateX(0)';
                    }, index * 100 + 200);
                });
            }
            
            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('shadow-3xl');
                sidebarOverlay.classList.add('opacity-0');
                setTimeout(() => {
                    sidebarOverlay.classList.add('hidden');
                    // Réinitialiser le contenu principal
                    if (mainContent) {
                        mainContent.style.transform = '';
                        mainContent.style.opacity = '';
                    }
                }, 300);
                document.body.style.overflow = '';
            }
            
            // Toggle sidebar on button click
            sidebarToggle.addEventListener('click', openSidebar);
            
            // Close sidebar on close button click
            sidebarClose.addEventListener('click', closeSidebar);
            
            // Close sidebar on overlay click
            sidebarOverlay.addEventListener('click', closeSidebar);
            
            // Close sidebar when a navigation link is clicked (on mobile)
            const navLinks = document.querySelectorAll('.sidebar nav a');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 768) {
                        closeSidebar();
                    }
                });
            });
            
            // Close sidebar on Escape key press
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
                    closeSidebar();
                }
            });
            
            // Adjust sidebar behavior on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                    document.body.style.overflow = '';
                    // Réinitialiser le contenu principal
                    if (mainContent) {
                        mainContent.style.transform = '';
                        mainContent.style.opacity = '';
                    }
                } else {
                    if (sidebar.classList.contains('-translate-x-full')) {
                        // Si la sidebar est fermée sur mobile, s'assurer que le contenu est visible
                        if (mainContent) {
                            mainContent.style.transform = '';
                            mainContent.style.opacity = '';
                        }
                    }
                }
            });

            // Add hover effects to navigation items
            navLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    if (window.innerWidth >= 768) {
                        this.style.transform = 'translateX(8px) scale(1.02)';
                    }
                });
                
                link.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('bg-gradient-to-r') && window.innerWidth >= 768) {
                        this.style.transform = 'translateX(0) scale(1)';
                    }
                });
            });

            // Add ripple effect to buttons
            function createRipple(event) {
                const button = event.currentTarget;
                const circle = document.createElement('span');
                const diameter = Math.max(button.clientWidth, button.clientHeight);
                const radius = diameter / 2;
                
                circle.style.width = circle.style.height = `${diameter}px`;
                circle.style.left = `${event.clientX - button.getBoundingClientRect().left - radius}px`;
                circle.style.top = `${event.clientY - button.getBoundingClientRect().top - radius}px`;
                circle.classList.add('ripple');
                
                const ripple = button.getElementsByClassName('ripple')[0];
                if (ripple) ripple.remove();
                
                button.appendChild(circle);
            }
            
            const buttons = document.querySelectorAll('#sidebarToggle, #sidebarClose');
            buttons.forEach(button => {
                button.addEventListener('click', createRipple);
            });
        });

        // Add ripple effect styles
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
                pointer-events: none;
            }
            
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            #sidebarToggle, #sidebarClose {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>