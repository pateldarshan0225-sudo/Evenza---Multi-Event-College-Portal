<?php
/**
 * Frontend/dashboard.php
 * Redirects to Dedicated Student Portal Dashboard in Student/Dashboard.php
 */
session_start();
header("Location: ../Student/Dashboard.php");
exit();
