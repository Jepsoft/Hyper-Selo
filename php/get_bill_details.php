<?php
include_once('conn.php');

header('Content-Type: application/json');

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    echo json_encode(['error' => 'Order ID is required.']);
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Check database connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Fetch order details
    $order_sql = "SELECT order_id, order_date, total 
                  FROM orders 
                  WHERE order_id = ?";
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order = $order_result->fetch_assoc();
    $order_stmt->close();

    if (!$order) {
        echo json_encode(['error' => 'Order not found.']);
        $conn->close();
        exit();
    }

    // Fetch order items
    $items_sql = "SELECT id, order_id, product_id, title, quantity, price ,returned_quantity
                  FROM order_items 
                  WHERE order_id = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $items = [];
    
    while ($row = $items_result->fetch_assoc()) {
        $items[] = [
            'id' => $row['id'],
            'product_id' => $row['product_id'],
            'title' => $row['title'],
            'quantity' => $row['quantity'] - $row['returned_quantity'],
            'price' => $row['price']
        ];
    }
    $items_stmt->close();

    // Prepare response
    $response = [
        'order' => [
            'order_id' => $order['order_id'],
            'order_date' => $order['order_date'],
            'total' => $order['total']
        ],
        'items' => $items
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>