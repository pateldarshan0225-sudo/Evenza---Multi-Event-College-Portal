<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["admin_id"]) && (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true)) {
    header("Location: ../Frontend/login.php");
    exit();
}
?>