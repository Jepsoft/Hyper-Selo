<?php
require __DIR__ . '/../vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;

include_once('conn.php');

// Error Reporting (for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
$logFile = 'error_log.txt';
ini_set("log_errors", 1);
ini_set("error_log", $logFile);

try {
    // Get the order data from the POST request
    $orderData = json_decode(file_get_contents('php://input'), true);

    // Log the received order data for debugging
    error_log("Received Order Data: " . print_r($orderData, true));

    // Make sure the orderData is properly received
    if (!$orderData) {
        throw new Exception('No order data received');
    }

    // Generate unique bill ID
    $billId = $orderData['order_id'];

    // Database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $connector = new WindowsPrintConnector("XP-80C");
    $printer = new Printer($connector);

    $printer->initialize();
$printer->pulse();
    // Center the following text
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    
    // Bold + Bigger for main title
    $printer->setEmphasis(true);
    $printer->setTextSize(2, 2);
    $printer->text("Daiya Buffet\n");
     $printer->text("\n");
    $printer->setTextSize(1, 1);
    
    // Reset back to normal size and no bold
    $printer->setTextSize(1, 1);
    $printer->setEmphasis(false);
    
    // Print the rest in normal size
    $printer->text("Kosgoda Gewatta, Hiththetiya West, Matara\n");
    $printer->text("Tel - 071 618 0738\n");
    
    // Add a line break (feed)
    $printer->feed();
    

    // Bill info
    $printer->text("Bill No: " . $billId . "     "."Date: " . date("Y-m-d") . "\n");

    $printer->feed();

    // Items
    $printer->setEmphasis(true);
   $printer->text(str_pad("Item", 34) . str_pad("Qty", 3, " ", STR_PAD_LEFT) . str_pad("Total", 11, " ", STR_PAD_LEFT) . "\n");
$printer->setEmphasis(false);
$printer->text(str_repeat("-", 48) . "\n");

$tbno = $orderData['tableNumber'];
$subtotal = 0;

foreach ($orderData['order_details'] as $item) {
    $name = str_pad(mb_strimwidth($item['title'], 0, 34, ""), 34); // Truncate and pad item name
    $qty = str_pad($item['quantity'], 3, " ", STR_PAD_LEFT);
    
    if ($tbno == "Take Away") {
        $total = $item['original_quantity'] * $item['price'];
    } else {
        $total = $item['original_quantity'] * $item['cost'];
    }

    $subtotal += $total;
    $totalFormatted = str_pad(number_format($total, 2), 11, " ", STR_PAD_LEFT);



        $printer->text(str_pad($name, 28) . $qty . $totalFormatted . "\n");
        // if ($item['type'] == "Drink" || $item['type'] == "Other" || $item['type'] == "Dessert") {
        //     $productId = $item['product_id'];
        //     $quantitySold = $item['quantity'];
        //     $updateStockSql = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
        //     $stmt = $conn->prepare($updateStockSql);
        //     $stmt->bind_param("ii", $quantitySold, $productId);
        //     $stmt->execute();
        //     if ($stmt->error) {
        //         throw new Exception("Error updating stock for product ID " . $productId . ": " . $stmt->error);
        //     }
        //     $stmt->close();
        // }
    }

    // Subtotal, discount, total
    $printer->text(str_repeat("-", 48) . "\n");
    $printer->text(str_pad("Subtotal:", 35) . str_pad( number_format($subtotal, 2), 13, " ", STR_PAD_LEFT) . "\n");

    // Happy Discount
    $happyDiscount = $orderData['happy_discount'];
    // Manual Discount
    $manualDiscount = $orderData['discount'];
    $ordis=$happyDiscount+$manualDiscount;
    
        $additional = $orderData['additional'];
    if ($additional > 0) {
        $printer->text(str_pad("Additional:", 35) . str_pad(number_format($additional, 2), 13, " ", STR_PAD_LEFT) . "\n");
    }
    if ($manualDiscount > 0) {
        $printer->text(str_pad("Discount:", 35) . str_pad(number_format($ordis, 2), 13, " ", STR_PAD_LEFT) . "\n");
    }
    $totalDiscount = $happyDiscount + $manualDiscount;
    $totalAmount = $subtotal - $totalDiscount+$additional;
    $printer->text(str_pad("Total:", 35) . str_pad( number_format($totalAmount, 2), 13, " ", STR_PAD_LEFT) . "\n");
    $printer->text(str_repeat("=", 48) . "\n");

    // Cash info
    $cashReceived = $orderData['cash_received'];
    $paymentMethod = $orderData['payment_method'];

    if ($paymentMethod === 'cash' && $cashReceived !== null) {
        $change = $cashReceived - $totalAmount;
        $printer->text(str_pad("Cash Received:", 35) . str_pad( number_format($cashReceived, 2), 13, " ", STR_PAD_LEFT) . "\n");
        $printer->text(str_pad("Balance:", 35) . str_pad( number_format($change, 2), 13, " ", STR_PAD_LEFT) . "\n");
        $printer->text(str_repeat("=", 48) . "\n");
    } else if ($paymentMethod === 'card') {
        $printer->text(str_pad("Payment Method:", 35) . str_pad(ucfirst($paymentMethod), 13, " ", STR_PAD_LEFT) . "\n");
        $printer->text(str_repeat("=", 48) . "\n");
    }

    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->text("Thank you. Come Again!\n");
    $printer->feed();
    $printer->text("Software by Jepsoft | Phone : 077 483 5253\n");
    $printer->feed(2);
    $printer->cut();
    $printer->close();


    // Respond with success message and the bill ID
    echo json_encode([
        'success' => true,
        'message' => 'Receipt printed successfully.',
        'bill_id' => $billId,
        // 'order_id' => $orderId // You need to define $orderId somewhere if you want to return it
    ]);

    $conn->close();

} catch (Exception $e) {
    // Log the exception details
    error_log("Exception caught: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // If any error occurred, rollback the transaction if started (you don't have a transaction here)
    if (isset($conn)) {
        $conn->close();
    }
    echo json_encode([
        'success' => true,
        'message' => 'Failed to process order and print receipt: ' . $e->getMessage()
    ]);
}
?>