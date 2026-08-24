<?php
/**
 * Frontend/login.php
 * Unified Single Login for Students, Organizers, and Admins
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
            $_SESSION['admin_name']  = $admin['name'] ?? 'Administrator';
            $_SESSION['admin_email'] = $admin['email'];
            header('Location: ../Admin/Dashboard.php');
            exit;
        }

        // 2. CHECK COLLEGES TABLE
        $stmt = $pdo->prepare("SELECT * FROM colleges WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $college = $stmt->fetch();

        if ($college && ($password === $college['password'] || password_verify($password, $college['password']))) {
            $_SESSION['college_id']    = $college['college_id'];
            $_SESSION['college_name']  = $college['name'];
            $_SESSION['college_email'] = $college['email'];
            $_SESSION['college_logo']  = $college['logo'] ?? '';
            header('Location: ../Organizer/Dashboard.php');
            exit;
        }

        // 3. CHECK STUDENTS TABLE
        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $student = $stmt->fetch();

        if ($student && ($password === $student['password'] || password_verify($password, $student['password']))) {
            $_SESSION['student_id']    = $student['student_id'];
            $_SESSION['student_name']  = $student['name'];
            $_SESSION['student_email'] = $student['email'];
            $_SESSION['college_id']    = $student['college_id'];
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid email address or password. Please check your credentials.';
    }
}

include 'Header.php';
?>

<div class="container-xl py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">

            <div class="bg-white rounded-5 p-4 p-md-5 border border-dark border-2 shadow-lg mt-3" style="box-shadow: 6px 6px 0px #14171a !important;">
                
                <div class="text-center mb-4">
                    <div class="bg-warning text-dark font-black px-3 py-1 rounded-3 d-inline-block fs-5 mb-2">
                        <i class="bi bi-intersect"></i> Evenza Portal
                    </div>
                    <h2 class="fw-black text-dark fs-3 mb-1">Unified Sign In</h2>
                    <p class="text-muted text-xs">Access your Student, College Organizer, or Admin account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-4 border-0 text-xs py-2 px-3 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label class="form-label font-bold text-xs text-uppercase text-dark mb-2">Email Address *</label>
                        <input type="email" class="form-control rounded-pill px-4 py-2.5 text-xs font-medium border" name="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" required placeholder="user@college.edu or admin@evenza.com">
                        <div class="invalid-feedback text-xs ps-2">Please enter a valid email.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label font-bold text-xs text-uppercase text-dark mb-2">Password *</label>
                        <input type="password" class="form-control rounded-pill px-4 py-2.5 text-xs font-medium border" name="password" required placeholder="Enter your password">
                        <div class="invalid-feedback text-xs ps-2">Please enter your password.</div>
                    </div>

                    <div class="d-grid gap-2 pt-2">
                        <button type="submit" class="btn-capsule-dark justify-content-center py-3 fs-6">
                            Sign In to Portal <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-4 text-center text-xs text-muted pt-3 border-top">
                    Don't have a student account yet? <a href="register.php" class="text-dark font-bold">Register as Student</a>
                </div>

            </div>

        </div>
    </div>
</div>

<?php include 'Footer.php'; ?>
