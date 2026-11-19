<?php
/**
 * Organizer/Logout.php
 * Tab-Isolated Logout Handler for Organizer Portal
 */
require_once __DIR__ . '/../tab_auth.php';

unset_tab_auth();

header('Location: ' . tab_context_url('../Frontend/login.php'));
exit();
