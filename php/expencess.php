<?php
include_once('conn.php');

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => "Connection failed: " . $conn->connect_error]));
}

header('Content-Type: application/json'); // Set header for JSON response

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
    case 'get_last_transactions':
        getLastTransactions($conn);
        break;
    case 'add_expense':
        addExpense($conn);
        break;
    case 'collect_cash':
        collectCash($conn);
        break;
    case 'get_total_sales':
        getTotalSales($conn);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();

function getLastTransactions($conn) {
    $sql = "SELECT expenses_id as id, reson as reason, other_notes as notes, price as amount,type, date as created_at FROM expenses ORDER BY expenses_id DESC LIMIT 50";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        echo json_encode(['success' => true, 'transactions' => $transactions]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No transactions found']);
    }
}

function addExpense($conn) {
    $reason = $conn->real_escape_string($_POST['reason']);
    $price = floatval($_POST['price']);
    $notes = $conn->real_escape_string($_POST['other_notes']);

    $sql = "INSERT INTO expenses (reson, other_notes, price) VALUES ('$reason', '$notes', $price)";

    if ($conn->query($sql) === TRUE) {
        updateTotalSales($conn, -$price);
        getTotalSales($conn);
    } else {
        echo json_encode(['success' => false, 'error' => "Error: " . $sql . "<br>" . $conn->error]);
    }
}

function collectCash($conn) {
    $amount = floatval($_POST['amount']);

    // Subtract amount from total_sales in cashier_balance
    $sql = "UPDATE cashier_balance SET total_sales = total_sales - $amount WHERE id = 1";

    if ($conn->query($sql) === TRUE) {
        $sqli = "INSERT INTO expenses (reson, other_notes, price,type) VALUES ('Cash withdraw', 'Sales', $amount,'cash withdraw')";

        if ($conn->query($sqli) === TRUE) {
            updateTotalSales($conn, -$price);
            getTotalSales($conn);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => "Error: " . $sqli . "<br>" . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => "Error: " . $sql . "<br>" . $conn->error]);
    }
   
}

function getTotalSales($conn) {
    $sql = "SELECT total_sales FROM cashier_balance WHERE id = 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'total_sales' => $row['total_sales']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Total sales not found']);
    }
}

function updateTotalSales($conn, $amount) {
    $sql = "UPDATE cashier_balance SET total_sales = total_sales + $amount WHERE id = 1";
    $conn->query($sql);
}
?>