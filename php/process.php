<?php
include_once('conn.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set header to return JSON
header('Content-Type: application/json');

// Handle get_chart_data.php
if (strpos($_SERVER['REQUEST_URI'], 'get_chart_data.php') !== false) {
    if (isset($_GET['range'])) {
        $range = $_GET['range'];
        $data = getWeeklyPerformanceData($conn, $range);
        echo json_encode(['weeklyPerformanceData' => $data]);
    } elseif (isset($_GET['trend_range']) && isset($_GET['calculate_profit'])) {
        $trendRange = $_GET['trend_range'];
        $data = getBusinessTrendData($conn, $trendRange);
        echo json_encode(['businessTrendData' => $data]);
    } else {
        echo json_encode(['error' => 'Invalid parameters']);
    }
    exit;
}

// Handle get_orders.php
if (strpos($_SERVER['REQUEST_URI'], 'get_orders.php') !== false) {
    $orders = getOrders($conn);
    echo json_encode($orders);
    exit;
}

// Handle get_order_details.php
if (strpos($_SERVER['REQUEST_URI'], 'get_order_details.php') !== false && isset($_GET['order_id'])) {
    $orderId = $_GET['order_id'];
    $order = getOrderDetails($conn, $orderId);
    echo json_encode($order);
    exit;
}

// Function to get weekly performance data
function getWeeklyPerformanceData($conn, $range) {
    $dateFilter = getDateFilter($range);
    $sql = "SELECT DATE(order_date) as date, SUM(total) as sales, 0 as expenses FROM orders WHERE $dateFilter GROUP BY DATE(order_date) ORDER BY DATE(order_date)";
    $result = $conn->query($sql);
    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data['labels'][] = $row['date'];
            $data['sales'][] = (float)$row['sales'];
            $data['expenses'][] = (float)$row['expenses']; // You might need to fetch expenses from another table
        }
    }
    return $data;
}

// Function to get business trend data
function getBusinessTrendData($conn, $range) {
    $dateFilter = getDateFilter($range);
    $sql = "SELECT DATE(orders.order_date) as period, SUM(orders.total) as sales, COALESCE(SUM(orders.discount), 0) as expenses FROM orders LEFT JOIN expenses ON DATE(orders.order_date) = DATE(orders.order_date) WHERE $dateFilter GROUP BY DATE(orders.order_date) ORDER BY DATE(orders.order_date)";
    $result = $conn->query($sql);
    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'period' => $row['period'],
                'sales' => (float)$row['sales'],
                'expenses' => (float)$row['expenses']
            ];
        }
    }
    return $data;
}

// Function to get orders
function getOrders($conn) {
    $sql = "SELECT order_id, order_date, total FROM orders ORDER BY order_date DESC LIMIT 100";
    $result = $conn->query($sql);
    $orders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    return $orders;
}

// Function to get order details
function getOrderDetails($conn, $orderId) {
    $sql = "SELECT orders.*, order_items.* FROM orders LEFT JOIN order_items ON orders.order_id = order_items.order_id WHERE orders.order_id = $orderId";
    $result = $conn->query($sql);
    $order = null;
    $items = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!$order) {
                $order = $row;
            }
            if ($row['product_id']) {
                $items[] = $row;
            }
        }
        if ($order) {
            $order['items'] = $items;
        }
    }
    return $order;
}

// Helper function to get date filter based on range
function getDateFilter($range) {
    $today = date('Y-m-d');
    switch ($range) {
        case '1d':
            return "DATE(order_date) = '$today'";
        case '7d':
            return "order_date >= DATE_SUB('$today', INTERVAL 7 DAY)";
        case '30d':
            return "order_date >= DATE_SUB('$today', INTERVAL 30 DAY)";
        case '90d':
            return "order_date >= DATE_SUB('$today', INTERVAL 90 DAY)";
        case '180d':
            return "order_date >= DATE_SUB('$today', INTERVAL 180 DAY)";
        case '365d':
            return "order_date >= DATE_SUB('$today', INTERVAL 365 DAY)";
        case '730d':
            return "order_date >= DATE_SUB('$today', INTERVAL 730 DAY)";
        case '1095d':
            return "order_date >= DATE_SUB('$today', INTERVAL 1095 DAY)";
        case '1460d':
            return "order_date >= DATE_SUB('$today', INTERVAL 1460 DAY)";
        default:
            return "order_date >= DATE_SUB('$today', INTERVAL 7 DAY)";
    }
}

$conn->close();
?>