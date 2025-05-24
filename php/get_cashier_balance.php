<?php
include_once('conn.php');

if ($conn->connect_error) {
    echo json_encode(["total_sales" => 0]);
    exit;
}

// Simulated "period" – but data doesn’t really change
$sql = "SELECT total_sales FROM cashier_balance WHERE id = 1";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total = $row['total_sales'] ?? 0;

echo json_encode(["total_sales" => $total]);
