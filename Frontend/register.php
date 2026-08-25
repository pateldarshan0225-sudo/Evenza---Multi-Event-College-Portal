<?php
/**
 * Frontend/register.php
 * Ultra-Executive Student Registration Gateway
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
            $tab_id = get_current_tab_id();
            if (!$tab_id) {
                $tab_id = 'tab_' . bin2hex(random_bytes(16));
                setcookie('evenza_tab_id', $tab_id, 0, '/');
            }
            set_tab_auth($tab_id, [
                'user_id'    => $new_id,
                'role'       => 'student',
                'name'       => $name,
                'email'      => $email,
                'college_id' => $college_id
            ]);

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
        padding: 60px 0 90px 0;
    }

    .register-pill-badge {
        background: #ffffff;
        border: 1.5px solid #14171a;
        box-shadow: 3px 3px 0px #ffd13b;
        color: #14171a;
        font-weight: 800;
        font-size: 11px;
        padding: 7px 18px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.2px;
    }

    .register-card-bespoke {
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 36px;
        padding: 44px;
        box-shadow: 8px 8px 0px #14171a;
        position: relative;
    }

    .register-input-group {
        position: relative;
    }

    .form-label-clean {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: #4b5563;
        margin-bottom: 8px;
        display: block;
    }

    .searchable-select-wrapper {
        position: relative;
        width: 100%;
    }

    .searchable-select-input {
        background: #ffffff;
        border: 1.5px solid #e5e7eb;
        border-radius: 9999px;
        padding: 13px 44px 13px 22px;
        font-size: 13.5px;
        font-weight: 600;
        color: #14171a;
        width: 100%;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .searchable-select-input::placeholder {
        color: #9ca3af;
        font-weight: 400;
    }

    .searchable-select-input:focus {
        border-color: #14171a;
        outline: none;
        box-shadow: 0 0 0 3px rgba(20, 23, 26, 0.08);
    }

    .select-caret-icon {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 11px;
        pointer-events: none;
    }

    .searchable-select-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1.5px solid #14171a;
        border-radius: 20px;
        max-height: 220px;
        overflow-y: auto;
        z-index: 1050;
        box-shadow: 0 12px 30px rgba(0,0,0,0.12);
        display: none;
    }

    .searchable-select-item {
        padding: 11px 20px;
        font-size: 13px;
        font-weight: 600;
        color: #14171a;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .searchable-select-item:hover {
        background: #ffd13b;
        color: #14171a;
    }

    .form-control-luxury, .form-select-luxury {
        background: #ffffff;
        border: 1.5px solid #e5e7eb;
        border-radius: 9999px;
        padding: 13px 22px;
        font-size: 13.5px;
        font-weight: 600;
        color: #14171a;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        width: 100%;
    }

    .form-control-luxury::placeholder {
        color: #9ca3af;
        font-weight: 400;
    }

    .form-control-luxury:focus, .form-select-luxury:focus {
        border-color: #14171a;
        box-shadow: 0 0 0 3px rgba(20, 23, 26, 0.08);
        outline: none;
    }

    .invalid-feedback-bespoke {
        color: #dc3545;
        font-size: 11px;
        font-weight: 700;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .btn-eye-toggle {
        position: absolute;
        right: 20px;
        top: 40px;
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        font-size: 15px;
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

    .metric-pill-strip {
        display: flex;
        align-items: center;
        gap: 16px;
        margin: 24px 0 32px 0;
        flex-wrap: wrap;
    }

    .metric-pill-item {
        background: #ffffff;
        border: 1.5px solid #14171a;
        border-radius: 18px;
        padding: 10px 18px;
        box-shadow: 3px 3px 0px #14171a;
    }
    .metric-pill-val { font-size: 18px; font-weight: 900; color: #14171a; line-height: 1; }
    .metric-pill-lbl { font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-top: 3px; }

    .editorial-feature-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-bottom: 32px;
    }

    .editorial-feature-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding-bottom: 14px;
        border-bottom: 1px dashed #e2e8f0;
    }

    .editorial-feature-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: #ffd13b;
        border: 1.5px solid #14171a;
        color: #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .register-card-bespoke { padding: 26px; border-radius: 28px; }
    }
</style>

<section class="register-gateway-section">
    <div class="container-xl">
        <div class="row g-5 align-items-center justify-content-center">

            <!-- LEFT COLUMN: HANDCRAFTED STUDENT BRAND HIGHLIGHTS -->
            <div class="col-12 col-lg-5">
                <span class="register-pill-badge mb-3">
                    <i class="bi bi-mortarboard-fill text-warning"></i> Student Competitor Access
                </span>
                <h1 class="fw-black text-dark display-6 mb-3" style="letter-spacing: -1px; line-height: 1.15;">
                    Create Your Verified Student Profile
                </h1>
                <p class="text-muted leading-relaxed text-sm mb-0">
                    Join 250+ accredited universities and partner institutions. Register once to claim digital venue entry passes, create hackathon squad teams, and compete across top campus festivals.
                </p>

                <!-- 3 HANDCRAFTED METRIC PILLS -->
                <div class="metric-pill-strip">
                    <div class="metric-pill-item">
                        <div class="metric-pill-val">250+</div>
                        <div class="metric-pill-lbl">Inter-College Events</div>
                    </div>
                    <div class="metric-pill-item">
                        <div class="metric-pill-val">80+</div>
                        <div class="metric-pill-lbl">Partner Colleges</div>
                    </div>
                    <div class="metric-pill-item">
                        <div class="metric-pill-val">100%</div>
                        <div class="metric-pill-lbl">Instant Passes</div>
                    </div>
                </div>

                <!-- EDITORIAL FEATURE LIST -->
                <div class="editorial-feature-list">
                    <div class="editorial-feature-item">
                        <div class="editorial-feature-icon"><i class="bi bi-qr-code-scan"></i></div>
                        <div>
                            <div class="fw-bold text-dark text-xs mb-0.5">Instant QR Venue Entry Passes</div>
                            <div class="text-muted" style="font-size: 11.5px;">Verified digital tickets generated upon registration for gate scanning</div>
                        </div>
                    </div>

                    <div class="editorial-feature-item">
                        <div class="editorial-feature-icon"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="fw-bold text-dark text-xs mb-0.5">Multi-Member Squad Rosters</div>
                            <div class="text-muted" style="font-size: 11.5px;">Generate team codes & invite campus squad mates to hackathons</div>
                        </div>
                    </div>

                    <div class="editorial-feature-item" style="border-bottom: none;">
                        <div class="editorial-feature-icon"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <div class="fw-bold text-dark text-xs mb-0.5">Single Sign-On Integration</div>
                            <div class="text-muted" style="font-size: 11.5px;">Access student competitor dashboard instantly with institutional credentials</div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-white rounded-4 border border-dark d-flex align-items-center gap-3 shadow-sm">
                    <i class="bi bi-lock-fill text-success fs-4"></i>
                    <div>
                        <div class="fw-bold text-dark text-xs">256-Bit SSL Encrypted Gateway</div>
                        <small class="text-muted" style="font-size: 11px;">Your institutional data is protected under FERPA & privacy protocols</small>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: REFINED LUXURY FORM CONTAINER -->
            <div class="col-12 col-lg-7">
                <div class="register-card-bespoke">
                    
                    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                        <div>
                            <h3 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">Student Profile Registration</h3>
                            <p class="text-muted text-xs mb-0">Select your university & college to connect your student account</p>
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
                        <div class="row g-4">

                            <!-- 1. SEARCH UNIVERSITY -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">UNIVERSITY *</label>
                                    <div class="searchable-select-wrapper" id="universityWrapper">
                                        <input type="text" 
                                               id="universityInput" 
                                               class="searchable-select-input <?= isset($errors['university_id']) ? 'is-invalid' : '' ?>" 
                                               placeholder="Search university..." 
                                               autocomplete="off"
                                               onfocus="openDropdown('university')" 
                                               onclick="openDropdown('university')"
                                               oninput="openDropdown('university'); filterDropdown('university');"
                                               onkeyup="openDropdown('university'); filterDropdown('university');">
                                        <i class="bi bi-caret-down-fill select-caret-icon"></i>
                                        <input type="hidden" name="university_id" id="universitySelect" value="<?= htmlspecialchars((string)($_POST['university_id'] ?? '')) ?>" required>

                                        <div class="searchable-select-dropdown" id="universityDropdown">
                                            <?php foreach ($universities as $u): ?>
                                                <div class="searchable-select-item" data-value="<?= $u['university_id'] ?>" onclick="selectUniversity(<?= $u['university_id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                                    <?= htmlspecialchars((string)$u['name']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback-bespoke" id="err_university_id" style="<?= isset($errors['university_id']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['university_id'] ?? 'Please select your university.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. SEARCH COLLEGE (CASCADING DEPENDENT) -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">COLLEGE *</label>
                                    <div class="searchable-select-wrapper" id="collegeWrapper">
                                        <input type="text" 
                                               id="collegeInput" 
                                               class="searchable-select-input <?= isset($errors['college_id']) ? 'is-invalid' : '' ?>" 
                                               placeholder="Search college..." 
                                               autocomplete="off"
                                               onfocus="openDropdown('college')" 
                                               onclick="openDropdown('college')"
                                               oninput="openDropdown('college'); filterDropdown('college');"
                                               onkeyup="openDropdown('college'); filterDropdown('college');">
                                        <i class="bi bi-caret-down-fill select-caret-icon"></i>
                                        <input type="hidden" name="college_id" id="collegeSelect" value="<?= htmlspecialchars((string)($_POST['college_id'] ?? '')) ?>" required>

                                        <div class="searchable-select-dropdown" id="collegeDropdown">
                                            <?php foreach ($colleges as $c): ?>
                                                <div class="searchable-select-item college-item" data-value="<?= $c['college_id'] ?>" data-univ="<?= $c['university_id'] ?>" onclick="selectCollege(<?= $c['college_id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')">
                                                    <?= htmlspecialchars((string)$c['name']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback-bespoke" id="err_college_id" style="<?= isset($errors['college_id']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['college_id'] ?? 'Please select your college.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. ENROLLMENT / ROLL NO -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">ENROLLMENT / ROLL NO *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['enrollment_no']) ? 'is-invalid' : '' ?>" name="enrollment_no" id="enrollment_no" value="<?= htmlspecialchars((string)($_POST['enrollment_no'] ?? '')) ?>" placeholder="e.g. STU-100-1" oninput="validateField('enrollment_no')" required>
                                    <div class="invalid-feedback-bespoke" id="err_enrollment_no" style="<?= isset($errors['enrollment_no']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['enrollment_no'] ?? 'Enrollment number is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. FULL NAME -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">FULL NAME *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" id="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" placeholder="Your full legal name" oninput="validateField('name')" required>
                                    <div class="invalid-feedback-bespoke" id="err_name" style="<?= isset($errors['name']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['name'] ?? 'Full name is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 5. EMAIL ADDRESS -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">EMAIL ADDRESS *</label>
                                    <input type="email" class="form-control-luxury <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="student@college.edu" oninput="validateField('email')" required>
                                    <div class="invalid-feedback-bespoke" id="err_email" style="<?= isset($errors['email']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['email'] ?? 'Valid email address is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 6. PHONE NUMBER -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">PHONE NUMBER *</label>
                                    <input type="tel" class="form-control-luxury <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" id="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="10-digit phone number" oninput="validateField('phone')" required>
                                    <div class="invalid-feedback-bespoke" id="err_phone" style="<?= isset($errors['phone']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['phone'] ?? 'Valid 10-digit phone number required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 7. PASSWORD WITH EYE TOGGLE & STRENGTH BAR -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group position-relative">
                                    <label class="form-label-clean">PASSWORD *</label>
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
                                    <label class="form-label-clean">GENDER *</label>
                                    <select class="form-select-luxury" name="gender" id="gender" required>
                                        <option value="male" <?= (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-md-3">
                                <div class="register-input-group">
                                    <label class="form-label-clean">CURRENT SEMESTER *</label>
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

                    <!-- CLEAN FOOTER LINKS WITH DOT SEPARATORS -->
                    <div class="mt-4 text-center text-xs text-muted pt-3 border-top d-flex justify-content-center align-items-center gap-3 flex-wrap">
                        <span>Already registered your profile? <a href="login.php" class="text-dark font-bold text-decoration-underline">Sign In To Student Portal</a></span>
                        <span class="text-muted opacity-40">&bull;</span>
                        <span>College / Institution? <a href="register_college.php" class="text-dark font-bold text-decoration-underline">College Registration</a></span>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- CASCADING SEARCHABLE SELECT JAVASCRIPT -->
<script>
    let selectedUniversityId = null;

    function openDropdown(type) {
        document.getElementById('universityDropdown').style.display = 'none';
        document.getElementById('collegeDropdown').style.display = 'none';

        if (type === 'university') {
            document.getElementById('universityDropdown').style.display = 'block';
            filterDropdown('university');
        } else if (type === 'college') {
            document.getElementById('collegeDropdown').style.display = 'block';
            filterDropdown('college');
        }
    }

    function filterDropdown(type) {
        if (type === 'university') {
            const query = document.getElementById('universityInput').value.toLowerCase().trim();
            const items = document.querySelectorAll('#universityDropdown .searchable-select-item:not(.no-results)');
            const dropdown = document.getElementById('universityDropdown');
            dropdown.style.display = 'block';

            let visibleCount = 0;
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                if (text.includes(query)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            let noMatch = document.getElementById('noMatchUniv');
            if (visibleCount === 0) {
                if (!noMatch) {
                    noMatch = document.createElement('div');
                    noMatch.id = 'noMatchUniv';
                    noMatch.className = 'searchable-select-item no-results opacity-70';
                    noMatch.innerText = 'No matching university found';
                    dropdown.appendChild(noMatch);
                } else {
                    noMatch.style.display = 'block';
                }
            } else if (noMatch) {
                noMatch.style.display = 'none';
            }

        } else if (type === 'college') {
            const query = document.getElementById('collegeInput').value.toLowerCase().trim();
            const items = document.querySelectorAll('#collegeDropdown .college-item');
            const dropdown = document.getElementById('collegeDropdown');
            dropdown.style.display = 'block';

            let visibleCount = 0;
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                const univId = item.getAttribute('data-univ');
                const matchUniv = !selectedUniversityId || univId == selectedUniversityId;
                const matchQuery = text.includes(query);

                if (matchUniv && matchQuery) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            let noMatch = document.getElementById('noMatchCol');
            if (visibleCount === 0) {
                if (!noMatch) {
                    noMatch = document.createElement('div');
                    noMatch.id = 'noMatchCol';
                    noMatch.className = 'searchable-select-item no-results opacity-70';
                    noMatch.innerText = 'No matching college found';
                    dropdown.appendChild(noMatch);
                } else {
                    noMatch.style.display = 'block';
                }
            } else if (noMatch) {
                noMatch.style.display = 'none';
            }
        }
    }

    function selectUniversity(id, name) {
        document.getElementById('universityInput').value = name;
        document.getElementById('universitySelect').value = id;
        selectedUniversityId = id;
        document.getElementById('universityDropdown').style.display = 'none';
        document.getElementById('err_university_id').style.display = 'none';
        document.getElementById('universityInput').classList.remove('is-invalid');

        // Reset college field and auto open college search
        document.getElementById('collegeInput').value = '';
        document.getElementById('collegeSelect').value = '';
        openDropdown('college');
    }

    function selectCollege(id, name) {
        document.getElementById('collegeInput').value = name;
        document.getElementById('collegeSelect').value = id;
        document.getElementById('collegeDropdown').style.display = 'none';
        document.getElementById('err_college_id').style.display = 'none';
        document.getElementById('collegeInput').classList.remove('is-invalid');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#universityWrapper') && !e.target.closest('#universityDropdown')) {
            const univDropdown = document.getElementById('universityDropdown');
            if (univDropdown) univDropdown.style.display = 'none';
        }
        if (!e.target.closest('#collegeWrapper') && !e.target.closest('#collegeDropdown')) {
            const collegeDropdown = document.getElementById('collegeDropdown');
            if (collegeDropdown) collegeDropdown.style.display = 'none';
        }
    });

    // Real-Time Form Validation
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

    window.addEventListener('DOMContentLoaded', function() {
        const selectedUnivId = document.getElementById('universitySelect').value;
        if (selectedUnivId) {
            const item = document.querySelector(`#universityDropdown .searchable-select-item[data-value="${selectedUnivId}"]`);
            if (item) {
                document.getElementById('universityInput').value = item.innerText.trim();
                selectedUniversityId = selectedUnivId;
            }
        }

        const selectedCollegeId = document.getElementById('collegeSelect').value;
        if (selectedCollegeId) {
            const item = document.querySelector(`#collegeDropdown .searchable-select-item[data-value="${selectedCollegeId}"]`);
            if (item) {
                document.getElementById('collegeInput').value = item.innerText.trim();
            }
        }
    });
</script>

<?php include 'Footer.php'; ?>
