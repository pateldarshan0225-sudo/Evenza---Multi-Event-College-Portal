<?php
// CLI-only validation checks inside the existing read-only rendering transaction.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$argv[1] = 'Organizer/Events.php';
ob_start();
require __DIR__ . '/render-audit.php';
ob_end_clean();
$category = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT category_id FROM categories ORDER BY category_id LIMIT 1'));
$valid = ['category_id' => (string)$category['category_id'], 'title' => 'Validation example', 'description' => 'A sufficiently detailed example event description.',
    'event_type' => 'solo', 'min_team_size' => '1', 'max_team_size' => '1', 'fee_type' => 'per_person', 'registration_fee' => '0',
    'event_date' => '2026-11-25', 'start_time' => '09:00', 'end_time' => '17:00', 'venue' => 'Main hall', 'dress_code' => 'Casual', 'status' => 'draft'];
$cases = [
    'Valid details are accepted without saving' => [$valid, null],
    'Array fields rejected without a PHP error' => [array_replace($valid, ['title' => []]), 'valid event details'],
    'Invalid start time rejected' => [array_replace($valid, ['start_time' => '99:99']), 'valid start time'],
    'Invalid end time rejected' => [array_replace($valid, ['end_time' => '25:00']), 'valid end time'],
    'Reversed time range rejected' => [array_replace($valid, ['start_time' => '18:00']), 'End time'],
    'Impossible date rejected' => [array_replace($valid, ['event_date' => '2026-02-31']), 'valid event date'],
    'Negative registration fee rejected' => [array_replace($valid, ['registration_fee' => '-5']), 'registration fee'],
    'Invalid team size rejected' => [array_replace($valid, ['event_type' => 'team', 'min_team_size' => '5', 'max_team_size' => '2']), 'Maximum team size'],
];
$failures = [];
foreach ($cases as $name => [$data, $expected]) {
    $errors = validate_organizer_event($conn, $data);
    if ($expected === null ? count($errors) !== 0 : !str_contains(implode(' ', $errors), $expected)) { $failures[] = $name; }
}
echo json_encode(['checks' => count($cases), 'failures' => $failures]), PHP_EOL;
exit($failures ? 1 : 0);
