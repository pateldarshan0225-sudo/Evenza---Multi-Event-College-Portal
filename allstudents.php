<?php
/**
 * allstudents.php
 * Single-file Student management: DB connection, validation,
 * create / read / update / delete, account-status toggle (AJAX),
 * async uniqueness checks (AJAX), limit & pagination, and UI.
 *
 * Assumes a `students` table:
 *   student_id, college_id, enrollment_no, name, email, password, phone,
 *   gender, semester, id_card_image, profile_photo,
 *   verification_status, account_status, created_at, updated_at
 * with college_id as a foreign key to colleges.college_id.
 */

include 'auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   DB CONNECTION
   ========================================================= */
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'evenza';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

$IDCARD_DIR      = __DIR__ . '/assets/images/students/idcards';
$IDCARD_WEB_PATH = 'assets/images/students/idcards/';
$PHOTO_DIR       = __DIR__ . '/assets/images/user';
$PHOTO_WEB_PATH  = 'assets/images/user/';

/* =========================================================
   VALIDATION HELPERS
   ========================================================= */
function validate_student(
    PDO $pdo,
    array $data,
    ?int $excludeId = null,
    ?array $idCardFile = null,
    ?string $existingIdCard = null,
    ?array $photoFile = null,
    ?string $existingPhoto = null
): array {
    $errors = [];

    $name          = trim($data['name'] ?? '');
    $collegeId     = filter_var($data['college_id'] ?? null, FILTER_VALIDATE_INT);
    $enrollmentNo  = trim($data['enrollment_no'] ?? '');
    $email         = trim($data['email'] ?? '');
    $phone         = trim($data['phone'] ?? '');
    $gender        = trim($data['gender'] ?? '');
    $semester      = trim($data['semester'] ?? '');
    $accountStatus = trim($data['account_status'] ?? '');
    $verifyStatus  = trim($data['verification_status'] ?? '');
    $password      = (string) ($data['password'] ?? '');

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Student name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Student name is too long (max 100 characters).';
    }

    $universityId = filter_var($data['university_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$collegeId) {
        $errors[] = 'Select a valid college.';
    } else {
        $chk = $pdo->prepare('SELECT college_id FROM colleges WHERE college_id = :id');
        $chk->execute(['id' => $collegeId]);
        if (!$chk->fetch()) {
            $errors[] = 'Selected college does not exist.';
        }
    }

    if (!$universityId) {
        $errors[] = 'Select a valid university.';
    } elseif ($collegeId) {
        $relChk = $pdo->prepare('SELECT college_id FROM colleges WHERE college_id = :college_id AND university_id = :university_id');
        $relChk->execute(['college_id' => $collegeId, 'university_id' => $universityId]);
        if (!$relChk->fetch()) {
            $errors[] = 'Selected college does not belong to the selected university.';
        }
    }

    if ($enrollmentNo === '') {
        $errors[] = 'Enrollment number is required.';
    } elseif (mb_strlen($enrollmentNo) > 50) {
        $errors[] = 'Enrollment number is too long (max 50 characters).';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors[] = 'Enter a valid email address (max 100 characters).';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    }

    if ($gender === '') {
        $errors[] = 'Select a gender.';
    } elseif (!in_array($gender, ['male', 'female', 'other'], true)) {
        $errors[] = 'Select a valid gender.';
    }

    if ($semester === '') {
        $errors[] = 'Semester is required.';
    } elseif (mb_strlen($semester) > 20) {
        $errors[] = 'Semester value is too long (max 20 characters).';
    }

    if (!file_provided($idCardFile) && empty($existingIdCard)) {
        $errors[] = 'Student ID card image is required.';
    }

    if (!file_provided($photoFile) && empty($existingPhoto)) {
        $errors[] = 'Profile photo is required.';
    }

    if (!in_array($accountStatus, ['active', 'inactive'], true)) {
        $errors[] = 'Select a valid account status.';
    }

    if (!in_array($verifyStatus, ['pending', 'verified', 'rejected'], true)) {
        $errors[] = 'Select a valid verification status.';
    }

    if ($excludeId === null) {
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
    } elseif ($password !== '' && mb_strlen($password) < 8) {
        $errors[] = 'New password must be at least 8 characters (leave blank to keep the current password).';
    }

    if ($enrollmentNo !== '') {
        $sql = 'SELECT student_id FROM students WHERE enrollment_no = :enrollment_no';
        $params = ['enrollment_no' => $enrollmentNo];
        if ($excludeId !== null) {
            $sql .= ' AND student_id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'This enrollment number is already in use.';
        }
    }

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql = 'SELECT student_id FROM students WHERE email = :email';
        $params = ['email' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND student_id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    return $errors;
}

function file_provided(?array $file): bool
{
    return $file !== null && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

function handle_image_upload(?array $file, string $destDir, string $prefix, bool $required = false): ?string
{
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('Only JPG or PNG images are allowed.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be smaller than 2MB.');
    }

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mime];

    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return $filename;
}

/* =========================================================
   AJAX Handlers
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'toggle_status') {
    header('Content-Type: application/json');

    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
    $status = strtolower(trim($body['status'] ?? ''));

    if (!$id || !in_array($status, ['active', 'inactive'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $check = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE students SET account_status = :status, updated_at = NOW() WHERE student_id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);

    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'check_unique') {
    header('Content-Type: application/json');

    $body      = json_decode(file_get_contents('php://input'), true);
    $field     = $body['field'] ?? '';
    $value     = trim($body['value'] ?? '');
    $excludeId = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT) ?: null;

    if (!in_array($field, ['enrollment_no', 'email'], true) || $value === '') {
        http_response_code(422);
        echo json_encode(['available' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $sql = "SELECT student_id FROM students WHERE {$field} = :value";
    $params = ['value' => $value];
    if ($excludeId !== null) {
        $sql .= ' AND student_id != :id';
        $params['id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exists = (bool) $stmt->fetch();

    echo json_encode([
        'available' => !$exists,
        'message'   => $exists
            ? ($field === 'enrollment_no' ? 'This enrollment number is already in use.' : 'This email is already registered.')
            : null,
    ]);
    exit;
}

/* =========================================================
   DELETE & POST HANDLERS
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $pdo->prepare('SELECT id_card_image, profile_photo FROM students WHERE student_id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare('DELETE FROM students WHERE student_id = :id')->execute(['id' => $id]);

            if (!empty($row['id_card_image'])) {
                $path = $IDCARD_DIR . '/' . $row['id_card_image'];
                if (is_file($path)) unlink($path);
            }
            if (!empty($row['profile_photo'])) {
                $path = $PHOTO_DIR . '/' . $row['profile_photo'];
                if (is_file($path)) unlink($path);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student deleted successfully.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Student not found.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid student id.'];
    }

    header('Location: allstudents.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    if ($_POST['form_action'] === 'create') {
        $errors = validate_student($pdo, $_POST, null, $_FILES['id_card_image'] ?? null, null, $_FILES['profile_photo'] ?? null, null);

        if (empty($errors)) {
            try {
                $idCard  = handle_image_upload($_FILES['id_card_image'] ?? null, $IDCARD_DIR, 'idc', true);
                $photo   = handle_image_upload($_FILES['profile_photo'] ?? null, $PHOTO_DIR, 'pf', false);

                $stmt = $pdo->prepare(
                    'INSERT INTO students (college_id, enrollment_no, name, email, password, phone, gender, semester, id_card_image, profile_photo, verification_status, account_status, created_at, updated_at)
                     VALUES (:college_id, :enrollment_no, :name, :email, :password, :phone, :gender, :semester, :id_card_image, :profile_photo, :verification_status, :account_status, NOW(), NOW())'
                );
                $stmt->execute([
                    'college_id'           => (int) $_POST['college_id'],
                    'enrollment_no'        => trim($_POST['enrollment_no']),
                    'name'                 => trim($_POST['name']),
                    'email'                => trim($_POST['email']),
                    'password'             => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    'phone'                => trim($_POST['phone']) !== '' ? trim($_POST['phone']) : null,
                    'gender'               => trim($_POST['gender']) !== '' ? trim($_POST['gender']) : null,
                    'semester'             => trim($_POST['semester']) !== '' ? trim($_POST['semester']) : null,
                    'id_card_image'        => $idCard,
                    'profile_photo'        => $photo,
                    'verification_status'  => trim($_POST['verification_status']),
                    'account_status'       => trim($_POST['account_status']),
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student added successfully.'];
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                $errors[] = 'Could not save student. Please try again.';
            }
        }
        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'addStudentModal';
        }
        header('Location: allstudents.php');
        exit;
    }

    if ($_POST['form_action'] === 'update') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $existingStmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :id');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();

        if (!$id || !$existing) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Student not found.'];
            header('Location: allstudents.php');
            exit;
        }

        $errors = validate_student($pdo, $_POST, $id, $_FILES['id_card_image'] ?? null, $existing['id_card_image'] ?? null, $_FILES['profile_photo'] ?? null, $existing['profile_photo'] ?? null);

        if (empty($errors)) {
            try {
                $newIdCard = handle_image_upload($_FILES['id_card_image'] ?? null, $IDCARD_DIR, 'idc', false);
                $newPhoto  = handle_image_upload($_FILES['profile_photo'] ?? null, $PHOTO_DIR, 'pf', false);

                $idCardToStore = $newIdCard !== null ? $newIdCard : $existing['id_card_image'];
                $photoToStore  = $newPhoto !== null ? $newPhoto : $existing['profile_photo'];

                if ($newIdCard !== null && !empty($existing['id_card_image'])) {
                    $oldPath = $IDCARD_DIR . '/' . $existing['id_card_image'];
                    if (is_file($oldPath)) unlink($oldPath);
                }
                if ($newPhoto !== null && !empty($existing['profile_photo'])) {
                    $oldPath = $PHOTO_DIR . '/' . $existing['profile_photo'];
                    if (is_file($oldPath)) unlink($oldPath);
                }

                $stmt = $pdo->prepare(
                    'UPDATE students
                     SET college_id = :college_id, enrollment_no = :enrollment_no, name = :name, email = :email, phone = :phone, gender = :gender, semester = :semester, id_card_image = :id_card_image, profile_photo = :profile_photo, verification_status = :verification_status, account_status = :account_status, updated_at = NOW()
                     ' . (trim($_POST['password'] ?? '') !== '' ? ', password = :password' : '') . '
                     WHERE student_id = :id'
                );

                $params = [
                    'college_id'          => (int) $_POST['college_id'],
                    'enrollment_no'       => trim($_POST['enrollment_no']),
                    'name'                => trim($_POST['name']),
                    'email'               => trim($_POST['email']),
                    'phone'               => trim($_POST['phone']) !== '' ? trim($_POST['phone']) : null,
                    'gender'              => trim($_POST['gender']) !== '' ? trim($_POST['gender']) : null,
                    'semester'            => trim($_POST['semester']) !== '' ? trim($_POST['semester']) : null,
                    'id_card_image'       => $idCardToStore,
                    'profile_photo'       => $photoToStore,
                    'verification_status' => trim($_POST['verification_status']),
                    'account_status'      => trim($_POST['account_status']),
                    'id'                  => $id,
                ];

                if (trim($_POST['password'] ?? '') !== '') {
                    $params['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                $stmt->execute($params);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student updated successfully.'];
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                $errors[] = 'Could not update student.';
            }
        }
        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'editStudentModal' . $id;
        }
        header('Location: allstudents.php');
        exit;
    }
}

/* =========================================================
   DATA FOR DISPLAY
   ========================================================= */
$students = $pdo->query(
    'SELECT s.*, c.name AS college_name, c.university_id AS student_university_id
     FROM students s
     LEFT JOIN colleges c ON c.college_id = s.college_id
     ORDER BY s.created_at DESC'
)->fetchAll();
$colleges = $pdo->query("
    SELECT college_id, university_id, name
    FROM colleges 
    WHERE status = 'active'
    ORDER BY name ASC
")->fetchAll();
$universities = $pdo->query("
    SELECT university_id, name
    FROM universities
    WHERE status = 'active'
    ORDER BY name ASC
")->fetchAll();
$total    = count($students);
$active   = count(array_filter($students, fn($s) => $s['account_status'] === 'active'));
$inactive = $total - $active;

$flash = $_SESSION['flash'] ?? null;
$reopenModal = $_SESSION['reopen_modal'] ?? null;
unset($_SESSION['flash'], $_SESSION['reopen_modal']);

$admin_email = $_SESSION['admin_email'] ?? 'admin@evenza.com';
$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Students | Evenza Admin</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Select2 (searchable University / College dropdowns) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />

    <style>
        :root {
            --bg-canvas: #ece7dd;
            --bg-card: #f4f2eb;
            --bg-hero: #e0d8cb;
            --bg-dark: #1c2024;
            --bg-white: #ffffff;
            --bg-row: #f6f4ee;
            --color-yellow: #ffd13b;
            --color-red: #ff6b52;
            --color-text-dark: #1c2024;
            --color-text-muted: #7c7d7e;
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: var(--color-text-dark);
            margin: 0;
            padding: 20px 28px;
            min-height: 100vh;
            width: 100%;
        }

        /* Fluid Layout Frame */
        .berun-window {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            position: relative;
        }

        /* Header Navigation Area */
        .berun-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .berun-logo-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--color-text-dark);
        }

        .berun-logo-dots {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .berun-logo-dots-top { display: flex; gap: 2px; }

        .berun-dot {
            width: 7px;
            height: 7px;
            background-color: var(--color-text-dark);
            border-radius: 50%;
        }

        .berun-logo-text {
            font-weight: 800;
            font-size: 20px;
            letter-spacing: -0.5px;
            color: var(--color-text-dark);
        }
        .berun-logo-text span { font-weight: 400; }

        .berun-greeting-h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: var(--color-text-dark);
            line-height: 1.2;
        }

        .custom-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
            padding: 0;
            margin: 4px 0 0 0;
            font-size: 12px;
            font-weight: 600;
        }
        .custom-breadcrumb li { color: var(--color-text-muted); }
        .custom-breadcrumb li a { text-decoration: none; color: var(--color-text-dark); }
        .custom-breadcrumb li:not(:last-child)::after { content: "/"; margin-left: 8px; color: #b5b7bc; }

        /* Search Box & Buttons */
        .berun-search-wrapper {
            position: relative;
            width: 290px;
        }

        .berun-search-input {
            width: 100%;
            background: var(--bg-white);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 9999px;
            padding: 10px 20px 10px 44px;
            font-size: 13px;
            font-weight: 500;
            color: var(--color-text-dark);
            outline: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .berun-search-input:focus {
            border-color: var(--color-text-dark);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .berun-search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ea0a5;
            font-size: 14px;
        }

        .berun-btn-dark {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .berun-btn-dark:hover { background-color: #2e343b; transform: translateY(-1px); }

        /* Layout Grid with Left Floating Sidebar Capsule */
        .berun-layout-body {
            display: flex;
            gap: 28px;
        }

        .berun-sidebar-capsule {
            background: var(--bg-white);
            border-radius: 40px;
            width: 68px;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            flex-shrink: 0;
            position: sticky;
            top: 20px;
            align-self: flex-start;
            z-index: 100;
            max-height: calc(100vh - 40px);
        }

        .berun-nav-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            width: 100%;
        }

        .berun-nav-item {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #72757c;
            text-decoration: none;
            font-size: 18px;
            transition: all 0.2s ease;
            position: relative;
        }

        .berun-nav-item:hover { color: var(--color-text-dark); background: rgba(0, 0, 0, 0.04); }

        .berun-nav-item.active {
            background-color: var(--bg-dark);
            color: var(--color-yellow) !important;
            box-shadow: 0 6px 16px rgba(28, 32, 36, 0.25);
        }

        .berun-avatar-pill {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        /* Main Content Grid */
        .berun-main-grid {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* Be.run Stat Cards */
        .berun-stat-card {
            background: var(--bg-white);
            border-radius: 22px;
            padding: 22px 24px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.02);
            transition: all 0.25s ease;
            border: 1px solid rgba(0,0,0,0.02);
            height: 100%;
        }
        .berun-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        }

        .berun-stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-primary { background: #e8edff; color: #4f46e5; }
        .icon-success { background: #e8f7ef; color: #198754; }
        .icon-danger  { background: #fdecec; color: #dc3545; }

        /* Card Panels */
        .berun-card-panel {
            background: var(--bg-white);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .berun-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f2eee6;
        }

        .berun-panel-title { font-size: 17px; font-weight: 700; color: var(--color-text-dark); margin: 0; }
        .berun-panel-sub   { font-size: 12px; color: var(--color-text-muted); margin: 2px 0 0 0; font-weight: 500; }

        .student-logo {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid #e8e4d9;
            background: #f8f6f0;
            flex-shrink: 0;
        }

        .student-name { font-weight: 700; color: var(--color-text-dark); font-size: 14px; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-success { background: #e8f7ef; color: #198754; }
        .badge-danger  { background: #fdecec; color: #dc3545; }
        .badge-warning { background: #fef9c3; color: #854d0e; }

        .action-btn {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid #e8e4d9;
            background: #ffffff;
            color: var(--color-text-dark);
            transition: all 0.2s ease;
        }
        .action-btn:hover { background: var(--bg-dark); color: #ffffff !important; border-color: var(--bg-dark); }
        .action-btn:hover i { color: #ffffff !important; }

        /* Form Inputs & Selects */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 9px 14px;
            font-size: 13px;
            color: #374151;
            background-color: #ffffff;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--color-text-dark);
            box-shadow: 0 0 0 3px rgba(28, 32, 36, 0.1);
        }

        /* Table styling in Be.run theme */
        .table thead th {
            background: #f9f8f4;
            color: #7c7d7e;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid #edf0f5;
        }
        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            color: #374151;
            border-bottom: 1px solid #f4f2eb;
            font-size: 13px;
        }
        .table tbody tr { transition: 0.2s ease; }
        .table tbody tr:hover { background-color: #f9f8f4; }

        /* Modals in Be.run Theme */
        .modal-content {
            border-radius: 26px;
            border: none;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .modal-header {
            background: #f9f8f4;
            padding: 20px 24px;
            border-bottom: 1px solid #f0ebd9;
        }
        .modal-title { font-weight: 700; color: var(--color-text-dark); }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; background: #f9f8f4; border-top: 1px solid #f0ebd9; }

        .async-feedback { min-height: 18px; font-size: 11px; }
        .async-spinner { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); }

        /* Select2 styling to match Be.run theme */
        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single {
            height: 42px !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 12px !important;
            display: flex;
            align-items: center;
            background-color: #fff;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal;
            padding-left: 14px;
            padding-right: 24px;
            color: #374151;
            font-size: 13px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 10px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            body { padding: 12px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
            .berun-sidebar-capsule { width: 100%; flex-direction: row; height: 60px; padding: 0 16px; border-radius: 9999px; }
            .berun-nav-group { flex-direction: row; justify-content: space-around; width: auto; flex-grow: 1; }
        }
    </style>
</head>

<body>

    <div class="berun-window">

        <!-- Top Header Navigation Bar -->
        <header class="berun-header">
            <div class="d-flex align-items-center gap-4">
                <a href="Dashboard.php" class="berun-logo-brand">
                    <div class="berun-logo-dots">
                        <div class="berun-logo-dots-top">
                            <div class="berun-dot"></div>
                            <div class="berun-dot"></div>
                        </div>
                        <div class="berun-dot"></div>
                    </div>
                    <div class="berun-logo-text">Even<span>za</span></div>
                </a>

                <div class="ps-2">
                    <h1 class="berun-greeting-h1">All Students</h1>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li>Student Management</li>
                        <li>All Students</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="berun-search-wrapper">
                    <i class="bi bi-search berun-search-icon"></i>
                    <input type="text" id="searchStudent" class="berun-search-input" placeholder="Search student name, enrollment..." />
                </div>

                <button type="button" class="berun-btn-dark" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="bi bi-plus-lg"></i> Add Student
                </button>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <!-- Flash Message Alert -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                        <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards (Be.run Theme Styling) -->
                <div class="row g-4">
                    <!-- Total Students Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Students</span>
                                    <h2 id="totalCount" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 26px;"><?= $total ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-primary">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8edff; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #4f46e5; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Students Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Active Students</span>
                                    <h2 id="activeCount" class="mb-0 fw-extrabold mt-1" style="color:#198754; font-size: 26px;"><?= $active ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8f7ef; border-radius: 9999px;">
                                <div id="activeBar" class="progress-bar" style="width: <?= $total > 0 ? ($active > 0 ? max(5, round(($active / $total) * 100)) : 0) : 0 ?>%; background-color: #198754; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Inactive Students Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Inactive Students</span>
                                    <h2 id="inactiveCount" class="mb-0 fw-extrabold mt-1" style="color:#dc3545; font-size: 26px;"><?= $inactive ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-danger">
                                    <i class="bi bi-x-circle"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #fdecec; border-radius: 9999px;">
                                <div id="inactiveBar" class="progress-bar" style="width: <?= $total > 0 ? ($inactive > 0 ? max(5, round(($inactive / $total) * 100)) : 0) : 0 ?>%; background-color: #dc3545; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Student List Table Card -->
                <div class="berun-card-panel p-0 overflow-hidden">
                    <div class="p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-color:#f4f2eb !important;">
                        <div>
                            <h3 class="berun-panel-title">Student List</h3>
                            <p class="berun-panel-sub">View and manage all registered students</p>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select text-xs font-semibold rounded-pill px-3 py-2 border shadow-sm" id="statusFilter" style="min-width: 140px; background-color: #ffffff; cursor: pointer;">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="studentTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Student</th>
                                    <th>College</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Semester</th>
                                    <th>Verification</th>
                                    <th>Status</th>
                                    <th>Status Toggle</th>
                                    <th>Created</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total === 0): ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">
                                            No students yet. Click "Add Student" to create one.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $maleAvatars   = ['avatar-2.jpg', 'avatar-4.jpg', 'avatar-7.jpg', 'avatar-9.jpg'];
                                    $femaleAvatars = ['avatar-1.jpg', 'avatar-3.jpg', 'avatar-5.jpg', 'avatar-6.jpg', 'avatar-8.jpg', 'avatar-10.jpg'];
                                    ?>
                                    <?php foreach ($students as $i => $s): ?>
                                        <?php
                                        $isActive = $s['account_status'] === 'active';
                                        $verify   = $s['verification_status'];
                                        $verifyClass = $verify === 'verified'
                                            ? 'badge-success'
                                            : ($verify === 'rejected' ? 'badge-danger' : 'badge-warning');
                                        
                                        $photoName = $s['profile_photo'] ?? '';
                                        if (empty($photoName) || str_contains($photoName, 'avatar.png') || str_contains($photoName, 'placeholder')) {
                                            if (strtolower($s['gender']) === 'female') {
                                                $photoName = $femaleAvatars[(int) $s['student_id'] % count($femaleAvatars)];
                                            } else {
                                                $photoName = $maleAvatars[(int) $s['student_id'] % count($maleAvatars)];
                                            }
                                        }
                                        $photoSrc = $PHOTO_WEB_PATH . htmlspecialchars($photoName);
                                        ?>
                                        <tr>
                                            <td class="ps-4 font-semibold text-muted"><?= $i + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= $photoSrc ?>" class="student-logo" alt="Student Photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($s['name']) ?>&background=f4f2eb&color=1c2024'" />
                                                    <div>
                                                        <div class="student-name"><?= htmlspecialchars($s['name']) ?></div>
                                                        <?php if (!empty($s['enrollment_no'])): ?>
                                                            <small class="text-muted d-block" style="font-size: 11px; font-weight: 500;"><?= htmlspecialchars($s['enrollment_no']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-dark font-medium"><?= htmlspecialchars($s['college_name'] ?? 'Unknown') ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($s['email']) ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($s['semester'] ?? '—') ?></td>
                                            <td>
                                                <span class="status-badge <?= $verifyClass ?> verification-badge">
                                                    <?= ucfirst($verify) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input status-toggle" type="checkbox" role="switch" data-id="<?= (int) $s['student_id'] ?>" <?= $isActive ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                            <td class="text-muted small"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                                            <td class="pe-4 text-end">
                                                <button type="button" class="action-btn me-1" title="Edit" data-bs-toggle="modal" data-bs-target="#editStudentModal<?= (int) $s['student_id'] ?>">
                                                    <i class="bi bi-pencil text-primary"></i>
                                                </button>
                                                <button type="button" class="action-btn" title="Delete" onclick="deleteStudent(<?= (int) $s['student_id'] ?>)">
                                                    <i class="bi bi-trash text-danger"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer with Limit Selector & Compact Pagination -->
                    <div class="p-3 bg-white border-top">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6 col-12">
                                <div class="d-flex align-items-center gap-2 text-xs font-semibold text-muted">
                                    <span>Show</span>
                                    <select class="form-select form-select-sm w-auto rounded-pill px-3 py-1 font-bold text-dark border shadow-sm" id="limitSelect" style="min-width: 65px; cursor: pointer;">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                    </select>
                                    <span class="me-1">entries</span>
                                    <span class="text-muted opacity-40">|</span>
                                    <span class="ms-1 text-dark font-medium" id="showingCountText">Showing 0 of 0 students</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <nav aria-label="Table pagination">
                                    <ul class="pagination pagination-sm mb-0 justify-content-md-end justify-content-center gap-2 align-items-center" id="pagination">
                                        <!-- Pagination Inject -->
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <?php include 'Footer.php'; ?>

                </div>

            </div>

        </div>

    </div>

    <!-- ADD STUDENT MODAL -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i> Add Student</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Student Name</label>
                                <input type="text" class="form-control" name="name" required minlength="2" maxlength="100" placeholder="Rahul Verma">
                                <div class="invalid-feedback">Student name is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">University</label>
                                <select class="form-select university-select" name="university_id" required>
                                    <option value="">Choose university</option>
                                    <?php foreach ($universities as $u): ?>
                                        <option value="<?= (int) $u['university_id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Select a university.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">College</label>
                                <select class="form-select college-select" name="college_id" required>
                                    <option value="">Choose university first</option>
                                    <?php foreach ($colleges as $c): ?>
                                        <option value="<?= (int) $c['college_id'] ?>" data-university="<?= (int) $c['university_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Select a college.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Enrollment No</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" name="enrollment_no" required maxlength="50" data-async-check="enrollment_no" aria-describedby="enrollAsyncFeedbackAdd" placeholder="STU-100-1">
                                    <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                </div>
                                <div class="invalid-feedback">Enrollment number is required.</div>
                                <div class="async-feedback mt-1" id="enrollAsyncFeedbackAdd" role="alert" aria-live="polite"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Email</label>
                                <div class="position-relative">
                                    <input type="email" class="form-control" name="email" required maxlength="100" data-async-check="email" aria-describedby="emailAsyncFeedbackAdd" placeholder="rahulv100@example.com">
                                    <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                </div>
                                <div class="invalid-feedback">Enter a valid email.</div>
                                <div class="async-feedback mt-1" id="emailAsyncFeedbackAdd" role="alert" aria-live="polite"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Login Password</label>
                                <input type="password" class="form-control" name="password" required minlength="8" placeholder="Minimum 8 characters">
                                <div class="invalid-feedback">Minimum 8 characters.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Phone</label>
                                <input type="text" class="form-control" name="phone" required pattern="[0-9+\-\s]{7,20}" placeholder="9800000001">
                                <div class="invalid-feedback">Enter a valid phone number.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Choose</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                <div class="invalid-feedback">Select a gender.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Semester</label>
                                <input type="text" class="form-control" name="semester" required maxlength="20" placeholder="Semester 2">
                                <div class="invalid-feedback">Semester is required.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Account Status</label>
                                <select class="form-select" name="account_status" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Verification Status</label>
                                <select class="form-select" name="verification_status" required>
                                    <option value="pending" selected>Pending</option>
                                    <option value="verified">Verified</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Profile Photo <small class="text-lowercase text-muted">(JPG/PNG, max 2MB)</small></label>
                                <input type="file" class="form-control" name="profile_photo" accept="image/png,image/jpeg" required>
                                <div class="invalid-feedback">Profile photo is required.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Student ID Card Image <small class="text-lowercase text-muted">(JPG/PNG, max 2MB)</small></label>
                                <input type="file" class="form-control" name="id_card_image" accept="image/png,image/jpeg" required>
                                <div class="invalid-feedback">Student ID card image is required.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT STUDENT MODALS -->
    <?php foreach ($students as $s): ?>
        <div class="modal fade" id="editStudentModal<?= (int) $s['student_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $s['student_id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Student — <?= htmlspecialchars($s['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Student Name</label>
                                    <input type="text" class="form-control" name="name" required minlength="2" maxlength="100" value="<?= htmlspecialchars($s['name']) ?>">
                                    <div class="invalid-feedback">Student name is required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">University</label>
                                    <select class="form-select university-select" name="university_id" required>
                                        <option value="">Choose</option>
                                        <?php foreach ($universities as $u): ?>
                                            <option value="<?= (int) $u['university_id'] ?>" <?= (int) $u['university_id'] === (int) $s['student_university_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">College</label>
                                    <select class="form-select college-select" name="college_id" required data-preset="<?= (int) $s['college_id'] ?>">
                                        <?php foreach ($colleges as $c): ?>
                                            <option value="<?= (int) $c['college_id'] ?>" data-university="<?= (int) $c['university_id'] ?>" <?= (int) $c['college_id'] === (int) $s['college_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Enrollment No</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" name="enrollment_no" required maxlength="50" value="<?= htmlspecialchars($s['enrollment_no'] ?? '') ?>" data-async-check="enrollment_no" aria-describedby="enrollAsyncFeedback<?= (int) $s['student_id'] ?>">
                                        <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                    </div>
                                    <div class="invalid-feedback">Enrollment number is required.</div>
                                    <div class="async-feedback mt-1" id="enrollAsyncFeedback<?= (int) $s['student_id'] ?>" role="alert" aria-live="polite"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Email</label>
                                    <div class="position-relative">
                                        <input type="email" class="form-control" name="email" required maxlength="100" value="<?= htmlspecialchars($s['email']) ?>" data-async-check="email" aria-describedby="emailAsyncFeedback<?= (int) $s['student_id'] ?>">
                                        <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                    </div>
                                    <div class="invalid-feedback">Enter a valid email.</div>
                                    <div class="async-feedback mt-1" id="emailAsyncFeedback<?= (int) $s['student_id'] ?>" role="alert" aria-live="polite"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Reset Password <small class="text-lowercase text-muted">(optional)</small></label>
                                    <input type="password" class="form-control" name="password" minlength="8" placeholder="Leave blank to keep current">
                                    <div class="invalid-feedback">Minimum 8 characters.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Phone</label>
                                    <input type="text" class="form-control" name="phone" required pattern="[0-9+\-\s]{7,20}" value="<?= htmlspecialchars($s['phone'] ?? '') ?>">
                                    <div class="invalid-feedback">Enter a valid phone number.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Choose</option>
                                        <option value="male" <?= $s['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= $s['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= $s['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Semester</label>
                                    <input type="text" class="form-control" name="semester" required maxlength="20" value="<?= htmlspecialchars($s['semester'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Account Status</label>
                                    <select class="form-select" name="account_status" required>
                                        <option value="active" <?= $s['account_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $s['account_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Verification Status</label>
                                    <select class="form-select" name="verification_status" required>
                                        <option value="pending" <?= $s['verification_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="verified" <?= $s['verification_status'] === 'verified' ? 'selected' : '' ?>>Verified</option>
                                        <option value="rejected" <?= $s['verification_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted"><?= empty($s['profile_photo']) ? 'Profile Photo' : 'Replace Photo (optional)' ?></label>
                                    <input type="file" class="form-control" name="profile_photo" accept="image/png,image/jpeg">
                                    <?php if (!empty($s['profile_photo'])): ?>
                                        <small class="text-muted">Current: <?= htmlspecialchars($s['profile_photo']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted"><?= empty($s['id_card_image']) ? 'ID Card Image' : 'Replace ID Card (optional)' ?></label>
                                    <input type="file" class="form-control" name="id_card_image" accept="image/png,image/jpeg">
                                    <?php if (!empty($s['id_card_image'])): ?>
                                        <small class="text-muted">Current: <?= htmlspecialchars($s['id_card_image']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">Update Student</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Form Validation -->
    <script>
        document.querySelectorAll(".needs-validation").forEach(form => {
            form.addEventListener("submit", function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add("was-validated");
            });
        });
    </script>

    <!-- Reopen Modal on Failure -->
    <script>
        <?php if ($reopenModal): ?>
            document.addEventListener("DOMContentLoaded", function () {
                const modalEl = document.getElementById(<?= json_encode($reopenModal) ?>);
                if (modalEl) { new bootstrap.Modal(modalEl).show(); }
            });
        <?php endif; ?>
    </script>

    <!-- Table Search, Filter, Limit & Pagination Engine -->
    <script>
        const searchInput = document.getElementById("searchStudent");
        const statusFilter = document.getElementById("statusFilter");
        const limitSelect = document.getElementById("limitSelect");
        const rows = document.querySelectorAll("#studentTable tbody tr");
        const showingCountText = document.getElementById("showingCountText");
        const paginationContainer = document.getElementById("pagination");

        let currentPage = 1;

        function filterStudents() {
            const searchValue = searchInput.value.toLowerCase().trim();
            const statusValue = statusFilter.value.toLowerCase().trim();
            const limitValue = limitSelect.value;

            const matchedRows = [];

            rows.forEach(row => {
                const badge = row.querySelector(".status-badge:not(.verification-badge)");
                if (!badge) return;

                const rowText = row.innerText.toLowerCase();
                const status = badge.innerText.toLowerCase().trim();

                const matchesSearch = rowText.includes(searchValue);
                const matchesStatus = statusValue === "" || status === statusValue;

                if (matchesSearch && matchesStatus) {
                    matchedRows.push(row);
                } else {
                    row.style.display = "none";
                }
            });

            const totalMatched = matchedRows.length;
            let pageSize = parseInt(limitValue, 10) || 5;
            const totalPages = Math.ceil(totalMatched / pageSize) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const endIdx = startIdx + pageSize;

            matchedRows.forEach((row, idx) => {
                row.style.display = (idx >= startIdx && idx < endIdx) ? "" : "none";
            });

            const visibleCount = Math.min(pageSize, totalMatched - startIdx > 0 ? totalMatched - startIdx : 0);
            showingCountText.textContent = `Showing ${visibleCount} of ${totalMatched} students`;

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            paginationContainer.innerHTML = "";
            if (totalPages <= 1) return;

            if (currentPage > 1) {
                const prevLi = document.createElement("li");
                prevLi.className = "page-item";
                prevLi.innerHTML = `<a class="page-link rounded-pill px-3 py-1 text-xs font-bold text-dark border bg-white shadow-sm" style="cursor:pointer;" aria-label="Previous"><i class="bi bi-chevron-left me-1"></i> Prev</a>`;
                prevLi.addEventListener("click", () => { currentPage--; filterStudents(); });
                paginationContainer.appendChild(prevLi);
            }

            const currentLi = document.createElement("li");
            currentLi.className = "page-item active";
            currentLi.innerHTML = `<a class="page-link rounded-circle d-inline-flex align-items-center justify-content-center fw-extrabold text-xs shadow-sm" style="width: 32px; height: 32px; background-color: #1c2024; color: #ffd13b; border: none;">${currentPage}</a>`;
            paginationContainer.appendChild(currentLi);

            if (currentPage < totalPages) {
                const nextLi = document.createElement("li");
                nextLi.className = "page-item";
                nextLi.innerHTML = `<a class="page-link rounded-pill px-3 py-1 text-xs font-bold text-dark border bg-white shadow-sm" style="cursor:pointer;" aria-label="Next">Next <i class="bi bi-chevron-right ms-1"></i></a>`;
                nextLi.addEventListener("click", () => { currentPage++; filterStudents(); });
                paginationContainer.appendChild(nextLi);
            }
        }

        searchInput.addEventListener("keyup", () => { currentPage = 1; filterStudents(); });
        statusFilter.addEventListener("change", () => { currentPage = 1; filterStudents(); });
        limitSelect.addEventListener("change", () => { currentPage = 1; filterStudents(); });

        document.addEventListener("DOMContentLoaded", filterStudents);
    </script>

    <!-- AJAX Status Toggle & Count Progress Bar Update -->
    <script>
        const totalCountBox = document.getElementById("totalCount");
        const activeCountBox = document.getElementById("activeCount");
        const inactiveCountBox = document.getElementById("inactiveCount");
        const activeBarEl = document.getElementById("activeBar");
        const inactiveBarEl = document.getElementById("inactiveBar");

        function setBadge(row, isActive) {
            const badge = row.querySelector(".status-badge:not(.verification-badge)");
            if (badge) {
                if (isActive) {
                    badge.textContent = "Active";
                    badge.className = "status-badge badge-success";
                } else {
                    badge.textContent = "Inactive";
                    badge.className = "status-badge badge-danger";
                }
            }
        }

        function adjustCounts(isActive) {
            let totalVal = parseInt(totalCountBox.textContent, 10) || 1;
            let activeVal = parseInt(activeCountBox.textContent, 10) || 0;
            let inactiveVal = parseInt(inactiveCountBox.textContent, 10) || 0;

            if (isActive) {
                activeVal += 1;
                if (inactiveVal > 0) inactiveVal -= 1;
            } else {
                if (activeVal > 0) activeVal -= 1;
                inactiveVal += 1;
            }

            activeCountBox.textContent = activeVal;
            inactiveCountBox.textContent = inactiveVal;

            if (activeBarEl) {
                let activePct = Math.round((activeVal / totalVal) * 100);
                activeBarEl.style.width = (activeVal > 0 ? Math.max(5, activePct) : 0) + "%";
            }
            if (inactiveBarEl) {
                let inactivePct = Math.round((inactiveVal / totalVal) * 100);
                inactiveBarEl.style.width = (inactiveVal > 0 ? Math.max(5, inactivePct) : 0) + "%";
            }
        }

        document.querySelectorAll(".status-toggle").forEach(toggle => {
            toggle.addEventListener("change", function () {
                const row = this.closest("tr");
                const studentId = this.dataset.id;
                const isActive = this.checked;

                setBadge(row, isActive);
                adjustCounts(isActive);
                filterStudents();
                this.disabled = true;

                fetch("allstudents.php?action=toggle_status", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: studentId, status: isActive ? "active" : "inactive" })
                })
                .then(res => {
                    if (!res.ok) throw new Error("Request failed");
                    return res.json();
                })
                .then(data => {
                    if (!data || !data.success) throw new Error("Update rejected");
                })
                .catch(() => {
                    toggle.checked = !isActive;
                    setBadge(row, !isActive);
                    adjustCounts(!isActive);
                    filterStudents();
                    alert("Could not update status. Please try again.");
                })
                .finally(() => { toggle.disabled = false; });
            });
        });

        function deleteStudent(id) {
            if (confirm("Are you sure you want to delete this student?")) {
                window.location.href = "allstudents.php?action=delete&id=" + id;
            }
        }

        // Async uniqueness checks (enrollment_no / email)
        (function() {
            const DEBOUNCE_MS = 450;
            const timers = new WeakMap();

            function debounce(el, fn) {
                clearTimeout(timers.get(el));
                timers.set(el, setTimeout(fn, DEBOUNCE_MS));
            }

            function setState(input, feedbackEl, spinner, state, message) {
                spinner.classList.toggle("d-none", state !== "checking");
                if (state === "valid") {
                    input.setAttribute("aria-invalid", "false");
                    input.setCustomValidity("");
                    feedbackEl.textContent = "Available";
                    feedbackEl.className = "async-feedback mt-1 text-success font-semibold";
                } else if (state === "invalid") {
                    input.setAttribute("aria-invalid", "true");
                    input.setCustomValidity(message || "Already in use.");
                    feedbackEl.textContent = message || "Already in use.";
                    feedbackEl.className = "async-feedback mt-1 text-danger font-semibold";
                } else if (state === "checking") {
                    input.setCustomValidity("Checking availability…");
                    feedbackEl.textContent = "Checking availability…";
                    feedbackEl.className = "async-feedback mt-1 text-muted";
                } else {
                    input.setCustomValidity("");
                    feedbackEl.textContent = "";
                    feedbackEl.className = "async-feedback mt-1";
                }
                input.dataset.asyncState = state;
            }

            async function runCheck(input) {
                const form = input.closest("form");
                if (!form) return;
                const field = input.dataset.asyncCheck;
                const value = input.value.trim();
                const feedbackEl = document.getElementById(input.getAttribute("aria-describedby"));
                const spinner = input.parentElement.querySelector(".async-spinner");
                const idField = form.querySelector('input[name="id"]');

                if (!feedbackEl || !spinner) return;
                if (!value || !input.checkValidity()) {
                    setState(input, feedbackEl, spinner, "idle");
                    return;
                }

                setState(input, feedbackEl, spinner, "checking");

                try {
                    const res = await fetch("allstudents.php?action=check_unique", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ field, value, id: idField ? idField.value : null })
                    });
                    if (!res.ok) throw new Error("Request failed");
                    const data = await res.json();
                    setState(input, feedbackEl, spinner, data.available ? "valid" : "invalid", data.message);
                } catch {
                    setState(input, feedbackEl, spinner, "idle");
                }
            }

            document.addEventListener("input", function(e) {
                if (!e.target.matches("[data-async-check]")) return;
                debounce(e.target, () => runCheck(e.target));
            });
        })();

        // Searchable University dropdown + University -> College filtering (client-side)
        (function() {
            function buildCollegeOptionsCache(collegeSel) {
                if (collegeSel.dataset.cached) return;
                collegeSel._fullOptions = Array.from(collegeSel.querySelectorAll("option[data-university]")).map(opt => opt.cloneNode(true));
                collegeSel.dataset.cached = "1";
            }

            function refreshCollegeOptions(uniSel, isInitial) {
                const form = uniSel.closest("form");
                if (!form) return;
                const collegeSel = form.querySelector(".college-select");
                if (!collegeSel) return;

                buildCollegeOptionsCache(collegeSel);

                const uniId = String(uniSel.value || "").trim();
                const presetCollegeId = collegeSel.dataset.preset || "";

                const hasJQ = typeof window.jQuery !== "undefined";
                const isSelect2 = hasJQ && jQuery(collegeSel).hasClass("select2-hidden-accessible");
                if (isSelect2) {
                    jQuery(collegeSel).select2("destroy");
                }

                collegeSel.innerHTML = "";
                const placeholder = document.createElement("option");
                placeholder.value = "";
                placeholder.textContent = uniId ? "Search College..." : "Select University First";
                collegeSel.appendChild(placeholder);

                if (uniId) {
                    collegeSel._fullOptions.forEach(function(opt) {
                        if (String(opt.dataset.university) === uniId) {
                            collegeSel.appendChild(opt.cloneNode(true));
                        }
                    });
                }

                collegeSel.value = "";

                if (isInitial && presetCollegeId && uniId) {
                    collegeSel.value = presetCollegeId;
                }

                if (hasJQ) {
                    const modalEl = form.closest(".modal");
                    jQuery(collegeSel).select2({
                        placeholder: uniId ? "Search College..." : "Select University First",
                        width: "100%",
                        dropdownParent: modalEl ? jQuery(modalEl) : jQuery(document.body)
                    });
                }
            }

            function initUniversitySelect2(uniSel) {
                if (typeof window.jQuery === "undefined") return;
                const $sel = jQuery(uniSel);
                if ($sel.hasClass("select2-hidden-accessible")) return;
                const modalEl = uniSel.closest(".modal");
                $sel.select2({
                    placeholder: "Search University...",
                    width: "100%",
                    dropdownParent: modalEl ? jQuery(modalEl) : jQuery(document.body)
                });
            }

            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".modal").forEach(function(modalEl) {
                    const uniSel = modalEl.querySelector(".university-select");
                    const collegeSel = modalEl.querySelector(".college-select");
                    if (!uniSel || !collegeSel) return;

                    modalEl.addEventListener("shown.bs.modal", function() {
                        initUniversitySelect2(uniSel);
                        refreshCollegeOptions(uniSel, true);
                    });

                    if (typeof window.jQuery !== "undefined") {
                        jQuery(uniSel).on("change", function() {
                            refreshCollegeOptions(uniSel, false);
                        });
                    } else {
                        uniSel.addEventListener("change", function() {
                            refreshCollegeOptions(uniSel, false);
                        });
                    }
                });
            });
        })();
    </script>
</body>
</html>