<?php
header('Content-Type: application/json');

// Include the database connection
require_once 'conn.php';

// Get the selected product_id from the query string
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

$response = [];

if ($product_id > 0) {
    $stmt = $conn->prepare("SELECT ingredient_id, name, amount, unit FROM recipe WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response[] = [
                'id' => $row['ingredient_id'],   // assuming 'ingredient_id' matches 'id' in kitchen table
                'name' => $row['name'],
                'quantity' => $row['amount'],
                'unit' => $row['unit']
            ];
        }
    }
    $stmt->close();
}

echo json_encode($response);
?>
