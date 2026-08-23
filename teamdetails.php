<?php
include 'auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   DB CONNECTION
   ========================================================= */
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'evenza';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

$PHOTO_WEB_PATH = 'assets/images/user/';

/* =========================================================
   FETCH TEAM DETAILS
   ========================================================= */
$teamId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$teamId) {
    $latestStmt = $pdo->query('SELECT team_id FROM teams ORDER BY created_at DESC LIMIT 1');
    $latestTeam = $latestStmt->fetch();
    $teamId = $latestTeam ? (int)$latestTeam['team_id'] : null;
}

$team = null;
$members = [];

if ($teamId) {
    $stmt = $pdo->prepare('
        SELECT 
            t.*,
            e.title AS event_title,
            e.venue,
            e.event_date,
            e.min_team_size,
            e.max_team_size,
            s.name AS leader_name,
            s.email AS leader_email,
            s.phone AS leader_phone,
            s.enrollment_no AS leader_enrollment,
            s.profile_photo AS leader_photo,
            c.name AS college_name
        FROM teams t
        JOIN events e ON t.event_id = e.event_id
        JOIN students s ON t.leader_id = s.student_id
        LEFT JOIN colleges c ON s.college_id = c.college_id
        WHERE t.team_id = :id
    ');
    $stmt->execute(['id' => $teamId]);
    $team = $stmt->fetch();

    if ($team) {
        $mStmt = $pdo->prepare('
            SELECT 
                s.student_id,
                s.name,
                s.enrollment_no,
                s.email,
                s.profile_photo,
                c.name AS college_name
            FROM team_members tm
            JOIN students s ON tm.student_id = s.student_id
            LEFT JOIN colleges c ON s.college_id = c.college_id
            WHERE tm.team_id = :tid
            ORDER BY (s.student_id = :leader_id) DESC, s.name ASC
        ');
        $mStmt->execute(['tid' => $teamId, 'leader_id' => $team['leader_id']]);
        $members = $mStmt->fetchAll();
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$admin_email = $_SESSION['admin_email'] ?? 'admin@evenza.com';
$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team Details | Evenza Admin</title>

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
            --bg-hero: #e0d8cb;
            --bg-dark: #1c2024;
            --bg-white: #ffffff;
            --bg-row: #f6f4ee;
            --color-yellow: #ffd13b;
            --color-red: #ff6b52;
            --color-text-dark: #1c2024;
            --color-text-muted: #7c7d7e;
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: var(--color-text-dark);
            margin: 0;
            padding: 20px 28px;
            min-height: 100vh;
            width: 100%;
        }

        /* Fluid Layout Frame */
        .berun-window {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            position: relative;
        }

        /* Header Navigation Area */
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
            color: var(--color-text-dark);
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
            background-color: var(--color-text-dark);
            border-radius: 50%;
        }

        .berun-logo-text {
            font-weight: 800;
            font-size: 20px;
            letter-spacing: -0.5px;
            color: var(--color-text-dark);
        }
        .berun-logo-text span { font-weight: 400; }

        .berun-greeting-h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: var(--color-text-dark);
            line-height: 1.2;
        }

        .custom-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
            padding: 0;
            margin: 4px 0 0 0;
            font-size: 12px;
            font-weight: 600;
        }
        .custom-breadcrumb li { color: var(--color-text-muted); }
        .custom-breadcrumb li a { text-decoration: none; color: var(--color-text-dark); }
        .custom-breadcrumb li:not(:last-child)::after { content: "/"; margin-left: 8px; color: #b5b7bc; }

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

        /* Layout Grid with Left Floating Sidebar Capsule */
        .berun-layout-body {
            display: flex;
            gap: 28px;
        }

        .berun-sidebar-capsule {
            background: var(--bg-white);
            border-radius: 40px;
            width: 68px;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            flex-shrink: 0;
            position: sticky;
            top: 20px;
            align-self: flex-start;
            z-index: 100;
            max-height: calc(100vh - 40px);
        }

        .berun-nav-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            width: 100%;
        }

        .berun-nav-item {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #72757c;
            text-decoration: none;
            font-size: 18px;
            transition: all 0.2s ease;
            position: relative;
        }

        .berun-nav-item:hover { color: var(--color-text-dark); background: rgba(0, 0, 0, 0.04); }

        .berun-nav-item.active {
            background-color: var(--bg-dark);
            color: var(--color-yellow) !important;
            box-shadow: 0 6px 16px rgba(28, 32, 36, 0.25);
        }

        .berun-avatar-pill {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        /* Main Content Grid */
        .berun-main-grid {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* Card Panels */
        .berun-card-panel {
            background: var(--bg-white);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .berun-panel-title { font-size: 17px; font-weight: 700; color: var(--color-text-dark); margin: 0; }
        .berun-panel-sub   { font-size: 12px; color: var(--color-text-muted); margin: 2px 0 0 0; font-weight: 500; }

        .leader-avatar {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 1px solid #e5e7eb;
        }

        /* Table styling in Be.run theme */
        .table thead th {
            background: #f9f8f4;
            color: #7c7d7e;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid #edf0f5;
        }
        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            color: #374151;
            border-bottom: 1px solid #f4f2eb;
            font-size: 13px;
        }
        .table tbody tr { transition: 0.2s ease; }
        .table tbody tr:hover { background-color: #f9f8f4; }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            body { padding: 12px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
            .berun-sidebar-capsule { width: 100%; flex-direction: row; height: 60px; padding: 0 16px; border-radius: 9999px; }
            .berun-nav-group { flex-direction: row; justify-content: space-around; width: auto; flex-grow: 1; }
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
                    <h1 class="berun-greeting-h1">Team Details</h1>
                    <p class="mb-0 text-muted" style="font-size: 13px; font-weight: 500;">
                        <?= $team ? 'Viewing details for team: ' . htmlspecialchars((string)$team['team_name']) : 'No team selected' ?>
                    </p>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li><a href="allteams.php">Team Management</a></li>
                        <li>Team Details</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="allteams.php" class="berun-btn-dark">
                    <i class="bi bi-arrow-left"></i> Back to Teams
                </a>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <!-- Flash Message Alert -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                        <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars((string)($flash['message'] ?? '')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$team): ?>
                    <!-- Empty State -->
                    <div class="berun-card-panel text-center py-5">
                        <i class="bi bi-people text-muted display-4"></i>
                        <h4 class="mt-3 fw-bold">No Team Information Available</h4>
                        <p class="text-muted">No teams exist in the database or an invalid team was requested.</p>
                        <a href="allteams.php" class="berun-btn-dark mt-2">Go to Teams List</a>
                    </div>
                <?php else: ?>

                    <!-- Team & Event Info Cards -->
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <div class="berun-card-panel h-100">
                                <h3 class="berun-panel-title mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Team Information</h3>
                                <div class="mb-3">
                                    <small class="text-muted font-semibold d-block text-uppercase" style="font-size: 11px;">Team Name</small>
                                    <span class="fw-bold text-dark fs-5"><?= htmlspecialchars((string)$team['team_name']) ?></span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted font-semibold d-block text-uppercase" style="font-size: 11px;">Team Code</small>
                                    <span class="badge bg-primary-subtle text-primary fw-bold rounded-pill px-3 py-1 fs-6">
                                        <?= htmlspecialchars((string)$team['team_code']) ?>
                                    </span>
                                </div>
                                <div>
                                    <small class="text-muted font-semibold d-block text-uppercase" style="font-size: 11px;">Created Date</small>
                                    <span class="text-dark font-medium"><?= date('d M Y', strtotime($team['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="berun-card-panel h-100">
                                <h3 class="berun-panel-title mb-3"><i class="bi bi-trophy text-warning me-2"></i>Event Information</h3>
                                <div class="mb-3">
                                    <small class="text-muted font-semibold d-block text-uppercase" style="font-size: 11px;">Event Name</small>
                                    <span class="fw-bold text-dark fs-5"><?= htmlspecialchars((string)$team['event_title']) ?></span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted font-semibold d-block text-uppercase" style="font-size: 11px;">Venue</small>
                                    <span class="text-dark font-medium"><i class="bi bi-geo-alt me-1 text-primary"></i><?= htmlspecialchars((string)($team['venue'] ?? 'Not Specified')) ?></span>
                                </div>
                                <div>
                                    <small class="text-muted font-semibold d-block text-uppercase" style="font-size: 11px;">Event Date</small>
                                    <span class="text-dark font-medium"><i class="bi bi-calendar3 me-1 text-primary"></i><?= !empty($team['event_date']) ? date('d M Y', strtotime($team['event_date'])) : 'TBA' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Leader Card -->
                    <div class="berun-card-panel">
                        <h3 class="berun-panel-title mb-3"><i class="bi bi-star-fill text-warning me-2"></i>Team Leader</h3>
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <?php 
                                $leaderPhoto = !empty($team['leader_photo']) 
                                    ? $PHOTO_WEB_PATH . htmlspecialchars((string)$team['leader_photo']) 
                                    : 'assets/images/user/avatar-1.jpg'; 
                            ?>
                            <img src="<?= $leaderPhoto ?>" class="leader-avatar" alt="Leader Photo" onerror="this.src='assets/images/user/avatar-1.jpg';" />
                            <div>
                                <h4 class="mb-1 text-dark fw-bold"><?= htmlspecialchars((string)$team['leader_name']) ?></h4>
                                <p class="mb-1 text-muted font-medium" style="font-size: 13px;"><strong>Enrollment:</strong> <?= htmlspecialchars((string)($team['leader_enrollment'] ?? 'N/A')) ?></p>
                                <p class="mb-1 text-muted font-medium" style="font-size: 13px;"><strong>Email:</strong> <?= htmlspecialchars((string)$team['leader_email']) ?></p>
                                <p class="mb-0 text-muted font-medium" style="font-size: 13px;"><strong>College:</strong> <?= htmlspecialchars((string)($team['college_name'] ?? 'N/A')) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Team Members Table Panel -->
                    <div class="berun-card-panel p-0 overflow-hidden">
                        <div class="p-4 border-bottom d-flex align-items-center justify-content-between" style="border-color:#f4f2eb !important;">
                            <div>
                                <h3 class="berun-panel-title">Team Members</h3>
                                <p class="berun-panel-sub">Total members registered in team</p>
                            </div>
                            <span class="badge bg-primary-subtle text-primary fw-bold rounded-pill px-3 py-2">
                                <?= count($members) ?> / <?= $team['max_team_size'] ?> Members
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">#</th>
                                        <th>Photo</th>
                                        <th>Name</th>
                                        <th>Enrollment</th>
                                        <th>Email</th>
                                        <th>College</th>
                                        <th class="pe-4">Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($members)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">No members recorded for this team yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($members as $index => $m): ?>
                                            <?php 
                                                $isLeader = (int)$m['student_id'] === (int)$team['leader_id']; 
                                                $photo = !empty($m['profile_photo']) 
                                                    ? $PHOTO_WEB_PATH . htmlspecialchars((string)$m['profile_photo']) 
                                                    : 'assets/images/user/avatar-1.jpg';
                                            ?>
                                            <tr>
                                                <td class="ps-4 font-semibold text-muted"><?= $index + 1 ?></td>
                                                <td>
                                                    <img src="<?= $photo ?>" class="member-avatar" alt="Member Photo" onerror="this.src='assets/images/user/avatar-1.jpg';" />
                                                </td>
                                                <td class="fw-semibold text-dark"><?= htmlspecialchars((string)$m['name']) ?></td>
                                                <td><?= htmlspecialchars((string)($m['enrollment_no'] ?? 'N/A')) ?></td>
                                                <td class="text-muted"><?= htmlspecialchars((string)$m['email']) ?></td>
                                                <td class="text-secondary"><?= htmlspecialchars((string)($m['college_name'] ?? 'N/A')) ?></td>
                                                <td class="pe-4">
                                                    <?php if ($isLeader): ?>
                                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1"><i class="bi bi-star-fill me-1"></i> Leader</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1">Member</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>