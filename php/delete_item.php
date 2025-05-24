<?php
include_once('conn.php');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $item_id = $_POST['id'] ?? null;

    if (empty($item_id) || !is_numeric($item_id) || $item_id <= 0) {
        throw new Exception("Invalid item ID provided.");
    }

    // First, get the image path of the item to delete the file
    $select_image_sql = "SELECT image_path FROM products WHERE product_id = ?";
    $select_image_stmt = $conn->prepare($select_image_sql);
    $select_image_stmt->bind_param("i", $item_id);
    $select_image_stmt->execute();
    $select_image_result = $select_image_stmt->get_result();

    $image_path_to_delete = null;
    if ($select_image_result && $select_image_result->num_rows > 0) {
        $row = $select_image_result->fetch_assoc();
        $image_path_to_delete = '../uploads/' . $row['image_path'];
    }
    if (isset($select_image_stmt)) $select_image_stmt->close();

    // Then, delete the item from the database
    $delete_sql = "DELETE FROM products WHERE product_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);

    if ($delete_stmt === false) {
        throw new Exception("Error preparing delete SQL statement: " . $conn->error);
    }

    $delete_stmt->bind_param("i", $item_id);
    $delete_stmt->execute();

    if ($delete_stmt->affected_rows > 0) {
        // If the item was deleted successfully, try to delete the associated image file
        if ($image_path_to_delete && file_exists($image_path_to_delete) && is_file($image_path_to_delete)) {
            if (unlink($image_path_to_delete)) {
                error_log("Image file deleted: " . $image_path_to_delete);
            } else {
                error_log("Error deleting image file: " . $image_path_to_delete);
                // It's important to note that the item was still deleted from the DB
                $response = array("success" => true, "message" => "Item deleted successfully, but there was an issue deleting the image file.");
                echo json_encode($response);
                exit();
            }
        }
        $response = array("success" => true, "message" => "Item deleted successfully.");
    } else {
        $response = array("success" => false, "error" => "Failed to delete item. Item not found or an error occurred.");
    }

    $delete_stmt->close();

} catch (Exception $e) {
    error_log("Error in delete_item.php: " . $e->getMessage());
    $response = array("success" => false, "error" => $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
    echo json_encode($response);
}
?>