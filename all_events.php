<?php
/**
 * all_events.php
 * Single-file Event management: DB connection, validation,
 * create / read / update / delete, status toggle (AJAX), limit & compact pagination, and UI theme.
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

/* =========================================================
   VALIDATION HELPERS
   ========================================================= */
function validate_event(PDO $pdo, array $data): array
{
    $errors = [];

    $college_id_raw         = $data['college_id'] ?? null;
    $category_id_raw        = $data['category_id'] ?? '';
    $college_id             = filter_var($college_id_raw, FILTER_VALIDATE_INT);
    $category_id            = ($category_id_raw !== '' && $category_id_raw !== null)
        ? filter_var($category_id_raw, FILTER_VALIDATE_INT)
        : null;
    $title                  = trim($data['title'] ?? '');
    $description             = trim($data['description'] ?? '');
    $event_type             = trim($data['event_type'] ?? '');
    $min_team_size          = filter_var($data['min_team_size'] ?? 1, FILTER_VALIDATE_INT);
    $max_team_size          = filter_var($data['max_team_size'] ?? 1, FILTER_VALIDATE_INT);
    $fee_type               = trim($data['fee_type'] ?? '');
    $registration_fee       = filter_var($data['registration_fee'] ?? 0.00, FILTER_VALIDATE_FLOAT);
    $registration_deadline  = trim($data['registration_deadline'] ?? '');
    $event_date             = trim($data['event_date'] ?? '');
    $start_time             = trim($data['start_time'] ?? '');
    $end_time               = trim($data['end_time'] ?? '');
    $venue                  = trim($data['venue'] ?? '');
    $dress_code             = trim($data['dress_code'] ?? '');
    $status                 = trim($data['status'] ?? 'draft');

    if (!$college_id) {
        $errors[] = 'Please select a college.';
    } else {
        $chk = $pdo->prepare('SELECT college_id FROM colleges WHERE college_id = :id');
        $chk->execute(['id' => $college_id]);
        if (!$chk->fetch()) {
            $errors[] = 'Selected college does not exist.';
        }
    }

    if (!$category_id) {
        $errors[] = 'Please select a category.';
    } else {
        $chk = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :id');
        $chk->execute(['id' => $category_id]);
        if (!$chk->fetch()) {
            $errors[] = 'Selected category does not exist.';
        }
    }

    if ($title === '' || mb_strlen($title) < 2) {
        $errors[] = 'Event title must be at least 2 characters.';
    } elseif (mb_strlen($title) > 150) {
        $errors[] = 'Title cannot exceed 150 characters.';
    }

    if ($description === '' || mb_strlen($description) < 10) {
        $errors[] = 'Description is required (at least 10 characters).';
    } elseif (mb_strlen($description) > 2000) {
        $errors[] = 'Description cannot exceed 2000 characters.';
    }

    if (!in_array($event_type, ['solo', 'team'], true)) {
        $errors[] = 'Select a valid event type.';
    }

    if ($event_type === 'team') {
        if ($min_team_size === false || $min_team_size < 1) {
            $errors[] = 'Minimum team size must be at least 1.';
        }
        if ($max_team_size === false || $max_team_size < 1) {
            $errors[] = 'Maximum team size must be at least 1.';
        }
        if ($min_team_size !== false && $max_team_size !== false && $max_team_size < $min_team_size) {
            $errors[] = 'Maximum team size cannot be less than minimum team size.';
        }
        if ($max_team_size !== false && $max_team_size > 100) {
            $errors[] = 'Maximum team size cannot exceed 100.';
        }
    }

    if (!in_array($fee_type, ['per_person', 'per_team'], true)) {
        $errors[] = 'Select a valid fee type.';
    }

    if ($registration_fee === false) {
        $errors[] = 'Enter a valid registration fee.';
    } elseif ($registration_fee < 0) {
        $errors[] = 'Registration fee cannot be less than 0.';
    } elseif ($registration_fee > 1000000) {
        $errors[] = 'Registration fee cannot exceed ₹10,00,000.';
    }

    $eventDateObj = null;
    if ($event_date === '') {
        $errors[] = 'Event date is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $event_date);
        if (!$d || $d->format('Y-m-d') !== $event_date) {
            $errors[] = 'Enter a valid event date.';
        } else {
            $eventDateObj = $d;
        }
    }

    if ($start_time === '') {
        $errors[] = 'Start time is required.';
    }
    if ($end_time === '') {
        $errors[] = 'End time is required.';
    }
    if ($start_time !== '' && $end_time !== '') {
        $s = DateTime::createFromFormat('H:i', $start_time) ?: DateTime::createFromFormat('H:i:s', $start_time);
        $e = DateTime::createFromFormat('H:i', $end_time) ?: DateTime::createFromFormat('H:i:s', $end_time);
        if (!$s || !$e) {
            $errors[] = 'Enter valid start and end times.';
        } elseif ($e <= $s) {
            $errors[] = 'End time must be after start time.';
        }
    }

    if ($registration_deadline === '') {
        $errors[] = 'Registration deadline is required.';
    } else {
        $dl = DateTime::createFromFormat('Y-m-d\TH:i', $registration_deadline)
            ?: DateTime::createFromFormat('Y-m-d H:i', $registration_deadline)
            ?: DateTime::createFromFormat('Y-m-d H:i:s', $registration_deadline);

        if (!$dl) {
            $errors[] = 'Enter a valid registration deadline.';
        } elseif ($eventDateObj !== null) {
            $eventEndOfDay = clone $eventDateObj;
            $eventEndOfDay->setTime(23, 59, 59);
            if ($dl > $eventEndOfDay) {
                $errors[] = 'Registration deadline must be on or before the event date.';
            }
        }
    }

    if ($venue === '' || mb_strlen($venue) < 3) {
        $errors[] = 'Venue is required (at least 3 characters).';
    } elseif (mb_strlen($venue) > 200) {
        $errors[] = 'Venue cannot exceed 200 characters.';
    }

    if ($dress_code === '' || mb_strlen($dress_code) < 2) {
        $errors[] = 'Dress code is required.';
    } elseif (mb_strlen($dress_code) > 255) {
        $errors[] = 'Dress code cannot exceed 255 characters.';
    }

    if (!in_array($status, ['draft', 'published', 'completed', 'cancelled'], true)) {
        $errors[] = 'Select a valid status.';
    }

    return $errors;
}

/* =========================================================
   AJAX HANDLERS
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'toggle_status') {
    header('Content-Type: application/json');

    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
    $status = strtolower(trim($body['status'] ?? ''));

    if (!$id || !in_array($status, ['draft', 'published', 'completed', 'cancelled'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    $check = $pdo->prepare('SELECT event_id FROM events WHERE event_id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE events SET status = :status, updated_at = NOW() WHERE event_id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);

    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'check_college') {
    header('Content-Type: application/json');

    $body      = json_decode(file_get_contents('php://input'), true);
    $collegeId = filter_var($body['college_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$collegeId) {
        echo json_encode(['valid' => false, 'message' => 'Select a college.']);
        exit;
    }

    $chk = $pdo->prepare('SELECT college_id, status FROM colleges WHERE college_id = :id');
    $chk->execute(['id' => $collegeId]);
    $row = $chk->fetch();

    if (!$row) {
        echo json_encode(['valid' => false, 'message' => 'Selected college does not exist.']);
        exit;
    }

    echo json_encode([
        'valid'   => true,
        'message' => $row['status'] === 'inactive' ? 'Note: this college is currently inactive.' : null,
    ]);
    exit;
}

/* =========================================================
   DELETE & POST HANDLERS
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        $check = $pdo->prepare('SELECT event_id FROM events WHERE event_id = :id');
        $check->execute(['id' => $id]);
        
        if ($check->fetch()) {
            $pdo->prepare('DELETE FROM events WHERE event_id = :id')->execute(['id' => $id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Event deleted successfully.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Event not found.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid event identity.'];
    }

    header('Location: all_events.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {

    if ($_POST['form_action'] === 'create') {
        $errors = validate_event($pdo, $_POST);

        if (empty($errors)) {
            try {
                $eventType = trim($_POST['event_type']);
                $minTeamSize = $eventType === 'team' ? (int)($_POST['min_team_size'] ?? 1) : 1;
                $maxTeamSize = $eventType === 'team' ? (int)($_POST['max_team_size'] ?? 1) : 1;

                $stmt = $pdo->prepare(
                    'INSERT INTO events (college_id, category_id, title, description, event_type, min_team_size, max_team_size, fee_type, registration_fee, registration_deadline, event_date, start_time, end_time, venue, dress_code, status)
                     VALUES (:college_id, :category_id, :title, :description, :event_type, :min_team_size, :max_team_size, :fee_type, :registration_fee, :registration_deadline, :event_date, :start_time, :end_time, :venue, :dress_code, :status)'
                );
                $stmt->execute([
                    'college_id'            => (int)$_POST['college_id'],
                    'category_id'           => filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT) ?: null,
                    'title'                 => trim($_POST['title']),
                    'description'           => trim($_POST['description'] ?? ''),
                    'event_type'            => $eventType,
                    'min_team_size'         => $minTeamSize,
                    'max_team_size'         => $maxTeamSize,
                    'fee_type'              => trim($_POST['fee_type']),
                    'registration_fee'      => $_POST['registration_fee'] ?? 0.00,
                    'registration_deadline' => !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null,
                    'event_date'            => $_POST['event_date'],
                    'start_time'            => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
                    'end_time'              => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
                    'venue'                 => trim($_POST['venue'] ?? ''),
                    'dress_code'            => trim($_POST['dress_code'] ?? ''),
                    'status'                => trim($_POST['status'] ?? 'draft'),
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Event added successfully.'];
            } catch (PDOException $e) {
                $errors[] = $e->getCode() === '23000'
                    ? 'The selected college/category is no longer valid.'
                    : 'Could not save event. Please try again.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'addEventModal';
        }

        header('Location: all_events.php');
        exit;
    }

    if ($_POST['form_action'] === 'update') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Event not found.'];
            header('Location: all_events.php');
            exit;
        }

        $existsCheck = $pdo->prepare('SELECT event_id FROM events WHERE event_id = :id');
        $existsCheck->execute(['id' => $id]);
        if (!$existsCheck->fetch()) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Event not found.'];
            header('Location: all_events.php');
            exit;
        }

        $errors = validate_event($pdo, $_POST);

        if (empty($errors)) {
            try {
                $eventType = trim($_POST['event_type']);
                $minTeamSize = $eventType === 'team' ? (int)($_POST['min_team_size'] ?? 1) : 1;
                $maxTeamSize = $eventType === 'team' ? (int)($_POST['max_team_size'] ?? 1) : 1;

                $stmt = $pdo->prepare(
                    'UPDATE events 
                     SET college_id = :college_id, category_id = :category_id, title = :title, description = :description, 
                         event_type = :event_type, min_team_size = :min_team_size, max_team_size = :max_team_size, 
                         fee_type = :fee_type, registration_fee = :registration_fee, registration_deadline = :registration_deadline, 
                         event_date = :event_date, start_time = :start_time, end_time = :end_time, venue = :venue, 
                         dress_code = :dress_code, status = :status, updated_at = NOW()
                     WHERE event_id = :id'
                );
                $stmt->execute([
                    'college_id'            => (int)$_POST['college_id'],
                    'category_id'           => filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT) ?: null,
                    'title'                 => trim($_POST['title']),
                    'description'           => trim($_POST['description'] ?? ''),
                    'event_type'            => $eventType,
                    'min_team_size'         => $minTeamSize,
                    'max_team_size'         => $maxTeamSize,
                    'fee_type'              => trim($_POST['fee_type']),
                    'registration_fee'      => $_POST['registration_fee'],
                    'registration_deadline' => !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null,
                    'event_date'            => $_POST['event_date'],
                    'start_time'            => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
                    'end_time'              => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
                    'venue'                 => trim($_POST['venue'] ?? ''),
                    'dress_code'            => trim($_POST['dress_code'] ?? ''),
                    'status'                => trim($_POST['status']),
                    'id'                    => $id,
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Event updated successfully.'];
            } catch (PDOException $e) {
                $errors[] = $e->getCode() === '23000'
                    ? 'The selected college/category is no longer valid.'
                    : 'Could not update event.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'editEventModal' . $id;
        }

        header('Location: all_events.php');
        exit;
    }
}

/* =========================================================
   DATA FETCHING
   ========================================================= */
$activeColleges   = $pdo->query("SELECT college_id, name FROM colleges WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$activeCategories = $pdo->query("SELECT category_id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$allColleges   = $pdo->query("SELECT college_id, name, status FROM colleges ORDER BY name ASC")->fetchAll();
$allCategories = $pdo->query("SELECT category_id, name, status FROM categories ORDER BY name ASC")->fetchAll();

$collegeLookup  = array_column($allColleges, 'name', 'college_id');
$categoryLookup = array_column($allCategories, 'name', 'category_id');

$events = $pdo->query('SELECT * FROM events ORDER BY event_date DESC')->fetchAll();
$total     = count($events);
$published = count(array_filter($events, fn($e) => $e['status'] === 'published'));
$drafts    = count(array_filter($events, fn($e) => $e['status'] === 'draft'));

$flash = $_SESSION['flash'] ?? null;
$reopenModal = $_SESSION['reopen_modal'] ?? null;
unset($_SESSION['flash'], $_SESSION['reopen_modal']);

$todayDate = date('Y-m-d');
$admin_email = $_SESSION['admin_email'] ?? 'admin@evenza.com';
$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Events | Evenza Admin</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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
        .icon-warning { background: #fff8e6; color: #d97706; }
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

        .event-name-title { font-weight: 700; color: var(--color-text-dark); font-size: 14px; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: capitalize;
        }
        .badge-success { background: #e8f7ef; color: #198754; }
        .badge-warning { background: #fff8e6; color: #d97706; }
        .badge-info    { background: #e0f2fe; color: #0284c7; }
        .badge-danger  { background: #fdecec; color: #dc3545; }

        .type-badge-solo { background-color: #dbeafe; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 9999px; font-size: 11px; }
        .type-badge-team { background-color: #f3e8ff; color: #7e22ce; font-weight: 700; padding: 4px 10px; border-radius: 9999px; font-size: 11px; }

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

        .action-option-btn {
            width: 100%;
            text-align: left;
            padding: 10px 16px;
            border-radius: 12px;
            border: 0;
            background: #f8f6f0;
            font-weight: 600;
            color: #374151;
            transition: 0.2s ease;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            font-size: 13px;
        }
        .action-option-btn:hover { background: #eef2ff; color: #4f46e5; }
        .action-option-btn.delete-option:hover { background: #fdecec; color: #dc3545; }

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
                    <h1 class="berun-greeting-h1">All Events</h1>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li>Event Management</li>
                        <li>All Events</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="berun-search-wrapper">
                    <i class="bi bi-search berun-search-icon"></i>
                    <input type="text" id="searchEvent" class="berun-search-input" placeholder="Search title, college, venue..." />
                </div>

                <button type="button" class="berun-btn-dark" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="bi bi-plus-lg"></i> Add Event
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
                    <!-- Total Events Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Events</span>
                                    <h2 id="totalCount" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 26px;"><?= $total ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-primary">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8edff; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #4f46e5; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Published Events Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Published Events</span>
                                    <h2 id="publishedCount" class="mb-0 fw-extrabold mt-1" style="color:#198754; font-size: 26px;"><?= $published ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8f7ef; border-radius: 9999px;">
                                <div id="publishedBar" class="progress-bar" style="width: <?= $total > 0 ? ($published > 0 ? max(5, round(($published / $total) * 100)) : 0) : 0 ?>%; background-color: #198754; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Drafts Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Drafts</span>
                                    <h2 id="draftCount" class="mb-0 fw-extrabold mt-1" style="color:#d97706; font-size: 26px;"><?= $drafts ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-warning">
                                    <i class="bi bi-file-earmark"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #fff8e6; border-radius: 9999px;">
                                <div id="draftBar" class="progress-bar" style="width: <?= $total > 0 ? ($drafts > 0 ? max(5, round(($drafts / $total) * 100)) : 0) : 0 ?>%; background-color: #d97706; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Event List Table Card -->
                <div class="berun-card-panel p-0 overflow-hidden">
                    <div class="p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-color:#f4f2eb !important;">
                        <div>
                            <h3 class="berun-panel-title">Event List</h3>
                            <p class="berun-panel-sub">View and manage all active system events</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto">
                            <select class="form-select text-xs font-semibold rounded-pill px-3 py-2 border shadow-sm" id="statusFilter" style="width: 140px; min-width: 140px; background-color: #ffffff; cursor: pointer;">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>

                            <select class="form-select text-xs font-semibold rounded-pill px-3 py-2 border shadow-sm" id="typeFilter" style="width: 130px; min-width: 130px; background-color: #ffffff; cursor: pointer;">
                                <option value="">All Types</option>
                                <option value="solo">Solo</option>
                                <option value="team">Team</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="eventTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Event</th>
                                    <th>College</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Fee</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                    <th>Change Status</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total === 0): ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">
                                            No events found. Click "Add Event" to create one.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $index => $e): ?>
                                        <?php 
                                        $statusClasses = [
                                            'published' => 'badge-success',
                                            'draft'     => 'badge-warning',
                                            'completed' => 'badge-info',
                                            'cancelled' => 'badge-danger'
                                        ];
                                        $badgeClass = $statusClasses[$e['status']] ?? 'badge-warning';
                                        ?>
                                        <tr data-type="<?= htmlspecialchars($e['event_type']) ?>">
                                            <td class="ps-4 font-semibold text-muted"><?= $index + 1 ?></td>
                                            <td>
                                                <div class="event-name-title"><?= htmlspecialchars($e['title']) ?></div>
                                            </td>
                                            <td class="text-dark font-medium"><?= htmlspecialchars($collegeLookup[$e['college_id']] ?? 'Unknown') ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($categoryLookup[$e['category_id']] ?? 'None') ?></td>
                                            <td>
                                                <span class="<?= $e['event_type'] === 'solo' ? 'type-badge-solo' : 'type-badge-team' ?>">
                                                    <?= htmlspecialchars($e['event_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">₹<?= number_format($e['registration_fee'], 2) ?></div>
                                                <small class="text-muted d-block" style="font-size: 11px; font-weight: 500;">
                                                    <?= $e['fee_type'] === 'per_person' ? 'Per Person' : 'Per Team' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($e['event_date'])) ?></div>
                                                <?php if(!empty($e['start_time'])): ?>
                                                    <small class="text-muted" style="font-size: 11px;"><?= date('h:i A', strtotime($e['start_time'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-secondary"><?= htmlspecialchars($e['venue'] ?: 'N/A') ?></td>
                                            <td>
                                                <span class="status-badge <?= $badgeClass ?>">
                                                    <?= htmlspecialchars($e['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm rounded-pill px-3 py-1 font-bold text-dark border shadow-sm status-dropdown" data-id="<?= (int)$e['event_id'] ?>" style="min-width: 100px; cursor: pointer; font-size: 12px;">
                                                    <option value="draft" <?= $e['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                    <option value="published" <?= $e['status'] === 'published' ? 'selected' : '' ?>>Publish</option>
                                                    <option value="completed" <?= $e['status'] === 'completed' ? 'selected' : '' ?>>Complete</option>
                                                    <option value="cancelled" <?= $e['status'] === 'cancelled' ? 'selected' : '' ?>>Cancel</option>
                                                </select>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#actionMenuModal<?= (int)$e['event_id'] ?>" title="Actions">
                                                    <i class="bi bi-three-dots-vertical"></i>
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
                                    <span class="ms-1 text-dark font-medium" id="showingCountText">Showing 0 of 0 events</span>
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

            </div>

        </div>

    </div>

    <!-- MODALS PER ROW -->
    <?php foreach ($events as $e): ?>
        
        <!-- POPUP ACTION MENU -->
        <div class="modal fade" id="actionMenuModal<?= (int)$e['event_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title font-semibold text-muted">Event Actions</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3">
                        <button type="button" class="action-option-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#viewEventModal<?= (int)$e['event_id'] ?>">
                            <i class="bi bi-eye text-info me-3 fs-5"></i> View Details
                        </button>
                        
                        <button type="button" class="action-option-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editEventModal<?= (int)$e['event_id'] ?>">
                            <i class="bi bi-pencil text-primary me-3 fs-5"></i> Edit Event
                        </button>
                        
                        <button type="button" class="action-option-btn delete-option text-danger" data-bs-dismiss="modal" onclick="deleteEvent(<?= (int)$e['event_id'] ?>)">
                            <i class="bi bi-trash text-danger me-3 fs-5"></i> Delete Event
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW DETAILS MODAL -->
        <div class="modal fade" id="viewEventModal<?= (int)$e['event_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="bi bi-info-circle text-primary me-2"></i>
                            <?= htmlspecialchars($e['title']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold d-block mb-1">COLLEGE</label>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($collegeLookup[$e['college_id']] ?? 'Unknown') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold d-block mb-1">CATEGORY</label>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($categoryLookup[$e['category_id']] ?? 'None') ?></div>
                            </div>

                            <div class="col-12"><hr class="my-1 text-muted opacity-25"></div>

                            <div class="col-md-4">
                                <label class="text-muted small fw-semibold d-block mb-1">EVENT TYPE</label>
                                <div>
                                    <span class="<?= $e['event_type'] === 'solo' ? 'type-badge-solo' : 'type-badge-team' ?>">
                                        <?= htmlspecialchars($e['event_type']) ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($e['event_type'] === 'team'): ?>
                                <div class="col-md-4">
                                    <label class="text-muted small fw-semibold d-block mb-1">MIN TEAM SIZE</label>
                                    <div class="fw-semibold text-dark"><?= (int)$e['min_team_size'] ?> Person(s)</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="text-muted small fw-semibold d-block mb-1">MAX TEAM SIZE</label>
                                    <div class="fw-semibold text-dark"><?= (int)$e['max_team_size'] ?> Person(s)</div>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-4">
                                <label class="text-muted small fw-semibold d-block mb-1">REGISTRATION FEE</label>
                                <div class="fw-bold text-dark">₹<?= number_format($e['registration_fee'], 2) ?> <small class="text-muted font-normal">(<?= $e['fee_type'] === 'per_person' ? 'Per Person' : 'Per Team' ?>)</small></div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small fw-semibold d-block mb-1">STATUS</label>
                                <div>
                                    <span class="status-badge <?= $statusClasses[$e['status']] ?? 'badge-warning' ?>">
                                        <?= htmlspecialchars($e['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="col-12"><hr class="my-1 text-muted opacity-25"></div>

                            <div class="col-md-4">
                                <label class="text-muted small fw-semibold d-block mb-1">EVENT DATE</label>
                                <div class="fw-semibold text-dark"><i class="bi bi-calendar3 me-1 text-primary"></i> <?= date('d M Y', strtotime($e['event_date'])) ?></div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small fw-semibold d-block mb-1">TIMINGS</label>
                                <div class="fw-semibold text-dark">
                                    <i class="bi bi-clock me-1 text-primary"></i> 
                                    <?= !empty($e['start_time']) ? date('h:i A', strtotime($e['start_time'])) : 'N/A' ?> - 
                                    <?= !empty($e['end_time']) ? date('h:i A', strtotime($e['end_time'])) : 'N/A' ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small fw-semibold d-block mb-1">REGISTRATION DEADLINE</label>
                                <div class="fw-semibold text-danger">
                                    <i class="bi bi-hourglass-split me-1"></i>
                                    <?= !empty($e['registration_deadline']) ? date('d M Y, h:i A', strtotime($e['registration_deadline'])) : 'N/A' ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold d-block mb-1">VENUE</label>
                                <div class="fw-semibold text-dark"><i class="bi bi-geo-alt me-1 text-primary"></i> <?= htmlspecialchars($e['venue'] ?: 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold d-block mb-1">DRESS CODE</label>
                                <div class="fw-semibold text-dark"><i class="bi bi-person-workspace me-1 text-primary"></i> <?= htmlspecialchars($e['dress_code'] ?: 'N/A') ?></div>
                            </div>

                            <div class="col-12"><hr class="my-1 text-muted opacity-25"></div>

                            <div class="col-12">
                                <label class="text-muted small fw-semibold d-block mb-1">DESCRIPTION</label>
                                <div class="p-3 rounded-3 text-secondary" style="background:#f8f6f0; white-space: pre-line; max-height: 150px; overflow-y: auto;">
                                    <?= htmlspecialchars($e['description']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- EDIT EVENT MODAL -->
        <div class="modal fade" id="editEventModal<?= (int)$e['event_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" class="needs-validation event-form" novalidate>
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?= (int)$e['event_id'] ?>">

                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Event — <?= htmlspecialchars($e['title']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">College *</label>
                                    <select class="form-select" name="college_id" required>
                                        <?php foreach ($allColleges as $c): ?>
                                            <option value="<?= (int)$c['college_id'] ?>" <?= $c['college_id'] == $e['college_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a college.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Category *</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Choose Category</option>
                                        <?php foreach ($allCategories as $cat): ?>
                                            <option value="<?= (int)$cat['category_id'] ?>" <?= $cat['category_id'] == $e['category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a category.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Title *</label>
                                    <input type="text" class="form-control" name="title" value="<?= htmlspecialchars((string)($e['title'] ?? '')) ?>" required minlength="2" maxlength="150">
                                    <div class="invalid-feedback">Enter a valid title (2-150 characters).</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Type *</label>
                                    <select class="form-select edit-type-select" name="event_type" required>
                                        <option value="solo" <?= $e['event_type'] === 'solo' ? 'selected' : '' ?>>Solo</option>
                                        <option value="team" <?= $e['event_type'] === 'team' ? 'selected' : '' ?>>Team</option>
                                    </select>
                                </div>

                                <div class="col-md-3 edit-team-fields <?= $e['event_type'] === 'solo' ? 'd-none' : '' ?>">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Min Team Size</label>
                                    <input type="number" class="form-control" name="min_team_size" value="<?= (int)$e['min_team_size'] ?>" min="1" max="100">
                                    <div class="invalid-feedback">Min team size must be at least 1.</div>
                                </div>

                                <div class="col-md-3 edit-team-fields <?= $e['event_type'] === 'solo' ? 'd-none' : '' ?>">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Max Team Size</label>
                                    <input type="number" class="form-control" name="max_team_size" value="<?= (int)$e['max_team_size'] ?>" min="1" max="100">
                                    <div class="invalid-feedback">Max team size cannot be less than min.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Fee Type *</label>
                                    <select class="form-select" name="fee_type" required>
                                        <option value="per_person" <?= $e['fee_type'] === 'per_person' ? 'selected' : '' ?>>Per Person</option>
                                        <option value="per_team" <?= $e['fee_type'] === 'per_team' ? 'selected' : '' ?>>Per Team</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Registration Fee *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" name="registration_fee" step="0.01" min="0" max="1000000" value="<?= htmlspecialchars($e['registration_fee']) ?>" required>
                                    </div>
                                    <div class="invalid-feedback">Enter a fee between 0 and 10,00,000.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Status *</label>
                                    <select class="form-select" name="status" required>
                                        <option value="draft" <?= $e['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                        <option value="published" <?= $e['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                        <option value="completed" <?= $e['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $e['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Date *</label>
                                    <input type="date" class="form-control event-date-input" name="event_date" value="<?= htmlspecialchars($e['event_date']) ?>" required>
                                    <div class="invalid-feedback">Please select a valid date.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Start Time *</label>
                                    <input type="time" class="form-control start-time-input" name="start_time" value="<?= htmlspecialchars($e['start_time']) ?>" required>
                                    <div class="invalid-feedback">Start time is required.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">End Time *</label>
                                    <input type="time" class="form-control end-time-input" name="end_time" value="<?= htmlspecialchars($e['end_time']) ?>" required>
                                    <div class="invalid-feedback">End time must be after start time.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Registration Deadline *</label>
                                    <input type="datetime-local" class="form-control deadline-input" name="registration_deadline" value="<?= !empty($e['registration_deadline']) ? date('Y-m-d\TH:i', strtotime($e['registration_deadline'])) : '' ?>" required>
                                    <div class="invalid-feedback">Deadline must be on or before the event date.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Venue *</label>
                                    <input type="text" class="form-control" name="venue" value="<?= htmlspecialchars((string)($e['venue'] ?? '')) ?>" required minlength="3" maxlength="200">
                                    <div class="invalid-feedback">Venue is required (min 3 characters).</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Dress Code *</label>
                                    <input type="text" class="form-control" name="dress_code" value="<?= htmlspecialchars((string)($e['dress_code'] ?? '')) ?>" required minlength="2" maxlength="255">
                                    <div class="invalid-feedback">Dress code is required.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Description *</label>
                                    <textarea class="form-control" name="description" required minlength="10" maxlength="2000" style="height: 90px;"><?= htmlspecialchars((string)($e['description'] ?? '')) ?></textarea>
                                    <div class="invalid-feedback">Description is required (min 10 characters).</div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">Update Event</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- MODAL: ADD EVENT -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" class="needs-validation event-form" novalidate>
                    <input type="hidden" name="form_action" value="create">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i> Add New Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">College *</label>
                                <div class="position-relative">
                                    <select class="form-select" name="college_id" required data-async-check="college" aria-describedby="collegeAsyncFeedbackAdd">
                                        <option value="">Choose College</option>
                                        <?php foreach ($activeColleges as $c): ?>
                                            <option value="<?= (int)$c['college_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="async-spinner spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
                                </div>
                                <div class="invalid-feedback">Please select an active college.</div>
                                <div class="async-feedback small mt-1" id="collegeAsyncFeedbackAdd" role="alert" aria-live="polite"></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Category *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Choose Category</option>
                                    <?php foreach ($activeCategories as $cat): ?>
                                        <option value="<?= (int)$cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an active category.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Title *</label>
                                <input type="text" class="form-control" name="title" required minlength="2" maxlength="150" placeholder="Event Name">
                                <div class="invalid-feedback">Enter a valid title (2-150 characters).</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Type *</label>
                                <select class="form-select type-select" name="event_type" required>
                                    <option value="solo" selected>Solo</option>
                                    <option value="team">Team</option>
                                </select>
                            </div>

                            <div class="col-md-3 team-fields d-none">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Min Team Size</label>
                                <input type="number" class="form-control" name="min_team_size" value="1" min="1" max="100">
                                <div class="invalid-feedback">Min team size must be at least 1.</div>
                            </div>

                            <div class="col-md-3 team-fields d-none">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Max Team Size</label>
                                <input type="number" class="form-control" name="max_team_size" value="1" min="1" max="100">
                                <div class="invalid-feedback">Max team size cannot be less than min.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Fee Type *</label>
                                <select class="form-select" name="fee_type" required>
                                    <option value="per_person" selected>Per Person</option>
                                    <option value="per_team">Per Team</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Registration Fee *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" name="registration_fee" step="0.01" min="0" max="1000000" value="0.00" required>
                                </div>
                                <div class="invalid-feedback">Enter a fee between 0 and 10,00,000.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="draft" selected>Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Date *</label>
                                <input type="date" class="form-control event-date-input" name="event_date" required min="<?= htmlspecialchars($todayDate) ?>">
                                <div class="invalid-feedback">Please select a valid date.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Start Time *</label>
                                <input type="time" class="form-control start-time-input" name="start_time" required>
                                <div class="invalid-feedback">Start time is required.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">End Time *</label>
                                <input type="time" class="form-control end-time-input" name="end_time" required>
                                <div class="invalid-feedback">End time must be after start time.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Registration Deadline *</label>
                                <input type="datetime-local" class="form-control deadline-input" name="registration_deadline" required>
                                <div class="invalid-feedback">Deadline must be on or before the event date.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Venue *</label>
                                <input type="text" class="form-control" name="venue" required minlength="3" maxlength="200" placeholder="Room, Auditorium, Ground...">
                                <div class="invalid-feedback">Venue is required (min 3 characters).</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Dress Code *</label>
                                <input type="text" class="form-control" name="dress_code" required minlength="2" maxlength="255" placeholder="e.g., Casual, Formals, Traditional">
                                <div class="invalid-feedback">Dress code is required.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Description *</label>
                                <textarea class="form-control" name="description" required minlength="10" maxlength="2000" style="height: 90px;" placeholder="Write structural description or event details..."></textarea>
                                <div class="invalid-feedback">Description is required (min 10 characters).</div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Form Validation Engine -->
    <script>
        function isFieldEmpty(field) {
            return field.value === null || field.value.trim() === '';
        }

        function findFeedbackEl(field) {
            const group = field.closest('[class*="col-"]');
            return group ? group.querySelector('.invalid-feedback') : null;
        }

        function validateField(field) {
            if (!field || field.type === 'hidden' || field.type === 'button' || field.type === 'submit') return true;

            const empty = isFieldEmpty(field);
            const feedbackEl = findFeedbackEl(field);

            if (!field.required && empty) {
                field.classList.remove('is-valid', 'is-invalid');
                if (feedbackEl) feedbackEl.style.display = 'none';
                return true;
            }

            const valid = field.checkValidity();
            field.classList.toggle('is-valid', valid);
            field.classList.toggle('is-invalid', !valid);
            if (feedbackEl) feedbackEl.style.display = valid ? 'none' : 'block';
            return valid;
        }

        function validateForm(form) {
            const fields = form.querySelectorAll('input, select, textarea');
            let formValid = true;
            fields.forEach(field => {
                if (field.type === 'hidden') return;
                if (!validateField(field)) formValid = false;
            });
            return formValid;
        }

        document.querySelectorAll('.event-form').forEach(form => {
            const fields = form.querySelectorAll('input, select, textarea');
            fields.forEach(field => {
                if (field.type === 'hidden') return;
                ['input', 'change', 'blur'].forEach(evt => {
                    field.addEventListener(evt, () => validateField(field));
                });
            });

            form.addEventListener('submit', function(e) {
                if (!validateForm(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) firstInvalid.focus({ preventScroll: false });
                }
            });
        });

        // Team size fields requirement toggling
        function syncTeamFieldRequirement(typeSelect, fieldsSelector) {
            const isTeam = typeSelect.value === 'team';
            const container = typeSelect.closest('.row');
            const fields = container.querySelectorAll(fieldsSelector);
            fields.forEach(field => {
                field.classList.toggle('d-none', !isTeam);
                const input = field.querySelector('input');
                if (input) {
                    input.required = isTeam;
                    if (!isTeam) {
                        input.setCustomValidity('');
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        validateField(input);
                    }
                }
            });
        }

        const typeSelect = document.querySelector('.type-select');
        if (typeSelect) {
            syncTeamFieldRequirement(typeSelect, '.team-fields');
            typeSelect.addEventListener('change', function() {
                syncTeamFieldRequirement(this, '.team-fields');
            });
        }

        document.querySelectorAll('.edit-type-select').forEach(sel => {
            syncTeamFieldRequirement(sel, '.edit-team-fields');
            sel.addEventListener('change', function() {
                syncTeamFieldRequirement(this, '.edit-team-fields');
            });
        });

        // Cross-field validations
        document.querySelectorAll('.event-form').forEach(form => {
            const minInput   = form.querySelector('input[name="min_team_size"]');
            const maxInput   = form.querySelector('input[name="max_team_size"]');
            const startInput = form.querySelector('.start-time-input');
            const endInput   = form.querySelector('.end-time-input');
            const dateInput  = form.querySelector('.event-date-input');
            const deadlineInput = form.querySelector('.deadline-input');

            function checkTeamSizes() {
                if (!minInput || !maxInput) return;
                if (minInput.value && maxInput.value && parseInt(maxInput.value, 10) < parseInt(minInput.value, 10)) {
                    maxInput.setCustomValidity('Maximum team size cannot be less than minimum team size.');
                } else {
                    maxInput.setCustomValidity('');
                }
                validateField(minInput);
                validateField(maxInput);
            }

            function checkTimes() {
                if (!startInput || !endInput) return;
                if (startInput.value && endInput.value && endInput.value <= startInput.value) {
                    endInput.setCustomValidity('End time must be after start time.');
                } else {
                    endInput.setCustomValidity('');
                }
                validateField(startInput);
                validateField(endInput);
            }

            function checkDeadline() {
                if (!deadlineInput || !dateInput) return;
                if (deadlineInput.value && dateInput.value) {
                    const deadlineDate = deadlineInput.value.split('T')[0];
                    if (deadlineDate > dateInput.value) {
                        deadlineInput.setCustomValidity('Registration deadline must be on or before the event date.');
                    } else {
                        deadlineInput.setCustomValidity('');
                    }
                } else {
                    deadlineInput.setCustomValidity('');
                }
                validateField(deadlineInput);
                validateField(dateInput);
            }

            [minInput, maxInput].forEach(el => el && el.addEventListener('input', checkTeamSizes));
            [startInput, endInput].forEach(el => el && el.addEventListener('input', checkTimes));
            [deadlineInput, dateInput].forEach(el => el && el.addEventListener('input', checkDeadline));
        });

        <?php if ($reopenModal): ?>
            document.addEventListener("DOMContentLoaded", function() {
                const modalEl = document.getElementById(<?= json_encode($reopenModal) ?>);
                if (modalEl) new bootstrap.Modal(modalEl).show();
            });
        <?php endif; ?>

        // Table Search, Multi-Filter, Limit & Compact Pagination Engine
        const searchInput = document.getElementById("searchEvent");
        const statusFilter = document.getElementById("statusFilter");
        const typeFilter = document.getElementById("typeFilter");
        const limitSelect = document.getElementById("limitSelect");
        const rows = document.querySelectorAll("#eventTable tbody tr");
        const showingCountText = document.getElementById("showingCountText");
        const paginationContainer = document.getElementById("pagination");

        let currentPage = 1;

        function filterEvents() {
            const searchVal = searchInput.value.toLowerCase().trim();
            const statusVal = statusFilter.value.toLowerCase().trim();
            const typeVal = typeFilter.value.toLowerCase().trim();
            const limitValue = limitSelect.value;

            const matchedRows = [];
            rows.forEach(row => {
                const badge = row.querySelector(".status-badge");
                if (!badge) return;

                const fullText = row.innerText.toLowerCase();
                const currentStatus = badge.innerText.toLowerCase().trim();
                const currentType = row.dataset.type ? row.dataset.type.toLowerCase().trim() : '';

                const matchesSearch = fullText.includes(searchVal);
                const matchesStatus = statusVal === "" || currentStatus === statusVal;
                const matchesType = typeVal === "" || currentType === typeVal;

                if (matchesSearch && matchesStatus && matchesType) {
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
            showingCountText.textContent = `Showing ${visibleCount} of ${totalMatched} events`;

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            paginationContainer.innerHTML = "";
            if (totalPages <= 1) return;

            if (currentPage > 1) {
                const prevLi = document.createElement("li");
                prevLi.className = "page-item";
                prevLi.innerHTML = `<a class="page-link rounded-pill px-3 py-1 text-xs font-bold text-dark border bg-white shadow-sm" style="cursor:pointer;" aria-label="Previous"><i class="bi bi-chevron-left me-1"></i> Prev</a>`;
                prevLi.addEventListener("click", () => { currentPage--; filterEvents(); });
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
                nextLi.addEventListener("click", () => { currentPage++; filterEvents(); });
                paginationContainer.appendChild(nextLi);
            }
        }

        searchInput.addEventListener("keyup", () => { currentPage = 1; filterEvents(); });
        statusFilter.addEventListener("change", () => { currentPage = 1; filterEvents(); });
        typeFilter.addEventListener("change", () => { currentPage = 1; filterEvents(); });
        limitSelect.addEventListener("change", () => { currentPage = 1; filterEvents(); });

        document.addEventListener("DOMContentLoaded", filterEvents);

        // Live AJAX Status Changes & Metrics Updates
        const totalCountBox = document.getElementById("totalCount");
        const publishedCountBox = document.getElementById("publishedCount");
        const draftCountBox = document.getElementById("draftCount");
        const publishedBarEl = document.getElementById("publishedBar");
        const draftBarEl = document.getElementById("draftBar");

        function adjustCounts(oldStatus, newStatus) {
            let totalVal = parseInt(totalCountBox.textContent, 10) || 1;
            let pubVal = parseInt(publishedCountBox.textContent, 10) || 0;
            let drfVal = parseInt(draftCountBox.textContent, 10) || 0;

            if (oldStatus === 'published' && pubVal > 0) pubVal -= 1;
            if (oldStatus === 'draft' && drfVal > 0) drfVal -= 1;

            if (newStatus === 'published') pubVal += 1;
            if (newStatus === 'draft') drfVal += 1;

            publishedCountBox.textContent = pubVal;
            draftCountBox.textContent = drfVal;

            if (publishedBarEl) {
                let pubPct = Math.round((pubVal / totalVal) * 100);
                publishedBarEl.style.width = (pubVal > 0 ? Math.max(5, pubPct) : 0) + "%";
            }
            if (draftBarEl) {
                let drfPct = Math.round((drfVal / totalVal) * 100);
                draftBarEl.style.width = (drfVal > 0 ? Math.max(5, drfPct) : 0) + "%";
            }
        }

        function updateBadgeColor(row, status) {
            const badge = row.querySelector(".status-badge");
            if (!badge) return;
            badge.textContent = status;
            badge.className = "status-badge";
            
            const classes = {
                'published': "badge-success",
                'draft': "badge-warning",
                'completed': "badge-info",
                'cancelled': "badge-danger"
            };

            const applyClass = classes[status] || "badge-warning";
            badge.classList.add(applyClass);
        }

        document.querySelectorAll(".status-dropdown").forEach(dropdown => {
            let previousValue = dropdown.value;

            dropdown.addEventListener("change", function() {
                const eventId = this.dataset.id;
                const newValue = this.value;
                const row = this.closest("tr");

                this.disabled = true;

                fetch("all_events.php?action=toggle_status", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: eventId, status: newValue })
                })
                .then(res => {
                    if (!res.ok) throw new Error("Network issue.");
                    return res.json();
                })
                .then(data => {
                    if (data && data.success) {
                        updateBadgeColor(row, newValue);
                        adjustCounts(previousValue, newValue);
                        previousValue = newValue;
                        filterEvents();
                    } else {
                        throw new Error("Rejected by server.");
                    }
                })
                .catch(() => {
                    this.value = previousValue;
                    alert("Could not update status. Please try again.");
                })
                .finally(() => {
                    this.disabled = false;
                });
            });
        });

        function deleteEvent(id) {
            if (confirm("Are you sure you want to delete this event permanently?")) {
                window.location.href = "all_events.php?action=delete&id=" + id;
            }
        }

        // Async college existence check
        (function() {
            const DEBOUNCE_MS = 350;
            const timers = new WeakMap();

            function debounce(el, fn) {
                clearTimeout(timers.get(el));
                timers.set(el, setTimeout(fn, DEBOUNCE_MS));
            }

            function setState(select, feedbackEl, spinner, state, message) {
                spinner.classList.toggle("d-none", state !== "checking");

                if (state === "valid") {
                    select.setCustomValidity("");
                    feedbackEl.textContent = message || "College confirmed.";
                    feedbackEl.classList.remove("text-danger");
                    feedbackEl.classList.add(message ? "text-warning" : "text-success");
                } else if (state === "invalid") {
                    select.setCustomValidity(message || "Invalid college.");
                    feedbackEl.textContent = message || "Invalid college.";
                    feedbackEl.classList.remove("text-success", "text-warning");
                    feedbackEl.classList.add("text-danger");
                } else if (state === "checking") {
                    select.classList.remove('is-valid', 'is-invalid');
                    feedbackEl.textContent = "Checking college…";
                    feedbackEl.classList.remove("text-success", "text-danger", "text-warning");
                    return;
                } else {
                    select.setCustomValidity("");
                    feedbackEl.textContent = "";
                    feedbackEl.classList.remove("text-success", "text-danger", "text-warning");
                }

                if (typeof validateField === 'function') validateField(select);
            }

            async function runCheck(select) {
                const feedbackId = select.getAttribute("aria-describedby");
                const feedbackEl = feedbackId ? document.getElementById(feedbackId) : null;
                const spinner = select.parentElement.querySelector(".async-spinner");
                if (!feedbackEl || !spinner) return;

                const value = select.value;
                if (!value) {
                    setState(select, feedbackEl, spinner, "idle");
                    return;
                }

                setState(select, feedbackEl, spinner, "checking");

                try {
                    const res = await fetch("all_events.php?action=check_college", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ college_id: value })
                    });

                    if (!res.ok) throw new Error("Request failed");
                    const data = await res.json();

                    setState(select, feedbackEl, spinner, data.valid ? "valid" : "invalid", data.message);
                } catch {
                    setState(select, feedbackEl, spinner, "idle");
                }
            }

            document.addEventListener("change", function(e) {
                if (!e.target.matches('[data-async-check="college"]')) return;
                debounce(e.target, () => runCheck(e.target));
            });
        })();
    </script>
</body>
</html>