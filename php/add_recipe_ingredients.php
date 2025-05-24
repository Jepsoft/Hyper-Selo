<?php
// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Return JSON output
header('Content-Type: application/json');

include 'conn.php'; // Make sure this file doesn't suppress errors

// Read and decode the JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['product_id']) || !isset($data['ingredients']) || !is_array($data['ingredients'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input format']);
    exit;
}

$product_id = intval($data['product_id']);

// Start transaction
$conn->begin_transaction();

try {
    // Delete existing ingredients for the product
    $delete_stmt = $conn->prepare("DELETE FROM recipe WHERE product_id = ?");
    if (!$delete_stmt) {
        throw new Exception("Delete prepare failed: " . $conn->error);
    }
    $delete_stmt->bind_param("i", $product_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Prepare insert statement
    $insert_stmt = $conn->prepare("INSERT INTO recipe (product_id, ingredient_id, amount,name,unit) VALUES (?, ?, ?, ?, ?)");
    if (!$insert_stmt) {
        throw new Exception("Insert prepare failed: " . $conn->error);
    }

    // Insert all new ingredients
    foreach ($data['ingredients'] as $ingredient) {
        if (!isset($ingredient['ingredient_id']) || !isset($ingredient['quantity'])) {
            throw new Exception("Missing ingredient_id or quantity in ingredient entry");
        }

        $ingredient_id = intval($ingredient['ingredient_id']);
        $quantity = floatval($ingredient['quantity']);
        $name = $ingredient['name'];
        $unit = $ingredient['unit'];

        $insert_stmt->bind_param("iidss", $product_id, $ingredient_id, $quantity,$name,$unit);
        $insert_stmt->execute();
    }

    $insert_stmt->close();
    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?>
