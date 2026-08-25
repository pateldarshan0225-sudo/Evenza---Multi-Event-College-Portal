<?php
/**
 * Organizer/Logout.php
 * Organizer Session Cleanup Using unset() (Without session_destroy)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['college_id'],
    $_SESSION['college_name'],
    $_SESSION['college_email'],
    $_SESSION['college_logo'],
    $_SESSION['organizer_logged_in']
);

header("Location: ../Frontend/login.php");
exit();
