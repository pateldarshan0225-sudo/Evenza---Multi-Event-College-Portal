<?php
/**
 * Organizer/Students.php
 * Premium Bespoke College Students Directory
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

$count_active = count(array_filter($students, fn($s) => ($s['account_status'] ?? 'active') === 'active'));
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
            --bg-dark: #14171a;
            --bg-dark-hover: #22272c;
            --bg-white: #ffffff;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --card-radius: 24px;
            --shadow-subtle: 0 4px 24px rgba(0, 0, 0, 0.03);
            --shadow-elevated: 0 12px 32px rgba(0, 0, 0, 0.06);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: #14171a;
            margin: 0;
            padding: 24px 32px;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
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
            gap: 12px;
            text-decoration: none;
            color: #14171a;
        }

        .berun-logo-dots {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }

        .berun-logo-dots-top { display: flex; gap: 3px; }

        .berun-dot {
            width: 7px;
            height: 7px;
            background-color: #14171a;
            border-radius: 50%;
        }

        .berun-logo-text { font-weight: 800; font-size: 22px; color: #14171a; letter-spacing: -0.6px; }
        .berun-logo-text span { font-weight: 400; opacity: 0.8; }

        .berun-greeting-h1 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -0.4px; }

        .berun-layout-body { display: flex; gap: 28px; }
        .berun-main-grid { flex-grow: 1; display: flex; flex-direction: column; gap: 24px; }

        /* Top Executive Summary KPI Bar */
        .kpi-mini-card {
            background: var(--bg-white);
            border-radius: 20px;
            padding: 18px 22px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .kpi-mini-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .icon-indigo  { background: #eef2ff; color: #4338ca; }
        .icon-emerald { background: #ecfdf5; color: #059669; }

        .kpi-mini-val { font-size: 22px; font-weight: 800; color: #111827; margin: 0; line-height: 1; }
        .kpi-mini-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; margin: 0; }

        /* Control & Filter Toolbar */
        .filter-toolbar {
            background: var(--bg-white);
            border-radius: 20px;
            padding: 16px 20px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        .search-pill-group {
            display: flex;
            align-items: center;
            background: #f9f8f4;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 6px 16px;
            width: 360px;
            max-width: 100%;
        }

        .search-pill-group input {
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 500;
            width: 100%;
            outline: none;
            padding-left: 8px;
        }

        .filter-select {
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            padding: 7px 16px;
            background-color: #f9f8f4;
            color: #111827;
            outline: none;
            cursor: pointer;
        }

        .berun-card-panel {
            background: var(--bg-white);
            border-radius: var(--card-radius);
            padding: 28px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .berun-panel-title { font-size: 18px; font-weight: 800; color: #111827; margin: 0; letter-spacing: -0.3px; }
        .berun-panel-sub   { font-size: 12px; color: #6b7280; margin: 2px 0 0 0; font-weight: 500; }

        .status-dot-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
        }

        .dot-green { background: #ecfdf5; color: #047857; }
        .dot-green::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background-color: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.6); }

        .dot-amber { background: #fffbeb; color: #b45309; }
        .dot-amber::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background-color: #f59e0b; }

        @media (max-width: 768px) {
            body { padding: 14px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
            .search-pill-group { width: 100%; }
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
                    <h1 class="berun-greeting-h1">College Students Roster</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Directory of enrolled students registered at <?= htmlspecialchars((string)$college_name) ?></p>
                </div>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Organizer_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <!-- 2 KPI MINI SUMMARY CARDS -->
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-mini-card">
                            <div class="kpi-mini-icon icon-indigo"><i class="bi bi-mortarboard"></i></div>
                            <div>
                                <h3 class="kpi-mini-val"><?= number_format(count($students)) ?></h3>
                                <p class="kpi-mini-lbl">Enrolled Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-mini-card">
                            <div class="kpi-mini-icon icon-emerald"><i class="bi bi-person-check"></i></div>
                            <div>
                                <h3 class="kpi-mini-val"><?= number_format($count_active) ?></h3>
                                <p class="kpi-mini-lbl">Active Accounts</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONTROL & FILTER TOOLBAR -->
                <div class="filter-toolbar">
                    <div class="d-flex align-items-center gap-3 flex-wrap flex-grow-1">
                        <div class="search-pill-group">
                            <i class="bi bi-search text-muted"></i>
                            <input type="text" id="studentSearchInput" placeholder="Search student name, enrollment no, email...">
                        </div>
                        <select class="filter-select" id="semesterFilter">
                            <option value="all">All Semesters</option>
                            <?php for ($i=1; $i<=8; $i++): ?>
                                <option value="sem <?= $i ?>">Semester <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- STUDENTS LIST PANEL -->
                <div class="berun-card-panel">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div>
                            <h3 class="berun-panel-title">Master Enrolled Student Directory</h3>
                            <p class="berun-panel-sub">Total <?= count($students) ?> active student records registered under your college</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;" id="studentsTable">
                            <thead>
                                <tr class="text-muted font-bold" style="font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase;">
                                    <th class="ps-3">#</th>
                                    <th>Student</th>
                                    <th>Enrollment No</th>
                                    <th>Email Address</th>
                                    <th>Phone Number</th>
                                    <th>Gender</th>
                                    <th>Semester</th>
                                    <th>Account Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="bi bi-people fs-1 text-muted opacity-50 d-block mb-2"></i>
                                            No enrolled students found for your college in the database.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $idx => $s): ?>
                                        <tr class="student-table-row"
                                            data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>"
                                            data-enrollment="<?= htmlspecialchars(strtolower($s['enrollment_no'] ?? '')) ?>"
                                            data-email="<?= htmlspecialchars(strtolower($s['email'])) ?>"
                                            data-semester="sem <?= htmlspecialchars((string)($s['semester'] ?? '1')) ?>">
                                            
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
                                            <td><span class="badge bg-light text-dark rounded-pill px-3 py-1 border font-semibold">Sem <?= htmlspecialchars((string)($s['semester'] ?? '1')) ?></span></td>
                                            <td>
                                                <?php if (($s['account_status'] ?? 'active') === 'active'): ?>
                                                    <span class="status-dot-pill dot-green">Active</span>
                                                <?php else: ?>
                                                    <span class="status-dot-pill dot-amber">Inactive</span>
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

    <!-- Real-time Filter JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput    = document.getElementById('studentSearchInput');
            const semesterFilter = document.getElementById('semesterFilter');

            function applyStudentFilters() {
                const query = searchInput.value.toLowerCase().trim();
                const sem   = semesterFilter.value.toLowerCase();

                document.querySelectorAll('.student-table-row').forEach(row => {
                    const name       = row.dataset.name || '';
                    const enrollment = row.dataset.enrollment || '';
                    const email      = row.dataset.email || '';
                    const semester   = row.dataset.semester || '';

                    const matchesSearch = name.includes(query) || enrollment.includes(query) || email.includes(query);
                    const matchesSem    = sem === 'all' || semester === sem;

                    if (matchesSearch && matchesSem) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            }

            if (searchInput)    searchInput.addEventListener('input', applyStudentFilters);
            if (semesterFilter) semesterFilter.addEventListener('change', applyStudentFilters);
        });
    </script>
</body>
</html>
