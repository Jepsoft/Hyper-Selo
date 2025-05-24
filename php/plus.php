<?php
// Error Reporting (for debugging - keep this for troubleshooting)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file (optional, but helpful for debugging)
$logFile = 'error_log.txt';
ini_set("log_errors", 1);
ini_set("error_log", $logFile);

require __DIR__ . '/../vendor/autoload.php';  // Ensure autoload.php is correctly located

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

try {



    // 3. Check for the printer name.
    $printer_name = "XP-80C"; // Or whatever your printer name is
    if (empty($printer_name)) {
        throw new Exception("Printer name is not configured. You must set the printer name.");
    }

    // 4. Printer connection
    $connector = new WindowsPrintConnector($printer_name);
    $printer = new Printer($connector);

    // 5. Open the cash drawer
    $printer->pulse(); // Send pulse command

    // 6. Respond with a success message
    echo json_encode([
        'success' => true,
        'message' => 'Cash drawer opened successfully.',
    ]);

    // 7. Close the printer connection
    $printer->close();

} catch (Exception $e) {
    // 8. Handle errors
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to open cash drawer: ' . $e->getMessage()
    ]);
}
?>
