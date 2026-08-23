<?php
/**
 * event_cat.php
 * Single-file Event Category management: DB connection, validation,
 * create / read / update / delete, status toggle (AJAX), limit & pagination, and UI.
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
function validate_category(PDO $pdo, array $data, ?int $excludeId = null): array
{
    $errors = [];

    $name   = trim($data['name'] ?? '');
    $status = trim($data['status'] ?? '');

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Category name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Category name is too long.';
    } elseif (preg_match('/^[\d\s]+$/', $name)) {
        $errors[] = 'Category name cannot be numbers only. It must contain letters.';
    } elseif (!preg_match('/[A-Za-z]/u', $name)) {
        $errors[] = 'Category name must contain letters.';
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Select a valid status.';
    }

    if ($name !== '') {
        $sql = 'SELECT category_id FROM categories WHERE name = :name';
        $params = ['name' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND category_id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'This category name already exists.';
        }
    }

    return $errors;
}

/* =========================================================
   AJAX: STATUS TOGGLE
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

    $check = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Category not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE categories SET status = :status WHERE category_id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);

    echo json_encode(['success' => true]);
    exit;
}

/* =========================================================
   DELETE
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :id');
        $stmt->execute(['id' => $id]);
        
        if ($stmt->fetch()) {
            try {
                $pdo->prepare('DELETE FROM categories WHERE category_id = :id')->execute(['id' => $id]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category deleted successfully.'];
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cannot delete category as it is currently linked to events.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Category not found.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid category id.'];
    }

    header('Location: event_cat.php');
    exit;
}

/* =========================================================
   CREATE / UPDATE
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {

    if ($_POST['form_action'] === 'create') {
        $errors = validate_category($pdo, $_POST);

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO categories (name, status) VALUES (:name, :status)'
                );
                $stmt->execute([
                    'name'   => trim($_POST['name']),
                    'status' => trim($_POST['status']),
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category added successfully.'];
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Could not save category. Please try again.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'addCategoryModal';
        }

        header('Location: event_cat.php');
        exit;
    }

    if ($_POST['form_action'] === 'update') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $existingStmt = $pdo->prepare('SELECT * FROM categories WHERE category_id = :id');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();

        if (!$id || !$existing) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Category not found.'];
            header('Location: event_cat.php');
            exit;
        }

        $errors = validate_category($pdo, $_POST, $id);

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE categories SET status = :status, name = :name WHERE category_id = :id'
                );
                $stmt->execute([
                    'name'   => trim($_POST['name']),
                    'status' => trim($_POST['status']),
                    'id'     => $id,
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category updated successfully.'];
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Could not update category. Please try again.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $errors)];
            $_SESSION['reopen_modal'] = 'editCategoryModal' . $id;
        }

        header('Location: event_cat.php');
        exit;
    }
}

/* =========================================================
   DATA FOR DISPLAY
   ========================================================= */
$categories = $pdo->query('SELECT * FROM categories ORDER BY category_id DESC')->fetchAll();
$total      = count($categories);
$active     = count(array_filter($categories, fn($c) => $c['status'] === 'active'));
$inactive   = $total - $active;

$flash       = $_SESSION['flash'] ?? null;
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
    <title>Event Categories | Evenza Admin</title>

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

        .category-icon-box {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-size: 20px;
            background: #f3e8ff;
            color: #9333ea;
        }

        .category-name-title { font-weight: 700; color: var(--color-text-dark); font-size: 14px; }

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
                    <h1 class="berun-greeting-h1">Event Categories</h1>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li>Category Management</li>
                        <li>All Categories</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="berun-search-wrapper">
                    <i class="bi bi-search berun-search-icon"></i>
                    <input type="text" id="searchCategory" class="berun-search-input" placeholder="Search category name..." />
                </div>

                <button type="button" class="berun-btn-dark" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-lg"></i> Add Category
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
                    <!-- Total Categories Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Categories</span>
                                    <h2 id="totalCount" class="mb-0 fw-extrabold mt-1" style="color:#1c2024; font-size: 26px;"><?= $total ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-primary">
                                    <i class="bi bi-tags"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8edff; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #4f46e5; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Categories Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Active Categories</span>
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

                    <!-- Inactive Categories Card -->
                    <div class="col-12 col-md-4">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Inactive Categories</span>
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

                <!-- Main Category List Table Card -->
                <div class="berun-card-panel p-0 overflow-hidden">
                    <div class="p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-color:#f4f2eb !important;">
                        <div>
                            <h3 class="berun-panel-title">Category List</h3>
                            <p class="berun-panel-sub">View and manage all registered event categories</p>
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
                        <table class="table table-hover align-middle mb-0" id="categoryTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Status Toggle</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No categories yet. Click "Add Category" to create one.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $i => $c): ?>
                                        <?php $isActive = $c['status'] === 'active'; ?>
                                        <tr>
                                            <td class="ps-4 font-semibold text-muted"><?= $i + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="category-icon-box">
                                                        <i class="bi bi-tag"></i>
                                                    </div>
                                                    <div class="category-name-title"><?= htmlspecialchars($c['name']) ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input status-toggle" type="checkbox" role="switch" data-id="<?= (int) $c['category_id'] ?>" <?= $isActive ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <button type="button" class="action-btn me-1" title="Edit" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?= (int) $c['category_id'] ?>">
                                                    <i class="bi bi-pencil text-primary"></i>
                                                </button>
                                                <button type="button" class="action-btn" title="Delete" onclick="deleteCategory(<?= (int) $c['category_id'] ?>)">
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
                                    <span class="ms-1 text-dark font-medium" id="showingCountText">Showing 0 of 0 categories</span>
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

    <!-- ADD CATEGORY MODAL -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-tags me-2"></i> Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Category Name</label>
                                <input type="text" class="form-control" name="name" required minlength="2" maxlength="100" pattern="^(?!^[0-9\s]+$).+$" placeholder="e.g. Social & Fun Events">
                                <div class="invalid-feedback">Enter a valid category name (letters required, not numbers only).</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="">Choose status</option>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="invalid-feedback">Select a status.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT CATEGORY MODALS -->
    <?php foreach ($categories as $c): ?>
        <div class="modal fade" id="editCategoryModal<?= (int) $c['category_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $c['category_id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Category — <?= htmlspecialchars($c['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Category Name</label>
                                    <input type="text" class="form-control" name="name" required minlength="2" maxlength="100" pattern="^(?!^[0-9\s]+$).+$" value="<?= htmlspecialchars($c['name']) ?>">
                                    <div class="invalid-feedback">Enter a valid category name (letters required, not numbers only).</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $c['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                    <div class="invalid-feedback">Select a status.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">Update Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
        const searchInput = document.getElementById("searchCategory");
        const statusFilter = document.getElementById("statusFilter");
        const limitSelect = document.getElementById("limitSelect");
        const rows = document.querySelectorAll("#categoryTable tbody tr");
        const showingCountText = document.getElementById("showingCountText");
        const paginationContainer = document.getElementById("pagination");

        let currentPage = 1;

        function filterCategories() {
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
            showingCountText.textContent = `Showing ${visibleCount} of ${totalMatched} categories`;

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            paginationContainer.innerHTML = "";
            if (totalPages <= 1) return;

            if (currentPage > 1) {
                const prevLi = document.createElement("li");
                prevLi.className = "page-item";
                prevLi.innerHTML = `<a class="page-link rounded-pill px-3 py-1 text-xs font-bold text-dark border bg-white shadow-sm" style="cursor:pointer;" aria-label="Previous"><i class="bi bi-chevron-left me-1"></i> Prev</a>`;
                prevLi.addEventListener("click", () => { currentPage--; filterCategories(); });
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
                nextLi.addEventListener("click", () => { currentPage++; filterCategories(); });
                paginationContainer.appendChild(nextLi);
            }
        }

        searchInput.addEventListener("keyup", () => { currentPage = 1; filterCategories(); });
        statusFilter.addEventListener("change", () => { currentPage = 1; filterCategories(); });
        limitSelect.addEventListener("change", () => { currentPage = 1; filterCategories(); });

        document.addEventListener("DOMContentLoaded", filterCategories);
    </script>

    <!-- AJAX Status Toggle & Progress Bar Lines -->
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
                const categoryId = this.dataset.id;
                const isActive = this.checked;

                setBadge(row, isActive);
                adjustCounts(isActive);
                filterCategories();
                this.disabled = true;

                fetch("event_cat.php?action=toggle_status", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: categoryId, status: isActive ? "active" : "inactive" })
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
                    filterCategories();
                    alert("Could not update status. Please try again.");
                })
                .finally(() => { toggle.disabled = false; });
            });
        });

        function deleteCategory(id) {
            if (confirm("Are you sure you want to delete this category?")) {
                window.location.href = "event_cat.php?action=delete&id=" + id;
            }
        }
    </script>
</body>
</html>