<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // In production, restrict this to your frontend's domain

include_once('conn.php'); // Ensure this path is correct and conn.php handles connection securely

// Check for database connection errors
if ($conn->connect_error) {
    // Send a structured error response
    echo json_encode(["error" => "Database connection failed.", "details" => $conn->connect_error]);
    exit;
}

// Get filters from query parameters
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_id_param = isset($_GET['id']) ? $_GET['id'] : ''; // Get as string first

// Build SQL with optional filters
$sql = "SELECT * FROM orders"; // Using SELECT * as per your structure
$conditions = [];

if (!empty($filter_date)) {
    $conditions[] = "order_date = '" . $conn->real_escape_string($filter_date) . "'";
}

if (!empty($filter_id_param)) {
    // Assuming order_id in the database is numeric if intval is used.
    $conditions[] = "order_id = " . intval($filter_id_param);
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY order_id DESC"; // Consider ordering by order_date as well if relevant

$result = $conn->query($sql);
$orders_array = []; // Renamed for clarity

if (!$result) {
    // Send a structured error response if the main query fails
    echo json_encode(["error" => "Failed to retrieve orders.", "details" => $conn->error, "debug_sql" => $sql]);
    $conn->close();
    exit;
}

while ($order_row = $result->fetch_assoc()) {
    // Ensure order_id from the database is treated as an integer for the sub-query
    // This adds a layer of type safety for the subquery.
    $current_order_id = intval($order_row['order_id']);
    $order_row['items'] = [];

    // Fetch items for this order
    // Using intval for $current_order_id makes the query safer if $order_row['order_id'] was unexpectedly not an integer.
    $item_sql = "SELECT * FROM order_items WHERE order_id = " . $current_order_id;
    $items_result = $conn->query($item_sql);
    
    if ($items_result) {
        while ($item = $items_result->fetch_assoc()) {
            $order_row['items'][] = [
                "product_id" => isset($item['product_id']) ? $item['product_id'] : null,
                "product_name" => !empty($item['title']) ? $item['title'] : "Unnamed Product", // Assumes 'title' column in order_items
                "quantity" => isset($item['quantity']) ? intval($item['quantity']) : 0,
                "price" => isset($item['price']) ? floatval($item['price']) : 0.0,
            ];
        }
    } else {
        // Optional: Log this error to a server log file for debugging
        error_log("Item query failed for order_id " . $current_order_id . ": " . $conn->error . " SQL: " . $item_sql);
        // Items list will remain empty for this order if query fails
    }

    // Calculate total_amount based on items.
    // This overwrites any 'total_amount' that might have been fetched by "SELECT * FROM orders".
    // The frontend JavaScript uses this value as the 'serverSubtotal'.
    $calculated_item_total = 0;
    foreach ($order_row['items'] as $order_item_detail) {
        $calculated_item_total += floatval($order_item_detail['price']) * intval($order_item_detail['quantity']);
    }
    $order_row['total_amount'] = $order_row['total'] ?? 0.0; 
    $order_row['discount'] = $order_row['happy_discount'] ?? 0.0; // Default to 0 if not set
    // The original $order_row['discount'] and $order_row['happy_discount'] (if they exist from SELECT *) are preserved.

    $orders_array[] = $order_row;
}

echo json_encode($orders_array);
$conn->close();
?>