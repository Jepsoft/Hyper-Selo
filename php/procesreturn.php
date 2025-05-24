<?php
include_once('conn.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get POST data
$brand = $_POST['brand'] ?? '';
$volts = $_POST['volts'] ?? '';
$ah = $_POST['ah'] ?? '';
$billid = intval($_POST['billid'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);

if ($quantity <= 0 || $billid <= 0) {
    die("Invalid quantity or bill ID.");
}

$conn->begin_transaction();

try {
    // 🔍 Step 1: Get price from products table
    $sqlPrice = "SELECT price FROM products WHERE type = ? AND volts = ? AND ah = ? LIMIT 1";
    $stmtPrice = $conn->prepare($sqlPrice);
    $stmtPrice->bind_param("sss", $brand, $volts, $ah);
    $stmtPrice->execute();
    $stmtPrice->bind_result($price);
    $stmtPrice->fetch();
    $stmtPrice->close();

    if ($price <= 0) {
        throw new Exception("Product not found or price is invalid.");
    }

    // 🧮 Step 2: Calculate total cost
    $totalCost = $price * $quantity;

    // 🔧 Step 3: Update product stock
    $sqlUpdateStock = "UPDATE products SET stock = stock + ? WHERE type = ? AND volts = ? AND ah = ?";
    $stmtStock = $conn->prepare($sqlUpdateStock);
    $stmtStock->bind_param("isss", $quantity, $brand, $volts, $ah);
    if (!$stmtStock->execute()) {
        throw new Exception("Error updating stock: " . $stmtStock->error);
    }
    $stmtStock->close();

    // 💰 Step 4: Reduce from balance in finance table
    $sqlUpdateBalance = "UPDATE cashier_balance SET total_sales = total_sales - ? WHERE id = 1";
    $stmtBalance = $conn->prepare($sqlUpdateBalance);
    $stmtBalance->bind_param("d", $totalCost);
    if (!$stmtBalance->execute()) {
        throw new Exception("Error updating balance: " . $stmtBalance->error);
    }
    $stmtBalance->close();

    // 🧹 Step 5: Delete from order_items first
    $sqlDeleteOrderItems = "DELETE FROM order_items WHERE order_id = ?";
    $stmtDeleteItems = $conn->prepare($sqlDeleteOrderItems);
    $stmtDeleteItems->bind_param("i", $billid);
    if (!$stmtDeleteItems->execute()) {
        throw new Exception("Error deleting from order_items: " . $stmtDeleteItems->error);
    }
    $stmtDeleteItems->close();

    // 🧹 Step 6: Delete from orders
    $sqlDeleteOrder = "DELETE FROM orders WHERE order_id = ?";
    $stmtDeleteOrder = $conn->prepare($sqlDeleteOrder);
    $stmtDeleteOrder->bind_param("i", $billid);
    if (!$stmtDeleteOrder->execute()) {
        throw new Exception("Error deleting from orders: " . $stmtDeleteOrder->error);
    }
    $stmtDeleteOrder->close();

    // ✅ Commit all changes
    $conn->commit();
    echo "Stock updated, balance reduced, and order deleted successfully.";

} catch (Exception $e) {
    $conn->rollback();
    echo "Transaction failed: " . $e->getMessage();
}

$conn->close();
?>
