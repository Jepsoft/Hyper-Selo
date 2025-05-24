<?php
include_once('conn.php');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $item_id = $_POST['id'] ?? null;
    $title = $_POST['title'] ?? '';
    $type = $_POST['type'] ?? '';
    $price = $_POST['price'] ?? 0;
    $cost = $_POST['cost'] ?? 0;
    $stock = trim($_POST['stock'] ?? '');
    $Discount = trim($_POST['Discount'] ?? '');
    $get = trim($_POST['get'] ?? '');
    $buy = trim($_POST['buy'] ?? '');

    if (empty($title) || !is_numeric($price) || !is_numeric($cost)) {
        throw new Exception("Please fill in all required fields (Title, Price, Cost).");
    }

    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        $uploadFile = $uploadDir . basename($_FILES['image']['name']);
        $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));

        $allowedTypes = ["jpg", "jpeg", "png"];
        if (!in_array($imageFileType, $allowedTypes)) {
            throw new Exception("Only JPG, JPEG, and PNG files are allowed.");
        }

        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            throw new Exception("Image size cannot exceed 2MB.");
        }

        $newFileName = uniqid() . '.' . $imageFileType;
        $uploadFile = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            if ($item_id) {
                $old_image_sql = "SELECT image_path FROM products WHERE product_id = ?";
                $old_image_stmt = $conn->prepare($old_image_sql);
                $old_image_stmt->bind_param("i", $item_id);
                $old_image_stmt->execute();
                $old_image_result = $old_image_stmt->get_result();
                if ($old_image_result && $old_image_result->num_rows > 0) {
                    $old_image_data = $old_image_result->fetch_assoc();
                    $old_image_path = '../uploads/' . $old_image_data['image_path'];
                    if (!empty($old_image_data['image_path']) && file_exists($old_image_path) && is_file($old_image_path)) {
                        if (unlink($old_image_path)) {
                            error_log("Old image deleted: " . $old_image_path);
                        } else {
                            error_log("Error deleting old image: " . $old_image_path);
                        }
                    }
                } elseif ($old_image_result === false) {
                    error_log("Error fetching old image path: " . $conn->error);
                }
                if (isset($old_image_stmt)) $old_image_stmt->close();
            }
            $image_path = $newFileName;
            error_log("New image uploaded: " . $uploadFile);
        } else {
            throw new Exception("Failed to upload new image.");
        }
    } elseif ($item_id) {
        $old_image_sql = "SELECT image_path FROM products WHERE product_id = ?";
        $old_image_stmt = $conn->prepare($old_image_sql);
        $old_image_stmt->bind_param("i", $item_id);
        $old_image_stmt->execute();
        $old_image_result = $old_image_stmt->get_result();
        if ($old_image_result && $old_image_result->num_rows > 0) {
            $old_image_data = $old_image_result->fetch_assoc();
            $image_path = $old_image_data['image_path'];
            error_log("Keeping existing image for item ID: " . $item_id . ", path: " . $image_path);
        } elseif ($old_image_result === false) {
            error_log("Error fetching existing image path: " . $conn->error);
        }
        if (isset($old_image_stmt)) $old_image_stmt->close();
    }

    if ($item_id) {
        // **UPDATE existing item**
        $sql = "UPDATE products SET title = ?, type = ?, price = ?, cost = ?, image_path = ?, stock = ? ,Discount = ?,get = ?,buy = ? WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing update SQL statement: " . $conn->error);
        }
        $stmt->bind_param("ssdssssiii", $title, $type, $price, $cost, $image_path,$stock,$Discount,$get,$buy,$item_id);
    } 

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response = array("success" => true, "message" => $item_id ? "Item updated successfully." : "Item added successfully.");
    } else {
        $response = array("success" => false, "error" => "Failed to save item. No changes made or an error occurred.");
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Error in save_item.php: " . $e->getMessage());
    $response = array("success" => false, "error" => $e->getMessage());
} finally {
    if ($conn) {
        $conn->close();
    }
    echo json_encode($response);
}
?>