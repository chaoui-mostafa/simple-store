<?php
session_start();
require_once '../config/db.php';
require_once '../controllers/OrderController.php';
require_once '../vendor/autoload.php';

use TCPDF as TCPDF;

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    die('Order ID is required');
}

$orderId = $_GET['order_id'];
$orderController = new OrderController();
$order = $orderController->getOrder($orderId);

if (!$order) {
    die('Order not found');
}

$orderItems = $orderController->getOrderItems($orderId);
$total = 0;
foreach ($orderItems as $item) {
    $total += $item['product_price'] * $item['quantity'];
}

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'fr';

$translations = [
    'fr' => [
        'invoice' => 'FACTURE',
        'invoice_number' => 'Facture N°',
        'date' => 'Date',
        'time' => 'Heure',
        'bill_to' => 'Facturé à',
        'description' => 'Description',
        'quantity' => 'Quantité',
        'unit_price' => 'Prix unitaire',
        'total' => 'Total',
        'subtotal' => 'Sous-total',
        'tax' => 'Taxe',
        'thank_you' => 'Merci pour votre commande!',
        'contact_info' => 'Pour toute question concernant cette facture, veuillez nous contacter.',
        'phone' => 'Tél',
        'email' => 'Email',
        'website' => 'Site web',
        'order_total' => 'Total de la commande',
        'invoice_date' => 'Date de facturation',
        'order_date' => 'Date de commande',
        'nom_prenom' => 'Nom et Prénom',
        'telephone' => 'Téléphone',
        'adresse' => 'Adresse',
        'payment_terms' => 'Paiement à réception de facture.'
    ],
    'en' => [
        'invoice' => 'INVOICE',
        'invoice_number' => 'Invoice #',
        'date' => 'Date',
        'time' => 'Time',
        'bill_to' => 'Bill To',
        'description' => 'Description',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit Price',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'tax' => 'Tax',
        'thank_you' => 'Thank you for your order!',
        'contact_info' => 'If you have any questions about this invoice, please contact us.',
        'phone' => 'Phone',
        'email' => 'Email',
        'website' => 'Website',
        'order_total' => 'Order Total',
        'invoice_date' => 'Invoice Date',
        'order_date' => 'Order Date',
        'nom_prenom' => 'Full Name',
        'telephone' => 'Phone',
        'adresse' => 'Address',
        'payment_terms' => 'Payment due upon receipt.'
    ],
    'ar' => [
        'invoice' => 'فاتورة',
        'invoice_number' => 'رقم الفاتورة',
        'date' => 'التاريخ',
        'time' => 'الوقت',
        'bill_to' => 'المُرسل إليه',
        'description' => 'الوصف',
        'quantity' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'total' => 'المجموع',
        'subtotal' => 'المجموع الفرعي',
        'tax' => 'الضريبة',
        'thank_you' => 'شكراً لطلبك!',
        'contact_info' => 'إذا كان لديك أي أسئلة حول هذه الفاتورة، يرجى الاتصال بنا.',
        'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني',
        'website' => 'الموقع الإلكتروني',
        'order_total' => 'إجمالي الطلب',
        'invoice_date' => 'تاريخ الفاتورة',
        'order_date' => 'تاريخ الطلب',
        'nom_prenom' => 'الاسم الكامل',
        'telephone' => 'الهاتف',
        'adresse' => 'العنوان',
        'payment_terms' => ' '
    ]
];

$t = $translations[$lang];

// Create new PDF document with modern settings
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Monster Store');
$pdf->SetAuthor('Monster Store');
$pdf->SetTitle($t['invoice'] . ' - ' . $order['order_code']);
$pdf->SetSubject($t['invoice']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins for modern design
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 25);

// Add a page
$pdf->AddPage();

// Set modern font
$pdf->SetFont('helvetica', '', 10);

// Store information
$storeName = "Monster Store";
$storeAddress = "123 Rue du Commerce, Paris 75001";
$storePhone = "+33 1 23 45 67 89";
$storeEmail = "info@monsterstore.fr";
$storeWebsite = "www.monsterstore.fr";

// Create modern header with gradient background
$pdf->SetFillColor(67, 97, 238); // Primary color
$pdf->Rect(0, 0, 210, 40, 'F');
$pdf->SetTextColor(255, 255, 255);

// Logo
$logoPath = '../assets/images/logo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 10, 25, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    $pdf->SetXY(45, 12);
} else {
    $pdf->SetXY(15, 12);
}

// Store name in header
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 8, $storeName, 0, 1);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, $storeAddress, 0, 1);

// Reset text color for content
$pdf->SetTextColor(0, 0, 0);
$pdf->SetY(45);

// Invoice title section
$pdf->SetFillColor(245, 247, 251);
$pdf->Rect(0, 45, 210, 25, 'F');
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 10, $t['invoice'], 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 6, $t['invoice_number'] . ' : ' . $order['order_code'], 0, 1, 'C');

// Date and time information with modern design
$pdf->SetY(75);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(40, 8, $t['invoice_date'] . ' : ', 0, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(50, 8, date('d/m/Y', strtotime($order['created_at'])), 0, 0);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(30, 8, $t['time'] . ' : ', 0, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, date('H:i:s', strtotime($order['created_at'])), 0, 1);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(40, 8, $t['order_date'] . ' : ', 0, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(50, 8, date('d/m/Y', strtotime($order['created_at'])), 0, 1);

$pdf->Ln(10);

// Customer information with modern card style
$pdf->SetFillColor(240, 240, 240);
$pdf->RoundedRect(15, $pdf->GetY(), 180, 60, 3, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, $t['bill_to'] . ' : ', 0, 1);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(30, 6, $t['nom_prenom'] . ' : ', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $order['customer_name'], 0, 1);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(30, 6, $t['email'] . ' : ', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $order['customer_email'], 0, 1);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(30, 6, $t['telephone'] . ' : ', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $order['customer_phone'], 0, 1);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(30, 6, $t['adresse'] . ' : ', 0, 0);
$pdf->SetFont('helvetica', '', 11);
// Address - handle multi-line
$address = $order['customer_address'] . ', ' . 
           $order['customer_city'] . ', ' . $order['customer_state'] . ' ' . 
           $order['customer_zipcode'] . ', ' . $order['customer_country'];
$pdf->MultiCell(0, 6, $address);
$pdf->Ln(15);

// Items table with modern styling
$pdf->SetFillColor(67, 97, 238);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);

// Column widths
$w = array(90, 25, 30, 35);

// Table header
$pdf->Cell($w[0], 8, $t['description'], 1, 0, 'C', 1);
$pdf->Cell($w[1], 8, $t['quantity'], 1, 0, 'C', 1);
$pdf->Cell($w[2], 8, $t['unit_price'], 1, 0, 'C', 1);
$pdf->Cell($w[3], 8, $t['total'], 1, 1, 'C', 1);

$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 10);

// Table data
foreach ($orderItems as $item) {
    $itemTotal = $item['product_price'] * $item['quantity'];
    
    // Product name (might need to be shortened for mobile)
    $productName = $item['product_name'];
    if (strlen($productName) > 40) {
        $productName = substr($productName, 0, 37) . '...';
    }
    
    $pdf->Cell($w[0], 8, $productName, 'LR', 0, 'L', true);
    $pdf->Cell($w[1], 8, $item['quantity'], 'LR', 0, 'C', true);
    $pdf->Cell($w[2], 8, number_format($item['product_price'], 2) . '  DH', 'LR', 0, 'R', true);
    $pdf->Cell($w[3], 8, number_format($itemTotal, 2) . '  DH', 'LR', 1, 'R', true);
}

// Closing line
$pdf->Cell(array_sum($w), 0, '', 'T');
$pdf->Ln(10);

// Total section with modern styling
$pdf->SetFillColor(245, 247, 251);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell($w[0] + $w[1] + $w[2], 10, $t['subtotal'] . ' : ', 0, 0, 'R', true);
$pdf->Cell($w[3], 10, number_format($total, 2) . '  DH', 0, 1, 'R', true);

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell($w[0] + $w[1] + $w[2], 12, $t['order_total'] . ' : ', 0, 0, 'R', true);
$pdf->Cell($w[3], 12, number_format($total, 2) . '  DH', 0, 1, 'R', true);
$pdf->Ln(15);

// Payment terms section
$pdf->SetFillColor(240, 240, 240);
$pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 3, '1111', 'DF');
$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(0, 10, $t['payment_terms'], 0, 1, 'C');
$pdf->Ln(10);

// Thank you message with modern footer
$pdf->SetTextColor(128, 128, 128);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 5, $t['thank_you'], 0, 1, 'C');
$pdf->Cell(0, 5, $t['contact_info'], 0, 1, 'C');

// Add contact information in footer
$pdf->SetY(-30);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 5, $storeName . ' | ' . $storeAddress . ' | ' . $t['phone'] . ' : ' . $storePhone . ' | ' . $t['email'] . ' : ' . $storeEmail . ' | ' . $t['website'] . ' : ' . $storeWebsite, 0, 1, 'C');
// $pdf->Cell(0, 2, $t['email'] . ' : ' . $storeEmail . ' | ' . $t['website'] . ' : ' . $storeWebsite, 0, 0, 'C');

// Output the PDF
$pdf->Output('invoice-' . $order['order_code'] . '.pdf', 'D');
?>