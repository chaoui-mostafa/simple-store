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
        'invoice' => 'FACTURE PRO FORMA',
        'invoice_number' => 'Reference Commande',
        'date' => 'Date emission',
        'time' => 'Heure',
        'bill_to' => 'Client',
        'description' => 'Description',
        'quantity' => 'Quantite',
        'unit_price' => 'Prix Unitaire',
        'total' => 'Total',
        'subtotal' => 'Sous-total',
        'shipping' => 'Frais Livraison',
        'discount' => 'Remise',
        'grand_total' => 'Total General',
        'thank_you' => 'Merci pour votre confiance',
        'contact_info' => 'Pour toute question concernant votre commande',
        'phone' => 'Telephone',
        'email' => 'Email',
        'website' => 'Site web',
        'order_total' => 'Total a Payer',
        'invoice_date' => 'Date facturation',
        'order_date' => 'Date commande',
        'nom_prenom' => 'Nom et Prenom',
        'telephone' => 'Telephone',
        'adresse' => 'Adresse',
        'payment_terms' => 'Conditions Paiement',
        'company_info' => 'MONSTER STORE SARL',
        'legal_info' => 'Capital Social: 200.000 DH | RC: 12345 | IF: 123456789',
        'bank_info' => 'Banque: BMCE | RIB: 123 456 789 123456789123 12',
        'delivery_info' => 'Livraison',
        'payment_methods' => 'Moyens Paiement',
        'return_policy' => 'Retour sous 14 jours',
        'free_shipping' => 'Livraison Offerte',
        'order_summary' => 'Recapitulatif Commande',
        'products_total' => 'Total Produits',
        'shipping_fees' => 'Frais Expedition',
        'order_details' => 'Details Commande'
    ],
    'en' => [
        'invoice' => 'PRO FORMA INVOICE',
        'invoice_number' => 'Order Reference',
        'date' => 'Issue Date',
        'time' => 'Time',
        'bill_to' => 'Client',
        'description' => 'Description',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit Price',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'shipping' => 'Shipping Fees',
        'discount' => 'Discount',
        'grand_total' => 'Grand Total',
        'thank_you' => 'Thank you for your trust',
        'contact_info' => 'For any questions regarding your order',
        'phone' => 'Phone',
        'email' => 'Email',
        'website' => 'Website',
        'order_total' => 'Total Due',
        'invoice_date' => 'Invoice Date',
        'order_date' => 'Order Date',
        'nom_prenom' => 'Full Name',
        'telephone' => 'Phone',
        'adresse' => 'Address',
        'payment_terms' => 'Payment Terms',
        'company_info' => 'MONSTER STORE LLC',
        'legal_info' => 'Share Capital: 200.000 DH | RC: 12345 | Tax ID: 123456789',
        'bank_info' => 'Bank: BMCE | Account: 123 456 789 123456789123 12',
        'delivery_info' => 'Delivery',
        'payment_methods' => 'Payment Methods',
        'return_policy' => '14-day returns',
        'free_shipping' => 'Free Shipping',
        'order_summary' => 'Order Summary',
        'products_total' => 'Products Total',
        'shipping_fees' => 'Shipping Fees',
        'order_details' => 'Order Details'
    ],
    'ar' => [
        'invoice' => 'فاتورة اولية',
        'invoice_number' => 'رقم المرجع',
        'date' => 'تاريخ الاصدار',
        'time' => 'الوقت',
        'bill_to' => 'العميل',
        'description' => 'الوصف',
        'quantity' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'total' => 'المجموع',
        'subtotal' => 'المجموع الفرعي',
        'shipping' => 'رسوم التوصيل',
        'discount' => 'الخصم',
        'grand_total' => 'المجموع الاجمالي',
        'thank_you' => 'شكرا لثقتكم',
        'contact_info' => 'لأي استفسارات بخصوص طلبك',
        'phone' => 'الهاتف',
        'email' => 'البريد الالكتروني',
        'website' => 'الموقع الالكتروني',
        'order_total' => 'المبلغ المستحق',
        'invoice_date' => 'تاريخ الفاتورة',
        'order_date' => 'تاريخ الطلب',
        'nom_prenom' => 'الاسم الكامل',
        'telephone' => 'الهاتف',
        'adresse' => 'العنوان',
        'payment_terms' => 'شروط الدفع',
        'company_info' => 'متجر مونستر ش.ذ.م.م',
        'legal_info' => 'رأس المال: 200.000 درهم | السجل التجاري: 12345 | الرقم الضريبي: 123456789',
        'bank_info' => 'البنك: BMCE | الحساب: 123 456 789 123456789123 12',
        'delivery_info' => 'التوصيل',
        'payment_methods' => 'طرق الدفع',
        'return_policy' => 'الارجاع خلال 14 يوم',
        'free_shipping' => 'توصيل مجاني',
        'order_summary' => 'ملخص الطلب',
        'products_total' => 'مجموع المنتجات',
        'shipping_fees' => 'رسوم الشحن',
        'order_details' => 'تفاصيل الطلب'
    ]
];

$t = $translations[$lang];

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(htmlspecialchars($store_settings['store_name'] ?? 'Monster Store'));
$pdf->SetAuthor(htmlspecialchars($store_settings['store_name'] ?? 'Monster Store'));
$pdf->SetTitle($t['invoice'] . ' - ' . $order['order_code']);
$pdf->SetSubject($t['invoice']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set compact margins
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 25);

// Add a page
$pdf->AddPage();

// Modern color scheme
$primaryColor = array(41, 128, 185);
$accentColor = array(231, 76, 60);
$darkColor = array(52, 73, 94);
$lightColor = array(245, 245, 245);
$successColor = array(46, 204, 113);

// Header section - compact design
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Rect(0, 0, 210, 20, 'F');

// Company name and invoice title
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetXY(10, 5);
$pdf->Cell(100, 8, 'MONSTER STORE', 0, 0);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetXY(120, 5);
$pdf->Cell(80, 8, $t['invoice'], 0, 1, 'R');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(10, 12);
$pdf->Cell(100, 6, $order['order_code'], 0, 0);

$pdf->SetXY(120, 12);
$pdf->Cell(80, 6, date('d/m/Y H:i', strtotime($order['created_at'])), 0, 1, 'R');

// Reset for content
$pdf->SetTextColor(0, 0, 0);
$pdf->SetY(25);

// Company and Client information - side by side
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(95, 5, $t['company_info'], 0, 0);
$pdf->Cell(95, 5, $t['bill_to'], 0, 1);

// Company details
$pdf->SetFont('helvetica', '', 8);
$pdf->MultiCell(95, 4, 
    "123 Avenue Mohammed V\n" .
    "Casablanca 20000, Maroc\n" .
    "Tel: +212 522 123 456\n" .
    "Email: contact@monsterstore.ma\n" .
    "Web: www.monsterstore.ma", 0, 'L');

$pdf->SetY(30);
$pdf->SetX(105);

// Client details
$pdf->MultiCell(95, 4, 
    $order['customer_name'] . "\n" .
    "Email: " . $order['customer_email'] . "\n" .
    "Tel: " . $order['customer_phone'] . "\n" .
    $order['customer_address'] . "\n" .
    $order['customer_city'] . ", " . $order['customer_zipcode'] . "\n" .
    $order['customer_country'], 0, 'L');

$pdf->Ln(8);

// Order details bar
$pdf->SetFillColor($lightColor[0], $lightColor[1], $lightColor[2]);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 6, $t['order_details'], 0, 1, 'C', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(70, 4, $t['date'] . ': ' . date('d/m/Y', strtotime($order['created_at'])), 0, 0);
$pdf->Cell(70, 4, $t['time'] . ': ' . date('H:i:s', strtotime($order['created_at'])), 0, 0);
$pdf->Cell(50, 4, $t['invoice_number'] . ': ' . $order['order_code'], 0, 1);

$pdf->Ln(5);

// Products table header
$pdf->SetFillColor($darkColor[0], $darkColor[1], $darkColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);

// Column widths
$w = array(100, 20, 35, 25);

// Table header
$pdf->Cell($w[0], 8, $t['description'], 1, 0, 'C', 1);
$pdf->Cell($w[1], 8, $t['quantity'], 1, 0, 'C', 1);
$pdf->Cell($w[2], 8, $t['unit_price'], 1, 0, 'C', 1);
$pdf->Cell($w[3], 8, $t['total'], 1, 1, 'C', 1);

// Table data
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 8);

$rowCount = 0;
foreach ($orderItems as $item) {
    $itemTotal = $item['product_price'] * $item['quantity'];
    
    // Alternating row colors
    if ($rowCount % 2 == 0) {
        $pdf->SetFillColor(255, 255, 255);
    } else {
        $pdf->SetFillColor(250, 250, 250);
    }
    
    // Product name
    $productName = $item['product_name'];
    if (strlen($productName) > 60) {
        $productName = substr($productName, 0, 57) . '...';
    }
    
    $pdf->Cell($w[0], 7, $productName, 'LR', 0, 'L', true);
    $pdf->Cell($w[1], 7, $item['quantity'], 'LR', 0, 'C', true);
    $pdf->Cell($w[2], 7, number_format($item['product_price'], 2) . ' DH', 'LR', 0, 'R', true);
    $pdf->Cell($w[3], 7, number_format($itemTotal, 2) . ' DH', 'LR', 1, 'R', true);
    
    $rowCount++;
}

// Closing line
$pdf->Cell(array_sum($w), 0, '', 'T');
$pdf->Ln(8);

// Order summary - compact layout
$subtotal = $total;
$shipping = $subtotal > 500 ? 0 : 45;
$grandTotal = $subtotal + $shipping;

// Summary section
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, $t['order_summary'], 0, 1);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(140, 6, $t['products_total'] . ':', 0, 0, 'R');
$pdf->Cell(30, 6, number_format($subtotal, 2) . ' DH', 0, 1, 'R');

$pdf->Cell(140, 6, $t['shipping_fees'] . ':', 0, 0, 'R');
if ($shipping == 0) {
    $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
    $pdf->Cell(30, 6, $t['free_shipping'], 0, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
} else {
    $pdf->Cell(30, 6, number_format($shipping, 2) . ' DH', 0, 1, 'R');
}

// Grand total
$pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(140, 8, $t['grand_total'] . ':', 0, 0, 'R', true);
$pdf->Cell(30, 8, number_format($grandTotal, 2) . ' DH', 0, 1, 'R', true);

$pdf->Ln(10);

// Additional information - compact layout
$pdf->SetTextColor(0, 0, 0);

// Two column layout for additional info
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(95, 6, $t['payment_methods'], 0, 0);
$pdf->Cell(95, 6, $t['delivery_info'], 0, 1);

$pdf->SetFont('helvetica', '', 8);
$pdf->MultiCell(95, 4, 
    " - Especes\n" .
    " - Carte Bancaire\n" .
    " - Virement Bancaire\n" .
    " - Cheque\n" .
    " - Paiement Mobile", 0, 'L');

$pdf->SetY($pdf->GetY() - 20);
$pdf->SetX(105);
$pdf->MultiCell(95, 4, 
    " - Casablanca: 24h\n" .
    " - Grand Casablanca: 48h\n" .
    " - Livraison offerte des 500 DH\n" .
    " - " . $t['return_policy'], 0, 'L');

$pdf->Ln(8);

// Thank you message
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 8, $t['thank_you'], 0, 1, 'C', true);

// Contact information
$pdf->SetTextColor(100, 100, 100);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, $t['contact_info'], 0, 1, 'C');
$pdf->Cell(0, 5, 'Tel: +212 522 123 456 | Email: contact@monsterstore.ma', 0, 1, 'C');

// Footer with legal information
$pdf->SetY(-25);
$pdf->SetTextColor(150, 150, 150);
$pdf->SetFont('helvetica', '', 7);

// Legal info
$pdf->Cell(0, 3, $t['legal_info'], 0, 1, 'C');
$pdf->Cell(0, 3, $t['bank_info'], 0, 1, 'C');

// Simple footer line
$pdf->SetLineWidth(0.2);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, 275, 200, 275);

// Page number
$pdf->SetY(-12);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(0, 10, 'Page 1/1', 0, 0, 'C');

// Output the PDF
$pdf->Output('invoice-' . $order['order_code'] . '.pdf', 'D');
?>