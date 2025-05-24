<?php
require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Only POST requests are allowed']);
    exit();
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!isset($data['product_id'], $data['ingredients']) || !is_array($data['ingredients'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid input data']);
    exit();
}

$conn->beginTransaction();

try {
    // Insert recipe
    $stmt = $conn->prepare("INSERT INTO recipe (product_id, created_at) VALUES (:product_id, NOW())");
    $stmt->execute([':product_id' => $data['product_id']]);
    $recipeId = $conn->lastInsertId();
    
    
    // Link product to recipe
    $stmt = $conn->prepare("INSERT INTO itemxkitchenm (food_id, recipe_id) VALUES (:product_id, :recipe_id) ON DUPLICATE KEY UPDATE recipe_id = :recipe_id");
    $stmt->execute([
        ':product_id' => $data['product_id'],
        ':recipe_id' => $recipeId
    ]);
    
    $conn->commit();
    http_response_code(200);
    echo json_encode(['message' => 'Recipe saved successfully', 'recipe_id' => $recipeId]);
} catch(PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['message' => 'Error saving recipe: ' . $e->getMessage()]);
}
?>