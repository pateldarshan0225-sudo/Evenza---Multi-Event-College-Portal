<?php
session_start();
include "connection.php";

$error = "";

if (isset($_POST["email"]) && isset($_POST["password"])) {
    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if ($email === "" || $password === "") {
        $error = "Please enter both college email and password.";
    } else {
        // Query college table
        $stmt = $pdo->prepare("SELECT * FROM colleges WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $college = $stmt->fetch();

        // Note: Supports plain-text or password_verify fallback
        if ($college && ($password === $college['password'] || (isset($college['password']) && password_verify($password, $college['password'])))) {
            if ($college['status'] !== 'active') {
                $error = "Your college account is inactive. Please contact the administrator.";
            } else {
                $_SESSION['organizer_logged_in'] = true;
                $_SESSION['college_id']          = $college['college_id'];
                $_SESSION['college_name']        = $college['name'];
                $_SESSION['college_email']       = $college['email'];
                $_SESSION['college_logo']        = $college['logo'] ?? '';

                header("Location: Dashboard.php");
                exit();
            }
        } else {
            $error = "Invalid college email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Portal | Evenza</title>

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
            --color-yellow: #ffd13b;
            --color-text-dark: #1c2024;
            --color-text-muted: #7c7d7e;
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }

        * { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--bg-card) !important;
            font-family: var(--font-family);
            color: var(--color-text-dark);
            padding: 24px 16px;
        }

        /* Container Card */
        .login-card {
            background-color: var(--bg-white);
            border-radius: 28px;
            padding: 40px 36px;
            width: 440px;
            max-width: 100%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        /* Logo Header */
        .berun-logo-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--color-text-dark);
            margin-bottom: 24px;
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
            font-size: 22px;
            letter-spacing: -0.5px;
            color: var(--color-text-dark);
        }
        .berun-logo-text span { font-weight: 400; }

        .login-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--color-text-dark);
            margin-bottom: 4px;
            letter-spacing: -0.3px;
        }

        .login-subtitle {
            font-size: 13px;
            color: var(--color-text-muted);
            font-weight: 500;
            margin-bottom: 28px;
        }

        /* Input Group Pill Styling */
        .form-label {
            font-weight: 700;
            color: #1e2937;
            font-size: 13px;
            margin-bottom: 8px;
            display: block;
        }

        .berun-input-group {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 4px 12px 4px 18px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.015);
            margin-bottom: 20px;
        }

        .berun-input-group:focus-within {
            border-color: var(--color-text-dark);
            box-shadow: 0 0 0 3px rgba(28, 32, 36, 0.08);
        }

        .berun-input-group .input-icon {
            color: #94a3b8;
            font-size: 16px;
            margin-right: 10px;
            flex-shrink: 0;
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
            width: 36px;
            height: 36px;
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

        /* Submit Button */
        .berun-btn-submit {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 13px 24px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            margin-top: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(28, 32, 36, 0.15);
        }
        .berun-btn-submit:hover {
            background-color: #2e343b;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(28, 32, 36, 0.25);
        }
        .berun-btn-submit:active { transform: translateY(0); }

        /* Error Callout */
        .error-alert {
            background-color: #fde8e8;
            color: #dc3545;
            border: none;
            border-radius: 16px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .portal-badge {
            background: #e0e7ff;
            color: #4f46e5;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 9999px;
            display: inline-block;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body>

    <div class="login-card">

        <!-- Logo Brand -->
        <div class="text-center">
            <a href="Index.php" class="berun-logo-brand">
                <div class="berun-logo-dots">
                    <div class="berun-logo-dots-top">
                        <div class="berun-dot"></div>
                        <div class="berun-dot"></div>
                    </div>
                    <div class="berun-dot"></div>
                </div>
                <div class="berun-logo-text">Even<span>za</span></div>
            </a>
            <br>
            <span class="portal-badge"><i class="bi bi-building me-1"></i> College Organizer Portal</span>
        </div>

        <h1 class="login-title text-center">Organizer Sign In</h1>
        <p class="login-subtitle text-center">Manage events, registrations & students for your college</p>

        <!-- Error Notification Alert -->
        <?php if (!empty($error)) : ?>
            <div class="error-alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="Index.php">

            <!-- Email Input -->
            <div class="mb-3">
                <label class="form-label" for="email">College Email Address</label>
                <div class="berun-input-group">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter college email" required
                        value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                </div>
            </div>

            <!-- Password Input -->
            <div class="mb-3">
                <label class="form-label" for="passwordField">Password</label>
                <div class="berun-input-group">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter password" required>
                    <button class="btn-eye" type="button" id="togglePassword" title="Toggle Visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button class="berun-btn-submit" type="submit">
                <i class="bi bi-box-arrow-in-right me-1"></i> Access Organizer Dashboard
            </button>

            <div class="text-center mt-3">
                <a href="../Index.php" class="text-decoration-none text-muted text-xs font-semibold">
                    <i class="bi bi-shield-lock me-1"></i> Go to Admin Portal Login
                </a>
            </div>

        </form>

    </div>

    <!-- Toggle Password Visibility JS -->
    <script>
        const toggleBtn = document.getElementById('togglePassword');
        const passwordField = document.getElementById('passwordField');

        toggleBtn.addEventListener('click', function() {
            const icon = toggleBtn.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                passwordField.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    </script>

</body>
</html>
