<?php
include_once('conn.php');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $item_id = $_POST['item_id'] ?? null;
    $nprice = $_POST['nprice'] ?? null;
    $ncost = $_POST['ncost'] ?? null;
    $nstock = $_POST['nstock'] ?? null;
    $nDiscount = $_POST['nDiscount'] ?? null;

    if ($item_id === null || !is_numeric($item_id) || $nprice === null || !is_numeric($nprice) || $ncost === null || !is_numeric($ncost) || $nstock === null || !is_numeric($nstock) || $nDiscount === null || !is_numeric($nDiscount)) {
        throw new Exception("Invalid data provided for stock update.");
    }

    // 1. Fetch the old row based on item_id
    $select_sql = "SELECT title, type, cost, image_path, stock, price, Discount FROM products WHERE product_id = ?";
    $select_stmt = $conn->prepare($select_sql);

    if ($select_stmt === false) {
        throw new Exception("Error preparing select SQL statement: " . $conn->error);
    }

    $select_stmt->bind_param("i", $item_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();

    if ($result->num_rows !== 1) {
        throw new Exception("Item with ID " . $item_id . " not found.");
    }

    $old_row = $result->fetch_assoc();
    $select_stmt->close();

    // 2. Prepare the insert SQL statement for the new row
    $insert_sql = "INSERT INTO products (title, type, cost, image_path, stock, price, Discount, backup) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);

    if ($insert_stmt === false) {
        throw new Exception("Error preparing insert SQL statement: " . $conn->error);
    }

    // Use the old data and replace with the new values
    $new_title = $old_row['title'];
    $new_type = $old_row['type'];
    $new_cost = $ncost;
    $new_image_path = $old_row['image_path']; // Keep the old image path
    $new_stock = $nstock;
    $new_price = $nprice;
    $new_discount = $nDiscount;
    $backup_id = $item_id; // Store the ID of the original item

    $insert_stmt->bind_param("ssdsdddi", $new_title, $new_type, $new_cost, $new_image_path, $new_stock, $new_price, $new_discount, $backup_id);

    // 3. Execute the insert query
    $insert_stmt->execute();

    if ($insert_stmt->affected_rows > 0) {
        $response = array("success" => true, "message" => "New Stock Added successfully.");
    } else {
        $response = array("success" => false, "message" => "Failed to Add the New Stock");
    }

    $insert_stmt->close();

} catch (Exception $e) {
    error_log("Error in duplicate_update_stock.php: " . $e->getMessage());
    $response = array("success" => false, "error" => $e->getMessage());
} finally {
    if ($conn) {
        $conn->close();
    }
    echo json_encode($response);
}
?>