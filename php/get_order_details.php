<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once('conn.php');

try {
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    if ($order_id <= 0) {
        throw new Exception("Invalid order ID");
    }
    // Get order header
    $sql_order = "SELECT 
                     order_id, 
                     order_date, 
                     subtotal, 
                     discount, 
                     user_id, 
                     total 
                  FROM orders 
                  WHERE order_id = ?";
    $stmt_order = $conn->prepare($sql_order);
    if (!$stmt_order) {
        throw new Exception("Order prepare failed: " . $conn->error);
    }
    
    $stmt_order->bind_param("i", $order_id);
    if (!$stmt_order->execute()) {
        throw new Exception("Order execute failed: " . $stmt_order->error);
    }
    
    $result_order = $stmt_order->get_result();
    
    if ($result_order->num_rows === 0) {
        throw new Exception("Order not found");
    }
    
    $order = $result_order->fetch_assoc();
    $stmt_order->close();

    // Get order items (assuming you have an order_items table)
    $sql_items = "SELECT 
                     product_id, 
                     quantity, 
                     price 
                  FROM order_items 
                  WHERE order_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    if (!$stmt_items) {
        throw new Exception("Items prepare failed: " . $conn->error);
    }
    
    $stmt_items->bind_param("i", $order_id);
    if (!$stmt_items->execute()) {
        throw new Exception("Items execute failed: " . $stmt_items->error);
    }
    
    $result_items = $stmt_items->get_result();
    $items = [];
    
    if ($result_items->num_rows > 0) {
        while($row = $result_items->fetch_assoc()) {
            $items[] = [
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'price' => $row['price']
            ];
        }
    }
    $stmt_items->close();

    $order['items'] = $items;
    
    echo json_encode([
        'status' => 'success',
        'data' => $order
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>