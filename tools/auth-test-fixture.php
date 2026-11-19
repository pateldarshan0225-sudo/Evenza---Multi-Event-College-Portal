<?php
// CLI-only disposable sessions for the multi-tab regression test. No database writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === 'destroy') {
    $id = $argv[2] ?? '';
    if (!preg_match('/^evenza-auth-audit-[a-f0-9]{24}$/', $id)) { exit(1); }
    session_id($id); session_start(); session_destroy(); exit;
}
require __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();
mysqli_query($conn, 'START TRANSACTION READ ONLY');
$tabs = [];
$auth = [];
try {
    foreach (['admin' => ['admins', 'admin_id'], 'organizer' => ['colleges', 'college_id'], 'student' => ['students', 'student_id']] as $role => [$table, $key]) {
        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT $key FROM $table ORDER BY $key LIMIT 1"));
        if (!$row) { throw new RuntimeException('Missing account fixture for ' . $role); }
        $tabs[$role] = 'tab_' . bin2hex(random_bytes(16));
        $auth[$tabs[$role]] = ['user_id' => (int)$row[$key], 'college_id' => $role === 'organizer' ? (int)$row[$key] : null,
            'role' => $role, 'name' => 'Audit ' . ucfirst($role), 'email' => 'audit@example.invalid', 'last_activity' => time()];
    }
} finally { mysqli_rollback($conn); }
$id = 'evenza-auth-audit-' . bin2hex(random_bytes(12));
session_id($id);
if (!session_start()) { throw new RuntimeException('Unable to create disposable session'); }
$_SESSION['authenticated_tabs'] = $auth;
session_write_close();
echo json_encode(['session' => $id, 'tabs' => $tabs]);
