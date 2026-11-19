<?php

/**
 * Frontend/register_college.php
 * Ultra-Executive College / Campus Event Organizer Registration Gateway (Matching Reference Form UI)
 */
$page_title = "College Registration";
require_once __DIR__ . '/connection.php';
$conn = get_evenza_connection();
require_once __DIR__ . '/../includes/phone_helper.php';
require_once __DIR__ . '/frontend_auth.php';

if ($is_logged_in && ($user_role === 'organizer' || $user_role === 'college')) {
    header('Location: ../Organizer/Dashboard.php');
    exit;
}

$errors = [];

// Fetch Universities for Searchable Selector
$res_uni = mysqli_query($conn, "SELECT university_id, name FROM universities ORDER BY name ASC");
$universities = [];
if ($res_uni) {
    while ($r = mysqli_fetch_assoc($res_uni)) {
        $universities[] = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reject array-shaped fields before trimming or rendering submitted values.
    foreach (['name', 'slug', 'email', 'password', 'phone', 'address', 'university_id'] as $field) {
        if (isset($_POST[$field]) && !is_string($_POST[$field])) {
            $_POST[$field] = '';
            $errors['general'] = 'Please enter valid registration details.';
        }
    }
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

    // Validate uploads now; move the file only after all form checks succeed.
    $logo_filename = 'default_college.png';
    $logo_extension = null;
    $upload_error = $_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($upload_error !== UPLOAD_ERR_NO_FILE) {
        if ($upload_error !== UPLOAD_ERR_OK) {
            $errors['logo'] = 'Logo upload failed. Choose a JPG, PNG or WEBP image up to 2 MB.';
        } elseif (($_FILES['logo']['size'] ?? 0) > 2 * 1024 * 1024) {
            $errors['logo'] = 'Logo must be no larger than 2 MB.';
        } else {
            $image = @getimagesize($_FILES['logo']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $logo_extension = $image ? ($extensions[$image['mime']] ?? null) : null;
            if (!$logo_extension) {
                $errors['logo'] = 'Please choose a valid JPG, PNG or WEBP image.';
            }
        }
    }

    // Validation
    if ($name === '') {
        $errors['name'] = 'College name is required.';
    } elseif (strlen($name) < 3) {
        $errors['name'] = 'College name must be at least 3 characters.';
    }

    if (!$university_id || $university_id < 1 || !in_array($university_id, array_column($universities, 'university_id'))) {
        $errors['university_id'] = 'Please select an existing university from the list.';
    }
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 150) {
        $errors['slug'] = 'Use lowercase letters, numbers and single hyphens (maximum 150 characters).';
    }
    if (strlen($name) > 150) $errors['name'] = 'College name must be at most 150 characters.';
    if (strlen($email) > 100) $errors['email'] = 'Email address must be at most 100 characters.';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email address is required.';
    }

    if ($password === '') {
        $errors['password'] = 'Login password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password must be at most 72 bytes.';
    }

    if ($phone === '' || !is_valid_phone_number($phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit Indian mobile number.';
    }

    // Uniqueness Checks for Email & Slug
    if (empty($errors)) {
        $check = mysqli_prepare($conn, 'SELECT email, slug FROM colleges WHERE email = ? OR slug = ?');
        if (!$check) {
            $errors['general'] = 'Registration is temporarily unavailable. Please try again.';
        } else {
            mysqli_stmt_bind_param($check, 'ss', $email, $slug);
            if (!mysqli_stmt_execute($check)) {
                $errors['general'] = 'Unable to validate your registration. Please try again.';
            } else {
                $chk_res = mysqli_stmt_get_result($check);
                while ($existing = mysqli_fetch_assoc($chk_res)) {
                    if (strcasecmp($existing['email'], $email) === 0) $errors['email'] = 'An account with this email address already exists.';
                    if (strcasecmp($existing['slug'], $slug) === 0) $errors['slug'] = 'This short name is already in use. Please choose another.';
                }
            }
            mysqli_stmt_close($check);
        }
    }

    if (empty($errors)) {
        $uploaded_path = null;
        try {
            if ($logo_extension !== null) {
                $upload_dir = __DIR__ . '/../uploads/colleges';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                    throw new RuntimeException('Unable to create the logo upload directory.');
                }
                $logo_filename = 'college_' . bin2hex(random_bytes(12)) . '.' . $logo_extension;
                $uploaded_path = $upload_dir . '/' . $logo_filename;
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploaded_path)) {
                    throw new RuntimeException('Unable to save the uploaded logo.');
                }
            }
            $uid      = (int)$university_id;
            $cname    = mysqli_real_escape_string($conn, $name);
            $cslug    = mysqli_real_escape_string($conn, $slug);
            $cemail   = mysqli_real_escape_string($conn, $email);
            $cphone   = mysqli_real_escape_string($conn, (string)normalize_phone_number($phone));
            $clogo    = mysqli_real_escape_string($conn, $logo_filename);
            $caddr    = mysqli_real_escape_string($conn, $address);
            $cpass    = mysqli_real_escape_string($conn, password_hash($password, PASSWORD_DEFAULT));

            $ins_sql = "
                INSERT INTO colleges (university_id, name, slug, email, phone, logo, address, status, password, created_at, updated_at)
                VALUES ($uid, '$cname', '$cslug', '$cemail', '$cphone', '$clogo', '$caddr', 'active', '$cpass', NOW(), NOW())
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
                'role'       => 'organizer',
                'name'       => $name,
                'email'      => $email,
                'logo'       => $logo_filename,
                'college_id' => $new_id
            ]);

            header('Location: ../Organizer/Dashboard.php');
            exit;
        } catch (Exception $e) {
            if ($uploaded_path && is_file($uploaded_path)) unlink($uploaded_path);
            error_log('College registration failed: ' . $e->getMessage());
            $errors['general'] = 'Registration could not be completed. Please try again.';
        }
    }
}

include __DIR__ . '/Header.php';
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
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
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

    .form-control-luxury,
    .form-select-luxury {
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
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
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
        .register-card-bespoke {
            padding: 24px;
            border-radius: 24px;
        }
    }
</style>

<link rel="stylesheet" href="../assets/css/registration.css">
<section class="register-gateway-section">
    <div class="container-xl">
        <div class="row g-5 align-items-center justify-content-center">

            <!-- LEFT COLUMN: COLLEGE ORGANIZER BRAND HIGHLIGHTS -->
            <div class="col-12 col-lg-5"><div class="registration-story">
<span class="registration-eyebrow"><i class="bi bi-building" aria-hidden="true"></i> College account</span>
<h1>Bring your campus<br><span>to life.</span></h1><p>Create a home for your college on Evenza. Organize events and manage your campus community from one workspace.</p>
<div class="registration-benefits"><div class="registration-benefit"><i class="bi bi-calendar-event" aria-hidden="true"></i><div><h2>Publish your events</h2><p>Share competitions and experiences with students.</p></div></div><div class="registration-benefit"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><div><h2>Manage registrations</h2><p>Keep participant lists and team rosters organized.</p></div></div><div class="registration-benefit"><i class="bi bi-bar-chart" aria-hidden="true"></i><div><h2>See your event activity</h2><p>Review sign-ups, registration status and paid fees.</p></div></div></div><div class="registration-note"><i class="bi bi-stars" aria-hidden="true"></i> Built for campus communities.</div></div></div>

            <div class="col-12 col-lg-7">
                <div class="register-card-bespoke">
                    <?php require __DIR__ . '/../includes/registration_roles.php'; ?>

                    <div class="registration-form-heading d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-building-add fs-3 text-dark"></i>
                            <div>
                                <h3 class="fw-black text-dark fs-4 mb-0" style="letter-spacing: -0.5px;">Register your college</h3>
                                <p class="text-muted text-xs mb-0">Add your institution details to create an organizer account.</p>
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
                                    <label class="form-label-clean">College Name *</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" id="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" placeholder="e.g. Stanford College of Engineering" oninput="generateSlug(); validateField('name');" required>
                                    <div class="invalid-feedback-bespoke" id="err_name" style="<?= isset($errors['name']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['name'] ?? 'College name is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">Slug / Short Name</label>
                                    <input type="text" class="form-control-luxury <?= isset($errors['slug']) ? 'is-invalid' : '' ?>" name="slug" id="slug" maxlength="150" value="<?= htmlspecialchars((string)($_POST['slug'] ?? '')) ?>" placeholder="e.g. stanford-engineering">
                                    <div class="invalid-feedback-bespoke" id="err_slug" style="<?= isset($errors['slug']) ? 'display:flex;' : 'display:none;' ?>"><?= htmlspecialchars($errors['slug'] ?? 'Use lowercase letters, numbers and single hyphens.') ?></div>
                                </div>
                            </div>

                            <!-- ROW 2: UNIVERSITY & EMAIL -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group">
                                    <label class="form-label-clean">University *</label>
                                    <div class="searchable-select-wrapper" id="universityWrapper">
                                        <input type="text"
                                            id="universityInput"
                                            class="searchable-select-input <?= isset($errors['university_id']) ? 'is-invalid' : '' ?>"
                                            placeholder="Search university..."
                                            autocomplete="off"
                                            onfocus="openDropdown('university')"
                                            onclick="openDropdown('university')"
                                            oninput="document.getElementById('universitySelect').value = ''; openDropdown('university'); filterDropdown('university');"
                                            onkeyup="openDropdown('university'); filterDropdown('university');">
                                        <i class="bi bi-caret-down-fill select-caret-icon"></i>
                                        <input type="hidden" name="university_id" id="universitySelect" value="<?= htmlspecialchars((string)($_POST['university_id'] ?? '')) ?>" required>

                                        <div class="searchable-select-dropdown" id="universityDropdown">
                                            <?php foreach ($universities as $u): ?>
                                                <div class="searchable-select-item" data-value="<?= (int)$u['university_id'] ?>" onclick="selectUniversity(this.dataset.value, this.textContent.trim())">
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
                                    <label class="form-label-clean">Email *</label>
                                    <input type="email" class="form-control-luxury <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="engineering@stanford.edu" oninput="validateField('email')" required>
                                    <div class="invalid-feedback-bespoke" id="err_email" style="<?= isset($errors['email']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['email'] ?? 'Valid email address is required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 3: LOGIN PASSWORD & PHONE -->
                            <div class="col-12 col-md-6">
                                <div class="register-input-group position-relative">
                                    <label class="form-label-clean">Login Password *</label>
                                    <input type="password" class="form-control-luxury <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password" id="registerPassword" placeholder="Minimum 6 characters" minlength="6" autocomplete="new-password" oninput="checkPasswordStrength()" required>
                                    <button type="button" class="btn-eye-toggle" aria-label="Show or hide password" onclick="toggleRegisterPassword()">
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
                                    <label class="form-label-clean">Phone *</label>
                                    <input type="tel" class="form-control-luxury <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" id="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="+91 9876543210" oninput="validateField('phone')" required>
                                    <div class="invalid-feedback-bespoke" id="err_phone" style="<?= isset($errors['phone']) ? 'display:flex;' : 'display:none;' ?>">
                                        <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errors['phone'] ?? 'Valid phone number required.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 4: LOGO (jpg/png, max 2mb) -->
                            <div class="col-12">
                                <div class="register-input-group">
                                    <label class="form-label-clean" for="logoInput">LOGO (JPG/PNG/WEBP, max 2 MB)</label>
                                    <div class="custom-file-upload-box">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-xs">Choose File</span>
                                            <span class="text-xs text-muted" id="fileNameDisplay">No file chosen</span>
                                        </div>
                                        <input type="file" name="logo" id="logoInput" accept="image/jpeg,image/png,image/webp" onchange="displaySelectedFileName(this)">
                                    </div>
                                    <div class="invalid-feedback-bespoke" id="err_logo" style="<?= isset($errors['logo']) ? 'display:flex;' : 'display:none;' ?>"><?= htmlspecialchars($errors['logo'] ?? 'Choose a JPG, PNG or WEBP image up to 2 MB.') ?></div>
                                </div>
                            </div>

                            <!-- ROW 5: ADDRESS -->
                            <div class="col-12">
                                <div class="register-input-group">
                                    <label class="form-label-clean">Address</label>
                                    <textarea class="form-control-luxury" name="address" id="address" rows="2" style="border-radius: 18px;" placeholder="College full address..."><?= htmlspecialchars((string)($_POST['address'] ?? '')) ?></textarea>
                                </div>
                            </div>

                        </div>

                        <!-- SUBMIT BUTTON matching Save College styling -->
                        <div class="pt-4 d-flex align-items-center justify-content-end gap-3">
                            <button type="submit" class="btn-capsule-dark px-5 py-2.5 fs-6" style="width: auto;">
                                Create college account &rarr;
                            </button>
                        </div>
                    </form>

                    <!-- CLEAN FOOTER LINKS -->
                    <div class="registration-form-footer">Already have an account? <a href="login.php">Sign in</a></div>

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
        slugInput.value = nameVal.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
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
        const err = document.getElementById('err_' + fieldName);
        if (!field || !err) return true;

        let isValid = true;
        let msg = '';

        if (fieldName === 'name') {
            if (field.value.trim() === '') {
                isValid = false;
                msg = 'College name is required.';
            } else if (field.value.trim().length < 3) {
                isValid = false;
                msg = 'College name must be at least 3 characters.';
            }
        } else if (fieldName === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (field.value.trim() === '') {
                isValid = false;
                msg = 'Email address is required.';
            } else if (!emailRegex.test(field.value.trim())) {
                isValid = false;
                msg = 'Please enter a valid email address.';
            }
        } else if (fieldName === 'phone') {
            let digits = field.value.replace(/\D/g, '');
            if (digits.length === 12 && digits.startsWith('91')) digits = digits.slice(2);
            else if (digits.length === 11 && digits.startsWith('0')) digits = digits.slice(1);
            if (field.value.trim() === '') {
                isValid = false;
                msg = 'Phone number is required.';
            } else if (/[a-z]/i.test(field.value) || !/^[2-9]\d{9}$/.test(digits)) {
                isValid = false;
                msg = 'Please enter a valid 10-digit Indian phone number.';
            }
        } else if (fieldName === 'slug' && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(field.value)) {
            isValid = false;
            msg = 'Use lowercase letters, numbers and single hyphens.';
        }

        if (isValid) {
            field.classList.remove('is-invalid');
            err.style.display = 'none';
        } else {
            field.classList.add('is-invalid');
            err.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + msg;
            err.style.display = 'flex';
        }
        return isValid;
    }

    function toggleRegisterPassword() {
        const input = document.getElementById('registerPassword');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }

    function checkPasswordStrength() {
        const val = document.getElementById('registerPassword').value;
        const fill = document.getElementById('strengthFill');
        const err = document.getElementById('err_password');

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
        document.getElementById('registerPassword').classList.toggle('is-invalid', val.length < 6);
    }

    window.addEventListener('DOMContentLoaded', function() {
        const selectedUnivId = document.getElementById('universitySelect').value;
        if (selectedUnivId) {
            const item = Array.from(document.querySelectorAll('#universityDropdown .searchable-select-item')).find(item => item.dataset.value === selectedUnivId);
            if (item) {
                document.getElementById('universityInput').value = item.innerText.trim();
            }
        }
        const form = document.getElementById('collegeRegisterForm');
        form.querySelectorAll('label.form-label-clean').forEach(label => {
            const field = label.parentElement.querySelector('input:not([type="hidden"]), textarea');
            if (field) label.htmlFor = field.id;
        });
        form.addEventListener('submit', function(event) {
            let valid = ['name', 'email', 'phone', 'slug'].map(validateField).every(Boolean);
            const university = document.getElementById('universitySelect');
            if (!university.value) {
                document.getElementById('err_university_id').style.display = 'flex';
                document.getElementById('universityInput').classList.add('is-invalid');
                valid = false;
            }
            checkPasswordStrength();
            if (document.getElementById('registerPassword').value.length < 6) {
                document.getElementById('registerPassword').classList.add('is-invalid');
                valid = false;
            }
            const logo = document.getElementById('logoInput').files[0];
            const invalidLogo = logo && (logo.size > 2 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(logo.type));
            document.getElementById('err_logo').style.display = invalidLogo ? 'flex' : 'none';
            if (invalidLogo) valid = false;
            if (!valid) {
                event.preventDefault();
                form.querySelector('.is-invalid')?.focus();
            }
        });
    });
</script>

<script src="../assets/js/registration-ui.js"></script>
<?php include __DIR__ . '/Footer.php'; ?>
