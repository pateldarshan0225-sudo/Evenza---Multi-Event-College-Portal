<?php
/**
 * Frontend/frontend_auth.php
 * Session Authentication & User State Helper
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = false;
$user_role    = null; // 'admin', 'organizer', 'student'
$user_name    = '';
$user_email   = '';
$user_id      = null;

if (isset($_SESSION['admin_id'])) {
    $is_logged_in = true;
    $user_role    = 'admin';
    $user_name    = $_SESSION['admin_name'] ?? 'System Admin';
    $user_email   = $_SESSION['admin_email'] ?? 'admin@evenza.com';
    $user_id      = $_SESSION['admin_id'];
} elseif (isset($_SESSION['college_id'])) {
    $is_logged_in = true;
    $user_role    = 'organizer';
    $user_name    = $_SESSION['college_name'] ?? 'College Organizer';
    $user_email   = $_SESSION['college_email'] ?? 'organizer@evenza.com';
    $user_id      = $_SESSION['college_id'];
} elseif (isset($_SESSION['student_id'])) {
    $is_logged_in = true;
    $user_role    = 'student';
    $user_name    = $_SESSION['student_name'] ?? 'Student User';
    $user_email   = $_SESSION['student_email'] ?? '';
    $user_id      = $_SESSION['student_id'];
}
