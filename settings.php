<?php
include('header.php');
include('sidenav.php');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: ./");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$profilePic = !empty($user['picture']) ? 'dist/img/' . htmlspecialchars($user['picture'], ENT_QUOTES, 'UTF-8') : 'dist/img/default.webp';
$displayName = trim(implode(' ', array_filter([
    $user['first_name'] ?? '',
    $user['middle_name'] ?? '',
    $user['last_name'] ?? '',
    $user['ext'] ?? ''
])));
$displayName = $displayName !== '' ? $displayName : ($user['username'] ?? 'User');
$roleLabel = strtoupper((string) ($user['userType'] ?? 'user'));
$themePreference = theme_normalize_preference($user['theme_preference'] ?? 'light');
$twoFaEnabled = !empty($user['two_fa_enabled']);
$flash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings</title>
    <link rel="stylesheet" href="plugins/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="plugins/bootstrap/css/bootstrap.min.css">
    <style>
        .settings-shell { padding-bottom: 1rem; }
        .settings-hero { position: relative; overflow: hidden; border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(13, 110, 253, 0.18), rgba(32, 201, 151, 0.15)); border: 1px solid rgba(13, 110, 253, 0.18); }
        .settings-hero::after { content: ""; position: absolute; inset: auto -60px -60px auto; width: 180px; height: 180px; border-radius: 50%; background: rgba(255, 255, 255, 0.08); }
        .settings-avatar { width: 112px; height: 112px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255, 255, 255, 0.25); box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, 0.16); }
        .settings-role-badge, .settings-theme-badge, .settings-status-badge { display: inline-flex; align-items: center; gap: 0.35rem; border-radius: 999px; padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.02em; }
        .settings-role-badge { background: rgba(13, 110, 253, 0.18); color: #8bc2ff; }
        .settings-theme-badge { background: rgba(108, 117, 125, 0.22); color: #dee2e6; }
        .settings-status-badge.enabled { background: rgba(40, 167, 69, 0.2); color: #8ff0b0; }
        .settings-status-badge.disabled { background: rgba(255, 193, 7, 0.18); color: #ffe08a; }
        .settings-card { border-radius: 1rem; overflow: hidden; box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.08); }
        .settings-card .card-header { border-bottom: 1px solid rgba(108, 117, 125, 0.18); }
        .settings-section-title { font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.75; margin-bottom: 1rem; }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .settings-stat { border: 1px solid rgba(108, 117, 125, 0.22); border-radius: 0.9rem; padding: 0.9rem 1rem; height: 100%; background: rgba(108, 117, 125, 0.06); }
        .settings-stat-label { display: block; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.75; margin-bottom: 0.35rem; }
        .settings-stat-value { font-size: 1rem; font-weight: 700; line-height: 1.4; word-break: break-word; }
        .avatar-upload-card { text-align: center; }
        .avatar-upload-card .btn { min-width: 140px; }
        .profile-hint { font-size: 0.85rem; opacity: 0.72; }
        .strength-meter { height: 8px; margin-top: 0.5rem; background: rgba(108, 117, 125, 0.22); border-radius: 999px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0%; transition: width 0.3s ease; }
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #fd7e14; }
        .strength-strong { background: #28a745; }
        .theme-choice-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.9rem; }
        .theme-choice { position: relative; border: 1px solid rgba(108, 117, 125, 0.22); border-radius: 0.9rem; padding: 1rem; cursor: pointer; transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease; background: rgba(108, 117, 125, 0.05); }
        .theme-choice:hover { transform: translateY(-1px); border-color: rgba(13, 110, 253, 0.4); }
        .theme-choice input { position: absolute; opacity: 0; pointer-events: none; }
        .theme-choice.active { border-color: rgba(13, 110, 253, 0.7); box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.12); }
        .theme-swatch { height: 82px; border-radius: 0.75rem; margin-bottom: 0.8rem; border: 1px solid rgba(108, 117, 125, 0.2); }
        .theme-swatch.light { background: linear-gradient(180deg, #ffffff 0%, #f4f6f9 100%); }
        .theme-swatch.dark { background: linear-gradient(180deg, #2f3542 0%, #1f2530 100%); }
        .theme-choice-title { display: block; font-weight: 700; margin-bottom: 0.25rem; }
        .danger-zone { border: 1px solid rgba(220, 53, 69, 0.28); }
        .danger-zone .card-header { background: rgba(220, 53, 69, 0.08); }
        body[data-theme="light"] .settings-hero { background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(32, 201, 151, 0.1)); }
        body[data-theme="light"] .settings-role-badge { color: #0d6efd; background: rgba(13, 110, 253, 0.12); }
        body[data-theme="light"] .settings-theme-badge { color: #495057; background: rgba(108, 117, 125, 0.12); }
        body[data-theme="light"] .settings-status-badge.enabled { color: #1e7e34; background: rgba(40, 167, 69, 0.14); }
        body[data-theme="light"] .settings-status-badge.disabled { color: #a16800; background: rgba(255, 193, 7, 0.18); }
        body[data-theme="light"] .settings-stat, body[data-theme="light"] .theme-choice { background: #ffffff; border-color: rgba(13, 110, 253, 0.12); }
        @media (max-width: 767.98px) { .settings-hero { text-align: center; } }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="content-wrapper">
        <br><br>
        <div class="content-header">
            <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="m-0">Account Settings</h1>
                    <p class="mb-0 text-muted">Manage your profile, preferences, and account security in one place.</p>
                </div>
                <ol class="breadcrumb float-sm-right mb-0 mt-2 mt-sm-0">
                    <li class="breadcrumb-item"><a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/home">Home</a></li>
                    <li class="breadcrumb-item active">Settings</li>
                </ol>
            </div>
        </div>

        <div class="content settings-shell">
            <div class="container-fluid">
                <div class="settings-hero">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 class="h4 mb-2"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="mb-3"><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?php if (!empty($user['position'])): ?> · <?= htmlspecialchars((string) $user['position'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                            <div class="d-flex flex-wrap" style="gap:0.5rem;">
                                <span class="settings-role-badge"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="settings-theme-badge"><?= ucfirst($themePreference) ?> Theme</span>
                                <span class="settings-status-badge <?= $twoFaEnabled ? 'enabled' : 'disabled' ?>"><?= $twoFaEnabled ? '2FA Enabled' : '2FA Disabled' ?></span>
                            </div>
                        </div>
                        <div class="col-lg-4 text-lg-right mt-4 mt-lg-0">
                            <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES, 'UTF-8') ?>" class="settings-avatar" id="profilePreview" alt="Profile photo">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4">
                        <div class="card settings-card mb-4">
                            <div class="card-header"><h3 class="card-title mb-0">Profile Snapshot</h3></div>
                            <div class="card-body">
                                <div class="avatar-upload-card mb-4">
                                    <input type="file" name="picture" id="picture" form="settingsForm" accept="image/png, image/jpeg" class="form-control mb-3">
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="removePhotoBtn">Remove Photo</button>
                                    <p class="profile-hint mt-3 mb-0">Use a JPG or PNG image up to 10MB for the clearest profile photo.</p>
                                </div>

                                <div class="settings-section-title">At A Glance</div>
                                <div class="settings-grid">
                                    <div class="settings-stat"><span class="settings-stat-label">Username</span><span class="settings-stat-value"><?= htmlspecialchars((string) ($user['username'] ?? 'Not set'), ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <div class="settings-stat"><span class="settings-stat-label">Area</span><span class="settings-stat-value"><?= htmlspecialchars((string) ($user['area'] ?? 'Not set'), ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <div class="settings-stat"><span class="settings-stat-label">Position Abbreviation</span><span class="settings-stat-value"><?= htmlspecialchars((string) ($user['positionAbr'] ?? 'Not set'), ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <div class="settings-stat"><span class="settings-stat-label">Authentication</span><span class="settings-stat-value"><?= $twoFaEnabled ? 'Protected with 2FA' : 'Password only' ?></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="card settings-card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">Two-Factor Authentication</h3>
                                <span class="settings-status-badge <?= $twoFaEnabled ? 'enabled' : 'disabled' ?>"><?= $twoFaEnabled ? 'Enabled' : 'Disabled' ?></span>
                            </div>
                            <div class="card-body">
                                <p class="mb-3"><?= $twoFaEnabled ? 'Your account currently requires a verification code during sensitive actions and sign-ins.' : 'Add another layer of protection by sending a verification code to your email when needed.' ?></p>
                                <button class="btn btn-info" id="toggle2FA"><?= $twoFaEnabled ? 'Disable 2FA' : 'Enable 2FA' ?></button>
                            </div>
                        </div>

                        <div class="card settings-card danger-zone mb-4">
                            <div class="card-header"><h3 class="card-title mb-0">Danger Zone</h3></div>
                            <div class="card-body">
                                <p class="mb-3">Deleting your account removes your access and may be irreversible. Continue only if you are sure.</p>
                                <button class="btn btn-danger" id="deleteAccountBtn">Delete My Account</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <form id="settingsForm" method="POST" action="save_profile_settings.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                            <div class="card settings-card mb-4">
                                <div class="card-header"><h3 class="card-title mb-0">Profile Information</h3></div>
                                <div class="card-body">
                                    <div class="settings-section-title">Identity</div>
                                    <div class="form-row">
                                        <div class="form-group col-md-3"><label for="first_name">First Name</label><input type="text" id="first_name" name="first_name" class="form-control" value="<?= htmlspecialchars((string) ($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                        <div class="form-group col-md-3"><label for="middle_name">Middle Name</label><input type="text" id="middle_name" name="middle_name" class="form-control" value="<?= htmlspecialchars((string) ($user['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                                        <div class="form-group col-md-4"><label for="last_name">Last Name</label><input type="text" id="last_name" name="last_name" class="form-control" value="<?= htmlspecialchars((string) ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                        <div class="form-group col-md-2"><label for="ext">Extension</label><input type="text" id="ext" name="ext" class="form-control" value="<?= htmlspecialchars((string) ($user['ext'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                                    </div>

                                    <div class="settings-section-title mt-3">Work Details</div>
                                    <div class="form-row">
                                        <div class="form-group col-md-5"><label for="position">Position</label><input type="text" id="position" name="position" class="form-control" value="<?= htmlspecialchars((string) ($user['position'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                        <div class="form-group col-md-3"><label for="positionAbr">Position Abbreviation</label><input type="text" id="positionAbr" name="positionAbr" class="form-control" value="<?= htmlspecialchars((string) ($user['positionAbr'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                        <div class="form-group col-md-4"><label for="area">Area of Assignment</label><input type="text" id="area" name="area" class="form-control" value="<?= htmlspecialchars((string) ($user['area'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                    </div>

                                    <div class="settings-section-title mt-3">Contact And Access</div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6"><label for="email">Email</label><input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                        <div class="form-group col-md-6"><label for="username">Username</label><input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                    </div>
                                </div>
                            </div>

                            <div class="card settings-card mb-4">
                                <div class="card-header"><h3 class="card-title mb-0">Preferences And Security</h3></div>
                                <div class="card-body">
                                    <div class="settings-section-title">Theme Preference</div>
                                    <div class="theme-choice-group mb-4">
                                        <label class="theme-choice <?= $themePreference === 'light' ? 'active' : '' ?>">
                                            <input type="radio" name="theme_preference" value="light" <?= $themePreference === 'light' ? 'checked' : '' ?>>
                                            <span class="theme-swatch light"></span>
                                            <span class="theme-choice-title">Light</span>
                                            <span class="profile-hint">Bright workspace for daytime use and shared-office screens.</span>
                                        </label>
                                        <label class="theme-choice <?= $themePreference === 'dark' ? 'active' : '' ?>">
                                            <input type="radio" name="theme_preference" value="dark" <?= $themePreference === 'dark' ? 'checked' : '' ?>>
                                            <span class="theme-swatch dark"></span>
                                            <span class="theme-choice-title">Dark</span>
                                            <span class="profile-hint">Lower-glare view for dim rooms and longer sessions.</span>
                                        </label>
                                    </div>

                                    <div class="settings-section-title">Password Update</div>
                                    <div class="form-group mb-2">
                                        <label for="password">New Password</label>
                                        <input type="password" name="password" id="password" class="form-control" autocomplete="new-password">
                                        <small class="form-text text-muted">Leave blank if you do not want to change your password.</small>
                                        <div class="strength-meter"><div id="strengthBar" class="strength-bar"></div></div>
                                        <small class="form-text text-muted" id="passwordStrengthText">Use at least 8 characters with uppercase, number, and special character.</small>
                                    </div>
                                </div>
                                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                                    <span class="text-muted small">Your saved theme preference also follows your account on other devices.</span>
                                    <button type="submit" class="btn btn-primary mt-2 mt-sm-0">Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $base_url;?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>
<script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function () {
    const passwordStrengthText = $('#passwordStrengthText');
    const csrfToken = window.KODUS_CSRF_TOKEN || $("input[name='csrf_token']").val() || "";

    function updateThemeChoiceState() {
        $('.theme-choice').removeClass('active');
        $('input[name="theme_preference"]:checked').closest('.theme-choice').addClass('active');
    }

    $('#picture').change(function () {
        const file = this.files[0];
        if (file && file.size <= 10 * 1024 * 1024 && ['image/jpeg', 'image/png'].includes(file.type)) {
            const reader = new FileReader();
            reader.onload = e => $('#profilePreview').attr('src', e.target.result);
            reader.readAsDataURL(file);
        } else {
            Swal.fire('Invalid file', 'Only JPG/PNG under 10MB allowed.', 'warning');
            this.value = '';
        }
    });

    $('input[name="theme_preference"]').on('change', updateThemeChoiceState);
    updateThemeChoiceState();

    $('#settingsForm').on('submit', function () {
        const form = this;

        if (!form.checkValidity()) {
            return;
        }

        Swal.fire({
            title: 'Saving Changes...',
            text: 'Please wait while we update your profile and preferences.',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
                form.submit();
            }
        });

        return false;
    });

    $('#password').on('input', function () {
        const val = $(this).val();
        const bar = $('#strengthBar');
        let strength = 0;
        if (val.length >= 8) strength++;
        if (/[A-Z]/.test(val)) strength++;
        if (/[0-9]/.test(val)) strength++;
        if (/[^a-zA-Z0-9]/.test(val)) strength++;

        const percents = [0, 25, 50, 75, 100];
        const classes = ['', 'strength-weak', 'strength-medium', 'strength-medium', 'strength-strong'];
        const labels = [
            'Use at least 8 characters with uppercase, number, and special character.',
            'Weak password. Add more complexity before saving.',
            'Fair password. Consider adding another unique character.',
            'Good password. One more improvement will make it stronger.',
            'Strong password. This is ready to use.'
        ];

        bar.css('width', percents[strength] + '%').attr('class', 'strength-bar ' + classes[strength]);
        passwordStrengthText.text(labels[strength]);
    });

    $('#removePhotoBtn').click(function () {
        $.post('remove_photo.php', { csrf_token: csrfToken }, function (data) {
            if (data.success) {
                $('#profilePreview').attr('src', 'dist/img/default.webp');
                Swal.fire('Photo removed', 'Your profile photo has been reset to the default image.', 'success');
            } else {
                Swal.fire('Error', data.message || 'Unable to remove the photo right now.', 'error');
            }
        }, 'json').fail(() => {
            Swal.fire('Error', 'Unable to remove the photo right now.', 'error');
        });
    });

    $('#deleteAccountBtn').click(async function () {
        const twoFAEnabled = <?= json_encode((bool) $twoFaEnabled) ?>;

        const { value: password } = await Swal.fire({
            title: 'Delete your account?',
            input: 'password',
            inputLabel: 'Enter your password to continue',
            inputAttributes: { required: true },
            inputPlaceholder: 'Your password',
            showCancelButton: true,
            confirmButtonText: twoFAEnabled ? 'Next' : 'Submit'
        });

        if (!password) return;

        let code = null;

        if (twoFAEnabled) {
            Swal.fire({
                title: 'Sending 2FA Code...',
                text: 'Please wait while we send a code to your email.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                await $.post('send_2fa_code.php', { csrf_token: csrfToken }, null, 'json');
                Swal.close();
            } catch {
                Swal.fire('Error', 'Failed to send 2FA code. Please try again.', 'error');
                return;
            }

            const result = await Swal.fire({
                title: 'Enter 2FA Code',
                input: 'text',
                inputLabel: 'Check your email for the code',
                inputPlaceholder: '6-digit code',
                inputAttributes: { maxlength: 6, autocapitalize: 'off' },
                showCancelButton: true,
                confirmButtonText: 'Verify'
            });

            if (!result.value) return;
            code = result.value;
        }

        Swal.fire({
            title: 'Deleting your Account...',
            text: 'Please wait while we finalize the process.',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const postData = { password, csrf_token: csrfToken };
            if (code !== null) postData.code = code;

            const data = await $.post('delete_account.php', postData, null, 'json').catch(err => {
                Swal.fire('Error', 'Invalid response received. Check console for details.', 'error');
                console.error(err.responseText);
            });
            Swal.close();

            if (data && data.success) {
                await Swal.fire({
                    title: 'Account Deleted',
                    text: 'Your account has been successfully removed.',
                    icon: 'success'
                });
                location.href = './';
            } else if (data) {
                Swal.fire('Error', data.message, 'error');
            }
        } catch {
            Swal.close();
            Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
        }
    });

    $('#toggle2FA').click(function () {
        const isEnabled = $(this).text().toLowerCase().includes('disable');

        if (isEnabled) {
            Swal.fire({
                title: 'Disable 2FA?',
                text: 'This will remove 2FA protection from your account.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, disable it'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Disabling Two-Factor Authentication...',
                        text: 'Please wait...',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => Swal.showLoading()
                    });

                    $.post('disable_2fa.php', { csrf_token: csrfToken }, function (res) {
                        Swal.close();
                        if (res.success) {
                            Swal.fire('2FA Disabled', 'Your account will now use password-only protection.', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message || 'Failed to disable 2FA.', 'error');
                        }
                    }, 'json').fail(() => {
                        Swal.close();
                        Swal.fire('Error', 'Something went wrong while disabling 2FA.', 'error');
                    });
                }
            });
        } else {
            Swal.fire({
                title: 'Sending verification code...',
                text: 'Please wait while we send a code to your email.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.post('send_2fa_code.php', { csrf_token: csrfToken }, function () {
                Swal.close();
                Swal.fire({
                    title: 'Enter the 6-digit code sent to your email',
                    input: 'text',
                    inputAttributes: {
                        maxlength: 6,
                        required: true,
                        autocapitalize: 'off',
                        autocorrect: 'off',
                        inputmode: 'numeric',
                        pattern: '[0-9]*'
                    },
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Verify',
                    preConfirm: code => {
                        Swal.fire({
                            title: 'Enabling Two-Factor Authentication...',
                            text: 'Please wait...',
                            icon: 'info',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => Swal.showLoading()
                        });

                        return $.post('verify_2fa_code.php', { code: code, csrf_token: csrfToken }, null, 'json')
                            .then(data => {
                                Swal.close();
                                if (data.success) {
                                    return Swal.fire('2FA Enabled!', 'Your account now has an extra layer of protection.', 'success').then(() => location.reload());
                                }
                                Swal.showValidationMessage(data.message || 'Invalid code.');
                            })
                            .catch(() => {
                                Swal.close();
                                Swal.showValidationMessage('Verification failed. Please try again.');
                            });
                    }
                });
            }).fail(() => {
                Swal.close();
                Swal.fire('Error', 'Failed to send 2FA code. Please try again.', 'error');
            });
        }
    });

    <?php if (is_array($flash) && !empty($flash['title'])): ?>
    Swal.fire({
        icon: <?= json_encode((string) ($flash['type'] ?? 'info')) ?>,
        title: <?= json_encode((string) ($flash['title'] ?? 'Settings')) ?>,
        text: <?= json_encode((string) ($flash['message'] ?? '')) ?>
    });
    <?php endif; ?>
});
</script>
</body>
</html>


