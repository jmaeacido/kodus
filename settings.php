<?php
include('header.php');
include('sidenav.php');
require_once __DIR__ . '/position_helpers.php';
require_once __DIR__ . '/profile_completion_helpers.php';
require_once __DIR__ . '/sso_helpers.php';

function settings_value_or_fallback(array $primary, array $fallback, string $key): string
{
    $primaryValue = trim((string) ($primary[$key] ?? ''));
    if ($primaryValue !== '') {
        return $primaryValue;
    }

    return trim((string) ($fallback[$key] ?? ''));
}

function settings_build_sso_profile_defaults(array $profile, mysqli $conn): array
{
    [$firstName, $middleName, $lastName, $suffix] = sso_parse_name_parts($profile);
    [$position, $positionAbr, $area] = sso_extract_position_parts($profile);
    $email = trim((string) ($profile['email'] ?? ''));
    $preferredUsername = trim((string) ($profile['preferred_username'] ?? ''));
    $usernameSeed = $preferredUsername !== '' ? $preferredUsername : ((strstr($email, '@', true) ?: $email) ?: ((string) ($profile['sub'] ?? '')));
    $username = $usernameSeed !== '' ? sso_normalize_username($usernameSeed) : '';

    if ($positionAbr === '') {
        $positionAbr = kodus_position_abbreviation($position);
    }

    return [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'ext' => $suffix,
        'position' => $position,
        'positionAbr' => $positionAbr,
        'area' => $area,
        'email' => $email,
        'username' => $username,
        'sso_avatar_url' => sso_extract_avatar_url($profile),
    ];
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: ./");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

$user = is_array($user) ? $user : [];
$localProfileStatus = kodus_profile_completion_status($user);
$profileReviewRequired = profile_review_is_required($user);
$profileNotice = null;
$ssoProfileDefaults = [];
$profileFields = ['first_name', 'middle_name', 'last_name', 'ext', 'position', 'positionAbr', 'area', 'email', 'username'];
$needsSsoFallback = $user === [];

if (!$needsSsoFallback) {
    foreach ($profileFields as $field) {
        if (trim((string) ($user[$field] ?? '')) === '') {
            $needsSsoFallback = true;
            break;
        }
    }
}

if ($needsSsoFallback && sso_is_configured()) {
    $accessToken = trim((string) ($_SESSION['idp_access_token'] ?? ''));
    if ($accessToken !== '') {
        try {
            $ssoProfileDefaults = settings_build_sso_profile_defaults(sso_fetch_userinfo($accessToken), $conn);

            if ($user === []) {
                $profileNotice = [
                    'type' => 'warning',
                    'title' => 'Profile Loaded From SSO',
                    'message' => 'Your local profile record was not available, so KODUS prefilled Profile Information from Caraga Connect. Please review and update the Profile Information section before saving.',
                ];
            } else {
                $filledFields = [];
                foreach ($profileFields as $field) {
                    if (trim((string) ($user[$field] ?? '')) === '' && trim((string) ($ssoProfileDefaults[$field] ?? '')) !== '') {
                        $filledFields[] = $field;
                    }
                }

                if ($filledFields !== []) {
                    $profileNotice = [
                        'type' => 'info',
                        'title' => 'Profile Information Needs Review',
                        'message' => 'Some Profile Information fields were prefilled from Caraga Connect because the local record was incomplete. Please review and update the Profile Information section before saving.',
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('Settings SSO profile fallback failed: ' . $e->getMessage());
        }
    }
}

$user = array_merge($ssoProfileDefaults, $user);
$showFirstTimeSsoProfilePrompt = $localProfileStatus['needs_attention']
    && !$profileReviewRequired
    && !empty($_SESSION['is_first_login'])
    && !empty($_SESSION['is_sso_authenticated'])
    && empty($_SESSION['settings_sso_profile_prompt_shown']);

if ($showFirstTimeSsoProfilePrompt) {
    $_SESSION['settings_sso_profile_prompt_shown'] = true;
}

$profilePic = avatar_resolve_url($user['picture'] ?? '', $user['sso_avatar_url'] ?? '', $base_url, __DIR__);
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
$twoFaHasSecret = two_factor_has_totp_secret($user);
$twoFaStatusLabel = $twoFaEnabled
    ? ($twoFaHasSecret ? '2FA Enabled' : '2FA Setup Required')
    : '2FA Disabled';
$twoFaSummary = $twoFaEnabled
    ? ($twoFaHasSecret ? 'Protected with authenticator 2FA' : 'Authenticator setup required')
    : 'Password only';
$twoFaRecoveryCount = two_factor_recovery_code_count($user);
$flash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);
$positionMap = kodus_position_map_with_custom($conn);
$currentPosition = trim((string) ($user['position'] ?? ''));
$currentPositionAbr = trim((string) ($user['positionAbr'] ?? ''));
$currentPositionAbr = $currentPositionAbr !== '' ? $currentPositionAbr : kodus_position_abbreviation($currentPosition);
$hasCustomPosition = $currentPosition !== '' && !array_key_exists($currentPosition, $positionMap);
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
        .settings-count-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.02em; background: rgba(255, 255, 255, 0.12); color: #e2e8f0; border: 1px solid rgba(148, 163, 184, 0.28); }
        .settings-card { border-radius: 1rem; overflow: hidden; box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.08); }
        .settings-card .card-header { border-bottom: 1px solid rgba(108, 117, 125, 0.18); }
        .settings-subsection { border: 1px solid rgba(13, 110, 253, 0.12); border-radius: 0.95rem; padding: 1rem; background: rgba(13, 110, 253, 0.04); }
        .settings-subsection + .settings-subsection { margin-top: 1rem; }
        .settings-subsection-title { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
        .settings-subsection-title h4 { margin: 0; font-size: 1rem; font-weight: 700; }
        .settings-subsection-title p { margin: 0; color: #6c757d; font-size: 0.9rem; }
        .settings-button-group { display: flex; flex-wrap: wrap; gap: 0.75rem; }
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
        body[data-theme="light"] .settings-count-badge { color: #0f172a; background: rgba(255, 255, 255, 0.92); border-color: rgba(13, 110, 253, 0.12); }
        body[data-theme="light"] .settings-stat, body[data-theme="light"] .theme-choice { background: #ffffff; border-color: rgba(13, 110, 253, 0.12); }
        body[data-theme="light"] .settings-subsection { background: #ffffff; border-color: rgba(13, 110, 253, 0.12); }
        @media (max-width: 767.98px) { .settings-hero { text-align: center; } }
    </style>
</head>
<body data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
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
                    <li class="breadcrumb-item"><a href="<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>home">Home</a></li>
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
                                <span class="settings-status-badge <?= $twoFaEnabled ? 'enabled' : 'disabled' ?>"><?= htmlspecialchars($twoFaStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <div class="col-lg-4 text-lg-right mt-4 mt-lg-0">
                            <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES, 'UTF-8') ?>" class="settings-avatar" id="profilePreview" alt="Profile photo">
                        </div>
                    </div>
                </div>

                <?php if (is_array($profileNotice)): ?>
                <div class="alert alert-<?= htmlspecialchars((string) ($profileNotice['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
                    <strong><?= htmlspecialchars((string) ($profileNotice['title'] ?? 'Profile Notice'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?= htmlspecialchars((string) ($profileNotice['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($profileReviewRequired): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong>Profile Review Reminder:</strong>
                    Please review and update your Profile Information in Settings. Some information from SSO may not match the required KODUS profile fields.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>

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
                                    <div class="settings-stat"><span class="settings-stat-label">Authentication</span><span class="settings-stat-value"><?= htmlspecialchars($twoFaSummary, ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <div class="settings-stat"><span class="settings-stat-label">Recovery Codes</span><span class="settings-stat-value"><?= $twoFaEnabled && $twoFaHasSecret ? number_format($twoFaRecoveryCount) . ' available' : 'Unavailable' ?></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="card settings-card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">Two-Factor Authentication</h3>
                                <span class="settings-status-badge <?= $twoFaEnabled ? 'enabled' : 'disabled' ?>"><?= $twoFaEnabled ? ($twoFaHasSecret ? 'Enabled' : 'Setup Required') : 'Disabled' ?></span>
                            </div>
                            <div class="card-body">
                                <div class="settings-subsection">
                                    <div class="settings-subsection-title">
                                        <div>
                                            <h4>Authenticator App</h4>
                                            <p>
                                                <?= $twoFaEnabled
                                                    ? ($twoFaHasSecret
                                                        ? 'Your account uses authenticator-based 2FA during sign-ins and protected actions.'
                                                        : 'Authenticator-based 2FA is enabled by default, but your app setup is still incomplete. Finish setup to keep your account protected.')
                                                    : 'Authenticator-based 2FA is currently off. You can enable it again anytime from here.' ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="settings-button-group">
                                        <button class="btn btn-info" id="toggle2FA"><?= $twoFaEnabled ? ($twoFaHasSecret ? 'Disable 2FA' : 'Finish 2FA Setup') : 'Enable 2FA' ?></button>
                                    </div>
                                </div>

                                <div class="settings-subsection">
                                    <div class="settings-subsection-title">
                                        <div>
                                            <h4>Recovery Codes</h4>
                                            <p><?= $twoFaEnabled && $twoFaHasSecret ? 'Keep a printable backup set in case your authenticator app is unavailable.' : 'Recovery codes become available after you finish authenticator setup.' ?></p>
                                        </div>
                                        <span class="settings-count-badge"><?= $twoFaEnabled && $twoFaHasSecret ? number_format($twoFaRecoveryCount) . ' available' : 'Unavailable' ?></span>
                                    </div>
                                    <?php if ($twoFaEnabled && $twoFaHasSecret): ?>
                                        <div class="settings-button-group">
                                            <button class="btn btn-outline-secondary" id="regenerateRecoveryCodesBtn" type="button">Regenerate Recovery Codes</button>
                                            <button class="btn btn-outline-primary" id="printRecoveryCodesBtn" type="button">Print / Download Codes</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
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
                                        <div class="form-group col-md-5">
                                            <label for="position">Position</label>
                                            <select id="position" name="position_option" class="form-control" required>
                                                <option value="" disabled <?= $currentPosition === '' ? 'selected' : '' ?>>Select position</option>
                                                <?php foreach ($positionMap as $positionLabel => $positionAbbreviation): ?>
                                                    <option value="<?= htmlspecialchars($positionLabel, ENT_QUOTES, 'UTF-8') ?>" <?= $currentPosition === $positionLabel ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($positionLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="__custom__" <?= $hasCustomPosition ? 'selected' : '' ?>>Manual input</option>
                                            </select>
                                            <input
                                                type="text"
                                                id="customPosition"
                                                name="custom_position"
                                                class="form-control mt-2 <?= $hasCustomPosition ? '' : 'd-none' ?>"
                                                value="<?= htmlspecialchars($hasCustomPosition ? $currentPosition : '', ENT_QUOTES, 'UTF-8') ?>"
                                                placeholder="Enter custom position"
                                            >
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="positionAbr">Position Abbreviation</label>
                                            <input type="text" id="positionAbr" name="positionAbr" class="form-control" value="<?= htmlspecialchars($currentPositionAbr, ENT_QUOTES, 'UTF-8') ?>" readonly required>
                                            <small class="form-text text-muted" id="positionAbrHelp"><?= $hasCustomPosition ? 'Generated automatically from your custom position using the same acronym-style format.' : 'Filled automatically from the selected position.' ?></small>
                                        </div>
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

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function () {
    const passwordStrengthText = $('#passwordStrengthText');
    const csrfToken = window.KODUS_CSRF_TOKEN || $("input[name='csrf_token']").val() || "";
    const twoFAEnabled = <?= json_encode((bool) $twoFaEnabled) ?>;
    const twoFAHasSecret = <?= json_encode((bool) $twoFaHasSecret) ?>;
    const twoFASetupIncomplete = twoFAEnabled && !twoFAHasSecret;
    const positionMap = <?= json_encode($positionMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const $position = $('#position');
    const $customPosition = $('#customPosition');
    const $positionAbr = $('#positionAbr');
    const $positionAbrHelp = $('#positionAbrHelp');

    function generateCustomPositionAbbreviation(value) {
        const trimmedValue = String(value || '').trim().replace(/\s+/g, ' ');
        if (!trimmedValue) {
            return '';
        }

        let suffix = '';
        let baseValue = trimmedValue;
        const suffixMatch = baseValue.match(/(?:^|\s|-)((?:X|IX|IV|V?I{1,3}))$/i);

        if (suffixMatch) {
            suffix = String(suffixMatch[1] || '').toUpperCase();
            baseValue = baseValue.slice(0, suffixMatch.index).trim();
        }

        const stopWords = new Set(['and', 'of', 'the', 'for', 'in', 'on', 'to', 'with', 'at', 'by']);
        const letters = baseValue
            .split(/[\s\-\/&,()]+/)
            .map(token => token.replace(/[^A-Za-z]/g, ''))
            .filter(token => token && !stopWords.has(token.toLowerCase()))
            .map(token => token.charAt(0).toUpperCase());

        const base = letters.join('');
        if (!base) {
            return suffix;
        }

        return suffix ? `${base}-${suffix}` : base;
    }

    function syncPositionAbbreviation() {
        const selectedPosition = $position.val();
        const isCustom = selectedPosition === '__custom__';

        $customPosition.toggleClass('d-none', !isCustom);
        $customPosition.prop('required', isCustom);

        if (isCustom) {
            $positionAbr.val(generateCustomPositionAbbreviation($customPosition.val()));
            $positionAbrHelp.text('Generated automatically from your custom position using the same acronym-style format.');
            return;
        }

        const abbreviation = positionMap[selectedPosition] || '';
        $positionAbr.val(abbreviation);
        $positionAbrHelp.text('Filled automatically from the selected position.');
    }

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
    $position.on('change', syncPositionAbbreviation);
    $customPosition.on('input', syncPositionAbbreviation);
    $customPosition.on('input', function () {
        this.setCustomValidity('');
    });
    syncPositionAbbreviation();

    $('#settingsForm').on('submit', function () {
        const form = this;

        if ($position.val() === '__custom__') {
            const hasCustomPosition = $.trim($customPosition.val()) !== '';
            $customPosition[0].setCustomValidity(hasCustomPosition ? '' : 'Please enter a custom position.');
        } else {
            $customPosition[0].setCustomValidity('');
        }

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

    async function requestTwoFactorSetup() {
        const response = await $.post('begin_2fa_setup.php', { csrf_token: csrfToken }, null, 'json');
        if (!response || !response.success) {
            throw new Error(response?.message || 'Unable to prepare authenticator setup.');
        }
        return response;
    }

    async function regenerateRecoveryCodes(options = {}) {
        const response = await $.post('regenerate_recovery_codes.php', { csrf_token: csrfToken }, null, 'json');
        if (!response || !response.success) {
            throw new Error(response?.message || 'Unable to regenerate recovery codes.');
        }

        if (options.showCodes) {
            await showRecoveryCodes(
                response.codes,
                options.title || 'New Recovery Codes',
                options.text || 'Your previous recovery codes have been replaced. Save the new set now.'
            );
        }

        return response;
    }

    async function showRecoveryCodes(codes, title, text) {
        const list = Array.isArray(codes) ? codes : [];
        const printUrl = 'recovery_codes_print.php';
        const html = `
            <p style="margin:0 0 14px;">${text}</p>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;text-align:center;">
                ${list.map(code => `<code style="display:block;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px dashed #cbd5e1;font-family:Consolas,monospace;letter-spacing:.08em;">${code}</code>`).join('')}
            </div>
            <p style="margin:14px 0 0;color:#64748b;font-size:0.92rem;">Store these somewhere safe. Each code can be used once if you lose access to your authenticator app.</p>
            <div style="margin-top:14px;"><a href="${printUrl}" target="_blank" rel="noopener" class="btn btn-outline-primary">Open Print View</a></div>
        `;

        await Swal.fire({
            title,
            html,
            width: 640,
            confirmButtonText: 'Done'
        });
    }

    async function promptAuthenticatorCode(options) {
        const setupPayload = options?.setupPayload || null;
        const title = options?.title || 'Enter Authenticator Code';
        const confirmButtonText = options?.confirmButtonText || 'Verify';
        const verifyMode = options?.verifyMode || (setupPayload ? 'setup' : 'verify');
        const successTitle = options?.successTitle || '2FA Updated';
        const successText = options?.successText || 'Your authenticator code was accepted.';

        const result = await Swal.fire({
            title,
            html: setupPayload
                ? `
                    <div style="text-align:center;">
                        <p style="margin:0 0 14px;">Scan this QR code with your authenticator app, then enter the current 6-digit code.</p>
                        <img src="${setupPayload.qr_code}" alt="Authenticator QR code" style="max-width:240px;width:100%;height:auto;border:1px solid #dbe7f5;border-radius:16px;padding:12px;background:#fff;margin:0 auto 16px;display:block;">
                        <div style="font-size:0.9rem;color:#475569;">Authenticator entry</div>
                        <code style="display:block;margin:10px auto 16px;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px dashed #cbd5e1;font-family:Consolas,monospace;word-break:break-all;">${setupPayload.issuer}: ${setupPayload.account}</code>
                        <div style="font-size:0.9rem;color:#475569;">Manual setup key</div>
                        <code style="display:block;margin:10px auto 0;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px dashed #cbd5e1;font-family:Consolas,monospace;letter-spacing:.12em;word-break:break-all;">${setupPayload.secret}</code>
                    </div>
                `
                : '<p style="margin:0;">Enter the current 6-digit code from your authenticator app.</p>',
            input: 'text',
            inputAttributes: {
                maxlength: 6,
                required: true,
                autocapitalize: 'off',
                autocorrect: 'off',
                inputmode: 'numeric',
                pattern: '[0-9]*'
            },
            showCancelButton: true,
            confirmButtonText,
            preConfirm: code => {
                const trimmedCode = String(code || '').trim();
                if (!trimmedCode) {
                    Swal.showValidationMessage('Enter the 6-digit code from your authenticator app.');
                    return false;
                }

                Swal.fire({
                    title: setupPayload ? 'Saving Authenticator Setup...' : 'Verifying Authenticator Code...',
                    text: 'Please wait...',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                return $.post('verify_2fa_code.php', {
                    code: trimmedCode,
                    mode: verifyMode,
                    csrf_token: csrfToken
                }, null, 'json').then(data => {
                    Swal.close();
                    if (data.success) {
                        const finish = async () => {
                            if (Array.isArray(data.recovery_codes) && data.recovery_codes.length > 0) {
                                await showRecoveryCodes(data.recovery_codes, 'Save Your Recovery Codes', 'Use these one-time codes only if you lose access to your authenticator app.');
                            } else {
                                await Swal.fire(successTitle, successText, 'success');
                            }
                            location.reload();
                        };
                        return finish();
                    }
                    Swal.showValidationMessage(data.message || 'Invalid code.');
                }).catch(error => {
                    Swal.close();
                    Swal.showValidationMessage(error?.responseJSON?.message || 'Verification failed. Please try again.');
                });
            }
        });

        return result;
    }

    $('#deleteAccountBtn').click(async function () {
        if (twoFASetupIncomplete) {
            Swal.fire('Finish 2FA Setup First', 'Complete your authenticator setup before deleting the account, or disable 2FA from the security card first.', 'warning');
            return;
        }

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
            const result = await Swal.fire({
                title: 'Enter Authenticator Code',
                input: 'text',
                inputLabel: 'Use the current code from your authenticator app or one of your saved recovery codes',
                inputPlaceholder: '6-digit code',
                inputAttributes: { maxlength: 6, autocapitalize: 'off', autocorrect: 'off', inputmode: 'numeric' },
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
        const isEnabled = twoFAEnabled && twoFAHasSecret;

        if (isEnabled) {
            Swal.fire({
                title: 'Disable 2FA?',
                text: 'This will turn off authenticator-based protection for your account.',
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
                title: twoFASetupIncomplete ? 'Preparing Authenticator Setup...' : 'Preparing Authenticator Setup...',
                text: 'Please wait while we prepare your QR code.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const setupPromise = requestTwoFactorSetup();
            setupPromise.then(setupPayload => {
                Swal.close();
                return promptAuthenticatorCode({
                    setupPayload,
                    title: twoFASetupIncomplete ? 'Finish Two-Factor Authentication Setup' : 'Enable Two-Factor Authentication',
                    confirmButtonText: twoFASetupIncomplete ? 'Finish Setup' : 'Enable 2FA',
                    verifyMode: 'setup',
                    successTitle: '2FA Enabled!',
                    successText: 'Your account now uses authenticator-based protection.'
                });
            }).catch(error => {
                Swal.close();
                Swal.fire('Error', error?.message || 'Failed to prepare authenticator setup.', 'error');
            });
        }
    });

    $('#regenerateRecoveryCodesBtn').click(function () {
        Swal.fire({
            title: 'Regenerate Recovery Codes?',
            text: 'Your old recovery codes will stop working immediately.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Regenerate'
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                title: 'Generating Recovery Codes...',
                text: 'Please wait...',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            regenerateRecoveryCodes({
                showCodes: true,
                title: 'New Recovery Codes',
                text: 'Your previous recovery codes have been replaced. Save the new set now.'
            })
                .then(async data => {
                    Swal.close();
                    location.reload();
                })
                .catch(error => {
                    Swal.close();
                    Swal.fire('Error', error?.message || error?.responseJSON?.message || 'Unable to regenerate recovery codes.', 'error');
                });
        });
    });

    $('#printRecoveryCodesBtn').click(function () {
        const printUrl = 'recovery_codes_print.php';

        Swal.fire({
            title: 'Prepare Recovery Codes?',
            text: 'If no printable recovery codes are currently loaded, KODUS will generate a fresh set before opening the print view.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Open Print View'
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                title: 'Preparing Print View...',
                text: 'Please wait while we prepare your recovery codes.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            regenerateRecoveryCodes({ showCodes: false })
                .then(() => {
                    Swal.close();
                    window.open(printUrl, '_blank', 'noopener');
                    Swal.fire({
                        icon: 'success',
                        title: 'Print View Ready',
                        text: 'A fresh recovery-code set was prepared and opened in a new tab.'
                    }).then(() => location.reload());
                })
                .catch(error => {
                    Swal.close();
                    Swal.fire('Error', error?.message || error?.responseJSON?.message || 'Unable to prepare the recovery code print view.', 'error');
                });
        });
    });

    const queuedAlerts = [];

    <?php if ($showFirstTimeSsoProfilePrompt): ?>
    queuedAlerts.push({
        icon: 'info',
        title: 'Complete Your Profile',
        text: 'This is your first SSO sign-in. Please review and update the Profile Information section so your local KODUS profile is complete.'
    });
    <?php endif; ?>

    <?php if (is_array($flash) && !empty($flash['title'])): ?>
    queuedAlerts.push({
        icon: <?= json_encode((string) ($flash['type'] ?? 'info')) ?>,
        title: <?= json_encode((string) ($flash['title'] ?? 'Settings')) ?>,
        text: <?= json_encode((string) ($flash['message'] ?? '')) ?>
    });
    <?php endif; ?>

    if (queuedAlerts.length > 0) {
        queuedAlerts.reduce((promise, alertConfig) => {
            return promise.then(() => Swal.fire(alertConfig));
        }, Promise.resolve());
    }
});
</script>
</body>
</html>

