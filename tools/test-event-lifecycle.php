<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/event_lifecycle.php';
$zone = new DateTimeZone('Asia/Kolkata');
$now = new DateTimeImmutable('2026-10-25 15:00:00', $zone);
$cases = [
 ['2026-10-24','17:00:00',true],
 ['2026-10-25','14:59:59',true],
 ['2026-10-25','15:00:00',true],
 ['2026-10-25','15:00:01',false],
 ['2026-10-26','09:00:00',false],
 ['2026-10-25',null,false],
 ['2026-10-24',null,true],
 ['2026-10-25','16:00',false],
 ['invalid','17:00:00',false],
];
foreach ($cases as [$date,$time,$expected]) {
 if (evenza_event_has_ended(['event_date'=>$date,'end_time'=>$time],$now) !== $expected) throw new RuntimeException('Lifecycle boundary failed');
}
foreach (['draft','cancelled','completed'] as $status) {
 if (evenza_event_status(['status'=>$status,'event_date'=>'2020-01-01','end_time'=>'10:00:00']) !== $status) throw new RuntimeException('Explicit status changed');
}
require __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();
mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_ONLY);
try {
 $sql = evenza_active_event_sql();
 $res = mysqli_query($conn,"SELECT e.*, $sql AS visible FROM events e");
 if (!$res) throw new RuntimeException(mysqli_error($conn));
 $count=0;
 while ($event=mysqli_fetch_assoc($res)) {
  $expected = $event['status'] === 'published' && !evenza_event_has_ended($event);
  if ((bool)$event['visible'] !== $expected) throw new RuntimeException('SQL/PHP lifecycle mismatch');
  $count++;
 }
 echo "12 boundary/status checks passed; SQL/PHP agreement for $count stored events. No writes.\n";
} finally { mysqli_rollback($conn); }
