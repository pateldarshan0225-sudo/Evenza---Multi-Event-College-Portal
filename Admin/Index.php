<?php
require_once __DIR__ . '/auth_check.php';
header('Location: ' . tab_context_url('Dashboard.php'));
exit;
