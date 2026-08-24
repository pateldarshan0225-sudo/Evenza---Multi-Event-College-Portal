<?php
/**
 * Student/Profile.php
 * Student Account Details & Password Security Hub
 */
include 'student_auth.php';
include 'connection.php';

$profile_flash  = null;
$password_flash = null;
$active_tab     = $_GET['tab'] ?? 'profile';

// Fetch Current Student Data
$stmt = $pdo->prepare("
    SELECT s.*, c.name AS college_name 
    FROM students s 
    LEFT JOIN colleges c ON s.college_id = c.college_id 
    WHERE s.student_id = :sid 
    LIMIT 1
");
$stmt->execute(['sid' => $student_id]);
$student_data = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'] ?? 'update_profile';

    if ($form_type === 'update_profile') {
        $active_tab = 'profile';
        $phone    = trim($_POST['phone'] ?? '');
        $semester = (int)($_POST['semester'] ?? 1);

        if ($phone !== '') {
            $stmt = $pdo->prepare("UPDATE students SET phone = :phone, semester = :sem WHERE student_id = :sid");
            $stmt->execute(['phone' => $phone, 'sem' => $semester, 'sid' => $student_id]);
            $student_data['phone']    = $phone;
            $student_data['semester'] = $semester;
            $profile_flash = 'Account contact details updated successfully!';
        }
    } elseif ($form_type === 'change_password') {
        $active_tab = 'security';
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if ($currentPass && $newPass && $newPass === $confirmPass && strlen($newPass) >= 6) {
            $stmt = $pdo->prepare("UPDATE students SET password = :pass WHERE student_id = :sid");
            $stmt->execute(['pass' => $newPass, 'sid' => $student_id]);
            $password_flash = 'Password security updated successfully!';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile & Security | Evenza Student Portal</title>

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

        .berun-card-panel {
            background: var(--bg-white);
            border-radius: var(--card-radius);
            padding: 32px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .nav-pills-custom {
            background: #f9f8f4;
            padding: 6px;
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            gap: 4px;
            margin-bottom: 24px;
        }

        .nav-pills-custom .nav-link {
            border-radius: 9999px;
            padding: 9px 24px;
            font-size: 13px;
            font-weight: 700;
            color: #6b7280;
            transition: all 0.2s ease;
            border: none;
        }

        .nav-pills-custom .nav-link.active {
            background-color: var(--bg-dark) !important;
            color: #ffffff !important;
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
                    <h1 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">Student Profile & Security</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Manage student profile details and update account security password</p>
                </div>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Student_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <div class="row justify-content-center">
                    <div class="col-12 col-lg-8">

                        <div class="d-flex justify-content-center">
                            <ul class="nav nav-pills nav-pills-custom" id="profileTab">
                                <li class="nav-item">
                                    <button class="nav-link <?= $active_tab === 'profile' ? 'active' : '' ?>" id="pills-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-profile" type="button">
                                        <i class="bi bi-person-badge me-1"></i> Student Details
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link <?= $active_tab === 'security' ? 'active' : '' ?>" id="pills-security-tab" data-bs-toggle="pill" data-bs-target="#pills-security" type="button">
                                        <i class="bi bi-shield-lock me-1"></i> Password & Security
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="tab-content" id="profileTabContent">

                            <!-- TAB 1: STUDENT PROFILE -->
                            <div class="tab-pane fade <?= $active_tab === 'profile' ? 'show active' : '' ?>" id="pills-profile">
                                <?php if ($profile_flash): ?>
                                    <div class="alert alert-success rounded-4 border-0 p-3 mb-4 text-xs font-semibold">
                                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($profile_flash) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="berun-card-panel">
                                    <h4 class="fw-black text-dark mb-3 fs-5">My Student Information</h4>

                                    <form method="POST">
                                        <input type="hidden" name="form_type" value="update_profile">

                                        <div class="row g-3 text-xs mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label font-bold text-dark mb-1">Full Name</label>
                                                <input type="text" class="form-control rounded-pill px-3 py-2 bg-light" value="<?= htmlspecialchars((string)$student_data['name']) ?>" readonly disabled>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label font-bold text-dark mb-1">Email Address</label>
                                                <input type="email" class="form-control rounded-pill px-3 py-2 bg-light" value="<?= htmlspecialchars((string)$student_data['email']) ?>" readonly disabled>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label font-bold text-dark mb-1">Enrollment Roll No</label>
                                                <input type="text" class="form-control rounded-pill px-3 py-2 bg-light" value="<?= htmlspecialchars((string)($student_data['enrollment_no'] ?? 'STU-001')) ?>" readonly disabled>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label font-bold text-dark mb-1">Enrolled College</label>
                                                <input type="text" class="form-control rounded-pill px-3 py-2 bg-light" value="<?= htmlspecialchars((string)($student_data['college_name'] ?? 'Partner Institution')) ?>" readonly disabled>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label font-bold text-dark mb-1">Phone Number *</label>
                                                <input type="tel" class="form-control rounded-pill px-3 py-2" name="phone" value="<?= htmlspecialchars((string)($student_data['phone'] ?? '')) ?>" required>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label font-bold text-dark mb-1">Current Semester *</label>
                                                <select class="form-select rounded-pill px-3 py-2 text-xs" name="semester">
                                                    <?php for ($s = 1; $s <= 8; $s++): ?>
                                                        <option value="<?= $s ?>" <?= ($student_data['semester'] ?? 1) == $s ? 'selected' : '' ?>>Semester <?= $s ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="pt-2 text-end">
                                            <button type="submit" class="btn btn-dark rounded-pill px-4 py-2.5 text-xs font-bold">
                                                Save Contact Details
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- TAB 2: SECURITY & PASSWORD -->
                            <div class="tab-pane fade <?= $active_tab === 'security' ? 'show active' : '' ?>" id="pills-security">
                                <?php if ($password_flash): ?>
                                    <div class="alert alert-success rounded-4 border-0 p-3 mb-4 text-xs font-semibold">
                                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($password_flash) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="berun-card-panel">
                                    <h4 class="fw-black text-dark mb-3 fs-5">Change Account Password</h4>

                                    <form method="POST">
                                        <input type="hidden" name="form_type" value="change_password">

                                        <div class="mb-3">
                                            <label class="form-label font-bold text-xs text-dark">Current Password *</label>
                                            <input type="password" class="form-control rounded-pill px-3 py-2 text-xs" name="current_password" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label font-bold text-xs text-dark">New Password *</label>
                                            <input type="password" class="form-control rounded-pill px-3 py-2 text-xs" name="new_password" minlength="6" required>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label font-bold text-xs text-dark">Confirm New Password *</label>
                                            <input type="password" class="form-control rounded-pill px-3 py-2 text-xs" name="confirm_password" required>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" class="btn btn-warning rounded-pill px-4 py-2.5 text-xs font-bold text-dark border border-dark">
                                                Update Password Security
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
