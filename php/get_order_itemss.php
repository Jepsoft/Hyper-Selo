<?php
// Include the database connection file
require_once 'conn.php'; // Make sure this uses mysqli connection

// Set the content type to application/json
header('Content-Type: application/json');

$sql = "SELECT 
    id, 
    order_id, 
    product_id, 
    title, 
    quantity, 
    price as pr, 
    applied_discount, 
    cost, 
    returned_quantity, 
    margin,
    date 
    FROM order_items 
    WHERE 1=1";

$date = isset($_GET['date']) ? $_GET['date'] : '';

$params = [];
$types = '';

if (!empty($date)) {
    $sql .= " AND DATE(date) = ?"; //  filtering by date
    $types .= 's';
    $params[] = $date;
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $orderItems = $result->fetch_all(MYSQLI_ASSOC);

    // Calculate the price and add it to the result array
    foreach ($orderItems as &$item) {
        $item['price'] = ($item['quantity'] * $item['pr']) - (($item['quantity'] * $item['applied_discount']) + $item['margin']);
    }

    echo json_encode($orderItems);
    $result->free();
} else {
    //  error handling.
    echo json_encode([]); // Return an empty array on error,  consistent JSON response.
}

$stmt->close();
$conn->close();
?>
