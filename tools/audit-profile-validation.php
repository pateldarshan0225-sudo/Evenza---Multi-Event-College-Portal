<?php
// CLI only. Validate profiles/uploads and SQL field bindings; never save account data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$argv[1] = 'Student/Profile.php';
ob_start(); require __DIR__ . '/render-audit.php'; ob_end_clean();
$checks = []; $temporary = [];
$check = function ($name, $pass) use (&$checks) { $checks[] = ['name'=>$name, 'pass'=>(bool)$pass]; };
try {
    foreach (['student','organizer'] as $role) {
        $table = $role === 'student' ? 'students' : 'colleges'; $key = $role === 'student' ? 'student_id' : 'college_id';
        $record = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM $table ORDER BY $key LIMIT 1"));
        $id = (int)$record[$key];
        $input = array_replace($record, ['phone'=>'9876543210']);
        if ($role === 'organizer' && empty($input['address'])) $input['address'] = 'Campus address';
        $validate = fn($data, $file = null) => validate_account_profile($conn, $role, $id, $data, $file);
        $valid = $validate($input);
        $check($role . ' valid profile accepted without saving', !$valid['errors']);
        foreach (['email'=>'invalid', 'phone'=>'+-------', 'name'=>[], $role === 'student' ? 'college_id' : 'university_id'=>'-1'] as $field=>$value) {
            $result = $validate(array_replace($input, [$field=>$value]));
            $check($role . ' rejects invalid ' . $field, isset($result['errors'][$field]));
        }
        $result = $validate(array_replace($input, $role === 'student' ? ['semester'=>'99','gender'=>'admin'] : ['slug'=>'Invalid URL !']));
        $check($role . ' rejects invalid role-specific fields', (bool)$result['errors']);
        $result = $validate(array_replace($input, ['status'=>'active','account_status'=>'active','verification_status'=>'verified']));
        $check($role . ' ignores posted administrative controls', !array_intersect(['status','account_status','verification_status'], array_keys($result['data'])));
        $result = save_account_profile($conn, $role, $id, $record, array_replace($input, ['profile_csrf'=>'invalid']), null);
        $check($role . ' rejects expired token before writing', isset($result['errors']['general']));
        $data = $valid['data']; $data[$role === 'student' ? 'profile_photo' : 'logo'] = $record[$role === 'student' ? 'profile_photo' : 'logo'];
        $comparisons = implode(', ', array_map(fn($field)=>"$field <=> ? AS $field", array_keys($data)));
        $stmt = $conn->prepare("SELECT $comparisons FROM $table WHERE $key = ?");
        $values = array_values($data); $values[] = $id; $types = str_repeat('s', count($data)) . 'i';
        $stmt->bind_param($types, ...$values); $stmt->execute();
        $check($role . ' profile field bindings match existing schema', (bool)$stmt->get_result()->fetch_assoc()); $stmt->close();
    }
    $png = tempnam(sys_get_temp_dir(), 'evenza-profile-'); $temporary[] = $png;
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB1kAAAAASUVORK5CYII='));
    $file = ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$png,'size'=>filesize($png),'name'=>'example.png'];
    [$extension,$error] = validate_profile_upload($file); $check('Real PNG accepted', $extension === 'png' && !$error);
    [$extension,$error] = validate_profile_upload(array_replace($file, ['size'=>3*1024*1024])); $check('Oversized upload rejected', (bool)$error);
    file_put_contents($png, '<?php echo "not an image";');
    [$extension,$error] = validate_profile_upload(array_replace($file, ['size'=>filesize($png)])); $check('Disguised executable rejected', (bool)$error);
    [$extension,$error] = validate_profile_upload(['error'=>UPLOAD_ERR_NO_FILE]); $check('Optional image keeps existing image', !$extension && !$error);
    $post = ['current_password'=>'wrong','new_password'=>'Example123','confirm_password'=>'Example123','profile_csrf'=>profile_csrf_token()];
    $errors = save_profile_password($conn,'student',$sid,['password'=>password_hash('OldExample123',PASSWORD_DEFAULT)],$post);
    $check('Wrong current password rejected without writing', isset($errors['current_password']));
    $hash = password_hash('OldExample123',PASSWORD_DEFAULT); $post['current_password']=$hash;
    $errors = save_profile_password($conn,'student',$sid,['password'=>$hash],$post);
    $check('Stored hash cannot be used as current password', isset($errors['current_password']));
    $check('Missing student photo has local default', profile_image_url('student',null)==='../assets/images/brand/avatar-placeholder.svg');
    $check('Missing college logo has local default', profile_image_url('organizer',null)==='../assets/images/brand/college-placeholder.svg');
} finally { foreach ($temporary as $file) if (is_file($file)) unlink($file); }
$failures = array_values(array_filter($checks, fn($row)=>!$row['pass']));
echo json_encode(['checks'=>count($checks),'failures'=>$failures]), PHP_EOL;
exit($failures ? 1 : 0);
