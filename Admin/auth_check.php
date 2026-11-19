<?php
/**
 * Admin/auth_check.php
 * Tab-Isolated Authentication Guard for Admin Portal
 */
require_once __DIR__ . '/../tab_auth.php';

$admin_auth = require_tab_role('admin');

$admin_id    = (int)$admin_auth['user_id'];
$admin_name  = $admin_auth['name'] ?? 'Administrator';
$admin_email = $admin_auth['email'] ?? '';