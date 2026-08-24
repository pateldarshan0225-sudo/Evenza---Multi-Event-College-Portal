<?php
/**
 * Frontend/register.php
 * Student Registration Page for Evenza Portal
 */
$page_title = "Student Registration";
include 'connection.php';
include_once 'frontend_auth.php';

if ($is_logged_in && $user_role === 'student') {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = false;

// Fetch Colleges for Dropdown
$colleges = $pdo->query("SELECT college_id, name FROM colleges WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $college_id    = filter_input(INPUT_POST, 'college_id', FILTER_VALIDATE_INT);
    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $phone         = trim($_POST['phone'] ?? '');
    $gender        = $_POST['gender'] ?? 'male';
    $semester      = (int)($_POST['semester'] ?? 1);

    // Validation
    if (!$college_id) $errors[] = 'Please select your college.';
    if ($enrollment_no === '') $errors[] = 'Enrollment number is required.';
    if ($name === '') $errors[] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($phone === '') $errors[] = 'Phone number is required.';

    // Email Uniqueness Check
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT student_id FROM students WHERE email = :email LIMIT 1");
        $chk->execute(['email' => $email]);
        if ($chk->fetch()) {
            $errors[] = 'An account with this email address already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO students (college_id, enrollment_no, name, email, password, phone, gender, semester, verification_status, account_status, created_at, updated_at)
                VALUES (:cid, :eno, :name, :email, :pass, :phone, :gender, :sem, 'verified', 'active', NOW(), NOW())
            ");
            $stmt->execute([
                'cid'      => $college_id,
                'eno'      => $enrollment_no,
                'name'     => $name,
                'email'    => $email,
                'pass'     => $password,
                'phone'    => $phone,
                'gender'   => $gender,
                'sem'      => $semester
            ]);

            $new_id = $pdo->lastInsertId();
            $_SESSION['student_id']    = $new_id;
            $_SESSION['student_name']  = $name;
            $_SESSION['student_email'] = $email;
            $_SESSION['college_id']    = $college_id;

            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Error registering account: ' . $e->getMessage();
        }
    }
}

include 'Header.php';
?>

<div class="container-xl py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7">

            <div class="bg-white rounded-5 p-4 p-md-5 border border-dark border-2 shadow-lg" style="box-shadow: 6px 6px 0px #14171a !important;">
                
                <div class="text-center mb-4">
                    <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Student Account</span>
                    <h2 class="fw-black text-dark fs-3 mb-1">Create Student Profile</h2>
                    <p class="text-muted text-xs">Register to participate in inter-college competitions & hackathons</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-4 border-0 text-xs py-2 px-3 mb-4" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Select College *</label>
                            <select class="form-select rounded-pill px-3 py-2 text-xs font-medium" name="college_id" required>
                                <option value="">Choose your college...</option>
                                <?php foreach ($colleges as $c): ?>
                                    <option value="<?= $c['college_id'] ?>" <?= isset($_POST['college_id']) && $_POST['college_id'] == $c['college_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Enrollment / Roll No *</label>
                            <input type="text" class="form-control rounded-pill px-3 py-2 text-xs font-medium" name="enrollment_no" value="<?= htmlspecialchars((string)($_POST['enrollment_no'] ?? '')) ?>" placeholder="e.g. STU-100-1" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Full Name *</label>
                            <input type="text" class="form-control rounded-pill px-3 py-2 text-xs font-medium" name="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" placeholder="Your full name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Email Address *</label>
                            <input type="email" class="form-control rounded-pill px-3 py-2 text-xs font-medium" name="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="student@college.edu" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Password *</label>
                            <input type="password" class="form-control rounded-pill px-3 py-2 text-xs font-medium" name="password" placeholder="At least 6 characters" minlength="6" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Phone Number *</label>
                            <input type="tel" class="form-control rounded-pill px-3 py-2 text-xs font-medium" name="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="10-digit phone number" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Gender *</label>
                            <select class="form-select rounded-pill px-3 py-2 text-xs font-medium" name="gender" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-bold text-xs text-uppercase text-dark">Current Semester *</label>
                            <select class="form-select rounded-pill px-3 py-2 text-xs font-medium" name="semester" required>
                                <?php for ($i=1; $i<=8; $i++): ?>
                                    <option value="<?= $i ?>">Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid gap-2 pt-4">
                        <button type="submit" class="btn-capsule-dark justify-content-center py-3 fs-6">
                            Register & Access Portal <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-4 text-center text-xs text-muted pt-3 border-top">
                    Already have an account? <a href="login.php" class="text-dark font-bold">Sign In Here</a>
                </div>

            </div>

        </div>
    </div>
</div>

<?php include 'Footer.php'; ?>
