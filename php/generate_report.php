<?php
require_once('conn.php'); // Make sure this path is correct
require_once('../vendor/autoload.php'); // Ensure this path is correct for TCPDF

// --- Error Reporting (Crucial for Debugging PDF Issues) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get date parameters with proper sanitization
$from_date_str = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to_date_str = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

$date_format = 'Y-m-d';
$from_obj = DateTime::createFromFormat($date_format, $from_date_str);
$to_obj = DateTime::createFromFormat($date_format, $to_date_str);

if (!$from_obj || $from_obj->format($date_format) !== $from_date_str) {
    die("Invalid 'from' date format. Please use YYYY-MM-DD.");
}
if (!$to_obj || $to_obj->format($date_format) !== $to_date_str) {
    die("Invalid 'to' date format. Please use YYYY-MM-DD.");
}

$from = $from_obj->format('Y-m-d');
$to = $to_obj->format('Y-m-d');

// --- Helper Functions ---

/**
 * Fetches summary data from the database.
 * Uses prepared statements to prevent SQL injection.
 *
 * @param mysqli $conn Database connection.
 * @param string $from Start date.
 * @param string $to End date.
 * @return array Summary data.
 */
function getSummaryData(mysqli $conn, string $from, string $to): array {
    $data = [
        'totalSales' => 0,
        'totalProfit' => 0,
        'orderCount' => 0,
        'cashierBalance' => 0,
        'totalExpenses' => 0,
    ];

    // Total Sales
    $stmt = $conn->prepare("SELECT SUM(total) as totalSales FROM orders WHERE order_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['totalSales'] = $row['totalSales'] ?? 0;
    $stmt->close();

    // Total Profit
    $stmt = $conn->prepare("SELECT SUM(oi.price * oi.quantity - oi.cost * oi.quantity) as totalProfit
                            FROM order_items oi
                            JOIN orders o ON oi.order_id = o.order_id
                            WHERE o.order_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['totalProfit'] = $row['totalProfit'] ?? 0;
    $stmt->close();

    // Order Count
    $stmt = $conn->prepare("SELECT COUNT(*) as orderCount FROM orders WHERE order_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['orderCount'] = $row['orderCount'] ?? 0;
    $stmt->close();

    // Cashier Balance: Kept original logic as per previous version.
    // Ensure this calculation aligns with your business definition of "Cashier Balance".
    $data['cashierBalance'] = $data['totalSales'] - $data['totalProfit'];

    // Total Expenses
    $stmt = $conn->prepare("SELECT SUM(price) as totalExpenses FROM expenses WHERE date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['totalExpenses'] = $row['totalExpenses'] ?? 0;
    $stmt->close();

    return $data;
}

/**
 * Fetches expenses data from the database.
 *
 * @param mysqli $conn Database connection.
 * @param string $from Start date.
 * @param string $to End date.
 * @return array Expenses data.
 */
function getExpensesData(mysqli $conn, string $from, string $to): array {
    $expenses = [];
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE date BETWEEN ? AND ? ORDER BY date DESC");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    $stmt->close();
    return $expenses;
}

/**
 * Fetches top selling products from the database.
 *
 * @param mysqli $conn Database connection.
 * @param string $from Start date.
 * @param string $to End date.
 * @param int $limit Maximum number of products to return.
 * @return array Top selling products data.
 */
function getTopProducts(mysqli $conn, string $from, string $to, int $limit = 10): array {
    $products = [];
    $stmt = $conn->prepare("SELECT
                                        oi.title,
                                        SUM(oi.quantity) as quantity,
                                        SUM(oi.price * oi.quantity) as revenue,
                                        SUM((oi.price - oi.cost) * oi.quantity) as profit
                                    FROM order_items oi
                                    JOIN orders o ON oi.order_id = o.order_id
                                    WHERE o.order_date BETWEEN ? AND ?
                                    GROUP BY oi.title
                                    ORDER BY revenue DESC
                                    LIMIT ?");
    $stmt->bind_param("ssi", $from, $to, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
    return $products;
}

/**
 * Fetches stock levels for kitchen ingredients from the database, ordered low to high.
 *
 * @param mysqli $conn Database connection.
 * @return array Stock level data.
 */
function getStockLevels(mysqli $conn): array {
    $stock = [];
    $query = "SELECT ingredient, volume, msh FROM kitchen ORDER BY volume ASC, ingredient"; // Order by volume ASC (low to high)
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stock[] = $row;
        }
    }
    return $stock;
}

/**
 * Fetches current product stock levels from the database, ordered by stock quantity (low to high).
 *
 * @param mysqli $conn Database connection.
 * @return array Product stock level data.
 */
function getProductStockLevels(mysqli $conn): array {
    $productStock = [];
    $query = "SELECT product_id, title, stock, type FROM products ORDER BY CAST(stock AS UNSIGNED) ASC, title ASC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $productStock[] = $row;
        }
    } else {
        // Log error if query fails
        error_log("Error fetching product stock levels: " . mysqli_error($conn));
    }
    return $productStock;
}


// --- !! IMPORTANT: Fetch data BEFORE generating HTML !! ---
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check conn.php.");
}

$summary = getSummaryData($conn, $from, $to);
$expenses = getExpensesData($conn, $from, $to);
$topProducts = getTopProducts($conn, $from, $to, 10);
$kitchenStockLevels = getStockLevels($conn); // Renamed for clarity and now ordered low to high
$productStockLevels = getProductStockLevels($conn); // Fetching new product stock data

// --- Custom PDF Class with Watermark and Footer ---
class MYPDF extends TCPDF {
    // Page footer
    public function Footer() {
        // --- Watermark ---
        $this->SetAlpha(0.15); // Set transparency for watermark
        $this->SetFont('helvetica', 'B', 60); // Watermark font
        $this->SetTextColor(150, 150, 150); // Watermark color (light grey)

        // Get the current page dimensions
        $pageWidth = $this->getPageWidth();
        $pageHeight = $this->getPageHeight();

        // Calculate position for watermark (centered)
        $watermarkText = 'Hyper Selo';
        $textWidth = $this->GetStringWidth($watermarkText, 'helvetica', 'B', 60);
        $x = ($pageWidth - $textWidth) / 2;
        $y = ($pageHeight / 2) - 10; // Adjust Y position as needed

        $this->StartTransform();
        $this->Rotate(-30, $x + ($textWidth/2), $y); // Rotate around center of text
        $this->Text($x, $y, $watermarkText, false, false, true, 0, 0, '', false, '', 0, false, 'C', 'C');
        $this->StopTransform();
        $this->SetAlpha(1); // Reset transparency
        $this->SetTextColor(0,0,0); // Reset text color to black for footer text

        // --- Page Number and Generation Date ---
        $this->SetY(-15); // Position at 15mm from bottom
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages() . ' | Generated on: ' . date('Y-m-d H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// --- PDF Generation ---
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Hyper Selo Sales Dashboard');
$pdf->SetAuthor('Hyper Selo Sales System');
$pdf->SetTitle('Sales Report (' . date('M j, Y', strtotime($from)) . ' - ' . date('M j, Y', strtotime($to)) . ')');
$pdf->SetSubject('Sales Report');
$pdf->SetKeywords('Sales, Report, Summary, Expenses, Products, Stock, Hyper Selo');

$pdf->setPrintHeader(false); // No default header
// Footer is handled by our custom MYPDF class

// Set margins (Left, Top, Right)
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(15); // Increased footer margin to accommodate watermark and text

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25); // Margin from bottom (ensure it's more than footer height)

$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();

// --- Enhanced HTML Content with Improved Styling ---
$logoPath = './icon.ico';
if (file_exists($logoPath)) {
    list($imgWidth, $imgHeight) = getimagesize($logoPath);
    $maxWidth = 50;
    if ($imgWidth > $maxWidth) {
        $ratio = $maxWidth / $imgWidth;
        $imgWidth = $maxWidth;
        $imgHeight = $imgHeight * $ratio;
    }
    $logoHtml = '<img src="@' . realpath($logoPath) . '" width="'.$imgWidth.'" height="'.$imgHeight.'" alt="Logo" style="position:absolute; top:15px; right:15px;">';
}

// Calculate totals for Top Products
$totalTopQuantity = 0;
$totalTopRevenue = 0;
foreach ($topProducts as $product) {
    $totalTopQuantity += $product['quantity'];
    $totalTopRevenue += $product['revenue'];
}

// Calculate total for Expenses
$totalExpensesAmount = 0;
foreach ($expenses as $expense) {
    $totalExpensesAmount += $expense['price'];
}

$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report</title>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 10pt;
            line-height: 1.5;
        }
        .report-container {
            padding: 5px;
            margin-top: -100px; /* Reduced top margin */
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 20pt;
            color: #003366;
            margin: 0;
        }
        .header p {
            font-size: 10pt;
            color: #555;
            margin-top: -35px;
        }
        .section-title {
            font-size: 14pt;
            color: #0056b3;
            margin-top: 30px !important;
            margin-bottom: 15px;
            margin-left: 15px;
            padding-bottom: 5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        .section-title:before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            margin-top: 10px;
        }
        th, td {
            height:20px;
            border: 1px solid #ccc;
            text-align: left;
            font-size: 9pt;
        }
        th {
            background-color: #e9ecef;
            font-weight: bold;
            color: #212529;
        }
        tr:nth-child(even) td {
            background-color: #f8f9fa;
        }
        .summary-table th {
            width: 45%;
            background-color: #ddeeff;
        }
        .summary-table td {
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            color: #777;
            padding: 15px;
            font-style: italic;
        }
        .currency {
            text-align: right;
        }
        .quantity {
            text-align: center;
        }
        .stock-level {
            text-align: right;
        }
        tfoot td {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        tfoot th {
            font-weight:bold;
            background-color: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <h1>Sales Performance Report For Daiya Buffet</h1>
            <p>For the Period: ' . date('F j, Y', strtotime($from)) . ' to ' . date('F j, Y', strtotime($to)) . '</p>
            <p style="font-size: 8pt; color: #777;">Report Generated: ' . date('Y-m-d H:i:s') . '</p>
        </div>

       <br> <div class="section-title">Top Selling Products</div><br>';
if (empty($topProducts)) {
    $html .= '<p class="no-data">No top selling products found for this period.</p>';
} else {
    $html .= '
       <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th class="quantity">Quantity Sold</th>
                    <th class="currency">Total Revenue</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($topProducts as $product) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($product['title']) . '</td>
                        <td class="quantity">' . $product['quantity'] . '</td>
                        <td class="currency">Rs ' . number_format($product['revenue'], 2) . '</td>
                    </tr>';
    }
    $html .= '
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Totals for Listed Products</strong></td>
                    <td class="quantity"><strong>' . $totalTopQuantity . '</strong></td>
                    <td class="currency"><strong>Rs ' . number_format($totalTopRevenue, 2) . '</strong></td>
                </tr>
            </tfoot>
        </table>';
}

$html .= '
       <br> <div class="section-title">Expenses & Withdrawals</div><br>';
if (empty($expenses)) {
    $html .= '<p class="no-data">No expenses recorded for this period.</p>';
} else {
    $html .= '
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reason / Description</th>
                    <th>Type</th>
                    <th class="currency">Amount</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($expenses as $expense) {
        $html .= '
                    <tr>
                        <td>' . date('M j, Y', strtotime($expense['date'])) . '</td>
                        <td>' . htmlspecialchars($expense['reson']) . '</td>
                        <td>' . htmlspecialchars($expense['type']) . '</td>
                        <td class="currency">Rs ' . number_format($expense['price'], 2) . '</td>
                    </tr>';
    }
    $html .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:left;"><strong>Total Expenses for Period</strong></td>
                    <td class="currency"><strong>Rs ' . number_format($totalExpensesAmount, 2) . '</strong></td>
                </tr>
            </tfoot>
        </table>';
}

// --- Current Product Stock Levels Section ---
$html .= '<br>
        <div class="section-title">Product Stock</div><br>';
if (empty($productStockLevels)) {
    $html .= '<p class="no-data">No product stock data available.</p>';
} else {
    $html .= '
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Type</th>
                    <th class="stock-level">Stock Level</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($productStockLevels as $product) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($product['title']) . '</td>
                        <td>' . htmlspecialchars($product['type']) . '</td>
                        <td class="stock-level">' . htmlspecialchars(is_numeric($product['stock']) ? number_format($product['stock']) : $product['stock']) . '</td>
                    </tr>';
    }
    $html .= '
            </tbody>
        </table>';
}

// --- Kitchen Stock Levels Section ---
$html .= '<br>
        <div class="section-title">Kitchen Stock</div><br>';
if (empty($kitchenStockLevels)) {
    $html .= '<p class="no-data">No kitchen stock data available.</p>';
} else {
    $html .= '
        <table>
            <thead>
                <tr>
                    <th>Ingredient / Item</th>
                    <th class="quantity">Quantity</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($kitchenStockLevels as $stock) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($stock['ingredient']) . '</td>
                        <td class="quantity">' . htmlspecialchars($stock['volume']) . '</td>
                        <td>' . htmlspecialchars($stock['msh']) . '</td>
                    </tr>';
    }
    $html .= '
            </tbody>
        </table>';
}
$html .= '
    </div>
</body>
</html>';

try {
    $pdf->writeHTML($html, true, false, true, false, '');
} catch (Exception $e) {
     error_log("TCPDF HTML Write Exception: " . $e->getMessage() . " HTML: " . $html);
     die("An error occurred while writing HTML to PDF. Please check server logs.");
}

// Output PDF
$fileName = 'SalesReport_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to) . '.pdf';
try {
    $pdf->Output($fileName, 'I');
} catch (Exception $e) {
    error_log("TCPDF Output Exception: " . $e->getMessage());
    die("An error occurred while generating the PDF. Please check server logs.");
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>