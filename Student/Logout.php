<?php
/**
 * Student/Logout.php
 * Student Session Cleanup Using unset() (Without session_destroy)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['student_id'],
    $_SESSION['student_name'],
    $_SESSION['student_email'],
    $_SESSION['student_logged_in'],
    $_SESSION['student_college_id'],
    $_SESSION['college_id']
);

header("Location: ../Frontend/login.php");
exit();
