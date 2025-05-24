<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include_once('conn.php');

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

$sql = "SELECT order_id, order_date, payment_method, total 
        FROM orders 
        ORDER BY order_id DESC
        LIMIT 100";



$result = $conn->query($sql);

$orders = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

$conn->close();
echo json_encode($orders);
?>