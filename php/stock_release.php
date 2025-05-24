<?php
header('Content-Type: application/json');
require_once 'conn.php';

// Handle all GET requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'getTitles':
            $stmt = $conn->prepare("SELECT DISTINCT title FROM products ORDER BY title");
            $stmt->execute();
            $result = $stmt->get_result();
            $titles = [];
            while ($row = $result->fetch_assoc()) {
                $titles[] = $row['title'];
            }
            echo json_encode(['success' => true, 'titles' => $titles]);
            break;

        case 'getBrands':
            if (isset($_GET['title'])) {
                $title = $_GET['title'];
                $stmt = $conn->prepare("SELECT DISTINCT type as brand FROM products WHERE title = ? ORDER BY type");
                $stmt->bind_param("s", $title);
                $stmt->execute();
                $result = $stmt->get_result();
                $brands = [];
                while ($row = $result->fetch_assoc()) {
                    $brands[] = $row['brand'];
                }
                echo json_encode(['success' => true, 'brands' => $brands]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Title parameter missing']);
            }
            break;
            
        case 'getAhs':
            if (isset($_GET['title']) && isset($_GET['brand'])) {
                $title = $_GET['title'];
                $brand = $_GET['brand'];
                $stmt = $conn->prepare("SELECT DISTINCT ah FROM products WHERE title = ? AND type = ? ORDER BY ah");
                $stmt->bind_param("ss", $title, $brand);
                $stmt->execute();
                $result = $stmt->get_result();
                $ahs = [];
                while ($row = $result->fetch_assoc()) {
                    $ahs[] = $row['ah'];
                }
                echo json_encode(['success' => true, 'ahs' => $ahs]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Title or Brand parameter missing']);
            }
            break;
            
        case 'getVolts':
            if (isset($_GET['title']) && isset($_GET['brand']) && isset($_GET['ah'])) {
                $title = $_GET['title'];
                $brand = $_GET['brand'];
                $ah = $_GET['ah'];
                $stmt = $conn->prepare("SELECT DISTINCT volts FROM products WHERE title = ? AND type = ? AND ah = ? ORDER BY volts");
                $stmt->bind_param("sss", $title, $brand, $ah);
                $stmt->execute();
                $result = $stmt->get_result();
                $volts = [];
                while ($row = $result->fetch_assoc()) {
                    $volts[] = $row['volts'];
                }
                echo json_encode(['success' => true, 'volts' => $volts]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Required parameters missing']);
            }
            break;
            
        case 'getProductId':
            if (isset($_GET['title']) && isset($_GET['brand']) && isset($_GET['ah']) && isset($_GET['volts'])) {
                $title = $_GET['title'];
                $brand = $_GET['brand'];
                $ah = $_GET['ah'];
                $volts = $_GET['volts'];
                $stmt = $conn->prepare("SELECT product_id FROM products WHERE title = ? AND type = ? AND ah = ? AND volts = ? LIMIT 1");
                $stmt->bind_param("ssss", $title, $brand, $ah, $volts);
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
            $stmt = $conn->prepare("SELECT product_id, title, type as brand, ah, volts, stock as quantity FROM products ORDER BY title, type, ah, volts");
            $stmt->execute();
            $result = $stmt->get_result();
            $stock = [];
            while ($row = $result->fetch_assoc()) {
                $stock[] = $row;
            }
            echo json_encode(['success' => true, 'stock' => $stock]);
            break;
            
        case 'getReleasedStock':
            $stmt = $conn->prepare("SELECT r.release_id, r.shop_name, p.title, p.type as brand, p.ah, p.volts, r.quantity, r.release_date 
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