<?php
include_once('conn.php');

$filter = $_GET['filter'] ?? 'today';
$query = "";

switch ($filter) {
    case 'today':
        $query = "SELECT SUM(CAST(total AS DECIMAL(10,2))) AS total_sum
                    FROM orders
                    WHERE DATE(STR_TO_DATE(order_date, '%Y-%m-%d %H:%i:%s')) = CURDATE() - INTERVAL 0 DAY";
        break;

    case 'yesterday':
        $query = "SELECT SUM(CAST(total AS DECIMAL(10,2))) AS total_sum
                    FROM orders
                    WHERE DATE(STR_TO_DATE(order_date, '%Y-%m-%d %H:%i:%s')) = CURDATE() - INTERVAL 1 DAY";
        break;

    case 'week':
        $query = "SELECT SUM(CAST(total AS DECIMAL(10,2))) AS total_sum
                    FROM orders
                    WHERE DATE(STR_TO_DATE(order_date, '%Y-%m-%d %H:%i:%s')) >= CURDATE() - INTERVAL 6 DAY";
        break;

    case 'month':
        $query = "SELECT SUM(CAST(total AS DECIMAL(10,2))) AS total_sum
                    FROM orders
                    WHERE MONTH(STR_TO_DATE(order_date, '%Y-%m-%d %H:%i:%s')) = MONTH(CURDATE())
                    AND YEAR(STR_TO_DATE(order_date, '%Y-%m-%d %H:%i:%s')) = YEAR(CURDATE())";
        break;

    case 'year':
        $query = "SELECT SUM(CAST(total AS DECIMAL(10,2))) AS total_sum
                    FROM orders
                    WHERE YEAR(STR_TO_DATE(order_date, '%Y-%m-%d %H:%i:%s')) = YEAR(CURDATE())";
        break;

    default:
        $query = "SELECT SUM(CAST(total AS DECIMAL(10,2))) AS total_sum FROM orders";
}

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$total = $row['total_sum'] ?? 0;

echo number_format($total, 2);
?>