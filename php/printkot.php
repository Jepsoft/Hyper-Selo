<?php
require __DIR__ . '/../vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

include_once('conn.php');
date_default_timezone_set('Asia/Colombo');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Colombo');

// Logging errors
$logFile = 'error_log.txt';
ini_set("log_errors", 1);
ini_set("error_log", $logFile);

try {
    $orderData = json_decode(file_get_contents('php://input'), true);
    $tableNumber = $orderData['tableNumber'] ?? 'Unknown Table';
    $billId = date('h:i:s A');

    error_log("Received Order Data: " . print_r($orderData, true));

    if (!$orderData || !isset($orderData['order_details'])) {
        throw new Exception('Invalid or empty order data received');
    }

    // DB connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Update stock for applicable items
    foreach ($orderData['order_details'] as $item) {
        if (in_array($item['type'], ["Drink", "Dessert", "Other", "Short Eats"])) {
            $paidQuantity = $item['quantity'];
            $productId = $item['product_id'];

            $updateStockSql = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
            $stmt = $conn->prepare($updateStockSql);
            $stmt->bind_param("ii", $paidQuantity, $productId);
            $stmt->execute();
            if ($stmt->error) {
                throw new Exception("Error updating stock for product ID " . $productId . ": " . $stmt->error);
            }
            $stmt->close();
        }
    }

    // Category groups to print separately
    $categoryGroups = [
        'Fried Rice | KOT' => ['Fried Rice'],
        'Kottu | KOT' => ['Kottu', 'Cheese Kottu'],
        'Chinese | KOT' => ['Biryani', 'Boiled','Chilli','Chopsuey','Chopsuey Rice','Curry','Devilled','Fried','Grilled','Hot Butter','Noodles','Pasta','Omelet','Pepper','Snacks','Soup','Stew']
    ];

    $hasPrintableItems = false;

    // Function to print each category group
    function printKOT($items, $groupName, $tableNumber) {
        $connector = new WindowsPrintConnector("XP-80C");
        $printer = new Printer($connector);
        $printer->initialize();

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 1);
        $header = ($tableNumber == "Take Away") ? "Take Away" : "Table $tableNumber";
        $printer->text("$header | $groupName \n" . date("Y-m-d h:i:s A") . "\n\n");


        $printer->setEmphasis(true);
        $printer->text(str_pad("Item", 38) . str_pad("Qty", 10, " ", STR_PAD_LEFT) . "\n");
        $printer->setEmphasis(false);
        $printer->text(str_repeat("-", 48) . "\n");

        foreach ($items as $item) {
            $name = mb_strimwidth($item['title'], 0, 38, "...");
            $qty = str_pad($item['quantity'], 10, " ", STR_PAD_LEFT);
            $printer->text(str_pad($name, 38) . $qty . "\n");
        }

        $printer->feed();
        $printer->cut();
        $printer->close();
    }

    // Process and print each category group
    foreach ($categoryGroups as $groupName => $types) {
        $groupItems = array_filter($orderData['order_details'], function ($item) use ($types) {
            return in_array($item['type'], $types);
        });

        if (count($groupItems) > 0) {
            $hasPrintableItems = true;
            printKOT($groupItems, $groupName, $tableNumber);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $hasPrintableItems ? 'Receipt(s) printed successfully.' : 'Only drinks/desserts - nothing printed.',
        'bill_id' => $billId,
    ]);

    $conn->close();
} catch (Exception $e) {
    error_log("Exception caught: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    if (isset($conn)) {
        $conn->close();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Failed to process order and print receipt: ' . $e->getMessage()
    ]);
}
?>
