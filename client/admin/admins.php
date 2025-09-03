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
    <title>Gestion des Administrateurs - StyleShop</title>
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
        
        .admin-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .admin-card:hover {
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
        
        /* Badge pour le statut */
        .role-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .role-admin {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .role-super-admin {
            background-color: #FEF3C7;
            color: #92400E;
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
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        
        .strength-weak {
            background-color: #EF4444;
            width: 25%;
        }
        
        .strength-medium {
            background-color: #F59E0B;
            width: 50%;
        }
        
        .strength-strong {
            background-color: #10B981;
            width: 75%;
        }
        
        .strength-very-strong {
            background-color: #10B981;
            width: 100%;
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
                   <a href="index.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-images mr-3"></i> Home
                </a>
                <a href="../index.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white hover:bg-white/10 transition-all duration-200">
                    <i class="fas fa-store mr-3"></i> Retour à la boutique
                </a>
                <a href="products.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-box mr-3"></i> Produits
                </a>
                <a href="orders.php" class="nav-link block py-3 px-4 text-blue-100 hover:text-white transition-all duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i> Commandes
                </a>
             
                <a href="admins.php" class="nav-link active block py-3 px-4 text-white bg-white/15 transition-all duration-200">
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
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo count($admins); ?></h3>
                                <p class="text-blue-100">Administrateurs Total</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.2s; background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-user-shield text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold">1</h3>
                                <p class="text-green-100">Super Administrateurs</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card rounded-2xl p-6 text-white animate-fade-in-up" style="animation-delay: 0.3s; background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <i class="fas fa-user-cog text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-3xl font-bold"><?php echo count($admins) - 1; ?></h3>
                                <p class="text-purple-100">Administrateurs</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Admins Section -->
                <div id="admins">
                    <!-- Add Admin Button -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Gestion des Administrateurs</h2>
                        <button onclick="openModal('addAdminModal')" 
                                class="btn btn-primary px-6 py-3 text-white rounded-xl font-medium">
                            <i class="fas fa-user-plus mr-2"></i> Ajouter un Administrateur
                        </button>
                    </div>
                    
                    <!-- Admins List -->
                    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                        <?php if (empty($admins)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun administrateur trouvé</h3>
                                <p class="text-gray-500 mb-6">Commencez par ajouter votre premier administrateur</p>
                                <button onclick="openModal('addAdminModal')" 
                                        class="btn btn-primary px-6 py-3 text-white">
                                    <i class="fas fa-user-plus mr-2"></i> Ajouter un administrateur
                                </button>
                            </div>
                        <?php else: ?>
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
                                        <?php foreach ($admins as $admin): 
                                            $isSuperAdmin = $admin['id'] == 1;
                                            $isCurrentUser = $admin['id'] == $currentAdminId;
                                        ?>
                                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                <td class="py-4 px-6">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold mr-3">
                                                            <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($admin['username']); ?></p>
                                                            <p class="text-sm text-gray-500">ID: <?php echo $admin['id']; ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="role-badge <?php echo $isSuperAdmin ? 'role-super-admin' : 'role-admin'; ?>">
                                                        <?php echo $isSuperAdmin ? 'Super Admin' : 'Admin'; ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <!-- Removed date display since created_at column doesn't exist -->
                                                    N/A
                                                </td>
                                                <td class="py-4 px-6">
                                                    <div class="action-buttons">
                                                        <?php if (!$isSuperAdmin): ?>
                                                            <button onclick="openChangePasswordModal(<?php echo $admin['id']; ?>)" 
                                                                    class="btn-icon bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors" title="Changer le mot de passe">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                            <form method="POST">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                                <button type="submit" name="delete_admin" 
                                                                        class="btn-icon bg-red-100 text-red-600 hover:bg-red-200 transition-colors"
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ?')" 
                                                                        title="Supprimer l'administrateur">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-sm text-gray-500">Actions non disponibles</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div id="addAdminModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="modal-content bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">Ajouter un Administrateur</h3>
            </div>
            <form method="POST" class="p-6 space-y-4">
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
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addAdminModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="create_admin" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors">
                        <i class="fas fa-user-plus mr-2"></i> Créer l'administrateur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="modal-content bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">Changer le Mot de Passe</h3>
            </div>
            <form method="POST" class="p-6 space-y-4">
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
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('changePasswordModal')" 
                            class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="change_password" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors">
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
                    strengthElement.style.width = '0%';
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
        });
    </script>
</body>
</html>