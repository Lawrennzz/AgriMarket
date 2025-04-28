<?php
require_once('config.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error_message'] = "Invalid order ID provided.";
    header('Location: my_orders.php');
    exit();
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$order_query = "SELECT * FROM orders WHERE order_id = ? AND user_id = ?";
$order_stmt = mysqli_prepare($conn, $order_query);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($order_stmt);
$order_result = mysqli_stmt_get_result($order_stmt);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    $_SESSION['error_message'] = "Order not found or access denied.";
    header('Location: my_orders.php');
    exit();
}

// Fetch order items
$items_query = "SELECT oi.*, p.name, p.image_url 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ?";
$items_stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($items_stmt, "i", $order_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

$order_items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $item;
}

// Parse shipping address
$shipping_address = json_decode($order['shipping_address'], true);

// Create new PDF document
class MYPDF extends TCPDF {
    public function Header() {
        $image_file = __DIR__ . '/assets/images/logo.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 10, 10, 40, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'AgriMarket Receipt', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('AgriMarket');
$pdf->SetAuthor('AgriMarket');
$pdf->SetTitle('Receipt - Order #' . $order_id);

// Set margins
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Order Information
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Order Information', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Order #: ' . $order_id, 0, 1, 'L');
$pdf->Cell(0, 10, 'Date: ' . date('F j, Y', strtotime($order['created_at'])), 0, 1, 'L');
$pdf->Cell(0, 10, 'Payment Method: ' . ucwords(str_replace('_', ' ', $order['payment_method'])), 0, 1, 'L');

// Shipping Information
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Shipping Information', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Name: ' . htmlspecialchars($shipping_address['full_name']), 0, 1, 'L');
$pdf->MultiCell(0, 10, 'Address: ' . htmlspecialchars($shipping_address['address'] . ', ' . 
                                                     $shipping_address['city'] . ', ' . 
                                                     $shipping_address['state'] . ' ' . 
                                                     $shipping_address['zip']), 0, 'L');

// Order Items
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Order Items', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

// Table header
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(80, 10, 'Product', 1, 0, 'L', true);
$pdf->Cell(30, 10, 'Price', 1, 0, 'R', true);
$pdf->Cell(30, 10, 'Quantity', 1, 0, 'R', true);
$pdf->Cell(40, 10, 'Total', 1, 1, 'R', true);

// Table content
foreach ($order_items as $item) {
    // If product name is too long, split it into multiple lines
    $name = htmlspecialchars($item['name']);
    if (strlen($name) > 40) {
        $name = substr($name, 0, 37) . '...';
    }
    
    $pdf->Cell(80, 10, $name, 1, 0, 'L');
    $pdf->Cell(30, 10, '$' . number_format($item['price'], 2), 1, 0, 'R');
    $pdf->Cell(30, 10, $item['quantity'], 1, 0, 'R');
    $pdf->Cell(40, 10, '$' . number_format($item['price'] * $item['quantity'], 2), 1, 1, 'R');
}

// Order Summary
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Order Summary', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

$pdf->Cell(140, 10, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(40, 10, '$' . number_format($order['subtotal'], 2), 0, 1, 'R');

$pdf->Cell(140, 10, 'Shipping:', 0, 0, 'R');
$pdf->Cell(40, 10, '$' . number_format($order['shipping'], 2), 0, 1, 'R');

$pdf->Cell(140, 10, 'Tax:', 0, 0, 'R');
$pdf->Cell(40, 10, '$' . number_format($order['tax'], 2), 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(140, 10, 'Total:', 0, 0, 'R');
$pdf->Cell(40, 10, '$' . number_format($order['total'], 2), 0, 1, 'R');

// Thank you message
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 12);
$pdf->Cell(0, 10, 'Thank you for shopping with AgriMarket!', 0, 1, 'C');

// Output the PDF
$pdf->Output('AgriMarket_Receipt_' . $order_id . '.pdf', 'I');
?> 