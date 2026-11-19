<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/event_registration.php';
$base=['status'=>'approved','event_status'=>'published','event_date'=>'2099-01-01','end_time'=>'17:00:00','pass_code'=>'PASS-TEST','is_used'=>0];
$cases=[
 [$base,'Active pass'],
 [array_replace($base,['is_used'=>1]),'Checked in'],
 [array_replace($base,['event_date'=>'2020-01-01']),'Expired'],
 [array_replace($base,['event_status'=>'completed']),'Expired'],
 [array_replace($base,['event_status'=>'cancelled']),'Cancelled'],
 [array_replace($base,['status'=>'cancelled']),'Cancelled'],
 [array_replace($base,['status'=>'pending']),'Awaiting approval'],
 [array_replace($base,['pass_code'=>null]),'Awaiting pass'],
 [array_replace($base,['expires_at'=>'2020-01-01 00:00:00']),'Expired'],
 [array_replace($base,['event_status'=>'draft']),'Unavailable']
];
foreach($cases as [$row,$expected]) if(evenza_pass_status($row)!==$expected) throw new RuntimeException('Pass state mismatch: '.$expected);
foreach (['draft','cancelled','completed'] as $status) if (evenza_registration_open(array_replace($base,['status'=>$status]))) throw new RuntimeException('Closed event accepted');
if (evenza_registration_open(array_replace($base,['status'=>'published','registration_deadline'=>'2020-01-01 10:00:00']))) throw new RuntimeException('Past deadline accepted');
require __DIR__ . '/../includes/connection.php';$conn=get_evenza_connection();
mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_ONLY);
try {
 $payments=evenza_payment_join_sql();$passes=evenza_pass_join_sql();
 $res=mysqli_query($conn,"SELECT r.*, e.event_date,e.end_time,e.status AS event_status,ep.pass_code,ep.is_used,ep.expires_at,COALESCE(p.amount,0) AS amount FROM registrations r JOIN events e ON e.event_id=r.event_id $payments $passes");
 if(!$res) throw new RuntimeException(mysqli_error($conn));
 $ids=[];$states=[];
 while($row=mysqli_fetch_assoc($res)) { if(isset($ids[$row['registration_id']])) throw new RuntimeException('Duplicate registration join');$ids[$row['registration_id']]=true;$states[evenza_pass_status($row)]=($states[evenza_pass_status($row)]??0)+1; }
 $raw=mysqli_query($conn,'SELECT COUNT(*) AS n FROM registrations');if(count($ids)!==(int)mysqli_fetch_assoc($raw)['n']) throw new RuntimeException('Registration count mismatch');
 echo '14 policy checks passed; '.count($ids).' joined registrations, no duplicates. Pass states: '.json_encode($states).". No writes.\n";
} finally { mysqli_rollback($conn); }
