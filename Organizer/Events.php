<?php
/**
 * Organizer/Events.php
 * My College Events Management
 */
include 'organizer_auth.php';
include 'connection.php';

$flash = null;

// Handle Form Actions (Create / Edit / Toggle Status / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create') {
        $title          = trim($_POST['title'] ?? '');
        $category_id    = (int)($_POST['category_id'] ?? 0);
        $event_type     = $_POST['event_type'] ?? 'solo';
        $min_team_size  = (int)($_POST['min_team_size'] ?? 1);
        $max_team_size  = (int)($_POST['max_team_size'] ?? 1);
        $venue          = trim($_POST['venue'] ?? '');
        $event_date     = $_POST['event_date'] ?? date('Y-m-d');
        $dress_code     = trim($_POST['dress_code'] ?? 'Formal / Casual');
        $description    = trim($_POST['description'] ?? '');
        $status         = $_POST['status'] ?? 'published';

        if (!empty($title) && $category_id > 0 && !empty($venue)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO events (college_id, category_id, title, description, event_type, min_team_size, max_team_size, venue, event_date, dress_code, status, created_at)
                    VALUES (:cid, :cat, :title, :desc, :type, :min, :max, :venue, :edate, :dress, :status, NOW())
                ");
                $stmt->execute([
                    'cid' => $college_id,
                    'cat' => $category_id,
                    'title' => $title,
                    'desc' => $description,
                    'type' => $event_type,
                    'min' => $event_type === 'team' ? $min_team_size : 1,
                    'max' => $event_type === 'team' ? $max_team_size : 1,
                    'venue' => $venue,
                    'edate' => $event_date,
                    'dress' => $dress_code,
                    'status' => $status
                ]);
                $flash = ['type' => 'success', 'message' => 'New event created successfully for your college!'];
            } catch (PDOException $e) {
                $flash = ['type' => 'danger', 'message' => 'Error creating event: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Please fill in all required event fields.'];
        }
    } elseif ($action === 'edit') {
        $event_id       = (int)($_POST['event_id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $category_id    = (int)($_POST['category_id'] ?? 0);
        $event_type     = $_POST['event_type'] ?? 'solo';
        $min_team_size  = (int)($_POST['min_team_size'] ?? 1);
        $max_team_size  = (int)($_POST['max_team_size'] ?? 1);
        $venue          = trim($_POST['venue'] ?? '');
        $event_date     = $_POST['event_date'] ?? date('Y-m-d');
        $dress_code     = trim($_POST['dress_code'] ?? 'Formal / Casual');
        $description    = trim($_POST['description'] ?? '');
        $status         = $_POST['status'] ?? 'published';

        if ($event_id > 0 && !empty($title)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET category_id = :cat, title = :title, description = :desc, event_type = :type, 
                        min_team_size = :min, max_team_size = :max, venue = :venue, event_date = :edate, 
                        dress_code = :dress, status = :status
                    WHERE event_id = :eid AND college_id = :cid
                ");
                $stmt->execute([
                    'cat' => $category_id,
                    'title' => $title,
                    'desc' => $description,
                    'type' => $event_type,
                    'min' => $event_type === 'team' ? $min_team_size : 1,
                    'max' => $event_type === 'team' ? $max_team_size : 1,
                    'venue' => $venue,
                    'edate' => $event_date,
                    'dress' => $dress_code,
                    'status' => $status,
                    'eid' => $event_id,
                    'cid' => $college_id
                ]);
                $flash = ['type' => 'success', 'message' => 'Event updated successfully!'];
            } catch (PDOException $e) {
                $flash = ['type' => 'danger', 'message' => 'Error updating event: ' . $e->getMessage()];
            }
        }
    } elseif ($action === 'toggle_status') {
        $event_id   = (int)($_POST['event_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'published';
        if ($event_id > 0) {
            $stmt = $pdo->prepare("UPDATE events SET status = :st WHERE event_id = :eid AND college_id = :cid");
            $stmt->execute(['st' => $new_status, 'eid' => $event_id, 'cid' => $college_id]);
            $flash = ['type' => 'info', 'message' => 'Event status updated to ' . ucfirst($new_status) . '.'];
        }
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

                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                        <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

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
                                        <td colspan="9" class="text-center text-muted py-4">No events found for your college. Click "Create New Event" to publish one!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $idx => $e): ?>
                                        <tr>
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
                                            <td class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)$e['venue']) ?></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($e['event_date'])) ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1"><?= $e['reg_count'] ?> Signed Up</span></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="form_action" value="toggle_status">
                                                    <input type="hidden" name="event_id" value="<?= $e['event_id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= $e['status'] === 'published' ? 'draft' : 'published' ?>">
                                                    <button type="submit" class="border-0 bg-transparent p-0">
                                                        <?php if ($e['status'] === 'published'): ?>
                                                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1" style="cursor:pointer;"><i class="bi bi-check-circle-fill me-1"></i> Published</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-1" style="cursor:pointer;"><i class="bi bi-clock-history me-1"></i> Draft</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
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
                                                    <form method="POST">
                                                        <input type="hidden" name="form_action" value="edit">
                                                        <input type="hidden" name="event_id" value="<?= $e['event_id'] ?>">
                                                        <div class="modal-header border-bottom">
                                                            <h5 class="modal-title font-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i> Edit Event: <?= htmlspecialchars((string)$e['title']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Title *</label>
                                                                    <input type="text" class="form-control" name="title" value="<?= htmlspecialchars((string)$e['title']) ?>" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Category *</label>
                                                                    <select class="form-select" name="category_id" required>
                                                                        <?php foreach ($categories as $cat): ?>
                                                                            <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $e['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$cat['name']) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Format *</label>
                                                                    <select class="form-select" name="event_type" required>
                                                                        <option value="solo" <?= $e['event_type'] === 'solo' ? 'selected' : '' ?>>Solo Event</option>
                                                                        <option value="team" <?= $e['event_type'] === 'team' ? 'selected' : '' ?>>Team Event</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Min Team Size</label>
                                                                    <input type="number" class="form-control" name="min_team_size" value="<?= $e['min_team_size'] ?>" min="1">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Max Team Size</label>
                                                                    <input type="number" class="form-control" name="max_team_size" value="<?= $e['max_team_size'] ?>" min="1">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Venue Location *</label>
                                                                    <input type="text" class="form-control" name="venue" value="<?= htmlspecialchars((string)$e['venue']) ?>" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Date *</label>
                                                                    <input type="date" class="form-control" name="event_date" value="<?= $e['event_date'] ?>" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Dress Code</label>
                                                                    <input type="text" class="form-control" name="dress_code" value="<?= htmlspecialchars((string)$e['dress_code']) ?>">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Publish Status</label>
                                                                    <select class="form-select" name="status" required>
                                                                        <option value="published" <?= $e['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                                                        <option value="draft" <?= $e['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Description</label>
                                                                    <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars((string)$e['description']) ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top">
                                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="berun-btn-dark">Update Event</button>
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
                <form method="POST">
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header border-bottom">
                        <h5 class="modal-title font-bold text-dark"><i class="bi bi-calendar-plus me-2 text-primary"></i> Create Event for <?= htmlspecialchars((string)$college_name) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Title *</label>
                                <input type="text" class="form-control" name="title" placeholder="e.g. Hackathon 2026" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Category *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars((string)$cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Format *</label>
                                <select class="form-select" name="event_type" required>
                                    <option value="solo" selected>Solo Event</option>
                                    <option value="team">Team Event</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Min Team Size</label>
                                <input type="number" class="form-control" name="min_team_size" value="1" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Max Team Size</label>
                                <input type="number" class="form-control" name="max_team_size" value="4" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Venue Location *</label>
                                <input type="text" class="form-control" name="venue" placeholder="e.g. Auditorium / Main Hall" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Date *</label>
                                <input type="date" class="form-control" name="event_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Dress Code</label>
                                <input type="text" class="form-control" name="dress_code" placeholder="e.g. Formal / College Uniform">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Publish Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="published" selected>Published</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label font-semibold text-xs text-uppercase text-muted">Event Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Enter details about rules, rounds, prizes..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="berun-btn-dark">Publish Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
