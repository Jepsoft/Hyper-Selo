<?php

include_once('conn.php');
header('Content-Type: application/json');


if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Query to get ingredients from the kitchen table
$sql = "SELECT id, ingredient as name,volume as quantity,msh as unit FROM kitchen ORDER BY id ASC";
$result = $conn->query($sql);

$ingredients = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ingredients[] = $row;
    }
}

echo json_encode($ingredients);

$conn->close();
