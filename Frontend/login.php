<?php
/**
 * Frontend/login.php
 * Unified Single Login for Students, College Organizers, and Admins
 */
$page_title = "Unified Portal Login";
include 'connection.php';
include_once 'frontend_auth.php';

// If already logged in, redirect to appropriate role portal
if ($is_logged_in) {
    if ($user_role === 'admin') {
        header('Location: ../Admin/Dashboard.php');
        exit;
    } elseif ($user_role === 'organizer') {
        header('Location: ../Organizer/Dashboard.php');
        exit;
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email address and password.';
    } else {
        // 1. CHECK ADMINS TABLE
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();

        if ($admin && ($password === $admin['password'] || password_verify($password, $admin['password']))) {
            $_SESSION['admin_id']    = $admin['admin_id'];
            $_SESSION['admin_name']  = $admin['name'] ?? $admin['admin_name'] ?? 'Administrator';
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['loggedin']    = true;
            header('Location: ../Admin/Dashboard.php');
            exit;
        }

        // 2. CHECK COLLEGES (ORGANIZERS) TABLE
        $stmt = $pdo->prepare("SELECT * FROM colleges WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $college = $stmt->fetch();

        if ($college && ($password === $college['password'] || password_verify($password, $college['password']))) {
            $_SESSION['college_id']           = $college['college_id'];
            $_SESSION['college_name']         = $college['name'];
            $_SESSION['college_email']        = $college['email'];
            $_SESSION['college_logo']         = $college['logo'] ?? '';
            $_SESSION['organizer_logged_in'] = true;
            header('Location: ../Organizer/Dashboard.php');
            exit;
        }

        // 3. CHECK STUDENTS TABLE
        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $student = $stmt->fetch();

        if ($student && ($password === $student['password'] || password_verify($password, $student['password']))) {
            $_SESSION['student_id']        = $student['student_id'];
            $_SESSION['student_name']      = $student['name'];
            $_SESSION['student_email']     = $student['email'];
            $_SESSION['college_id']        = $student['college_id'];
            $_SESSION['student_logged_in'] = true;
            header('Location: dashboard.php');
            exit;
        }

        $error = 'We could not verify your email address or password. Please check your credentials and try again.';
    }
}

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    .login-hero-section {
        background-color: #fffdf7;
        padding: 56px 0 48px 0;
        position: relative;
    }

    .hero-kpi-pill-badge {
        background: #ffffff;
        border: 2px solid #14171a;
        box-shadow: 4px 4px 0px #ffd13b;
        color: #14171a;
        font-weight: 800;
        font-size: 12px;
        padding: 8px 20px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        line-height: 1.2;
    }

    .login-grid-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 64px 0 88px 0;
        border-top: 1px solid #f1f5f9;
        min-height: 550px;
    }

    .login-info-card-luxury {
        background: #ffffff;
        border-radius: 28px;
        padding: 40px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .login-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 40px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        transition: all 0.3s ease;
    }

    .login-card-bespoke:hover {
        border-color: #14171a;
        box-shadow: 0 20px 42px rgba(0, 0, 0, 0.06);
    }

    .role-feature-pod {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        padding: 16px 20px;
        margin-bottom: 14px;
        transition: all 0.2s ease;
    }

    .role-feature-pod:hover {
        background: #ffffff;
        border-color: #14171a;
        box-shadow: 4px 4px 0px #ffd13b;
    }

    .role-icon-circle {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: #ffd13b;
        border: 1.5px solid #14171a;
        color: #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .form-control-luxury {
        background: #f8fafc !important;
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 16px !important;
        padding: 12px 18px !important;
        font-size: 13px !important;
        color: #14171a !important;
        transition: all 0.2s ease !important;
    }

    .form-control-luxury:focus {
        background: #ffffff !important;
        border-color: #14171a !important;
        box-shadow: 0 0 0 4px rgba(20, 23, 26, 0.06) !important;
        outline: none !important;
    }

    .btn-search-capsule-dark {
        background: #14171a;
        color: #ffffff;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 800;
        padding: 14px 28px;
        border: 1.5px solid #14171a;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-search-capsule-dark:hover {
        background: #ffd13b;
        color: #14171a;
        border-color: #14171a;
    }

    .password-input-group {
        position: relative;
    }

    .btn-toggle-password {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 16px;
        padding: 4px;
        z-index: 5;
    }

    .security-badge-box {
        background: #14171a;
        color: #ffffff;
        border-radius: 20px;
        padding: 18px 20px;
        border: 2px solid #14171a;
        box-shadow: 4px 4px 0px #ffd13b;
    }
</style>

<!-- PAGE HERO -->
<section class="login-hero-section">
    <div class="container-xl text-center">
        <div class="mb-3">
            <span class="hero-kpi-pill-badge">
                <i class="bi bi-shield-lock-fill text-warning fs-6"></i> Unified Single Authentication Gateway
            </span>
        </div>
        <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">Portal Sign In</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 580px;">
            Access your Student Portal, College Organizer Command Center, or Administrator Account from a single secure login portal.
        </p>
    </div>
</section>

<!-- LOGIN GRID CANVAS SECTION -->
<section class="login-grid-canvas">
    <div class="container-xl">
        <div class="row g-4">
            
            <!-- LEFT COLUMN: PLATFORM HIGHLIGHTS -->
            <div class="col-12 col-lg-5">
                <div class="login-info-card-luxury">
                    <div>
                        <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs mb-3">Multi-Role Support</span>
                        <h3 class="fw-black text-dark mb-4 fs-4" style="letter-spacing: -0.5px;">One Login for Every Campus Role.</h3>

                        <!-- Student Pod -->
                        <div class="role-feature-pod d-flex align-items-center gap-3">
                            <div class="role-icon-circle">
                                <i class="bi bi-person-badge-fill"></i>
                            </div>
                            <div>
                                <strong class="text-dark fs-6 font-black d-block mb-0">Student Portal</strong>
                                <small class="text-muted text-xs">Discover events, build squads, and access digital passes.</small>
                            </div>
                        </div>

                        <!-- Organizer Pod -->
                        <div class="role-feature-pod d-flex align-items-center gap-3">
                            <div class="role-icon-circle" style="background: #60a5fa; color: #ffffff;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <strong class="text-dark fs-6 font-black d-block mb-0">College Organizer</strong>
                                <small class="text-muted text-xs">Publish fests, manage registrations, and scan venue entry passes.</small>
                            </div>
                        </div>

                        <!-- Admin Pod -->
                        <div class="role-feature-pod d-flex align-items-center gap-3">
                            <div class="role-icon-circle" style="background: #34d399; color: #14171a;">
                                <i class="bi bi-speedometer2"></i>
                            </div>
                            <div>
                                <strong class="text-dark fs-6 font-black d-block mb-0">Administrator Console</strong>
                                <small class="text-muted text-xs">Campus network oversight, category approvals, and platform analytics.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Security Box -->
                    <div class="security-badge-box mt-4">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-shield-check text-warning fs-5"></i>
                            <span class="text-warning text-xs font-bold">Encrypted Session</span>
                        </div>
                        <p class="text-white-50 text-xs mb-0" style="line-height: 1.4;">
                            Automated multi-role credential resolution forwards you directly to your designated dashboard upon verification.
                        </p>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN: BESPOKE UNIFIED LOGIN FORM -->
            <div class="col-12 col-lg-7">
                <div class="login-card-bespoke">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h3 class="fw-black text-dark mb-0 fs-4" style="letter-spacing: -0.5px;">Sign In to Account</h3>
                        <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs">All Portals</span>
                    </div>
                    <p class="text-muted text-xs mb-4">Enter your registered email address and password to proceed.</p>

                    <?php if ($error): ?>
                        <div class="alert bg-danger text-white rounded-4 border-0 p-3 mb-4 d-flex align-items-center gap-2 text-xs font-semibold shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-1"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label font-bold text-xs text-dark mb-1">REGISTERED EMAIL ADDRESS *</label>
                            <input type="email" class="form-control form-control-luxury" name="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" required autocomplete="off" placeholder="name@college.edu or admin@evenza.com">
                        </div>

                        <div class="mb-4">
                            <label class="form-label font-bold text-xs text-dark mb-1">PASSWORD *</label>
                            <div class="password-input-group">
                                <input type="password" id="loginPassword" class="form-control form-control-luxury" name="password" required placeholder="Enter your account password">
                                <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility()">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-search-capsule-dark w-100 justify-content-center">
                                Sign In to Portal <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 pt-3 border-top text-center text-xs text-muted">
                        Don't have a student account yet? 
                        <a href="register.php" class="text-dark font-bold ms-1 text-decoration-underline">Register as Student &rarr;</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('loginPassword');
    const toggleIcon = document.getElementById('toggleIcon');
    if (!passwordInput || !toggleIcon) return;

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
}
</script>

<?php include 'Footer.php'; ?>
