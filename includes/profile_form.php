<?php
// Called by authenticated Student and Organizer profile pages.
$isStudentProfile = $profile_role === 'student';
$escapeProfile = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$institutionKey = $isStudentProfile ? 'college_id' : 'university_id';
$institutionTable = $isStudentProfile ? 'colleges' : 'universities';
$institutionOptions = mysqli_query($conn, "SELECT $institutionKey, name FROM $institutionTable ORDER BY name");
$profileInput = function ($name, $label, $type = 'text', $max = null) use ($profile_record, $profile_errors, $escapeProfile) {
    $invalid = isset($profile_errors[$name]);
    echo '<div class="profile-field"><label for="' . $name . '">' . $escapeProfile($label) . ' <span aria-hidden="true">*</span></label><input class="form-control' . ($invalid ? ' is-invalid' : '') . '" id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . $escapeProfile($profile_record[$name] ?? '') . '" required' . ($max ? ' maxlength="' . $max . '"' : '') . '>';
    if ($invalid) echo '<div class="invalid-feedback">' . $escapeProfile($profile_errors[$name]) . '</div>';
    echo '</div>';
};
?>
<form method="POST" enctype="multipart/form-data" id="profileForm" class="profile-editor" data-profile-role="<?= $profile_role ?>">
    <input type="hidden" name="form_type" value="update_profile">
    <input type="hidden" name="profile_csrf" value="<?= $escapeProfile(profile_csrf_token()) ?>">
    <div class="profile-upload-header">
        <img id="profileImagePreview" src="<?= $escapeProfile(profile_image_url($profile_role, $profile_record[$isStudentProfile ? 'profile_photo' : 'logo'] ?? null)) ?>" alt="<?= $isStudentProfile ? 'Profile photo' : 'College logo' ?>" width="88" height="88" onerror="this.onerror=null;this.src='../assets/images/brand/<?= $isStudentProfile ? 'avatar-placeholder.svg' : 'college-placeholder.svg' ?>'">
        <div class="profile-upload-copy"><span class="profile-editor-eyebrow"><?= $isStudentProfile ? 'YOUR STUDENT PROFILE' : 'YOUR COLLEGE PROFILE' ?></span><h3><?= $isStudentProfile ? 'Make it yours' : 'Your campus identity' ?></h3><p>Keep your details and <?= $isStudentProfile ? 'photo' : 'logo' ?> up to date.</p></div>
        <div class="profile-upload-control"><label for="profile_image"><?= $isStudentProfile ? 'Change photo' : 'Change logo' ?></label><input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="profileImageHelp profileImageError"><small id="profileImageHelp">JPG, PNG or WEBP · Up to 2 MB</small></div>
    </div>
    <p id="profileImageError" class="text-danger small" role="alert"><?= $escapeProfile($profile_errors['profile_image'] ?? '') ?></p>
    <div class="profile-fields-grid">
        <?php $profileInput('name', $isStudentProfile ? 'Full name' : 'College name', 'text', $isStudentProfile ? 100 : 150); ?>
        <?php $profileInput('email', 'Email address', 'email', 100); ?>
        <?php $profileInput('phone', 'Phone number', 'tel', 25); ?>
        <?php if ($isStudentProfile): $profileInput('enrollment_no', 'Enrollment number', 'text', 50); else: $profileInput('slug', 'College URL name', 'text', 150); endif; ?>
        <div class="profile-field"><label for="<?= $institutionKey ?>"><?= $isStudentProfile ? 'Enrolled college' : 'Affiliated university' ?> *</label><select name="<?= $institutionKey ?>" id="<?= $institutionKey ?>" class="form-select" required><option value="">Select institution</option><?php while ($institution = mysqli_fetch_assoc($institutionOptions)): ?><option value="<?= (int)$institution[$institutionKey] ?>" <?= (int)($profile_record[$institutionKey] ?? 0) === (int)$institution[$institutionKey] ? 'selected' : '' ?>><?= $escapeProfile($institution['name']) ?></option><?php endwhile; ?></select><?php if (isset($profile_errors[$institutionKey])): ?><small class="text-danger"><?= $escapeProfile($profile_errors[$institutionKey]) ?></small><?php endif; ?></div>
        <?php if ($isStudentProfile): ?>
            <div class="profile-field"><label for="gender">Gender *</label><select name="gender" id="gender" class="form-select" required><?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($profile_record['gender'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div class="profile-field"><label for="semester">Current semester *</label><select name="semester" id="semester" class="form-select" required><?php for ($semester=1; $semester<=8; $semester++): ?><option value="<?= $semester ?>" <?= (int)($profile_record['semester'] ?? 1) === $semester ? 'selected' : '' ?>>Semester <?= $semester ?></option><?php endfor; ?></select></div>
        <?php else: ?>
            <div class="profile-field profile-field-wide"><label for="address">College address *</label><textarea name="address" id="address" class="form-control" rows="3" maxlength="2000" required><?= $escapeProfile($profile_record['address'] ?? '') ?></textarea></div>
        <?php endif; ?>
    </div>
    <div class="profile-editor-footer"><span><i class="bi bi-shield-check" aria-hidden="true"></i> Status and verification are managed by the administrator.</span><div><button type="reset" class="profile-reset">Reset changes</button><button type="submit" class="btn btn-dark px-4">Save changes <i class="bi bi-check2 ms-1" aria-hidden="true"></i></button></div></div>
</form>
