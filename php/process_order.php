<?php
require_once('conn.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);

    // Debug log
    file_put_contents("debug_order.log", $json_data . PHP_EOL, FILE_APPEND);

    // Validate required fields
    if (!isset($data['username'], $data['order_details'], $data['total'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        exit();
    }

    // Sanitize input
    $customer_phone = $data['customer_phone'] ?? null;
    $customer_email = isset($data['customer_email']) ? (trim($data['customer_email'])) : null;
    $subtotal = floatval($data['subtotal'] ?? 0);
    $oldbdiscount=floatval($data['oldbdiscount'] ?? 0);
    $discount = floatval($data['discount'] ?? 0);
    $discount = $oldbdiscount + $discount;
    $happy_discount = floatval($data['additional'] ?? 0);
    $total = floatval($data['total'] ?? 0);
    $cash_received = floatval($data['cash_received'] ?? 0);
    $payment_method = trim($data['payment_method'] ?? 'cash');
    $username = trim($data['username']);

    $conn->begin_transaction();

    try {
        // Get or insert customer
        $customer_id = null;
        if ($customer_phone || $customer_email) {
            $stmt = $conn->prepare("SELECT c_id FROM coustomers WHERE phone = ? OR email = ?");
            $stmt->bind_param("ss", $customer_phone, $customer_email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $customer_id = $result->fetch_assoc()['c_id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO coustomers (phone, email) VALUES (?, ?)");
                $stmt->bind_param("ss", $customer_phone, $customer_email);
                $stmt->execute();
                $customer_id = $conn->insert_id;
            }
            $stmt->close();
        }

        // Get user ID
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_name = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) throw new Exception("Invalid username: $username");
        $user_id = $result->fetch_assoc()['user_id'];
        $stmt->close();

        // Insert order
        $stmt_insert_order = $conn->prepare("INSERT INTO orders (subtotal, discount, happy_discount, user_id, customer_id, total, payment_method, cash_received) VALUES (?, ?, ?, ?, ?, ?, ?,?)");
        $stmt_insert_order->bind_param("dddiidsd", $subtotal, $discount, $happy_discount, $user_id, $customer_id, $total, $payment_method, $cash_received);
        $stmt_insert_order->execute();
        $order_id = $conn->insert_id;
        $stmt_insert_order->close();

        // Update cashier balance
        if($payment_method != 'card'){
        $stmt = $conn->prepare("UPDATE cashier_balance SET total_sales = total_sales + ? WHERE id = 1");
        $stmt->bind_param("d", $total);
        $stmt->execute();
        $stmt->close();
        }
        // Insert order items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id,title, quantity, price, applied_discount,cost,margin) VALUES (?, ? ,? , ?, ?, ?,?,?)");

        foreach ($data['order_details'] as $item) {
            $product_name = trim($item['product_id']);
          $pn = trim($item['title']);
            $stmt_prod = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
            $stmt_prod->bind_param("s", $product_name);
            $stmt_prod->execute();
            $res_prod = $stmt_prod->get_result();

            if ($res_prod->num_rows === 0) {
                throw new Exception("Product not found: " . print_r($item, true));
            }

            $product_id = $item['product_id'];

            $stmt_prod->close();

            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            $cost = floatval($item['cost']);
            $applied_discount = floatval($item['applied_discount'] ?? 0);
            $valuess=( $discount*100)/$subtotal;
            $newhf=($price*$valuess)/100;

            $stmt->bind_param("iisidddd", $order_id,$product_id,$pn, $quantity, $price, $applied_discount,$cost,$newhf);
            $stmt->execute();
        }

        $stmt->close();
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Order placed successfully', 'order_id' => $order_id]);  
    } catch (Exception $e) {
        $conn->rollback();

        // Log the error message to a file
        $error_message = "Error: " . $e->getMessage() . " - Date: " . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents("error_log.txt", $error_message, FILE_APPEND);

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    } finally {
        $conn->close();
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
