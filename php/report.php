<?php
include_once('conn.php');

// Set default dates if not provided
$fromm = $_GET['from'] ;
$too = $_GET['to'];
$range = $_GET['range'];

// Helper function to fetch data
function fetchData($conn, $query) {
    $result = $conn->query($query);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Adjust date range based on the 'range' parameter
switch ($range) {
    case 'last_7_days':
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = date('Y-m-d');
        break;
    case 'this_month':
        $from = date('Y-m-01');
        $to = date('Y-m-d');
        break;
    case 'last_3_months':
        $from = date('Y-m-d', strtotime('-2 months', strtotime(date('Y-m-01'))));
        $to = date('Y-m-d');
        break;
    case 'last_year':
        $from = date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d'))));
        $to = date('Y-m-d');
        break;
    case 'last_2_years':
        $from = date('Y-m-d', strtotime('-2 years', strtotime(date('Y-m-d'))));
        $to = date('Y-m-d');
        break;
    case 'last_3_years':
        $from = date('Y-m-d', strtotime('-3 years', strtotime(date('Y-m-d'))));
        $to = date('Y-m-d');
        break;
    case 'last_4_years':
        $from = date('Y-m-d', strtotime('-4 years', strtotime(date('Y-m-d'))));
        $to = date('Y-m-d');
        break;
    // Default to the provided 'from' and 'to' dates if 'custom' or invalid range
    default:
        // If a custom range is provided via from/to, use that. Otherwise, default to last 7 days.
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = date('Y-m-d');
        break;
}

$salesData = fetchData($conn, "
SELECT
    DATE(order_date) AS sale_date,
    SUM(total) AS total_daily_sales
FROM
    orders
WHERE
    order_date BETWEEN '$fromm' AND '$too'
GROUP BY
    DATE(order_date)
ORDER BY
    DATE(order_date);
");

$dates = array_column($salesData, 'sale_date');
$salesAmounts = array_map(fn($item) => (float)$item['total_daily_sales'], $salesData);
$totalSales = array_sum($salesAmounts);

$profitData = fetchData($conn, "
    SELECT
        daily_profit.sale_date,
        daily_profit.total_item_profit AS total_daily_profit
    FROM (
        SELECT
            DATE(o.order_date) AS sale_date,
            SUM(
                CASE
                    -- If margin > 0, calculate profit with margin
                    WHEN oi.margin > 0 THEN
                        (oi.price - (oi.cost + oi.applied_discount+oi.margin)) * (oi.quantity-oi.returned_quantity)
                    -- If margin = 0, calculate profit normally
                    WHEN oi.margin = 0 AND oi.returned_quantity = 0 THEN
                        (oi.price - oi.applied_discount - oi.cost) * (oi.quantity-oi.returned_quantity)
                    ELSE 0
                END
            ) AS total_item_profit
        FROM
            order_items oi
        JOIN
            orders o ON oi.order_id = o.order_id
        WHERE
            o.order_date BETWEEN '$fromm' AND '$too'
        GROUP BY
            DATE(o.order_date)
    ) AS daily_profit
    LEFT JOIN (
        SELECT
            DATE(order_date) AS sale_date,
            SUM(discount) AS total_discount
        FROM
            orders
        WHERE
            order_date BETWEEN '$fromm' AND '$too'
        GROUP BY
            DATE(order_date)
    ) AS daily_discount
    ON daily_profit.sale_date = daily_discount.sale_date
    ORDER BY
        daily_profit.sale_date;
");


$profitAmountsBeforeExpenses = array_map(fn($item) => (float)$item['total_daily_profit'], $profitData);
$totalProfitBeforeExpenses = array_sum($profitAmountsBeforeExpenses);

// Fetch total expenses for the selected date range
$expensesData = fetchData($conn, "
    SELECT
        DATE(date) AS expense_date,
        SUM(price) AS total_daily_expenses
    FROM
        expenses
    WHERE
        date BETWEEN '$fromm' AND '$too' AND type = 'cash withdraw'
    GROUP BY
        DATE(date)
    ORDER BY
        DATE(date);
");


$expenseAmounts = array_map(fn($item) => (float)$item['total_daily_expenses'], $expensesData);
$totalExpenses = array_sum($expenseAmounts);

// Calculate profit after deducting expenses
$totalProfit = $totalExpenses;

// Merge profit and expense data by date
$mergedProfitData = [];
$profitIndex = 0;
$expenseIndex = 0;

while ($profitIndex < count($profitData) || $expenseIndex < count($expensesData)) {
    $profitDate = $profitIndex < count($profitData) ? $profitData[$profitIndex]['sale_date'] : null;
    $expenseDate = $expenseIndex < count($expensesData) ? $expensesData[$expenseIndex]['expense_date'] : null;
    $mergedDate = null;
    $dailyProfit = 0;
    $dailyExpense = 0;

    if ($profitDate === $expenseDate && $profitDate !== null) {
        $mergedDate = $profitDate;
        $dailyProfit = (float)$profitData[$profitIndex]['total_daily_profit'];
        $dailyExpense = (float)$expensesData[$expenseIndex]['total_daily_expenses'];
        $profitIndex++;
        $expenseIndex++;
    } elseif ($profitDate !== null && ($expenseDate === null || $profitDate < $expenseDate)) {
        $mergedDate = $profitDate;
        $dailyProfit = (float)$profitData[$profitIndex]['total_daily_profit'];
        $profitIndex++;
    } elseif ($expenseDate !== null) {
        $mergedDate = $expenseDate;
        $dailyExpense = (float)$expensesData[$expenseIndex]['total_daily_expenses'];
        $expenseIndex++;
    }

    if ($mergedDate !== null) {
        $mergedProfitData[] = ['date' => $mergedDate, 'profit' => $dailyProfit - $dailyExpense];
    }
}

// Prepare profit amounts after expenses for the chart
$profitAmountsAfterExpenses = array_map(fn($item) => (float)$item['profit'], $mergedProfitData);
$profitDates = array_column($mergedProfitData, 'date');


$orderCountResult = $conn->query("
    SELECT COUNT(DISTINCT oi.order_id) AS total_orders
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN '$fromm' AND '$too'
");
$orderCount = $orderCountResult ? ($orderCountResult->fetch_assoc()['total_orders'] ?? 0) : 0;

$dailyOrdersData = fetchData($conn, "
    SELECT DATE(o.order_date) AS order_date,
            COUNT(DISTINCT oi.order_id) AS daily_order_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN '$fromm' AND '$too'
    GROUP BY DATE(o.order_date)
    ORDER BY DATE(o.order_date)
");

$dailyOrderCounts = array_column($dailyOrdersData, 'daily_order_count');

$cashierBalanceResult = $conn->query("
    SELECT total_sales AS total_cashier_balance
    FROM cashier_balance
");

$cashierBalance = $cashierBalanceResult ? ($cashierBalanceResult->fetch_assoc()['total_cashier_balance'] ?? 0) : 0;

$conn->close();

$dashboardData = [
    'from' => $from,
    'to' => $to,
    'dates' => $dates,
    'salesAmounts' => $salesAmounts,
    'totalSales' => number_format($totalSales, 2),
    'profitDates' => $profitDates,
    'profitAmounts' => $profitAmountsAfterExpenses,
    'totalProfit' => number_format($totalProfit, 2),
    'orderCount' => $orderCount,
    'dailyOrderCounts' => $dailyOrderCounts,
    'cashierBalance' => number_format($cashierBalance, 2),
    'salesTrend' => array_map(function($date, $amount) {
        return ['date' => $date, 'amount' => $amount];
    }, $dates, $salesAmounts),
    'orderVolume' => array_map(function($date, $count) {
        return ['date' => $date, 'count' => $count];
    }, $dates, $dailyOrderCounts),
];

header('Content-Type: application/json');
echo json_encode($dashboardData);
?>