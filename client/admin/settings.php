<?php
session_start();
require_once '../../controllers/SettingsController.php';
$settingsController = new SettingsController();

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [];
    
    if (isset($_POST['update_store_info'])) {
        $store_settings = [
            'store_name' => $_POST['store_name'],
            'store_email' => $_POST['store_email'],
            'store_phone' => $_POST['store_phone'],
            'store_address' => $_POST['store_address'],
            'store_currency' => $_POST['store_currency'] ?? 'EUR',
            'store_language' => $_POST['store_language'] ?? 'fr',
            'store_timezone' => $_POST['store_timezone'] ?? 'Europe/Paris'
        ];
        
        $response = $settingsController->updateStoreSettings($store_settings);
    }
    
    if (isset($_POST['change_password'])) {
        $response = $settingsController->changeAdminPassword(
            $_POST['current_password'],
            $_POST['new_password'],
            $_POST['confirm_password']
        );
    }
    
    if (isset($_POST['backup_database'])) {
        $response = $settingsController->backupDatabase();
    }
    
    if (isset($_POST['toggle_maintenance'])) {
        $enable = $_POST['maintenance_mode'] === '1';
        $response = $settingsController->toggleMaintenanceMode($enable);
    }
    
    if (isset($_POST['clear_cache'])) {
        $response = $settingsController->clearCache();
    }
    
    // Rediriger avec le message de réponse
    if (isset($response['success'])) {
        $_SESSION['message'] = [
            'type' => $response['success'] ? 'success' : 'error',
            'text' => $response['message']
        ];
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Récupérer les données
$store_settings = $settingsController->getStoreSettings();
$admin_info = $settingsController->getAdminInfo();
$sidebar_stats = $settingsController->getSidebarStats();
$system_info = $settingsController->getSystemInfo();
$system_logs = $settingsController->getSystemLogs(10);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Monster Store Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray: #6b7280;
            --border: #e5e7eb;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
            --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Glassmorphism Effect */
        .glass {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 30px rgba(0,0,0,0.2);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        /* Main Content */
        .main-content {
            width: 100%;
            min-height: 100vh;
            transition: all 0.3s ease;
            margin-left: 0;
        }

        @media (min-width: 768px) {
            .sidebar {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
            width: 100%;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 32px;
            margin-bottom: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
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
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 16px;
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
            font-weight: 400;
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .settings-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            overflow: hidden;
            position: relative;
        }

        .settings-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header h2 i {
            color: var(--primary);
            font-size: 1.2em;
        }

        .card-body {
            padding: 24px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(245, 158, 11, 0.3);
        }

        /* Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left-color: var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #7f1d1d;
            border-left-color: var(--danger);
        }

        /* Admin Info */
        .admin-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: var(--radius);
            padding: 24px;
            margin-top: 24px;
        }

        .admin-info h3 {
            color: var(--primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Password Strength */
        .password-strength {
            margin-top: 8px;
            height: 6px;
            border-radius: 3px;
            background: var(--border);
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: var(--transition);
        }

        .strength-weak { background: var(--danger); width: 33%; }
        .strength-medium { background: var(--warning); width: 66%; }
        .strength-strong { background: var(--success); width: 100%; }

        .password-requirements {
            margin-top: 12px;
            font-size: 0.8rem;
            color: var(--gray);
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            transition: var(--transition);
        }

        .requirement.met {
            color: var(--success);
        }

        .requirement i {
            font-size: 0.8rem;
        }

        /* System Status */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-online {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-offline {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
            border-radius: 10px;
        }

        /* Maintenance Status */
        .maintenance-status {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .maintenance-active {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .maintenance-inactive {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #10b981;
        }

        /* Logs Container */
        .logs-container {
            background: #1f2937;
            color: #f3f4f6;
            border-radius: 12px;
            padding: 16px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 12px;
        }

        .log-entry {
            padding: 4px 0;
            border-bottom: 1px solid #374151;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        /* Mobile Responsive */
        @media (max-width: 767px) {
            .container {
                padding: 16px;
            }

            .header {
                padding: 24px 16px;
                margin-bottom: 20px;
            }

            .header h1 {
                font-size: 1.6rem;
            }

            .settings-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .card-body {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 12px;
            }

            .header h1 {
                font-size: 1.4rem;
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .card-header h2 {
                font-size: 1.1rem;
            }
        }

        /* Animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-in {
            animation: slideIn 0.5s ease-out;
        }

        /* Hover Effects */
        .hover-lift {
            transition: var(--transition);
        }

        .hover-lift:hover {
            transform: translateY(-2px);
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
                            <?php echo substr($_SESSION['admin_username'] ?? 'A', 0, 1); ?>
                        </span>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-slate-900"></div>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-white mb-1 truncate"><?php echo htmlspecialchars($store_settings['store_name'] ?? 'Monster Store'); ?></h1>
                    <p class="text-blue-200/80 text-sm font-medium truncate">Bienvenue, <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></p>
                    <div class="flex items-center mt-1 space-x-2">
                        <span class="status-badge status-online">
                            <i class="fas fa-circle text-[8px]"></i> En ligne
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
                    <?php echo $sidebar_stats['products_count'] ?? 0; ?>
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
                    <?php echo $sidebar_stats['orders_count'] ?? 0; ?>
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
            <div class="header animate-slide-in">
                <h1><i class="fas fa-cogs"></i> Paramètres du Système</h1>
                <p>Gérez les paramètres de votre boutique Monster Store</p>
            </div>

            <!-- Messages d'alerte -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type'] === 'success' ? 'success' : 'error'; ?> animate-slide-in">
                    <i class="fas fa-<?php echo $_SESSION['message']['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo $_SESSION['message']['text']; ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Grid des paramètres -->
            <div class="settings-grid">
                <!-- Carte des informations de la boutique -->
                <div class="settings-card animate-slide-in" style="animation-delay: 0.1s">
                    <div class="card-header">
                        <h2><i class="fas fa-store"></i> Informations de la Boutique</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="update_store_info" value="1">
                            <div class="form-group">
                                <label class="form-label" for="store_name">
                                    <i class="fas fa-signature"></i> Nom de la boutique
                                </label>
                                <input type="text" class="form-control" id="store_name" name="store_name" 
                                       value="<?php echo htmlspecialchars($store_settings['store_name'] ?? 'Monster Store'); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="store_email">
                                    <i class="fas fa-envelope"></i> Email de contact
                                </label>
                                <input type="email" class="form-control" id="store_email" name="store_email" 
                                       value="<?php echo htmlspecialchars($store_settings['store_email'] ?? 'contact@monsterstore.com'); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="store_phone">
                                    <i class="fas fa-phone"></i> Téléphone
                                </label>
                                <input type="tel" class="form-control" id="store_phone" name="store_phone" 
                                       value="<?php echo htmlspecialchars($store_settings['store_phone'] ?? '+33 1 23 45 67 89'); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="store_address">
                                    <i class="fas fa-map-marker-alt"></i> Adresse
                                </label>
                                <textarea class="form-control" id="store_address" name="store_address" 
                                          rows="3"><?php echo htmlspecialchars($store_settings['store_address'] ?? '123 Rue du Commerce, Paris, France'); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Carte de sécurité -->
                <div class="settings-card animate-slide-in" style="animation-delay: 0.2s">
                    <div class="card-header">
                        <h2><i class="fas fa-shield-alt"></i> Sécurité et Mot de Passe</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label class="form-label" for="current_password">
                                    <i class="fas fa-lock"></i> Mot de passe actuel
                                </label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="new_password">
                                    <i class="fas fa-key"></i> Nouveau mot de passe
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required 
                                       onkeyup="checkPasswordStrength(this.value)">
                                <div class="password-strength">
                                    <div class="strength-bar" id="passwordStrength"></div>
                                </div>
                                <div class="password-requirements">
                                    <div class="requirement" id="lengthReq">
                                        <i class="fas fa-circle"></i>
                                        <span>Au moins 8 caractères</span>
                                    </div>
                                    <div class="requirement" id="numberReq">
                                        <i class="fas fa-circle"></i>
                                        <span>Contient un chiffre</span>
                                    </div>
                                    <div class="requirement" id="specialReq">
                                        <i class="fas fa-circle"></i>
                                        <span>Contient un caractère spécial</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirm_password">
                                    <i class="fas fa-redo"></i> Confirmer le mot de passe
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                       onkeyup="checkPasswordMatch()">
                                <div id="passwordMatch" style="margin-top: 5px; font-size: 0.85rem;"></div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Changer le mot de passe
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Carte des préférences système -->
                <div class="settings-card animate-slide-in" style="animation-delay: 0.3s">
                    <div class="card-header">
                        <h2><i class="fas fa-sliders-h"></i> Préférences Système</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="update_store_info" value="1">
                            <div class="form-group">
                                <label class="form-label" for="store_currency">
                                    <i class="fas fa-euro-sign"></i> Devise
                                </label>
                                <select class="form-control" id="store_currency" name="store_currency">
                                    <option value="EUR" <?php echo ($store_settings['store_currency'] ?? 'EUR') === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                    <option value="USD" <?php echo ($store_settings['store_currency'] ?? 'EUR') === 'USD' ? 'selected' : ''; ?>>Dollar US ($)</option>
                                    <option value="GBP" <?php echo ($store_settings['store_currency'] ?? 'EUR') === 'GBP' ? 'selected' : ''; ?>>Livre Sterling (£)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="store_language">
                                    <i class="fas fa-globe"></i> Langue
                                </label>
                                <select class="form-control" id="store_language" name="store_language">
                                    <option value="fr" <?php echo ($store_settings['store_language'] ?? 'fr') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                    <option value="en" <?php echo ($store_settings['store_language'] ?? 'fr') === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo ($store_settings['store_language'] ?? 'fr') === 'es' ? 'selected' : ''; ?>>Español</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="store_timezone">
                                    <i class="fas fa-clock"></i> Fuseau horaire
                                </label>
                                <select class="form-control" id="store_timezone" name="store_timezone">
                                    <option value="Europe/Paris" <?php echo ($store_settings['store_timezone'] ?? 'Europe/Paris') === 'Europe/Paris' ? 'selected' : ''; ?>>Europe/Paris (UTC+1)</option>
                                    <option value="UTC" <?php echo ($store_settings['store_timezone'] ?? 'Europe/Paris') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="America/New_York" <?php echo ($store_settings['store_timezone'] ?? 'Europe/Paris') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (UTC-5)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les préférences
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Carte de maintenance -->
                <div class="settings-card animate-slide-in" style="animation-delay: 0.4s">
                    <div class="card-header">
                        <h2><i class="fas fa-tools"></i> Maintenance</h2>
                    </div>
                    <div class="card-body">
                        <!-- Statut de maintenance -->
                        <div class="maintenance-status <?php echo ($store_settings['store_maintenance'] ?? '0') === '1' ? 'maintenance-active' : 'maintenance-inactive'; ?>">
                            <i class="fas fa-<?php echo ($store_settings['store_maintenance'] ?? '0') === '1' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                            <span>Mode maintenance: <?php echo ($store_settings['store_maintenance'] ?? '0') === '1' ? 'ACTIVÉ' : 'DÉSACTIVÉ'; ?></span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-database"></i> Sauvegarde de la base de données
                            </label>
                            <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 15px;">
                                Créer une sauvegarde complète de votre base de données.
                            </p>
                            <form method="POST" action="">
                                <input type="hidden" name="backup_database" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Créer une sauvegarde
                                </button>
                            </form>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-broom"></i> Nettoyage du cache
                            </label>
                            <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 15px;">
                                Vider le cache du système pour améliorer les performances.
                            </p>
                            <form method="POST" action="">
                                <input type="hidden" name="clear_cache" value="1">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-broom"></i> Vider le cache
                                </button>
                            </form>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-exclamation-triangle"></i> Mode maintenance
                            </label>
                            <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 15px;">
                                <?php echo ($store_settings['store_maintenance'] ?? '0') === '1' ? 
                                    'Le site est actuellement en maintenance pour les visiteurs.' : 
                                    'Activer le mode maintenance pour les visiteurs.'; ?>
                            </p>
                            <form method="POST" action="">
                                <input type="hidden" name="toggle_maintenance" value="1">
                                <input type="hidden" name="maintenance_mode" value="<?php echo ($store_settings['store_maintenance'] ?? '0') === '1' ? '0' : '1'; ?>">
                                <button type="submit" class="btn btn-<?php echo ($store_settings['store_maintenance'] ?? '0') === '1' ? 'primary' : 'danger'; ?>">
                                    <i class="fas fa-power-off"></i> 
                                    <?php echo ($store_settings['store_maintenance'] ?? '0') === '1' ? 'Désactiver' : 'Activer'; ?> le mode maintenance
                                </button>
                            </form>
                        </div>

                        <!-- Logs système -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-list-alt"></i> Logs système récents
                            </label>
                            <div class="logs-container">
                                <?php if (!empty($system_logs)): ?>
                                    <?php foreach ($system_logs as $log): ?>
                                        <div class="log-entry"><?php echo htmlspecialchars($log); ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="log-entry">Aucun log système disponible</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations de l'administrateur et système -->
            <div class="settings-grid">
                <!-- Informations de l'administrateur -->
                <div class="settings-card animate-slide-in" style="animation-delay: 0.5s">
                    <div class="card-header">
                        <h2><i class="fas fa-user-cog"></i> Informations de l'Administrateur</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Nom d'utilisateur</span>
                                <span class="info-value"><?php echo htmlspecialchars($admin_info['username'] ?? 'Admin'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Compte créé le</span>
                                <span class="info-value"><?php echo date('d/m/Y à H:i', strtotime($admin_info['created_at'] ?? 'now')); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Dernière connexion</span>
                                <span class="info-value"><?php echo date('d/m/Y à H:i'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Rôle</span>
                                <span class="info-value">Administrateur Principal</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations système -->
                <div class="settings-card animate-slide-in" style="animation-delay: 0.6s">
                    <div class="card-header">
                        <h2><i class="fas fa-server"></i> Informations Système</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">PHP Version</span>
                                <span class="info-value"><?php echo $system_info['php_version'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Serveur</span>
                                <span class="info-value"><?php echo $system_info['server_software'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Base de données</span>
                                <span class="info-value"><?php echo $system_info['database_version'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Mémoire utilisée</span>
                                <span class="info-value"><?php echo $system_info['memory_usage'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Heure serveur</span>
                                <span class="info-value"><?php echo $system_info['server_time'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Statut maintenance</span>
                                <span class="info-value"><?php echo $system_info['maintenance_mode'] ?? 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Vérification de la force du mot de passe
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            const lengthReq = document.getElementById('lengthReq');
            const numberReq = document.getElementById('numberReq');
            const specialReq = document.getElementById('specialReq');

            let strength = 0;
            
            // Longueur minimale
            if (password.length >= 8) {
                strength += 1;
                lengthReq.classList.add('met');
                lengthReq.innerHTML = '<i class="fas fa-check-circle"></i><span>Au moins 8 caractères</span>';
            } else {
                lengthReq.classList.remove('met');
                lengthReq.innerHTML = '<i class="fas fa-circle"></i><span>Au moins 8 caractères</span>';
            }

            // Contient un chiffre
            if (/\d/.test(password)) {
                strength += 1;
                numberReq.classList.add('met');
                numberReq.innerHTML = '<i class="fas fa-check-circle"></i><span>Contient un chiffre</span>';
            } else {
                numberReq.classList.remove('met');
                numberReq.innerHTML = '<i class="fas fa-circle"></i><span>Contient un chiffre</span>';
            }

            // Contient un caractère spécial
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                strength += 1;
                specialReq.classList.add('met');
                specialReq.innerHTML = '<i class="fas fa-check-circle"></i><span>Contient un caractère spécial</span>';
            } else {
                specialReq.classList.remove('met');
                specialReq.innerHTML = '<i class="fas fa-circle"></i><span>Contient un caractère spécial</span>';
            }

            // Mise à jour de la barre de force
            strengthBar.className = 'strength-bar';
            if (strength === 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 2) {
                strengthBar.classList.add('strength-medium');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-strong');
            }
        }

        // Vérification de la correspondance des mots de passe
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchElement = document.getElementById('passwordMatch');

            if (confirmPassword === '') {
                matchElement.innerHTML = '';
                return;
            }

            if (password === confirmPassword) {
                matchElement.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Les mots de passe correspondent';
                matchElement.style.color = '#10b981';
            } else {
                matchElement.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i> Les mots de passe ne correspondent pas';
                matchElement.style.color = '#ef4444';
            }
        }

        // JavaScript for responsive sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainContent = document.querySelector('.main-content');
            
            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
                setTimeout(() => sidebarOverlay.classList.remove('opacity-0'), 10);
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('opacity-0');
                setTimeout(() => {
                    sidebarOverlay.classList.add('hidden');
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
        });

        // Confirmation pour les actions critiques
        document.addEventListener('DOMContentLoaded', function() {
            const dangerButtons = document.querySelectorAll('.btn-danger');
            dangerButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ? Cette action est irréversible.')) {
                        e.preventDefault();
                    }
                });
            });
        });

        // Animation au scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observer les cartes pour l'animation
        document.querySelectorAll('.settings-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease-out';
            observer.observe(card);
        });
    </script>
</body>
</html>