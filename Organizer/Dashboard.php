<?php
/**
 * Organizer/Dashboard.php
 * Premium Bespoke Dashboard for College Events & Organizer Analytics
 */
include 'organizer_auth.php';
include 'connection.php';

// Fetch College Details & Affiliated University
$stmt = $pdo->prepare("
    SELECT c.*, u.name AS university_name 
    FROM colleges c 
    LEFT JOIN universities u ON c.university_id = u.university_id 
    WHERE c.college_id = :cid 
    LIMIT 1
");
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
    SELECT COALESCE(SUM(p.amount), SUM(e.registration_fee), 0) AS total 
    FROM registrations r 
    INNER JOIN events e ON r.event_id = e.event_id 
    LEFT JOIN payments p ON r.registration_id = p.registration_id 
    WHERE e.college_id = :cid AND (LOWER(r.status) = 'approved' OR LOWER(p.payment_status) = 'paid')
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
    LEFT JOIN categories c ON e.category_id = c.category_id
    WHERE e.college_id = :cid
    ORDER BY e.event_id DESC
");
$stmt->execute(['cid' => $college_id]);
$events = $stmt->fetchAll();

// Fetch Recent Registrations
$stmt = $pdo->prepare("
    SELECT r.*, e.title AS event_title, 
           s.name AS student_name, s.email AS student_email, s.profile_photo,
           t.team_name, t.team_code,
           COALESCE(p.amount, e.registration_fee, 0) AS amount
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.event_id
    LEFT JOIN students s ON r.student_id = s.student_id
    LEFT JOIN teams t ON r.team_id = t.team_id
    LEFT JOIN payments p ON r.registration_id = p.registration_id
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
    <title>Organizer Dashboard | Evenza Portal</title>

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
            --color-yellow: #ffd13b;
            --color-text-dark: #14171a;
            --color-text-muted: #6b7280;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --card-radius: 24px;
            --shadow-subtle: 0 4px 24px rgba(0, 0, 0, 0.03);
            --shadow-elevated: 0 12px 32px rgba(0, 0, 0, 0.06);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: var(--color-text-dark);
            margin: 0;
            padding: 24px 32px;
            min-height: 100vh;
            width: 100%;
            -webkit-font-smoothing: antialiased;
        }

        .berun-window { width: 100%; position: relative; }

        /* Premium Header Navigation Bar */
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
            color: var(--color-text-dark);
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
            background-color: var(--color-text-dark);
            border-radius: 50%;
        }

        .berun-logo-text {
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.6px;
            color: var(--color-text-dark);
        }
        .berun-logo-text span { font-weight: 400; opacity: 0.8; }

        .berun-header-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #4f46e5;
            background: #eef2ff;
            padding: 4px 12px;
            border-radius: 9999px;
        }

        .berun-btn-dark {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 12px 26px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(20, 23, 26, 0.12);
        }
        .berun-btn-dark:hover {
            background-color: var(--bg-dark-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(20, 23, 26, 0.2);
        }

        .berun-layout-body { display: flex; gap: 28px; }
        .berun-main-grid { flex-grow: 1; display: flex; flex-direction: column; gap: 24px; }

        /* Hero Welcome Banner */
        .hero-welcome-card {
            background: linear-gradient(135deg, #14171a 0%, #232930 100%);
            border-radius: 28px;
            padding: 32px 36px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-elevated);
        }

        .hero-welcome-card::after {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 340px;
            height: 340px;
            background: radial-gradient(circle, rgba(255, 209, 59, 0.12) 0%, rgba(255, 209, 59, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-college-name {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.6px;
            margin: 0;
            line-height: 1.25;
        }

        .hero-univ-pill {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #f3f4f6;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
        }

        /* Executive Stat Cards */
        .exec-stat-card {
            background: var(--bg-white);
            border-radius: var(--card-radius);
            padding: 24px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0, 0, 0, 0.03);
            transition: all 0.25s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .exec-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-elevated);
            border-color: rgba(0, 0, 0, 0.06);
        }

        .exec-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .exec-stat-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
        }

        .exec-icon-pod {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .pod-indigo  { background: #eef2ff; color: #4338ca; }
        .pod-emerald { background: #ecfdf5; color: #059669; }
        .pod-amber   { background: #fffbeb; color: #d97706; }
        .pod-purple  { background: #faf5ff; color: #7e22ce; }

        .exec-stat-val {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.8px;
            color: #111827;
            margin-bottom: 8px;
            line-height: 1;
        }

        .exec-trend-tag {
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 9999px;
        }

        .trend-success { background: #ecfdf5; color: #059669; }
        .trend-indigo  { background: #eef2ff; color: #4338ca; }
        .trend-amber   { background: #fffbeb; color: #d97706; }
        .trend-purple  { background: #faf5ff; color: #7e22ce; }

        /* Panel Container */
        .berun-card-panel {
            background: var(--bg-white);
            border-radius: var(--card-radius);
            padding: 28px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .berun-panel-title {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            margin: 0;
            letter-spacing: -0.3px;
        }

        .berun-panel-sub {
            font-size: 12px;
            color: var(--color-text-muted);
            margin: 2px 0 0 0;
            font-weight: 500;
        }

        /* Status Dot Indicator Pills */
        .status-dot-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
        }

        .dot-green {
            background: #ecfdf5;
            color: #047857;
        }
        .dot-green::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
        }

        .dot-amber {
            background: #fffbeb;
            color: #b45309;
        }
        .dot-amber::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #f59e0b;
        }

        .dot-red {
            background: #fef2f2;
            color: #b91c1c;
        }
        .dot-red::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #ef4444;
        }

        /* Quick Action Capsule Shortcut Items */
        .action-shortcut-card {
            background: #f9f8f4;
            border-radius: 18px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: #111827;
            transition: all 0.2s ease;
            border: 1px solid rgba(0,0,0,0.02);
        }

        .action-shortcut-card:hover {
            background: #ffffff;
            box-shadow: var(--shadow-subtle);
            transform: translateY(-2px);
            color: #111827;
        }

        .action-shortcut-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        @media (max-width: 768px) {
            body { padding: 14px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
            .hero-welcome-card { padding: 24px; }
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

                <div class="d-flex align-items-center gap-2">
                    <span class="berun-header-tag"><i class="bi bi-shield-check me-1"></i> Verified College Organizer</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="Events.php" class="berun-btn-dark">
                    <i class="bi bi-plus-circle"></i> Create New Event
                </a>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Organizer_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <!-- HERO WELCOME BANNER -->
                <div class="hero-welcome-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <span class="text-xs text-uppercase font-bold tracking-widest text-warning opacity-90"><i class="bi bi-building me-1"></i> Organizer Command Center</span>
                            <h1 class="hero-college-name mt-1"><?= htmlspecialchars((string)$college_name) ?></h1>
                            <div class="hero-univ-pill">
                                <i class="bi bi-bank2"></i> Affiliated with <?= htmlspecialchars((string)($college_info['university_name'] ?? 'Main University')) ?>
                            </div>
                        </div>
                        <div class="text-end d-none d-md-block">
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 text-xs font-bold shadow-sm">
                                <i class="bi bi-broadcast text-success me-1"></i> Portal Active
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 4 EXECUTIVE KPI STAT CARDS -->
                <div class="row g-3">
                    <!-- Hosted Events -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Hosted Events</span>
                                <div class="exec-icon-pod pod-indigo">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val"><?= number_format($total_events) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-indigo">
                                    <i class="bi bi-check-circle-fill"></i> <?= $total_events ?> Events Created
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Total Registrations -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Total Sign-ups</span>
                                <div class="exec-icon-pod pod-emerald">
                                    <i class="bi bi-ticket-perforated"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val"><?= number_format($total_registrations) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-success">
                                    <i class="bi bi-graph-up-arrow"></i> Active Registrations
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Revenue Collected</span>
                                <div class="exec-icon-pod pod-amber">
                                    <i class="bi bi-currency-rupee"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val">₹<?= number_format($total_revenue, 2) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-amber">
                                    <i class="bi bi-wallet2"></i> Fee Collection
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Enrolled Students -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Enrolled Students</span>
                                <div class="exec-icon-pod pod-purple">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val"><?= number_format($total_students) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-purple">
                                    <i class="bi bi-mortarboard"></i> College Roster
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QUICK ACTIONS SHORTCUTS POD -->
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <a href="Events.php" class="action-shortcut-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="action-shortcut-icon text-indigo"><i class="bi bi-plus-lg"></i></div>
                                <div>
                                    <span class="fw-bold text-xs d-block">Create Event</span>
                                    <small class="text-muted text-xs">Publish new event</small>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted text-xs"></i>
                        </a>
                    </div>
                    <div class="col-12 col-md-3">
                        <a href="Registrations.php" class="action-shortcut-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="action-shortcut-icon text-emerald"><i class="bi bi-card-checklist"></i></div>
                                <div>
                                    <span class="fw-bold text-xs d-block">Inspect Rosters</span>
                                    <small class="text-muted text-xs">View team members</small>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted text-xs"></i>
                        </a>
                    </div>
                    <div class="col-12 col-md-3">
                        <a href="Students.php" class="action-shortcut-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="action-shortcut-icon text-purple"><i class="bi bi-mortarboard"></i></div>
                                <div>
                                    <span class="fw-bold text-xs d-block">My Students</span>
                                    <small class="text-muted text-xs">College directory</small>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted text-xs"></i>
                        </a>
                    </div>
                    <div class="col-12 col-md-3">
                        <a href="Profile.php" class="action-shortcut-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="action-shortcut-icon text-amber"><i class="bi bi-sliders"></i></div>
                                <div>
                                    <span class="fw-bold text-xs d-block">College Profile</span>
                                    <small class="text-muted text-xs">Manage settings</small>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted text-xs"></i>
                        </a>
                    </div>
                </div>

                <!-- HOSTED EVENTS PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">Events Hosted by Your College</h3>
                            <p class="berun-panel-sub">Manage event listings and monitor registration counts</p>
                        </div>
                        <a href="Events.php" class="btn btn-outline-dark rounded-pill px-3 py-1 text-xs font-bold">
                            View All Events <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted font-bold" style="font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase;">
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
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                                            No events created by your college yet. Click "Create Event" to get started!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $idx => $e): ?>
                                        <tr>
                                            <td class="ps-3 font-semibold text-muted"><?= $idx + 1 ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars((string)$e['title']) ?></td>
                                            <td><span class="badge bg-light text-dark rounded-pill px-3 py-1 border font-semibold"><?= htmlspecialchars((string)($e['category_name'] ?? 'General')) ?></span></td>
                                            <td>
                                                <?php if ($e['event_type'] === 'team'): ?>
                                                    <span class="badge bg-purple-subtle text-purple rounded-pill px-3 py-1" style="background:#faf5ff; color:#7e22ce;">Team (<?= $e['min_team_size'] ?>-<?= $e['max_team_size'] ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1" style="background:#f0f9ff; color:#0369a1;">Solo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars((string)$e['venue']) ?></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($e['event_date'])) ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 font-semibold"><?= $e['reg_count'] ?> Participants</span></td>
                                            <td>
                                                <?php if ($e['status'] === 'published'): ?>
                                                    <span class="status-dot-pill dot-green">Published</span>
                                                <?php else: ?>
                                                    <span class="status-dot-pill dot-amber">Draft</span>
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
                        <a href="Registrations.php" class="btn btn-outline-dark rounded-pill px-3 py-1 text-xs font-bold">
                            View All Registrations <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted font-bold" style="font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase;">
                                    <th class="ps-3">Participant / Team</th>
                                    <th>Event</th>
                                    <th>Format</th>
                                    <th>Fee Paid</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_registrations)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-person-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                                            No registrations recorded for your college events yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_registrations as $r): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <?php if ($r['registration_type'] === 'team'): ?>
                                                    <div class="fw-bold text-dark"><i class="bi bi-people-fill text-purple me-1"></i> <?= htmlspecialchars((string)($r['team_name'] ?? 'Team')) ?></div>
                                                    <small class="text-muted">Code: <?= htmlspecialchars((string)($r['team_code'] ?? 'N/A')) ?></small>
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= !empty($r['profile_photo']) ? '../uploads/students/' . htmlspecialchars($r['profile_photo']) : 'https://ui-avatars.com/api/?name=' . urlencode($r['student_name']) ?>" class="rounded-circle" width="30" height="30" style="object-fit:cover;">
                                                        <div>
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars((string)($r['student_name'] ?? 'Student')) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars((string)($r['student_email'] ?? '')) ?></small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars((string)$r['event_title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark rounded-pill px-3 py-1 border font-semibold"><?= ucfirst($r['registration_type']) ?></span>
                                            </td>
                                            <td class="fw-bold text-dark">₹<?= number_format((float)$r['amount'], 2) ?></td>
                                            <td>
                                                <?php if (strtolower($r['status']) === 'approved'): ?>
                                                    <span class="status-dot-pill dot-green">Approved</span>
                                                <?php elseif (strtolower($r['status']) === 'pending payment' || strtolower($r['status']) === 'pending'): ?>
                                                    <span class="status-dot-pill dot-amber">Pending</span>
                                                <?php else: ?>
                                                    <span class="status-dot-pill dot-red">Cancelled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($r['registered_at'] ?? 'now')) ?></td>
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
