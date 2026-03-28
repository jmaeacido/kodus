<?php
  require_once __DIR__ . '/theme_helpers.php';
  require_once __DIR__ . '/inbox/mailbox_helpers.php';
  function topbar_notification_avatar(array $row, string $baseUrl): string {
      $defaultAvatar = $baseUrl . "kodus/dist/img/default.webp";
      $picture = (string) ($row['picture'] ?? '');

      if ($picture === '') {
          return $defaultAvatar;
      }

      $filePath = __DIR__ . "/dist/img/" . $picture;
      if (!file_exists($filePath)) {
          return $defaultAvatar;
      }

      return $baseUrl . "kodus/dist/img/" . rawurlencode($picture);
  }

  function topbar_notification_time_label(?string $sentAt): string {
      if (!$sentAt) {
          return 'Unknown time';
      }

      $timestamp = strtotime($sentAt);
      if ($timestamp === false) {
          return 'Unknown time';
      }

      $seconds = time() - $timestamp;
      if ($seconds < 60) {
          return 'Just now';
      }

      $minutes = (int) floor($seconds / 60);
      if ($minutes < 60) {
          return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
      }

      $hours = (int) floor($minutes / 60);
      if ($hours < 24) {
          return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
      }

      return date("M d, H:i", $timestamp);
  }

  $current_page = basename($_SERVER['PHP_SELF']);
  $current_dir  = basename(dirname($_SERVER['PHP_SELF'])); // e.g., crossmatch or inbox
  $themePreference = theme_current_preference();
  $isDarkTheme = $themePreference === 'dark';
  $bodyThemeClass = $isDarkTheme ? 'dark-mode' : '';
  $navbarThemeClass = $isDarkTheme ? 'navbar-dark' : 'navbar-light navbar-white';
  $sidebarThemeClass = $isDarkTheme ? 'sidebar-dark-primary' : 'sidebar-light-primary';
  $themeToggleIcon = $isDarkTheme ? 'fa-sun' : 'fa-moon';
  $themeToggleLabel = $isDarkTheme ? 'Light mode' : 'Dark mode';

  mailboxEnsureSchema($conn);

  // Count unread messages
  $unreadCount = 0;

if (isset($_SESSION['user_id'])) {
    $userId   = $_SESSION['user_id'];
    $userType = $_SESSION['user_type'] ?? null;
    $userEmail = $_SESSION['email'] ?? '';

    if ($userType === 'admin') {
        $sql = "
            SELECT COUNT(*) AS unread
            FROM contact_messages cm
            LEFT JOIN message_reads mr
              ON cm.id = mr.message_id AND mr.user_id = ?
            WHERE (
                cm.user_email = ?
                OR EXISTS (
                    SELECT 1
                    FROM users u
                    WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                      AND u.userType = 'admin'
                )
            )
              AND COALESCE(mr.is_trashed, 0) = 0
              AND (mr.is_read IS NULL OR mr.is_read = 0)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $unreadCount = (int)$row['unread'];
        }
        $stmt->close();
    } else {
        // Non-admin: count only their own unread
        $userEmail = $_SESSION['email'] ?? '';
        $sql = "
            SELECT COUNT(*) AS unread
            FROM contact_messages cm
            LEFT JOIN message_reads mr 
              ON cm.id = mr.message_id AND mr.user_id = ?
            WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
              AND (mr.is_read IS NULL OR mr.is_read = 0)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userId, $userEmail, $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $unreadCount = (int)$row['unread'];
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="<?= htmlspecialchars(trim($bodyThemeClass . ' sidebar-mini layout-fixed layout-footer-fixed'), ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>" style="height: auto;">
  <style>
    .mail-alert-stack {
      position: fixed;
      top: 4.8rem;
      right: 1rem;
      bottom: auto;
      left: auto;
      z-index: 1085;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      width: min(340px, calc(100vw - 1.5rem));
      pointer-events: none;
      max-height: calc(100vh - 5.6rem);
      overflow: visible;
      transform: translateZ(0);
    }

    .mail-alert-toast {
      pointer-events: auto;
      display: flex;
      gap: 0.8rem;
      align-items: flex-start;
      padding: 0.9rem 1rem;
      border-radius: 1rem;
      background: rgba(33, 37, 41, 0.78);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.12);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.26);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      opacity: 0;
      transform: translateY(-10px);
      transition: opacity 0.22s ease, transform 0.22s ease;
      text-decoration: none;
    }

    .mail-alert-toast.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    .mail-alert-toast:hover {
      color: #fff;
      text-decoration: none;
      background: rgba(33, 37, 41, 0.88);
    }

    .mail-alert-toast img {
      width: 42px;
      height: 42px;
      border-radius: 999px;
      object-fit: cover;
      flex-shrink: 0;
      border: 2px solid rgba(255, 255, 255, 0.16);
    }

    .mail-alert-toast-copy {
      min-width: 0;
      flex: 1 1 auto;
    }

    .mail-alert-toast-title {
      font-size: 0.95rem;
      font-weight: 700;
      line-height: 1.25;
      margin-bottom: 0.2rem;
    }

    .mail-alert-toast-subject,
    .mail-alert-toast-snippet {
      margin: 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-size: 0.83rem;
    }

    .mail-alert-toast-subject {
      color: rgba(255, 255, 255, 0.94);
    }

    .mail-alert-toast-snippet,
    .mail-alert-toast-time {
      color: rgba(255, 255, 255, 0.72);
    }

    .mail-alert-toast-time {
      font-size: 0.76rem;
      margin-top: 0.3rem;
    }

    @media (max-width: 767.98px) {
      .mail-alert-stack {
        top: 4.25rem;
        right: 0.75rem;
        width: min(320px, calc(100vw - 1rem));
        max-height: calc(100vh - 4.9rem);
      }
    }
  </style>
  <script>
  (function() {
    try {
      const sidebarState = localStorage.getItem('kodus.sidebar.state');
      if (sidebarState === 'collapsed') {
        document.body.classList.add('sidebar-collapse');
      }
    } catch (error) {
      console.warn('Sidebar state restore failed.', error);
    }
  })();
  </script>

  <!-- Navbar -->
  <nav id="mainTopbar" class="main-header navbar navbar-expand <?= htmlspecialchars($navbarThemeClass, ENT_QUOTES, 'UTF-8') ?> fixed-top">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" id="sidebarToggleBtn" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?php echo $base_url; ?>kodus/home" class="nav-link">Home</a>
      </li>
      <?php if ($_SESSION['user_type'] !== 'admin'): ?>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="<?php echo $base_url; ?>kodus/inbox/?compose=1" class="nav-link">Contact Us</a>
        </li>
      <?php endif; ?>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

      <li class="nav-item dropdown">
        <a class="nav-link position-relative" data-toggle="dropdown" href="#" id="topbarChatToggle">
          <i class="far fa-comments"></i>
          <?php if ($unreadCount > 0): ?>
            <span class="badge badge-danger navbar-badge" id="topbarUnreadBadge"><?= $unreadCount ?></span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="topbarChatMenu">
          <span class="dropdown-item dropdown-header d-flex justify-content-between align-items-center">
            <span><strong>Mail Notifications</strong></span>
            <span class="text-muted small" id="topbarChatRefreshLabel">Live</span>
          </span>
          <div class="dropdown-divider"></div>
          <div id="topbarChatList">
          <?php
          if (isset($_SESSION['user_id'])) {
              $userId   = $_SESSION['user_id'];
              $userType = $_SESSION['user_type'] ?? null;
              $userEmail = $_SESSION['email'] ?? null;

              if ($userType === 'admin') {
                  // Admin: fetch latest unread messages visible to admins
                  $sql = "
                      SELECT cm.*, u.first_name, u.last_name, u.picture
                      FROM contact_messages cm
                      LEFT JOIN users u ON u.email = cm.user_email
                      LEFT JOIN message_reads mr
                        ON cm.id = mr.message_id AND mr.user_id = ?
                      WHERE (
                          cm.user_email = ?
                          OR EXISTS (
                              SELECT 1
                              FROM users ua
                              WHERE FIND_IN_SET(ua.email, cm.recipient) > 0
                                AND ua.userType = 'admin'
                          )
                      )
                      AND COALESCE(mr.is_trashed, 0) = 0
                      AND (mr.is_read IS NULL OR mr.is_read = 0)
                      ORDER BY cm.sent_at DESC
                      LIMIT 5
                  ";
                  $stmt = $conn->prepare($sql);
                  $stmt->bind_param("is", $userId, $userEmail);
              } else {
                  // Non-admin: fetch their unread messages
                  $sql = "
                      SELECT cm.*, u.first_name, u.last_name, u.picture
                      FROM contact_messages cm
                      LEFT JOIN users u ON u.email = cm.user_email
                      LEFT JOIN message_reads mr 
                        ON cm.id = mr.message_id AND mr.user_id = ?
                      WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
                        AND (mr.is_read IS NULL OR mr.is_read = 0)
                      ORDER BY cm.sent_at DESC
                      LIMIT 5
                  ";
                  $stmt = $conn->prepare($sql);
                  $stmt->bind_param("iss", $userId, $userEmail, $userEmail);
              }

              $stmt->execute();
              $result = $stmt->get_result();

              if ($result->num_rows === 0): ?>
                  <span class="dropdown-item text-center text-muted">No unread messages</span>
              <?php else:
                  while ($row = $result->fetch_assoc()):
                      $senderName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                      if ($senderName === '') {
                          $senderName = htmlspecialchars($row['user_name'] ?? 'Unknown');
                      }
                      $subject = htmlspecialchars($row['subject'] ?? '(No Subject)');
                      $snippet = htmlspecialchars(mb_strimwidth($row['message'] ?? '', 0, 40, '...'));
                      $sentAt  = topbar_notification_time_label($row['sent_at'] ?? null);
                      $avatar = topbar_notification_avatar($row, $base_url);
                  ?>
                  <a href="<?php echo $base_url;?>kodus/inbox/index.php?msg=<?= $row['id'] ?>" class="dropdown-item">
                    <div class="media">
                      <img src="<?= $avatar ?>" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                      <div class="media-body">
                        <h3 class="dropdown-item-title">
                          <?= $senderName ?>
                        </h3>
                        <p class="text-sm"><?= $subject ?></p>
                        <p class="text-sm text-muted">
                          <i class="far fa-clock mr-1"></i> <?= $sentAt ?>
                        </p>
                      </div>
                    </div>
                  </a>
                  <div class="dropdown-divider"></div>
              <?php endwhile;
              endif;
              $stmt->close();
          }
          ?>
          </div>
          <div class="dropdown-divider"></div>
          <a href="<?php echo $base_url;?>kodus/inbox/index" class="dropdown-item dropdown-footer">See All Messages</a>
        </div>
      </li>

      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
      <?php if (isset($_SESSION['user_id'])): ?>
      <li class="nav-item">
        <a class="nav-link" href="#" id="themeToggleBtn" title="<?= htmlspecialchars($themeToggleLabel, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas <?= htmlspecialchars($themeToggleIcon, ENT_QUOTES, 'UTF-8') ?>" id="themeToggleIcon"></i>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <!-- /.navbar -->
  <div id="mailAlertStack" class="mail-alert-stack" aria-live="polite" aria-atomic="true"></div>

  <!-- Main Sidebar Container -->
  <aside id="mainSidebar" class="main-sidebar <?= htmlspecialchars($sidebarThemeClass, ENT_QUOTES, 'UTF-8') ?> elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo $base_url;?>kodus" class="brand-link">
      <img src="<?php echo $base_url; ?>kodus/dist/img/kodus.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">KODUS</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?php echo $base_url; ?>kodus/dist/img/<?php echo $_SESSION['picture']; ?>" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info dropdown">
          <a href="#" class="d-block dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?php echo $_SESSION['first_name']; ?></a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>kodus/settings"><span class="nav-icon fas fa-cogs"></span> Settings</a></li>
            <li>
              <form method="post" action="<?php echo htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8'); ?>logout" class="mb-0">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="reason" value="manual">
                <button type="submit" class="dropdown-item"><span class="nav-icon fas fa-sign-out-alt"></span> Logout</button>
              </form>
            </li>
          </ul>
        </div>
      </div>

      <!-- SidebarSearch Form -->
      <div class="form-inline">
        <div class="input-group" data-widget="sidebar-search">
          <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
          <div class="input-group-append">
            <button class="btn btn-sidebar">
              <i class="fas fa-search fa-fw"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-header">Overview</li>
          <li class="nav-item">
            <a href="<?php echo $base_url; ?>kodus/home" class="nav-link <?= ($current_page == 'home.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Home
              </p>
            </a>
          </li>
          <?php
            // Set timezone to Philippine Standard Time
            date_default_timezone_set('Asia/Manila');

            // Get today's date (Philippine time)
            $today = date('Y-m-d');

            // Force MySQL to use the same timezone for date comparisons
            $conn->query("SET time_zone = '+08:00'");

            // Use clear and explicit comparison for safety
            $query = $conn->prepare("
              SELECT COUNT(*) AS event_count 
              FROM events 
              WHERE DATE(CONVERT_TZ(start, @@session.time_zone, '+08:00')) <= ? 
                AND DATE(CONVERT_TZ(end, @@session.time_zone, '+08:00')) >= ?
                AND deleted_at is NULL
            ");
            $query->bind_param("ss", $today, $today);
            $query->execute();
            $result = $query->get_result();
            $row = $result->fetch_assoc();
            $event_count = $row['event_count'] ?? 0;
          ?>
            <li class="nav-item">
              <a href="<?php echo $base_url; ?>kodus/pages/calendar" class="nav-link <?= ($current_page == 'calendar.php') ? 'active' : ''; ?>">
                <i class="nav-icon far fa-calendar-alt"></i>
                <p>
                  Calendar
                  <?php if ($event_count !== 0):?>
                  <span class="badge badge-info right"><?php echo $event_count; ?></span>
                  <?php endif;?>
                </p>
              </a>
            </li>
          <?php ; ?>
          <li class="nav-header">Operations</li>
          <li class="nav-item <?= in_array($current_page, ['data-tracking.php', 'data-tracking-meb_.php', 'data-tracking-meb.php', 'data-tracking-meb-edit.php', 'data-tracking-meb-validation.php', 'data-tracking-in.php', 'data-tracking-out.php', 'payout.php']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_page, ['data-tracking.php', 'data-tracking-meb.php', 'data-tracking-meb_.php', 'data-tracking-meb-edit.php', 'data-tracking-meb-validation.php', 'data-tracking-in.php', 'data-tracking-out.php', 'payout.php']) ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-edit"></i>
              <p>
                Tracking
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <!-- <li class="nav-item">
                <a href="pages/data-tracking" class="nav-link <?= ($current_page == 'data-tracking.php') ? 'active' : ''; ?>">
                  <i class="fa fa-table nav-icon"></i>
                  <p>Documents</p>
                </a>
              </li> -->
              <li class="nav-item <?= in_array($current_page, ['data-tracking-meb_.php','data-tracking-meb.php', 'data-tracking-meb-edit.php', 'data-tracking-meb-validation.php']) ? 'menu-open' : ''; ?>">
                <a href="#" class="nav-link <?= in_array($current_page, ['data-tracking-meb.php', 'data-tracking-meb_.php', 'data-tracking-meb-edit.php', 'data-tracking-meb-validation.php']) ? 'active' : ''; ?>">
                  <i class="fa fa-users nav-icon"></i>
                  <p>
                    Partner-Beneficiaries
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?= $base_url . 'kodus/pages/data-tracking-meb' ?>" class="nav-link <?= in_array($current_page, ['data-tracking-meb_.php','data-tracking-meb.php', 'data-tracking-meb-edit.php']) ? 'active' : ''; ?>">
                      <i class="fa fa-list nav-icon"></i>
                      <p>MEB</p>
                    </a>
                  </li>
                  <?php if ($_SESSION['user_type'] == 'admin'): ?>
                  <li class="nav-item">
                    <a href="<?php echo $base_url; ?>kodus/pages/data-tracking-meb-validation" class="nav-link <?= ($current_page == 'data-tracking-meb-validation.php') ? 'active' : ''; ?>">
                      <i class="fa fa-check-square nav-icon"></i>
                      <p>Validation</p>
                    </a>
                  </li>
                  <?php endif; ?>
                </ul>
              </li>
              <li class="nav-item">
                <a href="<?php echo $base_url; ?>kodus/pages/data-tracking-in" class="nav-link <?= ($current_page == 'data-tracking-in.php') ? 'active' : ''; ?>">
                  <i class="fa fa-sign-in-alt nav-icon"></i>
                  <p>Incoming</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $base_url; ?>kodus/pages/data-tracking-out" class="nav-link <?= ($current_page == 'data-tracking-out.php') ? 'active' : ''; ?>">
                  <i class="fa fa-sign-out-alt nav-icon"></i>
                  <p>Outgoing</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $base_url; ?>kodus/pages/payout" class="nav-link <?= ($current_page == 'payout.php') ? 'active' : ''; ?>">
                  <i class="fa fa-money-bill-wave nav-icon"></i>
                  <p>Payout</p>
                </a>
              </li>
            </ul>
          </li>
          <li class="nav-header">Reporting</li>
          <li class="nav-item <?= in_array($current_page, ['program-activities.php', 'program-targets.php', 'php_page_.php']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_page, ['program-activities.php', 'program-targets.php', 'php_page.php']) ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-project-diagram"></i>
              <p>
                Implementation Status
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <!-- <li class="nav-item">
                <a href="pages/data-tracking" class="nav-link <?= ($current_page == 'data-tracking.php') ? 'active' : ''; ?>">
                  <i class="fa fa-table nav-icon"></i>
                  <p>Documents</p>
                </a>
              </li> -->
              <?php if ($_SESSION['user_type'] == 'admin'): ?>
              <li class="nav-item">
                <a href="<?php echo $base_url; ?>kodus/implementation-status/program-targets" class="nav-link <?= ($current_page == 'program-targets.php') ? 'active' : ''; ?>">
                  <i class="far fa-dot-circle nav-icon"></i>
                  <p>Baseline Targets</p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="<?php echo $base_url; ?>kodus/implementation-status/program-activities" class="nav-link <?= ($current_page == 'program-activities.php') ? 'active' : ''; ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Program Activities</p>
                </a>
              </li>
            </ul>
          </li>
          <li class="nav-item <?= in_array($current_page, ['beneficiary-profile.php', 'sectoral.php', 'pwd.php', 'sex-disaggregated-pwd.php']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_page, ['beneficiary-profile.php', 'sectoral.php', 'pwd.php', 'sex-disaggregated-pwd.php']) ? 'active' : ''; ?>">
              <i class="fas fa-chart-bar nav-icon"></i>
              <p>
                Reports
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="<?= $base_url; ?>kodus/pages/summary/beneficiary-profile" class="nav-link <?= ($current_page == 'beneficiary-profile.php') ? 'active' : ''; ?>">
                  <i class="fas fa-users nav-icon"></i>
                  <p>Partner-Beneficiaries Profile</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?= $base_url; ?>kodus/pages/summary/sectoral" class="nav-link <?= ($current_page == 'sectoral.php') ? 'active' : ''; ?>">
                  <i class="fas fa-columns nav-icon"></i>
                  <p>Sectoral Data Summary</p>
                </a>
              </li>
              <li class="nav-item <?= in_array($current_page, ['pwd.php', 'sex-disaggregated-pwd.php']) ? 'menu-open' : ''; ?>">
                <a href="#" class="nav-link <?= in_array($current_page, ['pwd.php', 'sex-disaggregated-pwd.php']) ? 'active' : ''; ?>">
                  <i class="fas fa-wheelchair nav-icon"></i>
                  <p>
                    Persons with Disability
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?= $base_url; ?>kodus/pages/summary/pwd/pwd" class="nav-link <?= ($current_page == 'pwd.php') ? 'active' : ''; ?>">
                      <i class="fas fa-columns nav-icon"></i>
                      <p>Disabilities Summary</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?= $base_url; ?>kodus/pages/summary/pwd/sex-disaggregated-pwd" class="nav-link <?= ($current_page == 'sex-disaggregated-pwd.php') ? 'active' : ''; ?>">
                      <i class="fas fa-transgender-alt nav-icon"></i>
                      <p>PWD Sex Disaggregation</p>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
          <li class="nav-header">Utilities</li>
          <li class="nav-item <?= in_array($current_dir, ['crossmatch', 'deduplication']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_dir, ['crossmatch', 'deduplication']) ? 'active' : ''; ?>">
              <i class="fas fa-wrench nav-icon"></i>
              <p>
                Tools
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="<?= $base_url; ?>kodus/crossmatch/" class="nav-link <?= ($current_dir == 'crossmatch') ? 'active' : ''; ?>">
                  <i class="nav-icon fas fa-exchange-alt"></i>
                  <p>Crossmatching</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?= $base_url; ?>kodus/deduplication/" class="nav-link <?= ($current_dir == 'deduplication') ? 'active' : ''; ?>">
                  <span class="fa-stack nav-icon">
                    <i class="fas fa-clone fa-stack-1x"></i>
                    <i class="fas fa-slash fa-stack-1x text-danger"></i>
                  </span>
                  <p>Deduplication</p>
                </a>
              </li>
            </ul>
          </li>
          <!-- <?php if ($_SESSION['user_type'] == 'admin'): ?>
          <li class="nav-item">
            <a href="<?php echo $base_url; ?>kodus/crossmatch/" class="nav-link <?= ($current_dir == 'crossmatch') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-exchange-alt"></i>
              <p>
                Crossmatching
              </p>
            </a>
          </li>
          <?php endif; ?>
          <?php if ($_SESSION['user_type'] == 'admin'): ?>
          <li class="nav-item">
            <a href="<?php echo $base_url; ?>kodus/deduplication/" class="nav-link <?= ($current_dir == 'deduplication') ? 'active' : ''; ?>">
              <span class="fa-stack nav-icon">
                <i class="fas fa-clone fa-stack-1x"></i>
                <i class="fas fa-slash fa-stack-1x text-danger"></i>
              </span>
              <p>
                Deduplication
              </p>
            </a>
          </li>
          <?php endif; ?> -->
          <?php if ($_SESSION['user_type'] == 'admin'): ?>
          <li class="nav-header">Administration</li>
          <li class="nav-item <?= in_array($current_page, ['restore_users.php', 'classify_users.php', 'deactivate_users.php', 'users_management.php']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_page, ['restore_users.php', 'classify_users.php', 'deactivate_users.php', 'users_management.php']) ? 'active' : ''; ?>">
              <i class="fas fa-users nav-icon"></i>
              <p>
                Users Management
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="<?= $base_url; ?>kodus/admin/users_management" class="nav-link <?= in_array($current_page, ['restore_users.php', 'classify_users.php', 'deactivate_users.php', 'users_management.php']) ? 'active' : ''; ?>">
                  <i class="fas fa-user-cog nav-icon"></i>
                  <p>Users Management</p>
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>
          <li class="nav-header">Workspace</li>
          <li class="nav-item">
            <a href="<?php echo $base_url; ?>kodus/inbox/" class="nav-link <?= ($current_dir == 'inbox' || $current_page == 'contact.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-envelope"></i>
              <p>
                Mail
                <?php if ($unreadCount > 0): ?>
                  <span class="right badge badge-danger" id="sidebarMailUnreadBadge"><?= $unreadCount ?></span>
                <?php endif; ?>
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo $base_url; ?>kodus/settings" class="nav-link <?= ($current_page == 'settings.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-cogs"></i>
              <p>
                Settings
              </p>
            </a>
          </li>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <script src="<?php echo $base_url;?>kodus/cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
let topbarNotificationInitialized = false;
let topbarSeenNotificationIds = [];
let mailBellAudioContext = null;
let mailBellUnlocked = false;

function unlockMailBell() {
  if (mailBellUnlocked) {
    return;
  }

  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) {
    return;
  }

  try {
    mailBellAudioContext = mailBellAudioContext || new AudioContextClass();
    if (mailBellAudioContext.state === 'suspended') {
      mailBellAudioContext.resume();
    }
    mailBellUnlocked = true;
  } catch (error) {
    console.warn('Mail bell audio unlock failed.', error);
  }
}

function playMailBell() {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) {
    return;
  }

  try {
    mailBellAudioContext = mailBellAudioContext || new AudioContextClass();
    if (mailBellAudioContext.state === 'suspended') {
      return;
    }

    const now = mailBellAudioContext.currentTime;
    const oscillator = mailBellAudioContext.createOscillator();
    const gainNode = mailBellAudioContext.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(987.77, now);
    oscillator.frequency.exponentialRampToValueAtTime(1318.51, now + 0.08);
    gainNode.gain.setValueAtTime(0.0001, now);
    gainNode.gain.exponentialRampToValueAtTime(0.08, now + 0.02);
    gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.5);

    oscillator.connect(gainNode);
    gainNode.connect(mailBellAudioContext.destination);
    oscillator.start(now);
    oscillator.stop(now + 0.52);
  } catch (error) {
    console.warn('Mail bell playback failed.', error);
  }
}

function showMailAlert(item) {
  const stack = document.getElementById('mailAlertStack');
  if (!stack || !item) {
    return;
  }

  const toast = document.createElement('a');
  toast.className = 'mail-alert-toast';
  toast.href = item.url || '<?= $base_url; ?>kodus/inbox/';
  toast.innerHTML = `
    <img src="${escapeHtml(item.avatar || '')}" alt="">
    <div class="mail-alert-toast-copy">
      <div class="mail-alert-toast-title">${escapeHtml(item.sender || 'New message')}</div>
      <p class="mail-alert-toast-subject">${escapeHtml(item.subject || '(No Subject)')}</p>
      <p class="mail-alert-toast-snippet">${escapeHtml(item.snippet || 'You have a new unread message.')}</p>
      <div class="mail-alert-toast-time">${escapeHtml(item.sent_label || 'Just now')}</div>
    </div>
  `;

  stack.prepend(toast);
  requestAnimationFrame(() => toast.classList.add('is-visible'));

  const dismissToast = () => {
    toast.classList.remove('is-visible');
    window.setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 220);
  };

  let timer = window.setTimeout(dismissToast, 5000);
  toast.addEventListener('mouseenter', () => {
    window.clearTimeout(timer);
  });
  toast.addEventListener('mouseleave', () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(dismissToast, 1800);
  });
  toast.addEventListener('click', dismissToast, { once: true });
}

function refreshUnreadCount() {
  fetch("<?= $base_url; ?>kodus/inbox/get_notification_feed.php")
    .then(res => res.json())
    .then(data => {
      const badge = document.getElementById("topbarUnreadBadge");
      let sidebarBadge = document.getElementById("sidebarMailUnreadBadge");
      const sidebarLabel = document.querySelector('a[href$="kodus/inbox/"] p');
      const toggle = document.getElementById("topbarChatToggle");
      const list = document.getElementById("topbarChatList");
      const label = document.getElementById("topbarChatRefreshLabel");
      const count = Number(data.count || 0);
      const items = Array.isArray(data.items) ? data.items : [];
      const itemIds = items.map(item => Number(item.id || 0)).filter(id => id > 0);
      const unseenItems = topbarNotificationInitialized
        ? items.filter(item => {
            const id = Number(item.id || 0);
            return id > 0 && !topbarSeenNotificationIds.includes(id);
          })
        : [];

      if (badge) {
        if (count > 0) {
          badge.textContent = count;
          badge.style.display = "inline-block";
        } else {
          badge.textContent = "";
          badge.style.display = "none";
        }
      } else if (count > 0 && toggle) {
        const newBadge = document.createElement("span");
        newBadge.className = "badge badge-danger navbar-badge";
        newBadge.id = "topbarUnreadBadge";
        newBadge.textContent = count;
        toggle.appendChild(newBadge);
      }

      if (sidebarBadge) {
        if (count > 0) {
          sidebarBadge.textContent = count;
          sidebarBadge.style.display = "inline-block";
        } else {
          sidebarBadge.remove();
          sidebarBadge = null;
        }
      } else if (count > 0 && sidebarLabel) {
        const newSidebarBadge = document.createElement("span");
        newSidebarBadge.className = "right badge badge-danger";
        newSidebarBadge.id = "sidebarMailUnreadBadge";
        newSidebarBadge.textContent = count;
        sidebarLabel.appendChild(newSidebarBadge);
      }

      if (toggle) {
        toggle.classList.toggle("text-warning", count > 0);
      }

      if (list) {
        if (items.length === 0) {
          list.innerHTML = '<span class="dropdown-item text-center text-muted">No unread messages</span>';
        } else {
          list.innerHTML = items.map(item => `
            <a href="${item.url}" class="dropdown-item">
              <div class="media">
                <img src="${item.avatar}" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                <div class="media-body">
                  <h3 class="dropdown-item-title">${escapeHtml(item.sender)}</h3>
                  <p class="text-sm mb-1">${escapeHtml(item.subject)}</p>
                  <p class="text-sm text-muted mb-1">${escapeHtml(item.snippet)}</p>
                  <p class="text-sm text-muted">
                    <i class="far fa-clock mr-1"></i> ${escapeHtml(item.sent_label)}
                  </p>
                </div>
              </div>
            </a>
            <div class="dropdown-divider"></div>
          `).join("");
        }
      }

      if (label) {
        label.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
      }

      if (unseenItems.length > 0) {
        playMailBell();
        showMailAlert(unseenItems[0]);
      }

      topbarSeenNotificationIds = itemIds.slice(0, 20);
      topbarNotificationInitialized = true;
    })
    .catch(err => console.error("Unread count fetch failed:", err));
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value == null ? '' : String(value);
  return div.innerHTML;
}

function applyAppTheme(theme) {
  const normalized = theme === 'light' ? 'light' : 'dark';
  const body = document.body;
  const topbar = document.getElementById('mainTopbar');
  const sidebar = document.getElementById('mainSidebar');
  const icon = document.getElementById('themeToggleIcon');
  const toggle = document.getElementById('themeToggleBtn');

  body.classList.toggle('dark-mode', normalized === 'dark');
  body.dataset.theme = normalized;

  if (topbar) {
    topbar.classList.remove('navbar-dark', 'navbar-light', 'navbar-white');
    if (normalized === 'dark') {
      topbar.classList.add('navbar-dark');
    } else {
      topbar.classList.add('navbar-light', 'navbar-white');
    }
  }

  if (sidebar) {
    sidebar.classList.remove('sidebar-dark-primary', 'sidebar-light-primary');
    sidebar.classList.add(normalized === 'dark' ? 'sidebar-dark-primary' : 'sidebar-light-primary');
  }

  if (icon) {
    icon.classList.remove('fa-sun', 'fa-moon');
    icon.classList.add(normalized === 'dark' ? 'fa-sun' : 'fa-moon');
  }

  if (toggle) {
    toggle.title = normalized === 'dark' ? 'Light mode' : 'Dark mode';
  }
}

// Run once and then every 30s
refreshUnreadCount();
setInterval(refreshUnreadCount, 15000);
document.addEventListener('click', unlockMailBell, { passive: true });
document.addEventListener('keydown', unlockMailBell, { passive: true });
document.addEventListener('touchstart', unlockMailBell, { passive: true });

const themeToggleBtn = document.getElementById('themeToggleBtn');
const pushMenuToggle = document.getElementById('sidebarToggleBtn');
const mainSidebar = document.getElementById('mainSidebar');
const SIDEBAR_AUTO_COLLAPSE_BREAKPOINT = 1199.98;
const SIDEBAR_OVERLAY_BREAKPOINT = 991.98;
let wasCompactSidebarViewport = window.innerWidth <= SIDEBAR_AUTO_COLLAPSE_BREAKPOINT;

function saveSidebarState(state) {
  try {
    localStorage.setItem('kodus.sidebar.state', state);
  } catch (error) {
    console.warn('Sidebar state save failed.', error);
  }
}

function applySidebarState(state) {
  const body = document.body;
  const normalized = state === 'collapsed' ? 'collapsed' : 'expanded';
  const isOverlayViewport = window.innerWidth <= SIDEBAR_OVERLAY_BREAKPOINT;

  body.classList.remove('sidebar-open', 'sidebar-closed', 'sidebar-is-opening');

  if (isOverlayViewport) {
    body.classList.toggle('sidebar-collapse', normalized === 'collapsed');
    body.classList.toggle('sidebar-open', normalized === 'expanded');
  } else {
    body.classList.toggle('sidebar-collapse', normalized === 'collapsed');
  }

  saveSidebarState(normalized);
}

function syncSidebarForViewport() {
  const isCompactViewport = window.innerWidth <= SIDEBAR_AUTO_COLLAPSE_BREAKPOINT;

  if (isCompactViewport && !wasCompactSidebarViewport) {
    applySidebarState('collapsed');
  }

  wasCompactSidebarViewport = isCompactViewport;
}

function shouldAutoCollapseSidebarFromClick(target) {
  if (!target || !isCompactViewport()) {
    return false;
  }

  const body = document.body;
  if (!body || (body.classList.contains('sidebar-collapse') && !body.classList.contains('sidebar-open'))) {
    return false;
  }

  if (pushMenuToggle && pushMenuToggle.contains(target)) {
    return false;
  }

  if (mainSidebar && mainSidebar.contains(target)) {
    return false;
  }

  return true;
}

function isCompactViewport() {
  return window.innerWidth <= SIDEBAR_AUTO_COLLAPSE_BREAKPOINT;
}

if (pushMenuToggle) {
  pushMenuToggle.addEventListener('click', function(event) {
    event.preventDefault();
    const nextState = document.body.classList.contains('sidebar-collapse') ? 'expanded' : 'collapsed';
    applySidebarState(nextState);
  });

  window.addEventListener('load', function() {
    try {
      if (isCompactViewport()) {
        applySidebarState('collapsed');
      } else {
        applySidebarState(localStorage.getItem('kodus.sidebar.state') === 'collapsed' ? 'collapsed' : 'expanded');
      }
    } catch (error) {
      applySidebarState(isCompactViewport() ? 'collapsed' : 'expanded');
    }

    wasCompactSidebarViewport = isCompactViewport();
  });

  window.addEventListener('resize', syncSidebarForViewport);

  document.addEventListener('click', function(event) {
    if (shouldAutoCollapseSidebarFromClick(event.target)) {
      applySidebarState('collapsed');
    }
  });
}

if (themeToggleBtn) {
  themeToggleBtn.addEventListener('click', function(e) {
    e.preventDefault();
    const currentTheme = document.body.dataset.theme === 'light' ? 'light' : 'dark';
    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

    applyAppTheme(nextTheme);

    fetch("<?= $base_url; ?>kodus/save_theme_preference.php", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: new URLSearchParams({
        theme_preference: nextTheme,
        csrf_token: window.KODUS_CSRF_TOKEN
      }).toString()
    }).then(res => res.json())
      .then(data => {
        if (!data.success) {
          applyAppTheme(currentTheme);
        }
      })
      .catch(() => {
        applyAppTheme(currentTheme);
      });
  });
}
</script>
</body>
</html>
