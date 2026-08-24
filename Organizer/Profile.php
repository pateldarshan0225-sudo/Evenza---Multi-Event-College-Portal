<?php
/**
 * Organizer/Profile.php
 * Merged College Profile & Security Settings Hub
 */
include 'organizer_auth.php';
include 'connection.php';

$profile_errors  = [];
$password_errors = [];
$profile_flash   = null;
$password_flash  = null;

$active_tab = $_GET['tab'] ?? 'profile';

// Fetch Current Stored College Data
$stmt = $pdo->prepare("SELECT c.*, u.name AS university_name FROM colleges c LEFT JOIN universities u ON c.university_id = u.university_id WHERE c.college_id = :cid LIMIT 1");
$stmt->execute(['cid' => $college_id]);
$college_data = $stmt->fetch();
$storedPassword = $college_data['password'] ?? null;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'] ?? 'update_profile';

    if ($form_type === 'update_profile') {
        $active_tab = 'profile';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validation
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors['email'] = 'Please enter a valid contact email address.';
        }
        if ($phone === '') {
            $profile_errors['phone'] = 'Phone number is required.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $profile_errors['phone'] = 'Please enter a valid phone number (7-20 digits).';
        }

        if (empty($profile_errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE colleges SET email = :email, phone = :phone WHERE college_id = :cid");
                $stmt->execute(['email' => $email, 'phone' => $phone, 'cid' => $college_id]);

                $_SESSION['college_email'] = $email;
                $college_data['email'] = $email;
                $college_data['phone'] = $phone;
                $profile_flash = ['type' => 'success', 'message' => 'College contact details updated successfully!'];
            } catch (PDOException $e) {
                $profile_flash = ['type' => 'danger', 'message' => 'Error updating profile: ' . $e->getMessage()];
            }
        }
    } elseif ($form_type === 'change_password') {
        $active_tab = 'security';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $password_errors['current_password'] = 'Please enter your current password.';
        } elseif ($storedPassword !== null && $currentPassword !== $storedPassword && !password_verify($currentPassword, $storedPassword)) {
            $password_errors['current_password'] = 'Current password is incorrect.';
        }

        if ($newPassword === '') {
            $password_errors['new_password'] = 'Please enter a new password.';
        } elseif (strlen($newPassword) < 8) {
            $password_errors['new_password'] = 'Password must be at least 8 characters long.';
        } elseif ($currentPassword !== '' && $newPassword === $currentPassword) {
            $password_errors['new_password'] = 'New password cannot be the same as your current password.';
        }

        if ($confirmPassword === '') {
            $password_errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $password_errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($password_errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE colleges SET password = :pass WHERE college_id = :cid");
                $stmt->execute(['pass' => $newPassword, 'cid' => $college_id]);
                $storedPassword = $newPassword;
                $password_flash = ['type' => 'success', 'message' => 'Your security password has been updated successfully!'];
            } catch (PDOException $e) {
                $password_flash = ['type' => 'danger', 'message' => 'Error updating password: ' . $e->getMessage()];
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>College Profile & Security | Organizer Portal</title>

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

        .berun-btn-dark {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 12px 26px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(20, 23, 26, 0.12);
        }
        .berun-btn-dark:hover { background-color: var(--bg-dark-hover); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(20, 23, 26, 0.2); }

        .berun-layout-body { display: flex; gap: 28px; }
        .berun-main-grid { flex-grow: 1; display: flex; flex-direction: column; gap: 24px; }

        /* Panel Container */
        .berun-card-panel {
            background: var(--bg-white);
            border-radius: var(--card-radius);
            padding: 32px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .berun-panel-title { font-size: 18px; font-weight: 800; color: #111827; margin: 0; letter-spacing: -0.3px; }
        .berun-panel-sub   { font-size: 12px; color: #6b7280; margin: 2px 0 0 0; font-weight: 500; }

        /* Nav Pills Styling */
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
            box-shadow: 0 4px 12px rgba(20, 23, 26, 0.15);
        }

        .form-label { font-weight: 700; color: #1e2937; font-size: 13px; margin-bottom: 8px; }

        .form-control {
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            padding: 10px 20px;
            font-size: 14px;
            color: #1e2937;
        }
        .form-control:focus {
            border-color: var(--bg-dark);
            box-shadow: 0 0 0 3px rgba(28, 32, 36, 0.08);
        }

        /* Password Group Input */
        .berun-input-group {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 4px 6px 4px 20px;
            transition: all 0.2s ease;
        }

        .berun-input-group:focus-within {
            border-color: var(--bg-dark);
            box-shadow: 0 0 0 3px rgba(28, 32, 36, 0.08);
        }

        .berun-input-group.is-invalid-group {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
        }

        .berun-input-group .form-control {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 9px 0 !important;
            font-size: 14px;
            font-weight: 500;
            color: #1e2937;
        }

        .berun-input-group .btn-eye {
            border: none !important;
            background: transparent !important;
            color: #94a3b8;
            font-size: 16px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Password Strength Bar */
        .strength-wrap {
            height: 6px;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 8px;
        }

        .strength-wrap .bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 9999px;
        }

        .rule-item {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .rule-item.valid {
            color: #10b981;
            font-weight: 600;
        }
        .rule-item.valid i::before {
            content: "\F26B";
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
                <a href="Dashboard.php" class="d-inline-flex align-items-center text-decoration-none">
                    <img src="../assets/images/evenza-logo.svg" alt="Evenza Logo" height="38" style="height: 38px; width: auto;" />
                </a>

                <div class="ps-2">
                    <h1 class="berun-greeting-h1">College Profile & Security</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Manage profile contact details and update account security password for <?= htmlspecialchars((string)$college_name) ?></p>
                </div>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Organizer_Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <div class="row justify-content-center">
                    <div class="col-12 col-lg-9 col-xl-8">

                        <!-- TAB NAVIGATION PILLS -->
                        <div class="d-flex justify-content-center">
                            <ul class="nav nav-pills nav-pills-custom" id="profileTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $active_tab === 'profile' ? 'active' : '' ?>" id="pills-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-profile" type="button" role="tab">
                                        <i class="bi bi-building me-1"></i> Profile & Contact
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $active_tab === 'security' ? 'active' : '' ?>" id="pills-security-tab" data-bs-toggle="pill" data-bs-target="#pills-security" type="button" role="tab">
                                        <i class="bi bi-shield-lock me-1"></i> Security & Password
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="tab-content" id="profileTabContent">

                            <!-- TAB 1: COLLEGE PROFILE & CONTACT -->
                            <div class="tab-pane fade <?= $active_tab === 'profile' ? 'show active' : '' ?>" id="pills-profile" role="tabpanel">
                                
                                <?php if ($profile_flash): ?>
                                    <div class="alert alert-<?= htmlspecialchars($profile_flash['type']) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($profile_flash['message']) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($profile_errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <strong>Please fix the errors below:</strong>
                                        <ul class="mb-0 mt-2 ps-3 text-xs">
                                            <?php foreach ($profile_errors as $err): ?>
                                                <li><?= htmlspecialchars((string)$err) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="berun-card-panel">
                                    <div class="d-flex align-items-center gap-3 pb-3 mb-4 border-bottom">
                                        <div class="bg-primary-subtle text-primary p-3 rounded-4 fs-3">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <div>
                                            <h3 class="berun-panel-title"><?= htmlspecialchars((string)$college_data['name']) ?></h3>
                                            <p class="berun-panel-sub">Affiliated to: <strong><?= htmlspecialchars((string)($college_data['university_name'] ?? 'Main University')) ?></strong></p>
                                        </div>
                                    </div>

                                    <form method="POST" class="needs-validation" novalidate id="profileForm">
                                        <input type="hidden" name="form_type" value="update_profile">

                                        <div class="mb-4">
                                            <label class="form-label">College Name</label>
                                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars((string)$college_data['name']) ?>" readonly disabled>
                                            <small class="text-muted text-xs mt-1 d-block">College name is managed by platform administrator.</small>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label" for="email">Contact Email Address *</label>
                                            <input type="email" class="form-control <?= isset($profile_errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? $college_data['email'])) ?>" required>
                                            <div class="invalid-feedback text-xs ps-2 mt-1">Please enter a valid email address.</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label" for="phone">Phone Number *</label>
                                            <input type="tel" class="form-control <?= isset($profile_errors['phone']) ? 'is-invalid' : '' ?>" name="phone" id="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? $college_data['phone'])) ?>" required pattern="^[0-9+\-\s()]{7,20}$">
                                            <div class="invalid-feedback text-xs ps-2 mt-1">Please enter a valid phone number (7-20 digits).</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">Account Status</label>
                                            <div>
                                                <?php if ($college_data['status'] === 'active'): ?>
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fs-6"><i class="bi bi-check-circle-fill me-1"></i> Active Partner College</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 fs-6"><i class="bi bi-x-circle-fill me-1"></i> Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                                            <a href="Dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 font-semibold">Cancel</a>
                                            <button type="submit" class="berun-btn-dark">
                                                <i class="bi bi-save me-1"></i> Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- TAB 2: SECURITY & PASSWORD UPDATE -->
                            <div class="tab-pane fade <?= $active_tab === 'security' ? 'show active' : '' ?>" id="pills-security" role="tabpanel">

                                <?php if ($password_flash): ?>
                                    <div class="alert alert-<?= htmlspecialchars($password_flash['type']) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($password_flash['message']) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($password_errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <strong>Please fix the security errors below:</strong>
                                        <ul class="mb-0 mt-2 ps-3 text-xs">
                                            <?php foreach ($password_errors as $err): ?>
                                                <li><?= htmlspecialchars((string)$err) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="berun-card-panel">
                                    <div class="d-flex align-items-center gap-3 pb-3 mb-4 border-bottom">
                                        <div class="bg-primary-subtle text-primary p-3 rounded-4 fs-3">
                                            <i class="bi bi-shield-lock"></i>
                                        </div>
                                        <div>
                                            <h3 class="berun-panel-title">Update Account Password</h3>
                                            <p class="berun-panel-sub">Choose a strong password to keep your college portal secure</p>
                                        </div>
                                    </div>

                                    <form method="POST" class="needs-validation" novalidate id="changePasswordForm">
                                        <input type="hidden" name="form_type" value="change_password">

                                        <div class="mb-4">
                                            <label class="form-label" for="current_password">Current Password *</label>
                                            <div class="berun-input-group <?= isset($password_errors['current_password']) ? 'is-invalid-group' : '' ?>">
                                                <input type="password" class="form-control" name="current_password" id="current_password" required placeholder="Enter current password">
                                                <button class="btn-eye toggle-pwd" type="button" data-target="current_password"><i class="bi bi-eye"></i></button>
                                            </div>
                                            <div class="invalid-feedback text-xs ps-2 mt-1">Please enter your current password.</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label" for="new_password">New Password *</label>
                                            <div class="berun-input-group <?= isset($password_errors['new_password']) ? 'is-invalid-group' : '' ?>">
                                                <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8" placeholder="Enter new password">
                                                <button class="btn-eye toggle-pwd" type="button" data-target="new_password"><i class="bi bi-eye"></i></button>
                                            </div>
                                            <div class="invalid-feedback text-xs ps-2 mt-1">Password must be at least 8 characters.</div>

                                            <!-- Strength Meter -->
                                            <div class="strength-wrap mt-3">
                                                <div id="strengthBar" class="bar"></div>
                                            </div>
                                            <small id="strengthLabel" class="text-muted font-medium d-block mt-1">Password strength</small>

                                            <!-- Rule Checklist -->
                                            <div class="mt-3">
                                                <div class="rule-item" id="ruleMin"><i class="bi bi-circle"></i> At least 8 characters</div>
                                                <div class="rule-item" id="ruleUpper"><i class="bi bi-circle"></i> At least 1 uppercase letter</div>
                                                <div class="rule-item" id="ruleNumber"><i class="bi bi-circle"></i> At least 1 number</div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label" for="confirm_password">Confirm New Password *</label>
                                            <div class="berun-input-group <?= isset($password_errors['confirm_password']) ? 'is-invalid-group' : '' ?>">
                                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="8" placeholder="Re-enter new password">
                                                <button class="btn-eye toggle-pwd" type="button" data-target="confirm_password"><i class="bi bi-eye"></i></button>
                                            </div>
                                            <div class="invalid-feedback text-xs ps-2 mt-1" id="confirmFeedback">Please confirm your new password.</div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                                            <a href="Dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 font-semibold">Cancel</a>
                                            <button type="submit" class="berun-btn-dark">
                                                <i class="bi bi-save me-1"></i> Update Password
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

    <!-- Client-Side Form Validation & Live Password Rules JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Profile Form Submit Validation
            const profileForm = document.getElementById('profileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    if (!this.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    this.classList.add('was-validated');
                });
            }

            // Password Form Submit & Strength Validation
            const passwordForm = document.getElementById('changePasswordForm');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const strengthLabel = document.getElementById('strengthLabel');

            const ruleMin = document.getElementById('ruleMin');
            const ruleUpper = document.getElementById('ruleUpper');
            const ruleNumber = document.getElementById('ruleNumber');

            if (newPassword) {
                newPassword.addEventListener('input', function() {
                    const val = this.value;
                    let score = 0;

                    const hasMin = val.length >= 8;
                    const hasUpper = /[A-Z]/.test(val);
                    const hasNumber = /[0-9]/.test(val);

                    if (hasMin) { ruleMin.classList.add('valid'); score++; } else { ruleMin.classList.remove('valid'); }
                    if (hasUpper) { ruleUpper.classList.add('valid'); score++; } else { ruleUpper.classList.remove('valid'); }
                    if (hasNumber) { ruleNumber.classList.add('valid'); score++; } else { ruleNumber.classList.remove('valid'); }

                    if (val.length === 0) {
                        strengthBar.style.width = '0%';
                        strengthLabel.textContent = 'Password strength';
                    } else if (score === 1) {
                        strengthBar.style.width = '33%';
                        strengthBar.style.backgroundColor = '#dc3545';
                        strengthLabel.textContent = 'Weak password';
                    } else if (score === 2) {
                        strengthBar.style.width = '66%';
                        strengthBar.style.backgroundColor = '#fd7e14';
                        strengthLabel.textContent = 'Medium password';
                    } else if (score === 3) {
                        strengthBar.style.width = '100%';
                        strengthBar.style.backgroundColor = '#10b981';
                        strengthLabel.textContent = 'Strong password';
                    }
                });
            }

            if (confirmPassword && newPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (this.value !== newPassword.value) {
                        this.setCustomValidity('Passwords do not match.');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    if (confirmPassword.value !== newPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match.');
                    }

                    if (!this.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    this.classList.add('was-validated');
                });
            }

            // Eye Toggle
            document.querySelectorAll('.toggle-pwd').forEach(btn => {
                btn.addEventListener('click', function() {
                    const input = document.getElementById(this.dataset.target);
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'bi bi-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'bi bi-eye';
                    }
                });
            });
        });
    </script>
</body>
</html>
