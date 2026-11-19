<?php
require_once __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();

/**
 * Frontend/register_university.php
 * Ultra-Executive University Registration Gateway (Matching Reference Form UI)
 */
$page_title = "University Registration";
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../includes/phone_helper.php';
include_once 'frontend_auth.php';

if ($is_logged_in) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $short_name = trim($_POST['short_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';    $address    = trim($_POST['address'] ?? '');

    // Auto-generate short_name if empty
    if ($short_name === '' && $name !== '') {
        $words = explode(' ', $name);
        $acronym = '';
        foreach ($words as $w) {
            if (strlen($w) > 0 && !in_array(strtolower($w), ['of', 'and', 'the', 'for', 'in'])) {
                $acronym .= strtoupper($w[0]);
            }
        }
        $short_name = $acronym ?: strtoupper(substr($name, 0, 5));
    }

    // University Logo Processing
    $logo_filename = 'default_univ.png';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['logo']['tmp_name'];
        $fileName      = $_FILES['logo']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'u_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $uploadDir1  = '../uploads/universities/';
            $uploadDir2  = '../assets/images/universities/';
            
            if (!is_dir($uploadDir1)) mkdir($uploadDir1, 0755, true);
            if (!is_dir($uploadDir2)) mkdir($uploadDir2, 0755, true);

            $dest1 = $uploadDir1 . $newFileName;
            $dest2 = $uploadDir2 . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest1)) {
                @copy($dest1, $dest2);
                $logo_filename = $newFileName;
            }
        } else {
            $errors['logo'] = 'Invalid image format. Allowed: JPG, PNG, WEBP, SVG.';
        }
    }

    // Validation
    if ($name === '') {
        $errors['name'] = 'University name is required.';
    } elseif (strlen($name) < 2) {
        $errors['name'] = 'University name must be at least 2 characters.';
    }

    if ($short_name === '') {
        $errors['short_name'] = 'Short name / acronym is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email address is required.';
    }

    if ($phone === '' || !is_valid_phone_number($phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit Indian mobile number.';
    }

    if ($password === '') {
        $errors['password'] = 'Account password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    // Uniqueness Checks
    if (empty($errors)) {
        $clean_email = mysqli_real_escape_string($conn, $email);
        $chk_res = mysqli_query($conn, "SELECT university_id FROM universities WHERE email = '$clean_email' LIMIT 1");
        if ($chk_res && mysqli_fetch_assoc($chk_res)) {
            $errors['email'] = 'A university account with this email address already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $uname       = mysqli_real_escape_string($conn, $name);
            $ushort_name = mysqli_real_escape_string($conn, $short_name);
            $uemail      = mysqli_real_escape_string($conn, $email);
            $uphone      = mysqli_real_escape_string($conn, (string)normalize_phone_number($phone));
            $ulogo       = mysqli_real_escape_string($conn, $logo_filename);
            $uaddress    = mysqli_real_escape_string($conn, $address);

            $ins_sql = "
                INSERT INTO universities (name, short_name, email, phone, logo, address, status, created_at, updated_at)
                VALUES ('$uname', '$ushort_name', '$uemail', '$uphone', '$ulogo', '$uaddress', 'active', NOW(), NOW())
            ";
            if (!mysqli_query($conn, $ins_sql)) {
                throw new Exception(mysqli_error($conn));
            }

            $new_id = mysqli_insert_id($conn);
            $tab_id = get_current_tab_id();
            if (!$tab_id) {
                $tab_id = 'tab_' . bin2hex(random_bytes(16));
                setcookie('evenza_tab_id', $tab_id, 0, '/');
            }
            set_tab_auth($tab_id, [
                'user_id'    => $new_id,
                'role'       => 'admin',
                'name'       => $name,
                'email'      => $email,
                'college_id' => null
            ]);

            header('Location: ../Admin/Dashboard.php');
            exit;
        } catch (Exception $e) {
            $errors['general'] = 'Registration failed: ' . $e->getMessage();
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

    .form-control-luxury {
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

<link rel="stylesheet" href="../assets/css/registration.css">
<section class="register-gateway-section">
    <div class="container-xl">
        <div class="row g-5 align-items-center justify-content-center">

            <!-- LEFT COLUMN: UNIVERSITY GOVERNING HIGHLIGHTS -->
            <div class="col-12 col-lg-5"><div class="registration-story">
<span class="registration-eyebrow"><i class="bi bi-bank" aria-hidden="true"></i> University account</span>
<h1>Connect your campus<br><span>community.</span></h1><p>Bring your university to Evenza and keep your institutional information and affiliated campus network together.</p>
<div class="registration-benefits"><div class="registration-benefit"><i class="bi bi-bank" aria-hidden="true"></i><div><h2>Create an institutional profile</h2><p>Add your university identity and contact details.</p></div></div><div class="registration-benefit"><i class="bi bi-buildings" aria-hidden="true"></i><div><h2>Connect your colleges</h2><p>Keep your affiliated campus network organized.</p></div></div><div class="registration-benefit"><i class="bi bi-grid" aria-hidden="true"></i><div><h2>Access your workspace</h2><p>Manage campus information from your administration dashboard.</p></div></div></div><div class="registration-note"><i class="bi bi-stars" aria-hidden="true"></i> Built for campus communities.</div></div></div>

            <div class="col-12 col-lg-7">
                <div class="register-card-bespoke">
                    <?php require __DIR__ . '/../includes/registration_roles.php'; ?>
                    
                    <div class="registration-form-heading d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-bank2 fs-3 text-dark"></i>
                            <div>
                                <h3 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">Register your university</h3>
                                <p class="text-muted text-xs mb-0">Enter your institution details to create a university account.</p>
                            </div>
                        </div>
                        <span class="badge bg-warning text-dark font-bold px-3 py-1.5 rounded-pill text-xs border border-dark">
                            University Admin
                        </span>
                    </div>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger rounded-4 border-0 text-xs py-3 px-3 mb-4 shadow-sm" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errors['general']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="universityRegisterForm" enctype="multipart/form-data" novalidate>
                        <div class="row g-3">

                            <!-- ROW 1: UNIVERSITY NAME & SHORT NAME -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">University Name *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" id="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" placeholder="e.g. Manipal Academy of Higher Education" oninput="generateAcronym(); validateField('name');" required>
                                    <div class="invalid-feedback-bespoke" id="err_name" style="<?= isset($errors['name']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['name'] ?? 'University name is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">Short Name *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['short_name']) ? 'is-invalid' : '' ?>" name="short_name" id="short_name" value="<?= htmlspecialchars((string)($_POST['short_name'] ?? '')) ?>" placeholder="e.g. MAHE" oninput="validateField('short_name')" required>
                                    <div class="invalid-feedback-bespoke" id="err_short_name" style="<?= isset($errors['short_name']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['short_name'] ?? 'Short name is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 2: EMAIL & PHONE -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">Email *</label>
                                    <input type="email" class="form-control-luxury <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="info@manipal.edu" oninput="validateField('email')" required>
                                    <div class="invalid-feedback-bespoke" id="err_email" style="<?= isset($errors['email']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['email'] ?? 'Valid email address is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">Phone *</label>
                                    <input type="tel" class="form-control-luxury <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" id="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="+91 820 292 2400" oninput="validateField('phone')" required>
                                    <div class="invalid-feedback-bespoke" id="err_phone" style="<?= isset($errors['phone']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['phone'] ?? 'Valid phone number required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 3: ACCOUNT PASSWORD & LOGO -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group position-relative">
                                    <label class="form-label-clean">Account Password *</label>
                                    <input type="password" class="form-control-luxury <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password" id="registerPassword" placeholder="Minimum 6 characters" oninput="checkPasswordStrength()" required>
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

                            <!-- ROW 4: ADDRESS -->
                            <div class="col-12">
                                <div class="register-input-group">
                                    <label class="form-label-clean">Address</label>
                                    <textarea class="form-control-luxury" name="address" id="address" rows="2" style="border-radius: 18px;" placeholder="University campus full address..."><?= htmlspecialchars((string)($_POST['address'] ?? '')) ?></textarea>
                                </div>
                            </div>

                        </div>

                        <!-- SUBMIT BUTTON matching Save University styling -->
                        <div class="pt-4 d-flex align-items-center justify-content-end gap-3">
                            <button type="submit" class="btn-capsule-dark px-5 py-2.5 fs-6" style="width: auto;">
                                Create university account &rarr;
                            </button>
                        </div>
                    </form>

                    <!-- CLEAN FOOTER LINKS WITH DOT SEPARATORS -->
                    <div class="registration-form-footer">Already have an account? <a href="login.php">Sign in</a></div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- JAVASCRIPT LOGIC -->
<script>
    function generateAcronym() {
        const nameVal = document.getElementById('name').value.trim();
        const shortInput = document.getElementById('short_name');
        if (nameVal !== '') {
            const words = nameVal.split(/\s+/);
            let acronym = '';
            words.forEach(w => {
                if (w.length > 0 && !['of', 'and', 'the', 'for', 'in'].includes(w.toLowerCase())) {
                    acronym += w[0].toUpperCase();
                }
            });
            if (acronym.length >= 2) {
                shortInput.value = acronym;
            }
        }
    }

    function displaySelectedFileName(input) {
        const display = document.getElementById('fileNameDisplay');
        if (input.files && input.files[0]) {
            display.innerText = input.files[0].name;
        } else {
            display.innerText = 'No file chosen';
        }
    }

    // Real-Time Form Validation
    function validateField(fieldName) {
        const field = document.getElementById(fieldName);
        const err   = document.getElementById('err_' + fieldName);
        if (!field || !err) return;

        let isValid = true;
        let msg     = '';

        if (fieldName === 'name') {
            if (field.value.trim() === '') {
                isValid = false; msg = 'University name is required.';
            } else if (field.value.trim().length < 2) {
                isValid = false; msg = 'University name must be at least 2 characters.';
            }
        } else if (fieldName === 'short_name') {
            if (field.value.trim() === '') {
                isValid = false; msg = 'Short name is required.';
            }
        } else if (fieldName === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (field.value.trim() === '') {
                isValid = false; msg = 'Email address is required.';
            } else if (!emailRegex.test(field.value.trim())) {
                isValid = false; msg = 'Please enter a valid email address.';
            }
        } else if (fieldName === 'phone') {
            const phoneRegex = /^[0-9+\-\s()]{7,20}$/;
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
</script>

<script src="../assets/js/registration-ui.js"></script>
<?php include 'Footer.php'; ?>
