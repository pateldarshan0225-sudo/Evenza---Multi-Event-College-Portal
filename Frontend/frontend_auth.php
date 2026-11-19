<?php
/**
 * Frontend/frontend_auth.php
 * Session Authentication & User State Helper (Tab-Isolated)
 */
require_once __DIR__ . '/../tab_auth.php';

// Support unsetting current tab authentication state without destroying session
if (isset($_GET['unset_session']) || isset($_GET['unset'])) {
    unset_tab_auth();
}

$is_logged_in = false;
$user_role    = null; // 'admin', 'organizer', 'student'
$user_name    = '';
$user_email   = '';
$user_id      = null;

$tab_auth = get_tab_auth();

if ($tab_auth && is_array($tab_auth)) {
    $is_logged_in = true;
    $user_role    = $tab_auth['role'] ?? null;
    $user_name    = $tab_auth['name'] ?? '';
    $user_email   = $tab_auth['email'] ?? '';
    $user_id      = $tab_auth['user_id'] ?? null;
}
