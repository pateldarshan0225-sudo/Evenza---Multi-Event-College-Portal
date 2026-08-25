<?php
/**
 * Frontend/logout.php
 * Universal Session Cleanup Using unset() (Without session_destroy)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all authentication session keys selectively without destroying the session handler
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

header('Location: login.php');
exit;
