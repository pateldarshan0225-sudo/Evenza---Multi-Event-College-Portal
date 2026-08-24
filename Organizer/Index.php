<?php
/**
 * Organizer/Index.php
 * Redirects to Unified Login Page in Frontend/login.php
 */
session_start();

if (isset($_SESSION['college_id']) || (isset($_SESSION['organizer_logged_in']) && $_SESSION['organizer_logged_in'] === true)) {
    header("Location: Dashboard.php");
    exit();
}

header("Location: ../Frontend/login.php");
exit();
