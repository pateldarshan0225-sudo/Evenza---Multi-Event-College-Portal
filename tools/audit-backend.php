<?php
// CLI only. All database access is wrapped in a READ ONLY transaction.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();
if (!mysqli_query($conn, 'START TRANSACTION READ ONLY')) {
    fwrite(STDERR, "Unable to start a read-only transaction.\n"); exit(1);
}
try {
    $tables = mysqli_query($conn, 'SHOW TABLES');
    $schema = [];
    while ($row = mysqli_fetch_row($tables)) {
        $table = $row[0];
        $columns = mysqli_query($conn, 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        $schema[$table] = [];
        while ($column = mysqli_fetch_assoc($columns)) {
            $schema[$table][] = ['name' => $column['Field'], 'type' => $column['Type']];
        }
    }
    echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
} finally {
    mysqli_rollback($conn);
}
