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

function user_management_avatar_url(?string $picture, string $baseUrl): string
{
    $defaultAvatar = $baseUrl . 'kodus/dist/img/default.webp';
    $picture = (string) $picture;

    if ($picture === '') {
        return $defaultAvatar;
    }

    $filePath = __DIR__ . '/../dist/img/' . $picture;
    if (!file_exists($filePath)) {
        return $defaultAvatar;
    }

    return $baseUrl . 'kodus/dist/img/' . rawurlencode($picture);
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
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
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
    SELECT id, username, email, userType, first_name, middle_name, last_name, ext, picture, date_registered, last_activity, is_online
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

if ($activeUsersResult) {
    while ($row = $activeUsersResult->fetch_assoc()) {
        $row['full_name'] = user_management_full_name($row);
        $row['avatar_url'] = user_management_avatar_url($row['picture'] ?? '', $base_url);
        $row['presence'] = classify_user_presence_um($row['last_activity'], (int) ($row['is_online'] ?? 0));
        $statusSummary[$row['presence']['label']]++;
        $activeUsers[] = $row;
    }
}

$deletedUsersResult = $conn->query("
    SELECT id, username, email, first_name, middle_name, last_name, ext, picture, deleted_at
    FROM users
    WHERE deleted_at IS NOT NULL
    ORDER BY id ASC
");

$deletedUsers = [];
if ($deletedUsersResult) {
    while ($row = $deletedUsersResult->fetch_assoc()) {
        $row['full_name'] = user_management_full_name($row);
        $row['avatar_url'] = user_management_avatar_url($row['picture'] ?? '', $base_url);
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
  <link rel="stylesheet" href="<?php echo $base_url; ?>kodus/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url; ?>kodus/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <style>
    .users-management-page .content-wrapper {
      background:
        radial-gradient(circle at top right, rgba(0, 123, 255, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, #f4f6f9 100%);
    }
    .users-management-page .management-hero {
      border-radius: 1rem;
      background: linear-gradient(135deg, #ffffff 0%, #f4f8ff 100%);
      border: 1px solid rgba(13, 110, 253, 0.12);
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
      padding: 1.15rem 1.25rem;
      margin-bottom: 1rem;
    }
    .users-management-page .management-hero h2 {
      font-size: 1.2rem;
      margin: 0 0 0.35rem;
      font-weight: 700;
      color: #1f2d3d;
    }
    .users-management-page .management-hero p {
      margin: 0;
      color: #5c6773;
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
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    }
    .users-management-page .small-box .inner p {
      font-weight: 600;
      letter-spacing: 0.01em;
    }
    .users-management-page .summary-note {
      border-radius: 0.95rem;
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(31, 45, 61, 0.08);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
      padding: 0.95rem 1rem;
      color: #495057;
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
      color: #1f2d3d;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      border: 1px solid rgba(0, 123, 255, 0.14);
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
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
      color: #0b63ce;
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
      background: rgba(0, 123, 255, 0.09);
      color: #0b63ce;
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
      color: #6c757d;
      margin-top: 0.3rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .users-management-page .nav-pills .nav-link.active .tab-link-subtitle {
      color: rgba(255, 255, 255, 0.82);
    }
    .users-management-page .tab-pane-card {
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 1rem;
      overflow: hidden;
      background: rgba(255,255,255,0.98);
      box-shadow: 0 18px 34px rgba(15, 23, 42, 0.08);
    }
    .users-management-page .tab-pane-card .card-header {
      background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(255, 255, 255, 0.98));
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
      padding: 1rem 1.15rem;
    }
    .users-management-page .section-caption {
      margin-top: 0.25rem;
      color: #6c757d;
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
      border: 2px solid rgba(255, 255, 255, 0.8);
      box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
      background: #e9ecef;
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
      color: #6c757d;
      font-size: 0.78rem;
    }
    .users-management-page .table thead th {
      background: #f7f9fc;
      border-bottom: 0;
      color: #495057;
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
      background-color: rgba(0, 123, 255, 0.04);
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
<body class="bg-light users-management-page">
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
              <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>kodus/home">Home</a></li>
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

          <div class="tab-pane fade" id="restoration" role="tabpanel">
            <div class="card tab-pane-card">
              <div class="card-header">
                <h3 class="card-title">Restore Soft-Deleted Users</h3>
                <br>
                <div class="section-caption">Bring back deactivated accounts and return them to the active user list.</div>
              </div>
              <div class="card-body">
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
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $base_url; ?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $base_url; ?>kodus/dist/js/adminlte.min.js"></script>
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
    responsive: true,
    pageLength: 10,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    order: []
  });
}

$(document).ready(function () {
  const classificationTable = initUsersManagementTable('#classificationTable');
  initUsersManagementTable('#deactivateTable');
  initUsersManagementTable('#restorationTable');

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

  setInterval(function () {
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
  }, 10000);

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
});
</script>
</body>
</html>
