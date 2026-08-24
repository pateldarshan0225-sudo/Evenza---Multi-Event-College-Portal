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

$errors  = [];
$success = false;

$admin_id = $_SESSION['admin_id'] ?? 0;

/* Fetch current admin password */
$stmt = $pdo->prepare('SELECT password FROM admins WHERE admin_id = :id');
$stmt->execute(['id' => $admin_id]);
$adminRow = $stmt->fetch();
$storedPassword = $adminRow['password'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Current password check
    if ($storedPassword === null) {
        $errors['current_password'] = 'Admin account not found.';
    } elseif ($currentPassword === '') {
        $errors['current_password'] = 'Please enter your current password.';
    } elseif ($currentPassword !== $storedPassword) {
        // NOTE: plain-text comparison (DB me plain password store hai)
        $errors['current_password'] = 'Current password is incorrect.';
    }

    // New password checks
    if ($newPassword === '') {
        $errors['new_password'] = 'Please enter a new password.';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $errors['new_password'] = 'Password must include at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $errors['new_password'] = 'Password must include at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $errors['new_password'] = 'Password must include at least one number.';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
        $errors['new_password'] = 'Password must include at least one special character.';
    } elseif ($storedPassword !== null && $newPassword === $storedPassword) {
        $errors['new_password'] = 'New password must be different from the current password.';
    }

    // Confirm password check
    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $upd = $pdo->prepare('UPDATE admins SET password = :pass WHERE admin_id = :id');
            $upd->execute(['pass' => $newPassword, 'id' => $admin_id]);
            $success = true;
            $storedPassword = $newPassword;
        } catch (PDOException $e) {
            $errors['current_password'] = 'Something went wrong. Please try again.';
        }
    }
}

$admin_email = $_SESSION['admin_email'] ?? 'admin@evenza.com';
$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password | Evenza Admin</title>

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
            box-shadow: 0 4px 14px rgba(28, 32, 36, 0.15);
        }
        .berun-btn-dark:hover { background-color: #2e343b; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(28, 32, 36, 0.25); }

        .berun-btn-cancel {
            background-color: #ffffff;
            color: #475569 !important;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .berun-btn-cancel:hover { background-color: #f8fafc; color: #0f172a !important; border-color: #cbd5e1; }

        /* Layout Grid with Left Floating Sidebar Pod */
        .berun-layout-body {
            display: flex;
            gap: 28px;
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
            border-radius: 26px;
            padding: 32px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .berun-panel-title { font-size: 18px; font-weight: 700; color: var(--color-text-dark); margin: 0; }
        .berun-panel-sub   { font-size: 12px; color: var(--color-text-muted); margin: 2px 0 0 0; font-weight: 500; }

        .header-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: #e8edff;
            color: #4f46e5;
        }

        /* Seamless Input Group & Eye Button Styling */
        .form-label { font-weight: 700; color: #1e2937; font-size: 13px; margin-bottom: 8px; }

        .berun-input-group {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 4px 6px 4px 20px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.015);
            position: relative;
        }

        .berun-input-group:focus-within {
            border-color: var(--color-text-dark);
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
            border-radius: 0 !important;
            width: 100%;
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
            transition: all 0.2s ease;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
        }

        .berun-input-group .btn-eye:hover {
            color: #1e2937;
            background: rgba(0, 0, 0, 0.05) !important;
        }

        /* Strength Bar & Live Rules */
        .strength-wrap { height: 6px; border-radius: 20px; background: #e2e8f0; overflow: hidden; }
        .strength-wrap .bar { height: 100%; width: 0; transition: 0.3s ease; border-radius: 20px; }
        .rule-item { transition: 0.2s ease; font-size: 12px; font-weight: 500; }

        /* Modern Pastel Info Callout Note */
        .berun-info-note {
            background-color: #f9f8f4;
            border: 1px solid #ebe6d8;
            border-radius: 20px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .berun-info-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background-color: #e0e7ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            body { padding: 12px; }
            .berun-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .berun-layout-body { flex-direction: column; gap: 20px; }
            .berun-card-panel { padding: 20px; }
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
                    <h1 class="berun-greeting-h1">Change Password</h1>
                    <p class="mb-0 text-muted" style="font-size: 13px; font-weight: 500;">
                        Update your account password to keep it secure
                    </p>
                    <ul class="custom-breadcrumb">
                        <li><a href="Dashboard.php">Home</a></li>
                        <li>Admin Settings</li>
                        <li>Change Password</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="Dashboard.php" class="berun-btn-dark">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </header>

        <!-- Main Body Area with Left Floating Sidebar Pod -->
        <div class="berun-layout-body">

            <!-- Left Floating Sidebar Capsule Nav -->
            <?php include 'Sidebar.php'; ?>

            <!-- Main Content Grid -->
            <div class="berun-main-grid">

                <div class="row justify-content-center">
                    <div class="col-12 col-lg-8 col-xl-7">

                        <!-- Success Notification Alert -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="background-color: #e8f7ef; color: #198754;">
                                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                                <span class="font-semibold">Your password has been changed successfully.</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Errors Notification Alert -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert" style="background-color: #fde8e8; color: #dc3545;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                    <strong>Please fix the following issues:</strong>
                                </div>
                                <ul class="mb-0 mt-2 ps-4 text-xs font-medium">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= htmlspecialchars((string)$err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Change Password Card Panel -->
                        <div class="berun-card-panel">

                            <div class="d-flex align-items-center gap-3 pb-3 mb-4 border-bottom" style="border-color:#f4f2eb !important;">
                                <div class="header-icon"><i class="bi bi-shield-lock"></i></div>
                                <div>
                                    <h3 class="berun-panel-title">Update Your Password</h3>
                                    <p class="berun-panel-sub">Choose a strong, unique password for your account</p>
                                </div>
                            </div>

                            <form action="" method="post" id="changePasswordForm" class="needs-validation" novalidate>

                                <!-- Current Password -->
                                <div class="mb-4">
                                    <label class="form-label" for="current_password">
                                        Current Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="berun-input-group <?= isset($errors['current_password']) ? 'is-invalid-group' : '' ?>">
                                        <input type="password"
                                            class="form-control"
                                            name="current_password" id="current_password" required placeholder="Re-enter current password">
                                        <button class="btn-eye toggle-password" type="button" data-target="current_password" title="Toggle Visibility">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['current_password'])): ?>
                                        <div class="text-danger text-xs mt-1 font-semibold"><?= htmlspecialchars((string)$errors['current_password']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <hr class="my-4" style="border-color: #f4f2eb;">

                                <!-- New Password -->
                                <div class="mb-4">
                                    <label class="form-label" for="new_password">
                                        New Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="berun-input-group <?= isset($errors['new_password']) ? 'is-invalid-group' : '' ?>">
                                        <input type="password"
                                            class="form-control"
                                            name="new_password" id="new_password" minlength="8" required placeholder="Enter new password">
                                        <button class="btn-eye toggle-password" type="button" data-target="new_password" title="Toggle Visibility">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['new_password'])): ?>
                                        <div class="text-danger text-xs mt-1 font-semibold"><?= htmlspecialchars((string)$errors['new_password']) ?></div>
                                    <?php endif; ?>

                                    <!-- Strength meter -->
                                    <div class="strength-wrap mt-3">
                                        <div id="strengthBar" class="bar"></div>
                                    </div>
                                    <small id="strengthLabel" class="text-muted font-medium d-block mt-1">Password strength</small>

                                    <!-- Live requirement checklist -->
                                    <ul class="list-unstyled small mt-3 mb-0" id="passwordRules">
                                        <li id="rule-length" class="rule-item text-muted mb-1"><i class="bi bi-circle me-1"></i> At least 8 characters</li>
                                        <li id="rule-upper" class="rule-item text-muted mb-1"><i class="bi bi-circle me-1"></i> One uppercase letter</li>
                                        <li id="rule-lower" class="rule-item text-muted mb-1"><i class="bi bi-circle me-1"></i> One lowercase letter</li>
                                        <li id="rule-number" class="rule-item text-muted mb-1"><i class="bi bi-circle me-1"></i> One number</li>
                                        <li id="rule-special" class="rule-item text-muted mb-1"><i class="bi bi-circle me-1"></i> One special character</li>
                                    </ul>
                                </div>

                                <!-- Confirm Password -->
                                <div class="mb-4">
                                    <label class="form-label" for="confirm_password">
                                        Confirm New Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="berun-input-group <?= isset($errors['confirm_password']) ? 'is-invalid-group' : '' ?>" id="confirmGroup">
                                        <input type="password"
                                            class="form-control"
                                            name="confirm_password" id="confirm_password" required placeholder="Re-enter new password">
                                        <button class="btn-eye toggle-password" type="button" data-target="confirm_password" title="Toggle Visibility">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="text-danger text-xs mt-1 font-semibold d-none" id="confirmFeedback">Passwords do not match.</div>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="text-danger text-xs mt-1 font-semibold"><?= htmlspecialchars((string)$errors['confirm_password']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Security Note Box -->
                                <div class="berun-info-note mb-4">
                                    <div class="berun-info-icon">
                                        <i class="bi bi-info-circle"></i>
                                    </div>
                                    <div class="small text-muted font-medium" style="line-height: 1.5; font-size: 13px;">
                                        Use a unique password you don't use elsewhere. You'll be asked to sign in again on other devices after changing it.
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex align-items-center justify-content-end gap-3 mt-4">
                                    <a href="Dashboard.php" class="berun-btn-cancel">Cancel</a>
                                    <button type="submit" class="berun-btn-dark">
                                        <i class="bi bi-box-arrow-in-down-right"></i> Update Password
                                    </button>
                                </div>

                            </form>

                        </div>

                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Password strength meter, live rules, validation & eye toggle JS -->
    <script>
        (function () {
            'use strict';

            const form            = document.getElementById('changePasswordForm');
            const newPassword     = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const strengthBar     = document.getElementById('strengthBar');
            const strengthLabel   = document.getElementById('strengthLabel');
            const confirmFeedback = document.getElementById('confirmFeedback');
            const confirmGroup    = document.getElementById('confirmGroup');

            const rules = {
                length:  { test: v => v.length >= 8,          el: document.getElementById('rule-length') },
                upper:   { test: v => /[A-Z]/.test(v),        el: document.getElementById('rule-upper') },
                lower:   { test: v => /[a-z]/.test(v),        el: document.getElementById('rule-lower') },
                number:  { test: v => /[0-9]/.test(v),        el: document.getElementById('rule-number') },
                special: { test: v => /[^a-zA-Z0-9]/.test(v), el: document.getElementById('rule-special') }
            };

            function updateRules(value) {
                let passedCount = 0;
                Object.values(rules).forEach(function (rule) {
                    const icon = rule.el.querySelector('i');
                    if (rule.test(value)) {
                        rule.el.classList.remove('text-muted');
                        rule.el.classList.add('text-success');
                        icon.className = 'bi bi-check-circle-fill me-1';
                        passedCount++;
                    } else {
                        rule.el.classList.remove('text-success');
                        rule.el.classList.add('text-muted');
                        icon.className = 'bi bi-circle me-1';
                    }
                });
                return passedCount;
            }

            function updateStrengthBar(passedCount) {
                const percent = (passedCount / 5) * 100;
                strengthBar.style.width = percent + '%';

                let color = '#dc3545';
                let label = 'Very weak';
                if (passedCount <= 1)      { color = '#dc3545'; label = 'Very weak'; }
                else if (passedCount <= 3) { color = '#ffc107'; label = 'Moderate'; }
                else if (passedCount === 4){ color = '#fd7e14'; label = 'Strong'; }
                else                       { color = '#198754'; label = 'Very strong'; }

                strengthBar.style.background = color;
                strengthLabel.textContent = newPassword.value ? 'Password strength: ' + label : 'Password strength';
            }

            newPassword.addEventListener('input', function () {
                const passedCount = updateRules(newPassword.value);
                updateStrengthBar(passedCount);
                checkConfirmMatch();
            });

            function checkConfirmMatch() {
                if (confirmPassword.value === '') {
                    confirmGroup.classList.remove('is-invalid-group');
                    confirmFeedback.classList.add('d-none');
                    return;
                }
                if (confirmPassword.value !== newPassword.value) {
                    confirmGroup.classList.add('is-invalid-group');
                    confirmFeedback.classList.remove('d-none');
                } else {
                    confirmGroup.classList.remove('is-invalid-group');
                    confirmFeedback.classList.add('d-none');
                }
            }

            confirmPassword.addEventListener('input', checkConfirmMatch);

            // Show/hide password toggles
            document.querySelectorAll('.toggle-password').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const targetId = btn.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = btn.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'bi bi-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'bi bi-eye';
                    }
                });
            });

            // Final submit validation
            form.addEventListener('submit', function (e) {
                checkConfirmMatch();
                if (!form.checkValidity() || confirmPassword.value !== newPassword.value) {
                    e.preventDefault();
                    e.stopPropagation();
                    const firstInvalid = form.querySelector(':invalid, .is-invalid');
                    if (firstInvalid) firstInvalid.focus();
                }
                form.classList.add('was-validated');
            }, false);
        })();
    </script>
</body>
</html>
