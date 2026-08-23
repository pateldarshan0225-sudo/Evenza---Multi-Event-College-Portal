<?php
/**
 * Organizer/Dashboard.php
 * Organizer Dashboard for College Events & Metrics
 */
include 'organizer_auth.php';
include 'connection.php';

// Fetch College Details
$stmt = $pdo->prepare("SELECT * FROM colleges WHERE college_id = :cid LIMIT 1");
$stmt->execute(['cid' => $college_id]);
$college_info = $stmt->fetch();

// 1. Total Hosted Events
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM events WHERE college_id = :cid");
$stmt->execute(['cid' => $college_id]);
$total_events = (int)($stmt->fetch()['total'] ?? 0);

// 2. Total Registrations for College Events
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total 
    FROM registrations r 
    INNER JOIN events e ON r.event_id = e.event_id 
    WHERE e.college_id = :cid
");
$stmt->execute(['cid' => $college_id]);
$total_registrations = (int)($stmt->fetch()['total'] ?? 0);

// 3. Revenue Collected for College Events
$stmt = $pdo->prepare("
    SELECT SUM(r.amount) AS total 
    FROM registrations r 
    INNER JOIN events e ON r.event_id = e.event_id 
    WHERE e.college_id = :cid AND r.status = 'Approved'
");
$stmt->execute(['cid' => $college_id]);
$total_revenue = (float)($stmt->fetch()['total'] ?? 0.0);

// 4. Enrolled Students in College
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM students WHERE college_id = :cid");
$stmt->execute(['cid' => $college_id]);
$total_students = (int)($stmt->fetch()['total'] ?? 0);

// Fetch Hosted Events List
$stmt = $pdo->prepare("
    SELECT e.*, c.name AS category_name,
    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) AS reg_count
    FROM events e
    LEFT JOIN event_categories c ON e.category_id = c.category_id
    WHERE e.college_id = :cid
    ORDER BY e.event_id DESC
");
$stmt->execute(['cid' => $college_id]);
$events = $stmt->fetchAll();

// Fetch Recent Registrations
$stmt = $pdo->prepare("
    SELECT r.*, e.title AS event_title, s.name AS student_name, s.email AS student_email, t.team_name, t.team_code
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.event_id
    LEFT JOIN students s ON r.student_id = s.student_id
    LEFT JOIN teams t ON r.team_id = t.team_id
    WHERE e.college_id = :cid
    ORDER BY r.registration_id DESC
    LIMIT 6
");
$stmt->execute(['cid' => $college_id]);
$recent_registrations = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organizer Dashboard | Evenza</title>

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
            --color-yellow: #ffd13b;
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

        .berun-window { width: 100%; position: relative; }

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

        .berun-layout-body { display: flex; gap: 28px; }
        .berun-main-grid { flex-grow: 1; display: flex; flex-direction: column; gap: 28px; }

        /* Stat Cards */
        .berun-stat-card {
            background: var(--bg-white);
            border-radius: 20px;
            padding: 22px 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.02);
            height: 100%;
        }

        .berun-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .icon-primary { background: #e8edff; color: #4f46e5; }
        .icon-success { background: #e6f7ed; color: #10b981; }
        .icon-warning { background: #fef3c7; color: #d97706; }
        .icon-purple  { background: #f3e8ff; color: #9333ea; }

        .berun-card-panel {
            background: var(--bg-white);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .berun-panel-title { font-size: 18px; font-weight: 700; color: var(--color-text-dark); margin: 0; }
        .berun-panel-sub   { font-size: 12px; color: var(--color-text-muted); margin: 2px 0 0 0; font-weight: 500; }

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
                    <div class="d-flex align-items-center gap-2">
                        <h1 class="berun-greeting-h1">Welcome, <?= htmlspecialchars((string)$college_name) ?>!</h1>
                        <span class="status-badge badge-success">
                            <i class="bi bi-patch-check-fill me-1"></i> Organizer Portal
                        </span>
                    </div>
                    <p class="text-muted mb-0 text-xs font-medium">Overview of your college's events, registrations, and student metrics</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="Events.php" class="berun-btn-dark">
                    <i class="bi bi-calendar-plus"></i> Host New Event
                </a>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Organizer_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <!-- 4 KPI STAT CARDS (SCOPED TO COLLEGE) -->
                <div class="row g-3">
                    <!-- Hosted Events -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide font-bold" style="font-size:11px;">Hosted Events</span>
                                    <h2 class="mb-0 fw-extrabold mt-1 text-dark" style="font-size: 24px;"><?= number_format($total_events) ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-primary">
                                    <i class="bi bi-calendar3"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px; background-color: #e8edff; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #4f46e5; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Registrations -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide font-bold" style="font-size:11px;">Total Registrations</span>
                                    <h2 class="mb-0 fw-extrabold mt-1 text-dark" style="font-size: 24px;"><?= number_format($total_registrations) ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-success">
                                    <i class="bi bi-ticket-perforated"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px; background-color: #e6f7ed; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #10b981; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide font-bold" style="font-size:11px;">Revenue Collected</span>
                                    <h2 class="mb-0 fw-extrabold mt-1 text-dark" style="font-size: 24px;">₹<?= number_format($total_revenue, 2) ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-warning">
                                    <i class="bi bi-currency-rupee"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px; background-color: #fef3c7; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #d97706; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Enrolled Students -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="berun-stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted text-uppercase tracking-wide font-bold" style="font-size:11px;">College Students</span>
                                    <h2 class="mb-0 fw-extrabold mt-1 text-dark" style="font-size: 24px;"><?= number_format($total_students) ?></h2>
                                </div>
                                <div class="berun-stat-icon icon-purple">
                                    <i class="bi bi-mortarboard"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px; background-color: #f3e8ff; border-radius: 9999px;">
                                <div class="progress-bar" style="width: 100%; background-color: #9333ea; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HOSTED EVENTS PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">Events Hosted by <?= htmlspecialchars((string)$college_name) ?></h3>
                            <p class="berun-panel-sub">Manage and monitor events organized by your college</p>
                        </div>
                        <a href="Events.php" class="btn btn-outline-dark rounded-pill px-3 py-1 text-xs font-semibold">
                            View All Events <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted text-uppercase font-semibold" style="font-size: 11px;">
                                    <th class="ps-3">#</th>
                                    <th>Event Title</th>
                                    <th>Category</th>
                                    <th>Format</th>
                                    <th>Venue</th>
                                    <th>Date</th>
                                    <th>Registrations</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($events)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No events hosted by your college yet. Click "Host New Event" to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $idx => $e): ?>
                                        <tr>
                                            <td class="ps-3 font-semibold text-muted"><?= $idx + 1 ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars((string)$e['title']) ?></td>
                                            <td><span class="badge bg-light text-dark rounded-pill px-3 py-1 border"><?= htmlspecialchars((string)($e['category_name'] ?? 'General')) ?></span></td>
                                            <td>
                                                <?php if ($e['event_type'] === 'team'): ?>
                                                    <span class="badge bg-purple-subtle text-purple rounded-pill px-3 py-1" style="background:#f3e8ff; color:#9333ea;">Team (<?= $e['min_team_size'] ?>-<?= $e['max_team_size'] ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1" style="background:#e0f2fe; color:#0284c7;">Solo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)$e['venue']) ?></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($e['event_date'])) ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1"><?= $e['reg_count'] ?> Participants</span></td>
                                            <td>
                                                <?php if ($e['status'] === 'published'): ?>
                                                    <span class="status-badge badge-success"><i class="bi bi-check-circle-fill me-1"></i> Published</span>
                                                <?php else: ?>
                                                    <span class="status-badge badge-warning"><i class="bi bi-clock-history me-1"></i> Draft</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RECENT REGISTRATIONS PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">Recent Event Registrations</h3>
                            <p class="berun-panel-sub">Latest student and team sign-ups for your college events</p>
                        </div>
                        <a href="Registrations.php" class="btn btn-outline-dark rounded-pill px-3 py-1 text-xs font-semibold">
                            View All Registrations <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted text-uppercase font-semibold" style="font-size: 11px;">
                                    <th class="ps-3">Participant</th>
                                    <th>Event</th>
                                    <th>Format</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_registrations)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No recent registrations found for your college events.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_registrations as $r): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <?php if ($r['registration_type'] === 'team'): ?>
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
                                                <?php if ($r['status'] === 'Approved'): ?>
                                                    <span class="status-badge badge-success">Approved</span>
                                                <?php elseif ($r['status'] === 'Pending Payment'): ?>
                                                    <span class="status-badge badge-warning">Pending</span>
                                                <?php else: ?>
                                                    <span class="status-badge badge-danger">Cancelled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></td>
                                        </tr>
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
