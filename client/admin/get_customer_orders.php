<?php
session_start();
require_once '../../config/db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if (!isset($_GET['phone'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Paramètre phone manquant']);
    exit();
}

$phone = $_GET['phone'];

try {
    $db = new Database();
    $pdo = $db->connect();
    
    // Récupérer les commandes du client avec les informations produit
    $sql = "SELECT o.*, p.price as product_price, p.name as product_name 
            FROM orders o 
            LEFT JOIN products p ON o.product_id = p.id 
            WHERE o.customer_phone = ? 
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$phone]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo json_encode(['success' => true, 'customer' => null, 'orders' => []]);
        exit();
    }

    // Récupérer les informations du client (basées sur la première commande)
    $customer = [
        'customer_name' => $orders[0]['customer_name'],
        'customer_phone' => $orders[0]['customer_phone'],
        'customer_whatsapp' => $orders[0]['customer_whatsapp'] ?? '',
        'customer_email' => $orders[0]['customer_email']
    ];

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'customer' => $customer, 'orders' => $orders]);
    
} catch (PDOException $e) {
    error_log("Error in get_customer_orders.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>