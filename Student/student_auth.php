<?php
/**
 * Student/student_auth.php
 * Tab-Isolated Authentication Guard for Student Portal
 */
require_once __DIR__ . '/../tab_auth.php';

$stu_auth = require_tab_role('student');

$student_id    = (int)$stu_auth['user_id'];
$student_name  = $stu_auth['name'] ?? 'Student User';
$student_email = $stu_auth['email'] ?? '';
$college_id    = (int)($stu_auth['college_id'] ?? 0);
