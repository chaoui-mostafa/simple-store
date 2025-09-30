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
    <title>Gestion des Produits - Monster Store</title>
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
        
        /* MODALES RESPONSIVES CORRIGÉES */
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
            overflow-y: auto;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .modal.active .modal-content {
            transform: scale(1);
        }
        
        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #3B82F6;
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            flex-shrink: 0;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: bold;
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
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.4);
        }
        
        .modal-body {
            padding: 1.5rem;
            flex: 1;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            background: #f9f9f9;
            flex-shrink: 0;
        }
        
        /* Responsive modal sizes */
        @media (max-width: 640px) {
            .modal-content {
                width: 95%;
                max-width: 95vw;
                max-height: 95vh;
                margin: 0.5rem;
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
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .modal-content {
                max-width: 85vw;
            }
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        
        .form-input, .form-textarea, .form-file {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 0.9rem;
            outline: none;
        }
        
        .form-input:focus, .form-textarea:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 0.5rem;
        }
        
        .image-preview-container img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        
        .btn {
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #2563eb 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
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
                gap: 1rem;
                padding: 1rem;
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
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .form-input {
            border: 1px solid #D1D5DB;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 0.875rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-file {
            padding: 8px;
        }
        
        .form-help {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
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
        
        /* Table styles - RESPONSIVE TABLE STYLES */
        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            background: white;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
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
            position: sticky;
            top: 0;
        }
        
        .data-table tr:hover {
            background-color: #F9FAFB;
        }
        
        /* Mobile table card view */
        .table-card {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3B82F6;
        }
        
        .table-card-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #F3F4F6;
        }
        
        .table-card-row:last-child {
            border-bottom: none;
        }
        
        .table-card-label {
            font-weight: 600;
            color: #374151;
            min-width: 100px;
        }
        
        .table-card-value {
            color: #6B7280;
            text-align: right;
            flex: 1;
        }
        
        /* Show card view on mobile, table view on desktop */
        @media (max-width: 768px) {
            .table-responsive {
                display: none;
            }
            
            .table-card {
                display: block;
            }
            
            .data-table {
                min-width: unset;
            }
        }
        
        @media (min-width: 769px) {
            .table-card {
                display: none;
            }
            
            .table-responsive {
                display: block;
            }
        }
        
        /* Action buttons - CORRECTED */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 14px;
        }
        
        /* View toggle buttons */
        .view-toggle {
            display: flex;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        
        .view-toggle-btn {
            padding: 8px 12px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-toggle-btn.active {
            background: #3B82F6;
            color: white;
        }
        
        .view-toggle-btn:not(.active):hover {
            background: #F3F4F6;
        }
        
        /* Product image in table */
        .table-product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        /* Status indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .status-inactive {
            background: #FEF3C7;
            color: #92400E;
        }
        
        /* Mobile action menu - CORRECTED */
        .mobile-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .mobile-actions button {
            flex: 1;
            min-width: 120px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Compact view for smaller screens */
        @media (max-width: 480px) {
            .table-card {
                padding: 0.75rem;
            }
            
            .table-card-row {
                flex-direction: column;
                padding: 0.75rem 0;
            }
            
            .table-card-label {
                min-width: unset;
                margin-bottom: 0.25rem;
            }
            
            .table-card-value {
                text-align: left;
            }
            
            .mobile-actions {
                flex-direction: column;
            }
            
            .mobile-actions button {
                width: 100%;
                min-width: unset;
                justify-content: center;
            }
            
            /* CORRECTION: Boutons responsifs dans les cartes produits */
            .product-card-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .product-card-actions button {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Scrollbar styling for table */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #F1F5F9;
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }
        
        /* Image preview in modals */
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .image-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        
        /* Form grid responsive */
        .form-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-grid-full {
                grid-column: 1 / -1;
            }
        }
        
        /* Loading overlay for modals */
        .modal-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 16px;
            backdrop-filter: blur(5px);
        }
        
        /* CORRECTION: Styles pour les boutons dans les cartes produits */
        .product-card-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .product-card-actions button {
            flex: 1;
            min-width: 0;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }
        
        /* Scrollbar pour les modales */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Correction pour l'affichage mobile */
        @media (max-width: 640px) {
            .modal {
                padding: 0.5rem;
                align-items: flex-start;
            }
            
            .modal-content {
                margin-top: 2rem;
                margin-bottom: 2rem;
            }
            
            .main-content {
                padding: 0.5rem;
            }
            
            .filter-section {
                padding: 1rem;
            }
            
            .stats-card {
                padding: 1rem;
            }
        }
        
        /* Correction pour les boutons sur très petits écrans */
        @media (max-width: 360px) {
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .product-card-actions {
                flex-direction: column;
            }
            
            .mobile-actions {
                flex-direction: column;
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
                        
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- View Toggle -->
                            <div class="view-toggle">
                                <button class="view-toggle-btn active" data-view="card">
                                    <i class="fas fa-th-large"></i>
                                </button>
                                <button class="view-toggle-btn" data-view="table">
                                    <i class="fas fa-table"></i>
                                </button>
                            </div>
                            
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
                                    class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i> Ajouter un Produit
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Products Tab -->
                <div id="products">
                    <!-- Card View -->
                    <div id="card-view" class="view-content">
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
                                                    class="btn btn-primary">
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
                                                    <span class="text-2xl font-bold text-blue-600"><?php echo number_format($product['price'], 2); ?> DH </span>
                                                    <span class="text-sm text-gray-500">ID: <?php echo $product['id']; ?></span>
                                                </div>
                                                
                                                <!-- Images supplémentaires -->
                                                <?php if (!empty($additionalImages)): ?>
                                                <div class="additional-images mb-4">
                                                    <?php foreach ($additionalImages as $image): ?>
                                                        <div class="additional-image">
                                                            <img src="../../assets/images/product_images/<?php echo htmlspecialchars($image['image_path']); ?>" 
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
                                                
                                                <!-- CORRECTION: Boutons responsifs -->
                                                <div class="product-card-actions">
                                                    <button onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                            class="btn bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors">
                                                        <i class="fas fa-edit mr-1"></i> Modifier
                                                    </button>
                                                    <button onclick="openAddImagesModal(<?php echo $product['id']; ?>)" 
                                                            class="btn bg-green-100 text-green-600 hover:bg-green-200 transition-colors">
                                                        <i class="fas fa-images mr-1"></i> Images
                                                    </button>
                                                    <button onclick="confirmDelete(<?php echo $product['id']; ?>)" 
                                                            class="btn bg-red-100 text-red-600 hover:bg-red-200 transition-colors">
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
                    
                    <!-- Table View -->
                    <div id="table-view" class="view-content hidden">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Nom</th>
                                        <th>Description</th>
                                        <th>Prix</th>
                                        <th>Stock</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-8">
                                                <div class="empty-state">
                                                    <div class="empty-state-icon">
                                                        <i class="fas fa-box-open"></i>
                                                    </div>
                                                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun produit trouvé</h3>
                                                    <p class="text-gray-500 mb-6">Commencez par ajouter votre premier produit</p>
                                                    <button onclick="openModal('addProductModal')" 
                                                            class="btn btn-primary">
                                                        <i class="fas fa-plus mr-2"></i> Ajouter un produit
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): 
                                            $stockClass = 'stock-high';
                                            if ($product['quantity'] <= 5) {
                                                $stockClass = 'stock-low';
                                            } elseif ($product['quantity'] <= 15) {
                                                $stockClass = 'stock-medium';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <img src="../../assets/images/<?php echo htmlspecialchars($product['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                         class="table-product-image">
                                                </td>
                                                <td>
                                                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <div class="text-sm text-gray-500">ID: <?php echo $product['id']; ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-gray-600 max-w-xs truncate"><?php echo htmlspecialchars($product['description']); ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-lg font-bold text-blue-600"><?php echo number_format($product['price'], 2); ?> DH</div>
                                                </td>
                                                <td>
                                                    <span class="stock-badge <?php echo $stockClass; ?>">
                                                        <?php echo $product['quantity']; ?> en stock
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                                class="btn btn-icon bg-blue-100 text-blue-600 hover:bg-blue-200">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button onclick="openAddImagesModal(<?php echo $product['id']; ?>)" 
                                                                class="btn btn-icon bg-green-100 text-green-600 hover:bg-green-200">
                                                            <i class="fas fa-images"></i>
                                                        </button>
                                                        <button onclick="confirmDelete(<?php echo $product['id']; ?>)" 
                                                                class="btn btn-icon bg-red-100 text-red-600 hover:bg-red-200">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Card View for Table -->
                        <div class="table-cards">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): 
                                    $stockClass = 'stock-high';
                                    if ($product['quantity'] <= 5) {
                                        $stockClass = 'stock-low';
                                    } elseif ($product['quantity'] <= 15) {
                                        $stockClass = 'stock-medium';
                                    }
                                ?>
                                    <div class="table-card">
                                        <div class="table-card-row">
                                            <span class="table-card-label">Image</span>
                                            <span class="table-card-value">
                                                <img src="../../assets/images/<?php echo htmlspecialchars($product['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                     class="table-product-image">
                                            </span>
                                        </div>
                                        <div class="table-card-row">
                                            <span class="table-card-label">Nom</span>
                                            <span class="table-card-value"><?php echo htmlspecialchars($product['name']); ?></span>
                                        </div>
                                        <div class="table-card-row">
                                            <span class="table-card-label">Description</span>
                                            <span class="table-card-value"><?php echo htmlspecialchars($product['description']); ?></span>
                                        </div>
                                        <div class="table-card-row">
                                            <span class="table-card-label">Prix</span>
                                            <span class="table-card-value text-lg font-bold text-blue-600"><?php echo number_format($product['price'], 2); ?> DH</span>
                                        </div>
                                        <div class="table-card-row">
                                            <span class="table-card-label">Stock</span>
                                            <span class="table-card-value">
                                                <span class="stock-badge <?php echo $stockClass; ?>">
                                                    <?php echo $product['quantity']; ?> en stock
                                                </span>
                                            </span>
                                        </div>
                                        <div class="table-card-row">
                                            <span class="table-card-label">Actions</span>
                                            <span class="table-card-value">
                                                <div class="mobile-actions">
                                                    <button onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                            class="btn bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors">
                                                        <i class="fas fa-edit mr-1"></i> Modifier
                                                    </button>
                                                    <button onclick="openAddImagesModal(<?php echo $product['id']; ?>)" 
                                                            class="btn bg-green-100 text-green-600 hover:bg-green-200 transition-colors">
                                                        <i class="fas fa-images mr-1"></i> Images
                                                    </button>
                                                    <button onclick="confirmDelete(<?php echo $product['id']; ?>)" 
                                                            class="btn bg-red-100 text-red-600 hover:bg-red-200 transition-colors">
                                                        <i class="fas fa-trash mr-1"></i> Supprimer
                                                    </button>
                                                </div>
                                            </span>
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
    <div id="addProductModal" class="modal" data-type="add">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un Nouveau Produit</h3>
                <button class="modal-close" onclick="closeModal('addProductModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="modal-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-grid-full">
                            <label class="form-label">Nom du Produit *</label>
                            <input type="text" name="name" required class="form-input" placeholder="Entrez le nom du produit">
                        </div>
                        
                        <div class="form-group form-grid-full">
                            <label class="form-label">Description *</label>
                            <textarea name="description" required rows="4" class="form-input form-textarea" placeholder="Décrivez le produit"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Prix (DH) *</label>
                            <input type="number" name="price" step="0.01" required class="form-input" placeholder="0.00" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Quantité *</label>
                            <input type="number" name="quantity" min="0" required class="form-input" placeholder="0">
                        </div>
                        
                        <div class="form-group form-grid-full">
                            <label class="form-label">Image principale du Produit *</label>
                            <input type="file" name="image" accept="image/*" required class="form-input form-file">
                            <p class="form-help">Formats supportés: JPG, PNG, GIF. Taille max: 2MB</p>
                            <div id="mainImagePreview" class="image-preview-container"></div>
                        </div>
                        
                        <div class="form-group form-grid-full">
                            <label class="form-label">Images supplémentaires (optionnel)</label>
                            <input type="file" name="additional_images[]" accept="image/*" multiple class="form-input form-file">
                            <p class="form-help">Vous pouvez sélectionner plusieurs images. Formats supportés: JPG, PNG, GIF. Taille max: 2MB par image</p>
                            <div id="additionalImagesPreview" class="image-preview-container"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addProductModal')" class="btn btn-outline">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </button>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i> Ajouter le Produit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal" data-type="edit">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Modifier le Produit</h3>
                <button class="modal-close" onclick="closeModal('editProductModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="modal-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="editProductId">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-grid-full">
                            <label class="form-label">Nom du Produit *</label>
                            <input type="text" name="name" id="editProductName" required class="form-input">
                        </div>
                        
                        <div class="form-group form-grid-full">
                            <label class="form-label">Description *</label>
                            <textarea name="description" id="editProductDescription" required rows="4" class="form-input form-textarea"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Prix (DH) *</label>
                            <input type="number" name="price" id="editProductPrice" step="0.01" required class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Quantité *</label>
                            <input type="number" name="quantity" id="editProductQuantity" min="0" required class="form-input">
                        </div>
                        
                        <div class="form-group form-grid-full">
                            <label class="form-label">Image Actuelle</label>
                            <div class="flex items-center space-x-4">
                                <img id="editProductImagePreview" src="" alt="Image actuelle" class="w-32 h-32 object-cover rounded-xl border-2 border-gray-300">
                                <div>
                                    <p class="text-sm text-gray-600">Image actuelle du produit</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group form-grid-full">
                            <label class="form-label">Nouvelle Image (Optionnel)</label>
                            <input type="file" name="image" accept="image/*" class="form-input form-file">
                            <p class="form-help">Laissez vide pour conserver l'image actuelle. Formats supportés: JPG, PNG, GIF. Taille max: 2MB</p>
                            <div id="editImagePreview" class="image-preview-container"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editProductModal')" class="btn btn-outline">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </button>
                    <button type="submit" name="edit_product" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i> Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Images Modal -->
    <div id="addImagesModal" class="modal" data-type="add">
        <div class="modal-content modal-md">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter des Images au Produit</h3>
                <button class="modal-close" onclick="closeModal('addImagesModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="modal-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="product_id" id="addImagesProductId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Images supplémentaires *</label>
                        <input type="file" name="product_images[]" accept="image/*" multiple required class="form-input form-file">
                        <p class="form-help">Vous pouvez sélectionner plusieurs images. Formats supportés: JPG, PNG, GIF. Taille max: 2MB par image</p>
                        <div id="productImagesPreview" class="image-preview-container"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addImagesModal')" class="btn btn-outline">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </button>
                    <button type="submit" name="add_product_images" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i> Ajouter les Images
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal - CORRECTED -->
    <div id="deleteConfirmModal" class="modal" data-type="delete">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3 class="modal-title">Confirmer la Suppression</h3>
                <button class="modal-close" onclick="closeModal('deleteConfirmModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="modal-form" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="deleteId">
                <input type="hidden" name="delete_product" value="1">
                
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
                        <p class="text-gray-700 mb-2">Êtes-vous sûr de vouloir supprimer ce produit ?</p>
                        <p class="text-gray-500 text-sm">Cette action est irréversible et supprimera toutes les données associées.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('deleteConfirmModal')" class="btn btn-outline">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash mr-2"></i> Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Image Confirmation Modal - CORRECTED -->
    <div id="deleteImageConfirmModal" class="modal" data-type="delete">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3 class="modal-title">Confirmer la Suppression</h3>
                <button class="modal-close" onclick="closeModal('deleteImageConfirmModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="modal-form" id="deleteImageForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="image_id" id="deleteImageId">
                <input type="hidden" name="delete_product_image" value="1">
                
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
                        <p class="text-gray-700 mb-2">Êtes-vous sûr de vouloir supprimer cette image ?</p>
                        <p class="text-gray-500 text-sm">Cette action est irréversible.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('deleteImageConfirmModal')" class="btn btn-outline">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash mr-2"></i> Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', handleEscapeKey);
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            document.removeEventListener('keydown', handleEscapeKey);
        }

        function handleEscapeKey(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    closeModal(activeModal.id);
                }
            }
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

        // CORRECTION: Fonction de suppression corrigée
        function confirmDelete(productId) {
            document.getElementById('deleteId').value = productId;
            openModal('deleteConfirmModal');
        }

        function confirmImageDelete(imageId) {
            document.getElementById('deleteImageId').value = imageId;
            openModal('deleteImageConfirmModal');
        }

        // Image preview functionality
        function setupImagePreview(inputId, previewId) {
            const input = document.querySelector(`#${inputId} input[type="file"]`);
            const preview = document.getElementById(previewId);
            
            if (input && preview) {
                input.addEventListener('change', function(e) {
                    preview.innerHTML = '';
                    const files = e.target.files;
                    
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const previewItem = document.createElement('div');
                                previewItem.className = 'image-preview';
                                previewItem.innerHTML = `
                                    <img src="${e.target.result}" alt="Preview">
                                    <button type="button" class="image-preview-remove" onclick="removeImagePreview(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                `;
                                preview.appendChild(previewItem);
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                });
            }
        }

        function removeImagePreview(button) {
            const previewItem = button.closest('.image-preview');
            previewItem.remove();
            
            // Clear the file input
            const fileInput = button.closest('form').querySelector('input[type="file"]');
            fileInput.value = '';
        }

        // View toggle functionality
        function toggleView(viewType) {
            document.querySelectorAll('.view-content').forEach(view => {
                view.classList.add('hidden');
            });
            
            document.getElementById(viewType + '-view').classList.remove('hidden');
            
            document.querySelectorAll('.view-toggle-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.view-toggle-btn[data-view="${viewType}"]`).classList.add('active');
            
            localStorage.setItem('preferredView', viewType);
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
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

            // Setup image previews
            setupImagePreview('addProductModal', 'mainImagePreview');
            setupImagePreview('addProductModal', 'additionalImagesPreview');
            setupImagePreview('editProductModal', 'editImagePreview');
            setupImagePreview('addImagesModal', 'productImagesPreview');

            // Search functionality
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    // Search in card view
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
                    
                    // Search in table view
                    const tableRows = document.querySelectorAll('.data-table tbody tr');
                    const tableCards = document.querySelectorAll('.table-card');
                    
                    tableRows.forEach(row => {
                        const productName = row.cells[1].textContent.toLowerCase();
                        const productDescription = row.cells[2].textContent.toLowerCase();
                        
                        if (productName.includes(searchTerm) || productDescription.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    tableCards.forEach(card => {
                        const productName = card.querySelector('.table-card-row:nth-child(2) .table-card-value').textContent.toLowerCase();
                        const productDescription = card.querySelector('.table-card-row:nth-child(3) .table-card-value').textContent.toLowerCase();
                        
                        if (productName.includes(searchTerm) || productDescription.includes(searchTerm)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }

            // View toggle event listeners
            document.querySelectorAll('.view-toggle-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    toggleView(this.dataset.view);
                });
            });

            // Load preferred view from localStorage
            const preferredView = localStorage.getItem('preferredView') || 'card';
            toggleView(preferredView);
        });
    </script>
</body>
</html>