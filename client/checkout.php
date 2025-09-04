<?php
session_start();
require_once '../config/db.php';
require_once '../controllers/CartController.php';
require_once '../controllers/OrderController.php';
require_once '../controllers/ProductController.php';

// Créer la connexion à la base de données
$db = new Database();
$pdo = $db->connect();

// Créer les contrôleurs
$cartController = new CartController();
$orderController = new OrderController();
$productController = new ProductController($pdo);

// Obtenir l'ID de session et les articles du panier
$session_id = session_id();
$cartItems = $cartController->getCartItems();
$cartTotal = $cartController->getCartTotal();
$cartCount = $cartController->getCartCount();

// Rediriger si le panier est vide
if (empty($cartItems)) {
    header('Location: cart.php');
    exit();
}

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Générer CAPTCHA s'il n'existe pas
if (empty($_SESSION['captcha'])) {
    $_SESSION['captcha'] = generateCaptcha();
}

// Gérer la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Token CSRF invalide');
    }

    // Valider CAPTCHA
    if (!isset($_POST['captcha']) || $_POST['captcha'] !== $_SESSION['captcha']) {
        $_SESSION['error'] = "Code CAPTCHA incorrect. Veuillez réessayer.";
        // Sauvegarder les données du formulaire pour les réafficher
        $_SESSION['checkout_form_data'] = $_POST;
        header('Location: checkout.php');
        exit();
    }

    // Sauvegarder les données du formulaire pour les réafficher
    $_SESSION['checkout_form_data'] = [
        'customer_name' => $_POST['customer_name'],
        'customer_email' => $_POST['customer_email'],
        'customer_phone' => $_POST['customer_phone'],
        'customer_whatsapp' => $_POST['customer_whatsapp'] ?? '',
        'customer_city' => $_POST['customer_city'],
        'customer_state' => $_POST['customer_state'],
        'customer_zipcode' => $_POST['customer_zipcode'],
        'customer_country' => $_POST['customer_country'],
        'customer_address' => $_POST['customer_address'],
        'customer_notes' => $_POST['customer_notes'] ?? ''
    ];

    // Traiter chaque article du panier comme une commande séparée
    $successCount = 0;
    $errorMessages = [];

    foreach ($cartItems as $item) {
        $orderData = [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'customer_name' => htmlspecialchars($_POST['customer_name'], ENT_QUOTES, 'UTF-8'),
            'customer_email' => filter_var($_POST['customer_email'], FILTER_SANITIZE_EMAIL),
            'customer_phone' => htmlspecialchars($_POST['customer_phone'], ENT_QUOTES, 'UTF-8'),
            'customer_whatsapp' => isset($_POST['customer_whatsapp']) ? htmlspecialchars($_POST['customer_whatsapp'], ENT_QUOTES, 'UTF-8') : '',
            'customer_city' => htmlspecialchars($_POST['customer_city'], ENT_QUOTES, 'UTF-8'),
            'customer_state' => htmlspecialchars($_POST['customer_state'], ENT_QUOTES, 'UTF-8'),
            'customer_zipcode' => htmlspecialchars($_POST['customer_zipcode'], ENT_QUOTES, 'UTF-8'),
            'customer_country' => htmlspecialchars($_POST['customer_country'], ENT_QUOTES, 'UTF-8'),
            'customer_address' => htmlspecialchars($_POST['customer_address'], ENT_QUOTES, 'UTF-8'),
            'customer_notes' => isset($_POST['customer_notes']) ? htmlspecialchars($_POST['customer_notes'], ENT_QUOTES, 'UTF-8') : ''
        ];

        $result = $orderController->createOrder($orderData);

        if ($result['success']) {
            $successCount++;
        } else {
            $errorMessages[] = "Produit '{$item['name']}': " . $result['message'];
        }
    }

    // Vider le panier si toutes les commandes ont réussi
    if ($successCount === count($cartItems)) {
        $cartController->clearCart();
        unset($_SESSION['checkout_form_data']); // Effacer les données sauvegardées
        unset($_SESSION['captcha']); // Effacer CAPTCHA
        $_SESSION['order_success'] = true;
        $_SESSION['order_count'] = $successCount;
        header('Location: order_success.php');
        exit();
    } else {
        // Certaines commandes ont échoué
        $_SESSION['error'] = implode('<br>', $errorMessages);
        header('Location: checkout.php');
        exit();
    }
}

// Afficher les messages de succès/erreur
$successMessage = '';
$errorMessage = '';
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Obtenir les données sauvegardées du formulaire si disponibles
$formData = $_SESSION['checkout_form_data'] ?? [
    'customer_name' => '',
    'customer_email' => '',
    'customer_phone' => '',
    'customer_whatsapp' => '',
    'customer_city' => '',
    'customer_state' => '',
    'customer_zipcode' => '',
    'customer_country' => '',
    'customer_address' => '',
    'customer_notes' => ''
];

// Fonction de génération CAPTCHA sécurisée
function generateCaptcha($length = 6)
{
    // Jeu de caractères : lettres majuscules, minuscules, chiffres, et quelques symboles
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789qwertyuiopasdfghjklzxcvbnm#@&';
    $captcha = '';

    $maxIndex = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {
        // Utilisation de random_int pour plus de sécurité
        $captcha .= $chars[random_int(0, $maxIndex)];
    }

    return $captcha;
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Monster Store</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css">

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

        /* Mobile-first checkout layout */
        .checkout-container {
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .form-input.error {
            border-color: var(--error-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
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
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }

        .notification {
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

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: var(--success-color);
        }

        .notification.error {
            background: var(--error-color);
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

        .error-message {
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .captcha-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        .captcha-code {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            padding: 10px 15px;
            background: linear-gradient(135deg, var(--input-bg) 0%, var(--gray-border) 100%);
            border-radius: 8px;
            letter-spacing: 5px;
            user-select: none;
            border: 2px solid var(--gray-border);
            min-width: 150px;
            text-align: center;
            color: var(--text-primary);
        }

        .captcha-refresh {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .captcha-refresh:hover {
            background: var(--primary-hover);
            transform: rotate(45deg);
        }

        .input-hint {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
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
            .mobile-sticky-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: var(--bg-primary);
                padding: 1rem;
                box-shadow: 0 -4px 20px var(--shadow-color);
                z-index: 90;
            }

            .main-content {
                padding-bottom: 120px;
            }

            .captcha-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .card {
                padding: 1rem;
            }

            .form-input {
                padding: 0.75rem;
            }
        }

        /* Desktop styles */
        @media (min-width: 1024px) {
            .checkout-container {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 2rem;
            }

            .order-summary {
                position: sticky;
                top: 6rem;
                height: fit-content;
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
            padding-bottom: 80px;
            /* Space for bottom nav */
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

        /* Animation for elements */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Badge for stock */
        .stock-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
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

        /* Shake animation for errors */
        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

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

        /* Fix for paragraph display in white mode */
        p, span, label, h1, h2, h3, h4, h5, h6 {
            color: var(--text-primary);
        }
    </style>
</head>

<body class="antialiased">
    <!-- Notification Messages -->
    <?php if ($successMessage): ?>
        <div class="notification success" id="success-notification">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($successMessage); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="notification error" id="error-notification">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $errorMessage; ?></span>
        </div>
    <?php endif; ?>

<!-- Header -->
<header class="sticky-header">
    <div class="container">
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center">
                <a href="index.php" class="mr-3">
                    <div class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center overflow-hidden">
                        <img src="../assets/images/logo/logo.jpg" 
                             alt="Logo" 
                             class="w-full h-full object-cover" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <span class="text-white font-bold text-xl hidden">M</span>
                    </div>
                </a>
                <h1 class="text-xl font-bold dark:text">Finaliser la Commande</h1>
            </div>

            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="theme-toggle">
                    <i id="theme-icon" class="fas fa-moon"></i>
                </button>
                
                <a href="cart.php" 
                   class="text-blue-600 hover:text-blue-800 flex items-center 
                          text-sm sm:text-base md:text-lg 
                          dark:text-blue-400 dark:hover:text-blue-300 mb-3">
                    <i class="fas fa-arrow-left mr-2 text-xs sm:text-sm"></i> 
                    Retour au panier
                </a>
            </div>
        </div>
    </div>
</header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="checkout-container">
                <!-- Formulaire d'information client -->
                <div class="card animate-slide-in-up">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 dark:bg-blue-900">
                            <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <h2 class="text-xl font-bold dark:text">Informations Client</h2>
                    </div>

                    <form action="checkout.php" method="POST" id="checkoutForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label dark:text" for="customer_name">
                                    <i class="fas fa-user"></i> Nom Complet *
                                </label>
                                <input type="text" id="customer_name" name="customer_name" class="form-input" required
                                    value="<?php echo htmlspecialchars($formData['customer_name']); ?>">
                                <div class="input-hint">
                                    <i class="fas fa-info-circle"></i> Entrez votre nom complet
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label dark:text" for="customer_email">
                                    <i class="fas fa-envelope"></i> Adresse Email *
                                </label>
                                <input type="email" id="customer_email" name="customer_email" class="form-input" required
                                    value="<?php echo htmlspecialchars($formData['customer_email']); ?>">
                                <div class="input-hint">
                                    <i class="fas fa-info-circle"></i> Nous enverrons la confirmation ici
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label dark:text" for="customer_phone">
                                    <i class="fas fa-phone"></i> Numéro de Téléphone *
                                </label>
                                <input type="tel" id="customer_phone" name="customer_phone" class="form-input" required placeholder="06 123 45 67 89"
                                    value="<?php echo htmlspecialchars($formData['customer_phone']); ?>">
                                <div class="input-hint">
                                    <i class="fas fa-info-circle"></i> Pour les mises à jour de livraison
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label dark:text" for="customer_whatsapp">
                                    <i class="fab fa-whatsapp"></i> Numéro WhatsApp (Optionnel)
                                </label>
                                <input type="tel" id="customer_whatsapp" name="customer_whatsapp" class="form-input" placeholder="06 123 45 67 89"
                                    value="<?php echo htmlspecialchars($formData['customer_whatsapp']); ?>">
                                <div class="input-hint text-green-600">
                                    <i class="fab fa-whatsapp"></i> Recevez les mises à jour sur WhatsApp
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="form-group">
                                <label class="form-label dark:text" for="customer_city">
                                    <i class="fas fa-city"></i> Ville *
                                </label>
                                <input type="text" id="customer_city" name="customer_city" class="form-input" required placeholder="Fes"
                                    value="<?php echo htmlspecialchars($formData['customer_city']); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label dark:text" for="customer_state">
                                    <i class="fas fa-map-marked"></i> Région/Province *
                                </label>
                                <input type="text" id="customer_state" name="customer_state" class="form-input" required placeholder="Fès-Meknès"
                                    value="<?php echo htmlspecialchars($formData['customer_state']); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label dark:text" for="customer_zipcode">
                                    <i class="fas fa-mail-bulk"></i> Code Postal (Optionnel)
                                </label>
                                <input type="text" id="customer_zipcode" name="customer_zipcode" class="form-input" placeholder="30000"
                                    value="<?php echo htmlspecialchars($formData['customer_zipcode']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label dark:text" for="customer_country">
                                <i class="fas fa-globe"></i> Pays *
                            </label>
                            <select id="customer_country" name="customer_country" class="form-input" required>
                                <option value=""> Sélectionnez un pays</option>

                                <!-- 🌍 Afrique / إفريقيا -->
                                <optgroup label="Afrique / إفريقيا">
                                    <option value="Maroc" <?= ($formData['customer_country'] ?? '') === 'Maroc' ? 'selected' : '' ?>>
                                        🇲🇦 Maroc
                                    </option>
                                    <option value="Algérie" <?= ($formData['customer_country'] ?? '') === 'Algérie' ? 'selected' : '' ?>>
                                        🇩🇿 Algérie
                                    </option>
                                    <option value="Tunisie" <?= ($formData['customer_country'] ?? '') === 'Tunisie' ? 'selected' : '' ?>>
                                        🇹🇳 Tunisie
                                    </option>
                                    <option value="Libye" <?= ($formData['customer_country'] ?? '') === 'Libye' ? 'selected' : '' ?>>
                                        🇱🇾 Libye
                                    </option>
                                    <option value="Égypte" <?= ($formData['customer_country'] ?? '') === 'Égypte' ? 'selected' : '' ?>>
                                        🇪🇬 Égypte
                                    </option>
                                    <option value="Sénégal" <?= ($formData['customer_country'] ?? '') === 'Sénégal' ? 'selected' : '' ?>>
                                        🇸🇳 Sénégal
                                    </option>
                                    <option value="Côte d'Ivoire" <?= ($formData['customer_country'] ?? '') === "Côte d'Ivoire" ? 'selected' : '' ?>>
                                        🇨🇮 Côte d'Ivoire
                                    </option>
                                    <option value="Mali" <?= ($formData['customer_country'] ?? '') === 'Mali' ? 'selected' : '' ?>>
                                        🇲🇱 Mali
                                    </option>
                                    <option value="Burkina Faso" <?= ($formData['customer_country'] ?? '') === 'Burkina Faso' ? 'selected' : '' ?>>
                                        🇧🇫 Burkina Faso
                                    </option>
                                    <option value="Niger" <?= ($formData['customer_country'] ?? '') === 'Niger' ? 'selected' : '' ?>>
                                        🇳🇪 Niger
                                    </option>
                                    <option value="Guinée" <?= ($formData['customer_country'] ?? '') === 'Guinée' ? 'selected' : '' ?>>
                                        🇬🇳 Guinée
                                    </option>
                                </optgroup>

                                <!-- 🌍 Moyen-Orient / الشرق الأوسط -->
                                <optgroup label=" Moyen-Orient / الشرق الأوسط">
                                    <option value="Saudi Arabie Saoudite" <?= ($formData['customer_country'] ?? '') === 'Saudi Arabie Saoudite' ? 'selected' : '' ?>>
                                        🇸🇦 Arabie Saoudite
                                    </option>
                                    <option value="Émirats Arabes Unis" <?= ($formData['customer_country'] ?? '') === 'Émirats Arabes Unis' ? 'selected' : '' ?>>
                                        🇦🇪 Émirats Arabes Unis
                                    </option>
                                    <option value="Koweït" <?= ($formData['customer_country'] ?? '') === 'Koweït' ? 'selected' : '' ?>>
                                        🇰🇼 Koweït
                                    </option>
                                    <option value="Qatar" <?= ($formData['customer_country'] ?? '') === 'Qatar' ? 'selected' : '' ?>>
                                        🇶🇦 Qatar
                                    </option>
                                    <option value="Bahreïn" <?= ($formData['customer_country'] ?? '') === 'Bahreïn' ? 'selected' : '' ?>>
                                        🇧🇭 Bahreïn
                                    </option>
                                    <option value="Oman" <?= ($formData['customer_country'] ?? '') === 'Oman' ? 'selected' : '' ?>>
                                        🇴🇲 Oman
                                    </option>
                                    <option value="Jordanie" <?= ($formData['customer_country'] ?? '') === 'Jordanie' ? 'selected' : '' ?>>
                                        🇯🇴 Jordanie
                                    </option>
                                    <option value="Liban" <?= ($formData['customer_country'] ?? '') === 'Liban' ? 'selected' : '' ?>>
                                        🇱🇧 Liban
                                    </option>
                                    <option value="Syrie" <?= ($formData['customer_country'] ?? '') === 'Syrie' ? 'selected' : '' ?>>
                                        🇸🇾 Syrie
                                    </option>
                                    <option value="Irak" <?= ($formData['customer_country'] ?? '') === 'Irak' ? 'selected' : '' ?>>
                                        🇮🇶 Irak
                                    </option>
                                </optgroup>

                                <!-- 🌍 Europe / آخرين -->
                                <optgroup label=" Europe / آخرين">
                                    <option value="France" <?= ($formData['customer_country'] ?? '') === 'France' ? 'selected' : '' ?>>
                                        🇫🇷 France
                                    </option>
                                    <option value="Belgique" <?= ($formData['customer_country'] ?? '') === 'Belgique' ? 'selected' : '' ?>>
                                        🇧🇪 Belgique
                                    </option>
                                    <option value="Canada" <?= ($formData['customer_country'] ?? '') === 'Canada' ? 'selected' : '' ?>>
                                        🇨🇦 Canada
                                    </option>
                                    <option value="Suisse" <?= ($formData['customer_country'] ?? '') === 'Suisse' ? 'selected' : '' ?>>
                                        🇨🇭 Suisse
                                    </option>
                                    <option value="Allemagne" <?= ($formData['customer_country'] ?? '') === 'Allemagne' ? 'selected' : '' ?>>
                                        🇩🇪 Allemagne
                                    </option>
                                    <option value="Royaume-Uni" <?= ($formData['customer_country'] ?? '') === 'Royaume-Uni' ? 'selected' : '' ?>>
                                        🇬🇧 Royaume-Uni
                                    </option>
                                    <option value="Espagne" <?= ($formData['customer_country'] ?? '') === 'Espagne' ? 'selected' : '' ?>>
                                        🇪🇸 Espagne
                                    </option>
                                    <option value="Italie" <?= ($formData['customer_country'] ?? '') === 'Italie' ? 'selected' : '' ?>>
                                        🇮🇹 Italie
                                    </option>
                                    <option value="Pays-Bas" <?= ($formData['customer_country'] ?? '') === 'Pays-Bas' ? 'selected' : '' ?>>
                                        🇳🇱 Pays-Bas
                                    </option>
                                    <option value="Autre" <?= ($formData['customer_country'] ?? '') === 'Autre' ? 'selected' : '' ?>>
                                        <i class="fas fa-info-circle"></i> Autre
                                    </option>
                                </optgroup>

                                <!-- 🌍 Asie /  -->
                                <optgroup label=" Asie /  ">
                                    <option value="Inde" <?= ($formData['customer_country'] ?? '') === 'Inde' ? 'selected' : '' ?>>
                                        🇮🇳 Inde
                                    </option>
                                    <option value="Pakistan" <?= ($formData['customer_country'] ?? '') === 'Pakistan' ? 'selected' : '' ?>>
                                        🇵🇰 Pakistan
                                    </option>
                                    <option value="Bangladesh" <?= ($formData['customer_country'] ?? '') === 'Bangladesh' ? 'selected' : '' ?>>
                                        🇧🇩 Bangladesh
                                    </option>
                                    <option value="Sri Lanka" <?= ($formData['customer_country'] ?? '') === 'Sri Lanka' ? 'selected' : '' ?>>
                                        🇱🇰 Sri Lanka
                                    </option>
                                    <option value="Chine" <?= ($formData['customer_country'] ?? '') === 'Chine' ? 'selected' : '' ?>>
                                        🇨🇳 Chine
                                    </option>
                                    <option value="Japon" <?= ($formData['customer_country'] ?? '') === 'Japon' ? 'selected' : '' ?>>
                                        🇯🇵 Japon
                                    </option>
                                    <option value="Corée du Sud" <?= ($formData['customer_country'] ?? '') === 'Corée du Sud' ? 'selected' : '' ?>>
                                        🇰🇷 Corée du Sud
                                    </option>
                                    <option value="Indonésie" <?= ($formData['customer_country'] ?? '') === 'Indonésie' ? 'selected' : '' ?>>
                                        🇮🇩 Indonésie
                                    </option>
                                    <option value="Malaisie" <?= ($formData['customer_country'] ?? '') === 'Malaisie' ? 'selected' : '' ?>>
                                        🇲🇾 Malaisie
                                    </option>
                                    <option value="Autre" <?= ($formData['customer_country'] ?? '') === 'Autre' ? 'selected' : '' ?>>
                                        <i class="fas fa-info-circle"></i> Autre
                                    </option>
                                </optgroup>
                            </select>
                        </div>


                        <div class="form-group">
                            <label class="form-label dark:text" for="customer_address">
                                <i class="fas fa-home"></i> Adresse de Livraison *
                            </label>
                            <textarea id="customer_address" name="customer_address" rows="3" class="form-input" required placeholder="Nom de la rue, numéro d'appartement, code postal, ville"><?php echo htmlspecialchars($formData['customer_address']); ?></textarea>
                            <div class="input-hint">
                                <i class="fas fa-info-circle"></i> Incluez le numéro d'appartement si applicable
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label dark:text" for="customer_notes">
                                <i class="fas fa-sticky-note"></i> Notes de Commande (Optionnel)
                            </label>
                            <textarea id="customer_notes" name="customer_notes" rows="2" class="form-input" placeholder="Instructions spéciales pour la livraison "><?php echo htmlspecialchars($formData['customer_notes']); ?></textarea>
                        </div>

                        <!-- Section CAPTCHA -->
                        <div class="form-group">
                            <label class="form-label dark:text">
                                <i class="fas fa-shield-alt"></i> Vérification de Sécurité *
                            </label>
                            <div class="captcha-container">
                                <div class="captcha-code" id="captchaDisplay">
                                    <?php echo $_SESSION['captcha']; ?>
                                </div>
                                <button type="button" class="captcha-refresh" onclick="refreshCaptcha()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <input type="text" name="captcha" class="form-input" placeholder="Entrez le code affiché ci-dessus" required>
                            <div class="input-hint">
                                <i class="fas fa-shield-alt"></i> Cela aide à prévenir les soumissions automatisées
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Récapitulatif de la commande -->
                <div class="order-summary card animate-slide-in-up" style="animation-delay: 0.1s;">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3 dark:bg-green-900">
                            <i class="fas fa-receipt text-green-600 dark:text-green-400"></i>
                        </div>
                        <h2 class="text-xl font-bold dark:text">Récapitulatif</h2>
                    </div>

                    <div class="mb-6 max-h-80 overflow-y-auto">
                        <?php foreach ($cartItems as $item):
                            $stockClass = '';
                            if ($item['stock_quantity'] > 10) {
                                $stockClass = 'stock-high';
                            } elseif ($item['stock_quantity'] > 0) {
                                $stockClass = 'stock-medium';
                            } else {
                                $stockClass = 'stock-low';
                            }
                        ?>
                            <div class="order-item">
                                <div class="order-item-image-container">
                                    <img src="../assets/images/<?php echo htmlspecialchars($item['image']); ?>"
                                        alt="<?php echo htmlspecialchars($item['name']); ?>"
                                        class="order-item-image"
                                        onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIyNSIgdmlld0JveD0iMCAwIDMwMCAyMjUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjI1IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0xMTIuNSA4NC41QzExMi41IDc4LjAyODQgMTE3Ljc4MiA3Mi43NSAxMjQuMjUgNzIuNzVDMTMwLjcxOCA3Mi43NSAxMzYgNzguMDI4NCAxMzYgODQuNUMxMzYgOTAuOTcxNiAxMzAuNzE4IDk2LjI5IDEyNC4yNSA5Ni4yNUMxMTcuNzgyIDk2LjI1IDExMi41IDkwLjk3MTYgMTEyLjUgODQuNVoiIGZpbGw9IiNEOEQ4RDgiLz4KPHBhdGggZD0iTTE4NSA5NEgxNjMuNUMxNjEuMDEzIDk0IDE1OSA5Ni4wMTM0IDE1OSA5OC41VjEzN0MxNTkgMTM5LjQ4NyAxNjEuMDEzIDE0MS41IDE2My41IDE0MS41SDE4NUMxODcuNDg3IDE0MS41IDE5MCAxMzkuNDg3IDE5MCAxMzdWOTguNUMxOTAgOTYuMDEzNCAxODcuNDg3IDk0IDE4NSA5NFoiIGZpbGw9IiNEOEQ4RDgiLz4KPC9zdmc+Cg=='">
                                </div>
                                <div class="flex-grow">
                                    <p class="font-medium text-sm dark:text"><?php echo htmlspecialchars($item['name']); ?></p>
                                    <p class="text-gray-500 text-sm dark:text-gray-400">Qté: <?php echo $item['quantity']; ?></p>
                                    <span class="stock-badge <?php echo $stockClass; ?> text-xs">
                                        <?php echo $item['stock_quantity']; ?> en stock
                                    </span>
                                    <?php if ($item['stock_quantity'] < $item['quantity']): ?>
                                        <p class="text-red-500 text-xs flex items-center mt-1">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Seulement <?php echo $item['stock_quantity']; ?> disponible(s)
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <p class="font-semibold dark:text"><?php echo number_format($item['price'], 2); ?> DH </p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Sous-total (<?php echo $cartCount; ?> articles)</span>
                            <span class="font-medium dark:text"><?php echo number_format($cartTotal, 2); ?> DH </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Livraison</span>
                            <span class="font-medium dark:text">Gratuite</span>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between text-lg font-bold">
                            <span class="dark:text">Total</span>
                            <span class="text-blue-600 dark:text-blue-400"><?php echo number_format($cartTotal, 2); ?> DH </span>
                        </div>
                    </div>

                    <button type="submit" form="checkoutForm" class="btn btn-primary w-full" id="submitButton">
                        <i class="fas fa-lock mr-2"></i> Finaliser la Commande
                    </button>

                    <p class="text-xs text-gray-500 text-center mt-3 dark:text-gray-400">
                        <i class="fas fa-shield-alt mr-1"></i> Vos informations sont sécurisées et cryptées
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Desktop Footer -->
    <?php include '../assets/part/footer.php'  ?>
   

    <!-- Bottom Navigation (Mobile) -->
    <?php include '../assets/part/nav-mobil.php'  ?>

    <script>
        // Theme management
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcon(savedTheme);
            
            // Theme toggle functionality
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
            
            const successNotification = document.getElementById('success-notification');
            const errorNotification = document.getElementById('error-notification');

            if (successNotification) {
                successNotification.classList.add('show');
                setTimeout(() => {
                    successNotification.classList.remove('show');
                    setTimeout(() => successNotification.remove(), 300);
                }, 5000);
            }

            if (errorNotification) {
                errorNotification.classList.add('show');
                setTimeout(() => {
                    errorNotification.classList.remove('show');
                    setTimeout(() => errorNotification.remove(), 300);
                }, 5000);
            }

            const checkoutForm = document.getElementById('checkoutForm');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    const requiredFields = this.querySelectorAll('input[required], select[required], textarea[required]');

                    // Validation basique
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('error');

                            if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('error-message')) {
                                const errorMsg = document.createElement('p');
                                errorMsg.className = 'error-message';
                                errorMsg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Ce champ est requis';
                                field.parentNode.appendChild(errorMsg);
                            }

                            // Ajouter une animation de secousse
                            field.classList.add('shake');
                            setTimeout(() => field.classList.remove('shake'), 500);
                        } else {
                            field.classList.remove('error');
                            const errorMsg = field.parentNode.querySelector('.error-message');
                            if (errorMsg) errorMsg.remove();
                        }
                    });

                    // Validation email
                    const emailField = this.querySelector('input[type="email"]');
                    if (emailField && emailField.value) {
                        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailPattern.test(emailField.value)) {
                            isValid = false;
                            emailField.classList.add('error');

                            if (!emailField.nextElementSibling || !emailField.nextElementSibling.classList.contains('error-message')) {
                                const errorMsg = document.createElement('p');
                                errorMsg.className = 'error-message';
                                errorMsg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Veuillez entrer une adresse email valide';
                                emailField.parentNode.appendChild(errorMsg);
                            }

                            emailField.classList.add('shake');
                            setTimeout(() => emailField.classList.remove('shake'), 500);
                        } else {
                            emailField.classList.remove('error');
                            const errorMsg = emailField.parentNode.querySelector('.error-message');
                            if (errorMsg) errorMsg.remove();
                        }
                    }

                    if (!isValid) {
                        e.preventDefault();
                        // Défiler vers la première erreur
                        const firstError = this.querySelector('.error');
                        if (firstError) {
                            firstError.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                            firstError.focus();
                        }
                    }
                });
            }

            // Formatage automatique des numéros de téléphone
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');

                    // Formater selon la longueur
                    if (value.length > 10) {
                        value = value.substring(0, 10);
                    }

                    if (value.length > 6) {
                        value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4');
                    } else if (value.length > 4) {
                        value = value.replace(/(\d{2})(\d{2})(\d{2})/, '$1 $2 $3');
                    } else if (value.length > 2) {
                        value = value.replace(/(\d{2})(\d{2})/, '$1 $2');
                    }

                    e.target.value = value;
                });
            });
        });

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

        // Fonction de rafraîchissement CAPTCHA
        function refreshCaptcha() {
            const captchaDisplay = document.getElementById('captchaDisplay');
            const refreshBtn = document.querySelector('.captcha-refresh');

            // Animation de chargement
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            captchaDisplay.style.opacity = '0.5';

            fetch('refresh_captcha.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        captchaDisplay.textContent = data.captcha;
                        captchaDisplay.style.opacity = '1';
                        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                });
        }
    </script>
</body>

</html>