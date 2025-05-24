<?php
include_once('conn.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = isset($_POST["username"]) ? trim($_POST["username"]) : "";
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";

    if (empty($username) || empty($password)) {
        echo "Please enter both username and password.";
        exit();
    }

    try {
        // Prepare the SQL statement using MySQLi
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_name = ?");
        
        if (!$stmt) {
            die("SQL error: " . $conn->error);
        }

        // Bind the username parameter (s = string)
        $stmt->bind_param("s", $username);
        
        // Execute the statement
        $stmt->execute();
        
        // Fetch result
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Use password_verify() instead of plain text comparison
            if ($password== $user['password']) {
                echo "Login successful!";
            } else {
                echo "Invalid usernamfe or password.";
            }
        } else {
            echo "Invalid username or password.";
        }

        // Close the statement
        $stmt->close();

    } catch (Exception $e) {
        echo "Error during login: " . $e->getMessage();
    }

    // Close the database connection
    $conn->close();
    exit();
}
?>