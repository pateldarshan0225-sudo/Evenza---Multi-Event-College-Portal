<?php
/**
 * Frontend/logout.php
 * Tab-Isolated Logout Handler
 */
require_once __DIR__ . '/../tab_auth.php';

unset_tab_auth();

header('Location: ' . tab_context_url('login.php'));
exit;
