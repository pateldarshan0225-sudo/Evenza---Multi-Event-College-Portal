<?php
session_start();
include "connection.php";

if (isset($_POST["email"]) && isset($_POST["password"])) {

    $email    = $_POST["email"];
    $password = $_POST["password"];

    if ($email === "" || $password === "") {
        $error = "Please enter both email and password.";
    } else {

        $sql = "SELECT * FROM admins WHERE email='".$email."' AND password='".$password."'";
        $data = mysqli_query($conn, $sql);
        $count = mysqli_num_rows($data);

        if ($count > 0) {
            $admin = mysqli_fetch_assoc($data);

            $_SESSION["loggedin"]    = true;
            $_SESSION["admin_id"]    = $admin["admin_id"] ?? null;
            $_SESSION["admin_email"] = $admin["email"] ?? null;
            $_SESSION["admin_name"]  = $admin["name"] ?? $admin["admin_name"] ?? 'Admin';

            header("Location: Dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Evenza Admin</title>

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

        /* Checkbox & Forgot link */
        .form-check-input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            border-radius: 4px;
        }
        .form-check-input:checked {
            background-color: var(--color-text-dark);
            border-color: var(--color-text-dark);
        }
        .form-check-label {
            font-size: 13px;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 13px;
            color: #4f46e5;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .forgot-link:hover {
            color: #3730a3;
            text-decoration: underline;
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
        .berun-btn-submit:active {
            transform: translateY(0);
        }

        /* Divider & Socials */
        .divider-line {
            display: flex;
            align-items: center;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            margin: 20px 0;
            gap: 12px;
        }
        .divider-line::before, .divider-line::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: #e2e8f0;
        }

        .social-btn {
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            border-radius: 9999px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            flex: 1;
        }
        .social-btn:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

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

        /* Sign up text */
        .signup-text {
            text-align: center;
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        .signup-text a {
            color: #4f46e5;
            font-weight: 700;
            text-decoration: none;
        }
        .signup-text a:hover { text-decoration: underline; }
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
        </div>

        <h1 class="login-title text-center">Welcome Back</h1>
        <p class="login-subtitle text-center">Sign in to access your admin dashboard</p>

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
                <label class="form-label" for="email">Email Address</label>
                <div class="berun-input-group">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required
                        value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                </div>
            </div>

            <!-- Password Input -->
            <div class="mb-3">
                <label class="form-label" for="passwordField">Password</label>
                <div class="berun-input-group">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter your password" required>
                    <button class="btn-eye" type="button" id="togglePassword" title="Toggle Visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="form-check m-0">
                    <input type="checkbox" name="remember" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                </div>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>

            <!-- Submit Button -->
            <button class="berun-btn-submit" type="submit">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>

            <!-- Sign Up text -->
            <p class="signup-text">
                Don't have an account? <a href="#">Sign Up</a>
            </p>

            <!-- Divider -->
            <div class="divider-line">Or With</div>

            <!-- Social Logins -->
            <div class="d-flex align-items-center gap-2">
                <button class="social-btn" type="button">
                    <svg version="1.1" width="18" height="18" viewBox="0 0 512 512" xml:space="preserve" xmlns="http://www.w3.org/2000/svg">
                        <path style="fill:#FBBB00;" d="M113.47,309.408L95.648,375.94l-65.139,1.378C11.042,341.211,0,299.9,0,256c0-42.451,10.324-82.483,28.624-117.732h0.014l57.992,10.632l25.404,57.644c-5.317,15.501-8.215,32.141-8.215,49.456C103.821,274.792,107.225,292.797,113.47,309.408z"></path>
                        <path style="fill:#518EF8;" d="M507.527,208.176C510.467,223.662,512,239.655,512,256c0,18.328-1.927,36.206-5.598,53.451c-12.462,58.683-45.025,109.925-90.134,146.187l-0.014-0.014l-73.044-3.727l-10.338-64.535c29.932-17.554,53.324-45.025,65.646-77.911h-136.89V208.176h138.887L507.527,208.176L507.527,208.176z"></path>
                        <path style="fill:#28B446;" d="M416.253,455.624l0.014,0.014C372.396,490.901,316.666,512,256,512c-97.491,0-182.252-54.491-225.491-134.681l82.961-67.91c21.619,57.698,77.278,98.771,142.53,98.771c28.047,0,54.323-7.582,76.87-20.818L416.253,455.624z"></path>
                        <path style="fill:#F14336;" d="M419.404,58.936l-82.933,67.896c-23.335-14.586-50.919-23.012-80.471-23.012c-66.729,0-123.429,42.957-143.965,102.724l-83.397-68.276h-0.014C71.23,56.123,157.06,0,256,0C318.115,0,375.068,22.126,419.404,58.936z"></path>
                    </svg>
                    Google
                </button>

                <button class="social-btn" type="button">
                    <svg version="1.1" height="18" width="18" viewBox="0 0 22.773 22.773" xml:space="preserve" xmlns="http://www.w3.org/2000/svg">
                        <g>
                            <path d="M15.769,0c0.053,0,0.106,0,0.162,0c0.13,1.606-0.483,2.806-1.228,3.675c-0.731,0.863-1.732,1.7-3.351,1.573c-0.108-1.583,0.506-2.694,1.25-3.561C13.292,0.879,14.557,0.16,15.769,0z"></path>
                            <path d="M20.67,16.716c0,0.016,0,0.03,0,0.045c-0.455,1.378-1.104,2.559-1.896,3.655c-0.723,0.995-1.609,2.334-3.191,2.334c-1.367,0-2.275-0.879-3.676-0.903c-1.482-0.024-2.297,0.735-3.652,0.926c-0.155,0-0.31,0-0.462,0c-0.995-0.144-1.798-0.932-2.383-1.642c-1.725-2.098-3.058-4.808-3.306-8.276c0-0.34,0-0.679,0-1.019c0.105-2.482,1.311-4.5,2.914-5.478c0.846-0.52,2.009-0.963,3.304-0.765c0.555,0.086,1.122,0.276,1.619,0.464c0.471,0.181,1.06,0.502,1.618,0.485c0.378-0.011,0.754-0.208,1.135-0.347c1.116-0.403,2.21-0.865,3.652-0.648c1.733,0.262,2.963,1.032,3.723,2.22c-1.466,0.933-2.625,2.339-2.427,4.74C17.818,14.688,19.086,15.964,20.67,16.716z"></path>
                        </g>
                    </svg>
                    Apple
                </button>
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