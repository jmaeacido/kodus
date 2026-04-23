<?php
  require_once __DIR__ . '/theme_helpers.php';
  require_once __DIR__ . '/inbox/mailbox_helpers.php';
  require_once __DIR__ . '/profile_completion_helpers.php';
  require_once __DIR__ . '/app_notification_helpers.php';
  function topbar_notification_avatar(array $row, string $baseUrl): string {
      return avatar_resolve_url($row['picture'] ?? '', $row['sso_avatar_url'] ?? '', $baseUrl, __DIR__);
  }

  function current_user_avatar_url(string $baseUrl): string {
      return avatar_resolve_url($_SESSION['picture'] ?? '', $_SESSION['sso_avatar_url'] ?? '', $baseUrl, __DIR__);
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
  app_notification_ensure_schema($conn);

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
                    FROM contact_message_recipients cmr
                    INNER JOIN users u ON u.id = cmr.user_id
                    WHERE cmr.message_id = cm.id
                      AND u.userType = 'admin'
                )
            )
              AND COALESCE(mr.is_trashed, 0) = 0
              AND (mr.is_read IS NULL OR mr.is_read = 0)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $userEmail);
        $stmt->execute();
        if ($row = db_stmt_fetch_one_assoc($stmt)) {
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
            WHERE (cm.user_email = ? OR EXISTS (
                SELECT 1
                FROM contact_message_recipients cmr
                WHERE cmr.message_id = cm.id
                  AND LOWER(cmr.recipient_email) = LOWER(?)
            ))
              AND (mr.is_read IS NULL OR mr.is_read = 0)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userId, $userEmail, $userEmail);
        $stmt->execute();
        if ($row = db_stmt_fetch_one_assoc($stmt)) {
            $unreadCount = (int)$row['unread'];
        }
        $stmt->close();
    }
}

$settingsNeedsAttention = false;
$maintenanceModeEnabled = false;
$topbarAppNotificationFeed = [
    'count' => 0,
    'items' => [],
];
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $settingsUserId = (int) $_SESSION['user_id'];
    $settingsStmt = $conn->prepare('SELECT first_name, last_name, position, positionAbr, area, email, username FROM users WHERE id = ? LIMIT 1');
    if ($settingsStmt) {
        $settingsStmt->bind_param('i', $settingsUserId);
        $settingsStmt->execute();
        $settingsUser = db_stmt_fetch_one_assoc($settingsStmt) ?: [];
        $settingsStmt->close();

        $settingsNeedsAttention = kodus_profile_completion_status($settingsUser)['needs_attention'];
    }

    if (function_exists('kodus_maintenance_is_enabled') && ($_SESSION['user_type'] ?? '') === 'admin') {
        $maintenanceModeEnabled = kodus_maintenance_is_enabled($conn);
    }

    $topbarAppNotificationFeed = app_notification_get_feed($conn, $settingsUserId, 8);
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

    body[data-theme="light"] .mail-alert-toast {
      background: rgba(255, 255, 255, 0.96);
      color: #1f2937;
      border-color: rgba(15, 23, 42, 0.08);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.14);
    }

    body[data-theme="light"] .mail-alert-toast:hover {
      color: #1f2937;
      background: #ffffff;
    }

    body[data-theme="light"] .mail-alert-toast img {
      border-color: rgba(15, 23, 42, 0.1);
    }

    body[data-theme="light"] .mail-alert-toast-subject {
      color: #1f2937;
    }

    body[data-theme="light"] .mail-alert-toast-snippet,
    body[data-theme="light"] .mail-alert-toast-time {
      color: #64748b;
    }

    @media (max-width: 767.98px) {
      .mail-alert-stack {
        top: 4.25rem;
        right: 0.75rem;
        width: min(320px, calc(100vw - 1rem));
        max-height: calc(100vh - 4.9rem);
      }
    }

    .app-alert-stack {
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

    .app-alert-toast {
      pointer-events: auto;
      display: flex;
      gap: 0.8rem;
      align-items: flex-start;
      padding: 0.9rem 1rem;
      border-radius: 1rem;
      background: rgba(17, 24, 39, 0.84);
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

    .app-alert-toast.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    .app-alert-toast:hover {
      color: #fff;
      text-decoration: none;
      background: rgba(17, 24, 39, 0.94);
    }

    .app-alert-toast-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 42px;
      height: 42px;
      border-radius: 999px;
      background: rgba(245, 158, 11, 0.18);
      color: #fbbf24;
      flex-shrink: 0;
      border: 1px solid rgba(251, 191, 36, 0.28);
    }

    .app-alert-toast-copy {
      min-width: 0;
      flex: 1 1 auto;
    }

    .app-alert-toast-title {
      font-size: 0.95rem;
      font-weight: 700;
      line-height: 1.25;
      margin-bottom: 0.15rem;
    }

    .app-alert-toast-message,
    .app-alert-toast-time {
      margin: 0;
      color: rgba(255, 255, 255, 0.74);
      font-size: 0.82rem;
      line-height: 1.45;
    }

    .app-alert-toast-time {
      margin-top: 0.3rem;
      font-size: 0.76rem;
    }

    body[data-theme="light"] .app-alert-toast {
      background: rgba(255, 255, 255, 0.97);
      color: #0f172a;
      border-color: rgba(15, 23, 42, 0.08);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.14);
    }

    body[data-theme="light"] .app-alert-toast:hover {
      color: #0f172a;
      background: #ffffff;
    }

    body[data-theme="light"] .app-alert-toast-icon {
      background: rgba(245, 158, 11, 0.12);
      color: #b45309;
      border-color: rgba(180, 83, 9, 0.15);
    }

    body[data-theme="light"] .app-alert-toast-message,
    body[data-theme="light"] .app-alert-toast-time {
      color: #64748b;
    }

    .app-notification-item .media {
      align-items: flex-start;
    }

    .app-notification-item.is-unread {
      background: rgba(13, 110, 253, 0.06);
    }

    #topbarNotificationMenu {
      min-width: min(24rem, calc(100vw - 1rem));
    }

    .app-notification-list {
      max-height: min(24rem, calc(100vh - 12rem));
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }

    .app-notification-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.65rem 1rem;
    }

    .app-notification-action {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0;
      border: 0;
      background: none;
      font-size: 0.82rem;
      font-weight: 600;
      color: #0d6efd;
      cursor: pointer;
    }

    .app-notification-action[disabled] {
      color: #94a3b8;
      cursor: default;
    }

    .app-notification-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 46px;
      height: 46px;
      border-radius: 999px;
      background: rgba(245, 158, 11, 0.14);
      color: #f59e0b;
      flex-shrink: 0;
    }

    .app-notification-meta {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      flex-wrap: wrap;
      margin-top: 0.3rem;
      font-size: 0.75rem;
      color: #6c757d;
    }

    @media (max-width: 767.98px) {
      .app-alert-stack {
        top: 8.85rem;
        right: 0.75rem;
        width: min(320px, calc(100vw - 1rem));
        max-height: calc(100vh - 9.4rem);
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
        <a href="<?php echo $app_root; ?>home" class="nav-link">Home</a>
      </li>
      <?php if ($_SESSION['user_type'] !== 'admin'): ?>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="<?php echo $app_root; ?>inbox/?compose=1" class="nav-link">Contact Us</a>
        </li>
      <?php endif; ?>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

      <li class="nav-item dropdown">
        <a class="nav-link position-relative" data-toggle="dropdown" href="#" id="topbarNotificationToggle">
          <i class="far fa-bell"></i>
          <?php if (($topbarAppNotificationFeed['count'] ?? 0) > 0): ?>
            <span class="badge badge-warning navbar-badge" id="topbarNotificationBadge"><?= (int) $topbarAppNotificationFeed['count'] ?></span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="topbarNotificationMenu">
          <span class="dropdown-item dropdown-header d-flex justify-content-between align-items-center">
            <span><strong>Transaction Notifications</strong></span>
            <span class="text-muted small" id="topbarNotificationRefreshLabel">Live</span>
          </span>
          <div class="dropdown-divider"></div>
          <div class="app-notification-actions">
            <button type="button" class="app-notification-action" id="topbarNotificationMarkAllButton"<?= ($topbarAppNotificationFeed['count'] ?? 0) > 0 ? '' : ' disabled' ?>>
              <i class="fas fa-check-double"></i>
              <span>Mark All as Read</span>
            </button>
          </div>
          <div class="dropdown-divider"></div>
          <div id="topbarNotificationList" class="app-notification-list">
          <?php if (($topbarAppNotificationFeed['items'] ?? []) === []): ?>
            <span class="dropdown-item text-center text-muted">No new notifications</span>
          <?php else: ?>
            <?php foreach (($topbarAppNotificationFeed['items'] ?? []) as $notificationItem): ?>
              <a href="<?= htmlspecialchars((string) (($notificationItem['url'] ?? '') !== '' ? $notificationItem['url'] : ($app_root . 'home')), ENT_QUOTES, 'UTF-8') ?>" class="dropdown-item app-notification-item<?= !empty($notificationItem['is_unread']) ? ' is-unread' : '' ?>" data-notification-id="<?= (int) ($notificationItem['id'] ?? 0) ?>">
                <div class="media">
                  <span class="app-notification-icon mr-3">
                    <i class="<?= htmlspecialchars((string) ($notificationItem['icon_class'] ?? 'fas fa-bell'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($notificationItem['color_class'] ?? 'text-warning'), ENT_QUOTES, 'UTF-8') ?>"></i>
                  </span>
                  <div class="media-body">
                    <h3 class="dropdown-item-title"><?= htmlspecialchars((string) ($notificationItem['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-sm mb-1"><?= htmlspecialchars((string) ($notificationItem['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="app-notification-meta">
                      <?php if (!empty($notificationItem['actor_name'])): ?>
                        <span><?= htmlspecialchars((string) $notificationItem['actor_name'], ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                      <span><i class="far fa-clock mr-1"></i><?= htmlspecialchars((string) ($notificationItem['time_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  </div>
                </div>
              </a>
              <div class="dropdown-divider"></div>
            <?php endforeach; ?>
          <?php endif; ?>
          </div>
          <div class="dropdown-divider"></div>
          <a href="<?php echo $app_root; ?>notifications/" class="dropdown-item dropdown-footer">See All Notifications</a>
        </div>
      </li>

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
                      SELECT cm.*, u.first_name, u.last_name, u.picture, u.sso_avatar_url
                      FROM contact_messages cm
                      LEFT JOIN users u ON u.email = cm.user_email
                      LEFT JOIN message_reads mr
                        ON cm.id = mr.message_id AND mr.user_id = ?
                      WHERE (
                          cm.user_email = ?
                          OR EXISTS (
                              SELECT 1
                              FROM contact_message_recipients cmr
                              INNER JOIN users ua ON ua.id = cmr.user_id
                              WHERE cmr.message_id = cm.id
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
                      SELECT cm.*, u.first_name, u.last_name, u.picture, u.sso_avatar_url
                      FROM contact_messages cm
                      LEFT JOIN users u ON u.email = cm.user_email
                      LEFT JOIN message_reads mr 
                        ON cm.id = mr.message_id AND mr.user_id = ?
                      WHERE (cm.user_email = ? OR EXISTS (
                          SELECT 1
                          FROM contact_message_recipients cmr
                          WHERE cmr.message_id = cm.id
                            AND LOWER(cmr.recipient_email) = LOWER(?)
                      ))
                        AND (mr.is_read IS NULL OR mr.is_read = 0)
                      ORDER BY cm.sent_at DESC
                      LIMIT 5
                  ";
                  $stmt = $conn->prepare($sql);
                  $stmt->bind_param("iss", $userId, $userEmail, $userEmail);
              }

              $stmt->execute();
              $messages = db_stmt_fetch_all_assoc($stmt);

              if ($messages === []): ?>
                  <span class="dropdown-item text-center text-muted">No unread messages</span>
              <?php else:
                  foreach ($messages as $row):
                      $senderName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                      if ($senderName === '') {
                          $senderName = htmlspecialchars($row['user_name'] ?? 'Unknown');
                      }
                      $subject = htmlspecialchars($row['subject'] ?? '(No Subject)');
                      $snippet = htmlspecialchars(mb_strimwidth($row['message'] ?? '', 0, 40, '...'));
                      $sentAt  = topbar_notification_time_label($row['sent_at'] ?? null);
                      $avatar = topbar_notification_avatar($row, $base_url);
                  ?>
                  <a href="<?php echo $app_root; ?>inbox/index.php?msg=<?= $row['id'] ?>" class="dropdown-item">
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
              <?php endforeach;
              endif;
              $stmt->close();
          }
          ?>
          </div>
          <div class="dropdown-divider"></div>
          <a href="<?php echo $app_root; ?>inbox/index" class="dropdown-item dropdown-footer">See All Messages</a>
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
  <div id="appAlertStack" class="app-alert-stack" aria-live="polite" aria-atomic="true"></div>
  <div id="mailAlertStack" class="mail-alert-stack" aria-live="polite" aria-atomic="true"></div>

  <!-- Main Sidebar Container -->
  <aside id="mainSidebar" class="main-sidebar <?= htmlspecialchars($sidebarThemeClass, ENT_QUOTES, 'UTF-8') ?> elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo $app_root; ?>" class="brand-link">
      <img src="<?php echo $app_root; ?>dist/img/kodus.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">KODUS</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?php echo htmlspecialchars(current_user_avatar_url($base_url), ENT_QUOTES, 'UTF-8'); ?>" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info dropdown">
          <a href="#" class="d-block dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?php echo $_SESSION['first_name']; ?></a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?php echo $app_root; ?>settings"><span class="nav-icon fas fa-cogs"></span> Settings</a></li>
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
            <a href="<?php echo $app_root; ?>home" class="nav-link <?= ($current_page == 'home.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Home
              </p>
            </a>
          </li>
          <?php
            $today = date('Y-m-d');
            $currentTimezoneOffset = app_timezone_offset_string();

            // Use clear and explicit comparison for safety
            $query = $conn->prepare("
              SELECT COUNT(*) AS event_count 
              FROM events 
              WHERE DATE(CONVERT_TZ(start, @@session.time_zone, ?)) <= ? 
                AND DATE(CONVERT_TZ(end, @@session.time_zone, ?)) >= ?
                AND deleted_at is NULL
            ");
            $query->bind_param("ssss", $currentTimezoneOffset, $today, $currentTimezoneOffset, $today);
            $query->execute();
            $row = db_stmt_fetch_one_assoc($query) ?: [];
            $event_count = $row['event_count'] ?? 0;
          ?>
            <li class="nav-item">
              <a href="<?php echo $app_root; ?>pages/calendar" class="nav-link <?= ($current_page == 'calendar.php') ? 'active' : ''; ?>">
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
          <?php if (auth_can_view_operations()): ?>
          <li class="nav-header">Operations</li>
          <li class="nav-item <?= in_array($current_page, ['data-tracking.php', 'data-tracking-meb_.php', 'data-tracking-meb.php', 'data-tracking-meb-edit.php', 'data-tracking-meb-validation.php', 'data-tracking-in.php', 'data-tracking-out.php', 'payout.php', 'fund-monitoring.php']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_page, ['data-tracking.php', 'data-tracking-meb.php', 'data-tracking-meb_.php', 'data-tracking-meb-edit.php', 'data-tracking-meb-validation.php', 'data-tracking-in.php', 'data-tracking-out.php', 'payout.php', 'fund-monitoring.php']) ? 'active' : ''; ?>">
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
                    <a href="<?= $app_root . 'pages/data-tracking-meb' ?>" class="nav-link <?= in_array($current_page, ['data-tracking-meb_.php','data-tracking-meb.php', 'data-tracking-meb-edit.php']) ? 'active' : ''; ?>">
                      <i class="fa fa-list nav-icon"></i>
                      <p>MEB</p>
                    </a>
                  </li>
                  <?php if ($_SESSION['user_type'] == 'admin'): ?>
                  <li class="nav-item">
                    <a href="<?php echo $app_root; ?>pages/data-tracking-meb-validation" class="nav-link <?= ($current_page == 'data-tracking-meb-validation.php') ? 'active' : ''; ?>">
                      <i class="fa fa-check-square nav-icon"></i>
                      <p>Validation</p>
                    </a>
                  </li>
                  <?php endif; ?>
                </ul>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>pages/data-tracking-in" class="nav-link <?= ($current_page == 'data-tracking-in.php') ? 'active' : ''; ?>">
                  <i class="fa fa-sign-in-alt nav-icon"></i>
                  <p>Incoming</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>pages/data-tracking-out" class="nav-link <?= ($current_page == 'data-tracking-out.php') ? 'active' : ''; ?>">
                  <i class="fa fa-sign-out-alt nav-icon"></i>
                  <p>Outgoing</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>pages/payout" class="nav-link <?= ($current_page == 'payout.php') ? 'active' : ''; ?>">
                  <i class="fa fa-money-bill-wave nav-icon"></i>
                  <p>Payout</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>pages/fund-monitoring" class="nav-link <?= ($current_page == 'fund-monitoring.php') ? 'active' : ''; ?>">
                  <i class="fa fa-chart-line nav-icon"></i>
                  <p>Fund Monitoring</p>
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>
          <li class="nav-header">Reporting</li>
          <li class="nav-item <?= in_array($current_page, ['program-activities.php', 'program-targets.php', 'project-location-maps.php', 'project-location-records.php', 'lawa-summary.php', 'binhi-summary.php', 'php_page_.php']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_page, ['program-activities.php', 'program-targets.php', 'project-location-maps.php', 'project-location-records.php', 'lawa-summary.php', 'binhi-summary.php', 'php_page.php']) ? 'active' : ''; ?>">
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
              <?php if (auth_can_manage_program_targets()): ?>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>implementation-status/program-targets" class="nav-link <?= ($current_page == 'program-targets.php') ? 'active' : ''; ?>">
                  <i class="far fa-dot-circle nav-icon"></i>
                  <p>Baseline Targets</p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>implementation-status/program-activities" class="nav-link <?= ($current_page == 'program-activities.php') ? 'active' : ''; ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Program Activities</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>implementation-status/project-location-maps" class="nav-link <?= ($current_page == 'project-location-maps.php') ? 'active' : ''; ?>">
                  <i class="fas fa-map-marked-alt nav-icon"></i>
                  <p>Project Location Maps</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>implementation-status/project-location-records" class="nav-link <?= ($current_page == 'project-location-records.php') ? 'active' : ''; ?>">
                  <i class="fas fa-table nav-icon"></i>
                  <p>Project Location Records</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>implementation-status/lawa-summary" class="nav-link <?= ($current_page == 'lawa-summary.php') ? 'active' : ''; ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>LAWA Summary</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $app_root; ?>implementation-status/binhi-summary" class="nav-link <?= ($current_page == 'binhi-summary.php') ? 'active' : ''; ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>BINHI Summary</p>
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
              <?php if ($_SESSION['user_type'] !== 'user'): ?>
              <li class="nav-item">
                <a href="<?= $app_root; ?>pages/summary/beneficiary-profile" class="nav-link <?= ($current_page == 'beneficiary-profile.php') ? 'active' : ''; ?>">
                  <i class="fas fa-users nav-icon"></i>
                  <p>Partner-Beneficiaries Profile</p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="<?= $app_root; ?>pages/summary/sectoral" class="nav-link <?= ($current_page == 'sectoral.php') ? 'active' : ''; ?>">
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
                    <a href="<?= $app_root; ?>pages/summary/pwd/pwd" class="nav-link <?= ($current_page == 'pwd.php') ? 'active' : ''; ?>">
                      <i class="fas fa-columns nav-icon"></i>
                      <p>Disabilities Summary</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?= $app_root; ?>pages/summary/pwd/sex-disaggregated-pwd" class="nav-link <?= ($current_page == 'sex-disaggregated-pwd.php') ? 'active' : ''; ?>">
                      <i class="fas fa-transgender-alt nav-icon"></i>
                      <p>PWD Sex Disaggregation</p>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
          <li class="nav-header">Utilities</li>
          <li class="nav-item <?= in_array($current_dir, ['crossmatch', 'deduplication', 'mebis-consolidator', 'mebis-lgu-template']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_dir, ['crossmatch', 'deduplication', 'mebis-consolidator', 'mebis-lgu-template']) ? 'active' : ''; ?>">
              <i class="fas fa-wrench nav-icon"></i>
              <p>
                Tools
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="<?= $app_root; ?>crossmatch/" class="nav-link <?= ($current_dir == 'crossmatch') ? 'active' : ''; ?>">
                  <i class="nav-icon fas fa-exchange-alt"></i>
                  <p>Crossmatching</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?= $app_root; ?>deduplication/" class="nav-link <?= ($current_dir == 'deduplication') ? 'active' : ''; ?>">
                  <span class="fa-stack nav-icon">
                    <i class="fas fa-clone fa-stack-1x"></i>
                    <i class="fas fa-slash fa-stack-1x text-danger"></i>
                  </span>
                  <p>Deduplication</p>
                </a>
              </li>
              <?php if ($_SESSION['user_type'] === 'admin'): ?>
              <li class="nav-item">
                <a href="<?= $app_root; ?>mebis-consolidator/" class="nav-link <?= ($current_dir == 'mebis-consolidator') ? 'active' : ''; ?>">
                  <i class="nav-icon fas fa-layer-group"></i>
                  <p>MEBIS Name-Matching Template</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?= $app_root; ?>mebis-lgu-template/" class="nav-link <?= ($current_dir == 'mebis-lgu-template') ? 'active' : ''; ?>">
                  <i class="nav-icon fas fa-file-excel"></i>
                  <p>MEB Import Template</p>
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </li>
          <!-- <?php if ($_SESSION['user_type'] == 'admin'): ?>
          <li class="nav-item">
            <a href="<?php echo $app_root; ?>crossmatch/" class="nav-link <?= ($current_dir == 'crossmatch') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-exchange-alt"></i>
              <p>
                Crossmatching
              </p>
            </a>
          </li>
          <?php endif; ?>
          <?php if ($_SESSION['user_type'] == 'admin'): ?>
          <li class="nav-item">
            <a href="<?php echo $app_root; ?>deduplication/" class="nav-link <?= ($current_dir == 'deduplication') ? 'active' : ''; ?>">
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
          <?php if ($_SESSION['user_type'] == 'admin' || auth_can_manage_project_variables()): ?>
          <li class="nav-header">Administration</li>
          <li class="nav-item <?= in_array($current_page, ['audit_logs.php', 'restore_users.php', 'classify_users.php', 'deactivate_users.php', 'users_management.php', 'project_variables.php', 'payout_variables.php', 'password_security.php', 'maintenance.php']) ? 'menu-open' : ''; ?>">
            <a href="#" class="nav-link <?= in_array($current_page, ['audit_logs.php', 'restore_users.php', 'classify_users.php', 'deactivate_users.php', 'users_management.php', 'project_variables.php', 'payout_variables.php', 'password_security.php', 'maintenance.php']) ? 'active' : ''; ?>">
              <i class="fas fa-users nav-icon"></i>
              <p>
                Administration
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <?php if ($_SESSION['user_type'] == 'admin'): ?>
              <li class="nav-item">
                <a href="<?= $app_root; ?>admin/users_management" class="nav-link <?= in_array($current_page, ['restore_users.php', 'classify_users.php', 'deactivate_users.php', 'users_management.php']) ? 'active' : ''; ?>">
                  <i class="fas fa-user-cog nav-icon"></i>
                  <p>Users Management</p>
                </a>
              </li>
              <?php endif; ?>
              <?php if (auth_can_manage_project_variables()): ?>
              <li class="nav-item">
                <a href="<?= $app_root; ?>admin/project_variables" class="nav-link <?= in_array($current_page, ['project_variables.php', 'payout_variables.php']) ? 'active' : ''; ?>">
                  <i class="fas fa-sliders-h nav-icon"></i>
                  <p>Project Variables</p>
                </a>
              </li>
              <?php endif; ?>
              <?php if ($_SESSION['user_type'] == 'admin'): ?>
              <li class="nav-item">
                <a href="<?= $app_root; ?>admin/password_security" class="nav-link <?= ($current_page == 'password_security.php') ? 'active' : ''; ?>">
                  <i class="fas fa-shield-alt nav-icon"></i>
                  <p>Password Security</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?= $app_root; ?>admin/audit_logs" class="nav-link <?= ($current_page == 'audit_logs.php') ? 'active' : ''; ?>">
                  <i class="fas fa-history nav-icon"></i>
                  <p>Audit Logs</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?= $app_root; ?>admin/maintenance" class="nav-link <?= ($current_page == 'maintenance.php') ? 'active' : ''; ?>">
                  <i class="fas fa-tools nav-icon"></i>
                  <p>
                    Maintenance Mode
                    <?php if ($maintenanceModeEnabled): ?>
                      <span class="right badge badge-warning">ON</span>
                    <?php endif; ?>
                  </p>
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
          <li class="nav-header">Workspace</li>
          <li class="nav-item">
            <a href="<?php echo $app_root; ?>inbox/" class="nav-link <?= ($current_dir == 'inbox' || $current_page == 'contact.php') ? 'active' : ''; ?>">
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
            <a href="<?php echo $app_root; ?>settings" class="nav-link <?= ($current_page == 'settings.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-cogs"></i>
              <p>
                Settings
                <?php if ($settingsNeedsAttention): ?>
                  <span class="right badge badge-warning">!</span>
                <?php endif; ?>
              </p>
            </a>
          </li>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <script src="<?php echo $app_root; ?>cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
let topbarNotificationInitialized = false;
let topbarSeenNotificationIds = [];
let topbarAppNotificationInitialized = false;
let topbarSeenAppNotificationIds = [];
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
  toast.href = item.url || '<?= $app_root; ?>inbox/';
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

function looksLikeKodusUrlTitle(value) {
  const title = String(value || '').trim();
  if (!title) {
    return true;
  }

  const normalizedTitle = title.toLowerCase();
  const currentHref = String(window.location.href || '').trim().toLowerCase();
  const currentPath = String(window.location.pathname || '').trim().toLowerCase();

  return normalizedTitle === currentHref
    || normalizedTitle === currentPath
    || normalizedTitle.startsWith('http://')
    || normalizedTitle.startsWith('https://')
    || normalizedTitle.startsWith('www.');
}

function normalizeKodusBaseDocumentTitle(value) {
  const title = String(value || '').trim().replace(/^\(\d+\)\s*/, '');
  if (!title || looksLikeKodusUrlTitle(title)) {
    return 'KODUS | Dashboard';
  }

  if (/^kodus\s*\|/i.test(title)) {
    return title.replace(/^kodus\s*\|\s*/i, 'KODUS | ');
  }

  if (/^kodus$/i.test(title)) {
    return 'KODUS | Dashboard';
  }

  return `KODUS | ${title}`;
}

function deriveKodusTitleFromDom() {
  const explicitTitle = Array.from(document.getElementsByTagName('title'))
    .map((node) => String(node.textContent || '').trim())
    .reverse()
    .find((value) => !looksLikeKodusUrlTitle(value));

  if (explicitTitle) {
    return normalizeKodusBaseDocumentTitle(explicitTitle);
  }

  const heading = document.querySelector('.content-header h1, .content-wrapper h1, .login-box h1, .login-card-body h1');
  if (heading) {
    const headingText = String(heading.textContent || '').trim();
    if (headingText !== '') {
      return normalizeKodusBaseDocumentTitle(headingText);
    }
  }

  const pathname = String(window.location.pathname || '').trim();
  const rawSegment = pathname.split('/').filter(Boolean).pop() || '';
  const cleanedSegment = rawSegment
    .replace(/\.php$/i, '')
    .replace(/[-_]+/g, ' ')
    .trim();

  if (cleanedSegment !== '') {
    const humanizedSegment = cleanedSegment.replace(/\b\w/g, (char) => char.toUpperCase());
    return normalizeKodusBaseDocumentTitle(humanizedSegment);
  }

  return '';
}

function resolveKodusBaseDocumentTitle(preferDomDerived = false) {
  if (preferDomDerived) {
    const domDerivedTitle = deriveKodusTitleFromDom();
    if (domDerivedTitle !== '') {
      document.title = domDerivedTitle;
      return domDerivedTitle;
    }
  }

  const currentTitle = String(document.title || '').trim();
  if (!looksLikeKodusUrlTitle(currentTitle) && !/^kodus\s*\|\s*dashboard$/i.test(currentTitle)) {
    const normalizedCurrentTitle = normalizeKodusBaseDocumentTitle(currentTitle);
    document.title = normalizedCurrentTitle;
    return normalizedCurrentTitle;
  }

  const domDerivedTitle = deriveKodusTitleFromDom();
  if (domDerivedTitle !== '') {
    document.title = domDerivedTitle;
    return domDerivedTitle;
  }

  document.title = 'KODUS | Dashboard';
  return 'KODUS | Dashboard';
}

let kodusBaseDocumentTitle = resolveKodusBaseDocumentTitle();
let kodusUnreadMailCount = <?= (int) $unreadCount ?>;
let kodusUnreadAppNotificationCount = <?= (int) ($topbarAppNotificationFeed['count'] ?? 0) ?>;

function syncKodusDocumentTitle() {
  const combinedCount = Math.max(0, Number(kodusUnreadMailCount || 0)) + Math.max(0, Number(kodusUnreadAppNotificationCount || 0));
  document.title = combinedCount > 0
    ? `(${combinedCount}) ${kodusBaseDocumentTitle}`
    : kodusBaseDocumentTitle;
}

function refreshKodusBaseDocumentTitle() {
  kodusBaseDocumentTitle = resolveKodusBaseDocumentTitle(true);
  syncKodusDocumentTitle();
}

function refreshUnreadCount() {
  fetch("<?= $app_root; ?>inbox/get_notification_feed.php")
    .then(res => res.json())
    .then(data => {
      const badge = document.getElementById("topbarUnreadBadge");
      let sidebarBadge = document.getElementById("sidebarMailUnreadBadge");
      const sidebarLabel = document.querySelector('a[href$="inbox/"] p');
      const toggle = document.getElementById("topbarChatToggle");
      const list = document.getElementById("topbarChatList");
      const label = document.getElementById("topbarChatRefreshLabel");
      const count = Number(data.count || 0);
      const items = Array.isArray(data.items) ? data.items : [];
      const itemIds = items.map(item => Number(item.id || 0)).filter(id => id > 0);
      kodusUnreadMailCount = count;
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
      syncKodusDocumentTitle();
    })
    .catch(err => console.error("Unread count fetch failed:", err));
}

function showAppAlert(item) {
  const stack = document.getElementById('appAlertStack');
  if (!stack || !item) {
    return;
  }

  const toast = document.createElement('a');
  toast.className = 'app-alert-toast';
  toast.href = item.url || '<?= $app_root; ?>home';
  toast.innerHTML = `
    <span class="app-alert-toast-icon">
      <i class="${escapeHtml(item.icon_class || 'fas fa-bell')} ${escapeHtml(item.color_class || 'text-warning')}"></i>
    </span>
    <div class="app-alert-toast-copy">
      <div class="app-alert-toast-title">${escapeHtml(item.title || 'New notification')}</div>
      <p class="app-alert-toast-message">${escapeHtml(item.message || 'A new transaction update is available.')}</p>
      <div class="app-alert-toast-time">${escapeHtml(item.time_label || 'Just now')}</div>
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

  let timer = window.setTimeout(dismissToast, 5500);
  toast.addEventListener('mouseenter', () => {
    window.clearTimeout(timer);
  });
  toast.addEventListener('mouseleave', () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(dismissToast, 1800);
  });
  toast.addEventListener('click', dismissToast, { once: true });
}

function renderAppNotificationList(items) {
  if (!Array.isArray(items) || items.length === 0) {
    return '<span class="dropdown-item text-center text-muted">No new notifications</span>';
  }

  return items.map(item => `
    <a href="${escapeHtml(item.url || '<?= $app_root; ?>home')}" class="dropdown-item app-notification-item${item.is_unread ? ' is-unread' : ''}" data-notification-id="${Number(item.id || 0)}">
      <div class="media">
        <span class="app-notification-icon mr-3">
          <i class="${escapeHtml(item.icon_class || 'fas fa-bell')} ${escapeHtml(item.color_class || 'text-warning')}"></i>
        </span>
        <div class="media-body">
          <h3 class="dropdown-item-title">${escapeHtml(item.title || 'Notification')}</h3>
          <p class="text-sm mb-1">${escapeHtml(item.message || '')}</p>
          <div class="app-notification-meta">
            ${item.actor_name ? `<span>${escapeHtml(item.actor_name)}</span>` : ''}
            <span><i class="far fa-clock mr-1"></i>${escapeHtml(item.time_label || 'Just now')}</span>
          </div>
        </div>
      </div>
    </a>
    <div class="dropdown-divider"></div>
  `).join('');
}

function markAppNotificationsRead(ids) {
  const notificationIds = Array.isArray(ids)
    ? Array.from(new Set(ids.map(id => Number(id || 0)).filter(id => id > 0)))
    : [];

  if (!notificationIds.length) {
    return;
  }

  const body = new URLSearchParams();
  notificationIds.forEach(id => body.append('ids[]', String(id)));
  body.append('csrf_token', window.KODUS_CSRF_TOKEN || '');

  fetch('<?= $app_root; ?>notifications/mark_read.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body.toString()
  }).catch(function(error) {
    console.warn('Notification read sync failed.', error);
  });
}

function markAllAppNotificationsRead() {
  const button = document.getElementById('topbarNotificationMarkAllButton');
  if (button && button.disabled) {
    return Promise.resolve();
  }

  if (button) {
    button.disabled = true;
  }

  const body = new URLSearchParams();
  body.append('mark_all', '1');
  body.append('csrf_token', window.KODUS_CSRF_TOKEN || '');

  return fetch('<?= $app_root; ?>notifications/mark_read.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body.toString()
  })
    .then(function (res) {
      return res.json();
    })
    .then(function () {
      return refreshAppNotifications();
    })
    .catch(function (error) {
      if (button) {
        button.disabled = false;
      }
      console.warn('Notification mark-all failed.', error);
    });
}

function refreshAppNotifications() {
  fetch('<?= $app_root; ?>notifications/get_feed.php')
    .then(res => res.json())
    .then(data => {
      const toggle = document.getElementById('topbarNotificationToggle');
      const menu = document.getElementById('topbarNotificationMenu');
      const list = document.getElementById('topbarNotificationList');
      const label = document.getElementById('topbarNotificationRefreshLabel');
      const markAllButton = document.getElementById('topbarNotificationMarkAllButton');
      const count = Number(data.count || 0);
      const items = Array.isArray(data.items) ? data.items : [];
      const itemIds = items.map(item => Number(item.id || 0)).filter(id => id > 0);
      kodusUnreadAppNotificationCount = count;
      const unseenItems = topbarAppNotificationInitialized
        ? items.filter(item => {
            const id = Number(item.id || 0);
            return id > 0 && !topbarSeenAppNotificationIds.includes(id);
          })
        : [];

      let badge = document.getElementById('topbarNotificationBadge');
      if (badge) {
        if (count > 0) {
          badge.textContent = count;
          badge.style.display = 'inline-block';
        } else {
          badge.remove();
          badge = null;
        }
      } else if (count > 0 && toggle) {
        badge = document.createElement('span');
        badge.className = 'badge badge-warning navbar-badge';
        badge.id = 'topbarNotificationBadge';
        badge.textContent = count;
        toggle.appendChild(badge);
      }

      if (toggle) {
        toggle.classList.toggle('text-warning', count > 0);
      }

      if (list) {
        list.innerHTML = renderAppNotificationList(items);
      }

      if (label) {
        label.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
      }

      if (markAllButton) {
        markAllButton.disabled = count <= 0;
      }

      if (unseenItems.length > 0) {
        playMailBell();
        showAppAlert(unseenItems[0]);
      }

      topbarSeenAppNotificationIds = itemIds.slice(0, 20);
      topbarAppNotificationInitialized = true;
      syncKodusDocumentTitle();

      if (menu && menu.classList.contains('show') && itemIds.length > 0) {
        markAppNotificationsRead(itemIds);
      }
    })
    .catch(err => console.error('Notification feed fetch failed:', err));
}

window.refreshAppNotifications = refreshAppNotifications;

document.addEventListener('click', function (event) {
  const markAllButton = event.target.closest('#topbarNotificationMarkAllButton');
  if (!markAllButton) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  markAllAppNotificationsRead();
});

if (window.KODUSLiveRefresh && typeof window.KODUSLiveRefresh.watchSocket === 'function') {
  window.KODUSLiveRefresh.watchSocket({
    channel: 'kodus.mailbox',
    events: ['mail.changed'],
    onMessage: function () {
      refreshUnreadCount();
    }
  });
  window.KODUSLiveRefresh.watchSocket({
    channel: 'kodus.notifications',
    events: ['notifications.changed'],
    onMessage: function () {
      refreshAppNotifications();
    }
  });
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

const topbarNotificationToggle = document.getElementById('topbarNotificationToggle');
if (topbarNotificationToggle) {
  topbarNotificationToggle.addEventListener('click', function() {
    window.setTimeout(function() {
      refreshAppNotifications();
    }, 180);
  });
}

document.addEventListener('click', function (event) {
  const notificationLink = event.target.closest('#topbarNotificationList [data-notification-id]');
  if (!notificationLink) {
    return;
  }

  const notificationId = Number(notificationLink.getAttribute('data-notification-id') || 0);
  if (notificationId <= 0) {
    return;
  }

  markAppNotificationsRead([notificationId]);
  notificationLink.classList.remove('is-unread');
});

// Run once and then every 15s
syncKodusDocumentTitle();
document.addEventListener('DOMContentLoaded', refreshKodusBaseDocumentTitle);
window.addEventListener('load', refreshKodusBaseDocumentTitle);
refreshAppNotifications();
refreshUnreadCount();
setInterval(refreshAppNotifications, 15000);
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

    fetch("<?= $app_root; ?>save_theme_preference.php", {
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
