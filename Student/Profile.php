<?php
require_once __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();

/**
 * Student/Profile.php
 * Student Account Details & Password Security Hub
 */
include 'student_auth.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../includes/profile_helper.php';

$profile_flash  = null;
$password_flash = null;
$active_tab = ($_GET['tab'] ?? '') === 'security' ? 'security' : 'profile';

// Fetch Current Student Data
$sid = (int)$student_id;
$res_st = mysqli_query($conn, "
    SELECT s.*, c.name AS college_name 
    FROM students s 
    LEFT JOIN colleges c ON s.college_id = c.college_id 
    WHERE s.student_id = $sid 
    LIMIT 1
");
$student_data = $res_st ? mysqli_fetch_assoc($res_st) : null;

if (!$student_data) { header('Location: ' . tab_context_url('Logout.php')); exit; }
$profile_errors = [];
$password_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = is_string($_POST['form_type'] ?? null) ? $_POST['form_type'] : '';
    if ($form_type === 'update_profile') {
        $active_tab = 'profile';
        $result = save_account_profile($conn, 'student', $sid, $student_data, $_POST, $_FILES['profile_image'] ?? null);
        $profile_errors = $result['errors'];
        if ($profile_errors) {
            $student_data = array_replace($student_data, $result['data']);
        } else {
            $student_data = $result['data'];
            $profile_flash = 'Your profile has been updated.';
            $student_name = $student_data['name'];
                $student_email = $student_data['email'];
        }
    } elseif ($form_type === 'change_password') {
        $active_tab = 'security';
        $password_errors = save_profile_password($conn, 'student', $sid, $student_data, $_POST);
        if (!$password_errors) { $password_flash = 'Your password has been updated.'; }
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

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/fonts/bootstrap-icons/bootstrap-icons.min.css">

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
    <link rel="stylesheet" href="../assets/css/evenza-premium.css">
    <link rel="stylesheet" href="../assets/css/profile-editor.css">
    <script src="../assets/js/tab-auth.js"></script>
    <link rel="stylesheet" href="../assets/css/inline-validation.css">
    <script src="../assets/js/inline-validation.js" defer></script>
</head>

<body>

    <div class="berun-window">

        <!-- Top Header Navigation Bar -->
        <header class="berun-header">
            <div class="d-flex align-items-center gap-4">
                <a href="Dashboard.php" class="d-inline-flex align-items-center text-decoration-none">
                    <img src="../assets/images/evenza-logo.svg" alt="Evenza Logo" height="38" style="height: 38px; width: auto;" />
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
                                    <?php if ($profile_errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($profile_errors as $message): ?><li><?= htmlspecialchars((string)$message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

                                    <?php $profile_role = 'student'; $profile_record = $student_data; require __DIR__ . '/../includes/profile_form.php'; ?>
                                </div>
                            </div>

                            <!-- TAB 2: SECURITY & PASSWORD -->
                            <div class="tab-pane fade <?= $active_tab === 'security' ? 'show active' : '' ?>" id="pills-security">
<?php if ($password_errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($password_errors as $message): ?><li><?= htmlspecialchars((string)$message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                                <?php if ($password_flash): ?>
                                    <div class="alert alert-success rounded-4 border-0 p-3 mb-4 text-xs font-semibold">
                                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($password_flash) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="berun-card-panel">
                                    <h4 class="fw-black text-dark mb-3 fs-5">Change Account Password</h4>

                                    <form method="POST">
                                        <input type="hidden" name="form_type" value="change_password">
<input type="hidden" name="profile_csrf" value="<?= htmlspecialchars(profile_csrf_token()) ?>">

                                        <div class="mb-3">
                                            <label class="form-label font-bold text-xs text-dark">Current Password *</label>
                                            <input type="password" class="form-control rounded-pill px-3 py-2 text-xs" name="current_password" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label font-bold text-xs text-dark">New Password *</label>
                                            <input type="password" class="form-control rounded-pill px-3 py-2 text-xs" name="new_password" minlength="8" required>
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
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/profile-editor.js"></script>
</body>
</html>
