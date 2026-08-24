<?php
/**
 * Frontend/event_detail.php
 * Event Detail & Student Registration Page
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
        $error = 'You are already registered for this event!';
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
            $flash = 'Successfully registered for ' . htmlspecialchars((string)$event['title']) . '! View your ticket in Student Dashboard.';
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = 'Error completing registration: ' . $ex->getMessage();
        }
    }
}

include 'Header.php';
?>

<div class="container-xl py-5">

    <?php if ($flash): ?>
        <div class="alert alert-success rounded-4 border-0 p-3 mb-4 text-xs font-semibold" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($flash) ?>
            <a href="dashboard.php" class="text-dark font-bold ms-2">Go to My Dashboard &rarr;</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 border-0 p-3 mb-4 text-xs font-semibold" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- LEFT MAIN COLUMN -->
        <div class="col-12 col-lg-8">
            <div class="bg-white rounded-5 p-4 p-md-5 border border-dark border-2 shadow-sm" style="box-shadow: 6px 6px 0px #14171a !important;">
                
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <span class="badge bg-warning text-dark font-bold px-3 py-1.5 rounded-pill text-xs">
                        <?= htmlspecialchars((string)($event['category_name'] ?? 'General')) ?>
                    </span>
                    <span class="badge bg-dark text-white rounded-pill px-3 py-1.5 font-bold text-xs">
                        Hosted by <?= htmlspecialchars((string)($event['college_name'] ?? 'College')) ?>
                    </span>
                </div>

                <h1 class="fw-black text-dark display-6 mb-3"><?= htmlspecialchars((string)$event['title']) ?></h1>

                <div class="p-3 bg-light rounded-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3 text-xs">
                    <div><i class="bi bi-geo-alt me-1 text-danger fs-6"></i> <strong>Venue:</strong> <?= htmlspecialchars((string)$event['venue']) ?></div>
                    <div><i class="bi bi-calendar3 me-1 text-primary fs-6"></i> <strong>Date:</strong> <?= date('d M Y', strtotime($event['event_date'])) ?></div>
                    <div><i class="bi bi-clock me-1 text-success fs-6"></i> <strong>Time:</strong> <?= date('h:i A', strtotime($event['start_time'] ?? '09:00:00')) ?> - <?= date('h:i A', strtotime($event['end_time'] ?? '17:00:00')) ?></div>
                </div>

                <h5 class="fw-bold text-dark mb-2 fs-6 border-bottom pb-2">About the Event</h5>
                <p class="text-secondary text-xs leading-relaxed mb-4" style="white-space: pre-line;">
                    <?= htmlspecialchars((string)$event['description']) ?>
                </p>

                <h5 class="fw-bold text-dark mb-2 fs-6 border-bottom pb-2">Guidelines & Code</h5>
                <div class="row g-3 text-xs text-secondary mb-4">
                    <div class="col-6"><strong>Dress Code:</strong> <?= htmlspecialchars((string)$event['dress_code']) ?></div>
                    <div class="col-6"><strong>Format:</strong> <?= ucfirst($event['event_type']) ?> Registration</div>
                    <?php if ($event['event_type'] === 'team'): ?>
                        <div class="col-12"><strong>Team Size:</strong> Minimum <?= $event['min_team_size'] ?> to Maximum <?= $event['max_team_size'] ?> Members</div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- RIGHT REGISTRATION ACTION COLUMN -->
        <div class="col-12 col-lg-4">
            <div class="bg-white rounded-5 p-4 border border-dark border-2 shadow-sm position-sticky" style="top: 20px; box-shadow: 6px 6px 0px #14171a !important;">
                
                <h5 class="fw-black text-dark mb-3 fs-5">Registration Summary</h5>

                <div class="p-3 bg-light rounded-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted text-xs">Registration Fee:</span>
                        <strong class="text-dark fs-5 fw-black"><?= (float)($event['registration_fee']) > 0 ? '₹' . number_format((float)$event['registration_fee'], 2) : 'Free' ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-xs">
                        <span class="text-muted">Fee Type:</span>
                        <span class="badge bg-light text-dark border"><?= ucfirst($event['fee_type'] ?? 'per_person') ?></span>
                    </div>
                </div>

                <?php if ($is_logged_in && $user_role === 'student'): ?>
                    <form method="POST">
                        <input type="hidden" name="action_register" value="1">
                        
                        <?php if ($event['event_type'] === 'team'): ?>
                            <div class="mb-3">
                                <label class="form-label font-bold text-xs text-uppercase">Team Name *</label>
                                <input type="text" class="form-control rounded-pill text-xs px-3" name="team_name" placeholder="e.g. Code Warriors" required>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid">
                            <button type="submit" class="btn-capsule-dark justify-content-center py-3 fs-6">
                                Confirm & Register <i class="bi bi-check-circle"></i>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center p-3 bg-warning-subtle text-dark rounded-4 mb-3 text-xs">
                        Please sign in as a student to register for this college event.
                    </div>
                    <div class="d-grid">
                        <a href="login.php" class="btn-capsule-dark justify-content-center py-3 fs-6">
                            Login to Register <i class="bi bi-box-arrow-in-right"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top text-xs text-muted">
                    <div class="mb-1"><strong>Organizer Contact:</strong></div>
                    <div><?= htmlspecialchars((string)($event['college_email'] ?? '')) ?></div>
                    <div><?= htmlspecialchars((string)($event['college_phone'] ?? '')) ?></div>
                </div>

            </div>
        </div>
    </div>

</div>

<?php include 'Footer.php'; ?>
