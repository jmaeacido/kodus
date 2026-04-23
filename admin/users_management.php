<?php
include('../header.php');
include('../sidenav.php');

function format_relative_activity_um(int $timestamp): string
{
    $seconds = max(0, time() - $timestamp);

    if ($seconds < 60) {
        return 'just now';
    }

    $minutes = (int) floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    $hours = (int) floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($hours / 24);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function classify_user_presence_um(?string $lastActivity, int $isOnline): array
{
    if (!$lastActivity) {
        return [
            'label' => 'Offline',
            'badge' => 'secondary',
            'detail' => 'No activity recorded',
            'sort' => 2,
        ];
    }

    $lastActivityTs = strtotime($lastActivity);
    if ($lastActivityTs === false) {
        return [
            'label' => 'Offline',
            'badge' => 'secondary',
            'detail' => 'Activity unavailable',
            'sort' => 2,
        ];
    }

    $secondsSinceActive = time() - $lastActivityTs;
    if ($isOnline === 1 && $secondsSinceActive <= 300) {
        return [
            'label' => 'Online',
            'badge' => 'success',
            'detail' => 'Active just now',
            'sort' => 0,
        ];
    }

    if ($secondsSinceActive <= 1800) {
        return [
            'label' => 'Idle',
            'badge' => 'warning',
            'detail' => 'Last active ' . format_relative_activity_um($lastActivityTs),
            'sort' => 1,
        ];
    }

    return [
        'label' => 'Offline',
        'badge' => 'secondary',
        'detail' => 'Last active ' . format_relative_activity_um($lastActivityTs),
        'sort' => 2,
    ];
}

function user_management_full_name(array $row): string
{
    $parts = array_filter([
        $row['first_name'] ?? '',
        $row['middle_name'] ?? '',
        $row['last_name'] ?? ''
    ]);

    $fullName = trim(ucwords(strtolower(implode(' ', $parts))));
    if (!empty($row['ext'])) {
        $fullName .= ' ' . $row['ext'];
    }

    return $fullName !== '' ? $fullName : (string) ($row['username'] ?? 'Unknown User');
}

function user_management_avatar_url(?string $picture, ?string $ssoAvatarUrl, string $baseUrl): string
{
    return avatar_resolve_url($picture, $ssoAvatarUrl, $baseUrl, dirname(__DIR__));
}

function user_management_two_factor_status(array $row): array
{
    $enabled = !empty($row['two_fa_enabled']);
    $hasSecret = trim((string) ($row['two_fa_secret'] ?? '')) !== '';
    $recoveryCount = two_factor_recovery_code_count($row);

    if ($enabled && $hasSecret) {
        return [
            'label' => 'Configured',
            'badge' => 'success',
            'detail' => $recoveryCount . ' recovery code' . ($recoveryCount === 1 ? '' : 's'),
            'sort' => 0,
        ];
    }

    if ($enabled) {
        return [
            'label' => 'Pending Setup',
            'badge' => 'warning',
            'detail' => 'Authenticator not enrolled yet',
            'sort' => 1,
        ];
    }

    return [
        'label' => 'Disabled',
        'badge' => 'secondary',
        'detail' => 'Password-only access',
        'sort' => 2,
    ];
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../');
    exit;
}

$stmt = $conn->prepare("SELECT userType FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userType);
$stmt->fetch();
$stmt->close();

if ($userType !== 'admin') {
    echo "<script src='../plugins/sweetalert2/sweetalert2.min.js'></script>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'You are not authorized to view this page.',
      }).then(() => window.location.href = '../');
    </script>";
    exit;
}

$activeUsersResult = $conn->query("
    SELECT id, username, email, userType, first_name, middle_name, last_name, ext, picture, sso_avatar_url, date_registered, last_activity, is_online, two_fa_enabled, two_fa_secret, two_fa_recovery_codes
    FROM users
    WHERE deleted_at IS NULL
    ORDER BY id ASC
");

$activeUsers = [];
$statusSummary = [
    'Online' => 0,
    'Idle' => 0,
    'Offline' => 0,
];
$twoFactorSummary = [
    'configured' => 0,
    'pending' => 0,
    'disabled' => 0,
];

if ($activeUsersResult) {
    while ($row = $activeUsersResult->fetch_assoc()) {
        $row['full_name'] = user_management_full_name($row);
        $row['avatar_url'] = user_management_avatar_url($row['picture'] ?? '', $row['sso_avatar_url'] ?? '', $base_url);
        $row['presence'] = classify_user_presence_um($row['last_activity'], (int) ($row['is_online'] ?? 0));
        $row['two_factor'] = user_management_two_factor_status($row);
        $statusSummary[$row['presence']['label']]++;
        if ($row['two_factor']['label'] === 'Configured') {
            $twoFactorSummary['configured']++;
        } elseif ($row['two_factor']['label'] === 'Pending Setup') {
            $twoFactorSummary['pending']++;
        } else {
            $twoFactorSummary['disabled']++;
        }
        $activeUsers[] = $row;
    }
}

$deletedUsersResult = $conn->query("
    SELECT id, username, email, first_name, middle_name, last_name, ext, picture, sso_avatar_url, deleted_at
    FROM users
    WHERE deleted_at IS NOT NULL
    ORDER BY id ASC
");

$deletedUsers = [];
if ($deletedUsersResult) {
    while ($row = $deletedUsersResult->fetch_assoc()) {
        $row['full_name'] = user_management_full_name($row);
        $row['avatar_url'] = user_management_avatar_url($row['picture'] ?? '', $row['sso_avatar_url'] ?? '', $base_url);
        $deletedUsers[] = $row;
    }
}

$deactivatedCount = count($deletedUsers);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Users Management</title>
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <style>
    .users-management-page {
      --users-page-bg:
        radial-gradient(circle at top right, rgba(0, 123, 255, 0.16), transparent 28%),
        linear-gradient(180deg, #15202b 0%, #0f1720 100%);
      --users-panel-bg: rgba(31, 41, 55, 0.98);
      --users-panel-soft: linear-gradient(135deg, #1f2937 0%, #243140 100%);
      --users-panel-muted: rgba(36, 49, 64, 0.92);
      --users-border: rgba(255, 255, 255, 0.09);
      --users-text: #f8f9fa;
      --users-muted: #b8c2cc;
      --users-tab-icon-bg: rgba(96, 165, 250, 0.18);
      --users-tab-icon-text: #9ec5fe;
      --users-hover-bg: rgba(96, 165, 250, 0.08);
      --users-avatar-border: rgba(255, 255, 255, 0.18);
      --users-avatar-bg: #2d3748;
      --users-table-head-bg: #243140;
      --users-table-head-text: #dbe7f3;
      --users-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
      --users-soft-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
    }
    body[data-theme="light"] .users-management-page {
      --users-page-bg:
        radial-gradient(circle at top right, rgba(0, 123, 255, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, #f4f6f9 100%);
      --users-panel-bg: rgba(255, 255, 255, 0.98);
      --users-panel-soft: linear-gradient(135deg, #ffffff 0%, #f4f8ff 100%);
      --users-panel-muted: rgba(255, 255, 255, 0.9);
      --users-border: rgba(31, 45, 61, 0.08);
      --users-text: #1f2d3d;
      --users-muted: #5c6773;
      --users-tab-icon-bg: rgba(0, 123, 255, 0.09);
      --users-tab-icon-text: #0b63ce;
      --users-hover-bg: rgba(0, 123, 255, 0.04);
      --users-avatar-border: rgba(255, 255, 255, 0.8);
      --users-avatar-bg: #e9ecef;
      --users-table-head-bg: #f7f9fc;
      --users-table-head-text: #495057;
      --users-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
      --users-soft-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }
    .users-management-page .content-wrapper {
      background: var(--users-page-bg);
    }
    .users-management-page .management-hero {
      border-radius: 1rem;
      background: var(--users-panel-soft);
      border: 1px solid var(--users-border);
      box-shadow: var(--users-shadow);
      padding: 1.15rem 1.25rem;
      margin-bottom: 1rem;
    }
    .users-management-page .management-hero h2 {
      font-size: 1.2rem;
      margin: 0 0 0.35rem;
      font-weight: 700;
      color: var(--users-text);
    }
    .users-management-page .management-hero p {
      margin: 0;
      color: var(--users-muted);
    }
    .users-management-page .management-hero .hero-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border-radius: 999px;
      padding: 0.5rem 0.85rem;
      background: rgba(13, 110, 253, 0.1);
      color: #0f4fa8;
      font-weight: 600;
      font-size: 0.9rem;
      white-space: nowrap;
    }
    .users-management-page .small-box {
      margin-bottom: 0.9rem;
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: var(--users-soft-shadow);
    }
    .users-management-page .small-box .inner p {
      font-weight: 600;
      letter-spacing: 0.01em;
    }
    .users-management-page .summary-note {
      border-radius: 0.95rem;
      background: var(--users-panel-muted);
      border: 1px solid var(--users-border);
      box-shadow: var(--users-soft-shadow);
      padding: 0.95rem 1rem;
      color: var(--users-text);
    }
    .users-management-page .nav-pills {
      gap: 0.85rem;
    }
    .users-management-page .nav-pills .nav-item {
      flex: 1 1 0;
      min-width: 220px;
    }
    .users-management-page .nav-pills .nav-link {
      border-radius: 1.15rem;
      margin-right: 0;
      font-weight: 700;
      padding: 1rem 1.1rem;
      color: var(--users-text);
      background: linear-gradient(180deg, var(--users-panel-bg) 0%, color-mix(in srgb, var(--users-panel-bg) 88%, var(--users-panel-soft) 12%) 100%);
      border: 1px solid color-mix(in srgb, var(--users-border) 82%, rgba(0, 123, 255, 0.2) 18%);
      box-shadow: var(--users-shadow);
      font-size: 0.95rem;
      letter-spacing: 0.01em;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background-color 0.18s ease;
      min-height: 100%;
    }
    .users-management-page .nav-pills .nav-link.active {
      background: linear-gradient(135deg, #0d6efd 0%, #2f80ed 100%);
      border-color: rgba(13, 110, 253, 0.55);
      color: #fff;
      box-shadow: 0 18px 34px rgba(13, 110, 253, 0.24);
    }
    .users-management-page .nav-pills .nav-link:hover,
    .users-management-page .nav-pills .nav-link:focus {
      color: var(--users-tab-icon-text);
      border-color: rgba(0, 123, 255, 0.3);
      box-shadow: 0 18px 32px rgba(0, 123, 255, 0.14);
      transform: translateY(-3px);
    }
    .users-management-page .tab-link-content {
      display: flex;
      align-items: flex-start;
      gap: 0.9rem;
      text-align: left;
    }
    .users-management-page .tab-link-icon {
      width: 46px;
      height: 46px;
      border-radius: 0.95rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--users-tab-icon-bg);
      color: var(--users-tab-icon-text);
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .users-management-page .nav-pills .nav-link.active .tab-link-icon {
      background: rgba(255, 255, 255, 0.18);
      color: #fff;
    }
    .users-management-page .tab-link-text {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      line-height: 1.15;
      flex: 1 1 auto;
    }
    .users-management-page .tab-link-title {
      font-size: 1rem;
    }
    .users-management-page .tab-link-subtitle {
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--users-muted);
      margin-top: 0.3rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .users-management-page .nav-pills .nav-link.active .tab-link-subtitle {
      color: rgba(255, 255, 255, 0.82);
    }
    .users-management-page .tab-pane-card {
      border: 1px solid var(--users-border);
      border-radius: 1rem;
      overflow: hidden;
      background: var(--users-panel-bg);
      box-shadow: var(--users-shadow);
    }
    .users-management-page .tab-pane-card .card-header {
      background: color-mix(in srgb, var(--users-panel-bg) 92%, transparent);
      border-bottom: 1px solid var(--users-border);
      padding: 1rem 1.15rem;
    }
    .users-management-page .section-caption {
      margin-top: 0.25rem;
      color: var(--users-muted);
      font-size: 0.9rem;
    }
    .users-management-page .user-cell {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 0.55rem;
      text-align: left;
      min-width: 0;
      flex-wrap: nowrap;
      white-space: nowrap;
    }
    .users-management-page .username-cell {
      min-width: 210px;
      white-space: nowrap;
    }
    .users-management-page .username-text {
      display: inline-block;
      white-space: nowrap;
      font-size: 0.93rem;
      font-weight: 600;
      line-height: 1.2;
      vertical-align: middle;
    }
    .users-management-page .user-avatar-frame {
      width: 36px;
      min-width: 36px;
      max-width: 36px;
      height: 36px;
      min-height: 36px;
      max-height: 36px;
      border-radius: 999px;
      overflow: hidden;
      position: relative;
      display: inline-block;
      vertical-align: middle;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      border: 2px solid var(--users-avatar-border);
      box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
      background: var(--users-avatar-bg);
      line-height: 0;
    }
    .users-management-page .user-avatar {
      width: 36px;
      height: 36px;
      min-width: 36px;
      min-height: 36px;
      max-width: 36px;
      max-height: 36px;
      object-fit: cover;
      object-position: center center;
      display: block;
      border-radius: 999px;
      flex-shrink: 0;
      overflow: hidden;
    }
    .users-management-page .user-meta small {
      color: var(--users-muted);
      font-size: 0.78rem;
    }
    .users-management-page .table thead th {
      background: var(--users-table-head-bg);
      border-bottom: 0;
      color: var(--users-table-head-text);
      font-size: 0.76rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .users-management-page .table td,
    .users-management-page .table th {
      vertical-align: middle;
    }
    .users-management-page .table tbody tr {
      transition: background-color 0.18s ease, transform 0.18s ease;
    }
    .users-management-page .table tbody tr:hover {
      background-color: var(--users-hover-bg);
    }
    .users-management-page .activity-cell {
      min-width: 190px;
      text-align: left;
    }
    .users-management-page .action-stack {
      display: flex;
      gap: 0.45rem;
      flex-wrap: wrap;
      justify-content: center;
    }
    .users-management-page .status-badge {
      border-radius: 999px;
      padding: 0.45rem 0.7rem;
      font-size: 0.76rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    .users-management-page .checkbox-cell {
      width: 52px;
      text-align: center;
    }
    .users-management-page .checkbox-cell input[type="checkbox"] {
      width: 16px;
      height: 16px;
      cursor: pointer;
    }
    .users-management-page .btn {
      border-radius: 999px;
      font-weight: 600;
      box-shadow: none;
    }
    .users-management-page .dt-buttons .btn {
      margin-right: 0.4rem;
      margin-bottom: 0.4rem;
    }
    .users-management-page .dataTables_filter input {
      border-radius: 999px;
      padding-left: 0.9rem;
    }
    .users-management-page .dataTables_length select {
      border-radius: 999px;
    }
    @media (max-width: 1600px) {
      .users-management-page .management-hero {
        padding: 1rem 1.1rem;
      }
      .users-management-page .summary-note {
        padding: 0.85rem 0.95rem;
      }
      .users-management-page .nav-pills {
        gap: 0.75rem;
      }
      .users-management-page .nav-pills .nav-link {
        padding: 0.9rem 1rem;
      }
      .users-management-page .tab-pane-card .card-header {
        padding: 0.9rem 1rem;
      }
    }
    @media (max-width: 1366px) {
      .users-management-page .management-hero h2 {
        font-size: 1.08rem;
      }
      .users-management-page .management-hero .hero-pill {
        font-size: 0.84rem;
        padding: 0.42rem 0.75rem;
      }
      .users-management-page .nav-pills .nav-item {
        min-width: 190px;
      }
      .users-management-page .nav-pills .nav-link {
        border-radius: 1rem;
        padding: 0.82rem 0.9rem;
        font-size: 0.9rem;
      }
      .users-management-page .tab-link-content {
        gap: 0.75rem;
      }
      .users-management-page .tab-link-icon {
        width: 40px;
        height: 40px;
        border-radius: 0.85rem;
        font-size: 1rem;
      }
      .users-management-page .tab-link-title {
        font-size: 0.94rem;
      }
      .users-management-page .tab-link-subtitle,
      .users-management-page .section-caption {
        font-size: 0.74rem;
      }
      .users-management-page .summary-note,
      .users-management-page .small-box .inner {
        font-size: 0.88rem;
      }
    }
    @media (max-width: 1280px) {
      .users-management-page .management-hero,
      .users-management-page .summary-note {
        margin-bottom: 0.85rem;
      }
      .users-management-page .nav-pills {
        gap: 0.65rem;
      }
      .users-management-page .nav-pills .nav-item {
        min-width: 170px;
      }
      .users-management-page .username-cell {
        min-width: 180px;
      }
      .users-management-page .activity-cell {
        min-width: 165px;
      }
      .users-management-page .user-avatar-frame,
      .users-management-page .user-avatar {
        width: 32px;
        min-width: 32px;
        max-width: 32px;
        height: 32px;
        min-height: 32px;
        max-height: 32px;
      }
    }
    @media (max-width: 1024px) {
      .users-management-page .management-hero {
        padding: 0.9rem 0.95rem;
      }
      .users-management-page .nav-pills .nav-link {
        padding: 0.75rem 0.85rem;
      }
      .users-management-page .action-stack {
        gap: 0.35rem;
      }
      .users-management-page .status-badge {
        padding: 0.38rem 0.62rem;
      }
    }
    @media (max-width: 767.98px) {
      .users-management-page .management-hero {
        padding: 1rem;
      }
      .users-management-page .nav-pills .nav-link {
        margin-bottom: 0.5rem;
      }
      .users-management-page .user-cell {
        min-width: 180px;
      }
    }
  </style>
</head>
<body class="users-management-page">
<div class="wrapper">

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Users Management</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $app_root; ?>home">Home</a></li>
              <li class="breadcrumb-item active">Users Management</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="management-hero d-flex flex-wrap justify-content-between align-items-center">
          <div>
            <h2>Handle user access, roles, and recovery in one workspace</h2>
            <p>Review live presence, update account roles, deactivate access, and restore accounts without jumping between admin pages.</p>
          </div>
          <div class="hero-pill">
            <i class="fas fa-users-cog"></i>
            <span><?php echo count($activeUsers) + count($deletedUsers); ?> total managed accounts</span>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3 id="onlineCount"><?php echo (int) $statusSummary['Online']; ?></h3>
                <p>Online</p>
              </div>
              <div class="icon"><i class="fas fa-signal"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-warning">
              <div class="inner">
                <h3 id="idleCount"><?php echo (int) $statusSummary['Idle']; ?></h3>
                <p>Idle</p>
              </div>
              <div class="icon"><i class="fas fa-user-clock"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-secondary">
              <div class="inner">
                <h3 id="offlineCount"><?php echo (int) $statusSummary['Offline']; ?></h3>
                <p>Offline</p>
              </div>
              <div class="icon"><i class="fas fa-user-slash"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-danger">
              <div class="inner">
                <h3 id="deactivatedCount"><?php echo (int) $deactivatedCount; ?></h3>
                <p>Deactivated / Deleted</p>
              </div>
              <div class="icon"><i class="fas fa-user-times"></i></div>
            </div>
          </div>
        </div>

        <div class="summary-note mb-3 d-flex flex-wrap justify-content-between align-items-center">
          <span>Users Restoration, Users Classification, and Deactivate/Delete Users are now managed in one screen.</span>
          <strong id="statusRefreshText">Updated just now</strong>
        </div>

        <ul class="nav nav-pills mb-3" id="usersManagementTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="classification-tab" data-toggle="pill" href="#classification" role="tab">
              <span class="tab-link-content">
                <span class="tab-link-icon"><i class="fas fa-id-badge"></i></span>
                <span class="tab-link-text">
                  <span class="tab-link-title">Users Classification</span>
                  <span class="tab-link-subtitle">Roles and Presence</span>
                </span>
              </span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="deactivate-tab" data-toggle="pill" href="#deactivate" role="tab">
              <span class="tab-link-content">
                <span class="tab-link-icon"><i class="fas fa-user-slash"></i></span>
                <span class="tab-link-text">
                  <span class="tab-link-title">Deactivate / Delete Users</span>
                  <span class="tab-link-subtitle">Restrict Access</span>
                </span>
              </span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="restoration-tab" data-toggle="pill" href="#restoration" role="tab">
              <span class="tab-link-content">
                <span class="tab-link-icon"><i class="fas fa-undo-alt"></i></span>
                <span class="tab-link-text">
                  <span class="tab-link-title">Users Restoration</span>
                  <span class="tab-link-subtitle">Recover Accounts</span>
                </span>
              </span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="twofactor-tab" data-toggle="pill" href="#twofactor" role="tab">
              <span class="tab-link-content">
                <span class="tab-link-icon"><i class="fas fa-user-shield"></i></span>
                <span class="tab-link-text">
                  <span class="tab-link-title">2FA Readiness</span>
                  <span class="tab-link-subtitle">Authenticator Status</span>
                </span>
              </span>
            </a>
          </li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="classification" role="tabpanel">
            <div class="card tab-pane-card">
              <div class="card-header">
                <h3 class="card-title">Registered Users and Presence Classification</h3>
                <br>
                <div class="section-caption">See live presence, latest activity, and change user roles from one table.</div>
              </div>
              <div class="card-body">
                <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1100px;">
                <table id="classificationTable" class="table table-bordered table-striped" style="width:100%;">
                  <thead>
                    <tr>
                      <th>Username</th>
                      <th>Full Name</th>
                      <th>Email</th>
                      <th>Date Registered</th>
                      <th>User Type</th>
                      <th>Status</th>
                      <th>Latest Activity</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($activeUsers as $row): ?>
                      <tr data-user-id="<?php echo (int) $row['id']; ?>">
                        <td class="username-cell">
                          <div class="user-cell">
                            <span class="user-avatar-frame" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;border-radius:50%;overflow:hidden;display:inline-block;vertical-align:middle;line-height:0;">
                              <img src="<?php echo htmlspecialchars((string) $row['avatar_url'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string) $row['username'], ENT_QUOTES); ?>" class="user-avatar" width="36" height="36" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;display:block;border-radius:50%;object-fit:cover;object-position:center center;">
                            </span>
                            <span class="username-text"><?php echo htmlspecialchars((string) $row['username']); ?></span>
                          </div>
                        </td>
                        <td><?php echo htmlspecialchars((string) $row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['email']); ?></td>
                        <td><?php echo htmlspecialchars(date("F d, Y h:ia", strtotime((string) $row['date_registered']))); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['userType']); ?></td>
                        <td data-order="<?php echo htmlspecialchars((string) $row['presence']['sort']); ?>">
                          <span class="badge badge-<?php echo htmlspecialchars((string) $row['presence']['badge']); ?> status-badge"><?php echo htmlspecialchars((string) $row['presence']['label']); ?></span>
                        </td>
                        <td class="activity-cell">
                          <div class="font-weight-bold"><?php echo htmlspecialchars((string) $row['presence']['detail']); ?></div>
                          <div class="small text-muted">
                            <?php echo !empty($row['last_activity']) ? htmlspecialchars(date("F d, Y h:ia", strtotime((string) $row['last_activity']))) : 'No timestamp available'; ?>
                          </div>
                        </td>
                        <td>
                          <button class="btn btn-success btn-sm change-user-type-btn" data-id="<?php echo (int) $row['id']; ?>">
                            <i class="fas fa-user-tag mr-1"></i>Change User Type
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="deactivate" role="tabpanel">
            <div class="card tab-pane-card">
              <div class="card-header">
                <h3 class="card-title">Deactivate Active Accounts</h3>
                <br>
                <div class="section-caption">Temporarily remove access while keeping accounts restorable later.</div>
              </div>
              <div class="card-body">
                <div class="alert alert-warning">
                  This section performs a soft delete. Deactivated users are removed from active access and can be restored later.
                </div>
                <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 980px;">
                <table id="deactivateTable" class="table table-bordered table-striped" style="width:100%;">
                  <thead>
                    <tr>
                      <th>Username</th>
                      <th>Full Name</th>
                      <th>Email</th>
                      <th>Date Registered</th>
                      <th>User Type</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($activeUsers as $row): ?>
                      <?php
                        $isProtected = ((int) $row['id'] === (int) $userId) || ((string) $row['userType'] === 'admin');
                        $buttonLabel = ((int) $row['id'] === (int) $userId) ? 'Current Account' : (((string) $row['userType'] === 'admin') ? 'Admin Protected' : 'Deactivate');
                      ?>
                      <tr>
                        <td class="username-cell">
                          <div class="user-cell">
                            <span class="user-avatar-frame" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;border-radius:50%;overflow:hidden;display:inline-block;vertical-align:middle;line-height:0;">
                              <img src="<?php echo htmlspecialchars((string) $row['avatar_url'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string) $row['username'], ENT_QUOTES); ?>" class="user-avatar" width="36" height="36" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;display:block;border-radius:50%;object-fit:cover;object-position:center center;">
                            </span>
                            <span class="username-text"><?php echo htmlspecialchars((string) $row['username']); ?></span>
                          </div>
                        </td>
                        <td><?php echo htmlspecialchars((string) $row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['email']); ?></td>
                        <td><?php echo htmlspecialchars(date("F d, Y h:ia", strtotime((string) $row['date_registered']))); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['userType']); ?></td>
                        <td>
                          <button
                            class="btn btn-danger btn-sm deactivate-btn"
                            data-id="<?php echo (int) $row['id']; ?>"
                            data-name="<?php echo htmlspecialchars((string) $row['full_name'], ENT_QUOTES); ?>"
                            data-email="<?php echo htmlspecialchars((string) $row['email'], ENT_QUOTES); ?>"
                            <?php echo $isProtected ? 'disabled' : ''; ?>
                          >
                            <i class="fas fa-user-slash mr-1"></i><?php echo htmlspecialchars($buttonLabel); ?>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="restoration" role="tabpanel">
            <div class="card tab-pane-card">
              <div class="card-header">
                <h3 class="card-title">Restore Soft-Deleted Users</h3>
                <br>
                <div class="section-caption">Bring back deactivated accounts and return them to the active user list.</div>
              </div>
              <div class="card-body">
                <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 900px;">
                <table id="restorationTable" class="table table-bordered table-striped" style="width:100%;">
                  <thead>
                    <tr>
                      <th>Username</th>
                      <th>Full Name</th>
                      <th>Email</th>
                      <th>Deleted At</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($deletedUsers): ?>
                      <?php foreach ($deletedUsers as $row): ?>
                        <tr>
                          <td class="username-cell">
                            <div class="user-cell">
                              <span class="user-avatar-frame" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;border-radius:50%;overflow:hidden;display:inline-block;vertical-align:middle;line-height:0;">
                                <img src="<?php echo htmlspecialchars((string) $row['avatar_url'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string) $row['username'], ENT_QUOTES); ?>" class="user-avatar" width="36" height="36" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;display:block;border-radius:50%;object-fit:cover;object-position:center center;">
                              </span>
                              <span class="username-text"><?php echo htmlspecialchars((string) $row['username']); ?></span>
                            </div>
                          </td>
                          <td><?php echo htmlspecialchars((string) $row['full_name']); ?></td>
                          <td><?php echo htmlspecialchars((string) $row['email']); ?></td>
                          <td><?php echo htmlspecialchars((string) $row['deleted_at']); ?></td>
                          <td>
                            <button class="btn btn-success btn-sm restore-btn" data-id="<?php echo (int) $row['id']; ?>">
                              <i class="fas fa-undo-alt mr-1"></i>Restore
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="twofactor" role="tabpanel">
            <div class="card tab-pane-card">
              <div class="card-header">
                <h3 class="card-title">Authenticator 2FA Rollout Status</h3>
                <br>
                <div class="section-caption">See who already completed authenticator setup, who is still pending, and who has 2FA disabled.</div>
              </div>
              <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap:0.75rem;">
                  <div class="text-muted small">Select one or more users, then reset their authenticator setup in one action.</div>
                  <div class="d-flex flex-wrap align-items-center" style="gap:0.75rem;">
                    <select id="twoFactorStatusFilter" class="form-control form-control-sm" style="min-width:220px;">
                      <option value="">All 2FA Statuses</option>
                      <option value="Configured">Configured</option>
                      <option value="Pending Setup">Pending Setup</option>
                      <option value="Disabled">Disabled</option>
                    </select>
                    <button type="button" class="btn btn-warning btn-sm" id="bulkReset2faBtn" disabled>
                      <i class="fas fa-layer-group mr-1"></i>Reset Selected 2FA
                    </button>
                  </div>
                </div>
                <div class="row mb-3">
                  <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-success">
                      <div class="inner">
                        <h3><?= (int) $twoFactorSummary['configured']; ?></h3>
                        <p>Configured</p>
                      </div>
                      <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                  </div>
                  <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-warning">
                      <div class="inner">
                        <h3><?= (int) $twoFactorSummary['pending']; ?></h3>
                        <p>Pending Setup</p>
                      </div>
                      <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                  </div>
                  <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-secondary">
                      <div class="inner">
                        <h3><?= (int) $twoFactorSummary['disabled']; ?></h3>
                        <p>Disabled</p>
                      </div>
                      <div class="icon"><i class="fas fa-user-lock"></i></div>
                    </div>
                  </div>
                </div>
                <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1200px;">
                <table id="twoFactorTable" class="table table-bordered table-striped" style="width:100%;">
                  <thead>
                    <tr>
                      <th class="checkbox-cell">
                        <input type="checkbox" id="selectAll2faUsers" aria-label="Select all visible users">
                      </th>
                      <th>Username</th>
                      <th>Full Name</th>
                      <th>Email</th>
                      <th>2FA Status</th>
                      <th>Recovery Codes</th>
                      <th>Presence</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($activeUsers as $row): ?>
                      <tr>
                        <td class="checkbox-cell">
                          <input
                            type="checkbox"
                            class="twofactor-user-checkbox"
                            id="twofactor-user-<?= (int) $row['id']; ?>"
                            value="<?= (int) $row['id']; ?>"
                            data-name="<?= htmlspecialchars((string) $row['full_name'], ENT_QUOTES); ?>"
                            aria-label="Select <?= htmlspecialchars((string) $row['full_name'], ENT_QUOTES); ?>"
                          >
                        </td>
                        <td class="username-cell">
                          <div class="user-cell">
                            <span class="user-avatar-frame" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;border-radius:50%;overflow:hidden;display:inline-block;vertical-align:middle;line-height:0;">
                              <img src="<?php echo htmlspecialchars((string) $row['avatar_url'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string) $row['username'], ENT_QUOTES); ?>" class="user-avatar" width="36" height="36" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;display:block;border-radius:50%;object-fit:cover;object-position:center center;">
                            </span>
                            <span class="username-text"><?php echo htmlspecialchars((string) $row['username']); ?></span>
                          </div>
                        </td>
                        <td><?php echo htmlspecialchars((string) $row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['email']); ?></td>
                        <td data-order="<?php echo htmlspecialchars((string) $row['two_factor']['sort']); ?>">
                          <span class="badge badge-<?php echo htmlspecialchars((string) $row['two_factor']['badge']); ?> status-badge"><?php echo htmlspecialchars((string) $row['two_factor']['label']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars((string) $row['two_factor']['detail']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars((string) $row['presence']['badge']); ?> status-badge"><?php echo htmlspecialchars((string) $row['presence']['label']); ?></span></td>
                        <td>
                          <button
                            class="btn btn-outline-warning btn-sm reset-2fa-btn"
                            data-id="<?php echo (int) $row['id']; ?>"
                            data-name="<?php echo htmlspecialchars((string) $row['full_name'], ENT_QUOTES); ?>"
                            data-status="<?php echo htmlspecialchars((string) $row['two_factor']['label'], ENT_QUOTES); ?>"
                          >
                            <i class="fas fa-key mr-1"></i>Reset 2FA
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                </div>
              </div>
            </div>
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
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script>
function initUsersManagementTable(selector) {
  return $(selector).DataTable({
    dom: "<'row align-items-center mb-3'<'col-md-12'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row mt-2'<'col-md-5'i><'col-md-7'p>>",
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: false,
    scrollX: true,
    pageLength: 10,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    order: []
  });
}

$(document).ready(function () {
  const classificationTable = initUsersManagementTable('#classificationTable');
  initUsersManagementTable('#deactivateTable');
  initUsersManagementTable('#restorationTable');
  const twoFactorTable = initUsersManagementTable('#twoFactorTable');

  function setStatusSummary(summary) {
    $('#onlineCount').text(summary.online || 0);
    $('#idleCount').text(summary.idle || 0);
    $('#offlineCount').text(summary.offline || 0);
    $('#deactivatedCount').text(summary.deactivated || 0);
  }

  function setRefreshTimestamp() {
    const now = new Date();
    $('#statusRefreshText').text('Updated ' + now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' }));
  }

  setRefreshTimestamp();

  $(document).on('click', '.change-user-type-btn', function () {
    const userId = $(this).data('id');

    Swal.fire({
      title: 'Change User Type',
      input: 'select',
      icon: 'info',
      inputOptions: {
        'admin': 'Administrator',
        'editor': 'Implementation Editor',
        'aa': 'Administrative Staff',
        'user': 'User'
      },
      inputPlaceholder: 'Select a user type',
      showCancelButton: true,
      confirmButtonText: 'Submit',
      cancelButtonText: 'Cancel',
      inputValidator: (value) => {
        if (!value) {
          return 'You must select a user type';
        }
      }
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: 'Updating...',
        html: 'Please wait while the user type is being updated...',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      $.ajax({
        url: 'change_user_type.php',
        method: 'POST',
        dataType: 'json',
        data: { user_id: userId, user_type: result.value, csrf_token: window.KODUS_CSRF_TOKEN },
        success: function (response) {
          Swal.close();
          if (response.success) {
            Swal.fire({ icon: 'success', title: 'User Type Changed', text: response.message }).then(() => location.reload());
          } else {
            Swal.fire({ icon: 'error', title: 'Update Failed', text: response.message });
          }
        },
        error: function () {
          Swal.close();
          Swal.fire({ icon: 'error', title: 'Unexpected Server Response', text: 'Something went wrong. Please try again later.' });
        }
      });
    });
  });

  $(document).on('click', '.deactivate-btn', function () {
    const userId = $(this).data('id');
    const userName = $(this).data('name');
    const userEmail = $(this).data('email');

    Swal.fire({
      title: 'Deactivate this user?',
      html: 'This will remove <strong>' + userName + '</strong> from active access.<br><small>' + userEmail + '</small>',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, deactivate'
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: 'Deactivating...',
        html: 'Please wait while the account is being deactivated.',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      $.ajax({
        url: 'deactivate_user.php',
        method: 'POST',
        dataType: 'json',
        data: { user_id: userId, csrf_token: window.KODUS_CSRF_TOKEN },
        success: function (response) {
          Swal.close();
          if (response.success) {
            Swal.fire({ icon: 'success', title: 'User Deactivated', text: response.message }).then(() => location.reload());
          } else {
            Swal.fire({ icon: 'error', title: 'Deactivation Failed', text: response.message });
          }
        },
        error: function () {
          Swal.close();
          Swal.fire({ icon: 'error', title: 'Unexpected Server Response', text: 'Something went wrong. Please try again later.' });
        }
      });
    });
  });

  $(document).on('click', '.restore-btn', function () {
    const userId = $(this).data('id');
    Swal.fire({
      title: 'Restore User?',
      text: 'Are you sure you want to restore this account?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, Restore'
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: 'Restoring...',
        html: 'Please wait while the selected account is being restored...',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      $.ajax({
        url: 'restore_user.php',
        method: 'POST',
        dataType: 'json',
        data: { user_id: userId, csrf_token: window.KODUS_CSRF_TOKEN },
        success: function (response) {
          Swal.close();
          if (response.success) {
            Swal.fire({ icon: 'success', title: 'User Restored', text: response.message }).then(() => location.reload());
          } else {
            Swal.fire({ icon: 'error', title: 'Restore Failed', text: response.message });
          }
        },
        error: function () {
          Swal.close();
          Swal.fire({ icon: 'error', title: 'Unexpected Server Response', text: 'Something went wrong. Please try again later.' });
        }
      });
    });
  });

  function refreshUserStatuses() {
    $.ajax({
      url: 'get_user_status.php',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        if (!response.success || !Array.isArray(response.users)) return;

        response.users.forEach(function (user) {
          const row = $('#classificationTable tbody tr[data-user-id="' + user.id + '"]');
          if (!row.length) return;

          classificationTable.cell(row, 5).data('<span class="badge badge-' + user.badge + ' status-badge">' + user.status + '</span>');
          classificationTable.cell(row, 6).data(
            '<div class="font-weight-bold">' + user.activity_detail + '</div>' +
            '<div class="small text-muted">' + user.activity_timestamp + '</div>'
          );
        });

        setStatusSummary(response.summary || {});
        setRefreshTimestamp();
        classificationTable.rows().invalidate('dom').draw(false);
      }
    });
  }

  if (window.KODUSLiveRefresh) {
    window.KODUSLiveRefresh.watch({
      channels: ['user_status_table'],
      onChange: refreshUserStatuses
    });
  }

  window.addEventListener('kodus:partial-refresh', function () {
    refreshUserStatuses();
  });

  if (window.location.hash === '#deactivate') {
    $('#deactivate-tab').tab('show');
  } else if (window.location.hash === '#restoration') {
    $('#restoration-tab').tab('show');
  }

  $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
    const target = $(e.target).attr('href');
    if (target) {
      history.replaceState(null, '', target);
    }
  });

  $(document).on('click', '.reset-2fa-btn', function () {
    const userId = $(this).data('id');
    const userName = $(this).data('name');
    const statusLabel = $(this).data('status');

    Swal.fire({
      title: 'Reset this user\'s authenticator setup?',
      html: 'User: <strong>' + userName + '</strong><br><small>Current 2FA status: ' + statusLabel + '</small><br><br>This clears the stored authenticator secret and recovery codes. The user will be required to enroll again on next login.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, reset 2FA'
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: 'Resetting 2FA...',
        text: 'Please wait while the user authenticator setup is reset.',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      $.ajax({
        url: 'reset_user_2fa.php',
        method: 'POST',
        dataType: 'json',
        data: { user_id: userId, csrf_token: window.KODUS_CSRF_TOKEN },
        success: function (response) {
          Swal.close();
          if (response.success) {
            Swal.fire({ icon: 'success', title: '2FA Reset', text: response.message }).then(() => location.reload());
          } else {
            Swal.fire({ icon: 'error', title: 'Reset Failed', text: response.message });
          }
        },
        error: function () {
          Swal.close();
          Swal.fire({ icon: 'error', title: 'Unexpected Server Response', text: 'Something went wrong. Please try again later.' });
        }
      });
    });
  });

  function getSelectedTwoFactorUsers() {
    return $('.twofactor-user-checkbox:checked').map(function () {
      return {
        id: Number($(this).val()),
        name: String($(this).data('name') || '')
      };
    }).get();
  }

  function getVisibleTwoFactorCheckboxes() {
    return $(twoFactorTable.rows({ search: 'applied' }).nodes()).find('.twofactor-user-checkbox');
  }

  function syncBulkResetState() {
    const selectedCount = getSelectedTwoFactorUsers().length;
    $('#bulkReset2faBtn')
      .prop('disabled', selectedCount === 0)
      .html('<i class="fas fa-layer-group mr-1"></i>Reset Selected 2FA' + (selectedCount > 0 ? ' (' + selectedCount + ')' : ''));

    const $visibleCheckboxes = getVisibleTwoFactorCheckboxes();
    const totalCheckboxes = $visibleCheckboxes.length;
    const checkedCheckboxes = $visibleCheckboxes.filter(':checked').length;
    $('#selectAll2faUsers').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
  }

  $('#selectAll2faUsers').on('change', function () {
    const isChecked = $(this).is(':checked');
    getVisibleTwoFactorCheckboxes().prop('checked', isChecked);
    syncBulkResetState();
  });

  $(document).on('change', '.twofactor-user-checkbox', syncBulkResetState);
  twoFactorTable.on('draw', syncBulkResetState);
  syncBulkResetState();

  $('#twoFactorStatusFilter').on('change', function () {
    const statusValue = String($(this).val() || '');
    const searchValue = statusValue ? '^' + $.fn.dataTable.util.escapeRegex(statusValue) + '$' : '';
    $('.twofactor-user-checkbox').prop('checked', false);
    twoFactorTable.column(4).search(searchValue, true, false).draw();
    $('#selectAll2faUsers').prop('checked', false);
    syncBulkResetState();
  });

  $('#bulkReset2faBtn').on('click', function () {
    const selectedUsers = getSelectedTwoFactorUsers();
    if (selectedUsers.length === 0) {
      Swal.fire({ icon: 'info', title: 'No Users Selected', text: 'Select at least one user before running a bulk 2FA reset.' });
      return;
    }

    const previewNames = selectedUsers.slice(0, 5).map(user => user.name || ('User #' + user.id));
    const extraCount = selectedUsers.length - previewNames.length;

    Swal.fire({
      title: 'Reset authenticator setup for selected users?',
      html: 'Selected users:<br><strong>' + previewNames.join(', ') + '</strong>' + (extraCount > 0 ? '<br><small>and ' + extraCount + ' more</small>' : '') + '<br><br>This clears each selected user\'s authenticator secret and recovery codes. They will be required to enroll again on next login.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, reset selected users'
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: 'Resetting selected users...',
        text: 'Please wait while the selected authenticator setups are reset.',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      $.ajax({
        url: 'reset_users_2fa.php',
        method: 'POST',
        dataType: 'json',
        traditional: true,
        data: {
          user_ids: selectedUsers.map(user => user.id),
          csrf_token: window.KODUS_CSRF_TOKEN
        },
        success: function (response) {
          Swal.close();
          if (response.success) {
            const emailSuccessCount = Number(response.email_success_count || 0);
            const emailFailedCount = Number(response.email_failed_count || 0);
            const emailSkippedCount = Number(response.email_skipped_count || 0);

            Swal.fire({
              icon: 'success',
              title: 'Bulk 2FA Reset Complete',
              html: response.message
                + '<br><br><small>Email notifications: '
                + emailSuccessCount + ' sent'
                + (emailFailedCount > 0 ? ', ' + emailFailedCount + ' failed' : '')
                + (emailSkippedCount > 0 ? ', ' + emailSkippedCount + ' skipped' : '')
                + '.</small>'
            }).then(() => location.reload());
          } else {
            Swal.fire({ icon: 'error', title: 'Bulk Reset Failed', text: response.message });
          }
        },
        error: function () {
          Swal.close();
          Swal.fire({ icon: 'error', title: 'Unexpected Server Response', text: 'Something went wrong. Please try again later.' });
        }
      });
    });
  });
});
</script>
</body>
</html>
