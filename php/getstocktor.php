<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

// Get all distinct brands
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'getBrands':
            $stmt = $conn->prepare("SELECT DISTINCT title as brand FROM products ORDER BY title");
            $stmt->execute();
            $result = $stmt->get_result();
            $brands = [];
            while ($row = $result->fetch_assoc()) {
                $brands[] = $row['brand'];
            }
            echo json_encode(['success' => true, 'brands' => $brands]);
            break;
            
        case 'getAhs':
            if (isset($_GET['brand'])) {
                $brand = $_GET['brand'];
                $stmt = $conn->prepare("SELECT DISTINCT ah FROM products WHERE title = ? ORDER BY ah");
                $stmt->bind_param("s", $brand);
                $stmt->execute();
                $result = $stmt->get_result();
                $ahs = [];
                while ($row = $result->fetch_assoc()) {
                    $ahs[] = $row['ah'];
                }
                echo json_encode(['success' => true, 'ahs' => $ahs]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Brand parameter missing']);
            }
            break;
            
        case 'getVolts':
            if (isset($_GET['brand']) && isset($_GET['ah'])) {
                $brand = $_GET['brand'];
                $ah = $_GET['ah'];
                $stmt = $conn->prepare("SELECT DISTINCT volts FROM products WHERE title = ? AND ah = ? ORDER BY volts");
                $stmt->bind_param("ss", $brand, $ah);
                $stmt->execute();
                $result = $stmt->get_result();
                $volts = [];
                while ($row = $result->fetch_assoc()) {
                    $volts[] = $row['volts'];
                }
                echo json_encode(['success' => true, 'volts' => $volts]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Brand or AH parameter missing']);
            }
            break;
            
        case 'getProductId':
            if (isset($_GET['brand']) && isset($_GET['ah']) && isset($_GET['volts'])) {
                $brand = $_GET['brand'];
                $ah = $_GET['ah'];
                $volts = $_GET['volts'];
                $stmt = $conn->prepare("SELECT product_id FROM products WHERE title = ? AND ah = ? AND volts = ? LIMIT 1");
                $stmt->bind_param("sss", $brand, $ah, $volts);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    echo json_encode(['success' => true, 'product_id' => $row['product_id']]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Required parameters missing']);
            }
            break;
            
        case 'getCurrentStock':
            $stmt = $conn->prepare("SELECT product_id, title as brand, ah, volts, stock as quantity FROM products ORDER BY title, ah, volts");
            $stmt->execute();
            $result = $stmt->get_result();
            $stock = [];
            while ($row = $result->fetch_assoc()) {
                $stock[] = $row;
            }
            echo json_encode(['success' => true, 'stock' => $stock]);
            break;
            
        case 'getReleasedStock':
            $stmt = $conn->prepare("SELECT r.release_id, r.shop_name, p.title as brand, p.ah, p.volts, r.quantity, r.release_date 
                                  FROM released_stock r 
                                  JOIN products p ON r.product_id = p.product_id 
                                  ORDER BY r.release_date DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $releasedStock = [];
            while ($row = $result->fetch_assoc()) {
                $releasedStock[] = $row;
            }
            echo json_encode(['success' => true, 'releasedStock' => $releasedStock]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Handle form submission for releasing stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['product_id']) && isset($data['quantity']) && isset($data['shop_name'])) {
        $productId = $data['product_id'];
        $quantity = $data['quantity'];
        $shopName = $data['shop_name'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Check if product exists and has enough stock
            $stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = ? FOR UPDATE");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Product not found");
            }
            
            $row = $result->fetch_assoc();
            $currentStock = $row['stock'];
            
            if ($currentStock < $quantity) {
                throw new Exception("Insufficient stock");
            }
            
            // Update product stock
            $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
            $stmt->bind_param("ii", $quantity, $productId);
            $stmt->execute();
            
            // Record the release
            $stmt = $conn->prepare("INSERT INTO released_stock (product_id, shop_name, quantity, release_date) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("isi", $productId, $shopName, $quantity);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock released successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    }
    exit;
}
?>