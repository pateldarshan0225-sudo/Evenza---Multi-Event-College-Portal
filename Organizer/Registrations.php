<?php
/**
 * Organizer/Registrations.php
 * Premium Bespoke Registrations Command Center & Roster Inspector with Fixed Avatar Helper
 */
include 'organizer_auth.php';
include 'connection.php';

// Helper for Student Avatar Image Path & Fallback
function get_student_avatar_src(?string $photo, string $name, string $gender = 'male', int $id = 0): string {
    $maleAvatars   = ['avatar-2.jpg', 'avatar-4.jpg', 'avatar-7.jpg', 'avatar-9.jpg'];
    $femaleAvatars = ['avatar-1.jpg', 'avatar-3.jpg', 'avatar-5.jpg', 'avatar-6.jpg', 'avatar-8.jpg', 'avatar-10.jpg'];

    $photoName = trim((string)$photo);

    if (!empty($photoName) && file_exists(__DIR__ . '/../assets/images/user/' . $photoName)) {
        return '../assets/images/user/' . htmlspecialchars($photoName);
    }

    if (!empty($photoName) && file_exists(__DIR__ . '/../uploads/students/' . $photoName)) {
        return '../uploads/students/' . htmlspecialchars($photoName);
    }

    if (!empty($photoName) && (in_array($photoName, $maleAvatars, true) || in_array($photoName, $femaleAvatars, true))) {
        return '../assets/images/user/' . htmlspecialchars($photoName);
    }

    if (strtolower($gender) === 'female') {
        $assigned = $femaleAvatars[$id % count($femaleAvatars)];
    } else {
        $assigned = $maleAvatars[$id % count($maleAvatars)];
    }

    if (file_exists(__DIR__ . '/../assets/images/user/' . $assigned)) {
        return '../assets/images/user/' . $assigned;
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1c2024&color=ffffff&bold=true';
}

$flash = null;

// Handle Registration Status Update / Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['form_action'] ?? '';
    $reg_id  = (int)($_POST['registration_id'] ?? 0);
    $status  = $_POST['status'] ?? 'Approved';

    if ($reg_id > 0 && in_array(strtolower($status), ['approved', 'pending payment', 'cancelled'], true)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE registrations r
                INNER JOIN events e ON r.event_id = e.event_id
                SET r.status = :st
                WHERE r.registration_id = :rid AND e.college_id = :cid
            ");
            $stmt->execute(['st' => $status, 'rid' => $reg_id, 'cid' => $college_id]);
            $flash = ['type' => 'success', 'message' => "Registration #REG-" . str_pad((string)$reg_id, 4, '0', STR_PAD_LEFT) . " status updated to {$status}."];
        } catch (PDOException $e) {
            $flash = ['type' => 'danger', 'message' => 'Error updating status: ' . $e->getMessage()];
        }
    }
}

// Fetch Registrations Scoped to This College's Hosted Events
$stmt = $pdo->prepare("
    SELECT r.*, e.title AS event_title, e.event_type, 
           s.name AS student_name, s.email AS student_email, s.phone AS student_phone, s.enrollment_no, s.semester, s.gender, s.profile_photo,
           t.team_name, t.team_code, t.leader_id,
           COALESCE(p.amount, e.registration_fee, 0) AS amount
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.event_id
    LEFT JOIN students s ON r.student_id = s.student_id
    LEFT JOIN teams t ON r.team_id = t.team_id
    LEFT JOIN payments p ON r.registration_id = p.registration_id
    WHERE e.college_id = :cid
    ORDER BY r.registration_id DESC
");
$stmt->execute(['cid' => $college_id]);
$registrations = $stmt->fetchAll();

// Map Team Members for Team Registrations
$team_ids = array_filter(array_column($registrations, 'team_id'));
$teamMembersMap = [];

if (!empty($team_ids)) {
    $inClause = implode(',', array_map('intval', array_unique($team_ids)));
    $stmtMap = $pdo->query("
        SELECT tm.team_id, s.student_id, s.name, s.email, s.phone, s.enrollment_no, s.semester, s.gender, s.profile_photo
        FROM team_members tm
        INNER JOIN students s ON tm.student_id = s.student_id
        WHERE tm.team_id IN ($inClause)
    ");
    while ($m = $stmtMap->fetch()) {
        $teamMembersMap[$m['team_id']][] = $m;
    }
}

// KPI Summaries
$count_approved = count(array_filter($registrations, fn($r) => strtolower($r['status']) === 'approved'));
$count_pending  = count(array_filter($registrations, fn($r) => strtolower($r['status']) === 'pending payment' || strtolower($r['status']) === 'pending'));
$total_collected = array_reduce($registrations, fn($acc, $r) => strtolower($r['status']) === 'approved' ? $acc + (float)$r['amount'] : $acc, 0.0);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Registrations | Organizer Portal</title>

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
            --bg-dark: #14171a;
            --bg-dark-hover: #22272c;
            --bg-white: #ffffff;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --card-radius: 24px;
            --shadow-subtle: 0 4px 24px rgba(0, 0, 0, 0.03);
            --shadow-elevated: 0 12px 32px rgba(0, 0, 0, 0.06);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: #14171a;
            margin: 0;
            padding: 24px 32px;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .berun-window { width: 100%; position: relative; }

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
            gap: 12px;
            text-decoration: none;
            color: #14171a;
        }

        .berun-logo-dots {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }

        .berun-logo-dots-top { display: flex; gap: 3px; }

        .berun-dot {
            width: 7px;
            height: 7px;
            background-color: #14171a;
            border-radius: 50%;
        }

        .berun-logo-text { font-weight: 800; font-size: 22px; color: #14171a; letter-spacing: -0.6px; }
        .berun-logo-text span { font-weight: 400; opacity: 0.8; }

        .berun-greeting-h1 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -0.4px; }

        .berun-layout-body { display: flex; gap: 28px; }
        .berun-main-grid { flex-grow: 1; display: flex; flex-direction: column; gap: 24px; }

        /* Top Executive Summary KPI Bar */
        .kpi-mini-card {
            background: var(--bg-white);
            border-radius: 20px;
            padding: 18px 22px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .kpi-mini-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .icon-indigo  { background: #eef2ff; color: #4338ca; }
        .icon-emerald { background: #ecfdf5; color: #059669; }
        .icon-amber   { background: #fffbeb; color: #d97706; }
        .icon-purple  { background: #faf5ff; color: #7e22ce; }

        .kpi-mini-val { font-size: 22px; font-weight: 800; color: #111827; margin: 0; line-height: 1; }
        .kpi-mini-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; margin: 0; }

        /* Control & Filter Toolbar */
        .filter-toolbar {
            background: var(--bg-white);
            border-radius: 20px;
            padding: 16px 20px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        .search-pill-group {
            display: flex;
            align-items: center;
            background: #f9f8f4;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 6px 16px;
            width: 340px;
            max-width: 100%;
        }

        .search-pill-group input {
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 500;
            width: 100%;
            outline: none;
            padding-left: 8px;
        }

        .filter-select {
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            padding: 7px 16px;
            background-color: #f9f8f4;
            color: #111827;
            outline: none;
            cursor: pointer;
        }

        .berun-card-panel {
            background: var(--bg-white);
            border-radius: var(--card-radius);
            padding: 28px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .berun-panel-title { font-size: 18px; font-weight: 800; color: #111827; margin: 0; letter-spacing: -0.3px; }
        .berun-panel-sub   { font-size: 12px; color: #6b7280; margin: 2px 0 0 0; font-weight: 500; }

        .leader-star-badge {
            background: #ecfdf5;
            color: #047857;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        @media (max-width: 768px) {
            body { padding: 14px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
            .search-pill-group { width: 100%; }
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
                    <h1 class="berun-greeting-h1">Event Registrations</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Participant rosters and sign-up logs for <?= htmlspecialchars((string)$college_name) ?> events</p>
                </div>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Organizer_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                        <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- 4 EXECUTIVE KPI SUMMARY MINI CARDS -->
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-mini-card">
                            <div class="kpi-mini-icon icon-indigo"><i class="bi bi-ticket-perforated"></i></div>
                            <div>
                                <h3 class="kpi-mini-val"><?= number_format(count($registrations)) ?></h3>
                                <p class="kpi-mini-lbl">Total Registrations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-mini-card">
                            <div class="kpi-mini-icon icon-emerald"><i class="bi bi-check-circle"></i></div>
                            <div>
                                <h3 class="kpi-mini-val"><?= number_format($count_approved) ?></h3>
                                <p class="kpi-mini-lbl">Approved Sign-ups</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-mini-card">
                            <div class="kpi-mini-icon icon-amber"><i class="bi bi-clock"></i></div>
                            <div>
                                <h3 class="kpi-mini-val"><?= number_format($count_pending) ?></h3>
                                <p class="kpi-mini-lbl">Pending Payments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-mini-card">
                            <div class="kpi-mini-icon icon-purple"><i class="bi bi-wallet2"></i></div>
                            <div>
                                <h3 class="kpi-mini-val">₹<?= number_format($total_collected, 2) ?></h3>
                                <p class="kpi-mini-lbl">Revenue Collected</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONTROL & FILTER TOOLBAR -->
                <div class="filter-toolbar">
                    <div class="d-flex align-items-center gap-3 flex-wrap flex-grow-1">
                        <div class="search-pill-group">
                            <i class="bi bi-search text-muted"></i>
                            <input type="text" id="regSearchInput" placeholder="Search REG ID, student, team, event...">
                        </div>
                        <select class="filter-select" id="formatFilter">
                            <option value="all">All Formats</option>
                            <option value="solo">Solo</option>
                            <option value="team">Team</option>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="all">All Statuses</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <!-- REGISTRATIONS LOG PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">Master Participant Roster Log</h3>
                            <p class="berun-panel-sub">Total <?= count($registrations) ?> registration entries recorded</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;" id="regLogTable">
                            <thead>
                                <tr class="text-muted font-bold" style="font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase;">
                                    <th class="ps-3">Reg ID</th>
                                    <th>Participant / Team</th>
                                    <th>Event Title</th>
                                    <th>Format</th>
                                    <th>Fee Paid</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="pe-3 text-end">Roster / Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($registrations)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="bi bi-ticket-detailed fs-1 text-muted opacity-50 d-block mb-2"></i>
                                            No registrations recorded for your college's events yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registrations as $r): ?>
                                        <?php 
                                        $isTeam = $r['registration_type'] === 'team';
                                        $soloAvatar = get_student_avatar_src($r['profile_photo'] ?? null, $r['student_name'] ?? 'Student', $r['gender'] ?? 'male', (int)($r['student_id'] ?? 0));
                                        $fallbackUiAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($r['student_name'] ?? 'Student') . '&background=1c2024&color=ffffff&bold=true';
                                        ?>
                                        <tr class="reg-table-row"
                                            data-regid="#reg-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?>"
                                            data-participant="<?= htmlspecialchars(strtolower($isTeam ? ($r['team_name'] ?? '') : ($r['student_name'] ?? ''))) ?>"
                                            data-event="<?= htmlspecialchars(strtolower($r['event_title'])) ?>"
                                            data-format="<?= $r['registration_type'] ?>"
                                            data-status="<?= strtolower($r['status']) ?>">
                                            
                                            <td class="ps-3 font-semibold text-muted">#REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <?php if ($isTeam): ?>
                                                    <div class="fw-bold text-dark"><i class="bi bi-people-fill text-purple me-1"></i> <?= htmlspecialchars((string)($r['team_name'] ?? 'Team')) ?></div>
                                                    <small class="text-muted">Code: <strong><?= htmlspecialchars((string)($r['team_code'] ?? 'N/A')) ?></strong></small>
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= $soloAvatar ?>" class="rounded-circle border" width="30" height="30" style="object-fit:cover;" onerror="this.onerror=null; this.src='<?= $fallbackUiAvatar ?>';">
                                                        <div>
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars((string)($r['student_name'] ?? 'Student')) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars((string)($r['student_email'] ?? '')) ?></small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars((string)$r['event_title']) ?></td>
                                            <td>
                                                <?php if ($isTeam): ?>
                                                    <span class="badge bg-purple-subtle text-purple rounded-pill px-3 py-1 font-semibold" style="background:#faf5ff; color:#7e22ce;">Team</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1 font-semibold" style="background:#f0f9ff; color:#0369a1;">Solo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-dark">₹<?= number_format((float)$r['amount'], 2) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="form_action" value="update_status">
                                                    <input type="hidden" name="registration_id" value="<?= $r['registration_id'] ?>">
                                                    <select name="status" onchange="this.form.submit()" class="form-select form-select-sm rounded-pill font-semibold border-0" style="width: auto; font-size: 11px; cursor: pointer; background-color: #f9f8f4;">
                                                        <option value="Approved" <?= strtolower($r['status']) === 'approved' ? 'selected' : '' ?>>● Approved</option>
                                                        <option value="Pending Payment" <?= strtolower($r['status']) === 'pending payment' || strtolower($r['status']) === 'pending' ? 'selected' : '' ?>>● Pending</option>
                                                        <option value="Cancelled" <?= strtolower($r['status']) === 'cancelled' ? 'selected' : '' ?>>● Cancelled</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($r['registered_at'] ?? 'now')) ?></td>
                                            <td class="pe-3 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1 text-xs font-semibold" data-bs-toggle="modal" data-bs-target="#rosterModal<?= $r['registration_id'] ?>">
                                                    <i class="bi bi-eye me-1"></i> View Roster
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- ROSTER INSPECTOR MODAL -->
                                        <div class="modal fade" id="rosterModal<?= $r['registration_id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content border-0 rounded-4 shadow-lg">
                                                    <div class="modal-header border-bottom p-4">
                                                        <div>
                                                            <span class="badge bg-light text-dark border rounded-pill px-3 py-1 text-xs font-semibold mb-1">
                                                                #REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?>
                                                            </span>
                                                            <h5 class="modal-title font-bold text-dark mb-0">
                                                                Participant Roster: <?= htmlspecialchars((string)$r['event_title']) ?>
                                                            </h5>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-4">
                                                            <div class="col-md-6">
                                                                <span class="text-muted text-uppercase text-xs font-semibold d-block">Registration Type</span>
                                                                <span class="badge bg-light text-dark border rounded-pill px-3 py-1 mt-1 font-semibold"><?= ucfirst($r['registration_type']) ?> Registration</span>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <span class="text-muted text-uppercase text-xs font-semibold d-block">Fee Paid</span>
                                                                <strong class="text-dark fs-6 mt-1 d-block">₹<?= number_format((float)$r['amount'], 2) ?></strong>
                                                            </div>
                                                        </div>

                                                        <?php if ($isTeam): ?>
                                                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                                                <i class="bi bi-people-fill text-purple me-1"></i> Team Members Roster: <?= htmlspecialchars((string)($r['team_name'] ?? 'Team')) ?> (Code: <?= htmlspecialchars((string)($r['team_code'] ?? 'N/A')) ?>)
                                                            </h6>
                                                            <?php $mList = $teamMembersMap[$r['team_id']] ?? []; ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm align-middle mb-0">
                                                                    <thead>
                                                                        <tr class="text-muted text-xs text-uppercase font-semibold" style="letter-spacing: 0.6px;">
                                                                            <th class="ps-2">#</th>
                                                                            <th>Member Name</th>
                                                                            <th>Enrollment No</th>
                                                                            <th>Email Address</th>
                                                                            <th>Phone</th>
                                                                            <th class="text-end pe-2">Role</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (empty($mList)): ?>
                                                                            <tr><td colspan="6" class="text-center text-muted py-4">No team member details found.</td></tr>
                                                                        <?php else: ?>
                                                                            <?php foreach ($mList as $mIdx => $tm): ?>
                                                                                <?php 
                                                                                $tmAvatar = get_student_avatar_src($tm['profile_photo'] ?? null, $tm['name'], $tm['gender'] ?? 'male', (int)$tm['student_id']);
                                                                                $tmFallback = 'https://ui-avatars.com/api/?name=' . urlencode($tm['name']) . '&background=1c2024&color=ffffff&bold=true';
                                                                                ?>
                                                                                <tr>
                                                                                    <td class="ps-2 font-semibold text-muted"><?= $mIdx + 1 ?></td>
                                                                                    <td class="fw-bold text-dark">
                                                                                        <div class="d-flex align-items-center gap-2">
                                                                                            <img src="<?= $tmAvatar ?>" class="rounded-circle border" width="28" height="28" style="object-fit:cover;" onerror="this.onerror=null; this.src='<?= $tmFallback ?>';">
                                                                                            <span><?= htmlspecialchars((string)$tm['name']) ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="fw-semibold text-dark"><?= htmlspecialchars((string)($tm['enrollment_no'] ?? 'N/A')) ?></td>
                                                                                    <td class="text-muted"><?= htmlspecialchars((string)$tm['email']) ?></td>
                                                                                    <td class="text-muted"><?= htmlspecialchars((string)($tm['phone'] ?? 'N/A')) ?></td>
                                                                                    <td class="text-end pe-2">
                                                                                        <?php if ((int)$tm['student_id'] === (int)($r['leader_id'] ?? 0)): ?>
                                                                                            <span class="leader-star-badge"><i class="bi bi-star-fill"></i> Leader</span>
                                                                                        <?php else: ?>
                                                                                            <span class="badge bg-light text-muted rounded-pill px-3 py-1 font-normal">Member</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-person-fill text-primary me-1"></i> Solo Participant Profile</h6>
                                                            <div class="p-3 bg-light rounded-4">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <img src="<?= $soloAvatar ?>" class="rounded-circle border" width="56" height="56" style="object-fit:cover;" onerror="this.onerror=null; this.src='<?= $fallbackUiAvatar ?>';">
                                                                    <div>
                                                                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars((string)$r['student_name']) ?></h6>
                                                                        <span class="text-muted text-xs"><?= htmlspecialchars((string)$r['student_email']) ?> | Phone: <?= htmlspecialchars((string)($r['student_phone'] ?? 'N/A')) ?></span>
                                                                        <div class="text-xs text-muted mt-1">Enrollment: <strong><?= htmlspecialchars((string)($r['enrollment_no'] ?? 'N/A')) ?></strong> | Semester: <strong><?= htmlspecialchars((string)($r['semester'] ?? 'N/A')) ?></strong></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer border-top p-3">
                                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 font-semibold text-xs" data-bs-dismiss="modal">Close Roster</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Client-Side Filter Search JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput  = document.getElementById('regSearchInput');
            const formatFilter = document.getElementById('formatFilter');
            const statusFilter = document.getElementById('statusFilter');

            function applyRegFilters() {
                const query = searchInput.value.toLowerCase().trim();
                const fmt   = formatFilter.value;
                const st    = statusFilter.value;

                document.querySelectorAll('.reg-table-row').forEach(row => {
                    const regid       = row.dataset.regid || '';
                    const participant = row.dataset.participant || '';
                    const eventTitle  = row.dataset.event || '';
                    const format      = row.dataset.format || '';
                    const status      = row.dataset.status || '';

                    const matchesSearch = regid.includes(query) || participant.includes(query) || eventTitle.includes(query);
                    const matchesFmt    = fmt === 'all' || format === fmt;
                    const matchesSt     = st === 'all' || status.includes(st);

                    if (matchesSearch && matchesFmt && matchesSt) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            }

            if (searchInput)  searchInput.addEventListener('input', applyRegFilters);
            if (formatFilter) formatFilter.addEventListener('change', applyRegFilters);
            if (statusFilter) statusFilter.addEventListener('change', applyRegFilters);
        });
    </script>
</body>
</html>
