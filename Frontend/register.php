<?php
/**
 * Frontend/register.php
 * Ultra-Premium Student Registration Gateway (With Cascading University & Live Search Filters)
 */
$page_title = "Student Registration Gateway";
include 'connection.php';
include_once 'frontend_auth.php';

if ($is_logged_in && $user_role === 'student') {
    header('Location: ../Student/Dashboard.php');
    exit;
}

$errors = [];
$success = false;

// Fetch Universities & Colleges with Mapping
$universities = $pdo->query("SELECT university_id, name FROM universities ORDER BY name ASC")->fetchAll();
$colleges     = $pdo->query("
    SELECT c.college_id, c.university_id, c.name, u.name AS university_name 
    FROM colleges c 
    LEFT JOIN universities u ON c.university_id = u.university_id 
    WHERE c.status = 'active' 
    ORDER BY c.name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $university_id = filter_input(INPUT_POST, 'university_id', FILTER_VALIDATE_INT);
    $college_id    = filter_input(INPUT_POST, 'college_id', FILTER_VALIDATE_INT);
    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $phone         = trim($_POST['phone'] ?? '');
    $gender        = $_POST['gender'] ?? 'male';
    $semester      = (int)($_POST['semester'] ?? 1);

    // Validation
    if (!$university_id) $errors['university_id'] = 'Please select your university.';
    if (!$college_id) $errors['college_id'] = 'Please select your college.';
    if ($enrollment_no === '') {
        $errors['enrollment_no'] = 'Enrollment / Roll number is required.';
    } elseif (strlen($enrollment_no) < 3) {
        $errors['enrollment_no'] = 'Enrollment number must be at least 3 characters.';
    }
    
    if ($name === '') {
        $errors['name'] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors['name'] = 'Full name must be at least 2 characters.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email address is required.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit phone number.';
    }

    // Email Uniqueness Check
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT student_id FROM students WHERE email = :email LIMIT 1");
        $chk->execute(['email' => $email]);
        if ($chk->fetch()) {
            $errors['email'] = 'An account with this email address already exists. Please login instead.';
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
            $_SESSION['student_id']        = $new_id;
            $_SESSION['student_name']      = $name;
            $_SESSION['student_email']     = $email;
            $_SESSION['college_id']        = $college_id;
            $_SESSION['student_college_id'] = $college_id;
            $_SESSION['student_logged_in']  = true;

            header('Location: ../Student/Dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = 'Error registering account: ' . $e->getMessage();
        }
    }
}

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fffdf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    body {
        background-color: var(--bg-cream-canvas);
    }

    .register-gateway-section {
        padding: 56px 0 80px 0;
    }

    .register-pill-badge {
        background: #ffffff;
        border: 1.5px solid #14171a;
        box-shadow: 3px 3px 0px #ffd13b;
        color: #14171a;
        font-weight: 800;
        font-size: 11px;
        padding: 6px 16px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .register-card-bespoke {
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 32px;
        padding: 40px;
        box-shadow: 8px 8px 0px #14171a;
        position: relative;
    }

    .register-input-group {
        position: relative;
    }

    .form-label-custom {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: #14171a;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .form-control-luxury, .form-select-luxury {
        background: #ffffff;
        border: 1.5px solid #d1d5db;
        border-radius: 9999px;
        padding: 11px 20px;
        font-size: 13px;
        font-weight: 600;
        color: #14171a;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        width: 100%;
    }

    .form-control-luxury:focus, .form-select-luxury:focus {
        border-color: #14171a;
        box-shadow: 0 0 0 3px rgba(20, 23, 26, 0.1);
        outline: none;
    }

    .search-filter-box {
        background: #f9f8f4;
        border: 1.5px dashed #cbd5e1;
        border-radius: 16px;
        padding: 10px 14px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .search-filter-box input {
        border: none;
        background: transparent;
        font-size: 12px;
        font-weight: 600;
        outline: none;
        width: 100%;
        color: #14171a;
    }

    .invalid-feedback-bespoke {
        color: #dc3545;
        font-size: 11px;
        font-weight: 700;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .valid-feedback-bespoke {
        color: #059669;
        font-size: 11px;
        font-weight: 700;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .btn-eye-toggle {
        position: absolute;
        right: 16px;
        top: 36px;
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        font-size: 16px;
    }

    .password-strength-bar {
        height: 4px;
        background: #e2e8f0;
        border-radius: 9999px;
        margin-top: 6px;
        overflow: hidden;
    }

    .password-strength-fill {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
        border-radius: 9999px;
    }

    .eco-pillar-card {
        background: #ffffff;
        border: 1.5px solid #14171a;
        border-radius: 20px;
        padding: 18px 20px;
        box-shadow: 4px 4px 0px #14171a;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .eco-pillar-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: #ffd13b;
        color: #14171a;
        border: 1.5px solid #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .register-card-bespoke { padding: 24px; border-radius: 24px; }
    }
</style>

<section class="register-gateway-section">
    <div class="container-xl">
        <div class="row g-5 align-items-center justify-content-center">

            <!-- LEFT COLUMN: ECOSYSTEM BRAND HIGHLIGHTS -->
            <div class="col-12 col-lg-5">
                <span class="register-pill-badge mb-3">
                    <i class="bi bi-mortarboard-fill text-warning"></i> Student Competitor Access
                </span>
                <h1 class="fw-black text-dark display-6 mb-3" style="letter-spacing: -1px; line-height: 1.15;">
                    Create Your Verified Student Profile
                </h1>
                <p class="text-muted leading-relaxed text-sm mb-4">
                    Join 250+ accredited universities and partner institutions. Register once to claim digital venue entry passes, create hackathon squad teams, and compete across top campus festivals.
                </p>

                <!-- 3 ECOSYSTEM PILLAR CARDS -->
                <div class="eco-pillar-card">
                    <div class="eco-pillar-icon"><i class="bi bi-qr-code-scan"></i></div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Instant QR Venue Entry Passes</div>
                        <small class="text-muted" style="font-size: 11px;">Verified digital tickets generated upon registration</small>
                    </div>
                </div>

                <div class="eco-pillar-card">
                    <div class="eco-pillar-icon"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Multi-Member Squad Rosters</div>
                        <small class="text-muted" style="font-size: 11px;">Generate team codes & invite campus squad mates</small>
                    </div>
                </div>

                <div class="eco-pillar-card">
                    <div class="eco-pillar-icon"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Single Sign-On Integration</div>
                        <small class="text-muted" style="font-size: 11px;">Access student competitor dashboard instantly</small>
                    </div>
                </div>

                <div class="p-3 bg-white rounded-4 border border-dark d-flex align-items-center gap-3 shadow-sm mt-4">
                    <i class="bi bi-lock-fill text-success fs-4"></i>
                    <div>
                        <div class="fw-bold text-dark text-xs">256-Bit SSL Encrypted Gateway</div>
                        <small class="text-muted" style="font-size: 11px;">Your institutional data is protected under FERPA & privacy protocols</small>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: LUXURY REGISTRATION FORM WITH CASCADING SEARCH FILTERS -->
            <div class="col-12 col-lg-7">
                <div class="register-card-bespoke">
                    
                    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                        <div>
                            <h3 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">Student Registration</h3>
                            <p class="text-muted text-xs mb-0">Fill in your institutional credentials to generate your profile</p>
                        </div>
                        <span class="badge bg-warning text-dark font-bold px-3 py-1.5 rounded-pill text-xs border border-dark">
                            Step 1 of 1
                        </span>
                    </div>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger rounded-4 border-0 text-xs py-3 px-3 mb-4 shadow-sm" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errors['general']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="studentRegisterForm" novalidate>
                        <div class="row g-3">

                            <!-- 1. SELECT / SEARCH UNIVERSITY (Cascading Parent Filter) -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-custom">
                                        <span>Select University *</span>
                                        <span class="text-muted" style="font-size: 10px;" id="univCountBadge"><?= count($universities) ?> Available</span>
                                    </label>

                                    <!-- Live Search University Box -->
                                    <div class="search-filter-box">
                                        <i class="bi bi-search text-muted"></i>
                                        <input type="text" id="searchUniversityInput" placeholder="Search university name..." onkeyup="filterUniversityOptions()">
                                    </div>

                                    <select class="form-select-luxury <?= isset($errors['university_id']) ? 'is-invalid' : '' ?>" name="university_id" id="universitySelect" onchange="filterCollegesByUniversity()" required>
                                        <option value="">Choose University...</option>
                                        <?php foreach ($universities as $u): ?>
                                            <option value="<?= $u['university_id'] ?>" <?= isset($_POST['university_id']) && $_POST['university_id'] == $u['university_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)$u['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback-bespoke" id="err_university_id" style="<?= isset($errors['university_id']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['university_id'] ?? 'Please select your university.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. SELECT / SEARCH COLLEGE (Cascading Dependent Filter) -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-custom">
                                        <span>Select College *</span>
                                        <span class="text-muted" style="font-size: 10px;" id="collegeCountBadge"><?= count($colleges) ?> Colleges</span>
                                    </label>

                                    <!-- Live Search College Box -->
                                    <div class="search-filter-box">
                                        <i class="bi bi-building-add text-warning"></i>
                                        <input type="text" id="searchCollegeInput" placeholder="Search college name..." onkeyup="filterCollegeOptions()">
                                    </div>

                                    <select class="form-select-luxury <?= isset($errors['college_id']) ? 'is-invalid' : '' ?>" name="college_id" id="collegeSelect" required>
                                        <option value="">Choose College...</option>
                                        <?php foreach ($colleges as $c): ?>
                                            <option value="<?= $c['college_id'] ?>" data-univ="<?= $c['university_id'] ?>" <?= isset($_POST['college_id']) && $_POST['college_id'] == $c['college_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)$c['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback-bespoke" id="err_college_id" style="<?= isset($errors['college_id']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['college_id'] ?? 'Please select your college.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. ENROLLMENT / ROLL NO -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-custom">Enrollment / Roll No *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['enrollment_no']) ? 'is-invalid' : '' ?>" name="enrollment_no" id="enrollment_no" value="<?= htmlspecialchars((string)($_POST['enrollment_no'] ?? '')) ?>" placeholder="e.g. STU-100-1" oninput="validateField('enrollment_no')" required>
                                    <div class="invalid-feedback-bespoke" id="err_enrollment_no" style="<?= isset($errors['enrollment_no']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['enrollment_no'] ?? 'Enrollment number is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. FULL NAME -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-custom">Full Name *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" id="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" placeholder="Your full legal name" oninput="validateField('name')" required>
                                    <div class="invalid-feedback-bespoke" id="err_name" style="<?= isset($errors['name']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['name'] ?? 'Full name is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 5. EMAIL ADDRESS -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-custom">Email Address *</label>
                                    <input type="email" class="form-control-luxury <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="student@college.edu" oninput="validateField('email')" required>
                                    <div class="invalid-feedback-bespoke" id="err_email" style="<?= isset($errors['email']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['email'] ?? 'Valid email address is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 6. PHONE NUMBER -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-custom">Phone Number *</label>
                                    <input type="tel" class="form-control-luxury <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" id="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="10-digit phone number" oninput="validateField('phone')" required>
                                    <div class="invalid-feedback-bespoke" id="err_phone" style="<?= isset($errors['phone']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['phone'] ?? 'Valid 10-digit phone number required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 7. PASSWORD WITH EYE TOGGLE & STRENGTH BAR -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group position-relative">
                                    <label class="form-label-custom">Password *</label>
                                    <input type="password" class="form-control-luxury <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password" id="registerPassword" placeholder="Min 6 characters" oninput="checkPasswordStrength()" required>
                                    <button type="button" class="btn-eye-toggle" onclick="toggleRegisterPassword()">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                    <div class="password-strength-bar">
                                        <div class="password-strength-fill" id="strengthFill"></div>
                                    </div>
                                    <div class="invalid-feedback-bespoke" id="err_password" style="<?= isset($errors['password']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['password'] ?? 'Password must be at least 6 characters.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 8. GENDER & SEMESTER -->
                            <div class="col-12 col-md-3">
                                <div class="register-input-group">
                                    <label class="form-label-custom">Gender *</label>
                                    <select class="form-select-luxury" name="gender" id="gender" required>
                                        <option value="male" <?= (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-md-3">
                                <div class="register-input-group">
                                    <label class="form-label-custom">Semester *</label>
                                    <select class="form-select-luxury" name="semester" id="semester" required>
                                        <?php for ($i=1; $i<=8; $i++): ?>
                                            <option value="<?= $i ?>" <?= (isset($_POST['semester']) && (int)$_POST['semester'] === $i) ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <!-- SUBMIT BUTTON -->
                        <div class="pt-4">
                            <button type="submit" class="btn-capsule-dark w-100 justify-content-center py-3 fs-6">
                                Register Student Account &rarr;
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 text-center text-xs text-muted pt-3 border-top">
                        Already registered your profile? <a href="login.php" class="text-dark font-bold text-decoration-underline">Sign In To Student Portal</a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- CASCADING FILTER & REAL-TIME VALIDATION SCRIPT -->
<script>
    // 1. CASCADING UNIVERSITY TO COLLEGE FILTER
    function filterCollegesByUniversity() {
        const selectedUnivId = document.getElementById('universitySelect').value;
        const collegeSelect  = document.getElementById('collegeSelect');
        const options        = collegeSelect.querySelectorAll('option');
        let visibleCount     = 0;

        options.forEach(opt => {
            if (opt.value === '') {
                opt.style.display = 'block';
                return;
            }
            const univAttr = opt.getAttribute('data-univ');
            if (!selectedUnivId || univAttr === selectedUnivId) {
                opt.style.display = 'block';
                visibleCount++;
            } else {
                opt.style.display = 'none';
            }
        });

        document.getElementById('collegeCountBadge').innerText = visibleCount + ' Colleges';
        if (collegeSelect.value !== '') {
            const selectedOpt = collegeSelect.options[collegeSelect.selectedIndex];
            if (selectedOpt && selectedOpt.style.display === 'none') {
                collegeSelect.value = '';
            }
        }
    }

    // 2. LIVE SEARCH UNIVERSITY FILTER
    function filterUniversityOptions() {
        const query  = document.getElementById('searchUniversityInput').value.toLowerCase().trim();
        const select = document.getElementById('universitySelect');
        const opts   = select.querySelectorAll('option');
        let count    = 0;

        opts.forEach(opt => {
            if (opt.value === '') return;
            const text = opt.text.toLowerCase();
            if (text.includes(query)) {
                opt.style.display = 'block';
                count++;
            } else {
                opt.style.display = 'none';
            }
        });

        document.getElementById('univCountBadge').innerText = count + ' Available';
    }

    // 3. LIVE SEARCH COLLEGE FILTER
    function filterCollegeOptions() {
        const query          = document.getElementById('searchCollegeInput').value.toLowerCase().trim();
        const selectedUnivId = document.getElementById('universitySelect').value;
        const select         = document.getElementById('collegeSelect');
        const opts           = select.querySelectorAll('option');
        let count            = 0;

        opts.forEach(opt => {
            if (opt.value === '') return;
            const text     = opt.text.toLowerCase();
            const univAttr = opt.getAttribute('data-univ');
            
            const matchUniv  = !selectedUnivId || univAttr === selectedUnivId;
            const matchQuery = text.includes(query);

            if (matchUniv && matchQuery) {
                opt.style.display = 'block';
                count++;
            } else {
                opt.style.display = 'none';
            }
        });

        document.getElementById('collegeCountBadge').innerText = count + ' Colleges';
    }

    // 4. REAL-TIME INPUT VALIDATION HANDLER
    function validateField(fieldName) {
        const field = document.getElementById(fieldName);
        const err   = document.getElementById('err_' + fieldName);
        if (!field || !err) return;

        let isValid = true;
        let msg     = '';

        if (fieldName === 'enrollment_no') {
            if (field.value.trim() === '') {
                isValid = false; msg = 'Enrollment / Roll number is required.';
            } else if (field.value.trim().length < 3) {
                isValid = false; msg = 'Enrollment number must be at least 3 characters.';
            }
        } else if (fieldName === 'name') {
            if (field.value.trim() === '') {
                isValid = false; msg = 'Full name is required.';
            } else if (field.value.trim().length < 2) {
                isValid = false; msg = 'Full name must be at least 2 characters.';
            }
        } else if (fieldName === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (field.value.trim() === '') {
                isValid = false; msg = 'Email address is required.';
            } else if (!emailRegex.test(field.value.trim())) {
                isValid = false; msg = 'Please enter a valid email address.';
            }
        } else if (fieldName === 'phone') {
            const phoneRegex = /^[0-9+\-\s()]{10,15}$/;
            if (field.value.trim() === '') {
                isValid = false; msg = 'Phone number is required.';
            } else if (!phoneRegex.test(field.value.trim())) {
                isValid = false; msg = 'Please enter a valid 10-digit phone number.';
            }
        }

        if (isValid) {
            field.classList.remove('is-invalid');
            err.style.display = 'none';
        } else {
            field.classList.add('is-invalid');
            err.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + msg;
            err.style.display = 'flex';
        }
    }

    // 5. PASSWORD STRENGTH & EYE TOGGLE
    function toggleRegisterPassword() {
        const input = document.getElementById('registerPassword');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }

    function checkPasswordStrength() {
        const val  = document.getElementById('registerPassword').value;
        const fill = document.getElementById('strengthFill');
        const err  = document.getElementById('err_password');

        let score = 0;
        if (val.length >= 6) score += 33;
        if (val.length >= 8) score += 33;
        if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score += 34;

        fill.style.width = score + '%';
        if (score < 33) {
            fill.style.background = '#dc3545';
        } else if (score < 66) {
            fill.style.background = '#d97706';
        } else {
            fill.style.background = '#059669';
        }

        if (val.length < 6) {
            err.style.display = 'flex';
        } else {
            err.style.display = 'none';
        }
    }
</script>

<?php include 'Footer.php'; ?>
