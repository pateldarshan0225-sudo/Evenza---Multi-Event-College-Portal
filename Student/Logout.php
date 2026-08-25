<?php
/**
 * Student/Logout.php
 * Tab-Isolated Logout Handler for Student Portal
 */
require_once __DIR__ . '/../tab_auth.php';

unset_tab_auth();

header("Location: ../Frontend/login.php");
exit();
