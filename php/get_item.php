<?php
include_once('conn.php');
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if ($id === null || !is_numeric($id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid item ID.']);
    exit();
}

$sql = "SELECT * FROM products WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $item = $result->fetch_assoc();
    echo json_encode(['success' => true, 'item' => $item]);
} else {
    echo json_encode(['success' => false, 'error' => 'Item not found.']);
}

$stmt->close();
$conn->close();
?>