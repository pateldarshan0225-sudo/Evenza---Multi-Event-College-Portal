<?php
/**
 * Admin/Index.php
 * Redirects to Unified Login Page in Frontend/login.php
 */
session_start();

if (isset($_SESSION['admin_id']) || (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true)) {
    header("Location: Dashboard.php");
    exit();
}

header("Location: ../Frontend/login.php");
exit();