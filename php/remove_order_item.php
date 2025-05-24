<?php
include 'conn.php';

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM order_items WHERE        = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
?>
