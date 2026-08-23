<?php
/**
 * Organizer/Students.php
 * College Students Directory for College Organizers
 */
include 'organizer_auth.php';
include 'connection.php';

// Fetch Students Scoped to This College
$stmt = $pdo->prepare("
    SELECT * FROM students 
    WHERE college_id = :cid 
    ORDER BY student_id DESC
");
$stmt->execute(['cid' => $college_id]);
$students = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>College Students | Organizer Portal</title>

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
                    <h1 class="berun-greeting-h1">College Students</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Directory of enrolled students at <?= htmlspecialchars((string)$college_name) ?></p>
                </div>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Organizer_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <!-- STUDENTS LIST PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">Enrolled Students Roster</h3>
                            <p class="berun-panel-sub">Total <?= count($students) ?> active students registered under your college</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted text-uppercase font-semibold" style="font-size: 11px;">
                                    <th class="ps-3">#</th>
                                    <th>Student</th>
                                    <th>Enrollment No</th>
                                    <th>Email Address</th>
                                    <th>Phone Number</th>
                                    <th>Gender</th>
                                    <th>Semester</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No enrolled students found for your college in the database.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $idx => $s): ?>
                                        <tr>
                                            <td class="ps-3 font-semibold text-muted"><?= $idx + 1 ?></td>
                                            <td class="fw-bold text-dark">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= !empty($s['profile_photo']) ? '../uploads/students/' . htmlspecialchars($s['profile_photo']) : 'https://ui-avatars.com/api/?name=' . urlencode($s['name']) ?>" class="rounded-circle" width="32" height="32" style="object-fit:cover;">
                                                    <span><?= htmlspecialchars((string)$s['name']) ?></span>
                                                </div>
                                            </td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars((string)($s['enrollment_no'] ?? 'N/A')) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars((string)$s['email']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars((string)($s['phone'] ?? 'N/A')) ?></td>
                                            <td class="text-capitalize"><?= htmlspecialchars((string)($s['gender'] ?? 'N/A')) ?></td>
                                            <td><span class="badge bg-light text-dark rounded-pill px-3 py-1 border">Sem <?= htmlspecialchars((string)($s['semester'] ?? '1')) ?></span></td>
                                            <td>
                                                <?php if (($s['account_status'] ?? 'active') === 'active'): ?>
                                                    <span class="status-badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="status-badge badge-warning">Inactive</span>
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

        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
