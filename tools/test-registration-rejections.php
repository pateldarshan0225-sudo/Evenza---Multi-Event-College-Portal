<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/event_registration.php';
require __DIR__ . '/../includes/connection.php';$conn=get_evenza_connection();
function snapshot(mysqli $conn): array {
 $result=[];
 foreach(['registrations','entry_passes','payments','teams','team_members'] as $table) {
  $rows=mysqli_query($conn,"SELECT * FROM $table");if(!$rows)throw new RuntimeException('Snapshot query failed');
  $data=[];while($r=mysqli_fetch_assoc($rows))$data[]=json_encode($r);sort($data);$result[$table]=hash('sha256',implode('|',$data));
 }
 return $result;
}
$before=snapshot($conn);
$res=mysqli_query($conn,'SELECT r.registration_id,r.student_id,e.* FROM registrations r JOIN events e ON e.event_id=r.event_id ORDER BY r.registration_id');
$ended=null;while($row=mysqli_fetch_assoc($res))if(evenza_event_status($row)==='completed'){$ended=$row;break;}
if(!$ended)throw new RuntimeException('No ended event fixture available; no request sent.');
$denials=0;
try { evenza_register_student($conn,(int)$ended['event_id'],(int)$ended['student_id'],'Read-only rejection test');throw new LogicException('Ended registration accepted'); }
catch(RuntimeException $ex){if($ex->getMessage()!=='Registration is closed for this event.')throw $ex;$denials++;}
try { evenza_update_registration($conn,(int)$ended['registration_id'],'approved',(int)$ended['college_id']);throw new LogicException('Ended approval accepted'); }
catch(RuntimeException $ex){if(!str_starts_with($ex->getMessage(),'Cannot approve'))throw $ex;$denials++;}
try { evenza_update_registration($conn,(int)$ended['registration_id'],'cancelled',-1);throw new LogicException('Cross-college action accepted'); }
catch(RuntimeException $ex){if(!str_starts_with($ex->getMessage(),'Registration not found'))throw $ex;$denials++;}
if(snapshot($conn)!==$before)throw new RuntimeException('Unexpected database change');
echo "$denials service rejection tests passed. Five table fingerprints unchanged.\n";
