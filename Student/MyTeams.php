<?php
/**
 * Student/MyTeams.php
 * Squad Teams & Roster Page for Student Portal
 */
include 'student_auth.php';
include 'connection.php';

// Fetch Teams where student is Leader or Member
$stmt = $pdo->prepare("
    SELECT DISTINCT t.*, e.title AS event_title, e.event_date, col.name AS hosting_college,
           s_leader.name AS leader_name, s_leader.email AS leader_email
    FROM teams t
    INNER JOIN events e ON t.event_id = e.event_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    LEFT JOIN students s_leader ON t.leader_id = s_leader.student_id
    INNER JOIN team_members tm ON t.team_id = tm.team_id
    WHERE tm.student_id = :sid OR t.leader_id = :sid2
    ORDER BY t.team_id DESC
");
$stmt->execute(['sid' => $student_id, 'sid2' => $student_id]);
$teams = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Teams & Squads | Evenza Student Portal</title>

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

        .team-card-bespoke {
            background: #ffffff;
            border: 2px solid #14171a;
            border-radius: 28px;
            padding: 28px;
            box-shadow: 6px 6px 0px #14171a;
            height: 100%;
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
                    <h1 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">My Teams & Squad Rosters</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Team codes and member rosters for multi-person competitions</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="../Frontend/events.php" class="btn btn-dark rounded-pill px-4 py-2 text-xs font-bold text-white">
                    <i class="bi bi-search me-1"></i> Explore Events
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
                    <?php if (empty($teams)): ?>
                        <div class="col-12 text-center py-5 bg-white rounded-5 p-5 border">
                            <i class="bi bi-people fs-1 text-muted opacity-50 d-block mb-3"></i>
                            <h4 class="fw-black text-dark mb-2">No Teams Joined Yet</h4>
                            <p class="text-muted text-xs mb-4">When you register for team format competitions, your team squads will appear here.</p>
                            <a href="../Frontend/events.php" class="btn btn-warning rounded-pill px-4 py-2.5 text-xs font-bold text-dark border border-dark">
                                Browse Team Events &rarr;
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($teams as $t): 
                            // Fetch Team Members
                            $stmtMembers = $pdo->prepare("
                                SELECT s.name, s.email, s.enrollment_no 
                                FROM team_members tm 
                                INNER JOIN students s ON tm.student_id = s.student_id 
                                WHERE tm.team_id = :tid
                            ");
                            $stmtMembers->execute(['tid' => $t['team_id']]);
                            $members = $stmtMembers->fetchAll();
                        ?>
                            <div class="col-12 col-md-6">
                                <div class="team-card-bespoke">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="badge bg-warning text-dark font-bold px-3 py-1.5 rounded-pill text-xs border border-dark">
                                            Code: <?= htmlspecialchars((string)($t['team_code'] ?? 'N/A')) ?>
                                        </span>
                                        <span class="badge bg-dark text-white rounded-pill px-3 py-1 text-xs font-bold">
                                            <?= count($members) ?> Member<?= count($members) == 1 ? '' : 's' ?>
                                        </span>
                                    </div>

                                    <h4 class="fw-black text-dark mb-1 fs-5" style="letter-spacing: -0.3px;">
                                        <?= htmlspecialchars((string)$t['team_name']) ?>
                                    </h4>
                                    <p class="text-muted text-xs mb-3">
                                        Event: <strong><?= htmlspecialchars((string)$t['event_title']) ?></strong> (<?= date('d M Y', strtotime($t['event_date'])) ?>)
                                    </p>

                                    <div class="border-top pt-3">
                                        <h6 class="font-bold text-dark text-xs uppercase mb-2">Squad Roster:</h6>
                                        <ul class="list-group list-group-flush text-xs">
                                            <?php foreach ($members as $m): ?>
                                                <li class="list-group-item px-0 py-1.5 d-flex align-items-center justify-content-between bg-transparent">
                                                    <div>
                                                        <i class="bi bi-person-fill text-warning me-1"></i> <strong><?= htmlspecialchars((string)$m['name']) ?></strong>
                                                        <small class="text-muted text-truncate d-inline-block ms-1" style="max-width: 150px;">(<?= htmlspecialchars((string)$m['email']) ?>)</small>
                                                    </div>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars((string)($m['enrollment_no'] ?? 'STU')) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
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
