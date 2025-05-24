<?php
// Replace with your DB credentials
include_once('conn.php');

if ($conn->connect_error) {
    echo json_encode(["total" => 0]);
    exit;
}

$filter = $_GET['filter'] ?? 'today';
$where = "WHERE payment_method = 'cash'"; // Base condition

switch ($filter) {
    case 'yesterday':
        $yesterday = date('Y-m-d', strtotime("-1 day"));
        $where .= " AND order_date = '$yesterday'";
        break;
    case 'today':
        $today = date('Y-m-d');
        $where .= " AND order_date = '$today'";
        break;
    case 'week':
        $start = date('Y-m-d', strtotime("last Sunday"));
        $end = date('Y-m-d');
        $where .= " AND order_date BETWEEN '$start' AND '$end'";
        break;
    case 'month':
        $month = date('Y-m');
        $where .= " AND order_date LIKE '$month%'";
        break;
    case 'year':
        $year = date('Y');
        $where .= " AND order_date LIKE '$year%'";
        break;
    // If no valid filter is provided, just use base condition
}

$sql = "SELECT SUM(total) AS total FROM orders $where";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$total = $row['total'] ?? 0;

echo json_encode(["total" => $total]);
?>
