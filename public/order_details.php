<?php
session_start();
require_once '../config/db.php';
require_once '../controllers/OrderController.php';

header('Content-Type: application/json');

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]);
    exit;
}

$orderId = $_GET['order_id'];
$orderController = new OrderController();

// Get order details
$order = $orderController->getOrder($orderId);

if (!$order) {
    echo json_encode([
        'success' => false,
        'message' => 'Order not found'
    ]);
    exit;
}

// Get order items
$orderItems = $orderController->getOrderItems($orderId);

// Prepare response
$response = [
    'success' => true,
    'order' => [
        'id' => $order['id'],
        'order_code' => $order['order_code'],
        'customer_name' => $order['customer_name'],
        'customer_email' => $order['customer_email'],
        'customer_phone' => $order['customer_phone'],
        'customer_address' => $order['customer_address'],
        'customer_city' => $order['customer_city'],
        'customer_state' => $order['customer_state'],
        'customer_zipcode' => $order['customer_zipcode'],
        'customer_country' => $order['customer_country'],
        'status' => $order['status'],
        'created_at' => $order['created_at'],
        'items' => $orderItems
    ]
];

echo json_encode($response);
?>