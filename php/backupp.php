<?php
// backupp.php
header('Content-Type: application/json');

// Database configuration (should be in a secure location)
define('BACKUP_DIR', __DIR__ . '/backups');
define('MYSQLDUMP_PATH', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');

try {
    // Verify mysqldump exists
    if (!file_exists(MYSQLDUMP_PATH)) {
        throw new Exception('mysqldump.exe not found at specified path');
    }

    // Create backup directory if missing
    if (!file_exists(BACKUP_DIR)) {
        if (!mkdir(BACKUP_DIR, 0755, true)) {
            throw new Exception('Failed to create backup directory');
        }
    }

    // Verify directory is writable
    if (!is_writable(BACKUP_DIR)) {
        throw new Exception('Backup directory is not writable');
    }

    // Database credentials (replace with your actual credentials)
    $dbConfig = [
        'host'      => '127.0.0.1',
        'username'  => 'root',
        'password'  => '',
        'dbname'    => 'jepsoft_pos'
    ];

    // Generate backup filename
    $backupFile = BACKUP_DIR . '/database_backup_.sql';

    // Build command
    $command = sprintf(
        '"%s" --host=%s --user=%s --password=%s --databases %s --result-file=%s 2>&1',
        escapeshellarg(MYSQLDUMP_PATH),
        escapeshellarg($dbConfig['host']),
        escapeshellarg($dbConfig['username']),
        escapeshellarg($dbConfig['password']),
        escapeshellarg($dbConfig['dbname']),
        escapeshellarg($backupFile)
    );

    // Execute command
    exec($command, $output, $returnCode);

    // Verify success
    if ($returnCode !== 0 || !file_exists($backupFile)) {
        throw new Exception(sprintf(
            'Backup failed (Code: %d) - %s',
            $returnCode,
            implode('\n', $output)
        ));
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Backup created successfully',
        'file' => basename($backupFile),
        'size' => filesize($backupFile)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>
