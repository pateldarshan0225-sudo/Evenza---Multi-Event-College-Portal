<?php
// Render protected GET pages without logging in or changing database records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$page = $argv[1] ?? '';
$allowed = ['Admin/Dashboard.php', 'Admin/Colleges.php', 'Admin/all_events.php', 'Admin/allregistrations.php', 'Admin/allstudents.php', 'Admin/allteams.php', 'Admin/alluniversity.php', 'Admin/change_password.php', 'Admin/event_cat.php', 'Admin/teamdetails.php', 'Student/Dashboard.php', 'Student/MyEvents.php', 'Student/MyTeams.php', 'Student/Profile.php', 'Organizer/Dashboard.php', 'Organizer/Events.php', 'Organizer/Registrations.php', 'Organizer/Students.php', 'Organizer/Profile.php', 'Frontend/event_detail.php'];
if (!in_array($page, $allowed, true)) { fwrite(STDERR, 'Unsupported page'); exit(1); }
require __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();
if (!mysqli_query($conn, 'START TRANSACTION READ ONLY')) { exit(1); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_save_path(sys_get_temp_dir());
session_id('evenza-audit-' . bin2hex(random_bytes(12)));
session_start();
register_shutdown_function(function () use ($conn) { mysqli_rollback($conn); if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); } });
$role = str_starts_with($page, 'Admin/') ? 'admin' : (str_starts_with($page, 'Organizer/') ? 'organizer' : 'student');
$table = ['admin' => 'admins', 'organizer' => 'colleges', 'student' => 'students'][$role];
$key = ['admin' => 'admin_id', 'organizer' => 'college_id', 'student' => 'student_id'][$role];
$result = mysqli_query($conn, "SELECT $key" . ($role === 'student' ? ', college_id' : '') . " FROM $table ORDER BY $key LIMIT 1");
$record = mysqli_fetch_assoc($result);
if (!$record) { fwrite(STDERR, 'No existing account for role ' . $role); exit(2); }
$tab = 'audit_tab_0123456789abcdef';
$_SESSION['authenticated_tabs'][$tab] = ['user_id' => (int)$record[$key], 'role' => $role, 'name' => 'Audit User', 'email' => 'audit@example.invalid', 'college_id' => $role === 'organizer' ? (int)$record[$key] : ($record['college_id'] ?? null), 'last_activity' => time()];
$_GET['evenza_tab_id'] = $tab;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/Evenza/' . $page;
if ($page === 'Admin/teamdetails.php') {
    $r = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT team_id FROM teams LIMIT 1'));
    $_GET['id'] = $r['team_id'] ?? 0;
}
if ($page === 'Frontend/event_detail.php') {
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT event_id FROM events WHERE status = 'published' LIMIT 1"));
    $_GET['id'] = $r['event_id'] ?? 0;
    // CLI filter_input does not read injected $_GET; detail rendering is tested over HTTP instead.
}
chdir(dirname(__DIR__ . '/../' . $page));
require __DIR__ . '/../' . $page;
