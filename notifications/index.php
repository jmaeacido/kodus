<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/../app_notification_helpers.php';

$notificationUserId = (int) ($_SESSION['user_id'] ?? 0);
$notifications = $notificationUserId > 0 ? app_notification_list($conn, $notificationUserId, 100) : [];
$unreadNotificationCount = 0;

foreach ($notifications as $notificationItem) {
    if (!empty($notificationItem['is_unread'])) {
        $unreadNotificationCount++;
    }
}
?>
<style>
  .notification-history-shell {
    display: grid;
    gap: 1rem;
  }

  .notification-history-summary {
    border: 1px solid rgba(13, 110, 253, 0.12);
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.08), rgba(32, 201, 151, 0.08));
    padding: 1rem 1.1rem;
  }

  .notification-history-summary h5 {
    margin-bottom: 0.3rem;
    font-weight: 700;
  }

  .notification-history-summary p {
    margin: 0;
    color: #6c757d;
  }

  body[data-theme="dark"] .notification-history-summary p {
    color: #b8c7d9;
  }

  .notification-history-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
  }

  .notification-history-counts {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
  }

  .notification-history-counts .badge {
    font-size: 0.82rem;
    padding: 0.55rem 0.75rem;
  }

  .notification-history-list {
    display: grid;
    gap: 0.85rem;
  }

  .notification-history-item {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    padding: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.88);
    color: inherit;
    text-decoration: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
  }

  .notification-history-item:hover {
    color: inherit;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
    border-color: rgba(13, 110, 253, 0.2);
  }

  .notification-history-item.is-unread {
    border-color: rgba(13, 110, 253, 0.28);
    box-shadow: inset 3px 0 0 #0d6efd;
  }

  body[data-theme="dark"] .notification-history-item {
    background: rgba(17, 24, 39, 0.88);
    border-color: rgba(148, 163, 184, 0.14);
  }

  body[data-theme="dark"] .notification-history-item:hover {
    box-shadow: 0 16px 30px rgba(0, 0, 0, 0.22);
  }

  .notification-history-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
    flex-shrink: 0;
  }

  .notification-history-copy {
    min-width: 0;
    flex: 1 1 auto;
  }

  .notification-history-meta {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.45rem;
    color: #6c757d;
    font-size: 0.82rem;
  }

  body[data-theme="dark"] .notification-history-meta {
    color: #b8c7d9;
  }

  .notification-history-empty {
    padding: 2.75rem 1rem;
    text-align: center;
    color: #6c757d;
  }

  body[data-theme="dark"] .notification-history-empty {
    color: #b8c7d9;
  }

  @media (max-width: 575.98px) {
    .notification-history-item {
      padding: 0.9rem;
      gap: 0.85rem;
    }
  }
</style>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">All Notifications</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>home">Home</a></li>
            <li class="breadcrumb-item active">Notifications</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">
      <div class="notification-history-shell">
        <div class="notification-history-summary">
          <div class="notification-history-actions">
            <div>
              <h5>Transaction Notifications</h5>
              <p>Latest activity across imports, updates, deletions, and other tracked actions in KODUS.</p>
            </div>
            <button type="button" class="btn btn-outline-primary" id="notificationHistoryMarkAllButton"<?= $unreadNotificationCount > 0 ? '' : ' disabled' ?>>
              <i class="fas fa-check-double mr-1"></i>
              Mark All as Read
            </button>
          </div>
          <div class="notification-history-counts mt-3">
            <span class="badge badge-primary" id="notificationHistoryTotalBadge"><?= number_format(count($notifications)) ?> total</span>
            <span class="badge badge-warning" id="notificationHistoryUnreadBadge"><?= number_format($unreadNotificationCount) ?> unread</span>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <div class="notification-history-list" id="notificationHistoryList">
              <?php if ($notifications === []): ?>
                <div class="notification-history-empty">No notifications found yet.</div>
              <?php else: ?>
                <?php foreach ($notifications as $notificationItem): ?>
                  <?php
                  $notificationUrl = trim((string) ($notificationItem['url'] ?? '')) !== ''
                      ? (string) $notificationItem['url']
                      : ($app_root . 'home');
                  ?>
                  <a
                    href="<?= htmlspecialchars($notificationUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="notification-history-item<?= !empty($notificationItem['is_unread']) ? ' is-unread' : '' ?>"
                    data-notification-id="<?= (int) ($notificationItem['id'] ?? 0) ?>"
                  >
                    <span class="notification-history-icon">
                      <i class="<?= htmlspecialchars((string) ($notificationItem['icon_class'] ?? 'fas fa-bell'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($notificationItem['color_class'] ?? 'text-warning'), ENT_QUOTES, 'UTF-8') ?>"></i>
                    </span>
                    <span class="notification-history-copy">
                      <strong class="d-block"><?= htmlspecialchars((string) ($notificationItem['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8') ?></strong>
                      <span class="d-block mt-1"><?= htmlspecialchars((string) ($notificationItem['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="notification-history-meta">
                        <?php if (!empty($notificationItem['actor_name'])): ?>
                          <span><?= htmlspecialchars((string) $notificationItem['actor_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <span><i class="far fa-clock mr-1"></i><?= htmlspecialchars((string) ($notificationItem['time_label'] ?? 'Just now'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($notificationItem['is_unread'])): ?>
                          <span class="badge badge-warning">Unread</span>
                        <?php endif; ?>
                      </span>
                    </span>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<script>
  (function () {
    const markAllButton = document.getElementById('notificationHistoryMarkAllButton');
    const historyList = document.getElementById('notificationHistoryList');
    const unreadBadge = document.getElementById('notificationHistoryUnreadBadge');

    function updateUnreadBadge(count) {
      if (!unreadBadge) {
        return;
      }

      unreadBadge.textContent = `${Number(count || 0).toLocaleString()} unread`;
      unreadBadge.classList.toggle('badge-warning', Number(count || 0) > 0);
      unreadBadge.classList.toggle('badge-secondary', Number(count || 0) <= 0);
    }

    async function postNotificationRead(body) {
      const response = await fetch('<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>notifications/mark_read.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString(),
        credentials: 'same-origin',
        keepalive: true
      });

      return response.json();
    }

    if (historyList) {
      historyList.addEventListener('click', function (event) {
        const link = event.target.closest('[data-notification-id]');
        if (!link) {
          return;
        }

        const notificationId = Number(link.getAttribute('data-notification-id') || 0);
        if (notificationId <= 0) {
          return;
        }

        const body = new URLSearchParams();
        body.append('ids[]', String(notificationId));
        body.append('csrf_token', window.KODUS_CSRF_TOKEN || '');

        postNotificationRead(body)
          .then(function (payload) {
            link.classList.remove('is-unread');
            const unreadPill = link.querySelector('.badge.badge-warning');
            if (unreadPill) {
              unreadPill.remove();
            }

            updateUnreadBadge(payload && typeof payload.unread_count !== 'undefined' ? payload.unread_count : 0);
            if (markAllButton) {
              markAllButton.disabled = Number(payload && payload.unread_count || 0) <= 0;
            }
          })
          .catch(function () {
          });
      });
    }

    if (!markAllButton) {
      return;
    }

    markAllButton.addEventListener('click', async function () {
      if (markAllButton.disabled) {
        return;
      }

      markAllButton.disabled = true;

      const body = new URLSearchParams();
      body.append('mark_all', '1');
      body.append('csrf_token', window.KODUS_CSRF_TOKEN || '');

      try {
        const payload = await postNotificationRead(body);
        document.querySelectorAll('.notification-history-item.is-unread').forEach(function (item) {
          item.classList.remove('is-unread');
          const unreadPill = item.querySelector('.badge.badge-warning');
          if (unreadPill) {
            unreadPill.remove();
          }
        });

        updateUnreadBadge(payload && typeof payload.unread_count !== 'undefined' ? payload.unread_count : 0);

        if (window.refreshAppNotifications) {
          window.refreshAppNotifications();
        }
      } catch (error) {
        markAllButton.disabled = false;
        Swal.fire({
          icon: 'error',
          title: 'Unable to update notifications',
          text: 'Please try again.'
        });
      }
    });
  }());
</script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
</body>
</html>
