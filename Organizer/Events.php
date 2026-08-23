<?php
/**
 * Organizer/Events.php
 * My College Events Management with Comprehensive AJAX Validation
 */
include 'organizer_auth.php';
include 'connection.php';

/* =========================================================
   SERVER-SIDE EVENT VALIDATION HELPER
   ========================================================= */
function validate_organizer_event(PDO $pdo, array $data, ?int $excludeId = null): array
{
    $errors = [];

    $category_id           = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
    $title                 = trim($data['title'] ?? '');
    $description           = trim($data['description'] ?? '');
    $event_type            = trim($data['event_type'] ?? 'solo');
    $min_team_size         = filter_var($data['min_team_size'] ?? 1, FILTER_VALIDATE_INT);
    $max_team_size         = filter_var($data['max_team_size'] ?? 1, FILTER_VALIDATE_INT);
    $fee_type              = trim($data['fee_type'] ?? 'per_person');
    $registration_fee      = filter_var($data['registration_fee'] ?? 0.00, FILTER_VALIDATE_FLOAT);
    $registration_deadline = trim($data['registration_deadline'] ?? '');
    $event_date            = trim($data['event_date'] ?? '');
    $start_time            = trim($data['start_time'] ?? '');
    $end_time              = trim($data['end_time'] ?? '');
    $venue                 = trim($data['venue'] ?? '');
    $dress_code            = trim($data['dress_code'] ?? '');
    $status                = trim($data['status'] ?? 'published');

    // Category Validation
    if (!$category_id) {
        $errors[] = 'Please select a valid event category.';
    } else {
        $chk = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :id');
        $chk->execute(['id' => $category_id]);
        if (!$chk->fetch()) {
            $errors[] = 'Selected category does not exist.';
        }
    }

    // Title Validation
    if ($title === '' || mb_strlen($title) < 2) {
        $errors[] = 'Event title must be at least 2 characters.';
    } elseif (mb_strlen($title) > 150) {
        $errors[] = 'Title cannot exceed 150 characters.';
    }

    // Description Validation
    if ($description === '' || mb_strlen($description) < 10) {
        $errors[] = 'Description is required (at least 10 characters).';
    } elseif (mb_strlen($description) > 2000) {
        $errors[] = 'Description cannot exceed 2000 characters.';
    }

    // Event Type & Team Size Validation
    if (!in_array($event_type, ['solo', 'team'], true)) {
        $errors[] = 'Select a valid event type.';
    }

    if ($event_type === 'team') {
        if ($min_team_size === false || $min_team_size < 1) {
            $errors[] = 'Minimum team size must be at least 1.';
        }
        if ($max_team_size === false || $max_team_size < 1) {
            $errors[] = 'Maximum team size must be at least 1.';
        }
        if ($min_team_size !== false && $max_team_size !== false && $max_team_size < $min_team_size) {
            $errors[] = 'Maximum team size cannot be less than minimum team size.';
        }
        if ($max_team_size !== false && $max_team_size > 100) {
            $errors[] = 'Maximum team size cannot exceed 100.';
        }
    }

    // Fee Validation
    if (!in_array($fee_type, ['per_person', 'per_team'], true)) {
        $errors[] = 'Select a valid fee type.';
    }
    if ($registration_fee === false || $registration_fee < 0) {
        $errors[] = 'Enter a valid non-negative registration fee.';
    }

    // Date & Time Validation
    $eventDateObj = null;
    if ($event_date === '') {
        $errors[] = 'Event date is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $event_date);
        if (!$d || $d->format('Y-m-d') !== $event_date) {
            $errors[] = 'Enter a valid event date.';
        } else {
            $eventDateObj = $d;
        }
    }

    if ($start_time !== '' && $end_time !== '') {
        $s = DateTime::createFromFormat('H:i', $start_time) ?: DateTime::createFromFormat('H:i:s', $start_time);
        $e = DateTime::createFromFormat('H:i', $end_time) ?: DateTime::createFromFormat('H:i:s', $end_time);
        if ($s && $e && $e <= $s) {
            $errors[] = 'End time must be after start time.';
        }
    }

    // Venue & Dress Code Validation
    if ($venue === '' || mb_strlen($venue) < 3) {
        $errors[] = 'Venue location is required (at least 3 characters).';
    }
    if ($dress_code === '') {
        $errors[] = 'Dress code is required.';
    }

    if (!in_array($status, ['draft', 'published', 'completed', 'cancelled'], true)) {
        $errors[] = 'Select a valid event status.';
    }

    return $errors;
}

/* =========================================================
   AJAX ENDPOINTS
   ========================================================= */

// 1. AJAX: TOGGLE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'toggle_status') {
    header('Content-Type: application/json');
    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
    $status = strtolower(trim($body['status'] ?? ''));

    if (!$id || !in_array($status, ['draft', 'published', 'completed', 'cancelled'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    $check = $pdo->prepare('SELECT event_id FROM events WHERE event_id = :id AND college_id = :cid');
    $check->execute(['id' => $id, 'cid' => $college_id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found or unauthorized.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE events SET status = :status WHERE event_id = :id AND college_id = :cid');
    $stmt->execute(['status' => $status, 'id' => $id, 'cid' => $college_id]);

    echo json_encode(['success' => true, 'message' => 'Status updated to ' . ucfirst($status) . '.']);
    exit;
}

// 2. AJAX: CREATE / EDIT EVENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_GET['action'] ?? '', ['create', 'edit'], true)) {
    header('Content-Type: application/json');
    $action  = $_GET['action'];
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT) ?: null;

    if ($action === 'edit' && !$eventId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['Event ID is missing.']]);
        exit;
    }

    if ($action === 'edit') {
        $chk = $pdo->prepare('SELECT event_id FROM events WHERE event_id = :id AND college_id = :cid');
        $chk->execute(['id' => $eventId, 'cid' => $college_id]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'errors' => ['Event not found or access denied.']]);
            exit;
        }
    }

    $errors = validate_organizer_event($pdo, $_POST, $eventId);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    $category_id           = (int)$_POST['category_id'];
    $title                 = trim($_POST['title']);
    $description           = trim($_POST['description']);
    $event_type            = $_POST['event_type'];
    $min_team_size         = $event_type === 'team' ? (int)$_POST['min_team_size'] : 1;
    $max_team_size         = $event_type === 'team' ? (int)$_POST['max_team_size'] : 1;
    $fee_type              = $_POST['fee_type'] ?? 'per_person';
    $registration_fee      = (float)($_POST['registration_fee'] ?? 0.00);
    $registration_deadline = !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null;
    $event_date            = $_POST['event_date'];
    $start_time            = !empty($_POST['start_time']) ? $_POST['start_time'] : '09:00:00';
    $end_time              = !empty($_POST['end_time']) ? $_POST['end_time'] : '17:00:00';
    $venue                 = trim($_POST['venue']);
    $dress_code            = trim($_POST['dress_code']);
    $status                = $_POST['status'];

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO events (
                    college_id, category_id, title, description, event_type, 
                    min_team_size, max_team_size, fee_type, registration_fee, 
                    registration_deadline, event_date, start_time, end_time, venue, dress_code, status
                ) VALUES (
                    :cid, :cat, :title, :desc, :type, 
                    :min_team, :max_team, :fee_type, :reg_fee, 
                    :reg_deadline, :edate, :stime, :etime, :venue, :dress, :status
                )
            ");
            $stmt->execute([
                'cid'          => $college_id,
                'cat'          => $category_id,
                'title'        => $title,
                'desc'         => $description,
                'type'         => $event_type,
                'min_team'     => $min_team_size,
                'max_team'     => $max_team_size,
                'fee_type'     => $fee_type,
                'reg_fee'      => $registration_fee,
                'reg_deadline' => $registration_deadline,
                'edate'        => $event_date,
                'stime'        => $start_time,
                'etime'        => $end_time,
                'venue'        => $venue,
                'dress'        => $dress_code,
                'status'       => $status,
            ]);
            $message = "Event '{$title}' created successfully!";
        } else {
            $stmt = $pdo->prepare("
                UPDATE events SET 
                    category_id = :cat, title = :title, description = :desc, event_type = :type, 
                    min_team_size = :min_team, max_team_size = :max_team, fee_type = :fee_type, 
                    registration_fee = :reg_fee, registration_deadline = :reg_deadline, 
                    event_date = :edate, start_time = :stime, end_time = :etime, 
                    venue = :venue, dress_code = :dress, status = :status
                WHERE event_id = :eid AND college_id = :cid
            ");
            $stmt->execute([
                'cat'          => $category_id,
                'title'        => $title,
                'desc'         => $description,
                'type'         => $event_type,
                'min_team'     => $min_team_size,
                'max_team'     => $max_team_size,
                'fee_type'     => $fee_type,
                'reg_fee'      => $registration_fee,
                'reg_deadline' => $registration_deadline,
                'edate'        => $event_date,
                'stime'        => $start_time,
                'etime'        => $end_time,
                'venue'        => $venue,
                'dress'        => $dress_code,
                'status'       => $status,
                'eid'          => $eventId,
                'cid'          => $college_id,
            ]);
            $message = "Event '{$title}' updated successfully!";
        }

        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
        exit;
    }
}

// Fetch Categories for Dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

// Fetch Events Hosted By This College
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
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Events | Organizer Portal</title>

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
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: #1c2024;
            margin: 0;
            padding: 20px 28px;
            min-height: 100vh;
        }

        .berun-window { width: 100%; position: relative; }

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
            color: #1c2024;
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
            background-color: #1c2024;
            border-radius: 50%;
        }

        .berun-logo-text { font-weight: 800; font-size: 20px; color: #1c2024; }
        .berun-logo-text span { font-weight: 400; }

        .berun-greeting-h1 { font-size: 24px; font-weight: 700; margin: 0; }

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

        .berun-card-panel {
            background: var(--bg-white);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .berun-panel-title { font-size: 18px; font-weight: 700; margin: 0; }
        .berun-panel-sub   { font-size: 12px; color: #7c7d7e; margin: 2px 0 0 0; }

        .status-badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .badge-success { background: #e6f7ed; color: #10b981; }
        .badge-warning { background: #fef3c7; color: #d97706; }

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
                    <h1 class="berun-greeting-h1">Hosted Events</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Create, update and manage events hosted by <?= htmlspecialchars((string)$college_name) ?></p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="button" class="berun-btn-dark" data-bs-toggle="modal" data-bs-target="#createEventModal">
                    <i class="bi bi-plus-circle"></i> Create New Event
                </button>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Organizer_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <div id="pageAlertContainer"></div>

                <!-- EVENTS LIST PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">My College Event Listings</h3>
                            <p class="berun-panel-sub">Total <?= count($events) ?> events created under <?= htmlspecialchars((string)$college_name) ?></p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted text-uppercase font-semibold" style="font-size: 11px;">
                                    <th class="ps-3">#</th>
                                    <th>Event Title</th>
                                    <th>Category</th>
                                    <th>Format</th>
                                    <th>Fee</th>
                                    <th>Venue</th>
                                    <th>Event Date</th>
                                    <th>Registrations</th>
                                    <th>Status</th>
                                    <th class="pe-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($events)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">No events found for your college. Click "Create New Event" to publish one!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $idx => $e): ?>
                                        <tr id="eventRow<?= $e['event_id'] ?>">
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
                                            <td class="fw-bold text-dark">₹<?= number_format((float)($e['registration_fee'] ?? 0), 2) ?></td>
                                            <td class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)$e['venue']) ?></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($e['event_date'])) ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1"><?= $e['reg_count'] ?> Signed Up</span></td>
                                            <td>
                                                <button type="button" class="border-0 bg-transparent p-0 toggle-status-btn" data-id="<?= $e['event_id'] ?>" data-status="<?= $e['status'] === 'published' ? 'draft' : 'published' ?>">
                                                    <?php if ($e['status'] === 'published'): ?>
                                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1" style="cursor:pointer;"><i class="bi bi-check-circle-fill me-1"></i> Published</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-1" style="cursor:pointer;"><i class="bi bi-clock-history me-1"></i> Draft</span>
                                                    <?php endif; ?>
                                                </button>
                                            </td>
                                            <td class="pe-3 text-end">
                                                <button type="button" class="btn btn-sm btn-light rounded-circle border" data-bs-toggle="modal" data-bs-target="#editModal<?= $e['event_id'] ?>" title="Edit Event">
                                                    <i class="bi bi-pencil-square text-primary"></i>
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- EDIT EVENT MODAL -->
                                        <div class="modal fade" id="editModal<?= $e['event_id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content border-0 rounded-4 shadow">
                                                    <form class="needs-validation ajax-event-form" data-action="edit" novalidate>
                                                        <input type="hidden" name="event_id" value="<?= $e['event_id'] ?>">
                                                        <div class="modal-header border-bottom">
                                                            <h5 class="modal-title font-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i> Edit Event: <?= htmlspecialchars((string)$e['title']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="modal-alert-container"></div>
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Title *</label>
                                                                    <input type="text" class="form-control" name="title" value="<?= htmlspecialchars((string)$e['title']) ?>" required minlength="2" maxlength="150">
                                                                    <div class="invalid-feedback">Enter a valid title (2-150 chars).</div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Category *</label>
                                                                    <select class="form-select" name="category_id" required>
                                                                        <?php foreach ($categories as $cat): ?>
                                                                            <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $e['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$cat['name']) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <div class="invalid-feedback">Select a category.</div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Format *</label>
                                                                    <select class="form-select event-type-select" name="event_type" required>
                                                                        <option value="solo" <?= $e['event_type'] === 'solo' ? 'selected' : '' ?>>Solo Event</option>
                                                                        <option value="team" <?= $e['event_type'] === 'team' ? 'selected' : '' ?>>Team Event</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4 team-size-group <?= $e['event_type'] === 'solo' ? 'd-none' : '' ?>">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Min Team Size</label>
                                                                    <input type="number" class="form-control" name="min_team_size" value="<?= $e['min_team_size'] ?>" min="1" max="100">
                                                                </div>
                                                                <div class="col-md-4 team-size-group <?= $e['event_type'] === 'solo' ? 'd-none' : '' ?>">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Max Team Size</label>
                                                                    <input type="number" class="form-control" name="max_team_size" value="<?= $e['max_team_size'] ?>" min="1" max="100">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Fee Type *</label>
                                                                    <select class="form-select" name="fee_type" required>
                                                                        <option value="per_person" <?= ($e['fee_type'] ?? '') === 'per_person' ? 'selected' : '' ?>>Per Person</option>
                                                                        <option value="per_team" <?= ($e['fee_type'] ?? '') === 'per_team' ? 'selected' : '' ?>>Per Team</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Registration Fee (₹) *</label>
                                                                    <input type="number" step="0.01" class="form-control" name="registration_fee" value="<?= htmlspecialchars((string)($e['registration_fee'] ?? '0.00')) ?>" min="0" required>
                                                                    <div class="invalid-feedback">Enter a valid fee (>= 0).</div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Venue Location *</label>
                                                                    <input type="text" class="form-control" name="venue" value="<?= htmlspecialchars((string)$e['venue']) ?>" minlength="3" required>
                                                                    <div class="invalid-feedback">Enter venue location (min 3 chars).</div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Date *</label>
                                                                    <input type="date" class="form-control" name="event_date" value="<?= $e['event_date'] ?>" required>
                                                                    <div class="invalid-feedback">Select a valid event date.</div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Start Time</label>
                                                                    <input type="time" class="form-control" name="start_time" value="<?= htmlspecialchars((string)($e['start_time'] ?? '09:00')) ?>">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">End Time</label>
                                                                    <input type="time" class="form-control" name="end_time" value="<?= htmlspecialchars((string)($e['end_time'] ?? '17:00')) ?>">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Dress Code *</label>
                                                                    <input type="text" class="form-control" name="dress_code" value="<?= htmlspecialchars((string)$e['dress_code']) ?>" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Publish Status</label>
                                                                    <select class="form-select" name="status" required>
                                                                        <option value="published" <?= $e['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                                                        <option value="draft" <?= $e['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Description *</label>
                                                                    <textarea class="form-control" name="description" rows="3" minlength="10" required><?= htmlspecialchars((string)$e['description']) ?></textarea>
                                                                    <div class="invalid-feedback">Enter description (at least 10 chars).</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top">
                                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="berun-btn-dark btn-submit-event">Update Event</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- CREATE EVENT MODAL -->
    <div class="modal fade" id="createEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form class="needs-validation ajax-event-form" data-action="create" novalidate>
                    <div class="modal-header border-bottom">
                        <h5 class="modal-title font-bold text-dark"><i class="bi bi-calendar-plus me-2 text-primary"></i> Create Event for <?= htmlspecialchars((string)$college_name) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="modal-alert-container"></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Title *</label>
                                <input type="text" class="form-control" name="title" placeholder="e.g. Hackathon 2026" minlength="2" maxlength="150" required>
                                <div class="invalid-feedback">Enter a valid title (2-150 chars).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Category *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars((string)$cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Select a category.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Format *</label>
                                <select class="form-select event-type-select" name="event_type" required>
                                    <option value="solo" selected>Solo Event</option>
                                    <option value="team">Team Event</option>
                                </select>
                            </div>
                            <div class="col-md-4 team-size-group d-none">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Min Team Size</label>
                                <input type="number" class="form-control" name="min_team_size" value="1" min="1" max="100">
                            </div>
                            <div class="col-md-4 team-size-group d-none">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Max Team Size</label>
                                <input type="number" class="form-control" name="max_team_size" value="4" min="1" max="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Fee Type *</label>
                                <select class="form-select" name="fee_type" required>
                                    <option value="per_person" selected>Per Person</option>
                                    <option value="per_team">Per Team</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Registration Fee (₹) *</label>
                                <input type="number" step="0.01" class="form-control" name="registration_fee" value="0.00" min="0" required>
                                <div class="invalid-feedback">Enter a valid fee (>= 0).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Venue Location *</label>
                                <input type="text" class="form-control" name="venue" placeholder="e.g. Auditorium / Main Hall" minlength="3" required>
                                <div class="invalid-feedback">Enter venue location (min 3 chars).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Date *</label>
                                <input type="date" class="form-control" name="event_date" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Select a valid event date.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Start Time</label>
                                <input type="time" class="form-control" name="start_time" value="09:00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">End Time</label>
                                <input type="time" class="form-control" name="end_time" value="17:00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Dress Code *</label>
                                <input type="text" class="form-control" name="dress_code" placeholder="e.g. Formal / College Uniform" value="Formal / Casual" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Publish Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="published" selected>Published</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Description *</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Enter details about rules, rounds, prizes..." minlength="10" required></textarea>
                                <div class="invalid-feedback">Enter description (at least 10 chars).</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="berun-btn-dark btn-submit-event">Publish Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- AJAX Form Processing & Client Validation JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Dynamic Team Size Fields Toggle
            document.querySelectorAll('.event-type-select').forEach(select => {
                select.addEventListener('change', function () {
                    const form = this.closest('form');
                    const teamGroups = form.querySelectorAll('.team-size-group');
                    if (this.value === 'team') {
                        teamGroups.forEach(g => g.classList.remove('d-none'));
                    } else {
                        teamGroups.forEach(g => g.classList.add('d-none'));
                    }
                });
            });

            // AJAX Form Submission Handler
            document.querySelectorAll('.ajax-event-form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    if (!this.checkValidity()) {
                        e.stopPropagation();
                        this.classList.add('was-validated');
                        return;
                    }

                    const action = this.dataset.action;
                    const submitBtn = this.querySelector('.btn-submit-event');
                    const alertBox = this.querySelector('.modal-alert-container');
                    const originalBtnText = submitBtn.innerHTML;

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Saving...`;
                    alertBox.innerHTML = '';

                    const formData = new FormData(this);

                    fetch(`Events.php?action=${action}`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;

                        if (data.success) {
                            alertBox.innerHTML = `<div class="alert alert-success rounded-3 border-0 py-2 px-3 text-xs mb-3"><i class="bi bi-check-circle-fill me-1"></i> ${data.message}</div>`;
                            setTimeout(() => {
                                window.location.reload();
                            }, 800);
                        } else {
                            const errList = (data.errors || [data.message || 'An error occurred.'])
                                .map(err => `<li>${err}</li>`).join('');
                            alertBox.innerHTML = `<div class="alert alert-danger rounded-3 border-0 py-2 px-3 text-xs mb-3"><ul class="mb-0 ps-3">${errList}</ul></div>`;
                        }
                    })
                    .catch(err => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                        alertBox.innerHTML = `<div class="alert alert-danger rounded-3 border-0 py-2 px-3 text-xs mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i> Request failed. Please check network.</div>`;
                    });
                });
            });

            // AJAX Status Toggle Handler
            document.querySelectorAll('.toggle-status-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const eventId = this.dataset.id;
                    const newStatus = this.dataset.status;

                    fetch('Events.php?action=toggle_status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: eventId, status: newStatus })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Status toggle failed.');
                        }
                    })
                    .catch(err => alert('Network error during status toggle.'));
                });
            });

        });
    </script>
</body>
</html>
