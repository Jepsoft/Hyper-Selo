<?php
include_once('conn.php');

// Function to check if a backup exists as product_id
function backupExists($conn, $backup) {
    $checkSQL = "SELECT 1 FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($checkSQL);
    $stmt->bind_param("i", $backup); // Assuming backup is an integer
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->num_rows > 0;
}

// Step 1: Delete products with stock = 0 that have a backup
$deleteSQL = "
DELETE FROM products
WHERE stock = 0
AND EXISTS (
    SELECT 1
    FROM products p2
    WHERE p2.backup = products.product_id
)";

if ($conn->query($deleteSQL) === TRUE) {
    // echo "Deleted out-of-stock products with backups successfully.<br>";
} else {
    echo "Error deleting records: " . $conn->error . "<br>";
}

// Step 2: Select products with stock > 0 and categorize them
$selectWithStockSQL = "
SELECT *
FROM products
WHERE stock > 0
ORDER BY COALESCE(backup, product_id) ASC, created_at ASC
";

$resultWithStock = $conn->query($selectWithStockSQL);

$with_backup = array();
$without_backup = array();

if ($resultWithStock->num_rows > 0) {
    $groupedProducts = array();
    while ($row = $resultWithStock->fetch_assoc()) {
        $groupId = $row['backup'] ?? $row['product_id'];
        $groupedProducts[$groupId][] = $row;
    }

    foreach ($groupedProducts as $groupId => $productsInGroup) {
        $hasBackupReference = false;
        if (!empty($productsInGroup[0]['backup']) && backupExists($conn, $productsInGroup[0]['backup'])) {
            $hasBackupReference = true;
        }

        $count = 0;
        foreach ($productsInGroup as $product) {
            if ($hasBackupReference) {
                $with_backup[] = $product;
            } else {
                if ($count === 0) {
                    $without_backup[] = $product;
                } else {
                    $with_backup[] = $product; // Consider subsequent items with the same backup/id as 'with_backup'
                }
            }
            $count++;
        }
    }
}

// Step 3: Select out-of-stock products
$selectOutOfStockSQL = "
SELECT *
FROM products
WHERE stock = 0
ORDER BY COALESCE(backup, product_id) ASC, created_at ASC
";

$resultOutOfStock = $conn->query($selectOutOfStockSQL);
$out_of_stock = array();
if ($resultOutOfStock->num_rows > 0) {
    $out_of_stock = $resultOutOfStock->fetch_all(MYSQLI_ASSOC);
}

// Output JSON with three groups
header('Content-Type: application/json');
echo json_encode([
    "products" => [
        "with_backup" => $with_backup,
        "without_backup" => $without_backup,
        "out_of_stock" => $out_of_stock
    ]
]);

$conn->close();
?>