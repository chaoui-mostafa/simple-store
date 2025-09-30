<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/OrderController.php';

// ✅ Vérifier si l'utilisateur vient d'une commande réussie
if (!isset($_SESSION['order_success']) || !isset($_SESSION['order_id'])) {
    // Sinon, essayer de récupérer la commande par le code dans l'URL
    if (isset($_GET['order_code']) && !empty($_GET['order_code'])) {
        $orderController = new OrderController();
        $order = $orderController->getOrderByCode($_GET['order_code']);
        
        if ($order) {
            $_SESSION['order_success'] = true;
            $_SESSION['order_id'] = $order['id'];
        } else {
            $_SESSION['error'] = "Commande non trouvée ! Veuillez vérifier votre code de commande.";
            header('Location: index.php');
            exit();
        }
    } else {
        $_SESSION['error'] = "Accès invalide à la commande. Veuillez d'abord passer une commande.";
        header('Location: index.php');
        exit();
    }
}

// ✅ Initialiser OrderController
$orderController = new OrderController();

// ✅ Obtenir les détails de la commande depuis la base de données
$orderId = $_SESSION['order_id'];
$order = $orderController->getOrder($orderId);

if (!$order) {
    $_SESSION['error'] = "Commande non trouvée ! Veuillez contacter le support avec votre code de commande.";
    header('Location: index.php');
    exit();
}

// ✅ Formater les détails de la commande
$orderNumber   = $order['order_code'] ?? 'N/A';
$orderDate     = $order['created_at'] ?? date('Y-m-d H:i:s');
$customerName  = $order['customer_name'] ?? 'Client';
$customerEmail = $order['customer_email'] ?? '';
$customerPhone = $order['customer_phone'] ?? '';
$orderTotal    = isset($order['product_price'], $order['quantity']) 
                 ? ($order['product_price'] * $order['quantity']) 
                 : 0;
$address       = $order['customer_address'] ?? 'N/A';

// ✅ Contact support WhatsApp
$whatsappNumber = '+212724893110';

// ✅ Effacer le flag de session (garder order_id pour référence si nécessaire)
unset($_SESSION['order_success']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Commande Réussie - Monster Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --error-color: #ef4444;
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            min-height: 100vh;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Modern Success Card */
        .success-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        
        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #4f46e5, #8b5cf6, #ec4899, #f59e0b);
            background-size: 400% 400%;
            animation: gradient-shift 3s ease infinite;
            z-index: 10;
        }
        
        @keyframes gradient-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Confetti Animation */
        .confetti {
            position: fixed;
            width: 12px;
            height: 12px;
            background: #ffd700;
            opacity: 0;
            animation: confetti-fall 5s linear infinite;
            z-index: -1;
        }
        
        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg) scale(1);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg) scale(0.5);
                opacity: 0;
            }
        }
        
        /* Animated Checkmark */
        .checkmark {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: block;
            stroke-width: 5;
            stroke: #fff;
            stroke-miterlimit: 10;
            box-shadow: 0 0 30px rgba(79, 70, 229, 0.4);
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        
        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 5;
            stroke-miterlimit: 10;
            stroke: var(--primary-color);
            fill: none;
            animation: stroke .6s cubic-bezier(0.650, 0.000, 0.450, 1.000) forwards;
        }
        
        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke .3s cubic-bezier(0.650, 0.000, 0.450, 1.000) .8s forwards;
        }
        
        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }
        
        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }
        
        @keyframes fill {
            100% { box-shadow: inset 0px 0px 0px 50px var(--primary-color); }
        }
        
        /* Modern Grid Layout */
        .order-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .order-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Order Item Styling */
        .order-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid transparent;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .order-item:hover {
            border-left-color: var(--primary-color);
            transform: translateX(8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Animations */
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .floating-icon {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        
        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* Enhanced Progress Tracker */
        .progress-tracker {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 2rem 0;
            padding: 0 1rem;
        }
        
        .progress-tracker::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5 25%, #e5e7eb 25%);
            transform: translateY(-50%);
            z-index: 1;
            border-radius: 2px;
        }
        
        .progress-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 25%;
        }
        
        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }
        
        .step-active .step-icon {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .step-text {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .step-active .step-text {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Modern Feature Cards */
        .feature-card {
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(79, 70, 229, 0.05), transparent);
            transition: left 0.5s;
        }
        
        .feature-card:hover::before {
            left: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        /* Modern Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
            text-decoration: none;
            gap: 0.75rem;
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
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Enhanced Responsive Design */
        @media (max-width: 1024px) {
            .success-card {
                padding: 1.75rem;
                margin: 1.5rem auto;
            }
            
            .checkmark {
                width: 90px;
                height: 90px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 0.75rem;
                align-items: flex-start;
            }
            
            .success-card {
                padding: 1.5rem;
                margin: 1rem auto;
                border-radius: 20px;
            }
            
            .checkmark {
                width: 80px;
                height: 80px;
            }
            
            .order-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .progress-tracker {
                flex-direction: column;
                gap: 1.5rem;
                padding: 0;
                margin: 1.5rem 0;
            }
            
            .progress-tracker::before {
                display: none;
            }
            
            .progress-step {
                flex-direction: row;
                width: 100%;
                text-align: left;
                gap: 1rem;
                padding: 1rem;
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }
            
            .step-icon {
                width: 45px;
                height: 45px;
                margin-bottom: 0;
                flex-shrink: 0;
            }
            
            .step-text {
                font-size: 1rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn {
                width: 100%;
                padding: 1.125rem 2rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
            }
            
            .success-card {
                padding: 1.25rem;
                border-radius: 18px;
                margin: 0.5rem auto;
            }
            
            .checkmark {
                width: 70px;
                height: 70px;
            }
            
            h1 {
                font-size: 1.75rem !important;
            }
            
            h2 {
                font-size: 1.4rem !important;
            }
            
            .text-lg {
                font-size: 1rem;
            }
            
            .text-xl {
                font-size: 1.2rem;
            }
            
            .support-buttons {
                flex-direction: column;
            }
            
            .social-sharing {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
            }
            
            .social-sharing a {
                margin: 0;
            }
        }
        
        @media (max-width: 360px) {
            .success-card {
                padding: 1rem;
            }
            
            .checkmark {
                width: 60px;
                height: 60px;
            }
            
            h1 {
                font-size: 1.5rem !important;
            }
            
            .btn {
                padding: 1rem 1.5rem;
                font-size: 0.9rem;
            }
        }
        
        /* Mobile First Enhancements */
        .mobile-optimized {
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        
        /* Safe area support for modern phones */
        @supports (padding: max(0px)) {
            body {
                padding-left: max(0.5rem, env(safe-area-inset-left));
                padding-right: max(0.5rem, env(safe-area-inset-right));
                padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
                padding-top: max(0.5rem, env(safe-area-inset-top));
            }
        }
        
        /* Animation Classes */
        .animate-slide-in-up {
            animation: slideInUp 0.6s ease-out forwards;
        }
        
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
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        
        /* Print Styles */
        @media print {
            body {
                background: white !important;
                padding: 0;
            }
            
            .success-card {
                box-shadow: none;
                border: 1px solid #ddd;
                max-width: 100%;
                margin: 0;
                backdrop-filter: none;
            }
            
            .no-print {
                display: none !important;
            }
            
            .action-buttons, .social-sharing, .progress-tracker, .feature-grid {
                display: none !important;
            }
        }
        
        /* High contrast support */
        @media (prefers-contrast: high) {
            .success-card {
                border: 2px solid #000;
            }
            
            .btn {
                border: 2px solid;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="mobile-optimized">
    <!-- Confetti Animation -->
    <div id="confetti-container"></div>
    
    <div class="success-card animate-slide-in-up">
        <div class="text-center">
            <!-- Animated Checkmark -->
            <div class="flex justify-center mb-8">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                    <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
            
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                Commande Réussie !
            </h1>
            
            <p class="text-gray-600 mb-8 text-xl">
                Merci, <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($customerName); ?></span> ! 
                Votre commande a été confirmée.
            </p>

            <!-- Progress Tracker -->
            <div class="progress-tracker mb-8 no-print">
                <div class="progress-step step-active">
                    <div class="step-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="step-text">Commande<br>passée</div>
                </div>
                <div class="progress-step">
                    <div class="step-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="step-text">En<br>traitement</div>
                </div>
                <div class="progress-step">
                    <div class="step-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <div class="step-text">Expédiée</div>
                </div>
                <div class="progress-step">
                    <div class="step-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="step-text">Livrée</div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="order-grid">
                <!-- Order Summary -->
                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 p-6 rounded-2xl border border-indigo-100">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-receipt mr-3 text-indigo-600"></i>
                        Récapitulatif
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="animate-slide-in-up delay-100">
                            <p class="text-sm text-gray-600 mb-2 flex items-center">
                                <i class="fas fa-hashtag mr-2 text-indigo-500"></i>Numéro de Commande
                            </p>
                            <p class="font-mono font-bold text-indigo-700 text-lg"><?php echo htmlspecialchars($orderNumber); ?></p>
                        </div>
                        
                        <div class="animate-slide-in-up delay-200">
                            <p class="text-sm text-gray-600 mb-2 flex items-center">
                                <i class="fas fa-calendar-day mr-2 text-indigo-500"></i>Date de Commande
                            </p>
                            <p class="font-semibold text-gray-800"><?php echo date('d/m/Y à H:i', strtotime($orderDate)); ?></p>
                        </div>
                        
                        <div class="animate-slide-in-up delay-300">
                            <p class="text-sm text-gray-600 mb-2 flex items-center">
                                <i class="fas fa-map-marker-alt mr-2 text-indigo-500"></i>Adresse de Livraison
                            </p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($address); ?></p>
                        </div>

                        <!-- Order Status -->
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-2 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-indigo-500"></i>Statut
                            </p>
                            <span class="status-badge status-confirmed">
                                <i class="fas fa-check-circle mr-2"></i> Confirmée
                            </span>
                        </div>

                        <!-- Order Items -->
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-3 flex items-center">
                                <i class="fas fa-box-open mr-2 text-indigo-500"></i>Articles Commandés
                            </p>
                            <div class="space-y-3">
                                <div class="order-item">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($order['product_name']); ?></span>
                                        <span class="text-indigo-600 font-bold text-lg"><?php echo number_format($order['product_price'], 2); ?> DH</span>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-500">
                                        <span><i class="fas fa-layer-group mr-2"></i>Quantité: <?php echo $order['quantity']; ?></span>
                                        <span><i class="fas fa-calculator mr-2"></i>Sous-total: <?php echo number_format($order['product_price'] * $order['quantity'], 2); ?> DH</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Amount -->
                        <div class="border-t border-indigo-200 pt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-bold text-gray-800">Total</span>
                                <span class="text-3xl font-bold text-indigo-700"><?php echo number_format($orderTotal, 2); ?> DH</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Info & Support -->
                <div class="space-y-6">
                    <!-- Customer Information -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-user-circle mr-3 text-blue-600"></i>
                            Informations Client
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-user mr-3 w-6"></i>
                                <span class="font-medium"><?php echo htmlspecialchars($customerName); ?></span>
                            </div>
                            <?php if (!empty($customerEmail)): ?>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-envelope mr-3 w-6"></i>
                                <span><?php echo htmlspecialchars($customerEmail); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-phone mr-3 w-6"></i>
                                <span><?php echo htmlspecialchars($customerPhone); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Support Information -->
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-6 rounded-2xl border border-green-200">
                        <h3 class="text-xl font-semibold text-green-800 mb-4 flex items-center">
                            <i class="fas fa-headset mr-3"></i>
                            Support Client
                        </h3>
                        <p class="text-green-700 mb-4">
                            Notre équipe est disponible pour vous accompagner.
                        </p>
                        <div class="space-y-3 support-buttons">
                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $whatsappNumber); ?>" 
                               target="_blank" 
                               class="btn btn-primary w-full">
                                <i class="fab fa-whatsapp text-lg"></i>
                                WhatsApp
                            </a>
                            <a href="mailto:support@monsterstore.com" 
                               class="btn btn-secondary w-full">
                                <i class="fas fa-envelope"></i>
                                Email
                            </a>
                            <a href="tel:+212724893110" 
                               class="btn btn-secondary w-full">
                                <i class="fas fa-phone"></i>
                                Appeler
                            </a>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="bg-blue-50 p-6 rounded-2xl border border-blue-200">
                        <h3 class="text-xl font-semibold text-blue-800 mb-4 flex items-center">
                            <i class="fas fa-road mr-3"></i>
                            Prochaines Étapes
                        </h3>
                        <ul class="text-blue-700 space-y-2">
                            <li class="flex items-center"><i class="fas fa-check-circle mr-3 text-green-500"></i> Email de confirmation envoyé</li>
                            <li class="flex items-center"><i class="fas fa-clock mr-3 text-yellow-500"></i> Traitement sous 24h</li>
                            <li class="flex items-center"><i class="fas fa-truck mr-3 text-blue-500"></i> Livraison en 3-5 jours</li>
                            <li class="flex items-center"><i class="fas fa-bell mr-3 text-purple-500"></i> Mises à jour par email</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 action-buttons mt-8 no-print">
                <a href="index.php" 
                   class="btn btn-primary pulse">
                    <i class="fas fa-shopping-bag"></i>
                    Continuer les Achats
                </a>
                <a href="orders.php" 
                   class="btn btn-secondary">
                    <i class="fas fa-list"></i>
                    Mes Commandes
                </a>
                <button onclick="window.print()" 
                        class="btn btn-secondary">
                    <i class="fas fa-print"></i>
                    Imprimer
                </button>
            </div>

            <!-- Store Features -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6 feature-grid no-print">
                <div class="feature-card text-center">
                    <div class="text-blue-500 text-3xl mb-4">
                        <i class="fas fa-shipping-fast floating-icon"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 mb-2">Livraison Rapide</h4>
                    <p class="text-gray-600">3-5 jours au Maroc</p>
                </div>
                <div class="feature-card text-center">
                    <div class="text-green-500 text-3xl mb-4">
                        <i class="fas fa-shield-alt floating-icon"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 mb-2">Paiement Sécurisé</h4>
                    <p class="text-gray-600">100% sécurisé</p>
                </div>
                <div class="feature-card text-center">
                    <div class="text-purple-500 text-3xl mb-4">
                        <i class="fas fa-undo floating-icon"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 mb-2">Retours Faciles</h4>
                    <p class="text-gray-600">7 jours pour changer d'avis</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced Confetti Animation
        function createConfetti() {
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57', '#ff9ff3', '#54a0ff', '#5f27cd'];
            const container = document.getElementById('confetti-container');
            const confettiCount = window.innerWidth < 768 ? 40 : 80;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = Math.random() * 15 + 5 + 'px';
                confetti.style.height = Math.random() * 15 + 5 + 'px';
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
                container.appendChild(confetti);
            }
        }

        // Initialize page animations
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
            
            // Add celebration effects
            setTimeout(() => {
                document.querySelector('h1').classList.add('pulse');
            }, 1000);
            
            // Add shimmer effect to order number
            const orderNumber = document.querySelector('.font-mono');
            if (orderNumber) {
                orderNumber.classList.add('shimmer');
            }
            
            // Add staggered animations to feature cards
            document.querySelectorAll('.feature-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Enhanced mobile touch interactions
        document.addEventListener('touchstart', function(e) {
            // Add touch feedback for buttons
            if (e.target.closest('.btn')) {
                e.target.closest('.btn').style.transform = 'scale(0.98)';
            }
        });

        document.addEventListener('touchend', function(e) {
            if (e.target.closest('.btn')) {
                e.target.closest('.btn').style.transform = '';
            }
        });

        // Handle orientation changes
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                window.scrollTo(0, 0);
            }, 100);
        });
    </script>
</body>
</html>