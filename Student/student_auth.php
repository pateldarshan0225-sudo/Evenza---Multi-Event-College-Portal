<?php
/**
 * Student/student_auth.php
 * Authentication Guard for Student Portal
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['student_id'])) {
    header("Location: ../Frontend/login.php");
    exit();
}

$student_id    = (int)$_SESSION['student_id'];
$student_name  = $_SESSION['student_name'] ?? 'Student User';
$student_email = $_SESSION['student_email'] ?? '';
$college_id    = (int)($_SESSION['college_id'] ?? 0);
?>
