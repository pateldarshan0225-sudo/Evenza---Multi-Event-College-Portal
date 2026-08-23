<?php
/**
 * Organizer/Profile.php
 * College Profile & Settings
 */
include 'organizer_auth.php';
include 'connection.php';

$flash = null;

// Handle College Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($email) && !empty($phone)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE colleges 
                SET email = :email, phone = :phone
                WHERE college_id = :cid
            ");
            $stmt->execute([
                'email' => $email,
                'phone' => $phone,
                'cid'   => $college_id
            ]);

            $_SESSION['college_email'] = $email;
            $flash = ['type' => 'success', 'message' => 'College profile details updated successfully!'];
        } catch (PDOException $e) {
            $flash = ['type' => 'danger', 'message' => 'Error updating profile: ' . $e->getMessage()];
        }
    } else {
        $flash = ['type' => 'danger', 'message' => 'Please enter a valid email and phone number.'];
    }
}

// Fetch Latest College Record
$stmt = $pdo->prepare("SELECT c.*, u.name AS university_name FROM colleges c LEFT JOIN universities u ON c.university_id = u.university_id WHERE c.college_id = :cid LIMIT 1");
$stmt->execute(['cid' => $college_id]);
$college_data = $stmt->fetch();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>College Profile | Organizer Portal</title>

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
                    <h1 class="berun-greeting-h1">College Profile</h1>
                    <p class="text-muted mb-0 text-xs font-medium">Manage profile details for <?= htmlspecialchars((string)$college_name) ?></p>
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

                        <?php if ($flash): ?>
                            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars($flash['message']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- COLLEGE PROFILE CARD -->
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

                            <form method="POST">
                                <div class="mb-4">
                                    <label class="form-label">College Name</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars((string)$college_data['name']) ?>" readonly disabled>
                                    <small class="text-muted text-xs mt-1 d-block">College name is managed by platform administrator.</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="email">Contact Email Address *</label>
                                    <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars((string)$college_data['email']) ?>" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="phone">Phone Number *</label>
                                    <input type="text" class="form-control" name="phone" id="phone" value="<?= htmlspecialchars((string)$college_data['phone']) ?>" required>
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
                                    <a href="Dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 font-semibold">Back</a>
                                    <button type="submit" class="berun-btn-dark">
                                        <i class="bi bi-save me-1"></i> Save Changes
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
</body>
</html>
