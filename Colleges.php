<?php
/**
 * colleges.php
 * Single-file College management: DB connection, validation,
 * create / read / update / delete, status toggle (AJAX),
 * async uniqueness checks (AJAX), limit & pagination, and UI.
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

$LOGO_DIR = __DIR__ . '/assets/images/colleges';
$LOGO_WEB_PATH = 'assets/images/colleges/';

/* =========================================================
   VALIDATION HELPERS
   ========================================================= */
function validate_college(
    PDO $pdo,
    array $data,
    ?int $excludeId = null,
    ?array $logoFile = null,
    ?string $existingLogo = null
): array {
    $errors = [];

    $name         = trim($data['name'] ?? '');
    $slug         = trim($data['slug'] ?? '');
    $universityId = filter_var($data['university_id'] ?? null, FILTER_VALIDATE_INT);
    $email        = trim($data['email'] ?? '');
    $phone        = trim($data['phone'] ?? '');
    $address      = trim($data['address'] ?? '');
    $status       = trim($data['status'] ?? '');
    $password     = (string) ($data['password'] ?? '');

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'College name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'College name is too long (max 150 characters).';
    }

    if ($slug === '') {
        $errors[] = 'Slug / short name is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-]{1,150}$/', $slug)) {
        $errors[] = 'Slug may only contain letters, numbers, and hyphens (max 150 chars).';
    }

    if (!$universityId) {
        $errors[] = 'Select a valid university.';
    } else {
        $chk = $pdo->prepare('SELECT university_id FROM universities WHERE university_id = :id');
        $chk->execute(['id' => $universityId]);
        if (!$chk->fetch()) {
            $errors[] = 'Selected university does not exist.';
        }
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors[] = 'Enter a valid email address (max 100 characters).';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    }

    if ($address === '' || mb_strlen($address) < 5) {
        $errors[] = 'Address is required (min 5 characters).';
    }

    if (!logo_file_provided($logoFile) && empty($existingLogo)) {
        $errors[] = 'College logo is required.';
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Select a valid status.';
    }

    if ($excludeId === null) {
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
    } elseif ($password !== '' && mb_strlen($password) < 8) {
        $errors[] = 'New password must be at least 8 characters (leave blank to keep the current password).';
    }

    if ($slug !== '' && preg_match('/^[A-Za-z0-9\-]{1,150}$/', $slug)) {
        $sql = 'SELECT college_id FROM colleges WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND college_id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'This slug is already in use.';
        }
    }

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql = 'SELECT college_id FROM colleges WHERE email = :email';
        $params = ['email' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND college_id != :id';
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

function logo_file_provided(?array $file): bool
{
    return $file !== null && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

function handle_logo_upload(?array $file, string $destDir): ?string
{
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo upload failed. Please try again.');
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('Only JPG or PNG images are allowed.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Logo must be smaller than 2MB.');
    }

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $filename = 'c_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mime];

    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
        throw new RuntimeException('Could not save uploaded logo.');
    }

    return $filename;
}

/* =========================================================
   AJAX HANDLERS
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

    $check = $pdo->prepare('SELECT college_id FROM colleges WHERE college_id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'College not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE colleges SET status = :status, updated_at = NOW() WHERE college_id = :id');
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

    if (!in_array($field, ['slug', 'email'], true) || $value === '') {
        http_response_code(422);
        echo json_encode(['available' => false, 'message' => 'Invalid request.']);
        exit;
    }

    if ($field === 'slug' && !preg_match('/^[A-Za-z0-9\-]{1,150}$/', $value)) {
        echo json_encode(['available' => false, 'message' => 'Invalid slug format.']);
        exit;
    }

    if ($field === 'email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || mb_strlen($value) > 100)) {
        echo json_encode(['available' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    $sql = "SELECT college_id FROM colleges WHERE {$field} = :value";
    $params = ['value' => $value];
    if ($excludeId !== null) {
        $sql .= ' AND college_id != :id';
        $params['id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exists = (bool) $stmt->fetch();

    echo json_encode([
        'available' => !$exists,
        'message'   => $exists
            ? ($field === 'slug' ? 'This slug is already in use.' : 'This email is already registered.')
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
        $stmt = $pdo->prepare('SELECT logo FROM colleges WHERE college_id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare('DELETE FROM colleges WHERE college_id = :id')->execute(['id' => $id]);

            if (!empty($row['logo'])) {
                $path = $LOGO_DIR . '/' . $row['logo'];
                if (is_file($path)) unlink($path);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'College deleted successfully.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'College not found.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid college id.'];
    }

    header('Location: colleges.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {

    if ($_POST['form_action'] === 'create') {

        $errors = validate_college($pdo, $_POST, null, $_FILES['logo'] ?? null, null);

        if (empty($errors)) {
            try {
                $logo = handle_logo_upload($_FILES['logo'] ?? null, $LOGO_DIR);

                $stmt = $pdo->prepare(
                    'INSERT INTO colleges (university_id, name, slug, email, password, phone, address, logo, status, created_at, updated_at)
                     VALUES (:university_id, :name, :slug, :email, :password, :phone, :address, :logo, :status, NOW(), NOW())'
                );
                $stmt->execute([
                    'university_id' => (int) $_POST['university_id'],
                    'name'          => trim($_POST['name']),
                    'slug'          => trim($_POST['slug']),
                    'email'         => trim($_POST['email']),
                    'password'      => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    'phone'         => trim($_POST['phone']),
                    'address'       => trim($_POST['address']),
                    'logo'          => $logo,
                    'status'        => trim($_POST['status']),
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'College added successfully.'];
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                $errors[] = $e->getCode() === '23000'
                    ? 'This email is already registered.'
                    : 'Could not save college. Please try again.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'addCollegeModal';
        }

        header('Location: colleges.php');
        exit;
    }

    if ($_POST['form_action'] === 'update') {

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $existingStmt = $pdo->prepare('SELECT * FROM colleges WHERE college_id = :id');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();

        if (!$id || !$existing) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'College not found.'];
            header('Location: colleges.php');
            exit;
        }

        $errors = validate_college($pdo, $_POST, $id, $_FILES['logo'] ?? null, $existing['logo'] ?? null);

        if (empty($errors)) {
            try {
                $newLogo = handle_logo_upload($_FILES['logo'] ?? null, $LOGO_DIR);
                $logoToStore = $existing['logo'];

                if ($newLogo !== null) {
                    if (!empty($existing['logo'])) {
                        $oldPath = $LOGO_DIR . '/' . $existing['logo'];
                        if (is_file($oldPath)) unlink($oldPath);
                    }
                    $logoToStore = $newLogo;
                }

                $stmt = $pdo->prepare(
                    'UPDATE colleges
                     SET university_id = :university_id, name = :name, slug = :slug, email = :email,
                         phone = :phone, address = :address, logo = :logo,
                         status = :status, updated_at = NOW()
                         ' . (trim($_POST['password'] ?? '') !== '' ? ', password = :password' : '') . '
                     WHERE college_id = :id'
                );

                $params = [
                    'university_id' => (int) $_POST['university_id'],
                    'name'          => trim($_POST['name']),
                    'slug'          => trim($_POST['slug']),
                    'email'         => trim($_POST['email']),
                    'phone'         => trim($_POST['phone']),
                    'address'       => trim($_POST['address']),
                    'logo'          => $logoToStore,
                    'status'        => trim($_POST['status']),
                    'id'            => $id,
                ];

                if (trim($_POST['password'] ?? '') !== '') {
                    $params['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                $stmt->execute($params);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'College updated successfully.'];
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                $errors[] = $e->getCode() === '23000'
                    ? 'This email is already registered.'
                    : 'Could not update college. Please try again.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'editCollegeModal' . $id;
        }

        header('Location: colleges.php');
        exit;
    }
}

/* =========================================================
   DATA FETCHING
   ========================================================= */
$colleges = $pdo->query(
    'SELECT c.*, u.name AS university_name
     FROM colleges c
     LEFT JOIN universities u ON u.university_id = c.university_id
     ORDER BY c.created_at DESC'
)->fetchAll();

// Only fetch active universities for creation
$activeUniversities = $pdo->query("SELECT university_id, name FROM universities WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// All universities for edit lookup
$allUniversities = $pdo->query('SELECT university_id, name FROM universities ORDER BY name ASC')->fetchAll();

$total    = count($colleges);
$active   = count(array_filter($colleges, fn($c) => $c['status'] === 'active'));
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
    <title>All Colleges | Evenza Admin</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Select2 (searchable dropdown for University field) -->
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

        .college-logo {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid #e8e4d9;
            background: #f8f6f0;
        }

        .college-name { font-weight: 700; color: var(--color-text-dark); font-size: 14px; }

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

        .async-feedback { min-height: 18px; }
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
                    <h1 class="berun-greeting-h1">All Colleges</h1>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li>College Management</li>
                        <li>All Colleges</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="berun-search-wrapper">
                    <i class="bi bi-search berun-search-icon"></i>
                    <input type="text" id="collegeSearch" class="berun-search-input" placeholder="Search college name, slug, email..." />
                </div>

                <button type="button" class="berun-btn-dark" data-bs-toggle="modal" data-bs-target="#addCollegeModal">
                    <i class="bi bi-plus-lg"></i> Add College
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
                    <!-- Total Colleges Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Colleges</span>
                                    <h2 id="totalCount" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 26px;"><?= $total ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-primary">
                                    <i class="bi bi-building"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8edff; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #4f46e5; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Colleges Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Active Colleges</span>
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

                    <!-- Inactive Colleges Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Inactive Colleges</span>
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

                <!-- Main College List Table Card -->
                <div class="berun-card-panel p-0 overflow-hidden">
                    <div class="p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-color:#f4f2eb !important;">
                        <div>
                            <h3 class="berun-panel-title">College List</h3>
                            <p class="berun-panel-sub">View and manage all registered colleges</p>
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
                        <table class="table table-hover align-middle mb-0" id="collegeTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>College</th>
                                    <th>University</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Status Toggle</th>
                                    <th>Created</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total === 0): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            No colleges yet. Click "Add College" to create one.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($colleges as $i => $c): ?>
                                        <?php
                                        $isActive = $c['status'] === 'active';
                                        $logoSrc = !empty($c['logo']) ? $LOGO_WEB_PATH . htmlspecialchars($c['logo']) : $LOGO_WEB_PATH . 'placeholder.png';
                                        ?>
                                        <tr>
                                            <td class="ps-4 font-semibold text-muted"><?= $i + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= $logoSrc ?>" class="college-logo" alt="College Logo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($c['name']) ?>&background=f4f2eb&color=1c2024'" />
                                                    <div>
                                                        <div class="college-name"><?= htmlspecialchars($c['name']) ?></div>
                                                        <small class="text-muted d-block" style="font-size: 11px; font-weight: 500;">
                                                            <?= htmlspecialchars($c['slug']) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-dark font-medium"><?= htmlspecialchars($c['university_name'] ?? 'Unknown') ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($c['email']) ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                                            <td>
                                                <span class="status-badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input status-toggle" type="checkbox" role="switch" data-id="<?= (int) $c['college_id'] ?>" <?= $isActive ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                            <td class="text-muted small"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                            <td class="pe-4 text-end">
                                                <button type="button" class="action-btn me-1" title="Edit" data-bs-toggle="modal" data-bs-target="#editCollegeModal<?= (int) $c['college_id'] ?>">
                                                    <i class="bi bi-pencil text-primary"></i>
                                                </button>
                                                <button type="button" class="action-btn" title="Delete" onclick="deleteCollege(<?= (int) $c['college_id'] ?>)">
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
                                    <span class="ms-1 text-dark font-medium" id="showingCountText">Showing 0 of 0 colleges</span>
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

    <!-- ADD COLLEGE MODAL -->
    <div class="modal fade" id="addCollegeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-building me-2"></i> Add College</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">College Name</label>
                                <input type="text" class="form-control" name="name" required minlength="2" placeholder="e.g. Stanford College of Engineering">
                                <div class="invalid-feedback">College name is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Slug / Short Name</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" name="slug" required maxlength="150" pattern="[A-Za-z0-9\-]+" data-async-check="slug" aria-describedby="slugAsyncFeedbackAdd" placeholder="e.g. stanford-engineering">
                                    <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                </div>
                                <div class="invalid-feedback">Letters, numbers, hyphens only.</div>
                                <div class="async-feedback small mt-1" id="slugAsyncFeedbackAdd" role="alert" aria-live="polite"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">University</label>
                                <select class="form-select university-select" name="university_id" required>
                                    <option value="">Choose university</option>
                                    <?php foreach ($activeUniversities as $u): ?>
                                        <option value="<?= (int) $u['university_id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Select an active university.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Email</label>
                                <div class="position-relative">
                                    <input type="email" class="form-control" name="email" required maxlength="100" data-async-check="email" aria-describedby="emailAsyncFeedbackAdd" placeholder="engineering@stanford.edu">
                                    <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                </div>
                                <div class="invalid-feedback">Enter a valid email.</div>
                                <div class="async-feedback small mt-1" id="emailAsyncFeedbackAdd" role="alert" aria-live="polite"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Login Password</label>
                                <input type="password" class="form-control" name="password" required minlength="8" placeholder="Minimum 8 characters">
                                <div class="invalid-feedback">Minimum 8 characters.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Phone</label>
                                <input type="text" class="form-control" name="phone" required pattern="[0-9+\-\s]{7,20}" placeholder="+1 555 111 001">
                                <div class="invalid-feedback">Phone number is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="">Choose status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="invalid-feedback">Select a status.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Logo <small class="text-lowercase text-muted">(JPG/PNG, max 2MB)</small></label>
                                <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg" required>
                                <div class="invalid-feedback">College logo is required.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Address</label>
                                <textarea class="form-control" name="address" style="height:80px" required minlength="5" placeholder="College full address..."></textarea>
                                <div class="invalid-feedback">Address is required (min 5 characters).</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Save College</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT COLLEGE MODALS -->
    <?php foreach ($colleges as $c): ?>
        <div class="modal fade" id="editCollegeModal<?= (int) $c['college_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $c['college_id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit College — <?= htmlspecialchars($c['slug']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">College Name</label>
                                    <input type="text" class="form-control" name="name" required minlength="2" value="<?= htmlspecialchars($c['name']) ?>">
                                    <div class="invalid-feedback">College name is required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Slug / Short Name</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" name="slug" required maxlength="150" pattern="[A-Za-z0-9\-]+" value="<?= htmlspecialchars($c['slug']) ?>" data-async-check="slug" aria-describedby="slugAsyncFeedback<?= (int) $c['college_id'] ?>">
                                        <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                    </div>
                                    <div class="invalid-feedback">Letters, numbers, hyphens only.</div>
                                    <div class="async-feedback small mt-1" id="slugAsyncFeedback<?= (int) $c['college_id'] ?>" role="alert" aria-live="polite"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">University</label>
                                    <select class="form-select university-select" name="university_id" required>
                                        <?php foreach ($allUniversities as $u): ?>
                                            <option value="<?= (int) $u['university_id'] ?>" <?= (int) $u['university_id'] === (int) $c['university_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Email</label>
                                    <div class="position-relative">
                                        <input type="email" class="form-control" name="email" required maxlength="100" value="<?= htmlspecialchars($c['email']) ?>" data-async-check="email" aria-describedby="emailAsyncFeedback<?= (int) $c['college_id'] ?>">
                                        <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                    </div>
                                    <div class="invalid-feedback">Enter a valid email.</div>
                                    <div class="async-feedback small mt-1" id="emailAsyncFeedback<?= (int) $c['college_id'] ?>" role="alert" aria-live="polite"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Reset Password <small class="text-lowercase text-muted">(optional)</small></label>
                                    <input type="password" class="form-control" name="password" minlength="8" placeholder="Leave blank to keep current">
                                    <div class="invalid-feedback">Minimum 8 characters.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Phone</label>
                                    <input type="text" class="form-control" name="phone" required pattern="[0-9+\-\s]{7,20}" value="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                    <div class="invalid-feedback">Phone number is required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $c['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">
                                        <?= empty($c['logo']) ? 'Logo' : 'Replace Logo (optional)' ?>
                                    </label>
                                    <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg" <?= empty($c['logo']) ? 'required' : '' ?>>
                                    <?php if (!empty($c['logo'])): ?>
                                        <small class="text-muted">Current: <?= htmlspecialchars($c['logo']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Address</label>
                                    <textarea class="form-control" name="address" style="height:80px" required minlength="5"><?= htmlspecialchars($c['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">Update College</button>
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

    <!-- Reopen Modal on Validation Failure -->
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
        const searchInput = document.getElementById("collegeSearch");
        const statusFilter = document.getElementById("statusFilter");
        const limitSelect = document.getElementById("limitSelect");
        const rows = document.querySelectorAll("#collegeTable tbody tr");
        const showingCountText = document.getElementById("showingCountText");
        const paginationContainer = document.getElementById("pagination");

        let currentPage = 1;

        function filterColleges() {
            const searchValue = searchInput.value.toLowerCase().trim();
            const statusValue = statusFilter.value.toLowerCase().trim();
            const limitValue = limitSelect.value;

            const matchedRows = [];

            rows.forEach(row => {
                const badge = row.querySelector(".status-badge");
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
            showingCountText.textContent = `Showing ${visibleCount} of ${totalMatched} colleges`;

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            paginationContainer.innerHTML = "";
            if (totalPages <= 1) return;

            if (currentPage > 1) {
                const prevLi = document.createElement("li");
                prevLi.className = "page-item";
                prevLi.innerHTML = `<a class="page-link rounded-pill px-3 py-1 text-xs font-bold text-dark border bg-white shadow-sm" style="cursor:pointer;" aria-label="Previous"><i class="bi bi-chevron-left me-1"></i> Prev</a>`;
                prevLi.addEventListener("click", () => { currentPage--; filterColleges(); });
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
                nextLi.addEventListener("click", () => { currentPage++; filterColleges(); });
                paginationContainer.appendChild(nextLi);
            }
        }

        searchInput.addEventListener("keyup", () => { currentPage = 1; filterColleges(); });
        statusFilter.addEventListener("change", () => { currentPage = 1; filterColleges(); });
        limitSelect.addEventListener("change", () => { currentPage = 1; filterColleges(); });

        document.addEventListener("DOMContentLoaded", filterColleges);
    </script>

    <!-- AJAX Status Toggle & Count Progress Update -->
    <script>
        const totalCountBox = document.getElementById("totalCount");
        const activeCountBox = document.getElementById("activeCount");
        const inactiveCountBox = document.getElementById("inactiveCount");
        const activeBarEl = document.getElementById("activeBar");
        const inactiveBarEl = document.getElementById("inactiveBar");

        function setBadge(row, isActive) {
            const badge = row.querySelector(".status-badge");
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
                const collegeId = this.dataset.id;
                const isActive = this.checked;

                setBadge(row, isActive);
                adjustCounts(isActive);
                filterColleges();
                this.disabled = true;

                fetch("colleges.php?action=toggle_status", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: collegeId, status: isActive ? "active" : "inactive" })
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
                    filterColleges();
                    alert("Could not update status. Please try again.");
                })
                .finally(() => { toggle.disabled = false; });
            });
        });

        function deleteCollege(id) {
            if (confirm("Are you sure you want to delete this college?")) {
                window.location.href = "colleges.php?action=delete&id=" + id;
            }
        }

        // Async uniqueness validation
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
                    feedbackEl.classList.remove("text-danger");
                    feedbackEl.classList.add("text-success");
                } else if (state === "invalid") {
                    input.setAttribute("aria-invalid", "true");
                    input.setCustomValidity(message || "Already in use.");
                    feedbackEl.textContent = message || "Already in use.";
                    feedbackEl.classList.remove("text-success");
                    feedbackEl.classList.add("text-danger");
                } else if (state === "checking") {
                    input.setCustomValidity("Checking availability…");
                    feedbackEl.textContent = "Checking availability…";
                    feedbackEl.classList.remove("text-success", "text-danger");
                } else {
                    input.setCustomValidity("");
                    feedbackEl.textContent = "";
                    feedbackEl.classList.remove("text-success", "text-danger");
                }
                input.dataset.asyncState = state;
            }

            async function runCheck(input) {
                const form = input.closest("form");
                if (!form) return;

                const field = input.dataset.asyncCheck;
                const value = input.value.trim();
                const feedbackId = input.getAttribute("aria-describedby");
                const feedbackEl = feedbackId ? document.getElementById(feedbackId) : null;
                const spinner = input.parentElement.querySelector(".async-spinner");
                const idField = form.querySelector('input[name="id"]');

                if (!feedbackEl || !spinner) return;

                if (!value || !input.checkValidity()) {
                    setState(input, feedbackEl, spinner, "idle");
                    return;
                }

                setState(input, feedbackEl, spinner, "checking");

                try {
                    const res = await fetch("colleges.php?action=check_unique", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            field,
                            value,
                            id: idField ? idField.value : null
                        })
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

        // Searchable University dropdown (Select2)
        (function() {
            function initSelect2WithinModal(modalEl) {
                var $modal = jQuery(modalEl);
                $modal.find(".university-select").each(function() {
                    var $sel = jQuery(this);
                    if ($sel.hasClass("select2-hidden-accessible")) return;
                    $sel.select2({
                        placeholder: "Search university...",
                        width: "100%",
                        dropdownParent: $modal,
                        allowClear: false
                    });
                });
            }

            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".modal").forEach(function(modalEl) {
                    if (!modalEl.querySelector(".university-select")) return;
                    modalEl.addEventListener("shown.bs.modal", function() {
                        initSelect2WithinModal(modalEl);
                    });
                });
            });
        })();
    </script>
</body>
</html>