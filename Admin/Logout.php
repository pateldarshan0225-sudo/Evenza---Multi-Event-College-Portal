<?php
/**
 * Admin/Logout.php
 * Admin Session Cleanup Using unset() (Without session_destroy)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_name'],
    $_SESSION['admin_email'],
    $_SESSION['admin_logged_in'],
    $_SESSION['loggedin']
);

header("Location: ../Frontend/login.php");
exit();