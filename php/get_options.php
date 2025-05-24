<?php
header('Content-Type: application/json');
include_once('conn.php');

if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

// Get billid from GET parameters
$billid = isset($_GET['billid']) ? $conn->real_escape_string($_GET['billid']) : null;

$data = [
    'brands' => [],
    'volts' => [],
    'ahs' => [],
    'quantity' => [],
];

if ($billid) {
    // Now use billid to filter results
    $result = $conn->query("SELECT DISTINCT id FROM order_items WHERE order_id = '$billid'");
    while ($row = $result->fetch_assoc()) {
        $data['brands'][] = $row['id'];
    }

    $result = $conn->query("SELECT DISTINCT title FROM order_items WHERE order_id = '$billid'");
    while ($row = $result->fetch_assoc()) {
        $data['volts'][] = $row['title'];
    }

    $result = $conn->query("SELECT DISTINCT price FROM order_items WHERE order_id = '$billid'");
    while ($row = $result->fetch_assoc()) {
        $data['ahs'][] = $row['price'];
    }
    $result = $conn->query("SELECT DISTINCT quantity FROM order_items WHERE order_id = '$billid'");
    while ($row = $result->fetch_assoc()) {
        $data['quantity'][] = $row['quantity'];
    }
} else {
    $data['error'] = 'No billid provided';
}

echo json_encode($data);
?>
