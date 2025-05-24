<?php
// Include the database connection file
require_once 'conn.php'; // Make sure this uses mysqli connection

// Set the content type to application/json
header('Content-Type: application/json');

$type = isset($_GET['type']) ? $_GET['type'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

$sql = "SELECT expenses_id, reson, other_notes, price, date, type FROM expenses WHERE 1=1";

$params = [];
$types = '';

if (!empty($type)) {
    $sql .= " AND type = ?";
    $types .= 's';
    $params[] = $type;
}

if (!empty($date)) {
    $sql .= " AND date = ?";
    $types .= 's';
    $params[] = $date;
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Output JSON array
echo '[';
$first = true;
while ($row = $result->fetch_assoc()) { 
    if (!$first) echo ',';
    echo json_encode($row);
    $first = false;
}
echo ']';

$stmt->close();
$conn->close();
?>
