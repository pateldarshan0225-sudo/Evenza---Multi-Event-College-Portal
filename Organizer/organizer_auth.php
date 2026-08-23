<?php
/**
 * Organizer/organizer_auth.php
 * Authentication Middleware for Organizer Panel
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['organizer_logged_in']) || $_SESSION['organizer_logged_in'] !== true || empty($_SESSION['college_id'])) {
    header("Location: Index.php");
    exit();
}

$college_id   = (int)$_SESSION['college_id'];
$college_name = $_SESSION['college_name'] ?? 'College Organizer';
$college_email = $_SESSION['college_email'] ?? '';
$college_logo  = $_SESSION['college_logo'] ?? '';
?>
