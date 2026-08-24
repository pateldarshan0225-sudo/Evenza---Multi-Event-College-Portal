<?php
/**
 * Frontend/register_college.php
 * Ultra-Executive College / Campus Event Organizer Registration Gateway
 */
$page_title = "College & Campus Organizer Registration";
include 'connection.php';
include_once 'frontend_auth.php';

if ($is_logged_in && ($user_role === 'organizer' || $user_role === 'college')) {
    header('Location: ../Organizer/Dashboard.php');
    exit;
}

$errors = [];
$success = false;

// Fetch Universities for Searchable Selector
$universities = $pdo->query("SELECT university_id, name FROM universities ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $university_id = filter_input(INPUT_POST, 'university_id', FILTER_VALIDATE_INT);
    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $password      = $_POST['password'] ?? '';
    
    // College Logo File Processing
    $logo_filename = 'default_college.png';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['logo']['tmp_name'];
        $fileName      = $_FILES['logo']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'college_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $uploadFileDir = '../uploads/colleges/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $logo_filename = $newFileName;
            }
        } else {
            $errors['logo'] = 'Invalid image format. Allowed: JPG, PNG, WEBP, SVG.';
        }
    }

    // Validation
    if (!$university_id) $errors['university_id'] = 'Please select your university.';
    if ($name === '') {
        $errors['name'] = 'College / Institution name is required.';
    } elseif (strlen($name) < 3) {
        $errors['name'] = 'College name must be at least 3 characters.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid official email address is required.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Official contact phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit phone number.';
    }

    if ($password === '') {
        $errors['password'] = 'Account password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    // Uniqueness Checks for College Email
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT college_id FROM colleges WHERE email = :email LIMIT 1");
        $chk->execute(['email' => $email]);
        if ($chk->fetch()) {
            $errors['email'] = 'An institution account with this email address already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO colleges (university_id, name, email, phone, logo, status, password, created_at, updated_at)
                VALUES (:uid, :name, :email, :phone, :logo, 'active', :pass, NOW(), NOW())
            ");
            $stmt->execute([
                'uid'   => $university_id,
                'name'  => $name,
                'email' => $email,
                'phone' => $phone,
                'logo'  => $logo_filename,
                'pass'  => $password
            ]);

            $new_id = $pdo->lastInsertId();
            $_SESSION['college_id']            = $new_id;
            $_SESSION['college_name']          = $name;
            $_SESSION['college_email']         = $email;
            $_SESSION['college_logo']          = $logo_filename;
            $_SESSION['organizer_logged_in']   = true;

            header('Location: ../Organizer/Dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = 'Error registering college account: ' . $e->getMessage();
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

    .form-control-luxury {
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

    .form-control-luxury:focus {
        border-color: #14171a;
        box-shadow: 0 0 0 3px rgba(20, 23, 26, 0.08);
        outline: none;
    }

    .custom-file-upload-box {
        position: relative;
        background: #fdfbf7;
        border: 1.5px dashed #cbd5e1;
        border-radius: 9999px;
        padding: 8px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .custom-file-upload-box:hover {
        border-color: #14171a;
        background: #ffffff;
    }

    .custom-file-upload-box input[type="file"] {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        opacity: 0;
        cursor: pointer;
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

    /* Handcrafted Metric Pills */
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

            <!-- LEFT COLUMN: HANDCRAFTED BRAND HIGHLIGHTS -->
            <div class="col-12 col-lg-5">
                <span class="register-pill-badge mb-3">
                    <i class="bi bi-building-check text-warning"></i> Campus Event Organizer Gateway
                </span>
                <h1 class="fw-black text-dark display-6 mb-3" style="letter-spacing: -1px; line-height: 1.15;">
                    Register Your College & Host Inter-College Events
                </h1>
                <p class="text-muted leading-relaxed text-sm mb-0">
                    Connect your institution to the Evenza network. Publish hackathons, cultural festivals, sports leagues, and verify student competitor entry passes instantly.
                </p>

                <!-- 3 HANDCRAFTED METRIC PILLS -->
                <div class="metric-pill-strip">
                    <div class="metric-pill-item">
                        <div class="metric-pill-val">250+</div>
                        <div class="metric-pill-lbl">Live Events</div>
                    </div>
                    <div class="metric-pill-item">
                        <div class="metric-pill-val">80+</div>
                        <div class="metric-pill-lbl">Universities</div>
                    </div>
                    <div class="metric-pill-item">
                        <div class="metric-pill-val">100%</div>
                        <div class="metric-pill-lbl">QR Accuracy</div>
                    </div>
                </div>

                <!-- EDITORIAL FEATURE LIST -->
                <div class="editorial-feature-list">
                    <div class="editorial-feature-item">
                        <div class="editorial-feature-icon"><i class="bi bi-calendar2-event-fill"></i></div>
                        <div>
                            <div class="fw-bold text-dark text-xs mb-0.5">Publish & Manage Campus Events</div>
                            <div class="text-muted" style="font-size: 11.5px;">Create competitions with custom categories, rules, and registration deadlines</div>
                        </div>
                    </div>

                    <div class="editorial-feature-item">
                        <div class="editorial-feature-icon"><i class="bi bi-qr-code-scan"></i></div>
                        <div>
                            <div class="fw-bold text-dark text-xs mb-0.5">Digital Pass QR Check-in System</div>
                            <div class="text-muted" style="font-size: 11.5px;">Scan digital venue entry passes at gates to verify student credentials</div>
                        </div>
                    </div>

                    <div class="editorial-feature-item" style="border-bottom: none;">
                        <div class="editorial-feature-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
                        <div>
                            <div class="fw-bold text-dark text-xs mb-0.5">Real-Time Registration Analytics</div>
                            <div class="text-muted" style="font-size: 11.5px;">Track live sign-ups, squad teams, entry pass check-in rates, and revenue</div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-white rounded-4 border border-dark d-flex align-items-center gap-3 shadow-sm">
                    <i class="bi bi-shield-lock-fill text-success fs-4"></i>
                    <div>
                        <div class="fw-bold text-dark text-xs">Verified Institution Gateway</div>
                        <small class="text-muted" style="font-size: 11px;">Accredited colleges receive official verified organizer status & portal access</small>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: REFINED LUXURY FORM CONTAINER -->
            <div class="col-12 col-lg-7">
                <div class="register-card-bespoke">
                    
                    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                        <div>
                            <h3 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">College Registration</h3>
                            <p class="text-muted text-xs mb-0">Select university affiliation & enter institutional details</p>
                        </div>
                        <span class="badge bg-warning text-dark font-bold px-3 py-1.5 rounded-pill text-xs border border-dark">
                            Organizer Account
                        </span>
                    </div>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger rounded-4 border-0 text-xs py-3 px-3 mb-4 shadow-sm" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errors['general']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="collegeRegisterForm" enctype="multipart/form-data" novalidate>
                        <div class="row g-4">

                            <!-- 1. SEARCH UNIVERSITY (EXACT REFERENCE UI WITH LIVE CHAR-BY-CHAR SEARCH) -->
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

                            <!-- 2. COLLEGE / INSTITUTION NAME -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">COLLEGE / INSTITUTION NAME *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" id="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" placeholder="e.g. Stanford College of Engineering" oninput="validateField('name')" required>
                                    <div class="invalid-feedback-bespoke" id="err_name" style="<?= isset($errors['name']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['name'] ?? 'College name is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. OFFICIAL EMAIL ADDRESS -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">OFFICIAL EMAIL ADDRESS *</label>
                                    <input type="email" class="form-control-luxury <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="events@college.edu" oninput="validateField('email')" required>
                                    <div class="invalid-feedback-bespoke" id="err_email" style="<?= isset($errors['email']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['email'] ?? 'Valid official email address is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. OFFICIAL PHONE NUMBER -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">OFFICIAL PHONE NUMBER *</label>
                                    <input type="tel" class="form-control-luxury <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" id="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="10-digit phone / landline" oninput="validateField('phone')" required>
                                    <div class="invalid-feedback-bespoke" id="err_phone" style="<?= isset($errors['phone']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['phone'] ?? 'Valid 10-digit phone number required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 5. PASSWORD WITH EYE TOGGLE & STRENGTH BAR -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group position-relative">
                                    <label class="form-label-clean">ACCOUNT PASSWORD *</label>
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

                            <!-- 6. CUSTOM BESPOKE LOGO UPLOAD CONTROL -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">COLLEGE LOGO (OPTIONAL)</label>
                                    <div class="custom-file-upload-box">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-cloud-arrow-up-fill text-warning fs-5"></i>
                                            <span class="text-xs font-bold text-dark" id="fileNameDisplay">Upload Official Logo...</span>
                                        </div>
                                        <span class="badge bg-dark text-white rounded-pill px-2.5 py-1 text-xs">Browse</span>
                                        <input type="file" name="logo" id="logoInput" accept="image/*" onchange="displaySelectedFileName(this)">
                                    </div>
                                    <div class="invalid-feedback-bespoke" id="err_logo" style="<?= isset($errors['logo']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['logo'] ?? '') ?>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- SUBMIT BUTTON -->
                        <div class="pt-4">
                            <button type="submit" class="btn-capsule-dark w-100 justify-content-center py-3 fs-6">
                                Register College & Access Organizer Portal &rarr;
                            </button>
                        </div>
                    </form>

                    <!-- CLEAN FOOTER LINKS WITH DOT SEPARATORS -->
                    <div class="mt-4 text-center text-xs text-muted pt-3 border-top d-flex justify-content-center align-items-center gap-3 flex-wrap">
                        <span>Already registered your college? <a href="login.php" class="text-dark font-bold text-decoration-underline">Sign In To Organizer Portal</a></span>
                        <span class="text-muted opacity-40">&bull;</span>
                        <span>Student? <a href="register.php" class="text-dark font-bold text-decoration-underline">Register Student Profile</a></span>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- CASCADING SEARCHABLE SELECT JAVASCRIPT -->
<script>
    function openDropdown(type) {
        if (type === 'university') {
            document.getElementById('universityDropdown').style.display = 'block';
            filterDropdown('university');
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
        }
    }

    function selectUniversity(id, name) {
        document.getElementById('universityInput').value = name;
        document.getElementById('universitySelect').value = id;
        document.getElementById('universityDropdown').style.display = 'none';
        document.getElementById('err_university_id').style.display = 'none';
        document.getElementById('universityInput').classList.remove('is-invalid');
    }

    function displaySelectedFileName(input) {
        const display = document.getElementById('fileNameDisplay');
        if (input.files && input.files[0]) {
            display.innerText = input.files[0].name;
        } else {
            display.innerText = 'Upload Official Logo...';
        }
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#universityWrapper') && !e.target.closest('#universityDropdown')) {
            const univDropdown = document.getElementById('universityDropdown');
            if (univDropdown) univDropdown.style.display = 'none';
        }
    });

    // Real-Time Form Validation
    function validateField(fieldName) {
        const field = document.getElementById(fieldName);
        const err   = document.getElementById('err_' + fieldName);
        if (!field || !err) return;

        let isValid = true;
        let msg     = '';

        if (fieldName === 'name') {
            if (field.value.trim() === '') {
                isValid = false; msg = 'College name is required.';
            } else if (field.value.trim().length < 3) {
                isValid = false; msg = 'College name must be at least 3 characters.';
            }
        } else if (fieldName === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (field.value.trim() === '') {
                isValid = false; msg = 'Official email address is required.';
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
            }
        }
    });
</script>

<?php include 'Footer.php'; ?>
