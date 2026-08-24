<?php
/**
 * Student/Dashboard.php
 * Ultra-Premium Executive Dashboard for Student Portal
 */
include 'student_auth.php';
include 'connection.php';

// Fetch Full Student Profile & College Info
$stmt = $pdo->prepare("
    SELECT s.*, c.name AS college_name 
    FROM students s 
    LEFT JOIN colleges c ON s.college_id = c.college_id 
    WHERE s.student_id = :sid 
    LIMIT 1
");
$stmt->execute(['sid' => $student_id]);
$student = $stmt->fetch();

// 1. Total Registered Events
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total 
    FROM registrations r 
    WHERE r.student_id = :sid OR r.team_id IN (SELECT team_id FROM team_members WHERE student_id = :sid2)
");
$stmt->execute(['sid' => $student_id, 'sid2' => $student_id]);
$total_registrations = (int)($stmt->fetch()['total'] ?? 0);

// 2. Total Approved Entry Passes
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total 
    FROM registrations r 
    WHERE (r.student_id = :sid OR r.team_id IN (SELECT team_id FROM team_members WHERE student_id = :sid2))
    AND LOWER(r.status) = 'approved'
");
$stmt->execute(['sid' => $student_id, 'sid2' => $student_id]);
$total_approved_passes = (int)($stmt->fetch()['total'] ?? 0);

// 3. Total Teams Joined/Led
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT team_id) AS total 
    FROM team_members 
    WHERE student_id = :sid
");
$stmt->execute(['sid' => $student_id]);
$total_teams = (int)($stmt->fetch()['total'] ?? 0);

// 4. Total Fees Paid
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(p.amount), SUM(e.registration_fee), 0) AS total 
    FROM registrations r 
    INNER JOIN events e ON r.event_id = e.event_id 
    LEFT JOIN payments p ON r.registration_id = p.registration_id 
    WHERE (r.student_id = :sid OR r.team_id IN (SELECT team_id FROM team_members WHERE student_id = :sid2))
    AND (LOWER(r.status) = 'approved' OR LOWER(p.payment_status) = 'paid')
");
$stmt->execute(['sid' => $student_id, 'sid2' => $student_id]);
$total_paid = (float)($stmt->fetch()['total'] ?? 0.0);

// Fetch Recent Registered Events List
$stmt = $pdo->prepare("
    SELECT r.*, e.title AS event_title, e.event_date, e.start_time, e.venue, e.event_type,
           col.name AS hosting_college,
           COALESCE(p.amount, e.registration_fee, 0) AS amount,
           p.payment_status
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.event_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    LEFT JOIN payments p ON r.registration_id = p.registration_id
    WHERE r.student_id = :sid OR r.team_id IN (SELECT team_id FROM team_members WHERE student_id = :sid2)
    ORDER BY r.registration_id DESC
    LIMIT 6
");
$stmt->execute(['sid' => $student_id, 'sid2' => $student_id]);
$recent_registrations = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Portal Dashboard | Evenza</title>

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

        /* Top Header Bar */
        .berun-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            width: 100%;
        }

        .berun-logo-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--color-text-dark);
        }

        .berun-logo-dots {
            width: 32px;
            height: 32px;
            background: var(--bg-dark);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }
        .berun-logo-dots-top { display: flex; gap: 3px; }
        .berun-dot { width: 5px; height: 5px; background-color: var(--color-yellow); border-radius: 50%; }

        .berun-logo-text { font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
        .berun-logo-text span { color: #888; font-weight: 600; }

        .berun-header-tag {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: var(--color-text-dark);
            font-size: 12px;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-visit-website-luxury {
            background: #ffffff;
            color: #14171a !important;
            border: 2px solid #14171a;
            border-radius: 9999px;
            padding: 9px 20px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 3px 3px 0px #14171a;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            white-space: nowrap;
        }

        .btn-visit-website-luxury:hover {
            background: #ffd13b;
            color: #14171a !important;
            transform: translateY(-2px);
            box-shadow: 5px 5px 0px #14171a;
        }

        .berun-btn-dark {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        /* Action Shortcut Pills */
        .action-shortcut-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 18px 20px;
            text-decoration: none;
            color: #111827;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .action-shortcut-card:hover {
            border-color: #14171a;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.05);
            color: #14171a;
        }

        .action-shortcut-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #ffd13b;
            color: #14171a;
            border: 1.5px solid #14171a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
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
                <a href="Dashboard.php" class="d-inline-flex align-items-center text-decoration-none">
                    <img src="../assets/images/evenza-logo.svg" alt="Evenza Logo" height="38" style="height: 38px; width: auto;" />
                </a>

                <div class="d-flex align-items-center gap-2">
                    <span class="berun-header-tag"><i class="bi bi-person-badge-fill me-1 text-warning"></i> Student Portal</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="../Frontend/index.php" class="btn-visit-website-luxury">
                    <i class="bi bi-globe2 text-warning"></i> Visit Main Website <i class="bi bi-arrow-up-right text-muted opacity-60"></i>
                </a>
                <a href="../Frontend/events.php" class="berun-btn-dark">
                    <i class="bi bi-search"></i> Explore Competitions
                </a>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Student_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <!-- HERO WELCOME BANNER -->
                <div class="hero-welcome-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['name']) ?>&background=ffd13b&color=14171a&bold=true" 
                                 alt="<?= htmlspecialchars((string)$student['name']) ?>" 
                                 class="rounded-circle border border-2 border-warning shadow-sm flex-shrink-0" 
                                 width="64" 
                                 height="64" />
                            <div>
                                <span class="text-xs text-uppercase font-bold tracking-widest text-warning opacity-90"><i class="bi bi-mortarboard-fill me-1"></i> Student Competitor Command</span>
                                <h1 class="hero-college-name mt-1"><?= htmlspecialchars((string)$student['name']) ?></h1>
                                <div class="hero-univ-pill">
                                    <i class="bi bi-building"></i> Enrolled at <?= htmlspecialchars((string)($student['college_name'] ?? 'Partner Institution')) ?> • Roll: <?= htmlspecialchars((string)($student['enrollment_no'] ?? 'N/A')) ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end d-none d-md-block">
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 text-xs font-bold shadow-sm">
                                <i class="bi bi-check-circle-fill text-success me-1"></i> Account Active & Verified
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 4 EXECUTIVE KPI STAT CARDS -->
                <div class="row g-3">
                    <!-- Events Enrolled -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Events Enrolled</span>
                                <div class="exec-icon-pod pod-indigo">
                                    <i class="bi bi-ticket-perforated-fill"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val"><?= number_format($total_registrations) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-indigo">
                                    <i class="bi bi-calendar-event me-1"></i> Total Sign-ups
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Approved Entry Passes -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Verified Entry Passes</span>
                                <div class="exec-icon-pod pod-emerald">
                                    <i class="bi bi-patch-check-fill"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val"><?= number_format($total_approved_passes) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-success">
                                    <i class="bi bi-qr-code-scan me-1"></i> Venue Passes Active
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Teams Joined -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Squad Teams</span>
                                <div class="exec-icon-pod pod-purple">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val"><?= number_format($total_teams) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-purple">
                                    <i class="bi bi-person-fill-add me-1"></i> Team Registrations
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Fees Paid -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="exec-stat-card">
                            <div class="exec-card-header">
                                <span class="exec-stat-label">Total Fees Paid</span>
                                <div class="exec-icon-pod pod-amber">
                                    <i class="bi bi-currency-rupee"></i>
                                </div>
                            </div>
                            <div class="exec-stat-val">₹<?= number_format($total_paid, 2) ?></div>
                            <div>
                                <span class="exec-trend-tag trend-amber">
                                    <i class="bi bi-shield-check me-1"></i> Confirmed Receipts
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 QUICK ACTION SHORTCUT CARDS -->
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-md-3">
                        <a href="../Frontend/events.php" class="action-shortcut-card">
                            <div>
                                <div class="fw-black text-dark text-xs mb-0">Browse Events</div>
                                <small class="text-muted" style="font-size: 11px;">Find new competitions</small>
                            </div>
                            <div class="action-shortcut-icon"><i class="bi bi-search"></i></div>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                        <a href="MyEvents.php" class="action-shortcut-card">
                            <div>
                                <div class="fw-black text-dark text-xs mb-0">View Entry Passes</div>
                                <small class="text-muted" style="font-size: 11px;">Digital ticket passes</small>
                            </div>
                            <div class="action-shortcut-icon"><i class="bi bi-qr-code"></i></div>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                        <a href="MyTeams.php" class="action-shortcut-card">
                            <div>
                                <div class="fw-black text-dark text-xs mb-0">My Squad Teams</div>
                                <small class="text-muted" style="font-size: 11px;">Team codes & rosters</small>
                            </div>
                            <div class="action-shortcut-icon"><i class="bi bi-people"></i></div>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                        <a href="Profile.php" class="action-shortcut-card">
                            <div>
                                <div class="fw-black text-dark text-xs mb-0">Account Profile</div>
                                <small class="text-muted" style="font-size: 11px;">Update details & pass</small>
                            </div>
                            <div class="action-shortcut-icon"><i class="bi bi-person-gear"></i></div>
                        </a>
                    </div>
                </div>

                <!-- RECENT REGISTERED EVENTS TABLE -->
                <div class="berun-card-panel">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <div>
                            <h3 class="berun-panel-title"><i class="bi bi-ticket-perforated-fill text-warning me-2"></i> Registered Competitions & Passes</h3>
                            <p class="berun-panel-sub">Recent event registrations and venue entry status</p>
                        </div>
                        <a href="MyEvents.php" class="btn btn-outline-dark rounded-pill px-4 py-2 text-xs font-bold">
                            View All Passes &rarr;
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-xs">
                            <thead>
                                <tr class="text-muted font-bold text-uppercase border-bottom" style="font-size: 11px; letter-spacing: 0.6px;">
                                    <th class="ps-3 py-3">Registration ID</th>
                                    <th class="py-3">Event Title & Host</th>
                                    <th class="py-3">Format</th>
                                    <th class="py-3">Event Date</th>
                                    <th class="py-3">Fee Amount</th>
                                    <th class="py-3 text-end pe-3">Status Pass</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_registrations)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                                            You have not registered for any events or competitions yet. <br>
                                            <a href="../Frontend/events.php" class="text-dark font-bold text-decoration-underline mt-2 d-inline-block">Explore published events across partner institutions &rarr;</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_registrations as $r): ?>
                                        <tr>
                                            <td class="ps-3 font-semibold text-muted">#REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars((string)$r['event_title']) ?></div>
                                                <small class="text-muted"><i class="bi bi-bank2 me-1"></i> <?= htmlspecialchars((string)$r['hosting_college']) ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark rounded-pill px-3 py-1 border"><?= ucfirst($r['registration_type']) ?></span></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($r['event_date'])) ?></td>
                                            <td class="fw-bold text-dark"><?= (float)$r['amount'] > 0 ? '₹' . number_format((float)$r['amount'], 2) : 'Free Entry' ?></td>
                                            <td class="text-end pe-3">
                                                <?php if (strtolower($r['status']) === 'approved'): ?>
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-3.5 py-1.5 font-bold">● Pass Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3.5 py-1.5 font-bold">● <?= ucfirst($r['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
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
