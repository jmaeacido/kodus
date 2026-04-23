<?php
include('../header.php');
include('../sidenav.php');

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
        text: 'You are not authorized to view this page.'
      }).then(() => window.location.href = '../');
    </script>";
    exit;
}

$currentVersion = password_policy_current_version();
$cutoff = password_policy_cutoff();
$minLength = security_password_min_length();

$summary = [
    'total_active' => 0,
    'needs_update' => 0,
    'notified' => 0,
    'compliant' => 0,
];

$summarySql = "
    SELECT
        COUNT(*) AS total_active,
        SUM(CASE WHEN must_change_password = 1 OR password_policy_version < ? THEN 1 ELSE 0 END) AS needs_update,
        SUM(CASE WHEN password_strength_notified_at IS NOT NULL THEN 1 ELSE 0 END) AS notified,
        SUM(CASE WHEN must_change_password = 0 AND password_policy_version >= ? THEN 1 ELSE 0 END) AS compliant
    FROM users
    WHERE deleted_at IS NULL
";
$stmt = $conn->prepare($summarySql);
$stmt->bind_param('ii', $currentVersion, $currentVersion);
$stmt->execute();
$summary = array_map('intval', db_stmt_fetch_one_assoc($stmt) ?: $summary);
$stmt->close();

$users = [];
$sql = "
    SELECT
        id,
        username,
        email,
        first_name,
        middle_name,
        last_name,
        ext,
        picture,
        sso_avatar_url,
        date_registered,
        last_login_at,
        password_policy_version,
        password_changed_at,
        must_change_password,
        password_strength_notified_at
    FROM users
    WHERE deleted_at IS NULL
    ORDER BY must_change_password DESC, password_strength_notified_at IS NULL DESC, date_registered ASC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $parts = array_filter([
            $row['first_name'] ?? '',
            $row['middle_name'] ?? '',
            $row['last_name'] ?? '',
        ]);
        $fullName = trim(ucwords(strtolower(implode(' ', $parts))));
        if (!empty($row['ext'])) {
            $fullName .= ' ' . $row['ext'];
        }

        $avatar = avatar_resolve_url($row['picture'] ?? '', $row['sso_avatar_url'] ?? '', $base_url, dirname(__DIR__));

        $needsUpdate = !empty($row['must_change_password']) || (int) ($row['password_policy_version'] ?? 0) < $currentVersion;
        $row['full_name'] = $fullName !== '' ? $fullName : (string) $row['username'];
        $row['avatar_url'] = $avatar;
        $row['needs_update'] = $needsUpdate;
        $row['status_label'] = $needsUpdate ? 'Needs update' : 'Compliant';
        $row['status_badge'] = $needsUpdate ? 'warning' : 'success';
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Password Security</title>
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <style>
    .password-security-page {
      --password-page-bg:
        radial-gradient(circle at top right, rgba(220, 53, 69, 0.12), transparent 28%),
        linear-gradient(180deg, #15202b 0%, #0f1720 100%);
      --password-panel-bg: #1f2937;
      --password-panel-soft: linear-gradient(135deg, #1f2937 0%, #243140 100%);
      --password-border: rgba(255, 255, 255, 0.09);
      --password-text: #f8f9fa;
      --password-muted: #b8c2cc;
      --password-pill-bg: rgba(220, 53, 69, 0.2);
      --password-pill-text: #ffb3bd;
      --password-avatar-border: rgba(255, 255, 255, 0.18);
      --password-avatar-bg: #2d3748;
      --password-table-head-bg: #243140;
      --password-table-head-text: #dbe7f3;
      --password-shadow: 0 16px 34px rgba(0, 0, 0, 0.22);
    }
    body[data-theme="light"] .password-security-page {
      --password-page-bg:
        radial-gradient(circle at top right, rgba(220, 53, 69, 0.08), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, #f4f6f9 100%);
      --password-panel-bg: #ffffff;
      --password-panel-soft: linear-gradient(135deg, #ffffff 0%, #fff7f8 100%);
      --password-border: rgba(15, 23, 42, 0.08);
      --password-text: #1f2d3d;
      --password-muted: #5c6773;
      --password-pill-bg: rgba(220, 53, 69, 0.1);
      --password-pill-text: #a61d2d;
      --password-avatar-border: rgba(255, 255, 255, 0.8);
      --password-avatar-bg: #e9ecef;
      --password-table-head-bg: #f7f9fc;
      --password-table-head-text: #495057;
      --password-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
    }
    .password-security-page .content-wrapper {
      background: var(--password-page-bg);
    }
    .password-security-page .security-hero,
    .password-security-page .security-note,
    .password-security-page .table-card {
      border-radius: 1rem;
      background: var(--password-panel-bg);
      border: 1px solid var(--password-border);
      box-shadow: var(--password-shadow);
      color: var(--password-text);
    }
    .password-security-page .security-hero {
      padding: 1.2rem 1.25rem;
      margin-bottom: 1rem;
      background: var(--password-panel-soft);
    }
    .password-security-page .security-hero h2 {
      margin: 0 0 0.35rem;
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--password-text);
    }
    .password-security-page .security-hero p,
    .password-security-page .security-note p {
      margin: 0;
      color: var(--password-muted);
    }
    .password-security-page .security-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border-radius: 999px;
      padding: 0.5rem 0.85rem;
      background: var(--password-pill-bg);
      color: var(--password-pill-text);
      font-weight: 700;
      font-size: 0.9rem;
      white-space: nowrap;
    }
    .password-security-page .small-box {
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    }
    .password-security-page .security-note {
      padding: 1rem 1.1rem;
      margin-bottom: 1rem;
    }
    .password-security-page .table-card .card-header {
      background: color-mix(in srgb, var(--password-panel-bg) 90%, transparent);
      border-bottom: 1px solid var(--password-border);
    }
    .password-security-page .status-badge {
      border-radius: 999px;
      padding: 0.45rem 0.7rem;
      font-size: 0.76rem;
      font-weight: 700;
    }
    .password-security-page .user-cell {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 0.55rem;
      text-align: left;
      min-width: 0;
      flex-wrap: nowrap;
      white-space: nowrap;
    }
    .password-security-page .username-cell {
      min-width: 210px;
      white-space: nowrap;
    }
    .password-security-page .username-text {
      display: inline-block;
      white-space: nowrap;
      font-size: 0.93rem;
      font-weight: 600;
      line-height: 1.2;
      vertical-align: middle;
    }
    .password-security-page .avatar-frame {
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
      border: 2px solid var(--password-avatar-border);
      box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
      background: var(--password-avatar-bg);
      line-height: 0;
    }
    .password-security-page .avatar-frame img {
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
    @media (max-width: 1600px) {
      .password-security-page .security-hero {
        padding: 1rem 1.1rem;
      }
      .password-security-page .security-note {
        padding: 0.9rem 1rem;
      }
    }
    @media (max-width: 1366px) {
      .password-security-page .security-hero h2 {
        font-size: 1.08rem;
      }
      .password-security-page .security-pill {
        padding: 0.42rem 0.75rem;
        font-size: 0.84rem;
      }
      .password-security-page .security-hero p,
      .password-security-page .security-note p,
      .password-security-page .user-meta small {
        font-size: 0.84rem;
      }
      .password-security-page .status-badge {
        padding: 0.4rem 0.62rem;
      }
    }
    @media (max-width: 1280px) {
      .password-security-page .security-hero,
      .password-security-page .security-note {
        margin-bottom: 0.85rem;
      }
      .password-security-page .username-cell {
        min-width: 180px;
      }
      .password-security-page .avatar-frame,
      .password-security-page .avatar-frame img {
        width: 32px;
        min-width: 32px;
        max-width: 32px;
        height: 32px;
        min-height: 32px;
        max-height: 32px;
      }
    }
    @media (max-width: 1024px) {
      .password-security-page .security-hero {
        padding: 0.9rem 0.95rem;
      }
      .password-security-page .table-card .card-header {
        padding: 0.85rem 0.95rem;
      }
    }
    .password-security-page .user-meta small {
      color: var(--password-muted);
      font-size: 0.78rem;
    }
    .password-security-page .action-stack {
      display: flex;
      gap: 0.45rem;
      flex-wrap: wrap;
    }
    .password-security-page .btn {
      border-radius: 999px;
      font-weight: 600;
    }
    .password-security-page .table td,
    .password-security-page .table th {
      vertical-align: middle;
    }
    .password-security-page .table thead th {
      background: var(--password-table-head-bg);
      font-size: 0.76rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--password-table-head-text);
      border-bottom: 0;
    }
  </style>
</head>
<body class="password-security-page">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Password Security</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $app_root; ?>home">Home</a></li>
              <li class="breadcrumb-item active">Password Security</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="security-hero d-flex flex-wrap justify-content-between align-items-center">
          <div>
            <h2>Monitor password-policy rollout and follow up on at-risk accounts</h2>
            <p>The app now blocks weak passwords at login and can require password resets for legacy or manually flagged accounts.</p>
          </div>
          <div class="security-pill">
            <i class="fas fa-shield-alt"></i>
            <span><?php echo (int) $summary['needs_update']; ?> accounts currently need action</span>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-danger">
              <div class="inner">
                <h3><?php echo (int) $summary['needs_update']; ?></h3>
                <p>Needs Password Update</p>
              </div>
              <div class="icon"><i class="fas fa-user-shield"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3><?php echo (int) $summary['notified']; ?></h3>
                <p>Advisories Sent</p>
              </div>
              <div class="icon"><i class="fas fa-envelope-open-text"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3><?php echo (int) $summary['compliant']; ?></h3>
                <p>Compliant Accounts</p>
              </div>
              <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <div class="small-box bg-secondary">
              <div class="inner">
                <h3><?php echo (int) $summary['total_active']; ?></h3>
                <p>Total Active Accounts</p>
              </div>
              <div class="icon"><i class="fas fa-users"></i></div>
            </div>
          </div>
        </div>

        <div class="security-note d-flex flex-wrap justify-content-between align-items-center">
          <div>
            <p><strong>Current policy:</strong> at least <?php echo htmlspecialchars((string) $minLength, ENT_QUOTES, 'UTF-8'); ?> characters with uppercase, lowercase, number, and symbol.</p>
            <p><strong>Legacy cutoff:</strong> <?php echo htmlspecialchars($cutoff, ENT_QUOTES, 'UTF-8'); ?>. Accounts before this date are auto-flagged unless they later changed to the current password-policy version.</p>
          </div>
          <div class="mt-2 mt-md-0">
            <button type="button" class="btn btn-danger" id="sendBulkRemindersBtn">
              <i class="fas fa-paper-plane mr-1"></i>Resend Reminders To All Pending Users
            </button>
            <button type="button" class="btn btn-outline-primary ml-2" id="backfillInAppNoticesBtn">
              <i class="fas fa-inbox mr-1"></i>Backfill In-App Notices
            </button>
          </div>
        </div>

        <div class="card table-card">
          <div class="card-header">
            <h3 class="card-title">Password Policy Status</h3>
            <div class="text-muted small mt-1">Use the actions below to resend notices or require a password reset for a specific user.</div>
          </div>
          <div class="card-body">
            <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1250px;">
            <table id="passwordSecurityTable" class="table table-bordered table-striped" style="width:100%;">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Email</th>
                  <th>Registered</th>
                  <th>Last Login</th>
                  <th>Status</th>
                  <th>Policy Version</th>
                  <th>Password Changed</th>
                  <th>Last Notified</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $row): ?>
                  <tr data-user-id="<?php echo (int) $row['id']; ?>">
                    <td><?php echo (int) $row['id']; ?></td>
                    <td class="username-cell">
                      <div class="user-cell">
                        <span class="avatar-frame" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;border-radius:50%;overflow:hidden;display:inline-block;vertical-align:middle;line-height:0;">
                          <img src="<?php echo htmlspecialchars((string) $row['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8'); ?>" width="36" height="36" style="width:36px;height:36px;min-width:36px;max-width:36px;min-height:36px;max-height:36px;display:block;border-radius:50%;object-fit:cover;object-position:center center;">
                        </span>
                        <span class="username-text"><?php echo htmlspecialchars((string) $row['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                      </div>
                      <div class="small text-muted ml-5">@<?php echo htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(date('M d, Y h:ia', strtotime((string) $row['date_registered'])), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo !empty($row['last_login_at']) ? htmlspecialchars(date('M d, Y h:ia', strtotime((string) $row['last_login_at'])), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Never</span>'; ?></td>
                    <td data-order="<?php echo $row['needs_update'] ? '0' : '1'; ?>">
                      <span class="badge badge-<?php echo htmlspecialchars((string) $row['status_badge'], ENT_QUOTES, 'UTF-8'); ?> status-badge"><?php echo htmlspecialchars((string) $row['status_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php if (!empty($row['must_change_password'])): ?>
                        <div class="small text-muted mt-1">Forced reset pending</div>
                      <?php endif; ?>
                    </td>
                    <td><?php echo (int) ($row['password_policy_version'] ?? 0); ?></td>
                    <td><?php echo !empty($row['password_changed_at']) ? htmlspecialchars(date('M d, Y h:ia', strtotime((string) $row['password_changed_at'])), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Unknown</span>'; ?></td>
                    <td><?php echo !empty($row['password_strength_notified_at']) ? htmlspecialchars(date('M d, Y h:ia', strtotime((string) $row['password_strength_notified_at'])), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Not yet sent</span>'; ?></td>
                    <td>
                      <div class="action-stack">
                        <button
                          type="button"
                          class="btn btn-outline-danger btn-sm force-reset-btn"
                          data-id="<?php echo (int) $row['id']; ?>"
                          data-name="<?php echo htmlspecialchars((string) $row['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          <i class="fas fa-key mr-1"></i>Require Reset
                        </button>
                        <button
                          type="button"
                          class="btn btn-outline-primary btn-sm send-reminder-btn"
                          data-id="<?php echo (int) $row['id']; ?>"
                          data-name="<?php echo htmlspecialchars((string) $row['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          <i class="fas fa-envelope mr-1"></i>Send Reminder
                        </button>
                      </div>
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

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script>
$(function () {
  function initializePasswordSecurityTable() {
    return $('#passwordSecurityTable').DataTable({
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
      columnDefs: [
        { targets: 0, visible: false, searchable: false }
      ],
      order: [[0, 'asc']]
    });
  }

  let passwordSecurityTable = initializePasswordSecurityTable();
  let passwordSecurityPartialRefreshInFlight = false;

  async function refreshPasswordSecurityView() {
    if (passwordSecurityPartialRefreshInFlight) {
      return;
    }

    passwordSecurityPartialRefreshInFlight = true;

    try {
      const response = await fetch(window.location.href, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        throw new Error('Could not refresh password security data.');
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');

      const nextPill = doc.querySelector('.security-pill span');
      const currentPill = document.querySelector('.security-pill span');
      if (nextPill && currentPill) {
        currentPill.textContent = nextPill.textContent;
      }

      const nextBoxes = doc.querySelectorAll('.small-box .inner h3');
      const currentBoxes = document.querySelectorAll('.small-box .inner h3');
      nextBoxes.forEach(function (box, index) {
        if (currentBoxes[index]) {
          currentBoxes[index].textContent = box.textContent;
        }
      });

      const nextPolicyNote = doc.querySelector('.security-note > div:first-child');
      const currentPolicyNote = document.querySelector('.security-note > div:first-child');
      if (nextPolicyNote && currentPolicyNote) {
        currentPolicyNote.innerHTML = nextPolicyNote.innerHTML;
      }

      const nextBody = doc.querySelector('#passwordSecurityTable tbody');
      const currentBody = document.querySelector('#passwordSecurityTable tbody');
      if (!nextBody || !currentBody) {
        throw new Error('Could not locate password security rows.');
      }

      passwordSecurityTable.destroy();
      currentBody.innerHTML = nextBody.innerHTML;
      passwordSecurityTable = initializePasswordSecurityTable();
    } finally {
      passwordSecurityPartialRefreshInFlight = false;
    }
  }

  function runPasswordAction(action, userId, successTitle, loadingText) {
    Swal.fire({
      title: 'Processing...',
      html: loadingText,
      icon: 'info',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    $.ajax({
      url: 'password_security_action.php',
      method: 'POST',
      dataType: 'json',
      data: {
        action: action,
        user_id: userId,
        csrf_token: window.KODUS_CSRF_TOKEN
      },
      success: function (response) {
        Swal.close();
        if (response.success) {
          Swal.fire({
            icon: 'success',
            title: successTitle,
            text: response.message
          }).then(() => {
            refreshPasswordSecurityView().catch(() => window.location.reload());
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Action Failed',
            text: response.message || 'Unable to complete the request.'
          });
        }
      },
      error: function () {
        Swal.close();
        Swal.fire({
          icon: 'error',
          title: 'Unexpected Server Response',
          text: 'Something went wrong. Please try again later.'
        });
      }
    });
  }

  $(document).on('click', '.force-reset-btn', function () {
    const userId = $(this).data('id');
    const name = $(this).data('name');

    Swal.fire({
      title: 'Require password reset?',
      html: 'KODUS will mark <strong>' + name + '</strong> for a required password reset and send a reset email.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Require Reset'
    }).then((result) => {
      if (!result.isConfirmed) return;
      runPasswordAction('force_reset', userId, 'Reset Required', 'Preparing a reset link and sending the password security email...');
    });
  });

  $(document).on('click', '.send-reminder-btn', function () {
    const userId = $(this).data('id');
    const name = $(this).data('name');

    Swal.fire({
      title: 'Send reminder email?',
      html: 'This will send a password security reminder to <strong>' + name + '</strong>.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Send Reminder'
    }).then((result) => {
      if (!result.isConfirmed) return;
      runPasswordAction('send_reminder', userId, 'Reminder Sent', 'Sending the password security reminder...');
    });
  });

  $('#sendBulkRemindersBtn').on('click', function () {
    Swal.fire({
      title: 'Resend reminders to all pending users?',
      text: 'Every account that still needs a password update will receive a fresh reminder email, even if it was already notified before.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Resend Bulk Reminders'
    }).then((result) => {
      if (!result.isConfirmed) return;
      runPasswordAction('bulk_send_pending', 0, 'Bulk Reminder Complete', 'Resending reminder emails to all pending accounts...');
    });
  });

  $('#backfillInAppNoticesBtn').on('click', function () {
    Swal.fire({
      title: 'Backfill earlier reminders into in-app mail?',
      text: 'This creates in-app password reminder messages for users who were already emailed before this feature existed. Existing in-app notices will be skipped.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Backfill Notices'
    }).then((result) => {
      if (!result.isConfirmed) return;
      runPasswordAction('backfill_in_app_notices', 0, 'Backfill Complete', 'Creating in-app password reminder notices for previously emailed users...');
    });
  });

  window.addEventListener('kodus:partial-refresh', function () {
    refreshPasswordSecurityView().catch(() => {});
  });
});
</script>
</body>
</html>
