<?php
/**
 * Organizer/Registrations.php
 * Registrations & Team Roster Inspector for College Events
 */
include 'organizer_auth.php';
include 'connection.php';

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
            $flash = ['type' => 'success', 'message' => "Registration #{$reg_id} status updated to {$status}."];
        } catch (PDOException $e) {
            $flash = ['type' => 'danger', 'message' => 'Error updating status: ' . $e->getMessage()];
        }
    }
}

// Fetch Registrations Scoped to This College's Hosted Events
$stmt = $pdo->prepare("
    SELECT r.*, e.title AS event_title, e.event_type, 
           s.name AS student_name, s.email AS student_email, s.phone AS student_phone, s.enrollment_no, s.semester, s.profile_photo,
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
        SELECT tm.team_id, s.student_id, s.name, s.email, s.phone, s.enrollment_no, s.semester, s.profile_photo
        FROM team_members tm
        INNER JOIN students s ON tm.student_id = s.student_id
        WHERE tm.team_id IN ($inClause)
    ");
    while ($m = $stmtMap->fetch()) {
        $teamMembersMap[$m['team_id']][] = $m;
    }
}
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
            --bg-dark: #1c2024;
            --bg-white: #ffffff;
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: #1c2024;
            margin: 0;
            padding: 20px 28px;
            min-height: 100vh;
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
            gap: 10px;
            text-decoration: none;
            color: #1c2024;
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
            background-color: #1c2024;
            border-radius: 50%;
        }

        .berun-logo-text { font-weight: 800; font-size: 20px; color: #1c2024; }
        .berun-logo-text span { font-weight: 400; }

        .berun-greeting-h1 { font-size: 24px; font-weight: 700; margin: 0; }

        .berun-layout-body { display: flex; gap: 28px; }
        .berun-main-grid { flex-grow: 1; display: flex; flex-direction: column; gap: 28px; }

        .berun-card-panel {
            background: var(--bg-white);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .berun-panel-title { font-size: 18px; font-weight: 700; margin: 0; }
        .berun-panel-sub   { font-size: 12px; color: #7c7d7e; margin: 2px 0 0 0; }

        .status-badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .badge-success { background: #e6f7ed; color: #10b981; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-danger  { background: #fde8e8; color: #dc3545; }

        @media (max-width: 768px) {
            body { padding: 12px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
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
                    <h1 class="berun-greeting-h1">Event Registrations</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Participant rosters and sign-ups for <?= htmlspecialchars((string)$college_name) ?> events</p>
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

                <!-- REGISTRATIONS PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">Registration Log</h3>
                            <p class="berun-panel-sub">Total <?= count($registrations) ?> participant entries recorded</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted text-uppercase font-semibold" style="font-size: 11px;">
                                    <th class="ps-3">Reg ID</th>
                                    <th>Participant / Team</th>
                                    <th>Event</th>
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
                                        <td colspan="8" class="text-center text-muted py-4">No registrations recorded for your college's events yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registrations as $r): ?>
                                        <?php $isTeam = $r['registration_type'] === 'team'; ?>
                                        <tr>
                                            <td class="ps-3 font-semibold text-muted">#REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <?php if ($isTeam): ?>
                                                    <div class="fw-bold text-dark"><i class="bi bi-people-fill text-purple me-1"></i> <?= htmlspecialchars((string)($r['team_name'] ?? 'Team')) ?></div>
                                                    <small class="text-muted">Code: <?= htmlspecialchars((string)($r['team_code'] ?? 'N/A')) ?></small>
                                                <?php else: ?>
                                                    <div class="fw-bold text-dark"><i class="bi bi-person-fill text-primary me-1"></i> <?= htmlspecialchars((string)($r['student_name'] ?? 'Student')) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars((string)($r['student_email'] ?? '')) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars((string)$r['event_title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark rounded-pill px-3 py-1 border"><?= ucfirst($r['registration_type']) ?></span>
                                            </td>
                                            <td class="fw-bold text-dark">₹<?= number_format((float)$r['amount'], 2) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="form_action" value="update_status">
                                                    <input type="hidden" name="registration_id" value="<?= $r['registration_id'] ?>">
                                                    <select name="status" onchange="this.form.submit()" class="form-select form-select-sm rounded-pill font-semibold border-0" style="width: auto; font-size: 11px; cursor: pointer;">
                                                        <option value="Approved" <?= strtolower($r['status']) === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                        <option value="Pending Payment" <?= strtolower($r['status']) === 'pending payment' || strtolower($r['status']) === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="Cancelled" <?= strtolower($r['status']) === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($r['registered_at'] ?? 'now')) ?></td>
                                            <td class="pe-3 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1 text-xs" data-bs-toggle="modal" data-bs-target="#rosterModal<?= $r['registration_id'] ?>">
                                                    <i class="bi bi-eye me-1"></i> View Roster
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- ROSTER INSPECTOR MODAL -->
                                        <div class="modal fade" id="rosterModal<?= $r['registration_id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content border-0 rounded-4 shadow">
                                                    <div class="modal-header border-bottom">
                                                        <h5 class="modal-title font-bold text-dark">
                                                            <i class="bi bi-card-checklist me-2 text-primary"></i> Participant Roster — Registration #REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-4">
                                                            <div class="col-md-6">
                                                                <span class="text-muted text-uppercase text-xs font-semibold d-block">Event Title</span>
                                                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars((string)$r['event_title']) ?></h6>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <span class="text-muted text-uppercase text-xs font-semibold d-block">Registration Type</span>
                                                                <span class="badge bg-light text-dark border rounded-pill px-3 py-1 mt-1"><?= ucfirst($r['registration_type']) ?> Registration</span>
                                                            </div>
                                                        </div>

                                                        <?php if ($isTeam): ?>
                                                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Team Members Roster (<?= htmlspecialchars((string)($r['team_name'] ?? 'Team')) ?>)</h6>
                                                            <?php $mList = $teamMembersMap[$r['team_id']] ?? []; ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm align-middle">
                                                                    <thead>
                                                                        <tr class="text-muted text-xs text-uppercase">
                                                                            <th>#</th>
                                                                            <th>Member Name</th>
                                                                            <th>Enrollment No</th>
                                                                            <th>Email</th>
                                                                            <th>Phone</th>
                                                                            <th>Role</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (empty($mList)): ?>
                                                                            <tr><td colspan="6" class="text-center text-muted py-3">No member details found for this team.</td></tr>
                                                                        <?php else: ?>
                                                                            <?php foreach ($mList as $mIdx => $tm): ?>
                                                                                <tr>
                                                                                    <td><?= $mIdx + 1 ?></td>
                                                                                    <td class="fw-semibold text-dark"><?= htmlspecialchars((string)$tm['name']) ?></td>
                                                                                    <td><?= htmlspecialchars((string)($tm['enrollment_no'] ?? 'N/A')) ?></td>
                                                                                    <td class="text-muted"><?= htmlspecialchars((string)$tm['email']) ?></td>
                                                                                    <td class="text-muted"><?= htmlspecialchars((string)($tm['phone'] ?? 'N/A')) ?></td>
                                                                                    <td>
                                                                                        <?php if ((int)$tm['student_id'] === (int)($r['leader_id'] ?? 0)): ?>
                                                                                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1"><i class="bi bi-star-fill me-1"></i> Leader</span>
                                                                                        <?php else: ?>
                                                                                            <span class="badge bg-light text-muted rounded-pill px-2 py-1">Member</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Solo Participant Details</h6>
                                                            <div class="p-3 bg-light rounded-4">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <img src="<?= !empty($r['profile_photo']) ? '../uploads/students/' . htmlspecialchars($r['profile_photo']) : 'https://ui-avatars.com/api/?name=' . urlencode($r['student_name']) ?>" class="rounded-circle" width="54" height="54" style="object-fit:cover;">
                                                                    <div>
                                                                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars((string)$r['student_name']) ?></h6>
                                                                        <span class="text-muted text-xs"><?= htmlspecialchars((string)$r['student_email']) ?> | Phone: <?= htmlspecialchars((string)($r['student_phone'] ?? 'N/A')) ?></span>
                                                                        <div class="text-xs text-muted mt-1">Enrollment: <strong><?= htmlspecialchars((string)($r['enrollment_no'] ?? 'N/A')) ?></strong> | Semester: <strong><?= htmlspecialchars((string)($r['semester'] ?? 'N/A')) ?></strong></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer border-top">
                                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
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
</body>
</html>
