<?php
require_once __DIR__ . '/../includes/event_registration.php';
$payment_join_sql = evenza_payment_join_sql();
$pass_join_sql = evenza_pass_join_sql();
require_once __DIR__ . '/../includes/event_lifecycle.php';
require_once __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();

/**
 * Student/MyEvents.php
 * Registered Competitions & Digital Verification Passes Page for Student Portal
 */
include 'student_auth.php';
require_once __DIR__ . '/connection.php';

// Fetch All Student Registrations
$sid = (int)$student_id;
$res_reg = mysqli_query($conn, "
    SELECT r.*, e.title AS event_title, e.description AS event_desc, e.event_date, e.start_time, e.end_time, e.status AS event_status, e.venue, e.event_type, e.dress_code,
           col.name AS hosting_college, col.email AS college_email, col.phone AS college_phone,
           t.team_name, t.team_code,
           COALESCE(p.amount, 0) AS amount,
           p.payment_status,
           ep.pass_code, ep.is_used, ep.expires_at
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.event_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    LEFT JOIN teams t ON r.team_id = t.team_id
    {$payment_join_sql}
    {$pass_join_sql}
    WHERE r.student_id = $sid OR r.team_id IN (SELECT team_id FROM team_members WHERE student_id = $sid)
    ORDER BY r.registration_id DESC
");
$registrations = [];
if ($res_reg) {
    while ($r = mysqli_fetch_assoc($res_reg)) {
        $registrations[] = $r;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Events & Digital Passes | Evenza Student Portal</title>

    <!-- Google Fonts: Plus Jakarta Sans -->

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/fonts/bootstrap-icons/bootstrap-icons.min.css">

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
    <link rel="stylesheet" href="../assets/css/evenza-premium.css">
    <link rel="stylesheet" href="../assets/css/student-passes.css">
    <script src="../assets/js/tab-auth.js"></script>
    <link rel="stylesheet" href="../assets/css/inline-validation.css">
    <script src="../assets/js/inline-validation.js" defer></script>
</head>

<body class="student-passes-page">

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
                <a href="../Frontend/events.php" class="btn btn-dark btn-capsule-dark px-4 py-2 text-white text-decoration-none d-inline-flex align-items-center gap-2">
                    <i class="bi bi-search me-1"></i> Browse More Events
                </a>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Student_Sidebar.php'; ?>

            <main class="berun-main-grid passes-workspace">
                <section class="passes-intro" aria-label="Your registrations">
                    <div class="passes-intro-icon"><i class="bi bi-ticket-perforated" aria-hidden="true"></i></div>
                    <div class="passes-intro-copy">
                        <span class="passes-eyebrow">YOUR CAMPUS EXPERIENCES</span>
                        <h2>Ready for your next event?</h2>
                        <p>Your event details and entry codes, together in one place.</p>
                    </div>
                    <div class="passes-total"><strong><?= count($registrations) ?></strong><span>Registered <?= count($registrations) === 1 ? 'event' : 'events' ?></span></div>
                </section>

                <div class="passes-section-heading">
                    <div><h2>My registrations</h2><p>Keep your pass code ready when you arrive.</p></div>
                    <?php if ($registrations): ?>
                        <button type="button" class="passes-print" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Print passes</button>
                    <?php endif; ?>
                </div>

                <?php if (!$registrations): ?>
                    <section class="passes-empty">
                        <div class="passes-empty-icon"><i class="bi bi-ticket-perforated" aria-hidden="true"></i></div>
                        <h3>Your next experience is waiting</h3>
                        <p>Explore campus events and register for something you love. Your registrations will appear here.</p>
                        <a href="../Frontend/events.php" class="btn-capsule-dark">Explore events <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </section>
                <?php else: ?>
                    <div class="passes-grid">
                        <?php foreach ($registrations as $r): ?>
                            <?php
                                $eventTimestamp = strtotime((string)$r['event_date']);
                                $hasPass = !empty($r['pass_code']);
                                $passLabel = evenza_pass_status($r);
                                $passState = match ($passLabel) { 'Active pass'=>'issued', 'Checked in'=>'used', 'Expired'=>'expired', 'Cancelled'=>'cancelled', default=>'pending' };
                            ?>
                            <article class="event-entry-ticket" aria-label="<?= htmlspecialchars((string)$r['event_title']) ?> registration">
                                <div class="ticket-main">
                                    <div class="ticket-topline">
                                        <span class="ticket-format"><i class="bi <?= $r['registration_type'] === 'team' ? 'bi-people' : 'bi-person' ?>" aria-hidden="true"></i> <?= htmlspecialchars(ucfirst((string)$r['registration_type'])) ?> entry</span>
                                        <span class="ticket-state ticket-state-<?= $passState ?>"><span aria-hidden="true"></span><?= $passLabel ?></span>
                                    </div>
                                    <div class="ticket-event-heading">
                                        <div class="ticket-date"><span><?= $eventTimestamp ? date('M', $eventTimestamp) : 'DATE' ?></span><strong><?= $eventTimestamp ? date('d', $eventTimestamp) : '&mdash;' ?></strong></div>
                                        <div><h3><?= htmlspecialchars((string)$r['event_title']) ?></h3><small><?= htmlspecialchars(ucfirst(evenza_row_event_status($r))) ?></small><p><?= htmlspecialchars((string)$r['hosting_college']) ?></p></div>
                                    </div>
                                    <dl class="ticket-details">
                                        <div><dt><i class="bi bi-geo-alt" aria-hidden="true"></i> Venue</dt><dd><?= htmlspecialchars((string)$r['venue']) ?></dd></div>
                                        <div><dt><i class="bi bi-clock" aria-hidden="true"></i> Date &amp; time</dt><dd><?= $eventTimestamp ? date('d M Y', $eventTimestamp) : 'To be announced' ?><?php if (!empty($r['start_time'])): ?> <span>&middot; <?= date('g:i A', strtotime((string)$r['start_time'])) ?></span><?php endif; ?></dd></div>
                                    </dl>
                                    <?php if (!empty($r['team_name'])): ?>
                                        <div class="ticket-team"><i class="bi bi-people" aria-hidden="true"></i><div><span>Your team</span><strong><?= htmlspecialchars((string)$r['team_name']) ?></strong></div><code><?= htmlspecialchars((string)($r['team_code'] ?? '')) ?></code></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-stub">
                                    <div class="ticket-pass-code"><i class="bi bi-ticket-detailed" aria-hidden="true"></i><div><span><?= $hasPass ? 'ENTRY PASS CODE' : 'REGISTRATION ID' ?></span><code><?= htmlspecialchars((string)($r['pass_code'] ?: ('REG-' . str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT)))) ?></code></div></div>
                                    <div class="ticket-fee"><span>Amount paid</span><strong><?= (float)$r['amount'] > 0 ? '₹' . number_format((float)$r['amount'], 2) : 'Free entry' ?></strong></div>
                                </div>
                                <a class="ticket-details-link" href="../Frontend/event_detail.php?id=<?= (int)$r['event_id'] ?>">View event details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
<?php include __DIR__ . '/../includes/event_clock.php'; ?>
</body>
</html>
