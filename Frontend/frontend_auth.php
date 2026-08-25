<?php
/**
 * Frontend/frontend_auth.php
 * Session Authentication & User State Helper (Fixed Role Priority & Session Unset)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Support unsetting authentication session state without destroying session
if (isset($_GET['unset_session']) || isset($_GET['unset'])) {
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_email'],
        $_SESSION['admin_logged_in'],
        $_SESSION['loggedin'],
        $_SESSION['student_id'],
        $_SESSION['student_name'],
        $_SESSION['student_email'],
        $_SESSION['student_logged_in'],
        $_SESSION['student_college_id'],
        $_SESSION['college_id'],
        $_SESSION['college_name'],
        $_SESSION['college_email'],
        $_SESSION['college_logo'],
        $_SESSION['organizer_logged_in']
    );
}

$is_logged_in = false;
$user_role    = null; // 'admin', 'organizer', 'student'
$user_name    = '';
$user_email   = '';
$user_id      = null;

// 1. Check Admin Session
if (!empty($_SESSION['admin_logged_in']) || isset($_SESSION['admin_id'])) {
    $is_logged_in = true;
    $user_role    = 'admin';
    $user_name    = $_SESSION['admin_name'] ?? 'System Admin';
    $user_email   = $_SESSION['admin_email'] ?? 'admin@evenza.com';
    $user_id      = $_SESSION['admin_id'];
}
// 2. Check Student Session (Higher Priority than College ID)
elseif (!empty($_SESSION['student_logged_in']) || isset($_SESSION['student_id'])) {
    $is_logged_in = true;
    $user_role    = 'student';
    $user_name    = $_SESSION['student_name'] ?? 'Student User';
    $user_email   = $_SESSION['student_email'] ?? '';
    $user_id      = $_SESSION['student_id'];
}
// 3. Check College Organizer Session (Only if NOT a student)
elseif (!empty($_SESSION['organizer_logged_in']) || (isset($_SESSION['college_id']) && empty($_SESSION['student_id']))) {
    $is_logged_in = true;
    $user_role    = 'organizer';
    $user_name    = $_SESSION['college_name'] ?? 'College Organizer';
    $user_email   = $_SESSION['college_email'] ?? 'organizer@evenza.com';
    $user_id      = $_SESSION['college_id'];
}
