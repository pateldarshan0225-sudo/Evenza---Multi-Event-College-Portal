<?php
/**
 * Frontend/frontend_auth.php
 * Isolated Public Website Session Helper (Student Scope Only)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = false;
$user_role    = 'student';
$user_name    = '';
$user_email   = '';
$user_id      = null;

// Only active Student sessions show logged-in state on public website header
if (!empty($_SESSION['student_logged_in']) && !empty($_SESSION['student_id'])) {
    $is_logged_in = true;
    $user_role    = 'student';
    $user_name    = $_SESSION['student_name'] ?? 'Student User';
    $user_email   = $_SESSION['student_email'] ?? '';
    $user_id      = $_SESSION['student_id'];
}
