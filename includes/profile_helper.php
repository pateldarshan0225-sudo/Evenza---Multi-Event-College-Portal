<?php
require_once __DIR__ . '/phone_helper.php';

function profile_image_url(string $role, ?string $filename): string {
    $filename = basename(str_replace('\\', '/', (string)$filename));
    $directories = $role === 'student' ? ['uploads/students', 'assets/images/user'] : ['uploads/colleges', 'assets/images/colleges'];
    foreach ($directories as $directory) {
        if ($filename !== '' && is_file(__DIR__ . '/../' . $directory . '/' . $filename)) {
            return '../' . $directory . '/' . rawurlencode($filename);
        }
    }
    return '../assets/images/brand/' . ($role === 'student' ? 'avatar-placeholder.svg' : 'college-placeholder.svg');
}

function profile_csrf_token(): string {
    $tab = get_current_tab_id() ?? 'none';
    if (empty($_SESSION['profile_csrf'][$tab])) $_SESSION['profile_csrf'][$tab] = bin2hex(random_bytes(32));
    return $_SESSION['profile_csrf'][$tab];
}

function valid_profile_csrf(array $post): bool {
    return is_string($post['profile_csrf'] ?? null) && hash_equals(profile_csrf_token(), $post['profile_csrf']);
}

/** Validate an optional upload without moving it or writing account records. */
function validate_profile_upload(?array $file): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) return [null, 'The image could not be uploaded. Please try again.'];
    if (!is_string($file['tmp_name'] ?? null) || !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 2 * 1024 * 1024) return [null, 'Choose an image up to 2 MB.'];
    $info = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($types[$mime]) || ($info['mime'] ?? '') !== $mime || $info[0] > 8000 || $info[1] > 8000) return [null, 'Choose a valid JPG, PNG or WEBP image (maximum 8000 × 8000 pixels).'];
    return [$types[$mime], null];
}

/** User-editable fields only. Status, verification and account ownership cannot be posted. */
function validate_account_profile(mysqli $conn, string $role, int $id, array $post, ?array $file): array {
    $student = $role === 'student';
    $fields = $student ? ['name','email','phone','enrollment_no','college_id','gender','semester'] : ['name','email','phone','slug','address','university_id'];
    $data = []; $errors = [];
    foreach ($fields as $field) {
        if (isset($post[$field]) && !is_string($post[$field])) $errors[$field] = 'Please enter a valid value.';
        $data[$field] = is_string($post[$field] ?? null) ? trim($post[$field]) : '';
    }
    if ($data['name'] === '' || mb_strlen($data['name']) > ($student ? 100 : 150)) $errors['name'] = 'Enter a name up to ' . ($student ? 100 : 150) . ' characters.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) $errors['email'] = 'Enter a valid email address up to 100 characters.';
    if (is_valid_phone_number($data['phone'])) $data['phone'] = normalize_phone_number($data['phone']);
    elseif (!preg_match($student ? '/^\+[1-9][0-9 ()-]{6,19}$/' : '/^\+?[1-9][0-9 ()-]{6,19}$/', $data['phone']) || strlen(preg_replace('/\D/', '', $data['phone'])) < 7 || strlen(preg_replace('/\D/', '', $data['phone'])) > 15) $errors['phone'] = 'Enter a valid phone number, including the country code for international numbers.';
    if (!isset($errors['email'])) {
        foreach (['students'=>'student_id', 'colleges'=>'college_id', 'admins'=>'admin_id'] as $table=>$key) {
            $own = $table === ($student ? 'students' : 'colleges') ? $id : 0;
            $stmt = $conn->prepare("SELECT $key FROM $table WHERE email = ? AND $key <> ? LIMIT 1");
            $stmt->bind_param('si', $data['email'], $own); $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors['email'] = 'This email is already used by another account.';
            $stmt->close();
        }
    }
    $affiliation = $student ? 'college_id' : 'university_id';
    $table = $student ? 'colleges' : 'universities';
    $affiliationId = filter_var($data[$affiliation], FILTER_VALIDATE_INT);
    if (!$affiliationId || $affiliationId < 1) $errors[$affiliation] = 'Select a valid institution.';
    else {
        $stmt = $conn->prepare("SELECT $affiliation FROM $table WHERE $affiliation = ?");
        $stmt->bind_param('i', $affiliationId); $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) $errors[$affiliation] = 'Selected institution was not found.';
        $stmt->close();
    }
    if ($student) {
        if ($data['enrollment_no'] === '' || mb_strlen($data['enrollment_no']) > 50) $errors['enrollment_no'] = 'Enter an enrollment number up to 50 characters.';
        if (!in_array($data['gender'], ['male','female','other'], true)) $errors['gender'] = 'Select a gender.';
        if (!ctype_digit($data['semester']) || (int)$data['semester'] < 1 || (int)$data['semester'] > 8) $errors['semester'] = 'Select a semester from 1 to 8.';
        $stmt = $conn->prepare('SELECT student_id FROM students WHERE enrollment_no = ? AND student_id <> ? LIMIT 1');
        $stmt->bind_param('si', $data['enrollment_no'], $id); $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) $errors['enrollment_no'] = 'This enrollment number is already in use.';
        $stmt->close();
    } else {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug']) || strlen($data['slug']) > 150) $errors['slug'] = 'Use lowercase letters, numbers and single hyphens (up to 150 characters).';
        if ($data['address'] === '' || mb_strlen($data['address']) > 2000) $errors['address'] = 'Enter an address up to 2000 characters.';
        $stmt = $conn->prepare('SELECT college_id FROM colleges WHERE slug = ? AND college_id <> ? LIMIT 1');
        $stmt->bind_param('si', $data['slug'], $id); $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) $errors['slug'] = 'This college URL name is already in use.';
        $stmt->close();
    }
    [$extension, $uploadError] = validate_profile_upload($file);
    if ($uploadError) $errors['profile_image'] = $uploadError;
    return ['data'=>$data, 'errors'=>$errors, 'extension'=>$extension];
}

function save_account_profile(mysqli $conn, string $role, int $id, array $current, array $post, ?array $file): array {
    $result = validate_account_profile($conn, $role, $id, $post, $file);
    if (!valid_profile_csrf($post)) $result['errors']['general'] = 'Your form expired. Refresh the page and try again.';
    if ($result['errors']) return $result;
    $student = $role === 'student'; $table = $student ? 'students' : 'colleges';
    $key = $student ? 'student_id' : 'college_id'; $imageKey = $student ? 'profile_photo' : 'logo';
    $data = $result['data']; $image = (string)($current[$imageKey] ?? ''); $newFile = null;
    try {
        if (!$conn->begin_transaction()) throw new RuntimeException('Could not start profile update.');
        $lock = $conn->prepare("SELECT $imageKey FROM $table WHERE $key = ? FOR UPDATE");
        if (!$lock) throw new RuntimeException('Could not load account.');
        $lock->bind_param('i', $id);
        if (!$lock->execute()) throw new RuntimeException('Could not load account.');
        $account = $lock->get_result()->fetch_assoc(); $lock->close();
        if (!$account) throw new RuntimeException('Account no longer exists.');
        $image = (string)($account[$imageKey] ?? '');
        if ($result['extension']) {
            $directory = __DIR__ . '/../uploads/' . ($student ? 'students' : 'colleges');
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Upload directory unavailable.');
            $image = 'profile_' . bin2hex(random_bytes(16)) . '.' . $result['extension'];
            $newFile = $directory . '/' . $image;
            if (!move_uploaded_file($file['tmp_name'], $newFile)) throw new RuntimeException('Image upload failed.');
        }
        $data[$imageKey] = $image;
        $assignments = implode(', ', array_map(fn($field) => "$field = ?", array_keys($data)));
        $values = array_values($data); $values[] = $id;
        $stmt = $conn->prepare("UPDATE $table SET $assignments WHERE $key = ?");
        if (!$stmt) throw new RuntimeException('Could not prepare profile update.');
        $types = str_repeat('s', count($data)) . 'i';
        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) throw new RuntimeException('Could not save profile.');
        $stmt->close();
        if (!$conn->commit()) throw new RuntimeException('Could not commit profile update.');
        $tab = get_current_tab_id();
        $_SESSION['authenticated_tabs'][$tab]['name'] = $data['name'];
        $_SESSION['authenticated_tabs'][$tab]['email'] = $data['email'];
        if ($student) $_SESSION['authenticated_tabs'][$tab]['college_id'] = (int)$data['college_id'];
        else $_SESSION['authenticated_tabs'][$tab]['logo'] = $image;
        $result['data'] = array_replace($current, $data);
    } catch (Throwable $error) {
        $conn->rollback();
        if ($newFile && is_file($newFile)) unlink($newFile);
        error_log('Profile update failed: ' . $error->getMessage());
        $result['errors']['general'] = 'Your profile could not be saved. Please try again.';
    }
    return $result;
}

function save_profile_password(mysqli $conn, string $role, int $id, array $current, array $post): array {
    $errors = [];
    $old = is_string($post['current_password'] ?? null) ? $post['current_password'] : '';
    $new = is_string($post['new_password'] ?? null) ? $post['new_password'] : '';
    $confirm = is_string($post['confirm_password'] ?? null) ? $post['confirm_password'] : '';
    if (!valid_profile_csrf($post)) $errors['general'] = 'Your form expired. Refresh the page and try again.';
    $stored = (string)($current['password'] ?? '');
    $matches = (password_get_info($stored)['algoName'] ?? 'unknown') !== 'unknown' ? password_verify($old, $stored) : hash_equals($stored, $old);
    if ($old === '' || $stored === '' || !$matches) $errors['current_password'] = 'Current password is incorrect.';
    if (strlen($new) < 8 || strlen($new) > 72) $errors['new_password'] = 'Use a password between 8 and 72 bytes.';
    elseif ($new === $old) $errors['new_password'] = 'Choose a different password.';
    if ($new !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';
    if ($errors) return $errors;
    $table = $role === 'student' ? 'students' : 'colleges'; $key = $role === 'student' ? 'student_id' : 'college_id';
    try {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE $key = ?");
        if (!$stmt) throw new RuntimeException('Could not prepare password update.');
        $stmt->bind_param('si', $hash, $id);
        if (!$stmt->execute()) throw new RuntimeException('Could not save password.');
        $stmt->close();
    } catch (Throwable $error) {
        error_log('Password update failed: ' . $error->getMessage());
        $errors['general'] = 'Your password could not be updated. Please try again.';
    }
    return $errors;
}
