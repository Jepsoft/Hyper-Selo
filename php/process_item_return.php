<?php
include_once('conn.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit();
}

$required_fields = ['order_id', 'product_id', 'quantity', 'order_item_id'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

$order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
$product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
$quantity = (int)$_POST['quantity'];
$order_item_id = mysqli_real_escape_string($conn, $_POST['order_item_id']);

try {
    $conn->begin_transaction();

    // 1. Retrieve details of the order item and the associated order
    $check_sql = "SELECT oi.quantity AS ordered_quantity, oi.price AS item_price, oi.applied_discount AS item_happy_discount, oi.margin AS margin,
                            o.discount AS order_manual_discount, o.total AS original_order_total, o.subtotal As subtotal
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE oi.id = ? AND oi.order_id = ? AND oi.product_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iii", $order_item_id, $order_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $item = $check_result->fetch_assoc();
    $check_stmt->close();

    if (!$item) {
        throw new Exception("Order item not found.");
    }

    $ordered_quantity = (int)$item['ordered_quantity'];
    $item_price = (float)$item['item_price'];
    $mnargin = (float)$item['margin'];
    $item_happy_discount = (float)$item['item_happy_discount'];
    $order_manual_discount = (float)$item['order_manual_discount'];
    $original_order_total = (float)$item['original_order_total'];
    $subtotal = (float)$item['subtotal'];

    if ($quantity > $ordered_quantity) {
        throw new Exception("Return quantity exceeds ordered quantity.");
    }

    $original_return_value = $quantity * $item_price;
    $returned_happy_discount = $quantity * $item_happy_discount;

    $return_value_after_happy_discount = $original_return_value - $returned_happy_discount;

    // 2. Get the total value of all *remaining* items and their original quantities
    $get_order_items_sql = "SELECT id, quantity, COALESCE(returned_quantity, 0) AS returned_quantity FROM order_items WHERE order_id = ?";
    $get_order_items_stmt = $conn->prepare($get_order_items_sql);
    $get_order_items_stmt->bind_param("i", $order_id);
    $get_order_items_stmt->execute();
    $order_items_result = $get_order_items_stmt->get_result();
    $order_items_data = $order_items_result->fetch_all(MYSQLI_ASSOC);
    $get_order_items_stmt->close();

    $total_returned_quantity_for_order = 0;
    $total_original_quantity_for_order = 0;
    foreach ($order_items_data as $oi) {
        $returned_so_far = isset($oi['returned_quantity']) ? (int)$oi['returned_quantity'] : 0;
        $quantity_to_add = ($oi['id'] == $order_item_id) ? $quantity : 0;
        $total_returned_quantity_for_order += $returned_so_far + $quantity_to_add;
        $total_original_quantity_for_order += (int)$oi['quantity'];
    }

    // 3. Calculate the prorated manual discount
    $prorated_manual_discount = 0;
    $item_retun_amount = 0;
    if ($order_manual_discount > 0 && $original_order_total > 0) {
        $prorated_manual_discount =   ($order_manual_discount*100)/$subtotal;
       $item_retun_amount= ($item_price*$prorated_manual_discount)/100;

    }
    $distwo=$mnargin;
    $final_return_amount = round($return_value_after_happy_discount - $item_retun_amount, 2);

    // 4. Update the products table to increase the stock
    $update_stock_sql = "UPDATE products SET stock = stock + ? WHERE product_id = ?";
    $update_stock_stmt = $conn->prepare($update_stock_sql);
    $update_stock_stmt->bind_param("ii", $quantity, $product_id);
    $update_stock_stmt->execute();
    $update_stock_stmt->close();

    // 5. Update order_items to reflect the returned quantity
    $add_column_sql = "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS returned_quantity INT DEFAULT 0";
    $conn->query($add_column_sql);

    $update_item_sql = "UPDATE order_items
                        SET returned_quantity = returned_quantity + ?
                        WHERE id = ?";
    $update_item_stmt = $conn->prepare($update_item_sql);
    $update_item_stmt->bind_param("ii", $quantity, $order_item_id);
    $update_item_stmt->execute();
    $update_item_stmt->close();

    // 6 & 7. Update the order total and discount
    if ($total_returned_quantity_for_order >= $total_original_quantity_for_order) {
        // If all items are returned, set total and discount to exactly 0
        $update_order_sql = "UPDATE orders SET total = 0.00, discount = 0.00 WHERE order_id = ?";
        $update_order_stmt = $conn->prepare($update_order_sql);
        $update_order_stmt->bind_param("i", $order_id);
        $update_order_stmt->execute();
        $update_order_stmt->close();
        $final_return_amount = round($original_order_total, 2);
        $prorated_manual_discount = round($order_manual_discount, 2);
    } else {
        $new_order_total = round($original_order_total - $final_return_amount, 2);
        $update_order_sql = "UPDATE orders SET total = ? WHERE order_id = ?";
        $update_order_stmt = $conn->prepare($update_order_sql);
        $update_order_stmt->bind_param("di", $new_order_total, $order_id);
        $update_order_stmt->execute();
        $update_order_stmt->close();

        $new_order_discount = round($order_manual_discount - $prorated_manual_discount, 2);
        $update_order_discount_sql = "UPDATE orders SET happy_discount=happy_discount-? WHERE order_id = ?";
        $update_order_discount_stmt = $conn->prepare($update_order_discount_sql);
        $update_order_discount_stmt->bind_param("dd", $distwo, $order_id);
        $update_order_discount_stmt->execute();
        $update_order_discount_stmt->close();
    }

    // 8. Update the cashier balance
    $update_cashier_sql = "UPDATE cashier_balance SET total_sales = ROUND(total_sales - ?, 2) WHERE id = 1";
    $update_cashier_stmt = $conn->prepare($update_cashier_sql);
    $update_cashier_stmt->bind_param("d", $final_return_amount);
    $update_cashier_stmt->execute();
    $update_cashier_stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Return processed successfully with updated manual discount.',
        'return_amount' => $final_return_amount,
        'prorated_manual_discount' => $prorated_manual_discount,
        'remaining_order_total' => ($total_returned_quantity_for_order >= $total_original_quantity_for_order) ? '0.00' : round($original_order_total - $final_return_amount, 2),
        'updated_order_discount' => ($total_returned_quantity_for_order >= $total_original_quantity_for_order) ? '0.00' : round($order_manual_discount - $prorated_manual_discount, 2)
    ]);

} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>