<?php
/**
 * Organizer/Change_Password.php
 * Password Security Settings for College Organizer
 */
include 'organizer_auth.php';
include 'connection.php';

$errors  = [];
$success = false;

// Fetch current stored password
$stmt = $pdo->prepare("SELECT password FROM colleges WHERE college_id = :cid LIMIT 1");
$stmt->execute(['cid' => $college_id]);
$storedPassword = $stmt->fetch()['password'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Current password validation
    if ($currentPassword === '') {
        $errors['current_password'] = 'Please enter your current password.';
    } elseif ($storedPassword !== null && $currentPassword !== $storedPassword && !password_verify($currentPassword, $storedPassword)) {
        $errors['current_password'] = 'Current password is incorrect.';
    }

    // New password validation
    if ($newPassword === '') {
        $errors['new_password'] = 'Please enter a new password.';
    } elseif (strlen($newPassword) < 6) {
        $errors['new_password'] = 'Password must be at least 6 characters long.';
    }

    // Confirm password validation
    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE colleges SET password = :pass WHERE college_id = :cid");
            $stmt->execute(['pass' => $newPassword, 'cid' => $college_id]);
            $success = true;
        } catch (PDOException $e) {
            $errors['current_password'] = 'Error updating password: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password | Organizer Portal</title>

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
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .berun-panel-title { font-size: 18px; font-weight: 700; margin: 0; }
        .berun-panel-sub   { font-size: 12px; color: #7c7d7e; margin: 2px 0 0 0; }

        .form-label { font-weight: 700; color: #1e2937; font-size: 13px; margin-bottom: 8px; }

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
                    <h1 class="berun-greeting-h1">Change Password</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Update account security password for <?= htmlspecialchars((string)$college_name) ?></p>
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
                    <div class="col-12 col-lg-8 col-xl-7">

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i> Your college password has been updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>Please fix the errors below:</strong>
                                <ul class="mb-0 mt-2 ps-3 text-xs">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= htmlspecialchars((string)$err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- CHANGE PASSWORD CARD -->
                        <div class="berun-card-panel">
                            <div class="d-flex align-items-center gap-3 pb-3 mb-4 border-bottom">
                                <div class="bg-primary-subtle text-primary p-3 rounded-4 fs-3">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                                <div>
                                    <h3 class="berun-panel-title">Update Password</h3>
                                    <p class="berun-panel-sub">Choose a strong password to keep your college portal secure</p>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="mb-4">
                                    <label class="form-label" for="current_password">Current Password *</label>
                                    <div class="berun-input-group">
                                        <input type="password" class="form-control" name="current_password" id="current_password" required placeholder="Enter current password">
                                        <button class="btn-eye toggle-pwd" type="button" data-target="current_password"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="new_password">New Password *</label>
                                    <div class="berun-input-group">
                                        <input type="password" class="form-control" name="new_password" id="new_password" required placeholder="Enter new password">
                                        <button class="btn-eye toggle-pwd" type="button" data-target="new_password"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="confirm_password">Confirm New Password *</label>
                                    <div class="berun-input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required placeholder="Re-enter new password">
                                        <button class="btn-eye toggle-pwd" type="button" data-target="confirm_password"><i class="bi bi-eye"></i></button>
                                    </div>
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

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    </script>
</body>
</html>
