<?php
header("Content-Type: application/json");
include 'conn.php';

// Step 0: Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['order_details']) || !is_array($input['order_details'])) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

$response = [];

foreach ($input['order_details'] as $item) {
    $product_id = intval($item['product_id']);
    $quantity = intval($item['quantity']);

    if ($product_id <= 0 || $quantity <= 0) {
        $response[] = ["product_id" => $product_id, "status" => "error", "message" => "Invalid product or quantity"];
        continue;
    }

    // Step 1: Get kitchen_id and amount from recipe table
    $stmt = $conn->prepare("SELECT ingredient_id as kichen_id, amount FROM recipe WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $response[] = ["product_id" => $product_id, "status" => "error", "message" => "No recipe found"];
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        $kichen_id = $row['kichen_id'];
        $amount = $row['amount'] * $quantity;

        // Step 2: Get current volume from kitchen table
        $stmt2 = $conn->prepare("SELECT volume FROM kitchen WHERE id = ?");
        $stmt2->bind_param("i", $kichen_id);
        $stmt2->execute();
        $volume_result = $stmt2->get_result();

        if ($volume_result->num_rows === 0) {
            $response[] = ["product_id" => $product_id, "kichen_id" => $kichen_id, "status" => "error", "message" => "Kitchen not found"];
            continue;
        }

        $volume_row = $volume_result->fetch_assoc();
        $current_volume = $volume_row['volume'];
        $new_volume = $current_volume - $amount;

        if ($new_volume < 0) {
            $response[] = ["product_id" => $product_id, "kichen_id" => $kichen_id, "status" => "error", "message" => "Not enough volume"];
            continue;
        }

        // Step 3: Update new volume
        $stmt3 = $conn->prepare("UPDATE kitchen SET volume = ? WHERE id = ?");
        $stmt3->bind_param("di", $new_volume, $kichen_id);

        if ($stmt3->execute()) {
            $response[] = [
                "product_id" => $product_id,
                "kichen_id" => $kichen_id,
                "used_amount" => $amount,
                "new_volume" => $new_volume,
                "status" => "success"
            ];
        } else {
            $response[] = ["product_id" => $product_id, "kichen_id" => $kichen_id, "status" => "error", "message" => "Update failed"];
        }

        $stmt2->close();
        $stmt3->close();
    }

    $stmt->close();
}

$conn->close();
echo json_encode(["status" => "done", "results" => $response]);
?>
