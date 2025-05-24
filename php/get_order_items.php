<?php
include 'conn.php';

$billid = $_GET['billid'];

$stmt = $conn->prepare("SELECT id, title, quantity, price FROM order_items WHERE order_id = ?");
$stmt->bind_param("s", $billid);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode(['items' => $items]);
?>
