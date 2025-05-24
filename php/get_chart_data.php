<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once('conn.php');

// Get parameters from request
$range = isset($_GET['range']) ? $_GET['range'] : '7d';
$trend_range = isset($_GET['trend_range']) ? $_GET['trend_range'] : '30d';

// Function to calculate date range
function getDateRange($range) {
    $endDate = new DateTime();
    $startDate = new DateTime();
 
    switch ($range) {
        case '1d': $startDate->modify('-1 day'); break;
        case '7d': $startDate->modify('-7 days'); break;
        case '30d': $startDate->modify('-30 days'); break;
        case '90d': $startDate->modify('-90 days'); break;
        case '180d': $startDate->modify('-180 days'); break;
        case '365d': $startDate->modify('-365 days'); break;
        case '730d': $startDate->modify('-730 days'); break;
        case '1095d': $startDate->modify('-1095 days'); break;
        case '1460d': $startDate->modify('-1460 days'); break;
        default: $startDate->modify('-7 days'); break;
    }

    return [
        'start' => $startDate->format('Y-m-d 00:00:00'),
        'end' => $endDate->format('Y-m-d 23:59:59')
    ];
}

try {
    // Initialize response array
    $response = [
        'status' => 'success',
        'weeklyPerformanceData' => [
            'labels' => [],
            'sales' => [],
            'expenses' => []
        ],
        'businessTrendData' => [
            'labels' => [],
            'progress' => [], // Represents sales - expenses
            'sales' => []    // Represents sales only
        ]
    ];

    // Weekly Performance Data
    $weeklyRange = getDateRange($range);
    
    // Query for sales data
    $sql_weekly_sales = "SELECT 
                            DATE(order_date) AS sale_date, 
                            SUM(total) AS total_sales
                        FROM orders 
                        WHERE order_date BETWEEN ? AND ? 
                        GROUP BY DATE(order_date) 
                        ORDER BY DATE(order_date) ASC";

    $stmt_weekly_sales = $conn->prepare($sql_weekly_sales);
    if (!$stmt_weekly_sales) {
        throw new Exception("Weekly sales data prepare failed: " . $conn->error);
    }
    
    $stmt_weekly_sales->bind_param("ss", $weeklyRange['start'], $weeklyRange['end']);
    if (!$stmt_weekly_sales->execute()) {
        throw new Exception("Weekly sales data execute failed: " . $stmt_weekly_sales->error);
    }
    
    $result_weekly_sales = $stmt_weekly_sales->get_result();
    
    // Query for expenses data
    $sql_weekly_expenses = "SELECT 
                                DATE(date) AS expense_date, 
                                SUM(price) AS total_expenses
                            FROM expenses 
                            WHERE date BETWEEN ? AND ? 
                            GROUP BY DATE(date) 
                            ORDER BY DATE(date) ASC";

    $stmt_weekly_expenses = $conn->prepare($sql_weekly_expenses);
    if (!$stmt_weekly_expenses) {
        throw new Exception("Weekly expenses data prepare failed: " . $conn->error);
    }
    
    $stmt_weekly_expenses->bind_param("ss", $weeklyRange['start'], $weeklyRange['end']);
    if (!$stmt_weekly_expenses->execute()) {
        throw new Exception("Weekly expenses data execute failed: " . $stmt_weekly_expenses->error);
    }
    
    $result_weekly_expenses = $stmt_weekly_expenses->get_result();
    
    // Process sales data
    $salesData = [];
    if ($result_weekly_sales->num_rows > 0) {
        while ($row = $result_weekly_sales->fetch_assoc()) {
            $salesData[$row['sale_date']] = [
                'sales' => (float)$row['total_sales'],
                'expenses' => 0 // Initialize expenses as 0
            ];
            $response['weeklyPerformanceData']['labels'][] = $row['sale_date'];
        }
    }
    
    // Process expenses data
    if ($result_weekly_expenses->num_rows > 0) {
        while ($row = $result_weekly_expenses->fetch_assoc()) {
            if (isset($salesData[$row['expense_date']])) {
                $salesData[$row['expense_date']]['expenses'] = (float)$row['total_expenses'];
            }
        }
    }
    
    // Populate response with combined data
    foreach ($salesData as $date => $data) {
        $response['weeklyPerformanceData']['sales'][] = $data['sales'];
        $response['weeklyPerformanceData']['expenses'][] = $data['expenses'];
    }
    
    $stmt_weekly_sales->close();
    $stmt_weekly_expenses->close();

    // Business Trend Data
    $trendRange = getDateRange($trend_range);
    
    // Determine grouping interval based on range
    if (in_array($trend_range, ['1d', '7d'])) {
        $sql_trend_sales = "SELECT 
                                DATE(order_date) AS period, 
                                SUM(total) AS total_sales
                            FROM orders 
                            WHERE order_date BETWEEN ? AND ? 
                            GROUP BY DATE(order_date) 
                            ORDER BY DATE(order_date) ASC";
                            
        $sql_trend_expenses = "SELECT 
                                    DATE(date) AS period, 
                                    SUM(price) AS total_expenses
                                FROM expenses 
                                WHERE date BETWEEN ? AND ? 
                                GROUP BY DATE(date) 
                                ORDER BY DATE(date) ASC";
    } elseif (in_array($trend_range, ['90d', '180d'])) {
        $sql_trend_sales = "SELECT 
                                CONCAT(YEAR(order_date), '-', WEEK(order_date)) AS period, 
                                SUM(total) AS total_sales
                            FROM orders 
                            WHERE order_date BETWEEN ? AND ? 
                            GROUP BY YEAR(order_date), WEEK(order_date) 
                            ORDER BY YEAR(order_date), WEEK(order_date) ASC";
                            
        $sql_trend_expenses = "SELECT 
                                    CONCAT(YEAR(date), '-', WEEK(date)) AS period, 
                                    SUM(price) AS total_expenses
                                FROM expenses 
                                WHERE date BETWEEN ? AND ? 
                                GROUP BY YEAR(date), WEEK(date) 
                                ORDER BY YEAR(date), WEEK(date) ASC";
    } else {
        $sql_trend_sales = "SELECT 
                                DATE_FORMAT(order_date, '%Y-%m') AS period, 
                                SUM(total) AS total_sales
                            FROM orders 
                            WHERE order_date BETWEEN ? AND ? 
                            GROUP BY DATE_FORMAT(order_date, '%Y-%m') 
                            ORDER BY DATE_FORMAT(order_date, '%Y-%m') ASC";
                            
        $sql_trend_expenses = "SELECT 
                                    DATE_FORMAT(date, '%Y-%m') AS period, 
                                    SUM(price) AS total_expenses
                                FROM expenses 
                                WHERE date BETWEEN ? AND ? 
                                GROUP BY DATE_FORMAT(date, '%Y-%m') 
                                ORDER BY DATE_FORMAT(date, '%Y-%m') ASC";
    }

    // Process sales data for trend
    $stmt_trend_sales = $conn->prepare($sql_trend_sales);
    if (!$stmt_trend_sales) {
        throw new Exception("Trend sales data prepare failed: " . $conn->error);
    }
    
    $stmt_trend_sales->bind_param("ss", $trendRange['start'], $trendRange['end']);
    if (!$stmt_trend_sales->execute()) {
        throw new Exception("Trend sales data execute failed: " . $stmt_trend_sales->error);
    }
    
    $result_trend_sales = $stmt_trend_sales->get_result();
    
    // Process expenses data for trend
    $stmt_trend_expenses = $conn->prepare($sql_trend_expenses);
    if (!$stmt_trend_expenses) {
        throw new Exception("Trend expenses data prepare failed: " . $conn->error);
    }
    
    $stmt_trend_expenses->bind_param("ss", $trendRange['start'], $trendRange['end']);
    if (!$stmt_trend_expenses->execute()) {
        throw new Exception("Trend expenses data execute failed: " . $stmt_trend_expenses->error);
    }
    
    $result_trend_expenses = $stmt_trend_expenses->get_result();
    
    // Process sales data
    $trendData = [];
    if ($result_trend_sales->num_rows > 0) {
        while ($row = $result_trend_sales->fetch_assoc()) {
            $trendData[$row['period']] = [
                'sales' => (float)$row['total_sales'],
                'expenses' => 0 // Initialize expenses as 0
            ];
            $response['businessTrendData']['labels'][] = $row['period'];
        }
    }
    
    // Process expenses data
    if ($result_trend_expenses->num_rows > 0) {
        while ($row = $result_trend_expenses->fetch_assoc()) {
            if (isset($trendData[$row['period']])) {
                $trendData[$row['period']]['expenses'] = (float)$row['total_expenses'];
            }
        }
    }
    
    // Populate response with combined data
    foreach ($trendData as $period => $data) {
        $response['businessTrendData']['progress'][] = $data['sales'] - $data['expenses'];
        $response['businessTrendData']['sales'][] = $data['sales'];
    }
    
    $stmt_trend_sales->close();
    $stmt_trend_expenses->close();

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>