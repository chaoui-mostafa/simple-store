<?php
session_start();
require_once '../../config/db.php';
require_once '../../controllers/AdminController.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$pdo = $db->connect();
$adminController = new AdminController();

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
    
    if (isset($_POST['create_admin'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $_SESSION['error'] = 'Les mots de passe ne correspondent pas.';
        } else {
            $adminController->createAdmin($username, $password);
        }
    } elseif (isset($_POST['change_password'])) {
        $adminId = $_POST['admin_id'];
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'Les nouveaux mots de passe ne correspondent pas.';
        } else {
            $adminController->changePassword($adminId, $currentPassword, $newPassword);
        }
    } elseif (isset($_POST['delete_admin'])) {
        $adminId = $_POST['admin_id'];
        $adminController->deleteAdmin($adminId);
    }
    
    // Actualiser la page pour afficher les mises à jour
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$admins = $adminController->getAllAdmins();
$currentAdminId = $_SESSION['admin_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Administrateurs - Monster Store</title>
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
            --dark-color: #1F2937;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .glass-morphism {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
        }
        
        .glass-dark {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar {
            width: 280px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(180deg, rgba(30, 64, 175, 0.9) 0%, rgba(59, 130, 246, 0.8) 100%);
            color: white;
            backdrop-filter: blur(20px);
        }
        
        .main-content {
            margin-left: 280px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: calc(100% - 280px);
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
                height: 100vh;
                top: 0;
                left: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 50px rgba(0, 0, 0, 0.3);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        .nav-link {
            transition: all 0.3s ease;
            border-radius: 12px;
            margin: 0.25rem 0.5rem;
            padding: 12px 16px;
            position: relative;
            overflow: hidden;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .nav-link:hover::before {
            left: 100%;
        }
        
        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .nav-link:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .stats-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s;
        }
        
        .stats-card:hover::before {
            transform: rotate(45deg) translate(50px, 50px);
        }
        
        .stats-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .admin-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 16px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .admin-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .admin-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal {
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
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
            transform: scale(0.8) translateY(50px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .modal.active .modal-content {
            transform: scale(1) translateY(0);
        }
        
        .btn {
            transition: all 0.3s ease;
            border-radius: 12px;
            font-weight: 600;
            padding: 14px 24px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 16px;
            color: white;
            z-index: 1000;
            opacity: 0;
            transform: translateX(100%) scale(0.8);
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(20px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .notification.show {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }
        
        /* Animation pour les nouveaux éléments */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        /* Style pour les formulaires */
        .form-input {
            border: 1px solid rgba(209, 213, 219, 0.5);
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            width: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            font-size: 14px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
        }
        
        /* Badge pour le statut */
        .role-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(10px);
            border: 1px solid;
        }
        
        .role-admin {
            background: rgba(219, 234, 254, 0.8);
            color: #1E40AF;
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .role-super-admin {
            background: rgba(254, 243, 199, 0.8);
            color: #92400E;
            border-color: rgba(245, 158, 11, 0.3);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6B7280;
        }
        
        .empty-state-icon {
            font-size: 5rem;
            color: #D1D5DB;
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }
        
        /* Table styles - Modern design */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .data-table th {
            background: rgba(249, 250, 251, 0.8);
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        
        .data-table tr:hover {
            background: rgba(249, 250, 251, 0.6);
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
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
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .btn-icon:hover {
            transform: translateY(-2px) scale(1.1);
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 6px;
            border-radius: 3px;
            margin-top: 8px;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .password-strength::after {
            content: '';
            display: block;
            height: 100%;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .strength-weak::after {
            background: linear-gradient(90deg, #EF4444, #F59E0B);
            width: 25%;
        }
        
        .strength-medium::after {
            background: linear-gradient(90deg, #F59E0B, #10B981);
            width: 50%;
        }
        
        .strength-strong::after {
            background: linear-gradient(90deg, #10B981, #3B82F6);
            width: 75%;
        }
        
        .strength-very-strong::after {
            background: linear-gradient(90deg, #10B981, #059669);
            width: 100%;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .table-container {
                border-radius: 16px;
                margin: 0 -1rem;
                width: calc(100% + 2rem);
            }
            
            .data-table {
                display: none;
            }
            
            .mobile-cards {
                display: block;
                padding: 1rem;
            }
            
            .admin-card-mobile {
                background: rgba(255, 255, 255, 0.95);
                border-radius: 16px;
                padding: 1.5rem;
                margin-bottom: 1rem;
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 13px;
            }
            
            .modal-content {
                width: 95%;
                max-width: 95vw;
                margin: 1rem;
                border-radius: 20px;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-cards {
                display: none;
            }
            
            .data-table {
                display: table;
            }
        }
        
        /* Floating elements animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
        }
        
        /* Loading animation */
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
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
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Hover effects for cards */
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
        }
        
        /* Pulse animation for notifications */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-400 via-purple-500 to-pink-500">
    <!-- Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification gradient-bg show pulse">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-white text-lg mr-3"></i>
                <span class="text-white font-medium"><?php echo $_SESSION['success']; ?></span>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification bg-red-500 show pulse">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-white text-lg mr-3"></i>
                <span class="text-white font-medium"><?php echo $_SESSION['error']; ?></span>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'assets/slide.php'; ?>
       
        <!-- Main content -->
        <div class="main-content">
            <!-- Top bar -->
            <header class="glass-morphism m-4 rounded-2xl shadow-xl">
                <div class="flex justify-between items-center py-4 px-6">
                   
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center cursor-pointer hover:bg-white/30 transition-all backdrop-blur-sm">
                                <i class="fas fa-bell text-white text-lg"></i>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs pulse"><?php echo count($admins); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center shadow-lg floating">
                                <span class="text-white font-bold text-lg"><?php echo substr($_SESSION['admin_username'], 0, 1); ?></span>
                            </div>
                            <span class="text-white font-medium hidden md:block text-shadow"><?php echo $_SESSION['admin_username']; ?></span>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main content area -->
            <main class="p-4">
                <!-- Stats overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 stats-grid">
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up hover-lift" style="animation-delay: 0.1s;">
                        <div class="flex items-center relative z-10">
                            <div class="p-3 rounded-2xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo count($admins); ?></h3>
                                <p class="text-blue-100 opacity-90">Administrateurs Total</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up hover-lift" style="animation-delay: 0.2s;">
                        <div class="flex items-center relative z-10">
                            <div class="p-3 rounded-2xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-user-shield text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold">1</h3>
                                <p class="text-green-100 opacity-90">Super Administrateurs</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up hover-lift" style="animation-delay: 0.3s;">
                        <div class="flex items-center relative z-10">
                            <div class="p-3 rounded-2xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-user-cog text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo max(0, count($admins) - 1); ?></h3>
                                <p class="text-purple-100 opacity-90">Administrateurs</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Admins Section -->
                <div id="admins">
                    <!-- Add Admin Button -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-white drop-shadow-lg">Gestion des Administrateurs</h2>
                        <button onclick="openModal('addAdminModal')" 
                                class="btn btn-primary shadow-lg hover-lift">
                            <i class="fas fa-user-plus mr-2"></i> Ajouter un Administrateur
                        </button>
                    </div>
                    
                    <!-- Admins List -->
                    <div class="table-container">
                        <?php if (empty($admins)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon floating">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun administrateur trouvé</h3>
                                <p class="text-gray-500 mb-6">Commencez par ajouter votre premier administrateur</p>
                                <button onclick="openModal('addAdminModal')" 
                                        class="btn btn-primary hover-lift">
                                    <i class="fas fa-user-plus mr-2"></i> Ajouter un administrateur
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Desktop Table View -->
                            <div class="overflow-x-auto">
                                <table class="w-full data-table">
                                    <thead>
                                        <tr>
                                            <th class="py-4 px-6">Nom d'utilisateur</th>
                                            <th class="py-4 px-6">Rôle</th>
                                            <th class="py-4 px-6">Date de création</th>
                                            <th class="py-4 px-6">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admins as $index => $admin): 
                                            $isSuperAdmin = $admin['id'] == 1;
                                            $isCurrentUser = $admin['id'] == $currentAdminId;
                                            $animationDelay = $index * 0.1;
                                        ?>
                                            <tr class="animate-fade-in-up hover-lift" style="animation-delay: <?php echo $animationDelay; ?>s">
                                                <td class="py-4 px-6">
                                                    <div class="flex items-center">
                                                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold mr-4 shadow-lg">
                                                            <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($admin['username']); ?></p>
                                                            <p class="text-sm text-gray-500">ID: <?php echo $admin['id']; ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="role-badge <?php echo $isSuperAdmin ? 'role-super-admin' : 'role-admin'; ?>">
                                                        <i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user'; ?>"></i>
                                                        <?php echo $isSuperAdmin ? 'Super Admin' : 'Admin'; ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6 text-gray-600">
                                                    <?php 
                                                        if(isset($admin['created_at'])) {
                                                            echo date('d/m/Y H:i', strtotime($admin['created_at']));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    ?>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <div class="action-buttons">
                                                        <?php if (!$isSuperAdmin): ?>
                                                            <button onclick="openChangePasswordModal(<?php echo $admin['id']; ?>)" 
                                                                    class="btn-icon bg-blue-100 text-blue-600 hover:bg-blue-200" title="Changer le mot de passe">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                            <form method="POST">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                                <button type="submit" name="delete_admin" 
                                                                        class="btn-icon bg-red-100 text-red-600 hover:bg-red-200"
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ?')" 
                                                                        title="Supprimer l'administrateur">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Actions non disponibles</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Mobile Card View -->
                            <div class="mobile-cards">
                                <?php foreach ($admins as $index => $admin): 
                                    $isSuperAdmin = $admin['id'] == 1;
                                    $isCurrentUser = $admin['id'] == $currentAdminId;
                                ?>
                                    <div class="admin-card-mobile animate-fade-in-up" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center">
                                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold mr-3 shadow-lg">
                                                    <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($admin['username']); ?></p>
                                                    <span class="role-badge <?php echo $isSuperAdmin ? 'role-super-admin' : 'role-admin'; ?> text-xs">
                                                        <?php echo $isSuperAdmin ? 'Super Admin' : 'Admin'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-2 text-sm text-gray-600">
                                            <div class="flex justify-between">
                                                <span>ID:</span>
                                                <span class="font-semibold"><?php echo $admin['id']; ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Date de création:</span>
                                                <span class="font-semibold">
                                                    <?php 
                                                        if(isset($admin['created_at'])) {
                                                            echo date('d/m/Y H:i', strtotime($admin['created_at']));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <?php if (!$isSuperAdmin): ?>
                                                <div class="flex space-x-2">
                                                    <button onclick="openChangePasswordModal(<?php echo $admin['id']; ?>)" 
                                                            class="btn btn-primary flex-1 text-sm">
                                                        <i class="fas fa-key mr-2"></i> Changer MDP
                                                    </button>
                                                    <form method="POST" class="flex-1">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                        <button type="submit" name="delete_admin" 
                                                                class="btn btn-danger w-full text-sm"
                                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ?')">
                                                            <i class="fas fa-trash mr-2"></i> Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-500 text-center block w-full bg-gray-100 px-3 py-2 rounded-full">
                                                    Actions non disponibles pour Super Admin
                                                </span>
                                            <?php endif; ?>
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

    <!-- Add Admin Modal -->
    <div id="addAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6">
                <h2 class="modal-title text-xl font-bold">Ajouter un Administrateur</h2>
                <button class="modal-close bg-white/20 hover:bg-white/30" onclick="closeModal('addAdminModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div>
                        <label class="form-label">Nom d'utilisateur</label>
                        <input type="text" name="username" required class="form-input" placeholder="Entrez le nom d'utilisateur">
                    </div>
                    
                    <div>
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" id="password" required class="form-input" placeholder="Entrez le mot de passe">
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    
                    <div>
                        <label class="form-label">Confirmer le mot de passe</label>
                        <input type="password" name="confirm_password" required class="form-input" placeholder="Confirmez le mot de passe">
                    </div>
                </div>
                <div class="modal-footer p-6 border-t border-gray-200">
                    <button type="button" onclick="closeModal('addAdminModal')" 
                            class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Annuler
                    </button>
                    <button type="submit" name="create_admin" 
                            class="btn btn-primary">
                        <i class="fas fa-user-plus mr-2"></i> Créer l'administrateur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header bg-gradient-to-r from-green-500 to-blue-500 text-white p-6">
                <h2 class="modal-title text-xl font-bold">Changer le Mot de Passe</h2>
                <button class="modal-close bg-white/20 hover:bg-white/30" onclick="closeModal('changePasswordModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="admin_id" id="changePasswordAdminId">
                    
                    <div>
                        <label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="current_password" required class="form-input" placeholder="Entrez votre mot de passe actuel">
                    </div>
                    
                    <div>
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="new_password" id="newPassword" required class="form-input" placeholder="Entrez le nouveau mot de passe">
                        <div id="new-password-strength" class="password-strength"></div>
                    </div>
                    
                    <div>
                        <label class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" required class="form-input" placeholder="Confirmez le nouveau mot de passe">
                    </div>
                </div>
                <div class="modal-footer p-6 border-t border-gray-200">
                    <button type="button" onclick="closeModal('changePasswordModal')" 
                            class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Annuler
                    </button>
                    <button type="submit" name="change_password" 
                            class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i> Mettre à jour
                    </button>
                </div>
            </form>
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

        function openChangePasswordModal(adminId) {
            document.getElementById('changePasswordAdminId').value = adminId;
            openModal('changePasswordModal');
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

            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const passwordStrength = document.getElementById('password-strength');
            
            if (passwordInput && passwordStrength) {
                passwordInput.addEventListener('input', function() {
                    updatePasswordStrength(this.value, passwordStrength);
                });
            }

            const newPasswordInput = document.getElementById('newPassword');
            const newPasswordStrength = document.getElementById('new-password-strength');
            
            if (newPasswordInput && newPasswordStrength) {
                newPasswordInput.addEventListener('input', function() {
                    updatePasswordStrength(this.value, newPasswordStrength);
                });
            }

            function updatePasswordStrength(password, strengthElement) {
                let strength = 0;
                
                // Check password length
                if (password.length >= 8) strength += 1;
                
                // Check for mixed case
                if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
                
                // Check for numbers
                if (password.match(/([0-9])/)) strength += 1;
                
                // Check for special characters
                if (password.match(/([!,@,#,$,%,^,&,*,?,_,~])/)) strength += 1;
                
                // Update strength indicator
                strengthElement.className = 'password-strength';
                
                if (password.length === 0) {
                    strengthElement.classList.add('strength-weak');
                    strengthElement.style.setProperty('--width', '0%');
                } else if (strength <= 1) {
                    strengthElement.classList.add('strength-weak');
                } else if (strength === 2) {
                    strengthElement.classList.add('strength-medium');
                } else if (strength === 3) {
                    strengthElement.classList.add('strength-strong');
                } else {
                    strengthElement.classList.add('strength-very-strong');
                }
            }

            // Add hover effects to cards
            const cards = document.querySelectorAll('.admin-card, .stats-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add CSS for ripple effect
            const style = document.createElement('style');
            style.textContent = `
                .ripple {
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.6);
                    transform: scale(0);
                    animation: ripple-animation 0.6s linear;
                }
                
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>