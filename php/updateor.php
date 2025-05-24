<?php
include 'conn.php'; // Assumes $conn is your mysqli connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? '';
    $payid = $_POST['payid'] ?? '';

    if (empty($order_id) || empty($payid)) {
        echo "Missing required parameters.";
        exit;
    }

    // Validate payment method
    if ($payid !== 'cash' && $payid !== 'card') {
        echo "Invalid payment method.";
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get total amount of the order
        $stmt = $conn->prepare("SELECT total FROM orders WHERE order_id = ?");
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $stmt->bind_result($total_amount);

        if ($stmt->fetch()) {
            $stmt->close();

            // Update order: set paid_by
            $stmt2 = $conn->prepare("UPDATE orders SET payment_method = ? WHERE order_id = ?");
            $stmt2->bind_param("ss", $payid, $order_id);
            $stmt2->execute();
            $stmt2->close();

            // If paid by cash, update cashier balance
            if ($payid === 'cash') {
                // Assuming there's only one cashier row in cashier table (id = 1)
                $stmt3 = $conn->prepare("UPDATE cashier_balance SET total_sales = total_sales + ? WHERE id = 1");
                $stmt3->bind_param("d", $total_amount);
                $stmt3->execute();
                $stmt3->close();
            }

            $conn->commit();
            echo "Payment updated successfully.";
        } else {
            $stmt->close();
            $conn->rollback();
            echo "Order not found.";
        }

    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}
?>
