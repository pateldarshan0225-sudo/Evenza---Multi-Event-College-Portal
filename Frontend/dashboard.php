<?php
/**
 * Frontend/dashboard.php
 * Student Portal Dashboard for Registered Events & Tickets
 */
$page_title = "Student Dashboard";
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

<div class="container-xl py-5">

    <!-- WELCOME HERO BANNER -->
    <div class="bg-dark text-white rounded-5 p-4 p-md-5 mb-4 shadow-lg position-relative overflow-hidden" style="background: linear-gradient(135deg, #14171a 0%, #252b31 100%) !important;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 position-relative z-1">
            <div>
                <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Student Portal</span>
                <h1 class="fw-black display-6 mb-1 text-white">Welcome back, <?= htmlspecialchars((string)$student['name']) ?>!</h1>
                <p class="text-secondary text-xs mb-0">
                    Enrolled at <strong><?= htmlspecialchars((string)($student['college_name'] ?? 'College')) ?></strong> (Roll: <?= htmlspecialchars((string)($student['enrollment_no'] ?? 'N/A')) ?>)
                </p>
            </div>
            <div>
                <a href="events.php" class="btn btn-warning rounded-pill px-4 py-2 text-xs font-bold text-dark">
                    <i class="bi bi-search me-1"></i> Discover Events
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- LEFT COLUMN: MY REGISTRATIONS -->
        <div class="col-12 col-lg-8">
            <div class="bg-white rounded-5 p-4 border border-dark border-2 shadow-sm" style="box-shadow: 4px 4px 0px #14171a !important;">
                <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                    <div>
                        <h4 class="fw-black text-dark mb-0 fs-5"><i class="bi bi-ticket-perforated text-warning me-2"></i> My Registered Events</h4>
                        <small class="text-muted text-xs">Total <?= count($my_registrations) ?> event sign-ups recorded</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-xs">
                        <thead>
                            <tr class="text-muted font-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.6px;">
                                <th class="ps-3">Reg ID</th>
                                <th>Event Title</th>
                                <th>Format</th>
                                <th>Event Date</th>
                                <th>Fee</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($my_registrations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                                        You haven't registered for any events yet. <br>
                                        <a href="events.php" class="text-dark font-bold">Browse upcoming events now &rarr;</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($my_registrations as $r): ?>
                                    <tr>
                                        <td class="ps-3 font-semibold text-muted">#REG-<?= str_pad((string)$r['registration_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars((string)$r['event_title']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars((string)$r['hosting_college']) ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark rounded-pill px-3 py-1 border"><?= ucfirst($r['registration_type']) ?></span></td>
                                        <td class="text-muted"><?= date('d M Y', strtotime($r['event_date'])) ?></td>
                                        <td class="fw-bold text-dark">₹<?= number_format((float)$r['amount'], 2) ?></td>
                                        <td>
                                            <?php if (strtolower($r['status']) === 'approved'): ?>
                                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 font-bold">● Approved</span>
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
            <div class="bg-white rounded-5 p-4 border border-dark border-2 shadow-sm" style="box-shadow: 4px 4px 0px #14171a !important;">
                <h5 class="fw-black text-dark mb-3 border-bottom pb-2 fs-6"><i class="bi bi-person-badge text-primary me-2"></i> Student Details</h5>

                <div class="d-flex align-items-center gap-3 mb-4">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['name']) ?>&background=1c2024&color=ffd13b&bold=true" class="rounded-circle border" width="54" height="54">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars((string)$student['name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars((string)$student['email']) ?></small>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2 text-xs">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Enrollment No:</span>
                        <strong class="text-dark"><?= htmlspecialchars((string)($student['enrollment_no'] ?? 'N/A')) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">College:</span>
                        <strong class="text-dark"><?= htmlspecialchars((string)($student['college_name'] ?? 'N/A')) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Semester:</span>
                        <strong class="text-dark">Semester <?= htmlspecialchars((string)($student['semester'] ?? '1')) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Phone:</span>
                        <strong class="text-dark"><?= htmlspecialchars((string)($student['phone'] ?? 'N/A')) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Account Status:</span>
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 font-bold">Active Student</span>
                    </div>
                </div>

                <div class="pt-3 mt-3 border-top d-grid">
                    <a href="logout.php" class="btn btn-outline-danger rounded-pill py-2 text-xs font-bold">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout Account
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'Footer.php'; ?>
