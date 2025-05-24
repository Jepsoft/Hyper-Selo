<?php
require_once 'conn.php';

try {
    $stmt = $conn->prepare("SELECT product_id AS id, title FROM products");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Error fetching products: ' . $e->getMessage()]);
}
?>