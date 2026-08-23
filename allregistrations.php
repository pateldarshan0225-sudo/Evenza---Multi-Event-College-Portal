<?php
/**
 * allregistrations.php
 * Admin monitoring dashboard for event registrations and participant rosters.
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
   CANCEL / DISQUALIFY HANDLER
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'cancel') {
    $registration_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($registration_id) {
        try {
            $stmt = $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE registration_id = :id");
            $stmt->execute(['id' => $registration_id]);

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => "Registration REG" . str_pad((string)$registration_id, 4, '0', STR_PAD_LEFT) . " has been cancelled."
            ];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Could not cancel registration.'];
        }
    }

    header('Location: allregistrations.php');
    exit;
}

/* =========================================================
   FETCH REGISTRATIONS WITH FULL JOIN DATA
   ========================================================= */
$query = "
    SELECT 
        r.registration_id,
        r.registration_type,
        r.status,
        r.registered_at,
        e.event_id,
        e.title AS event_title,
        e.event_date,
        e.start_time,
        e.end_time,
        e.venue,
        e.registration_fee,
        e.fee_type,
        -- Student / Leader info
        s.student_id,
        s.name AS student_name,
        s.email AS student_email,
        s.phone AS student_phone,
        -- Team info
        t.team_id,
        t.team_name,
        t.team_code,
        -- College info
        c.name AS college_name,
        -- Payment info
        p.payment_status,
        p.amount
    FROM registrations r
    JOIN events e ON r.event_id = e.event_id
    LEFT JOIN students s ON r.student_id = s.student_id
    LEFT JOIN teams t ON r.team_id = t.team_id
    LEFT JOIN students leader ON t.leader_id = leader.student_id
    LEFT JOIN colleges c ON (s.college_id = c.college_id OR leader.college_id = c.college_id)
    LEFT JOIN payments p ON r.registration_id = p.registration_id
    ORDER BY r.registered_at DESC
";

$registrations = $pdo->query($query)->fetchAll();

// Retrieve Team Members for Team Registrations
$teamMembersMap = [];
$teamIds = array_filter(array_column($registrations, 'team_id'));

if (!empty($teamIds)) {
    $inClause = implode(',', array_map('intval', array_unique($teamIds)));
    $membersQuery = "
        SELECT 
            tm.team_id,
            s.name,
            s.email,
            s.phone,
            s.enrollment_no
        FROM team_members tm
        JOIN students s ON tm.student_id = s.student_id
        WHERE tm.team_id IN ($inClause)
    ";
    $membersList = $pdo->query($membersQuery)->fetchAll();
    foreach ($membersList as $m) {
        $teamMembersMap[$m['team_id']][] = $m;
    }
}

// Quick Metrics
$total      = count($registrations);
$approved   = count(array_filter($registrations, fn($r) => $r['status'] === 'approved'));
$pending    = count(array_filter($registrations, fn($r) => $r['status'] === 'pending'));
$cancelled  = count(array_filter($registrations, fn($r) => $r['status'] === 'cancelled'));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$admin_email = $_SESSION['admin_email'] ?? 'admin@evenza.com';
$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Registrations | Evenza Admin</title>

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

        /* Search Box */
        .berun-search-wrapper {
            position: relative;
            width: 320px;
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

        .reg-code-badge { font-weight: 700; color: #4f46e5; }

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
                    <h1 class="berun-greeting-h1">All Registrations</h1>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li>Registration Management</li>
                        <li>All Registrations</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="berun-search-wrapper">
                    <i class="bi bi-search berun-search-icon"></i>
                    <input type="text" id="searchRegistration" class="berun-search-input" placeholder="Search ID, participant, event, college..." />
                </div>
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
                    <!-- Total Registrations Card -->
                    <div class="col-12 col-md-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Total Registrations</span>
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

                    <!-- Confirmed / Approved Card -->
                    <div class="col-12 col-md-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Confirmed / Approved</span>
                                    <h2 id="approvedCount" class="mb-0 fw-extrabold mt-1" style="color:#198754; font-size: 26px;"><?= $approved ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #e8f7ef; border-radius: 9999px;">
                                <div id="approvedBar" class="progress-bar" style="width: <?= $total > 0 ? ($approved > 0 ? max(5, round(($approved / $total) * 100)) : 0) : 0 ?>%; background-color: #198754; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Payment Card -->
                    <div class="col-12 col-md-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Pending Payment</span>
                                    <h2 id="pendingCount" class="mb-0 fw-extrabold mt-1" style="color:#d97706; font-size: 26px;"><?= $pending ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-warning">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #fff8e6; border-radius: 9999px;">
                                <div id="pendingBar" class="progress-bar" style="width: <?= $total > 0 ? ($pending > 0 ? max(5, round(($pending / $total) * 100)) : 0) : 0 ?>%; background-color: #d97706; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Cancelled Card -->
                    <div class="col-12 col-md-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide" style="font-size:11px; font-weight:700;">Cancelled</span>
                                    <h2 id="cancelledCount" class="mb-0 fw-extrabold mt-1" style="color:#dc3545; font-size: 26px;"><?= $cancelled ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-danger">
                                    <i class="bi bi-x-circle-fill"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; background-color: #fdecec; border-radius: 9999px;">
                                <div id="cancelledBar" class="progress-bar" style="width: <?= $total > 0 ? ($cancelled > 0 ? max(5, round(($cancelled / $total) * 100)) : 0) : 0 ?>%; background-color: #dc3545; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Registration List Table Card Panel -->
                <div class="berun-card-panel p-0 overflow-hidden">
                    <div class="p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-color:#f4f2eb !important;">
                        <div>
                            <h3 class="berun-panel-title">Registration List</h3>
                            <p class="berun-panel-sub">Real-time overview of participant entries</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto">
                            <select class="form-select text-xs font-semibold rounded-pill px-3 py-2 border shadow-sm" id="statusFilter" style="width: 140px; min-width: 140px; background-color: #ffffff; cursor: pointer;">
                                <option value="">All Statuses</option>
                                <option value="approved">Approved</option>
                                <option value="pending">Pending</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="registrationTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Reg ID</th>
                                    <th>Event Name</th>
                                    <th>Type</th>
                                    <th>Participant / Team</th>
                                    <th>College</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total === 0): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No registrations found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registrations as $i => $r): ?>
                                        <?php 
                                            $isTeam = $r['registration_type'] === 'team';
                                            $participantName = $isTeam ? ($r['team_name'] ?? 'N/A') : ($r['student_name'] ?? 'N/A');
                                            $regCode = 'REG' . str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT);
                                            
                                            $badgeClass = 'badge-warning';
                                            if ($r['status'] === 'approved') $badgeClass = 'badge-success';
                                            if ($r['status'] === 'cancelled') $badgeClass = 'badge-danger';
                                        ?>
                                        <tr>
                                            <td class="ps-4 font-semibold text-muted"><?= $i + 1 ?></td>
                                            <td><span class="reg-code-badge"><?= $regCode ?></span></td>
                                            <td><span class="fw-bold text-dark"><?= htmlspecialchars((string)($r['event_title'] ?? '')) ?></span></td>
                                            <td>
                                                <span class="<?= $isTeam ? 'type-badge-team' : 'type-badge-solo' ?>">
                                                    <?= ucfirst((string)($r['registration_type'] ?? '')) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars((string)($participantName ?? '')) ?></div>
                                                <?php if ($isTeam && !empty($r['team_code'])): ?>
                                                    <small class="text-muted" style="font-size: 11px;">Code: <?= htmlspecialchars((string)($r['team_code'] ?? '')) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-secondary"><?= htmlspecialchars((string)($r['college_name'] ?? 'N/A')) ?></td>
                                            <td>
                                                <span class="status-badge <?= $badgeClass ?>">
                                                    <?= ucfirst((string)($r['status'] ?? '')) ?>
                                                </span>
                                            </td>
                                            <td><small class="text-muted font-medium"><?= date('d M Y', strtotime($r['registered_at'])) ?></small></td>
                                            <td class="pe-4 text-end">
                                                <!-- Popup Action Menu -->
                                                <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#actionMenuModal<?= $r['registration_id'] ?>" title="Actions">
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
                                    <span class="ms-1 text-dark font-medium" id="showingCountText">Showing 0 of 0 registrations</span>
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

                    <?php include 'Footer.php'; ?>

                </div>

            </div>

        </div>

    </div>

    <!-- MODALS PER ROW -->
    <?php foreach ($registrations as $r): ?>
        <?php 
            $isTeam = $r['registration_type'] === 'team';
            $regCode = 'REG' . str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT);
            $members = $isTeam && isset($teamMembersMap[$r['team_id']]) ? $teamMembersMap[$r['team_id']] : [];
        ?>

        <!-- 1. POPUP ACTION MENU -->
        <div class="modal fade" id="actionMenuModal<?= $r['registration_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title font-semibold text-muted">Registration Actions</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3">
                        <button type="button" class="action-option-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#viewEventModal<?= $r['registration_id'] ?>">
                            <i class="bi bi-calendar-event text-primary me-3 fs-5"></i> View Event Info
                        </button>

                        <button type="button" class="action-option-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#viewParticipantModal<?= $r['registration_id'] ?>">
                            <i class="bi bi-people text-info me-3 fs-5"></i> View Roster / Details
                        </button>

                        <?php if ($r['status'] !== 'cancelled'): ?>
                            <button type="button" class="action-option-btn delete-option text-danger" data-bs-dismiss="modal" onclick="cancelRegistration(<?= $r['registration_id'] ?>)">
                                <i class="bi bi-x-circle text-danger me-3 fs-5"></i> Cancel Registration
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. VIEW EVENT DETAILS MODAL -->
        <div class="modal fade" id="viewEventModal<?= $r['registration_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark">
                            <i class="bi bi-calendar-event text-primary me-2"></i> Event Information
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3 border-bottom pb-2">
                            <h5 class="mb-1 text-primary fw-bold"><?= htmlspecialchars((string)($r['event_title'] ?? '')) ?></h5>
                            <small class="text-muted"><i class="bi bi-building me-1"></i><?= htmlspecialchars((string)($r['college_name'] ?? 'N/A')) ?></small>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <small class="text-muted d-block font-semibold" style="font-size: 11px;">EVENT DATE</small>
                                <span class="fw-semibold text-dark"><i class="bi bi-calendar3 me-1 text-primary"></i><?= date('d M Y', strtotime($r['event_date'])) ?></span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block font-semibold" style="font-size: 11px;">TIMING</small>
                                <span class="fw-semibold text-dark">
                                    <i class="bi bi-clock me-1 text-primary"></i><?= !empty($r['start_time']) ? date('h:i A', strtotime($r['start_time'])) : 'N/A' ?>
                                </span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block font-semibold" style="font-size: 11px;">VENUE</small>
                                <span class="fw-semibold text-dark"><i class="bi bi-geo-alt me-1 text-primary"></i><?= htmlspecialchars((string)($r['venue'] ?: 'N/A')) ?></span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block font-semibold" style="font-size: 11px;">FEE</small>
                                <span class="fw-bold text-success">₹<?= number_format($r['registration_fee'], 2) ?> (<?= $r['fee_type'] === 'per_person' ? 'Per Person' : 'Per Team' ?>)</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. VIEW PARTICIPANT / TEAM ROSTER MODAL -->
        <div class="modal fade" id="viewParticipantModal<?= $r['registration_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Participant Details - <?= $regCode ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <?php if ($isTeam): ?>
                            <div class="mb-3">
                                <h6><i class="bi bi-people me-2 text-primary"></i>Team Name: <strong><?= htmlspecialchars((string)($r['team_name'] ?? 'N/A')) ?></strong></h6>
                                <small class="text-muted">Team Code: <?= htmlspecialchars((string)($r['team_code'] ?? 'N/A')) ?></small>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Student Name</th>
                                            <th>Enrollment No</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($members)): ?>
                                            <tr><td colspan="5" class="text-center text-muted py-3">No team members registered yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($members as $mIdx => $m): ?>
                                                <tr>
                                                    <td><?= $mIdx + 1 ?></td>
                                                    <td class="fw-semibold"><?= htmlspecialchars((string)($m['name'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string)($m['enrollment_no'] ?? 'N/A')) ?></td>
                                                    <td><?= htmlspecialchars((string)($m['email'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string)($m['phone'] ?? 'N/A')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <h6><i class="bi bi-person me-2 text-primary"></i>Individual Participant Information</h6>
                            <div class="card card-body bg-light border-0 mt-2 rounded-4">
                                <p class="mb-2"><strong>Name:</strong> <?= htmlspecialchars((string)($r['student_name'] ?? 'N/A')) ?></p>
                                <p class="mb-2"><strong>Email:</strong> <?= htmlspecialchars((string)($r['student_email'] ?? 'N/A')) ?></p>
                                <p class="mb-0"><strong>Phone:</strong> <?= htmlspecialchars((string)($r['student_phone'] ?? 'N/A')) ?></p>
                            </div>
                        <?php endif; ?>
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
        // Real-time Search, Status Filter, Limit & Compact Pagination Engine
        const searchInput = document.getElementById("searchRegistration");
        const statusFilter = document.getElementById("statusFilter");
        const limitSelect = document.getElementById("limitSelect");
        const rows = document.querySelectorAll("#registrationTable tbody tr");
        const showingCountText = document.getElementById("showingCountText");
        const paginationContainer = document.getElementById("pagination");

        let currentPage = 1;

        function filterRegistrations() {
            const searchValue = searchInput.value.toLowerCase().trim();
            const statusValue = statusFilter.value.toLowerCase().trim();
            const limitValue = limitSelect.value;

            const matchedRows = [];
            rows.forEach(row => {
                const badge = row.querySelector(".status-badge");
                if (!badge) return;

                const rowText = row.innerText.toLowerCase();
                const statusText = badge.innerText.toLowerCase().trim();

                const matchesSearch = rowText.includes(searchValue);
                const matchesStatus = statusValue === "" || statusText === statusValue;

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
            showingCountText.textContent = `Showing ${visibleCount} of ${totalMatched} registrations`;

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            paginationContainer.innerHTML = "";
            if (totalPages <= 1) return;

            if (currentPage > 1) {
                const prevLi = document.createElement("li");
                prevLi.className = "page-item";
                prevLi.innerHTML = `<a class="page-link rounded-pill px-3 py-1 text-xs font-bold text-dark border bg-white shadow-sm" style="cursor:pointer;" aria-label="Previous"><i class="bi bi-chevron-left me-1"></i> Prev</a>`;
                prevLi.addEventListener("click", () => { currentPage--; filterRegistrations(); });
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
                nextLi.addEventListener("click", () => { currentPage++; filterRegistrations(); });
                paginationContainer.appendChild(nextLi);
            }
        }

        searchInput.addEventListener("keyup", () => { currentPage = 1; filterRegistrations(); });
        statusFilter.addEventListener("change", () => { currentPage = 1; filterRegistrations(); });
        limitSelect.addEventListener("change", () => { currentPage = 1; filterRegistrations(); });

        document.addEventListener("DOMContentLoaded", filterRegistrations);

        function cancelRegistration(id) {
            if (confirm("Are you sure you want to cancel this registration?")) {
                window.location.href = "allregistrations.php?action=cancel&id=" + id;
            }
        }
    </script>
</body>
</html>