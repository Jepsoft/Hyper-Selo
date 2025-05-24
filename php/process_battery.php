<?php
include_once('conn.php');

// Check connection
if ($conn->connect_error) {
    $response = array('success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error);
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_GET['action'] == 'add') {
    $type = $conn->real_escape_string($_POST['type']);
    $ah_str = $conn->real_escape_string($_POST['ah']);
    $ah = floatval($ah_str); // Convert to float immediately
    $quantity = intval($_POST['quantity']); // Ensure quantity is an integer
    $today = date('Y-m-d');

    if (!empty($type) && is_numeric($ah) && is_numeric($quantity)) {
        // Check if a record with the same type and Ah exists for today
        $check_sql = "SELECT id, quntity, date FROM old_battery WHERE type = '$type' AND ah = $ah AND date = '$today'";
        $check_result = $conn->query($check_sql);


        if ($check_result->num_rows > 0) {
            // Update the quantity of the existing record for today
            $row = $check_result->fetch_assoc();
            $new_quantity = $row['quntity'] + $quantity;
            $update_sql = "UPDATE old_battery SET quntity = $new_quantity WHERE id = " . $row['id'];
            if ($conn->query($update_sql) === TRUE) {
                $response = array('success' => true, 'message' => 'Battery details updated successfully!');
            } else {
                $response = array('success' => false, 'message' => 'Error updating record: ' . $conn->error);
            }
        } else {
            // Insert a new record
             $sql = "INSERT INTO old_battery (type, ah, quntity, date) VALUES ('$type', '$ah','$quantity','$today')";
            if ($conn->query($sql) === TRUE) {
                $response = array('success' => true, 'message' => 'New battery details added successfully!');
            } else {
                $response = array('success' => false, 'message' => 'Error adding record: ' . $conn->error);
            }
        }
    } else {
        $response = array('success' => false, 'message' => 'Please fill in all fields correctly.');
    }

    header('Content-Type: application/json');
    echo json_encode($response);
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && $_GET['action'] == 'remove') {
    $removeBatteryId = $conn->real_escape_string($_POST['removeBatteryId']);
    $removeQuantity = intval($_POST['removeQuantity']);
    $today = date('Y-m-d');


    if (!empty($removeBatteryId) && is_numeric($removeQuantity) && $removeQuantity > 0) {
        // Check if the battery exists and if there's enough quantity
        $check_sql = "SELECT quntity, date FROM old_battery WHERE id = '$removeBatteryId'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows == 1) {
            $row = $check_result->fetch_assoc();
            $current_quantity = $row['quntity'];
            $stored_date = $row['date'];

            if ($removeQuantity <= $current_quantity) {
                $new_quantity = $current_quantity - $removeQuantity;

                if ($new_quantity > 0) {
                  //update quantity
                    $update_sql = "UPDATE old_battery SET quntity = $new_quantity WHERE id = '$removeBatteryId'";
                    if ($conn->query($update_sql) === TRUE) {
                        $response = array('success' => true, 'message' => 'Battery quantity removed successfully!');
                    } else {
                        $response = array('success' => false, 'message' => 'Error updating battery quantity: ' . $conn->error);
                    }
                } else {
                    $delete_sql = "DELETE FROM old_battery WHERE id = '$removeBatteryId'";
                    if ($conn->query($delete_sql) === TRUE) {
                        $response = array('success' => true, 'message' => 'Battery removed successfully (quantity reached zero)!');
                    } else {
                        $response = array('success' => false, 'message' => 'Error removing battery: ' . $conn->error);
                    }
                }
            } else {
                $response = array('success' => false, 'message' => 'Not enough quantity to remove.');
            }
        } else {
            $response = array('success' => false, 'message' => 'No battery found with the specified ID.');
        }
    } else {
        $response = array('success' => false, 'message' => 'Please select a battery and enter a valid quantity to remove.');
    }

    header('Content-Type: application/json');
    echo json_encode($response);
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && $_GET['action'] == 'get') {
$sql_select = "
SELECT 
    type, 
    ah, 
    SUM(quntity) AS quntity, 
    MAX(date) AS date
FROM 
    old_battery
GROUP BY 
    type, ah";


    $result = $conn->query($sql_select);
    $data = array();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    $response = array('success' => false, 'message' => 'Invalid request.');
    header('Content-Type: application/json');
    echo json_encode($response);
}

$conn->close();
?>
