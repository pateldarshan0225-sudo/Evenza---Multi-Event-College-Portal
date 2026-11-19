<?php
/**
 * includes/connection.php
 * Central Canonical Database Connection for Evenza Platform with WAMP Auto-Fallback
 */
mysqli_report(MYSQLI_REPORT_OFF);

$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'evenza';

$connection_targets = [
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost', 'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3307],
    ['host' => 'localhost', 'port' => 3307],
    ['host' => 'localhost', 'port' => null]
];

$conn = false;

foreach ($connection_targets as $target) {
    $h = $target['host'];
    $p = $target['port'];
    
    $tmp_conn = $p ? @mysqli_connect($h, $DB_USER, $DB_PASS, $DB_NAME, $p) : @mysqli_connect($h, $DB_USER, $DB_PASS, $DB_NAME);
    if ($tmp_conn) {
        $conn = $tmp_conn;
        break;
    }
}

if (!$conn) {
    die('<div style="font-family:\'Plus Jakarta Sans\', sans-serif; padding:40px; text-align:center; max-width:540px; margin:80px auto; background:#ffffff; border-radius:24px; box-shadow:0 20px 40px rgba(0,0,0,0.08); border:1px solid #fee2e2;">'
        . '<div style="width:56px; height:56px; background:#fef2f2; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; color:#dc2626; font-size:24px; margin-bottom:16px;">⚠️</div>'
        . '<h2 style="color:#0f172a; margin:0 0 8px 0; font-weight:800; font-size:20px;">Database Connection Failed</h2>'
        . '<p style="color:#64748b; font-size:14px; line-height:1.6; margin:0 0 16px 0;">Could not connect to MySQL database service on <code>127.0.0.1</code> or <code>localhost</code>.</p>'
        . '<p style="color:#94a3b8; font-size:12px; margin:0;">Please ensure WampServer / MySQL service is started and running on your local machine.</p>'
        . '</div>');
}

mysqli_set_charset($conn, 'utf8mb4');

/** Return the established connection with an explicit type for page callers. */
function get_evenza_connection(): mysqli {
    global $conn;
    if (!$conn instanceof mysqli) {
        throw new RuntimeException('Database connection is unavailable.');
    }
    return $conn;
}
