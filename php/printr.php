<?php

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

    // Validate received data
    if (!$orderData || !isset($orderData['order_id']) || !isset($orderData['tableNumber']) || !isset($orderData['order_details'])) {
        throw new Exception('Invalid or incomplete order data received');
    }

    // Generate unique bill ID
    $billId = $orderData['order_id'];
    $tbno = $orderData['tableNumber'];
    $orderDetails = $orderData['order_details'];

    // Database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $subtotal = 0;
    foreach ($orderDetails as $item) {
        // Validate essential item fields
        if (!isset($item['title'], $item['quantity'], $item['price'], $item['cost'], $item['type'], $item['product_id'], $item['original_quantity'])) {
            error_log("Skipping item due to missing fields: " . print_r($item, true));
            continue;
        }

        $name = mb_strimwidth($item['title'], 0, 28, "...");
        $qty = str_pad($item['quantity'], 7, " ", STR_PAD_LEFT);

        if ($tbno == "Take Away") {
            $total = $item['original_quantity'] * $item['price'];
        } else {
            $total = $item['original_quantity'] * $item['cost'];
        }

        $subtotal += $total;
        $totalFormatted = str_pad(number_format($total, 2), 13, " ", STR_PAD_LEFT);

        // Only update stock for certain types
        if ($item['type'] == "Drink" || $item['type'] == "Other" || $item['type'] == "Dessert") {
            $productId = $item['product_id'];
            $quantitySold = $item['quantity'];

            $updateStockSql = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
            $stmt = $conn->prepare($updateStockSql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ii", $quantitySold, $productId);
            $stmt->execute();

            if ($stmt->error) {
                throw new Exception("Error updating stock for product ID $productId: " . $stmt->error);
            }

            $stmt->close();
        }
    }

    // Optionally respond to frontend
    echo json_encode(['status' => 'success', 'subtotal' => $subtotal]);

    // Close DB connection
    $conn->close();

} catch (Exception $e) {
    error_log("Exception caught: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
