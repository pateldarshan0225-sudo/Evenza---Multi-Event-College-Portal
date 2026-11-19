<?php
require_once __DIR__ . '/../includes/event_registration.php';
$payment_join_sql = evenza_payment_join_sql();
$pass_join_sql = evenza_pass_join_sql();
require_once __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();

include 'auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connection.php';

/* =========================================================
   REAL-TIME DATABASE STATS QUERIES
   ========================================================= */
if (!function_exists('db_fetch_val')) {
    function db_fetch_val($conn, $sql) {
        $res = mysqli_query($conn, $sql);
        if ($res) {
            $row = mysqli_fetch_row($res);
            return $row ? $row[0] : 0;
        }
        return 0;
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all($conn, $sql) {
        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        return $rows;
    }
}

$total_students     = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM students");
$total_events       = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM events");
$total_regs         = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM registrations");
$total_colleges     = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM colleges WHERE status='active'");
$total_universities = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM universities WHERE status='active'");
$cancelled_regs     = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM registrations WHERE status='cancelled'");
$approved_regs      = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM registrations WHERE status='approved'");
$pending_regs       = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM registrations WHERE status='pending'");

// Revenue calculation from DB
$total_revenue = (float)db_fetch_val($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status='paid'");
// Pass metrics from DB
$passes_issued = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM entry_passes");
$passes_used   = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM entry_passes WHERE is_used = 1");
// Filter Options from DB
$colleges_list = db_fetch_all($conn, "SELECT college_id, name FROM colleges ORDER BY name ASC");
$events_list   = db_fetch_all($conn, "SELECT event_id, title FROM events ORDER BY title ASC");

// Dynamic Registrations List from DB
$db_registrations = db_fetch_all($conn, "
    SELECT r.registration_id, r.student_id, r.team_id, r.event_id, r.registration_type, r.status, r.registered_at,
           CASE WHEN r.registration_type = 'team' THEN COALESCE(t.team_name, s.name, 'Participant') ELSE COALESCE(s.name, 'Participant') END AS participant_name,
           COALESCE(s.profile_photo, '') AS avatar,
           COALESCE(s.email, '') AS email,
           COALESCE(s.phone, '') AS phone,
           e.title AS event_title, e.event_date, e.end_time, e.status AS event_status, ep.pass_code, ep.is_used, ep.expires_at,
           e.registration_fee,
           COALESCE(ce.name, 'Main Campus') AS college_name
    FROM registrations r
    JOIN events e ON r.event_id = e.event_id
    LEFT JOIN students s ON r.student_id = s.student_id
    LEFT JOIN teams t ON r.team_id = t.team_id
    {$pass_join_sql}
    LEFT JOIN colleges cs ON s.college_id = cs.college_id
    LEFT JOIN colleges ce ON e.college_id = ce.college_id
    ORDER BY r.registered_at DESC, r.registration_id DESC
");

foreach ($db_registrations as &$record) { $record['pass_status'] = evenza_pass_status($record); $record['event_status'] = evenza_row_event_status($record); }
unset($record);

// Dynamic Payments List from DB
$db_payments = db_fetch_all($conn, "
    SELECT p.payment_id, p.registration_id, p.transaction_id, p.amount, p.payment_status, p.payment_method, p.payment_date,
           COALESCE(s.name, 'Student') AS student_name,
           e.title AS event_title
    FROM payments p
    JOIN registrations r ON p.registration_id = r.registration_id
    JOIN events e ON r.event_id = e.event_id
    LEFT JOIN students s ON r.student_id = s.student_id
    ORDER BY p.payment_date DESC
");

// Top Events from DB
$top_events = db_fetch_all($conn, "
    SELECT e.title, COUNT(r.registration_id) AS reg_count
    FROM events e
    LEFT JOIN registrations r ON e.event_id = r.event_id
    GROUP BY e.event_id, e.title
    ORDER BY reg_count DESC
    LIMIT 5
");

// Read-only source records used by overview filters and charts.
$overview_students = db_fetch_all($conn, "SELECT student_id, college_id, name, created_at FROM students");
$overview_events = db_fetch_all($conn, "SELECT event_id, college_id, title, created_at FROM events");
$overview_colleges = db_fetch_all($conn, "SELECT college_id, name, status, created_at FROM colleges");
$overview_passes = db_fetch_all($conn, "SELECT registration_id, issued_at, is_used, used_at FROM entry_passes");
$overview_members = db_fetch_all($conn, "SELECT team_id, student_id FROM team_members");
$team_count = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM registrations WHERE registration_type='team'");
$solo_count = (int)db_fetch_val($conn, "SELECT COUNT(*) FROM registrations WHERE registration_type='solo'");
$total_split = max(1, $team_count + $solo_count);
$team_pct = round($team_count / $total_split * 100, 1);
$solo_pct = round($solo_count / $total_split * 100, 1);

// Admin name resolution
$admin_email = $_SESSION['admin_email'] ?? 'admin@evenza.com';
$admin_name  = $_SESSION['admin_name'] ?? 'Admin';

// Dynamic Calculations for UI
$pass_checkin_pct = ($passes_issued > 0) ? min(100, round(($passes_used / $passes_issued) * 100)) : 0;
$registration_goal = max(100, (int)($total_regs * 1.25));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reports & Analytics | Evenza Admin</title>

    <!-- Google Fonts: Plus Jakarta Sans -->

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/fonts/bootstrap-icons/bootstrap-icons.min.css" />

    <style>
        :root {
            --bg-canvas: #ece7dd;
            --bg-card: #f4f2eb;
            --bg-hero: #e0d8cb;
            --bg-dark: #1c2024;
            --bg-white: #ffffff;
            --bg-row: #f6f4ee;
            --color-yellow: #ffd13b;
            --color-red: #ff6b52;
            --color-text-dark: #1c2024;
            --color-text-muted: #7c7d7e;
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: var(--color-text-dark);
            margin: 0;
            padding: 20px 28px;
            min-height: 100vh;
            width: 100%;
        }

        /* Fluid Container Layout */
        .berun-window {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            position: relative;
        }

        /* Header Navigation Area */
        .berun-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .berun-logo-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--color-text-dark);
        }

        .berun-logo-dots {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .berun-logo-dots-top { display: flex; gap: 2px; }

        .berun-dot {
            width: 7px;
            height: 7px;
            background-color: var(--color-text-dark);
            border-radius: 50%;
        }

        .berun-logo-text {
            font-weight: 800;
            font-size: 20px;
            letter-spacing: -0.5px;
            color: var(--color-text-dark);
        }
        .berun-logo-text span { font-weight: 400; }

        .berun-greeting-h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: var(--color-text-dark);
            line-height: 1.2;
        }

        .berun-greeting-sub {
            font-size: 13px;
            color: var(--color-text-muted);
            margin: 3px 0 0 0;
            font-weight: 500;
        }

        /* Top Right Search & Action Controls */
        .berun-search-wrapper {
            position: relative;
            width: 290px;
        }

        .berun-search-input {
            width: 100%;
            background: var(--bg-white);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 9999px;
            padding: 10px 20px 10px 44px;
            font-size: 13px;
            font-weight: 500;
            color: var(--color-text-dark);
            outline: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .berun-search-input:focus {
            border-color: var(--color-text-dark);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .berun-search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ea0a5;
            font-size: 14px;
        }

        .berun-btn-dark {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 10px 26px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .berun-btn-dark:hover {
            background-color: #2e343b;
            transform: translateY(-1px);
        }

        /* Layout Grid with Left Floating Sidebar Capsule */
        .berun-layout-body {
            display: flex;
            gap: 28px;
        }

        /* Left Floating Sidebar Capsule */
        .berun-sidebar-capsule {
            background: var(--bg-white);
            border-radius: 40px;
            width: 68px;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            flex-shrink: 0;
            position: sticky;
            top: 20px;
            align-self: flex-start;
            z-index: 100;
            max-height: calc(100vh - 40px);
        }

        .berun-nav-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            width: 100%;
        }

        .berun-nav-item {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #72757c;
            text-decoration: none;
            font-size: 18px;
            transition: all 0.2s ease;
            position: relative;
        }

        .berun-nav-item:hover {
            color: var(--color-text-dark);
            background: rgba(0, 0, 0, 0.04);
        }

        .berun-nav-item.active {
            background-color: var(--bg-dark);
            color: var(--color-yellow) !important;
            box-shadow: 0 6px 16px rgba(28, 32, 36, 0.25);
        }

        .berun-nav-badge {
            position: absolute;
            top: 10px;
            right: 12px;
            width: 7px;
            height: 7px;
            background-color: #ff4747;
            border-radius: 50%;
        }

        .berun-avatar-pill {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        /* Main Dashboard Content Area */
        .berun-main-grid {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* Row 1: Hero Card + Training Days Calendar */
        .berun-row-top {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 24px;
        }

        /* Hero Summary Card ("Platform Overview for Today") */
        .berun-hero-card {
            background-color: var(--bg-hero);
            border-radius: 28px;
            padding: 26px 30px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 320px;
            overflow: hidden;
        }

        .berun-hero-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .berun-hero-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--color-text-dark);
            margin: 0;
        }

        .berun-hero-sub {
            font-size: 13px;
            color: #5c5e63;
            margin: 2px 0 0 0;
            font-weight: 500;
        }

        .berun-icon-btn-dark {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--bg-dark);
            color: var(--color-yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Central Dynamic Glowing Bubbles Visualization */
        .berun-bubbles-art {
            position: relative;
            height: 190px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 6px 0;
        }

        .berun-bubble-yellow {
            position: absolute;
            width: 175px;
            height: 175px;
            border-radius: 50%;
            background: radial-gradient(circle at 40% 40%, #ffe366 0%, #ffc83b 65%, #f5b72b 100%);
            filter: drop-shadow(0 0 35px rgba(255, 205, 50, 0.55));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--color-text-dark);
            right: 15%;
            top: 5px;
            z-index: 2;
            box-shadow: inset 0 2px 10px rgba(255,255,255,0.4);
            padding: 10px;
            text-align: center;
        }
        .berun-bubble-yellow .val { font-size: 18px; font-weight: 800; line-height: 1.1; }
        .berun-bubble-yellow .unit { font-size: 11px; font-weight: 600; margin-top: 3px; }

        .berun-bubble-red {
            position: absolute;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: radial-gradient(circle at 40% 40%, #ff8278 0%, #ff5b4f 70%, #f0483c 100%);
            filter: drop-shadow(0 0 30px rgba(255, 90, 80, 0.5));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            left: 26%;
            bottom: 5px;
            z-index: 3;
            box-shadow: inset 0 2px 8px rgba(255,255,255,0.3);
            text-align: center;
            padding: 8px;
        }
        .berun-bubble-red .val { font-size: 18px; font-weight: 800; line-height: 1; }
        .berun-bubble-red .unit { font-size: 11px; font-weight: 500; margin-top: 3px; opacity: 0.95; }

        .berun-bubble-dark {
            position: absolute;
            background-color: var(--bg-dark);
            color: #ffffff;
            border-radius: 9999px;
            padding: 12px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            left: 18%;
            top: 20px;
            z-index: 4;
            box-shadow: 0 10px 25px rgba(28, 32, 36, 0.3);
            text-align: center;
        }
        .berun-bubble-dark .val { font-size: 16px; font-weight: 800; line-height: 1; }
        .berun-bubble-dark .unit { font-size: 11px; font-weight: 500; color: #a0a4ab; margin-top: 2px; }

        /* Bottom Legend */
        .berun-legend-row {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-top: 5px;
            flex-wrap: wrap;
        }

        .berun-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #4a4c52;
        }

        .berun-legend-bar {
            width: 24px;
            height: 6px;
            border-radius: 9999px;
        }
        .berun-legend-bar.yellow { background-color: #f5bc2c; }
        .berun-legend-bar.red { background-color: #ff5b4f; }
        .berun-legend-bar.dark { background-color: var(--bg-dark); }

        /* Event Schedule Calendar Card */
        .berun-calendar-card {
            background-color: var(--bg-dark);
            border-radius: 28px;
            padding: 26px 28px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 320px;
        }

        .berun-calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .berun-calendar-title {
            font-size: 17px;
            font-weight: 700;
            margin: 0;
            color: #ffffff;
        }

        .berun-month-select {
            background: transparent;
            border: none;
            color: #9ea2aa;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            outline: none;
            padding: 0 4px;
        }

        .berun-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px 4px;
            text-align: center;
            align-items: center;
            margin-bottom: 12px;
        }

        .berun-weekday {
            font-size: 11px;
            font-weight: 600;
            color: #6a6e78;
            margin-bottom: 6px;
        }

        .berun-day-cell {
            font-size: 12px;
            font-weight: 500;
            color: #9ea2aa;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 50%;
            margin: 0 auto;
            width: 32px;
            transition: all 0.2s ease;
        }
        .berun-day-cell:hover { color: #ffffff; background: rgba(255,255,255,0.1); }

        .berun-day-cell.active-yellow {
            background-color: var(--color-yellow) !important;
            color: var(--color-text-dark) !important;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(255, 209, 59, 0.4);
        }

        .berun-day-cell.outline-circle {
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            color: #e2e4e8;
        }

        .berun-calendar-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 11px;
            color: #8a8e98;
            font-weight: 500;
        }

        .berun-cal-legend { display: flex; align-items: center; gap: 6px; }
        .berun-cal-dot-outline { width: 8px; height: 8px; border-radius: 50%; border: 1.5px solid #8a8e98; }
        .berun-cal-dot-yellow { width: 8px; height: 8px; border-radius: 50%; background-color: var(--color-yellow); }

        /* Row 2: Quick Widgets Grid */
        .berun-row-bottom {
            display: grid;
            grid-template-columns: 0.9fr 0.9fr 1.6fr;
            gap: 24px;
        }

        .berun-widget-card {
            background: var(--bg-white);
            border-radius: 26px;
            padding: 24px 26px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            height: 100%;
        }

        .berun-widget-title { font-size: 16px; font-weight: 700; margin: 0; color: var(--color-text-dark); }
        .berun-widget-sub { font-size: 12px; color: var(--color-text-muted); margin: 2px 0 0 0; font-weight: 500; }

        /* Gauge Progress Arc Widget */
        .berun-gauge-container {
            position: relative;
            width: 150px;
            height: 95px;
            margin: 12px auto 6px auto;
            display: flex;
            justify-content: center;
        }

        .berun-gauge-svg { width: 150px; height: 100px; }
        .berun-gauge-center { position: absolute; bottom: 4px; text-align: center; }
        .berun-gauge-label { font-size: 10px; font-weight: 600; color: #9ea0a5; text-transform: uppercase; }
        .berun-gauge-val { font-size: 18px; font-weight: 800; color: var(--color-text-dark); line-height: 1.1; }

        .berun-gauge-tag {
            position: absolute;
            right: -10px;
            top: 22px;
            background: var(--bg-white);
            border-radius: 9999px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 700;
            color: var(--color-text-dark);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            border: 1px solid #f0ebd9;
        }

        .berun-pill-btn-action {
            background-color: var(--bg-white);
            border: 1px solid #e8e4d9;
            border-radius: 9999px;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 700;
            color: var(--color-text-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.2s ease;
        }
        .berun-pill-btn-action:hover { border-color: var(--color-text-dark); background: #fcfbf7; }

        .berun-pill-btn-icon {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background-color: var(--bg-dark);
            color: var(--color-yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        /* Pass Check-in Rate / Linear Progress Card */
        .berun-progress-header { display: flex; align-items: center; justify-content: space-between; }
        .berun-pct-badge { font-size: 15px; font-weight: 800; color: var(--color-text-dark); }
        .berun-pct-sub { font-size: 11px; font-weight: 500; color: var(--color-text-muted); display: block; text-align: right; }

        .berun-track-wrapper { position: relative; margin: 32px 0 16px 0; }
        .berun-progress-track { height: 14px; background-color: #eee9df; border-radius: 9999px; overflow: hidden; position: relative; }
        .berun-progress-fill { height: 100%; background-color: var(--bg-dark); border-radius: 9999px; }

        .berun-marker-pill {
            position: absolute;
            top: -26px;
            transform: translateX(-50%);
            background-color: var(--bg-dark);
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 9999px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            white-space: nowrap;
        }
        .berun-marker-pill::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 4px 4px 0 4px;
            border-style: solid;
            border-color: var(--bg-dark) transparent transparent transparent;
        }

        .berun-track-labels { display: flex; justify-content: space-between; font-size: 11px; font-weight: 700; color: var(--color-text-dark); }

        /* Recent Registrations List Widget */
        .berun-habits-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }

        .berun-btn-add {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }
        .berun-btn-add:hover { background-color: #2e343b; }

        .berun-habits-list { display: flex; flex-direction: column; gap: 10px; }

        .berun-habit-row {
            background-color: var(--bg-row);
            border-radius: 18px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .berun-habit-row:hover { background-color: #ebe7db; transform: translateX(2px); }

        .berun-habit-left { display: flex; align-items: center; gap: 12px; }
        .berun-habit-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(0,0,0,0.06); background: #e2dbcd; }
        .berun-habit-name { font-size: 13px; font-weight: 700; color: var(--color-text-dark); margin: 0; line-height: 1.2; }
        .berun-habit-sub { font-size: 11px; color: var(--color-text-muted); margin: 2px 0 0 0; font-weight: 500; }
        .berun-habit-right { display: flex; align-items: center; gap: 16px; }
        .berun-sessions-text { font-size: 11px; color: var(--color-text-muted); font-weight: 600; }
        .berun-sessions-text strong { color: var(--color-text-dark); }

        .berun-strength-bars { display: flex; align-items: center; gap: 3px; }
        .berun-bar-pill { width: 4px; height: 14px; border-radius: 9999px; background-color: #ded9cd; }
        .berun-bar-pill.active-red { background-color: #ff5b4f; }
        .berun-more-dots { color: #9ea0a5; font-size: 16px; cursor: pointer; padding: 0 4px; }

        /* =========================================================
           8 KPI STAT CARDS & ANALYTICS CARDS (BE.RUN THEME STYLING)
           ========================================================= */
        .berun-card-panel {
            background: var(--bg-white);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
            height: 100%;
        }

        .berun-stat-card {
            background: var(--bg-white);
            border-radius: 22px;
            padding: 20px 22px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.02);
            transition: all 0.25s ease;
            border: 1px solid rgba(0,0,0,0.02);
            height: 100%;
        }
        .berun-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        }

        .berun-stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-primary { background: #e8edff; color: #4f46e5; }
        .icon-success { background: #e8f7ef; color: #198754; }
        .icon-warning { background: #fef3c7; color: #d97706; }
        .icon-info    { background: #e0f2fe; color: #0284c7; }
        .icon-danger  { background: #fdecec; color: #dc3545; }
        .icon-purple  { background: #f3e8ff; color: #9333ea; }

        .berun-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f2eee6;
        }

        .berun-panel-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-text-dark);
            margin: 0;
        }
        .berun-panel-sub { font-size: 12px; color: var(--color-text-muted); margin: 2px 0 0 0; }

        .chart-tab-btn {
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 9999px;
            background: #f4f2eb;
            color: #6b7280;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .chart-tab-btn.active, .chart-tab-btn:hover {
            background: var(--bg-dark);
            color: var(--color-yellow);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-success { background: #e8f7ef; color: #198754; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-danger  { background: #fdecec; color: #dc3545; }
        .badge-info    { background: #e0f2fe; color: #0284c7; }

        .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%; background-color: #198754;
            box-shadow: 0 0 8px rgba(25, 135, 84, 0.5); display: inline-block;
            animation: evenzaPulse 1.8s infinite;
        }
        @keyframes evenzaPulse {
            0%,100%{ opacity: 1; transform: scale(1); }
            50%{ opacity: .4; transform: scale(1.4); }
        }

        /* Controls & Selects */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 9px 14px;
            font-size: 13px;
            color: #374151;
            background-color: #ffffff;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--color-text-dark);
            box-shadow: 0 0 0 3px rgba(28, 32, 36, 0.1);
        }

        /* Table styling in Be.run theme */
        .table thead th {
            background: #f9f8f4;
            color: #7c7d7e;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid #edf0f5;
            cursor: pointer;
            user-select: none;
        }
        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            color: #374151;
            border-bottom: 1px solid #f4f2eb;
            font-size: 13px;
        }
        .table tbody tr { transition: 0.2s ease; cursor: pointer; }
        .table tbody tr:hover { background-color: #f9f8f4; }

        /* Modal Backdrop */
        .evenza-modal-backdrop {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
        }
        .evenza-modal-backdrop.show { opacity: 1; pointer-events: auto; }
        .evenza-modal-content {
            background: #ffffff; border-radius: 24px; border: 0; width: 90%; max-width: 550px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2); transform: scale(0.95); transition: transform 0.25s ease; overflow: hidden;
        }
        .evenza-modal-backdrop.show .evenza-modal-content { transform: scale(1); }

        /* Responsive Breakpoints & Fluid Adaptations */
        @media (max-width: 1399px) {
            .berun-row-top { grid-template-columns: 1.2fr 1fr; gap: 20px; }
            .berun-row-bottom { grid-template-columns: 1fr 1fr 1.4fr; gap: 20px; }
            .berun-bubble-yellow { width: 155px; height: 155px; right: 10%; }
            .berun-bubble-red { width: 115px; height: 115px; left: 24%; }
            .berun-bubble-dark { left: 14%; }
        }

        @media (max-width: 1199px) {
            .berun-row-top { grid-template-columns: 1fr; }
            .berun-row-bottom { grid-template-columns: 1fr 1fr; }
            .berun-row-bottom > :nth-child(3) { grid-column: span 2; }
            .berun-hero-card { min-height: 300px; }
            .berun-bubble-yellow { right: 20%; }
            .berun-bubble-red { left: 30%; }
            .berun-bubble-dark { left: 22%; }
        }

        @media (max-width: 991px) {
            .berun-row-bottom { grid-template-columns: 1fr; }
            .berun-row-bottom > :nth-child(3) { grid-column: span 1; }
            .berun-search-wrapper { width: 220px; }
        }

        @media (max-width: 768px) {
            body { padding: 12px; }
            .berun-window { padding: 20px 18px; border-radius: 24px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-header > div:last-child { width: 100%; justify-content: space-between; }
            .berun-search-wrapper { flex-grow: 1; width: auto; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
            .berun-sidebar-capsule { width: 100%; flex-direction: row; height: 60px; padding: 0 16px; border-radius: 9999px; }
            .berun-nav-group { flex-direction: row; justify-content: space-around; width: auto; flex-grow: 1; }
            .berun-bubbles-art { height: 170px; }
            .berun-bubble-yellow { width: 135px; height: 135px; right: 8%; }
            .berun-bubble-red { width: 105px; height: 105px; left: 18%; }
            .berun-bubble-dark { padding: 8px 14px; left: 10%; top: 10px; }
            .berun-habit-row { flex-direction: column; align-items: flex-start; gap: 10px; }
            .berun-habit-right { width: 100%; justify-content: space-between; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/evenza-premium.css">
    <script src="../assets/js/tab-auth.js"></script>
    <link rel="stylesheet" href="../assets/css/inline-validation.css">
    <script src="../assets/js/inline-validation.js" defer></script>
</head>

<body>

    <!-- Main Outer Window Frame -->
    <div class="berun-window">

        <!-- Top Header Navigation Bar -->
        <header class="berun-header">
            <div class="d-flex align-items-center gap-4">
                <a href="Dashboard.php" class="d-inline-flex align-items-center text-decoration-none">
                    <img src="../assets/images/evenza-logo.svg" alt="Evenza Logo" height="38" style="height: 38px; width: auto;" />
                </a>

                <div class="ps-2">
                    <div class="d-flex align-items-center gap-2">
                        <h1 class="berun-greeting-h1">Hi, <?php echo htmlspecialchars($admin_name); ?>!</h1>
                        <span class="status-badge badge-success text-xs">
                            <span class="pulse-dot me-1"></span> Workspace ready
                        </span>
                    </div>
                    <p class="berun-greeting-sub">A clear view of your events, community, and growth</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <select id="datePresetSelect" class="form-select form-select-sm border-0 bg-white rounded-pill px-3 py-2 shadow-sm text-xs font-semibold" style="max-width:140px;" onchange="handleDatePresetChange(this.value)">
                    <option value="30">Last 30 Days</option>
                    <option value="7">Last 7 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="all" selected>All Time</option>
                </select>

                <div class="berun-search-wrapper">
                    <i class="bi bi-search berun-search-icon"></i>
                    <input type="text" id="liveSearchInput" class="berun-search-input" placeholder="Search student, event, college..." oninput="applyFilters()" />
                </div>

                <div class="dropdown">
                    <button class="berun-btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2 mt-2">
                        <li><h6 class="dropdown-header">Quick actions</h6></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-xs" href="javascript:void(0)" onclick="exportCSVData()"><i class="bi bi-download me-2"></i> Export CSV Report</a></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-xs" href="javascript:void(0)" onclick="resetFilters()"><i class="bi bi-arrow-clockwise me-2"></i> Reset Search &amp; Filters</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-xs text-primary" href="alluniversity.php"><i class="bi bi-globe me-2"></i> All Universities</a></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-xs text-primary" href="Colleges.php"><i class="bi bi-building me-2"></i> All Colleges</a></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-xs text-primary" href="all_events.php"><i class="bi bi-calendar-event me-2"></i> All Events</a></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-xs text-primary" href="allregistrations.php"><i class="bi bi-ticket-perforated me-2"></i> All Registrations</a></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-xs text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Body Area with Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">



                <!-- SECTION 3: 8 KPI STAT CARDS (STYLING IN BE.RUN THEME) -->
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="fw-bold text-dark mb-0 text-md">Platform Statistics Overview</h4>
                        <span class="text-xs text-muted font-semibold">Platform activity</span>
                    </div>

                    <div class="row g-3">
                        <!-- Total Students Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Students</span>
                                        <h2 id="kpi-students-val" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 24px;"><?php echo number_format($total_students); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-primary">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #e8edff; border-radius: 9999px;">
                                    <div id="kpi-students-bar" class="progress-bar" style="width: 0%; background-color: #4f46e5; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Events Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Events</span>
                                        <h2 id="kpi-events-val" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 24px;"><?php echo number_format($total_events); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-info">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #e0f2fe; border-radius: 9999px;">
                                    <div id="kpi-events-bar" class="progress-bar" style="width: 0%; background-color: #0284c7; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Registrations Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Registrations</span>
                                        <h2 id="kpi-regs-val" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 24px;"><?php echo number_format($total_regs); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-purple">
                                        <i class="bi bi-ticket-perforated"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #f3e8ff; border-radius: 9999px;">
                                    <div id="kpi-regs-bar" class="progress-bar" style="width: 0%; background-color: #9333ea; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Revenue Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Revenue</span>
                                        <h2 id="kpi-rev-val" class="mb-0 fw-extrabold mt-1" style="color:#198754; font-size: 24px;">₹<?php echo number_format($total_revenue, 2); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-success">
                                        <i class="bi bi-currency-rupee"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #e8f7ef; border-radius: 9999px;">
                                    <div id="kpi-rev-bar" class="progress-bar" style="width: 0%; background-color: #198754; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Colleges Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Active Colleges</span>
                                        <h2 id="kpi-colleges-val" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 24px;"><?php echo number_format($total_colleges); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-info">
                                        <i class="bi bi-building"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #e0f2fe; border-radius: 9999px;">
                                    <div id="kpi-colleges-bar" class="progress-bar" style="width: 0%; background-color: #0284c7; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Passes Issued Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Passes Issued</span>
                                        <h2 id="kpi-issued-val" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 24px;"><?php echo number_format($passes_issued); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-warning">
                                        <i class="bi bi-qr-code"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #fef3c7; border-radius: 9999px;">
                                    <div id="kpi-issued-bar" class="progress-bar" style="width: 0%; background-color: #d97706; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Passes Used Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Passes Used</span>
                                        <h2 id="kpi-used-val" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 24px;"><?php echo number_format($passes_used); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-success">
                                        <i class="bi bi-check2-all"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #e8f7ef; border-radius: 9999px;">
                                    <div id="kpi-used-bar" class="progress-bar" style="width: 0%; background-color: #198754; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Cancelled Card -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="berun-stat-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Cancelled</span>
                                        <h2 id="kpi-cancelled-val" class="mb-0 fw-extrabold mt-1" style="color:#dc3545; font-size: 24px;"><?php echo number_format($cancelled_regs); ?></h2>
                                    </div>
                                    <div class="berun-stat-icon icon-danger">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 5px; background-color: #fdecec; border-radius: 9999px;">
                                    <div id="kpi-cancelled-bar" class="progress-bar" style="width: 0%; background-color: #dc3545; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 4: CHARTS ROW 1 (TIMELINE LINE CHART & DONUT RATIO CHART) -->
                <div class="row g-4">
                    <div class="col-12 col-xl-7">
                        <div class="berun-card-panel">
                            <div class="berun-panel-header">
                                <div>
                                    <h3 class="berun-panel-title">Registrations Timeline</h3>
                                    <p class="berun-panel-sub">Registration activity over time</p>
                                </div>
                                <div class="d-flex gap-1 flex-wrap" id="lineChartTabs">
                                    <button type="button" class="chart-tab-btn active" data-period="all" onclick="changeLineChartPeriod('all', this)">All</button>
                                    <button type="button" class="chart-tab-btn" data-period="30" onclick="changeLineChartPeriod('30', this)">30D</button>
                                    <button type="button" class="chart-tab-btn" data-period="7" onclick="changeLineChartPeriod('7', this)">7D</button>
                                    <button type="button" class="chart-tab-btn" data-period="90" onclick="changeLineChartPeriod('90', this)">90D</button>
                                </div>
                            </div>
                            <div style="height: 270px; position: relative;">
                                <canvas id="lineChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-5">
                        <div class="berun-card-panel">
                            <div class="berun-panel-header">
                                <div>
                                    <h3 class="berun-panel-title">Pass Check-in Ratio</h3>
                                    <p class="berun-panel-sub">Issued vs checked-in status</p>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-center justify-content-center pt-2">
                                <div style="height: 200px; width: 200px; position: relative;">
                                    <canvas id="donutChart"></canvas>
                                </div>
                                <div class="d-flex align-items-center gap-4 mt-3">
                                    <span class="small text-muted d-flex align-items-center gap-2">
                                        <span style="width:8px;height:8px;border-radius:50%;background:#198754;display:inline-block;"></span>
                                        Used · <strong id="donut-used-pct" class="text-dark">0.0%</strong>
                                    </span>
                                    <span class="small text-muted d-flex align-items-center gap-2">
                                        <span style="width:8px;height:8px;border-radius:50%;background:#e5e7eb;display:inline-block;"></span>
                                        Unused · <strong id="donut-unused-pct" class="text-dark">0.0%</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: CHARTS ROW 2 (REVENUE BAR CHART & TOP EVENTS HBAR CHART) -->
                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="berun-card-panel">
                            <div class="berun-panel-header">
                                <div>
                                    <h3 class="berun-panel-title">Revenue Trend</h3>
                                    <p class="berun-panel-sub">Monthly earnings trajectory</p>
                                </div>
                                <div class="d-flex gap-1">
                                    <button type="button" class="chart-tab-btn active" onclick="toggleRevenueView('monthly', this)">Monthly</button>
                                    <button type="button" class="chart-tab-btn" onclick="toggleRevenueView('quarterly', this)">Quarterly</button>
                                </div>
                            </div>
                            <div style="height: 250px; position: relative;">
                                <canvas id="barChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="berun-card-panel">
                            <div class="berun-panel-header">
                                <div>
                                    <h3 class="berun-panel-title">Top Ranked Events</h3>
                                    <p class="berun-panel-sub">Events with highest registrations</p>
                                </div>
                            </div>
                            <div style="height: 250px; position: relative;">
                                <canvas id="hBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 6: PIE CHART & PLATFORM INSIGHTS CARDS -->
                <div class="row g-4">
                    <div class="col-12 col-xl-4">
                        <div class="berun-card-panel">
                            <div class="berun-panel-header">
                                <div>
                                    <h3 class="berun-panel-title">Team vs Solo Split</h3>
                                    <p class="berun-panel-sub">Participation format ratio</p>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-center justify-content-center pt-2">
                                <div style="height: 180px; width: 180px; position: relative;">
                                    <canvas id="pieChart"></canvas>
                                </div>
                                <div class="d-flex align-items-center gap-4 mt-3">
                                    <span class="small text-muted d-flex align-items-center gap-2">
                                        <span style="width:8px;height:8px;border-radius:50%;background:#4f46e5;display:inline-block;"></span>
                                        Team · <strong id="team-share" class="text-dark"><?php echo $team_pct; ?>%</strong>
                                    </span>
                                    <span class="small text-muted d-flex align-items-center gap-2">
                                        <span style="width:8px;height:8px;border-radius:50%;background:#0284c7;display:inline-block;"></span>
                                        Solo · <strong id="solo-share" class="text-dark"><?php echo $solo_pct; ?>%</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-8">
                        <div class="berun-card-panel">
                            <div class="berun-panel-header">
                                <div>
                                    <h3 class="berun-panel-title">Platform Insights</h3>
                                    <p class="berun-panel-sub">Registration and campus activity</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <div class="p-3 rounded-4 bg-light border-0 d-flex align-items-center gap-3" style="background:#f9f8f4 !important;">
                                        <div class="berun-stat-icon icon-success shrink-0"><i class="bi bi-trophy"></i></div>
                                        <div>
                                            <span class="text-muted text-uppercase font-semibold d-block" style="font-size:10px;">Top Performing Event</span>
                                            <h6 id="insight-event" class="mb-0 fw-bold text-dark"><?php echo !empty($top_events) ? htmlspecialchars($top_events[0]['title']) : 'No registrations'; ?></h6>
                                            <span id="insight-event-count" class="text-muted text-xs"><?php echo !empty($top_events) ? $top_events[0]['reg_count'] . ' registrations' : '0 registrations'; ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <div class="p-3 rounded-4 bg-light border-0 d-flex align-items-center gap-3" style="background:#f9f8f4 !important;">
                                        <div class="berun-stat-icon icon-info shrink-0"><i class="bi bi-building"></i></div>
                                        <div>
                                            <span class="text-muted text-uppercase font-semibold d-block" style="font-size:10px;">Active Campuses</span>
                                            <h6 id="insight-colleges" class="mb-0 fw-bold text-dark"><?php echo $total_colleges; ?> Partner Colleges</h6>
                                            <span class="text-muted text-xs"><?php echo $total_universities; ?> Universities Onboarded</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <div class="p-3 rounded-4 bg-light border-0 d-flex align-items-center gap-3" style="background:#f9f8f4 !important;">
                                        <div class="berun-stat-icon icon-warning shrink-0"><i class="bi bi-clock-history"></i></div>
                                        <div>
                                            <span class="text-muted text-uppercase font-semibold d-block" style="font-size:10px;">Registration Status</span>
                                            <h6 id="insight-status" class="mb-0 fw-bold text-dark"><?php echo $approved_regs; ?> Approved / <?php echo $pending_regs; ?> Pending</h6>
                                            <span class="text-muted text-xs">Database Verified</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <div class="p-3 rounded-4 bg-light border-0 d-flex align-items-center gap-3" style="background:#f9f8f4 !important;">
                                        <div class="berun-stat-icon icon-purple shrink-0"><i class="bi bi-graph-up-arrow"></i></div>
                                        <div>
                                            <span class="text-muted text-uppercase font-semibold d-block" style="font-size:10px;">Conversion Health</span>
                                            <h6 id="insight-approval" class="mb-0 fw-bold text-dark"><?php echo $total_regs ? round($approved_regs / $total_regs * 100, 1) : 0; ?>% Approval Rate</h6>
                                            <span class="text-muted text-xs">Approved registrations / total registrations</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 7: DYNAMIC FILTER & QUERY CONTROLS -->
                <div class="berun-card-panel">
                    <div class="berun-panel-header">
                        <div>
                            <h3 class="berun-panel-title">Dynamic Filter &amp; Query Controls</h3>
                            <p class="berun-panel-sub">Narrow down platform statistics and table data</p>
                        </div>
                        <span id="activeFilterBadge" class="status-badge badge-success" style="display:none;">
                            <i class="bi bi-funnel"></i> Filter Active
                        </span>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-3">
                            <label class="form-label text-muted text-uppercase font-semibold" style="font-size:11px;">Search Participant / ID</label>
                            <input type="text" id="filterSearch" class="form-control" placeholder="Search name, event, college..." oninput="applyFilters()" />
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-muted text-uppercase font-semibold" style="font-size:11px;">Event</label>
                            <select id="filterEvent" class="form-select" onchange="applyFilters()">
                                <option value="all">All Events (<?php echo count($events_list); ?>)</option>
                                <?php foreach ($events_list as $ev): ?>
                                    <option value="<?php echo htmlspecialchars($ev['title']); ?>"><?php echo htmlspecialchars($ev['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-muted text-uppercase font-semibold" style="font-size:11px;">College</label>
                            <select id="filterCollege" class="form-select" onchange="applyFilters()">
                                <option value="all">All Colleges (<?php echo count($colleges_list); ?>)</option>
                                <?php foreach ($colleges_list as $clg): ?>
                                    <option value="<?php echo htmlspecialchars($clg['name']); ?>"><?php echo htmlspecialchars($clg['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label text-muted text-uppercase font-semibold" style="font-size:11px;">Status</label>
                            <select id="filterStatus" class="form-select" onchange="applyFilters()">
                                <option value="all">All Status</option>
                                <option value="approved">Approved / Confirmed</option>
                                <option value="pending">Pending</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-1 d-flex gap-1">
                            <button type="button" class="btn btn-dark w-100 p-2 rounded-3" onclick="applyFilters()" title="Apply Filter">
                                <i class="bi bi-funnel"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary w-100 p-2 rounded-3" onclick="resetFilters()" title="Reset Filters">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 8: FULL DATA TABLES (REGISTRATIONS & PAYMENTS) -->
                <div class="row g-4">
                    <!-- Registrations Table -->
                    <div class="col-12 col-xl-7">
                        <div class="berun-card-panel p-0 overflow-hidden">
                            <div class="p-4 border-bottom d-flex align-items-center justify-content-between" style="border-color:#f4f2eb !important;">
                                <div>
                                    <h3 class="berun-panel-title">Recent Registrations</h3>
                                    <p class="berun-panel-sub">Latest 5 registrations ? Click a row for details</p>
                                </div>
                                <a href="allregistrations.php" class="text-decoration-none small">View all</a>
                                <span id="regCountBadge" class="status-badge badge-success"><?php echo min(5, count($db_registrations)); ?> of <?php echo count($db_registrations); ?></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4" onclick="sortTable('regTable', 0)">Participant</th>
                                            <th onclick="sortTable('regTable', 1)">Event</th>
                                            <th onclick="sortTable('regTable', 2)">Status</th>
                                            <th class="pe-4" onclick="sortTable('regTable', 3)">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="regTableBody">
                                        <!-- Populated dynamically via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Payments Table -->
                    <div class="col-12 col-xl-5">
                        <div class="berun-card-panel p-0 overflow-hidden">
                            <div class="p-4 border-bottom d-flex align-items-center justify-content-between" style="border-color:#f4f2eb !important;">
                                <div>
                                    <h3 class="berun-panel-title">Transactions &amp; Payments</h3>
                                    <p class="berun-panel-sub">Latest payment statuses (Click row for receipt)</p>
                                </div>
                                <span id="payCountBadge" class="status-badge badge-info"><?php echo count($db_payments); ?> Records</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4" onclick="sortTable('payTable', 0)">Txn ID</th>
                                            <th onclick="sortTable('payTable', 1)">Amount</th>
                                            <th onclick="sortTable('payTable', 2)">Status</th>
                                            <th class="pe-4" onclick="sortTable('payTable', 3)">Event</th>
                                        </tr>
                                    </thead>
                                    <tbody id="payTableBody">
                                        <!-- Populated dynamically via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

            </div>

        </div>

    </div>

    <!-- Record Details Modal -->
    <div id="detailModal" class="evenza-modal-backdrop">
        <div class="evenza-modal-content">
            <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
                <h5 id="modalTitle" class="mb-0 text-dark fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text text-primary"></i> Record Details
                </h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <div class="p-4" id="modalBody">
                <!-- Dynamic details injected here -->
            </div>
            <div class="p-3 bg-light d-flex justify-content-end gap-2 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill" onclick="closeModal()">Close</button>
                <button type="button" class="btn btn-dark btn-sm px-3 rounded-pill d-flex align-items-center gap-1" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Summary
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle & Chart.js -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/chart.umd.min.js"></script>

    <script>
        window.masterDataset = <?php echo json_encode([
            'students' => $overview_students, 'events' => $overview_events,
            'colleges' => $overview_colleges, 'passes' => $overview_passes,
            'members' => $overview_members,
            'registrations' => $db_registrations, 'payments' => $db_payments
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    </script>
    <script src="../assets/js/admin-overview.js"></script>
<?php include __DIR__ . '/../includes/event_clock.php'; ?>
</body>
</html>
