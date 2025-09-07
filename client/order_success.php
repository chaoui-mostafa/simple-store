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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande Réussie - Monster Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --error-color: #ef4444;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .success-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 900px;
            margin: 2rem auto;
            padding: 1.5rem;
            overflow: hidden;
            position: relative;
        }
        
        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #8b5cf6, #ec4899);
            z-index: 10;
        }
        
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
        
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 5;
            stroke: #fff;
            stroke-miterlimit: 10;
            box-shadow: 0 0 25px rgba(79, 70, 229, 0.4);
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
            100% { box-shadow: inset 0px 0px 0px 40px var(--primary-color); }
        }
        
        .order-item {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .order-item:hover {
            border-left-color: var(--primary-color);
            transform: translateX(5px);
        }
        
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
            50% { transform: translateY(-10px); }
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
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .success-card {
                padding: 1.25rem;
                margin: 1rem auto;
                border-radius: 16px;
            }
            
            .checkmark {
                width: 70px;
                height: 70px;
            }
            
            .grid-cols-2 {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
            
            .flex-row {
                flex-direction: column;
            }
            
            .text-3xl {
                font-size: 1.75rem;
            }
            
            .text-2xl {
                font-size: 1.4rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons a, .action-buttons button {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 0.75rem;
            }
            
            .success-card {
                padding: 1rem;
                border-radius: 14px;
            }
            
            .text-lg {
                font-size: 1rem;
            }
            
            .text-xl {
                font-size: 1.2rem;
            }
            
            .social-sharing {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .social-sharing a {
                margin: 0.25rem;
            }
        }
        
        /* Badge pour le statut de la commande */
        .status-badge {
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .status-confirmed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        /* Animation pour les nouveaux éléments */
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
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        
        /* Progress tracker */
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
            height: 2px;
            background: #e5e7eb;
            transform: translateY(-50%);
            z-index: 1;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .step-active .step-icon {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .step-text {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .step-active .step-text {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Mobile progress tracker */
        @media (max-width: 640px) {
            .progress-tracker {
                flex-direction: column;
                gap: 1rem;
                padding: 0;
            }
            
            .progress-tracker::before {
                display: none;
            }
            
            .progress-step {
                flex-direction: row;
                width: 100%;
                text-align: left;
                gap: 0.75rem;
            }
            
            .step-text {
                font-size: 0.875rem;
            }
        }
        
        /* Feature cards */
        .feature-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #f3f4f6;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Print styles */
        @media print {
            body {
                background: white !important;
                padding: 0;
            }
            
            .success-card {
                box-shadow: none;
                border: none;
                max-width: 100%;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .action-buttons, .social-sharing, .progress-tracker {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Confetti Animation -->
    <div id="confetti-container"></div>
    
    <div class="success-card animate-slide-in-up">
        <div class="text-center">
            <!-- Animated Checkmark -->
            <div class="flex justify-center mb-6">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                    <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-900 mb-3">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                Commande Réussie !
            </h1>
            
            <p class="text-gray-600 mb-6 text-lg">
                Merci, <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($customerName); ?></span> ! 
                Votre commande a été confirmée.
            </p>

            <!-- Progress Tracker -->
            <div class="progress-tracker mb-8">
                <div class="progress-step step-active">
                    <div class="step-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="step-text">Commande passée</div>
                </div>
                <div class="progress-step">
                    <div class="step-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="step-text">En traitement</div>
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

            <!-- Order Summary -->
            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-4 md:p-6 rounded-xl mb-6 text-left border border-indigo-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-receipt mr-2 text-indigo-600"></i>
                    Récapitulatif de la Commande
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="animate-slide-in-up delay-100">
                        <p class="text-sm text-gray-600 mb-1 flex items-center">
                            <i class="fas fa-hashtag mr-2 text-indigo-500"></i>Numéro de Commande
                        </p>
                        <p class="font-mono font-bold text-indigo-700"><?php echo htmlspecialchars($orderNumber); ?></p>
                    </div>
                    <div class="animate-slide-in-up delay-200">
                        <p class="text-sm text-gray-600 mb-1 flex items-center">
                            <i class="fas fa-calendar-day mr-2 text-indigo-500"></i>Date de Commande
                        </p>
                        <p class="font-semibold text-gray-800"><?php echo date('d/m/Y à H:i', strtotime($orderDate)); ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="animate-slide-in-up delay-300">
                        <p class="text-sm text-gray-600 mb-1 flex items-center">
                            <i class="fas fa-envelope mr-2 text-indigo-500"></i>Adresse de Livraison
                        </p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($address); ?></p>
                    </div>
                    <div class="animate-slide-in-up delay-400">
                        <p class="text-sm text-gray-600 mb-1 flex items-center">
                            <i class="fas fa-phone mr-2 text-indigo-500"></i>Numéro de Téléphone
                        </p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($customerPhone); ?></p>
                    </div>
                </div>

                <!-- Additional Customer Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <?php if (!empty($order['customer_city'])): ?>
                    <div>
                        <p class="text-sm text-gray-600 mb-1 flex items-center">
                            <i class="fas fa-city mr-2 text-indigo-500"></i>Ville
                        </p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['customer_city']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($order['customer_country'])): ?>
                    <div>
                        <p class="text-sm text-gray-600 mb-1 flex items-center">
                            <i class="fas fa-flag mr-2 text-indigo-500"></i>Pays
                        </p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['customer_country']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Statut de la commande -->
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-1 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-indigo-500"></i>Statut de la Commande
                    </p>
                    <span class="status-badge status-confirmed">
                        <i class="fas fa-check-circle mr-1"></i> Confirmée
                    </span>
                </div>

                <!-- Order Items -->
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2 flex items-center">
                        <i class="fas fa-box-open mr-2 text-indigo-500"></i>Articles Commandés (1)
                    </p>
                    <div class="space-y-2">
                        <div class="order-item bg-white p-3 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($order['product_name']); ?></span>
                                <span class="text-indigo-600 font-semibold"><?php echo number_format($order['product_price'], 2); ?> DH</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-500">
                                <span><i class="fas fa-layer-group mr-1"></i>Qté: <?php echo $order['quantity']; ?></span>
                                <span><i class="fas fa-calculator mr-1"></i>Total: <?php echo number_format($order['product_price'] * $order['quantity'], 2); ?> DH</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="border-t border-indigo-200 pt-4">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-800">Montant Total</span>
                        <span class="text-2xl font-bold text-indigo-700"><?php echo number_format($orderTotal, 2); ?> DH</span>
                    </div>
                </div>

                <!-- Customer Notes -->
                <?php if (!empty($order['customer_notes'])): ?>
                <div class="mt-4 pt-4 border-t border-indigo-200">
                    <p class="text-sm text-gray-600 mb-1 flex items-center">
                        <i class="fas fa-sticky-note mr-2 text-indigo-500"></i>Vos Notes
                    </p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['customer_notes']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Next Steps -->
            <div class="bg-blue-50 p-4 rounded-xl mb-6 border border-blue-200">
                <h3 class="font-semibold text-blue-800 mb-2 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Prochaines Étapes
                </h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li class="flex items-center"><i class="fas fa-envelope-open-text mr-2"></i> Vous recevrez un email de confirmation sous peu</li>
                    <li class="flex items-center"><i class="fas fa-cogs mr-2"></i> Notre équipe traitera votre commande dans les 24 heures</li>
                    <li class="flex items-center"><i class="fas fa-truck mr-2"></i> Vous recevrez des mises à jour de livraison par email</li>
                    <li class="flex items-center"><i class="fas fa-calendar-check mr-2"></i> Livraison prévue : 3-5 jours ouvrables</li>
                </ul>
            </div>

            <!-- Support Information -->
            <div class="bg-green-50 p-4 rounded-xl mb-6 border border-green-200">
                <h3 class="font-semibold text-green-800 mb-3 flex items-center">
                    <i class="fas fa-headset mr-2"></i>
                    Besoin d'Aide ?
                </h3>
                <p class="text-sm text-green-700 mb-3">
                    Notre équipe de support client est là pour vous aider.
                </p>
                <div class="flex flex-col sm:flex-row gap-2">
                    <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $whatsappNumber); ?>" 
                       target="_blank" 
                       class="bg-green-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-700 transition-colors text-center flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2 text-lg"></i>
                        Support WhatsApp
                    </a>
                    <a href="mailto:support@monsterstore.com" 
                       class="bg-gray-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-gray-700 transition-colors text-center flex items-center justify-center">
                        <i class="fas fa-envelope mr-2"></i>
                        Email Support
                    </a>
                    <a href="tel:+212724893110" 
                       class="bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors text-center flex items-center justify-center">
                        <i class="fas fa-phone mr-2"></i>
                        Appeler
                    </a>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 action-buttons no-print">
                <a href="index.php" 
                   class="bg-indigo-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-indigo-700 transition-colors text-center flex items-center justify-center pulse">
                    <i class="fas fa-shopping-bag mr-2"></i>
                    Continuer les Achats
                </a>
                <a href="orders.php" 
                   class="bg-white text-indigo-600 border border-indigo-200 py-3 px-6 rounded-lg font-semibold hover:bg-indigo-50 transition-colors text-center flex items-center justify-center">
                    <i class="fas fa-list mr-2"></i>
                    Voir les Commandes
                </a>
                <button onclick="window.print()" 
                        class="bg-gray-100 text-gray-700 border border-gray-200 py-3 px-6 rounded-lg font-semibold hover:bg-gray-200 transition-colors text-center flex items-center justify-center">
                    <i class="fas fa-print mr-2"></i>
                    Imprimer le Reçu
                </button>
            </div>

            <!-- Social Sharing -->
            <div class="mt-6 pt-6 border-t border-gray-200 no-print">
                <p class="text-sm text-gray-600 mb-3 flex items-center justify-center">
                    <i class="fas fa-share-alt mr-2 text-indigo-500"></i>Partagez votre achat :
                </p>
                <div class="flex justify-center space-x-3 social-sharing">
                    <a href="#" class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center hover:bg-blue-700 transition-colors">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-blue-400 text-white rounded-full flex items-center justify-center hover:bg-blue-500 transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-pink-600 text-white rounded-full flex items-center justify-center hover:bg-pink-700 transition-colors">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center hover:bg-green-600 transition-colors">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors">
                        <i class="fab fa-pinterest"></i>
                    </a>
                </div>
            </div>

            <!-- Store Features -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 no-print">
                <div class="feature-card text-center">
                    <div class="text-blue-500 text-2xl mb-2">
                        <i class="fas fa-shipping-fast floating-icon"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Livraison Rapide</h4>
                    <p class="text-sm text-gray-600">Livraison en 3-5 jours partout au Maroc</p>
                </div>
                <div class="feature-card text-center">
                    <div class="text-green-500 text-2xl mb-2">
                        <i class="fas fa-shield-alt floating-icon"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Paiement Sécurisé</h4>
                    <p class="text-sm text-gray-600">Transactions 100% sécurisées</p>
                </div>
                <div class="feature-card text-center">
                    <div class="text-purple-500 text-2xl mb-2">
                        <i class="fas fa-undo floating-icon"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Retours Faciles</h4>
                    <p class="text-sm text-gray-600">Retours acceptés sous 7 jours</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Create confetti animation
        function createConfetti() {
            const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ff9900', '#ff66cc'];
            const container = document.getElementById('confetti-container');
            
            for (let i = 0; i < 60; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = Math.random() * 12 + 5 + 'px';
                confetti.style.height = Math.random() * 12 + 5 + 'px';
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                container.appendChild(confetti);
            }
        }

        // Create confetti on page load
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
            
            // Add celebration effect
            setTimeout(() => {
                document.querySelector('h1').classList.add('pulse');
            }, 1000);
            
            // Add shimmer effect to order number
            const orderNumber = document.querySelector('.font-mono');
            if (orderNumber) {
                orderNumber.classList.add('shimmer');
            }
        });

        // Print functionality
        function printReceipt() {
            window.print();
        }

        // Share functionality
        function shareOrder() {
            if (navigator.share) {
                navigator.share({
                    title: 'Ma Commande Monster Store',
                    text: 'Je viens de faire un achat sur Monster Store !',
                    url: window.location.href
                });
            } else {
                alert('API de partage non supportée dans votre navigateur');
            }
        }
        
        // Add floating animation to feature icons
        document.querySelectorAll('.floating-icon').forEach((icon, index) => {
            icon.style.animationDelay = `${index * 0.3}s`;
        });
    </script>
</body>
</html>