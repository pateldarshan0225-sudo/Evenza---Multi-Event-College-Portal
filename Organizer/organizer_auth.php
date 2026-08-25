<?php
/**
 * Organizer/organizer_auth.php
 * Tab-Isolated Authentication Guard for College Organizer Panel
 */
require_once __DIR__ . '/../tab_auth.php';

$org_auth = require_tab_role('organizer');

$college_id    = (int)($org_auth['college_id'] ?? $org_auth['user_id']);
$college_name  = $org_auth['name'] ?? 'College Organizer';
$college_email = $org_auth['email'] ?? '';
$college_logo  = $org_auth['logo'] ?? '';
