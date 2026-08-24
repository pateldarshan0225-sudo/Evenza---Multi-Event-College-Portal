<?php
/**
 * Frontend/dashboard.php
 * Ultra-Premium Student Portal Dashboard for Evenza
 */
$page_title = "Student Portal Dashboard";
include 'connection.php';
include_once 'frontend_auth.php';

// Auth Guard: Only Logged-in Students
if (!$is_logged_in || $user_role !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch Student Profile
$stmt = $pdo->prepare("
    SELECT s.*, c.name AS college_name 
    FROM students s 
    LEFT JOIN colleges c ON s.college_id = c.college_id 
    WHERE s.student_id = :sid 
    LIMIT 1
");
$stmt->execute(['sid' => $student_id]);
$student = $stmt->fetch();

// Fetch Student's Registrations
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
");
$stmt->execute(['sid' => $student_id, 'sid2' => $student_id]);
$my_registrations = $stmt->fetchAll();

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    .dashboard-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 56px 0 88px 0;
        min-height: 600px;
    }

    .dashboard-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 32px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    }

    .welcome-hero-banner {
        background: linear-gradient(135deg, #14171a 0%, #252b31 100%);
        border-radius: 32px;
        padding: 40px;
        border: 2px solid #14171a;
        box-shadow: 6px 6px 0px #ffd13b;
    }
</style>

<section class="dashboard-canvas">
    <div class="container-xl">

        <!-- WELCOME HERO BANNER -->
        <div class="welcome-hero-banner text-white mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs mb-2">Student Portal</span>
                    <h1 class="fw-black display-6 mb-1 text-white" style="letter-spacing: -1px;">Welcome back, <?= htmlspecialchars((string)$student['name']) ?>!</h1>
                    <p class="text-white-50 text-xs mb-0">
                        Enrolled at <strong class="text-white"><?= htmlspecialchars((string)($student['college_name'] ?? 'Partner Institution')) ?></strong> • Roll No: <strong><?= htmlspecialchars((string)($student['enrollment_no'] ?? 'N/A')) ?></strong>
                    </p>
                </div>
                <div>
                    <a href="events.php" class="btn btn-warning rounded-pill px-4 py-2.5 text-xs font-bold text-dark shadow-sm">
                        <i class="bi bi-search me-1"></i> Explore Competitions
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- LEFT COLUMN: MY REGISTRATIONS -->
            <div class="col-12 col-lg-8">
                <div class="dashboard-card-bespoke">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                        <div>
                            <h4 class="fw-black text-dark mb-0 fs-5"><i class="bi bi-ticket-perforated-fill text-warning me-2"></i> My Registered Events</h4>
                            <small class="text-muted text-xs"><?= count($my_registrations) ?> event registration records found</small>
                        </div>
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
                                    <th class="py-3 text-end pe-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($my_registrations)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                                            You have not registered for any events or competitions yet. <br>
                                            <a href="events.php" class="text-dark font-bold text-decoration-underline mt-2 d-inline-block">Explore published events across partner institutions &rarr;</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($my_registrations as $r): ?>
                                        <tr>
                                            <td class="ps-3 font-semibold text-muted">#REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars((string)$r['event_title']) ?></div>
                                                <small class="text-muted"><i class="bi bi-bank2 me-1"></i> <?= htmlspecialchars((string)$r['hosting_college']) ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark rounded-pill px-3 py-1 border"><?= ucfirst($r['registration_type']) ?></span></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($r['event_date'])) ?></td>
                                            <td class="fw-bold text-dark"><?= (float)$r['amount'] > 0 ? '₹' . number_format((float)$r['amount'], 2) : 'Free Entry' ?></td>
                                            <td class="text-end pe-3">
                                                <?php if (strtolower($r['status']) === 'approved'): ?>
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 font-bold">● Approved Pass</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1 font-bold">● <?= ucfirst($r['status']) ?></span>
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

            <!-- RIGHT COLUMN: STUDENT PROFILE SUMMARY -->
            <div class="col-12 col-lg-4">
                <div class="dashboard-card-bespoke">
                    <h5 class="fw-black text-dark mb-3 border-bottom pb-2 fs-6"><i class="bi bi-person-badge-fill text-primary me-2"></i> Student Account Details</h5>

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['name']) ?>&background=14171a&color=ffd13b&bold=true" class="rounded-circle border" width="54" height="54">
                        <div class="overflow-hidden">
                            <h6 class="fw-bold text-dark mb-0 text-truncate"><?= htmlspecialchars((string)$student['name']) ?></h6>
                            <small class="text-muted text-truncate d-block"><?= htmlspecialchars((string)$student['email']) ?></small>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2 text-xs">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Enrollment Roll No:</span>
                            <strong class="text-dark"><?= htmlspecialchars((string)($student['enrollment_no'] ?? 'N/A')) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Partner Institution:</span>
                            <strong class="text-dark text-truncate ms-2" style="max-width: 180px;"><?= htmlspecialchars((string)($student['college_name'] ?? 'N/A')) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Current Semester:</span>
                            <strong class="text-dark">Semester <?= htmlspecialchars((string)($student['semester'] ?? '1')) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Contact Phone:</span>
                            <strong class="text-dark"><?= htmlspecialchars((string)($student['phone'] ?? 'N/A')) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-muted">Verification Status:</span>
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 font-bold">● Active Verified</span>
                        </div>
                    </div>

                    <div class="pt-3 mt-3 border-top d-grid">
                        <a href="logout.php" class="btn btn-outline-danger rounded-pill py-2.5 text-xs font-bold">
                            <i class="bi bi-box-arrow-right me-1"></i> Sign Out Account
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php include 'Footer.php'; ?>
