<?php
include 'conn.php';

$billid = $_GET['billid'];

$stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
$stmt->bind_param("s", $billid);
$stmt->execute();
?>
