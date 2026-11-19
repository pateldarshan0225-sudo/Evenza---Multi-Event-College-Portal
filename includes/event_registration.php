<?php
require_once __DIR__ . '/event_lifecycle.php';
function evenza_registration_csrf(): string {
    if (empty($_SESSION['event_action_token'])) $_SESSION['event_action_token'] = bin2hex(random_bytes(32));
    return $_SESSION['event_action_token'];
}
function evenza_valid_event_token($token): bool {
    return is_string($token) && isset($_SESSION['event_action_token']) && hash_equals($_SESSION['event_action_token'], $token);
}
function evenza_registration_open(array $event): bool {
    if (evenza_event_status($event) !== 'published') return false;
    $deadline = trim((string)($event['registration_deadline'] ?? ''));
    if ($deadline === '') return true;
    try { return new DateTimeImmutable($deadline, new DateTimeZone('Asia/Kolkata')) > evenza_event_now(); }
    catch (Exception $ex) { return false; }
}
/** Registration status is independent of event/pass status; never overwrite the approval history. */
function evenza_pass_status(array $row): string {
    $status = strtolower((string)($row['status'] ?? 'pending'));
    if ($status === 'cancelled' || ($row['event_status'] ?? '') === 'cancelled') return 'Cancelled';
    if (($row['event_status'] ?? '') === 'completed' || evenza_event_has_ended($row)) return 'Expired';
    if (!empty($row['expires_at'])) {
        try { if (new DateTimeImmutable($row['expires_at'], new DateTimeZone('Asia/Kolkata')) <= evenza_event_now()) return 'Expired'; }
        catch (Exception $ex) { return 'Unavailable'; }
    }
    if ($status !== 'approved') return 'Awaiting approval';
    if (empty($row['pass_code'])) return 'Awaiting pass';
    if (($row['event_status'] ?? 'published') !== 'published') return 'Unavailable';
    return (int)($row['is_used'] ?? 0) === 1 ? 'Checked in' : 'Active pass';
}
function evenza_issue_pass(mysqli $conn, int $registrationId): void {
    $res = mysqli_query($conn, "SELECT pass_id FROM entry_passes WHERE registration_id = $registrationId LIMIT 1");
    if (!$res) throw new RuntimeException('Could not check entry pass.');
    if (mysqli_fetch_assoc($res)) return;
    $code = 'PASS-' . strtoupper(bin2hex(random_bytes(8)));
    if (!mysqli_query($conn, "INSERT INTO entry_passes (registration_id, pass_code, issued_at, expires_at, is_used) SELECT r.registration_id, '$code', NOW(), TIMESTAMP(e.event_date, COALESCE(e.end_time, '23:59:59')), 0 FROM registrations r INNER JOIN events e ON e.event_id = r.event_id WHERE r.registration_id = $registrationId")) throw new RuntimeException('Could not issue entry pass.');
}
function evenza_register_student(mysqli $conn, int $eventId, int $studentId, string $teamName): array {
    if (!mysqli_begin_transaction($conn)) throw new RuntimeException('Could not start registration.');
    try {
        // Lock the event before checking time/duplicates. Concurrent submissions serialize here.
        $res = mysqli_query($conn, "SELECT * FROM events WHERE event_id = $eventId FOR UPDATE");
        $event = $res ? mysqli_fetch_assoc($res) : null;
        if (!$event || !evenza_registration_open($event)) throw new RuntimeException('Registration is closed for this event.');
        $res = mysqli_query($conn, "SELECT student_id FROM students WHERE student_id = $studentId AND account_status = 'active' AND status NOT IN ('blocked','inactive','rejected') LIMIT 1");
        if (!$res || !mysqli_fetch_assoc($res)) throw new RuntimeException('An active student account is required.');
        $res = mysqli_query($conn, "SELECT r.registration_id FROM registrations r WHERE r.event_id = $eventId AND (r.student_id = $studentId OR EXISTS (SELECT 1 FROM team_members tm WHERE tm.team_id = r.team_id AND tm.student_id = $studentId)) LIMIT 1");
        if (!$res) throw new RuntimeException('Could not verify registration.');
        if (mysqli_fetch_assoc($res)) throw new RuntimeException('You already have a registration for this event.');
        $teamId = 'NULL';
        if ($event['event_type'] === 'team') {
            if (trim($teamName) === '' || strlen($teamName) > 100) throw new RuntimeException('Enter a team name up to 100 characters.');
            $name = mysqli_real_escape_string($conn, trim($teamName));
            $code = 'TEAM-' . strtoupper(bin2hex(random_bytes(5)));
            if (!mysqli_query($conn, "INSERT INTO teams (event_id, team_name, leader_id, team_code) VALUES ($eventId, '$name', $studentId, '$code')")) throw new RuntimeException('Could not create team.');
            $teamId = (int)mysqli_insert_id($conn);
            if (!mysqli_query($conn, "INSERT INTO team_members (team_id, student_id) VALUES ($teamId, $studentId)")) throw new RuntimeException('Could not add team leader.');
        }
        // A click is not a payment. Paid events await organizer approval; never fabricate a paid transaction.
        $status = (float)$event['registration_fee'] > 0 ? 'pending' : 'approved';
        $type = $event['event_type'] === 'team' ? 'team' : 'solo';
        if (!mysqli_query($conn, "INSERT INTO registrations (event_id, student_id, team_id, registration_type, status, registered_at) VALUES ($eventId, $studentId, $teamId, '$type', '$status', NOW())")) throw new RuntimeException('Could not save registration.');
        $id = (int)mysqli_insert_id($conn);
        if ($status === 'approved') evenza_issue_pass($conn, $id);
        if (!mysqli_commit($conn)) throw new RuntimeException('Could not confirm registration.');
        return ['registration_id'=>$id, 'status'=>$status];
    } catch (Throwable $ex) { mysqli_rollback($conn); throw $ex; }
}
/** Aggregate payments before joining so installments never duplicate registrations. */
function evenza_payment_join_sql(): string {
    return "LEFT JOIN (SELECT registration_id, SUM(CASE WHEN LOWER(payment_status) = 'paid' THEN amount ELSE 0 END) AS amount, CASE WHEN SUM(CASE WHEN LOWER(payment_status) = 'paid' THEN amount ELSE 0 END) > 0 THEN 'paid' ELSE 'pending' END AS payment_status FROM payments GROUP BY registration_id) p ON r.registration_id = p.registration_id";
}
function evenza_pass_join_sql(): string {
    return 'LEFT JOIN entry_passes ep ON ep.pass_id = (SELECT MAX(ep_latest.pass_id) FROM entry_passes ep_latest WHERE ep_latest.registration_id = r.registration_id)';
}
function evenza_row_event_status(array $row): string {
    return evenza_event_status(['status'=>$row['event_status'] ?? 'published', 'event_date'=>$row['event_date'] ?? '', 'end_time'=>$row['end_time'] ?? null]);
}
function evenza_update_registration(mysqli $conn, int $id, string $status, ?int $collegeId = null): void {
    if (!in_array($status, ['pending','approved','cancelled'], true) || $id < 1) throw new RuntimeException('Invalid registration status.');
    if (!mysqli_begin_transaction($conn)) throw new RuntimeException('Could not start status update.');
    try {
        $scope = $collegeId === null ? '' : ' AND e.college_id = ' . $collegeId;
        $res = mysqli_query($conn, "SELECT e.* FROM registrations r INNER JOIN events e ON e.event_id = r.event_id WHERE r.registration_id = $id $scope FOR UPDATE");
        $event = $res ? mysqli_fetch_assoc($res) : null;
        if (!$event) throw new RuntimeException('Registration not found for this account.');
        if ($status === 'approved' && evenza_event_status($event) !== 'published') throw new RuntimeException('Cannot approve a pass for a completed, cancelled or unpublished event.');
        if (!mysqli_query($conn, "UPDATE registrations SET status = '$status' WHERE registration_id = $id")) throw new RuntimeException('Could not save registration status.');
        if ($status === 'approved') evenza_issue_pass($conn, $id);
        if (!mysqli_commit($conn)) throw new RuntimeException('Could not confirm status update.');
    } catch (Throwable $ex) { mysqli_rollback($conn); throw $ex; }
}
