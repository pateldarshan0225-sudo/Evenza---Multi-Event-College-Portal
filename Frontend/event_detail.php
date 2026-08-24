<?php
/**
 * Frontend/event_detail.php
 * Ultra-Premium Event Detail & Student Registration Page for Evenza
 */
$page_title = "Event Details";
include 'connection.php';
include_once 'frontend_auth.php';

$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$event_id) {
    header('Location: events.php');
    exit;
}

// Fetch Event Details
$stmt = $pdo->prepare("
    SELECT e.*, c.name AS category_name, col.name AS college_name, col.email AS college_email, col.phone AS college_phone
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE e.event_id = :id AND e.status = 'published'
    LIMIT 1
");
$stmt->execute(['id' => $event_id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: events.php');
    exit;
}

$flash = null;
$error = null;

// Handle Student Registration Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_register'])) {
    if (!$is_logged_in || $user_role !== 'student') {
        header('Location: login.php');
        exit;
    }

    $student_id = $_SESSION['student_id'];
    $team_name  = trim($_POST['team_name'] ?? '');

    // Check if already registered
    $chk = $pdo->prepare("SELECT registration_id FROM registrations WHERE event_id = :eid AND student_id = :sid LIMIT 1");
    $chk->execute(['eid' => $event_id, 'sid' => $student_id]);
    if ($chk->fetch()) {
        $error = 'You are already registered for this event. Access your verification pass in your Student Dashboard.';
    } else {
        try {
            $pdo->beginTransaction();

            $team_id = null;
            if ($event['event_type'] === 'team') {
                $team_code = 'TEAM-' . rand(100, 999);
                $stmtTeam = $pdo->prepare("INSERT INTO teams (event_id, team_name, leader_id, team_code) VALUES (:eid, :tname, :lid, :tcode)");
                $stmtTeam->execute(['eid' => $event_id, 'tname' => !empty($team_name) ? $team_name : 'Team Alpha', 'lid' => $student_id, 'tcode' => $team_code]);
                $team_id = $pdo->lastInsertId();

                $stmtMem = $pdo->prepare("INSERT INTO team_members (team_id, student_id) VALUES (:tid, :sid)");
                $stmtMem->execute(['tid' => $team_id, 'sid' => $student_id]);
            }

            $stmtReg = $pdo->prepare("
                INSERT INTO registrations (event_id, student_id, team_id, registration_type, status, registered_at)
                VALUES (:eid, :sid, :tid, :rtype, 'Approved', NOW())
            ");
            $stmtReg->execute([
                'eid'   => $event_id,
                'sid'   => $student_id,
                'tid'   => $team_id,
                'rtype' => $event['event_type']
            ]);
            $reg_id = $pdo->lastInsertId();

            if ((float)$event['registration_fee'] > 0) {
                $stmtPay = $pdo->prepare("INSERT INTO payments (registration_id, amount, payment_status, created_at) VALUES (:rid, :amt, 'Paid', NOW())");
                $stmtPay->execute(['rid' => $reg_id, 'amt' => $event['registration_fee']]);
            }

            $pdo->commit();
            $flash = 'Event registration confirmed for ' . htmlspecialchars((string)$event['title']) . '! Access your verification pass in the Student Portal.';
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = 'We could not complete your registration request. Please try again.';
        }
    }
}

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    .event-detail-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 56px 0 88px 0;
        min-height: 600px;
    }

    .detail-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 40px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    }

    .sidebar-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 32px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    }

    .college-host-banner {
        font-size: 13px;
        font-weight: 700;
        color: #14171a;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 12px 18px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<section class="event-detail-canvas">
    <div class="container-xl">

        <?php if ($flash): ?>
            <div class="alert bg-success text-white rounded-4 border-0 p-3 mb-4 d-flex align-items-center justify-content-between text-xs font-semibold shadow-sm">
                <div><i class="bi bi-check-circle-fill fs-5 me-2"></i> <?= htmlspecialchars($flash) ?></div>
                <a href="dashboard.php" class="btn btn-light rounded-pill px-3 py-1 text-dark text-xs font-bold">Go to Student Dashboard &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert bg-danger text-white rounded-4 border-0 p-3 mb-4 d-flex align-items-center gap-2 text-xs font-semibold shadow-sm">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- LEFT MAIN CONTENT COLUMN -->
            <div class="col-12 col-lg-8">
                <div class="detail-card-bespoke">
                    
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs">
                            <?= htmlspecialchars((string)($event['category_name'] ?? 'General')) ?>
                        </span>
                        <span class="badge bg-dark text-white rounded-pill px-3.5 py-1.5 font-bold text-xs">
                            <?= ucfirst($event['event_type']) ?> Competition
                        </span>
                    </div>

                    <h1 class="fw-black text-dark display-5 mb-3" style="letter-spacing: -1px;"><?= htmlspecialchars((string)$event['title']) ?></h1>

                    <!-- FULL COLLEGE HOST NAME BANNER -->
                    <div class="college-host-banner">
                        <i class="bi bi-bank2 text-warning fs-5"></i>
                        <span>Organized & Hosted by <strong class="text-dark"><?= htmlspecialchars((string)($event['college_name'] ?? 'Partner Institution')) ?></strong></span>
                    </div>

                    <div class="p-3.5 bg-light rounded-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3 text-xs border">
                        <div><i class="bi bi-geo-alt-fill text-danger me-1 fs-6"></i> <strong>Venue:</strong> <?= htmlspecialchars((string)$event['venue']) ?></div>
                        <div><i class="bi bi-calendar-event-fill text-primary me-1 fs-6"></i> <strong>Date:</strong> <?= date('d M Y', strtotime($event['event_date'])) ?></div>
                        <div><i class="bi bi-clock-fill text-success me-1 fs-6"></i> <strong>Timings:</strong> <?= date('h:i A', strtotime($event['start_time'] ?? '09:00:00')) ?> - <?= date('h:i A', strtotime($event['end_time'] ?? '17:00:00')) ?></div>
                    </div>

                    <h5 class="fw-black text-dark mb-2 fs-6 border-bottom pb-2">Event Overview & Details</h5>
                    <p class="text-secondary text-xs leading-relaxed mb-4" style="white-space: pre-line;">
                        <?= htmlspecialchars((string)$event['description']) ?>
                    </p>

                    <h5 class="fw-black text-dark mb-2 fs-6 border-bottom pb-2">Participation Guidelines</h5>
                    <div class="row g-3 text-xs text-secondary mb-2">
                        <div class="col-6"><strong>Dress Code:</strong> <?= htmlspecialchars((string)$event['dress_code']) ?></div>
                        <div class="col-6"><strong>Registration Format:</strong> <?= ucfirst($event['event_type']) ?> Entry</div>
                        <?php if ($event['event_type'] === 'team'): ?>
                            <div class="col-12"><strong>Team Size Limits:</strong> Minimum <?= $event['min_team_size'] ?> to Maximum <?= $event['max_team_size'] ?> Members</div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- RIGHT REGISTRATION ACTION COLUMN -->
            <div class="col-12 col-lg-4">
                <div class="sidebar-card-bespoke position-sticky" style="top: 24px;">
                    
                    <h5 class="fw-black text-dark mb-3 fs-5">Registration Summary</h5>

                    <div class="p-3 bg-light rounded-4 mb-4 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted text-xs font-semibold">Registration Fee:</span>
                            <strong class="text-dark fs-4 fw-black"><?= (float)($event['registration_fee']) > 0 ? '₹' . number_format((float)$event['registration_fee'], 2) : 'Free Entry' ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center text-xs">
                            <span class="text-muted font-semibold">Fee Type:</span>
                            <span class="badge bg-white text-dark border px-2.5 py-1 font-bold"><?= ucfirst(str_replace('_', ' ', $event['fee_type'] ?? 'per_person')) ?></span>
                        </div>
                    </div>

                    <?php if ($is_logged_in && $user_role === 'student'): ?>
                        <form method="POST">
                            <input type="hidden" name="action_register" value="1">
                            
                            <?php if ($event['event_type'] === 'team'): ?>
                                <div class="mb-3">
                                    <label class="form-label font-bold text-xs text-uppercase text-dark mb-1">Squad / Team Name *</label>
                                    <input type="text" class="form-control rounded-pill text-xs px-3 py-2" name="team_name" autocomplete="off" placeholder="e.g. Code Warriors" required>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid">
                                <button type="submit" class="btn-capsule-dark justify-content-center py-3 text-xs font-bold">
                                    Confirm Event Registration <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center p-3 bg-warning-subtle text-dark rounded-4 mb-3 text-xs font-semibold border border-warning">
                            Please sign in with your student account to register for this competition.
                        </div>
                        <div class="d-grid">
                            <a href="login.php" class="btn-capsule-dark justify-content-center py-3 text-xs font-bold">
                                Sign In to Register <i class="bi bi-box-arrow-in-right ms-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 pt-3 border-top text-xs text-muted">
                        <div class="mb-1 text-dark font-bold">Host Organizer Support:</div>
                        <div class="text-truncate"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars((string)($event['college_email'] ?? 'N/A')) ?></div>
                        <div><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars((string)($event['college_phone'] ?? 'N/A')) ?></div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</section>

<?php include 'Footer.php'; ?>
