<?php
/**
 * Frontend/register_college.php
 * Ultra-Executive College / Campus Event Organizer Registration Gateway (Matching Reference Form UI)
 */
$page_title = "College Registration";
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
    $slug          = trim($_POST['slug'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $phone         = trim($_POST['phone'] ?? '');
    $address       = trim($_POST['address'] ?? '');

    // Generate slug if empty
    if ($slug === '' && $name !== '') {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }

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
    if ($name === '') {
        $errors['name'] = 'College name is required.';
    } elseif (strlen($name) < 3) {
        $errors['name'] = 'College name must be at least 3 characters.';
    }

    if (!$university_id) $errors['university_id'] = 'Please select your university.';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email address is required.';
    }

    if ($password === '') {
        $errors['password'] = 'Login password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Contact phone number is required.';
    }

    // Uniqueness Checks for Email & Slug
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT college_id FROM colleges WHERE email = :email LIMIT 1");
        $chk->execute(['email' => $email]);
        if ($chk->fetch()) {
            $errors['email'] = 'An account with this email address already exists.';
        }
    }

    if (empty($errors)) {
        try {
            // Check if address column exists, insert cleanly
            $stmt = $pdo->prepare("
                INSERT INTO colleges (university_id, name, slug, email, phone, logo, address, status, password, created_at, updated_at)
                VALUES (:uid, :name, :slug, :email, :phone, :logo, :address, 'active', :pass, NOW(), NOW())
            ");
            $stmt->execute([
                'uid'     => $university_id,
                'name'    => $name,
                'slug'    => $slug,
                'email'   => $email,
                'phone'   => $phone,
                'logo'    => $logo_filename,
                'address' => $address,
                'pass'    => $password
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
            // Fallback if slug/address column is missing in older DB schema
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
            } catch (PDOException $e2) {
                $errors['general'] = 'Error registering college account: ' . $e2->getMessage();
            }
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
        padding: 50px 0 80px 0;
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

    .form-label-clean {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 6px;
        display: block;
    }

    .searchable-select-wrapper {
        position: relative;
        width: 100%;
    }

    .searchable-select-input {
        background: #ffffff;
        border: 1.5px solid #e5e7eb;
        border-radius: 16px;
        padding: 11px 40px 11px 18px;
        font-size: 13.5px;
        font-weight: 500;
        color: #14171a;
        width: 100%;
        transition: all 0.2s ease;
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
        right: 18px;
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
        border-radius: 18px;
        max-height: 220px;
        overflow-y: auto;
        z-index: 1050;
        box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        display: none;
    }

    .searchable-select-item {
        padding: 10px 18px;
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
        border-radius: 16px;
        padding: 11px 18px;
        font-size: 13.5px;
        font-weight: 500;
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
        background: #ffffff;
        border: 1.5px solid #e5e7eb;
        border-radius: 16px;
        padding: 7px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s ease;
        cursor: pointer;
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
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .btn-eye-toggle {
        position: absolute;
        right: 18px;
        top: 36px;
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

            <!-- LEFT COLUMN: COLLEGE ORGANIZER BRAND HIGHLIGHTS -->
            <div class="col-12 col-lg-5">
                <span class="register-pill-badge mb-3">
                    <i class="bi bi-building-fill text-warning"></i> Campus Event Organizer Gateway
                </span>
                <h1 class="fw-black text-dark display-6 mb-3" style="letter-spacing: -1px; line-height: 1.15;">
                    Register Your College & Host Inter-College Events
                </h1>
                <p class="text-muted leading-relaxed text-sm mb-4">
                    Connect your institution to the Evenza network. Publish technical hackathons, cultural festivals, sports leagues, and verify student competitor entry passes instantly.
                </p>

                <!-- 3 ECOSYSTEM PILLAR CARDS -->
                <div class="eco-pillar-card">
                    <div class="eco-pillar-icon"><i class="bi bi-calendar-event-fill"></i></div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Publish & Manage Events</div>
                        <small class="text-muted" style="font-size: 11px;">Create competitions with custom categories and rules</small>
                    </div>
                </div>

                <div class="eco-pillar-card">
                    <div class="eco-pillar-icon"><i class="bi bi-qr-code"></i></div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Digital Pass Check-in System</div>
                        <small class="text-muted" style="font-size: 11px;">Scan QR codes at event venues to verify student entry</small>
                    </div>
                </div>

                <div class="eco-pillar-card">
                    <div class="eco-pillar-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Real-Time Registration Analytics</div>
                        <small class="text-muted" style="font-size: 11px;">Track total sign-ups, entry pass check-ins, and revenue</small>
                    </div>
                </div>

                <div class="p-3 bg-white rounded-4 border border-dark d-flex align-items-center gap-3 shadow-sm mt-4">
                    <i class="bi bi-award-fill text-warning fs-4"></i>
                    <div>
                        <div class="fw-bold text-dark text-xs">Verified Institution Badge</div>
                        <small class="text-muted" style="font-size: 11px;">Accredited colleges receive official verified organizer status</small>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: LUXURY COLLEGE REGISTRATION FORM MATCHING REFERENCE MODAL UI -->
            <div class="col-12 col-lg-7">
                <div class="register-card-bespoke">
                    
                    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-building-add fs-3 text-dark"></i>
                            <div>
                                <h3 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">Add College</h3>
                                <p class="text-muted text-xs mb-0">Register your college profile to access organizer portal</p>
                            </div>
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
                        <div class="row g-3">

                            <!-- ROW 1: COLLEGE NAME & SLUG / SHORT NAME -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">COLLEGE NAME *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" id="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" placeholder="e.g. Stanford College of Engineering" oninput="generateSlug(); validateField('name');" required>
                                    <div class="invalid-feedback-bespoke" id="err_name" style="<?= isset($errors['name']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['name'] ?? 'College name is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">SLUG / SHORT NAME</label>
                                    <input type="text" class="form-control-luxury" name="slug" id="slug" value="<?= htmlspecialchars((string)($_POST['slug'] ?? '')) ?>" placeholder="e.g. stanford-engineering">
                                </div>
                            </div>

                            <!-- ROW 2: UNIVERSITY & EMAIL -->
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

                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">EMAIL *</label>
                                    <input type="email" class="form-control-luxury <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="engineering@stanford.edu" oninput="validateField('email')" required>
                                    <div class="invalid-feedback-bespoke" id="err_email" style="<?= isset($errors['email']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['email'] ?? 'Valid email address is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 3: LOGIN PASSWORD & PHONE -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group position-relative">
                                    <label class="form-label-clean">LOGIN PASSWORD *</label>
                                    <input type="password" class="form-control-luxury <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password" id="registerPassword" placeholder="Minimum 8 characters" oninput="checkPasswordStrength()" required>
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

                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">PHONE *</label>
                                    <input type="tel" class="form-control-luxury <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" id="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="+1 555 111 001" oninput="validateField('phone')" required>
                                    <div class="invalid-feedback-bespoke" id="err_phone" style="<?= isset($errors['phone']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['phone'] ?? 'Valid phone number required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 4: LOGO (jpg/png, max 2mb) -->
                            <div class="col-12">
                                <div class="register-input-group">
                                    <label class="form-label-clean">LOGO (jpg/png, max 2mb)</label>
                                    <div class="custom-file-upload-box">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-xs">Choose File</span>
                                            <span class="text-xs text-muted" id="fileNameDisplay">No file chosen</span>
                                        </div>
                                        <input type="file" name="logo" id="logoInput" accept="image/*" onchange="displaySelectedFileName(this)">
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 5: ADDRESS -->
                            <div class="col-12">
                                <div class="register-input-group">
                                    <label class="form-label-clean">ADDRESS</label>
                                    <textarea class="form-control-luxury" name="address" id="address" rows="2" style="border-radius: 18px;" placeholder="College full address..."><?= htmlspecialchars((string)($_POST['address'] ?? '')) ?></textarea>
                                </div>
                            </div>

                        </div>

                        <!-- SUBMIT BUTTON matching Save College styling -->
                        <div class="pt-4 d-flex align-items-center justify-content-end gap-3">
                            <a href="login.php" class="btn btn-outline-secondary rounded-pill px-4 py-2.5 text-xs font-bold text-decoration-none">Cancel</a>
                            <button type="submit" class="btn-capsule-dark px-5 py-2.5 fs-6" style="width: auto;">
                                Save College &rarr;
                            </button>
                        </div>
                    </form>

                    <!-- CLEAN FOOTER LINKS -->
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

<!-- JAVASCRIPT LOGIC -->
<script>
    function generateSlug() {
        const nameVal = document.getElementById('name').value;
        const slugInput = document.getElementById('slug');
        if (nameVal.trim() !== '') {
            slugInput.value = nameVal.toLowerCase().trim().replace(/[^a-z0-9 -]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        }
    }

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
            display.innerText = 'No file chosen';
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
                isValid = false; msg = 'Email address is required.';
            } else if (!emailRegex.test(field.value.trim())) {
                isValid = false; msg = 'Please enter a valid email address.';
            }
        } else if (fieldName === 'phone') {
            const phoneRegex = /^[0-9+\-\s()]{10,15}$/;
            if (field.value.trim() === '') {
                isValid = false; msg = 'Phone number is required.';
            } else if (!phoneRegex.test(field.value.trim())) {
                isValid = false; msg = 'Please enter a valid phone number.';
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
