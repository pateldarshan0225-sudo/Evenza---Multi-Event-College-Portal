<?php
/**
 * allteams.php
 * Single-file Team Management: DB Connection, CRUD with Multi-Member Creation,
 * member management modal, leader update fix, event filtering, search, limit & compact pagination, and matching UI.
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
function validate_team(PDO $pdo, array $data, ?int $excludeId = null): array
{
    $errors = [];

    $teamName  = trim($data['team_name'] ?? '');
    $eventId   = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT);
    $leaderId  = filter_var($data['leader_id'] ?? null, FILTER_VALIDATE_INT);
    $teamCode  = trim($data['team_code'] ?? '');
    $memberIds = array_filter(array_map('intval', $data['member_ids'] ?? []));

    if ($teamName === '' || mb_strlen($teamName) < 2) {
        $errors[] = 'Team name must be at least 2 characters.';
    } elseif (mb_strlen($teamName) > 100) {
        $errors[] = 'Team name is too long (max 100 chars).';
    }

    if (!$eventId) {
        $errors[] = 'Select a valid event.';
    } else {
        $stmt = $pdo->prepare('SELECT event_id, event_type, min_team_size, max_team_size FROM events WHERE event_id = :id');
        $stmt->execute(['id' => $eventId]);
        $evt = $stmt->fetch();

        if (!$evt) {
            $errors[] = 'Selected event does not exist.';
        } elseif ($evt['event_type'] !== 'team') {
            $errors[] = 'Selected event is a solo event. Teams can only be created for team events.';
        } else {
            if ($excludeId !== null) {
                $countStmt = $pdo->prepare('SELECT COUNT(*) FROM team_members WHERE team_id = :tid');
                $countStmt->execute(['tid' => $excludeId]);
                $existingMemberCount = (int)$countStmt->fetchColumn();

                $chkLeader = $pdo->prepare('SELECT 1 FROM team_members WHERE team_id = :tid AND student_id = :sid');
                $chkLeader->execute(['tid' => $excludeId, 'sid' => $leaderId]);
                if (!$chkLeader->fetch()) {
                    $existingMemberCount++;
                }

                $totalMembers = $existingMemberCount;
            } else {
                $totalMembers = count(array_unique(array_merge([$leaderId], $memberIds)));
            }

            if ($totalMembers < $evt['min_team_size']) {
                $errors[] = "This event requires a minimum of {$evt['min_team_size']} members (including leader).";
            }
            if ($totalMembers > $evt['max_team_size']) {
                $errors[] = "This event allows a maximum of {$evt['max_team_size']} members.";
            }
        }
    }

    if (!$leaderId) {
        $errors[] = 'Select a valid team leader.';
    } else {
        $stmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id');
        $stmt->execute(['id' => $leaderId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected team leader does not exist.';
        }
    }

    if ($teamCode !== '') {
        $sql = 'SELECT team_id FROM teams WHERE team_code = :team_code';
        $params = ['team_code' => $teamCode];
        if ($excludeId !== null) {
            $sql .= ' AND team_id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'This team code is already in use.';
        }
    }

    return $errors;
}

function generate_team_code(PDO $pdo, string $prefix = 'TM'): string
{
    do {
        $code = strtoupper($prefix . rand(100, 999) . chr(rand(65, 90)));
        $stmt = $pdo->prepare('SELECT team_id FROM teams WHERE team_code = :code');
        $stmt->execute(['code' => $code]);
    } while ($stmt->fetch());
    return $code;
}

/* =========================================================
   MEMBER ACTIONS (ADD / REMOVE MEMBER)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_action'])) {
    $teamId    = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT);
    $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);

    if ($teamId && $studentId) {
        if ($_POST['member_action'] === 'add_member') {
            try {
                $stmt = $pdo->prepare('SELECT e.max_team_size, (SELECT COUNT(*) FROM team_members WHERE team_id = t.team_id) AS current_count FROM teams t JOIN events e ON t.event_id = e.event_id WHERE t.team_id = :tid');
                $stmt->execute(['tid' => $teamId]);
                $limitInfo = $stmt->fetch();

                if ($limitInfo && $limitInfo['current_count'] >= $limitInfo['max_team_size']) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cannot add member. Team limit reached (' . $limitInfo['max_team_size'] . ').'];
                } else {
                    $ins = $pdo->prepare('INSERT IGNORE INTO team_members (team_id, student_id) VALUES (:tid, :sid)');
                    $ins->execute(['tid' => $teamId, 'sid' => $studentId]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Team member added successfully.'];
                }
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Could not add member. Student may already be in the team.'];
            }
        } elseif ($_POST['member_action'] === 'remove_member') {
            $stmt = $pdo->prepare('SELECT leader_id FROM teams WHERE team_id = :tid');
            $stmt->execute(['tid' => $teamId]);
            $teamRow = $stmt->fetch();

            if ($teamRow && (int)$teamRow['leader_id'] === $studentId) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cannot remove team leader. Change the team leader first before removing.'];
            } else {
                $del = $pdo->prepare('DELETE FROM team_members WHERE team_id = :tid AND student_id = :sid');
                $del->execute(['tid' => $teamId, 'sid' => $studentId]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Team member removed successfully.'];
            }
        }
        $_SESSION['reopen_modal'] = 'membersModal' . $teamId;
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid team or student selected.'];
    }

    header('Location: allteams.php');
    exit;
}

/* =========================================================
   DELETE TEAM
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $pdo->prepare('SELECT team_id FROM teams WHERE team_id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetch()) {
            $pdo->prepare('DELETE FROM teams WHERE team_id = :id')->execute(['id' => $id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Team deleted successfully.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Team not found.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid team ID.'];
    }

    header('Location: allteams.php');
    exit;
}

/* =========================================================
   CREATE / UPDATE TEAM
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {

    if ($_POST['form_action'] === 'create') {
        $errors = validate_team($pdo, $_POST);

        if (empty($errors)) {
            try {
                $teamCode = trim($_POST['team_code'] ?? '');
                if ($teamCode === '') {
                    $teamCode = generate_team_code($pdo);
                }

                $leaderId  = (int)$_POST['leader_id'];
                $memberIds = array_filter(array_map('intval', $_POST['member_ids'] ?? []));
                $allMembers = array_unique(array_merge([$leaderId], $memberIds));

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO teams (event_id, leader_id, team_name, team_code, created_at)
                     VALUES (:event_id, :leader_id, :team_name, :team_code, NOW())'
                );
                $stmt->execute([
                    'event_id'  => (int)$_POST['event_id'],
                    'leader_id' => $leaderId,
                    'team_name' => trim($_POST['team_name']),
                    'team_code' => $teamCode,
                ]);

                $newTeamId = (int)$pdo->lastInsertId();

                $stmtMember = $pdo->prepare('INSERT IGNORE INTO team_members (team_id, student_id) VALUES (:tid, :sid)');
                foreach ($allMembers as $sid) {
                    $stmtMember->execute(['tid' => $newTeamId, 'sid' => $sid]);
                }

                $stmtReg = $pdo->prepare(
                    'INSERT INTO registrations (event_id, student_id, team_id, registration_type, status, registered_at)
                     VALUES (:event_id, :student_id, :team_id, "team", "approved", NOW())'
                );
                $stmtReg->execute([
                    'event_id'   => (int)$_POST['event_id'],
                    'student_id' => $leaderId,
                    'team_id'    => $newTeamId,
                ]);

                $pdo->commit();

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Team created successfully with ' . count($allMembers) . ' members.'];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Could not save team. Database error.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'addTeamModal';
        }

        header('Location: allteams.php');
        exit;
    }

    if ($_POST['form_action'] === 'update') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $existingStmt = $pdo->prepare('SELECT * FROM teams WHERE team_id = :id');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();

        if (!$id || !$existing) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Team not found.'];
            header('Location: allteams.php');
            exit;
        }

        $errors = validate_team($pdo, $_POST, $id);

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $teamCode = trim($_POST['team_code'] ?? '');
                if ($teamCode === '') {
                    $teamCode = $existing['team_code'];
                }

                $newLeaderId = (int)$_POST['leader_id'];

                $stmt = $pdo->prepare(
                    'UPDATE teams
                     SET event_id = :event_id, leader_id = :leader_id, team_name = :team_name, team_code = :team_code
                     WHERE team_id = :id'
                );
                $stmt->execute([
                    'event_id'  => (int)$_POST['event_id'],
                    'leader_id' => $newLeaderId,
                    'team_name' => trim($_POST['team_name']),
                    'team_code' => $teamCode,
                    'id'        => $id,
                ]);

                $stmtMember = $pdo->prepare('INSERT IGNORE INTO team_members (team_id, student_id) VALUES (:tid, :sid)');
                $stmtMember->execute(['tid' => $id, 'sid' => $newLeaderId]);

                $pdo->commit();

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Team updated successfully.'];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Could not update team.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'editTeamModal' . $id;
        }

        header('Location: allteams.php');
        exit;
    }
}

/* =========================================================
   DATA FETCHING
   ========================================================= */
$teamsSql = '
    SELECT 
        t.*,
        e.title AS event_title,
        e.min_team_size,
        e.max_team_size,
        s.name AS leader_name,
        s.email AS leader_email,
        c.name AS college_name,
        (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.team_id) AS total_members
    FROM teams t
    JOIN events e ON t.event_id = e.event_id
    JOIN students s ON t.leader_id = s.student_id
    LEFT JOIN colleges c ON s.college_id = c.college_id
    ORDER BY t.created_at DESC';

$teams = $pdo->query($teamsSql)->fetchAll();

$events   = $pdo->query("SELECT event_id, title, event_type, min_team_size, max_team_size FROM events ORDER BY title ASC")->fetchAll();
$students = $pdo->query("SELECT s.student_id, s.name, s.enrollment_no, c.name as college_name FROM students s LEFT JOIN colleges c ON s.college_id = c.college_id ORDER BY s.name ASC")->fetchAll();

$total = count($teams);

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
    <title>All Teams | Evenza Admin</title>

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

        .team-name-title { font-weight: 700; color: var(--color-text-dark); font-size: 14px; }

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
            text-decoration: none;
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
                <a href="Dashboard.php" class="d-flex align-items-center me-3">
                    <img src="../assets/images/evenza-logo.svg" alt="Evenza Logo" height="38" style="height: 38px; width: auto;" />
                </a>

                <div class="ps-2">
                    <h1 class="berun-greeting-h1">All Teams</h1>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li>Team Management</li>
                        <li>All Teams</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="berun-search-wrapper">
                    <i class="bi bi-search berun-search-icon"></i>
                    <input type="text" id="searchTeam" class="berun-search-input" placeholder="Search team..." />
                </div>

                <button type="button" class="berun-btn-dark" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                    <i class="bi bi-plus-lg"></i> Add Team
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
                    <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                        <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars((string)($flash['message'] ?? '')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards (Be.run Theme Styling) -->
                <div class="row g-4">
                    <!-- Total Teams Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Teams</span>
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

                    <!-- Total Events Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Events</span>
                                    <h2 class="mb-0 fw-extrabold mt-1" style="color:#198754; font-size: 26px;"><?= count($events) ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-success">
                                    <i class="bi bi-trophy-fill"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8f7ef; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #198754; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Available Students Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Available Students</span>
                                    <h2 class="mb-0 fw-extrabold mt-1" style="color:#d97706; font-size: 26px;"><?= count($students) ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-warning">
                                    <i class="bi bi-person-fill-check"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #fff8e6; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #d97706; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Team List Table Card Panel -->
                <div class="berun-card-panel p-0 overflow-hidden">
                    <div class="p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-color:#f4f2eb !important;">
                        <div>
                            <h3 class="berun-panel-title">Team List</h3>
                            <p class="berun-panel-sub">View and manage all teams and members</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto">
                            <select class="form-select text-xs font-semibold rounded-pill px-3 py-2 border shadow-sm" id="eventFilter" style="min-width: 160px; background-color: #ffffff; cursor: pointer;">
                                <option value="">All Events</option>
                                <?php foreach ($events as $ev): ?>
                                    <option value="<?= htmlspecialchars((string)$ev['title']) ?>"><?= htmlspecialchars((string)$ev['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="teamTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Team Details</th>
                                    <th>Team Code</th>
                                    <th>Event</th>
                                    <th>Leader</th>
                                    <th>Members</th>
                                    <th>Created Date</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total === 0): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            No teams yet. Click "Add Team" to create one.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($teams as $i => $t): ?>
                                        <tr>
                                            <td class="ps-4 font-semibold text-muted"><?= $i + 1 ?></td>
                                            <td>
                                                <div class="team-name-title"><?= htmlspecialchars((string)($t['team_name'] ?? '')) ?></div>
                                                <small class="text-muted" style="font-size: 11px;">ID: #<?= (int)$t['team_id'] ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary fw-bold rounded-pill px-3 py-1">
                                                    <?= htmlspecialchars((string)($t['team_code'] ?? '')) ?>
                                                </span>
                                            </td>
                                            <td class="text-dark font-medium"><?= htmlspecialchars((string)($t['event_title'] ?? '')) ?></td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars((string)($t['leader_name'] ?? '')) ?></div>
                                                <small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars((string)($t['leader_email'] ?? '')) ?></small>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 font-semibold" data-bs-toggle="modal" data-bs-target="#membersModal<?= (int)$t['team_id'] ?>">
                                                    <i class="bi bi-people me-1"></i> <?= $t['total_members'] ?> / <?= $t['max_team_size'] ?>
                                                </button>
                                            </td>
                                            <td class="text-secondary"><small class="font-medium"><?= date('d M Y', strtotime($t['created_at'])) ?></small></td>
                                            <td class="pe-4 text-end">
                                                <a href="teamdetails.php?id=<?= (int)$t['team_id'] ?>" class="action-btn me-1" title="View Details">
                                                    <i class="bi bi-eye text-info"></i>
                                                </a>
                                                <button type="button" class="action-btn me-1" title="Edit" data-bs-toggle="modal" data-bs-target="#editTeamModal<?= (int)$t['team_id'] ?>">
                                                    <i class="bi bi-pencil text-primary"></i>
                                                </button>
                                                <button type="button" class="action-btn" title="Delete" onclick="deleteTeam(<?= (int)$t['team_id'] ?>)">
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
                                    <span class="ms-1 text-dark font-medium" id="showingCountText">Showing 0 of 0 teams</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <nav aria-label="Table pagination">
                                    <ul class="pagination pagination-sm mb-0 justify-content-md-end justify-content-center gap-2 align-items-center" id="pagination">
                                        <!-- Dynamic compact pagination controls inject here -->
                                    </ul>
                                </nav>
                            </div>
                        </div>
                </div>

            </div>

        </div>

    </div>

    <!-- ADD TEAM MODAL -->
    <div class="modal fade" id="addTeamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="form_action" value="create">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Add Team</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Team Name *</label>
                                <input type="text" class="form-control" name="team_name" required minlength="2" maxlength="100" placeholder="e.g. Code Warriors">
                                <div class="invalid-feedback">Enter team name.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Team Code <span class="text-muted">(Optional)</span></label>
                                <input type="text" class="form-control" name="team_code" maxlength="30" placeholder="e.g. TEAM-101">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event *</label>
                                <select class="form-select" name="event_id" required>
                                    <option value="">Choose Event</option>
                                    <?php if (empty($events)): ?>
                                        <option value="" disabled>No events found in database</option>
                                    <?php else: ?>
                                        <?php foreach ($events as $ev): ?>
                                            <?php $isTeamEvent = $ev['event_type'] === 'team'; ?>
                                            <option value="<?= (int)$ev['event_id'] ?>" <?= !$isTeamEvent ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars((string)$ev['title']) ?> 
                                                <?= $isTeamEvent 
                                                    ? "(Team Event: {$ev['min_team_size']}-{$ev['max_team_size']} members)" 
                                                    : "(Solo Event - Cannot attach team)" ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Select a team event.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Team Leader *</label>
                                <select class="form-select" name="leader_id" required>
                                    <option value="">Choose Student Leader</option>
                                    <?php foreach ($students as $st): ?>
                                        <option value="<?= (int)$st['student_id'] ?>"><?= htmlspecialchars((string)$st['name']) ?> (<?= htmlspecialchars((string)($st['enrollment_no'] ?? 'No Enr')) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Select a team leader.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">
                                    Additional Team Members <span class="text-muted">(Hold Ctrl/Cmd to select multiple)</span>
                                </label>
                                <select class="form-select" name="member_ids[]" multiple style="height: 120px;">
                                    <?php foreach ($students as $st): ?>
                                        <option value="<?= (int)$st['student_id'] ?>">
                                            <?= htmlspecialchars((string)$st['name']) ?> (<?= htmlspecialchars((string)($st['enrollment_no'] ?? 'No Enr')) ?> - <?= htmlspecialchars((string)($st['college_name'] ?? 'No College')) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted mt-1 d-block">The team leader selected above will automatically be added as a member.</small>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Save Team</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- EDIT & MEMBER MODALS -->
    <?php foreach ($teams as $t): ?>
        <div class="modal fade" id="editTeamModal<?= (int)$t['team_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?= (int)$t['team_id'] ?>">

                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Team Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Team Name *</label>
                                    <input type="text" class="form-control" name="team_name" required minlength="2" value="<?= htmlspecialchars((string)($t['team_name'] ?? '')) ?>">
                                    <div class="invalid-feedback">Enter team name.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Team Code</label>
                                    <input type="text" class="form-control" name="team_code" value="<?= htmlspecialchars((string)($t['team_code'] ?? '')) ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event *</label>
                                    <select class="form-select" name="event_id" required>
                                        <?php foreach ($events as $ev): ?>
                                            <?php if ($ev['event_type'] === 'team' || (int)$ev['event_id'] === (int)$t['event_id']): ?>
                                                <option value="<?= (int)$ev['event_id'] ?>" <?= (int)$ev['event_id'] === (int)$t['event_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string)$ev['title']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Team Leader *</label>
                                    <select class="form-select" name="leader_id" required>
                                        <?php foreach ($students as $st): ?>
                                            <option value="<?= (int)$st['student_id'] ?>" <?= (int)$st['student_id'] === (int)$t['leader_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)$st['name']) ?> (<?= htmlspecialchars((string)($st['enrollment_no'] ?? 'No Enr')) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">Update Team</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <?php
            $mStmt = $pdo->prepare('
                SELECT tm.student_id, s.name, s.email, s.enrollment_no, c.name as college_name 
                FROM team_members tm
                JOIN students s ON tm.student_id = s.student_id
                LEFT JOIN colleges c ON s.college_id = c.college_id
                WHERE tm.team_id = :tid');
            $mStmt->execute(['tid' => $t['team_id']]);
            $currentMembers = $mStmt->fetchAll();
            $currentMemberIds = array_column($currentMembers, 'student_id');
        ?>
        <div class="modal fade" id="membersModal<?= (int)$t['team_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title"><i class="bi bi-people me-2"></i> Manage Members — <?= htmlspecialchars((string)$t['team_name']) ?></h5>
                            <small class="text-muted">Event Limit: <?= $t['min_team_size'] ?> to <?= $t['max_team_size'] ?> members</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" class="row g-2 mb-4 p-3 rounded-4" style="background:#f8f6f0;">
                            <input type="hidden" name="member_action" value="add_member">
                            <input type="hidden" name="team_id" value="<?= (int)$t['team_id'] ?>">
                            <div class="col-md-9">
                                <select class="form-select" name="student_id" required>
                                    <option value="">Select Student to Add...</option>
                                    <?php foreach ($students as $st): ?>
                                        <?php if (!in_array((int)$st['student_id'], $currentMemberIds, true)): ?>
                                            <option value="<?= (int)$st['student_id'] ?>">
                                                <?= htmlspecialchars((string)$st['name']) ?> (<?= htmlspecialchars((string)($st['enrollment_no'] ?? 'No Enr')) ?> - <?= htmlspecialchars((string)($st['college_name'] ?? 'No College')) ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-dark w-100 rounded-pill font-semibold">
                                    <i class="bi bi-plus-lg me-1"></i> Add Member
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Name</th>
                                        <th>Enrollment No</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentMembers as $mIdx => $cm): ?>
                                        <?php $isLeader = (int)$cm['student_id'] === (int)$t['leader_id']; ?>
                                        <tr>
                                            <td><?= $mIdx + 1 ?></td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars((string)$cm['name']) ?></td>
                                            <td><?= htmlspecialchars((string)($cm['enrollment_no'] ?? 'N/A')) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars((string)$cm['email']) ?></td>
                                            <td>
                                                <?php if ($isLeader): ?>
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1"><i class="bi bi-star-fill me-1"></i> Leader</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1">Member</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if (!$isLeader): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove this member from team?');">
                                                        <input type="hidden" name="member_action" value="remove_member">
                                                        <input type="hidden" name="team_id" value="<?= (int)$t['team_id'] ?>">
                                                        <input type="hidden" name="student_id" value="<?= (int)$cm['student_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Remove</button>
                                                    </form>
                                                <?php else: ?>
                                                    <small class="text-muted">Leader</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Real-time Search, Event Filter, Limit & Compact Pagination Engine
        const searchInput = document.getElementById("searchTeam");
        const eventFilter = document.getElementById("eventFilter");
        const limitSelect = document.getElementById("limitSelect");
        const rows = document.querySelectorAll("#teamTable tbody tr");
        const showingCountText = document.getElementById("showingCountText");
        const paginationContainer = document.getElementById("pagination");

        let currentPage = 1;

        function filterTeams() {
            const searchValue = searchInput.value.toLowerCase().trim();
            const eventValue = eventFilter.value.toLowerCase().trim();
            const limitValue = limitSelect.value;

            const matchedRows = [];
            rows.forEach(row => {
                const fullText = row.innerText.toLowerCase();
                const eventCell = row.children[3] ? row.children[3].innerText.toLowerCase().trim() : '';

                const matchesSearch = fullText.includes(searchValue);
                const matchesEvent = eventValue === "" || eventCell === eventValue;

                if (matchesSearch && matchesEvent) {
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
            showingCountText.textContent = `Showing ${visibleCount} of ${totalMatched} teams`;

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            paginationContainer.innerHTML = "";
            if (totalPages <= 1) return;

            if (currentPage > 1) {
                const prevLi = document.createElement("li");
                prevLi.className = "page-item";
                prevLi.innerHTML = `<a class="page-link rounded-pill px-3 py-1 text-xs font-bold text-dark border bg-white shadow-sm" style="cursor:pointer;" aria-label="Previous"><i class="bi bi-chevron-left me-1"></i> Prev</a>`;
                prevLi.addEventListener("click", () => { currentPage--; filterTeams(); });
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
                nextLi.addEventListener("click", () => { currentPage++; filterTeams(); });
                paginationContainer.appendChild(nextLi);
            }
        }

        searchInput.addEventListener("keyup", () => { currentPage = 1; filterTeams(); });
        eventFilter.addEventListener("change", () => { currentPage = 1; filterTeams(); });
        limitSelect.addEventListener("change", () => { currentPage = 1; filterTeams(); });

        document.addEventListener("DOMContentLoaded", filterTeams);

        <?php if ($reopenModal): ?>
            document.addEventListener("DOMContentLoaded", function() {
                const modalEl = document.getElementById(<?= json_encode($reopenModal) ?>);
                if (modalEl) new bootstrap.Modal(modalEl).show();
            });
        <?php endif; ?>

        function deleteTeam(id) {
            if (confirm("Are you sure you want to delete this team permanently?")) {
                window.location.href = "allteams.php?action=delete&id=" + id;
            }
        }
    </script>
</body>
</html>