<?php
require __DIR__ . '/../vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;

include_once('conn.php');

// Error Reporting (for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
$logFile = 'error_log.txt';
ini_set("log_errors", 1);
ini_set("error_log", $logFile);

try {
    // Database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $connector = new WindowsPrintConnector("XP-80C");
    $printer = new Printer($connector);

    $printer->initialize();

    // Center the following text
    $printer->setTextSize(1, 1);
    $printer->text("Date: " . date("Y-m-d") ." Daily Report". "\n\n");
    $printer->setEmphasis(true);
    $printer->text("Sales Items\n");
    $printer->text(str_pad("Item", 30) . str_pad("Qty", 7, " ", STR_PAD_LEFT) . str_pad("Total", 10, " ", STR_PAD_LEFT) . "\n");
    $printer->setEmphasis(false);
    $printer->text(str_repeat("-", 47) . "\n");

    $today = date("Y-m-d"); // Get today's date
    $salesTotal = 0;
    $cashSales = 0;
    $cardSales = 0;
    $oldBatteryDiscounts = 0;

    $salesSql = "SELECT oi.title, oi.quantity, oi.price,oi.margin,oi.applied_discount, o.payment_method, o.old_dis
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.order_id
                 WHERE DATE(o.order_date) = ?"; 

    $stmt = $conn->prepare($salesSql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $itemName = mb_strimwidth($row['title'], 0, 30, "...");
            $quantity = str_pad($row['quantity'], 7, " ", STR_PAD_LEFT);
            $totalPrice = $row['quantity'] * $row['price']-(($row['quantity']*$row['applied_discount'])+$row['margin']) ;
            $totalPriceFormatted = str_pad(number_format($totalPrice, 2), 10, " ", STR_PAD_LEFT);
            $printer->text(str_pad($itemName, 30) . $quantity . $totalPriceFormatted . "\n");
            $salesTotal += $totalPrice;

            if ($row['payment_method'] == 'cash') {
                $cashSales += $totalPrice;
            } elseif ($row['payment_method'] == 'card') {
                $cardSales += $totalPrice;
            }
        }
    } else {
        $printer->text("No sales today.\n");
    }
    $stmt->close();

    // Print totals
    $printer->text(str_repeat("-", 47) . "\n");
    $printer->text(str_pad("Total Sales:", 37) . str_pad(number_format($salesTotal, 2), 10, " ", STR_PAD_LEFT) . "\n");
    $printer->feed();

    //--------------------------------------------------------------------------
    // Print Expenses for today
    //--------------------------------------------------------------------------
    $printer->setEmphasis(true);
    $printer->text("Expenses\n");
    $printer->text(str_pad("Reason", 35) . str_pad("Amount", 12, " ", STR_PAD_LEFT) . "\n");
    $printer->setEmphasis(false);
    $printer->text(str_repeat("-", 47) . "\n");

    $expensesTotal = 0;
    $expensesSql = "SELECT reson, price FROM expenses WHERE DATE(date) = ?";
    $stmt = $conn->prepare($expensesSql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $expensesResult = $stmt->get_result();

    if ($expensesResult->num_rows > 0) {
        while ($expense = $expensesResult->fetch_assoc()) {
            $reason = mb_strimwidth($expense['reson'], 0, 35, "...");
            $price = str_pad(number_format($expense['price'], 2), 12, " ", STR_PAD_LEFT);
            $printer->text(str_pad($reason, 30) . $price . "\n");
            $expensesTotal += $expense['price'];
        }
    } else {
        $printer->text("No expenses today.\n");
    }
    $stmt->close();

    $printer->text(str_repeat("-", 47) . "\n");
    $printer->text(str_pad("Expenses Total:", 35) . str_pad(number_format($expensesTotal, 2), 12, " ", STR_PAD_LEFT) . "\n");
    $printer->feed();

    //--------------------------------------------------------------------------
    // Print Old Battery Details for today
    //--------------------------------------------------------------------------
    $printer->setEmphasis(true);
    $printer->text("Old Battery Details\n");
    $printer->text(str_pad("Brand & Ah", 40) . str_pad("Qty", 7, " ", STR_PAD_LEFT). "\n");
    $printer->setEmphasis(false);
    $printer->text(str_repeat("-", 47) . "\n");

    $oldBatteryTotalDiscount = 0;
    $oldBatterySql = "SELECT type, ah, quntity, discount FROM old_battery WHERE DATE(date) = ?";
    $stmt = $conn->prepare($oldBatterySql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $oldBatteryResult = $stmt->get_result();

    if ($oldBatteryResult->num_rows > 0) {
        while ($oldBattery = $oldBatteryResult->fetch_assoc()) {
            $typeAh = mb_strimwidth($oldBattery['type'] . " " . $oldBattery['ah'].'Ah', 0, 40, "...");
            $quantity = str_pad($oldBattery['quntity'], 7, " ", STR_PAD_LEFT);
            $printer->text(str_pad($typeAh, 40) . $quantity . $discount . "\n");
            $oldBatteryTotalDiscount += $oldBattery['discount'];
        }
    } else {
        $printer->text("No old batteries today.\n");
    }
    $stmt->close();

    $printer->text(str_repeat("-", 47) . "\n");
    $printer->feed();
    $printer->cut();
    $printer->close();

    echo json_encode([
        'success' => true,
        'message' => 'Report printed successfully.',
    ]);

    $conn->close();

} catch (Exception $e) {
    // Log the exception details
    error_log("Exception caught: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // If any error occurred, rollback the transaction if started
    if (isset($conn)) {
        $conn->close();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process and print report: ' . $e->getMessage()
    ]);
}
?>
