<?php 
include_once('conn.php');  
header('Content-Type: application/json');  

try { 
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        throw new Exception("Invalid request method."); 
    }  

    // Collect and sanitize input values
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $cost = trim($_POST['cost'] ?? '');
    $Discount = trim($_POST['Discount'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $buy = trim($_POST['buy'] ?? '');
    $get = trim($_POST['get'] ?? '');
    
    // Validate required fields
    if (empty($title) || empty($type) || !is_numeric($price)) { 
        throw new Exception("Please fill in all required fields."); 
    }  

    // Image upload handling
    $image_path = '';  
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {  
        $uploadDir = '../uploads/'; // Adjust this path as needed  
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Get file extension and validate type
        $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png'])) {
            throw new Exception("Only JPG, JPEG, and PNG files are allowed.");
        }

        // Validate file size (2MB limit)
        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {  
            throw new Exception("Image size cannot exceed 2MB.");  
        }  

        // Generate a unique file name
        $newFileName = uniqid() . '.' . $imageFileType;  
        $uploadFile = $uploadDir . $newFileName; 

        // Attempt to move uploaded file
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {  
            $image_path = $newFileName;  
            error_log("Image uploaded successfully: " . $uploadFile);
        } else {  
            throw new Exception("Failed to upload image.");  
        }  
    }  

    // Insert into database
    $sql = "INSERT INTO products (title, type, price, image_path, cost,stock,Discount,buy,get) VALUES (?, ?, ?, ?, ?, ?,?,?,?)";  
    $stmt = $conn->prepare($sql);  

    if ($stmt === false) {  
        throw new Exception("Error preparing SQL statement: " . $conn->error);  
    }  

    $stmt->bind_param("ssdssssii", $title, $type, $price, $image_path, $cost,$stock,$Discount,$buy,$get);  
    $stmt->execute();  

    if ($stmt->affected_rows > 0) {  
        $response = array("success" => true, "message" => "Item added successfully.");  
    } else {  
        throw new Exception("Error adding item to the database: " . $stmt->error);  
    }  

    $stmt->close();  
} catch (Exception $e) {  
    error_log("Error in add_item.php: " . $e->getMessage());  
    $response = array("success" => false, "error" => $e->getMessage());  
} finally {  
    if (isset($conn)) {  
        $conn->close();  
    }  
    echo json_encode($response);  
}  
?>
