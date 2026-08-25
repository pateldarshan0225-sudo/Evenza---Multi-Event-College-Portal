<?php
/**
 * Frontend/event_detail.php
 * Ultra-Premium Bespoke Event Detail & Student Registration Portal for Evenza
 */
$page_title = "Event Details";
include 'connection.php';
require_once __DIR__ . '/../includes/phone_helper.php';
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

    $student_id = $user_id;
    $team_name  = trim($_POST['team_name'] ?? '');

    // Check if already registered
    $chk = $pdo->prepare("SELECT registration_id FROM registrations WHERE event_id = :eid AND student_id = :sid LIMIT 1");
    $chk->execute(['eid' => $event_id, 'sid' => $student_id]);
    if ($chk->fetch()) {
        $error = 'You are already registered for this event. Access your pass in your Student Dashboard.';
    } else {
        try {
            $pdo->beginTransaction();

            $team_id = null;
            if ($event['event_type'] === 'team') {
                $team_code = 'TEAM-' . rand(100, 999);
                $stmtTeam = $pdo->prepare("INSERT INTO teams (event_id, team_name, leader_id, team_code) VALUES (:eid, :tname, :lid, :tcode)");
                $stmtTeam->execute([
                    'eid'   => $event_id,
                    'tname' => !empty($team_name) ? $team_name : 'Team Alpha',
                    'lid'   => $student_id,
                    'tcode' => $team_code
                ]);
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
            $flash = 'Event registration confirmed for ' . htmlspecialchars((string)$event['title']) . '! Your pass is now active in your Student Portal.';
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
        --ez-canvas-bg: #f6f5f1;
        --ez-card-bg: #ffffff;
        --ez-dark-card: #15181c;
        --ez-text-dark: #111418;
        --ez-text-muted: #6b7280;
        --ez-accent-gold: #ffd13b;
        --ez-accent-amber: #f59e0b;
        --ez-border-light: #e5e2da;
        --ez-shadow-soft: 0 12px 32px rgba(0, 0, 0, 0.04);
        --ez-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.08);
    }

    body {
        background-color: var(--ez-canvas-bg) !important;
    }

    .event-detail-section {
        padding: 40px 0 80px 0;
    }

    /* Hero Banner Card */
    .ez-hero-banner {
        background: linear-gradient(135deg, #181c20 0%, #111417 100%);
        border-radius: 28px;
        padding: 40px;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 16px 40px rgba(17, 20, 23, 0.2);
        margin-bottom: 32px;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .ez-hero-banner::before {
        content: '';
        position: absolute;
        top: -100px;
        right: -100px;
        width: 320px;
        height: 320px;
        background: radial-gradient(circle, rgba(255, 209, 59, 0.12) 0%, rgba(0,0,0,0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .ez-badge-glass-gold {
        background: rgba(255, 209, 59, 0.15);
        color: #ffd13b;
        border: 1px solid rgba(255, 209, 59, 0.3);
        padding: 6px 16px;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.3px;
    }

    .ez-badge-glass-white {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.18);
        padding: 6px 16px;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.3px;
    }

    .ez-event-hero-title {
        font-size: 36px;
        font-weight: 800;
        letter-spacing: -0.8px;
        line-height: 1.25;
        margin-top: 16px;
        margin-bottom: 20px;
        color: #ffffff;
    }

    .ez-host-strip {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background: rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        padding: 10px 20px;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 600;
        color: #e2e8f0;
    }

    .ez-host-icon {
        width: 28px;
        height: 28px;
        background: var(--ez-accent-gold);
        color: #111418;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    /* Micro-Highlights Grid Cards */
    .ez-highlight-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 20px;
        border: 1px solid var(--ez-border-light);
        box-shadow: var(--ez-shadow-soft);
        transition: all 0.25s ease;
        height: 100%;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .ez-highlight-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--ez-shadow-hover);
        border-color: #d8d4ca;
    }

    .ez-highlight-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .ez-icon-gold { background: #fef9c3; color: #b45309; }
    .ez-icon-blue { background: #dbeafe; color: #1d4ed8; }
    .ez-icon-purple { background: #f3e8ff; color: #7e22ce; }
    .ez-icon-emerald { background: #d1fae5; color: #047857; }

    .ez-highlight-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--ez-text-muted);
        margin-bottom: 2px;
    }

    .ez-highlight-value {
        font-size: 14px;
        font-weight: 700;
        color: var(--ez-text-dark);
        line-height: 1.3;
    }

    /* Main Content Card */
    .ez-content-card {
        background: #ffffff;
        border-radius: 24px;
        padding: 32px;
        border: 1px solid var(--ez-border-light);
        box-shadow: var(--ez-shadow-soft);
        margin-bottom: 32px;
    }

    .ez-section-heading {
        font-size: 18px;
        font-weight: 800;
        color: var(--ez-text-dark);
        letter-spacing: -0.3px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ez-section-heading::before {
        content: '';
        width: 4px;
        height: 18px;
        background: var(--ez-accent-gold);
        border-radius: 4px;
    }

    .ez-description-text {
        font-size: 14px;
        line-height: 1.8;
        color: #4b5563;
        white-space: pre-line;
    }

    .ez-guideline-box {
        background: #f9f8f5;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #eeebe3;
    }

    .ez-spec-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px dashed #e5e2da;
        font-size: 13px;
    }

    .ez-spec-item:last-child {
        border-bottom: none;
    }

    /* Sticky Registration Sidebar Card */
    .ez-sidebar-sticky {
        position: sticky;
        top: 24px;
    }

    .ez-register-card {
        background: #ffffff;
        border-radius: 24px;
        padding: 32px;
        border: 1px solid var(--ez-border-light);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06);
    }

    .ez-price-hero {
        background: #f9f8f5;
        border-radius: 20px;
        padding: 24px;
        border: 1px solid #ece8de;
        margin-bottom: 24px;
        text-align: center;
    }

    .ez-price-tag {
        font-size: 38px;
        font-weight: 900;
        color: var(--ez-text-dark);
        letter-spacing: -1px;
    }

    .ez-cta-button {
        background: var(--ez-text-dark);
        color: #ffffff;
        border: none;
        border-radius: 9999px;
        padding: 16px 28px;
        font-size: 14px;
        font-weight: 700;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        cursor: pointer;
        transition: all 0.25s ease;
        box-shadow: 0 8px 20px rgba(17, 20, 24, 0.2);
        text-decoration: none;
    }

    .ez-cta-button:hover {
        background: #2b3036;
        color: var(--ez-accent-gold) !important;
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(17, 20, 24, 0.3);
    }

    .ez-user-badge {
        background: #f3f4f6;
        border-radius: 14px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        border: 1px solid #e5e7eb;
    }

    .ez-support-box {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--ez-border-light);
    }

    .ez-support-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: #f9f8f5;
        border-radius: 12px;
        color: var(--ez-text-dark);
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 8px;
        transition: all 0.2s ease;
        border: 1px solid #ede9e0;
    }

    .ez-support-link:hover {
        background: #ffffff;
        border-color: var(--ez-text-dark);
        color: var(--ez-text-dark);
    }

    @media (max-width: 768px) {
        .ez-hero-banner { padding: 24px; }
        .ez-event-hero-title { font-size: 26px; }
        .ez-register-card { padding: 24px; }
    }
</style>

<section class="event-detail-section">
    <div class="container-xl">

        <!-- Flash & Alert Banners -->
        <?php if ($flash): ?>
            <div class="alert bg-success text-white rounded-4 border-0 p-3 mb-4 d-flex align-items-center justify-content-between shadow-sm">
                <div class="d-flex align-items-center text-xs font-semibold">
                    <i class="bi bi-check-circle-fill fs-5 me-2"></i> <?= htmlspecialchars($flash) ?>
                </div>
                <a href="dashboard.php" class="btn btn-light rounded-pill px-3 py-1 text-dark text-xs font-bold shadow-sm">Go to Dashboard &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert bg-danger text-white rounded-4 border-0 p-3 mb-4 d-flex align-items-center text-xs font-semibold shadow-sm">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Hero Immersion Banner -->
        <div class="ez-hero-banner">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <span class="ez-badge-glass-gold">
                    <i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars((string)($event['category_name'] ?? 'General Event')) ?>
                </span>
                <span class="ez-badge-glass-white">
                    <i class="bi bi-trophy-fill me-1"></i> <?= ucfirst($event['event_type']) ?> Competition
                </span>
            </div>

            <h1 class="ez-event-hero-title"><?= htmlspecialchars((string)$event['title']) ?></h1>

            <div class="ez-host-strip">
                <div class="ez-host-icon">
                    <i class="bi bi-bank2"></i>
                </div>
                <span>Organized by <strong><?= htmlspecialchars((string)($event['college_name'] ?? 'Partner Institution')) ?></strong></span>
                <i class="bi bi-patch-check-fill text-warning ms-1" title="Verified Host"></i>
            </div>
        </div>

        <!-- Micro-Highlights 4-Card Grid -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="ez-highlight-card">
                    <div class="ez-highlight-icon ez-icon-gold">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div>
                        <div class="ez-highlight-label">Event Date</div>
                        <div class="ez-highlight-value"><?= date('d M Y', strtotime($event['event_date'])) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <div class="ez-highlight-card">
                    <div class="ez-highlight-icon ez-icon-blue">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="ez-highlight-label">Timings</div>
                        <div class="ez-highlight-value"><?= date('h:i A', strtotime($event['start_time'] ?? '09:00:00')) ?> - <?= date('h:i A', strtotime($event['end_time'] ?? '17:00:00')) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <div class="ez-highlight-card">
                    <div class="ez-highlight-icon ez-icon-purple">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div>
                        <div class="ez-highlight-label">Venue Location</div>
                        <div class="ez-highlight-value text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars((string)$event['venue']) ?>">
                            <?= htmlspecialchars((string)$event['venue']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <div class="ez-highlight-card">
                    <div class="ez-highlight-icon ez-icon-emerald">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="ez-highlight-label">Entry Format</div>
                        <div class="ez-highlight-value">
                            <?= $event['event_type'] === 'team' ? $event['min_team_size'] . '-' . $event['max_team_size'] . ' Members/Team' : 'Solo Participation' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- LEFT COLUMN: EVENT DETAILS & GUIDELINES -->
            <div class="col-12 col-lg-8">
                
                <!-- Main Description Card -->
                <div class="ez-content-card">
                    <h3 class="ez-section-heading">Event Overview & Details</h3>
                    <div class="ez-description-text mb-4">
                        <?= htmlspecialchars((string)$event['description']) ?>
                    </div>

                    <h4 class="ez-section-heading mt-4">Participation & Competition Guidelines</h4>
                    <div class="ez-guideline-box">
                        <div class="ez-spec-item">
                            <span class="text-muted font-semibold"><i class="bi bi-person-workspace me-2 text-dark"></i> Dress Code Requirement</span>
                            <span class="fw-bold text-dark"><?= htmlspecialchars((string)$event['dress_code']) ?></span>
                        </div>
                        <div class="ez-spec-item">
                            <span class="text-muted font-semibold"><i class="bi bi-diagram-3 me-2 text-dark"></i> Registration Format</span>
                            <span class="fw-bold text-dark"><?= ucfirst($event['event_type']) ?> Entry</span>
                        </div>
                        <?php if ($event['event_type'] === 'team'): ?>
                            <div class="ez-spec-item">
                                <span class="text-muted font-semibold"><i class="bi bi-people-fill me-2 text-dark"></i> Team Constraints</span>
                                <span class="fw-bold text-dark">Min <?= $event['min_team_size'] ?> & Max <?= $event['max_team_size'] ?> Members</span>
                            </div>
                        <?php endif; ?>
                        <div class="ez-spec-item">
                            <span class="text-muted font-semibold"><i class="bi bi-hourglass-split me-2 text-dark"></i> Registration Deadline</span>
                            <span class="fw-bold text-danger"><?= !empty($event['registration_deadline']) ? date('d M Y, h:i A', strtotime($event['registration_deadline'])) : 'Open until slots full' ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: STICKY REGISTRATION SIDEBAR -->
            <div class="col-12 col-lg-4">
                <div class="ez-sidebar-sticky">
                    <div class="ez-register-card">
                        
                        <h4 class="fw-black text-dark mb-1 fs-5">Registration Pass</h4>
                        <p class="text-muted text-xs mb-3">Secure your entry pass for this event</p>

                        <!-- Price Hero Box -->
                        <div class="ez-price-hero">
                            <div class="text-xs text-uppercase font-bold text-muted mb-1">Registration Fee</div>
                            <div class="ez-price-tag">
                                <?= (float)($event['registration_fee']) > 0 ? '₹' . number_format((float)$event['registration_fee'], 2) : 'FREE' ?>
                            </div>
                            <span class="badge bg-white text-dark border px-3 py-1 font-bold rounded-pill text-xs mt-2 shadow-sm">
                                <?= ucfirst(str_replace('_', ' ', $event['fee_type'] ?? 'per_person')) ?>
                            </span>
                        </div>

                        <?php if ($is_logged_in && $user_role === 'student'): ?>
                            <!-- Logged In Student Indicator -->
                            <div class="ez-user-badge">
                                <div class="bg-dark text-warning rounded-circle d-flex align-items-center justify-content-center fw-bold text-xs" style="width:32px; height:32px;">
                                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                                </div>
                                <div class="text-truncate" style="max-width: 200px;">
                                    <div class="fw-bold text-dark text-xs text-truncate"><?= htmlspecialchars($user_name) ?></div>
                                    <div class="text-muted text-xs text-truncate"><?= htmlspecialchars($user_email) ?></div>
                                </div>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="action_register" value="1">
                                
                                <?php if ($event['event_type'] === 'team'): ?>
                                    <div class="mb-3">
                                        <label class="form-label font-bold text-xs text-uppercase text-dark mb-1">Squad / Team Name *</label>
                                        <input type="text" class="form-control rounded-pill text-xs px-3 py-2.5" name="team_name" autocomplete="off" placeholder="e.g. Apex Predators" required>
                                        <div class="form-text text-muted text-xs ms-2">You will be registered as the team leader.</div>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="ez-cta-button">
                                    Confirm Event Registration <i class="bi bi-arrow-right"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center p-3 bg-warning-subtle text-dark rounded-4 mb-3 text-xs font-semibold border border-warning">
                                <i class="bi bi-lock-fill me-1"></i> Please sign in with your student account to register for this competition.
                            </div>
                            <a href="login.php" class="ez-cta-button">
                                Sign In to Register <i class="bi bi-box-arrow-in-right"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Host Organizer Support Contact Section -->
                        <div class="ez-support-box">
                            <div class="text-xs font-bold text-dark text-uppercase tracking-wider mb-2">Host Organizer Support</div>
                            
                            <?php if (!empty($event['college_email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($event['college_email']) ?>" class="ez-support-link">
                                    <i class="bi bi-envelope-at text-primary fs-6"></i>
                                    <span class="text-truncate"><?= htmlspecialchars($event['college_email']) ?></span>
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($event['college_phone'])): ?>
                                <a href="tel:<?= htmlspecialchars(normalize_phone_number($event['college_phone']) ?? '') ?>" class="ez-support-link">
                                    <i class="bi bi-telephone-outbound text-success fs-6"></i>
                                    <span><?= htmlspecialchars(format_phone_number($event['college_phone'])) ?></span>
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php include 'Footer.php'; ?>
