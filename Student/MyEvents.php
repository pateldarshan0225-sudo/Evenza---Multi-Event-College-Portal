<?php
/**
 * Student/MyEvents.php
 * Registered Competitions & Digital Verification Passes Page for Student Portal
 */
include 'student_auth.php';
include 'connection.php';

// Fetch All Student Registrations
$stmt = $pdo->prepare("
    SELECT r.*, e.title AS event_title, e.description AS event_desc, e.event_date, e.start_time, e.end_time, e.venue, e.event_type, e.dress_code,
           col.name AS hosting_college, col.email AS college_email, col.phone AS college_phone,
           t.team_name, t.team_code,
           COALESCE(p.amount, e.registration_fee, 0) AS amount,
           p.payment_status
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.event_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    LEFT JOIN teams t ON r.team_id = t.team_id
    LEFT JOIN payments p ON r.registration_id = p.registration_id
    WHERE r.student_id = :sid OR r.team_id IN (SELECT team_id FROM team_members WHERE student_id = :sid2)
    ORDER BY r.registration_id DESC
");
$stmt->execute(['sid' => $student_id, 'sid2' => $student_id]);
$registrations = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Events & Digital Passes | Evenza Student Portal</title>

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

        .berun-layout-body { display: flex; gap: 28px; }
        .berun-main-grid { flex-grow: 1; display: flex; flex-direction: column; gap: 24px; }

        .berun-card-panel {
            background: var(--bg-white);
            border-radius: var(--card-radius);
            padding: 28px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .pass-ticket-card {
            background: #ffffff;
            border: 2px solid #14171a;
            border-radius: 28px;
            padding: 28px;
            box-shadow: 6px 6px 0px #14171a;
            transition: all 0.25s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .pass-ticket-card:hover {
            transform: translateY(-4px);
            box-shadow: 8px 8px 0px #ffd13b;
        }

        .pass-status-pill {
            background: #ffd13b;
            color: #14171a;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 14px;
            border-radius: 9999px;
            border: 1.5px solid #14171a;
        }

        @media (max-width: 768px) {
            body { padding: 14px; }
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
                <a href="Dashboard.php" class="d-inline-flex align-items-center text-decoration-none">
                    <img src="../assets/images/evenza-logo.svg" alt="Evenza Logo" height="38" style="height: 38px; width: auto;" />
                </a>

                <div class="ps-2">
                    <h1 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">My Events & Digital Entry Passes</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Manage registered competitions, venue locations, and show digital entry passes</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="../Frontend/events.php" class="btn btn-dark rounded-pill px-4 py-2 text-xs font-bold text-white">
                    <i class="bi bi-search me-1"></i> Browse More Events
                </a>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Student_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <div class="row g-4">
                    <?php if (empty($registrations)): ?>
                        <div class="col-12 text-center py-5 bg-white rounded-5 p-5 border">
                            <i class="bi bi-ticket-perforated fs-1 text-muted opacity-50 d-block mb-3"></i>
                            <h4 class="fw-black text-dark mb-2">No Registered Events Found</h4>
                            <p class="text-muted text-xs mb-4">You have not registered for any college competitions or hackathons yet.</p>
                            <a href="../Frontend/events.php" class="btn btn-warning rounded-pill px-4 py-2.5 text-xs font-bold text-dark border border-dark">
                                Explore Published Competitions &rarr;
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($registrations as $r): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="pass-ticket-card">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <span class="pass-status-pill">
                                                <i class="bi bi-qr-code me-1"></i> #REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?>
                                            </span>
                                            <span class="badge bg-dark text-white rounded-pill px-3 py-1 text-xs font-bold">
                                                <?= ucfirst($r['registration_type']) ?>
                                            </span>
                                        </div>

                                        <h4 class="fw-black text-dark mb-2 fs-5" style="letter-spacing: -0.3px;">
                                            <?= htmlspecialchars((string)$r['event_title']) ?>
                                        </h4>

                                        <div class="p-2.5 bg-light rounded-4 border mb-3 text-xs">
                                            <div class="text-muted mb-1"><i class="bi bi-bank2 text-warning me-1"></i> <strong>Host:</strong> <?= htmlspecialchars((string)$r['hosting_college']) ?></div>
                                            <div class="text-muted mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <strong>Venue:</strong> <?= htmlspecialchars((string)$r['venue']) ?></div>
                                            <div class="text-muted"><i class="bi bi-calendar-event-fill text-primary me-1"></i> <strong>Date:</strong> <?= date('d M Y', strtotime($r['event_date'])) ?></div>
                                        </div>

                                        <?php if (!empty($r['team_name'])): ?>
                                            <div class="p-2 bg-warning-subtle text-dark rounded-3 text-xs mb-3 font-bold border border-warning">
                                                <i class="bi bi-people-fill me-1"></i> Squad: <?= htmlspecialchars((string)$r['team_name']) ?> (Code: <?= htmlspecialchars((string)($r['team_code'] ?? 'N/A')) ?>)
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                                        <div>
                                            <small class="text-muted text-xs d-block">Fee Paid</small>
                                            <strong class="text-dark fs-6 font-black"><?= (float)$r['amount'] > 0 ? '₹' . number_format((float)$r['amount'], 2) : 'Free Entry' ?></strong>
                                        </div>
                                        <span class="badge bg-success text-white rounded-pill px-3 py-1.5 font-bold text-xs">
                                            ● Verified Pass
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
