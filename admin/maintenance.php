<?php
include('../header.php');
include('../sidenav.php');

$maintenanceEnabled = kodus_maintenance_is_enabled($conn);
$maintenanceState = kodus_maintenance_state($conn);
$maintenanceSettingEnabled = !empty($maintenanceState['enabled']);
$maintenanceMessage = kodus_maintenance_message($conn);
$warningMessage = kodus_maintenance_warning_message($conn);
$warningSeconds = kodus_maintenance_warning_seconds($conn);
$redirectSeconds = kodus_maintenance_redirect_seconds($conn);
?>
<script>
document.title = 'KODUS | Maintenance Mode';
</script>
<style>
    body.maintenance-page {
      background:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.16), transparent 28%),
        linear-gradient(180deg, #f6f9fc 0%, #eef3f8 100%);
    }
    body.maintenance-page .content-wrapper {
      background:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.16), transparent 28%),
        linear-gradient(180deg, #f6f9fc 0%, #eef3f8 100%);
    }
    body.maintenance-page {
      min-height: 100vh;
    }
    body.dark-mode.maintenance-page .content-wrapper,
    body[data-theme="dark"].maintenance-page .content-wrapper {
      background:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.18), transparent 28%),
        linear-gradient(180deg, #111927 0%, #0b1220 100%);
    }
    body.dark-mode.maintenance-page,
    body[data-theme="dark"].maintenance-page {
      background:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.18), transparent 28%),
        linear-gradient(180deg, #111927 0%, #0b1220 100%);
    }
    body.maintenance-page .wrapper,
    body.maintenance-page .content-wrapper,
    body.maintenance-page .main-footer {
      background-color: transparent;
    }
    body.maintenance-page .content-wrapper {
      padding-bottom: 4.75rem;
    }
    body.maintenance-page .main-footer {
      background: rgba(255, 255, 255, 0.82);
      backdrop-filter: blur(10px);
      border-top: 1px solid rgba(15, 23, 42, 0.08);
      color: #607080;
    }
    body.dark-mode.maintenance-page .main-footer,
    body[data-theme="dark"].maintenance-page .main-footer {
      background: rgba(15, 23, 42, 0.82);
      backdrop-filter: blur(10px);
      border-top-color: rgba(255, 255, 255, 0.08);
      color: #9fb0c2;
    }
    .maintenance-hero,
    .maintenance-card {
      border-radius: 1.2rem;
      border: 1px solid rgba(15, 23, 42, 0.08);
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    body.dark-mode .maintenance-hero,
    body.dark-mode .maintenance-card,
    body[data-theme="dark"] .maintenance-hero,
    body[data-theme="dark"] .maintenance-card {
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
    }
    body.dark-mode .maintenance-card,
    body.dark-mode .maintenance-card .card-header,
    body.dark-mode .maintenance-card .card-body,
    body.dark-mode .maintenance-card .card-footer,
    body[data-theme="dark"] .maintenance-card,
    body[data-theme="dark"] .maintenance-card .card-header,
    body[data-theme="dark"] .maintenance-card .card-body,
    body[data-theme="dark"] .maintenance-card .card-footer {
      background: #141d2b;
      color: #e8eef5;
    }
    body.dark-mode .maintenance-card .card-header,
    body.dark-mode .maintenance-card .card-footer,
    body[data-theme="dark"] .maintenance-card .card-header,
    body[data-theme="dark"] .maintenance-card .card-footer {
      border-color: rgba(255, 255, 255, 0.08);
    }
    body.dark-mode .maintenance-card label,
    body.dark-mode .maintenance-card .text-muted,
    body.dark-mode .maintenance-card .form-text,
    body[data-theme="dark"] .maintenance-card label,
    body[data-theme="dark"] .maintenance-card .text-muted,
    body[data-theme="dark"] .maintenance-card .form-text {
      color: #9fb0c2 !important;
    }
    body.dark-mode .maintenance-card .card-title,
    body.dark-mode .maintenance-hero,
    body[data-theme="dark"] .maintenance-card .card-title,
    body[data-theme="dark"] .maintenance-hero {
      color: #e8eef5;
    }
    body.dark-mode .maintenance-card .form-control,
    body.dark-mode .maintenance-card textarea.form-control,
    body[data-theme="dark"] .maintenance-card .form-control,
    body[data-theme="dark"] .maintenance-card textarea.form-control {
      background: #0f1724;
      border-color: rgba(125, 196, 255, 0.14);
      color: #e8eef5;
    }
    body.dark-mode .maintenance-card .form-control:focus,
    body.dark-mode .maintenance-card textarea.form-control:focus,
    body[data-theme="dark"] .maintenance-card .form-control:focus,
    body[data-theme="dark"] .maintenance-card textarea.form-control:focus {
      background: #101a28;
      border-color: rgba(125, 196, 255, 0.42);
      color: #ffffff;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.16);
    }
    body.dark-mode .maintenance-card .form-control::placeholder,
    body[data-theme="dark"] .maintenance-card .form-control::placeholder {
      color: #7f93a8;
    }
    body.dark-mode .maintenance-card .custom-control-label,
    body[data-theme="dark"] .maintenance-card .custom-control-label {
      color: #e8eef5;
    }
    .maintenance-hero {
      position: relative;
      padding: 1.5rem;
      margin-bottom: 1rem;
      background: linear-gradient(135deg, rgba(255, 193, 7, 0.22), rgba(0, 123, 255, 0.15));
    }
    .maintenance-hero::after {
      content: "";
      position: absolute;
      top: -40px;
      right: -40px;
      width: 180px;
      height: 180px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.12);
    }
    .maintenance-status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.45rem 0.8rem;
      border-radius: 999px;
      font-weight: 700;
      background: rgba(15, 23, 42, 0.08);
    }
    .maintenance-status-badge.enabled {
      background: rgba(255, 193, 7, 0.24);
      color: #8a5700;
    }
    body.dark-mode .maintenance-status-badge,
    body[data-theme="dark"] .maintenance-status-badge {
      background: rgba(255, 255, 255, 0.08);
      color: #d7e3f0;
    }
    body.dark-mode .maintenance-status-badge.enabled,
    body[data-theme="dark"] .maintenance-status-badge.enabled {
      background: rgba(255, 193, 7, 0.18);
      color: #ffd978;
      box-shadow: inset 0 0 0 1px rgba(255, 217, 120, 0.14);
    }
    .maintenance-preview {
      border-radius: 1rem;
      padding: 1rem;
      background: rgba(15, 23, 42, 0.04);
      border: 1px dashed rgba(15, 23, 42, 0.16);
    }
    body.dark-mode .maintenance-preview,
    body[data-theme="dark"] .maintenance-preview {
      background: rgba(255, 255, 255, 0.04);
      border-color: rgba(255, 255, 255, 0.12);
    }
    body.dark-mode.maintenance-page .text-muted,
    body[data-theme="dark"].maintenance-page .text-muted {
      color: #9fb0c2 !important;
    }
    @media (max-width: 1600px) {
      .maintenance-hero {
        padding: 1.25rem;
      }
      .maintenance-preview,
      .maintenance-card .card-body,
      .maintenance-card .card-footer {
        padding: 0.9rem;
      }
    }
    @media (max-width: 1366px) {
      .maintenance-hero {
        padding: 1.1rem;
        margin-bottom: 0.85rem;
      }
      .maintenance-hero::after {
        width: 150px;
        height: 150px;
      }
      .maintenance-status-badge {
        padding: 0.4rem 0.7rem;
        font-size: 0.84rem;
      }
      .maintenance-preview,
      .maintenance-card .card-body,
      .maintenance-card .card-footer {
        padding: 0.85rem;
      }
    }
    @media (max-width: 1280px) {
      .maintenance-hero {
        padding: 1rem;
      }
      .maintenance-hero h2,
      .maintenance-card .card-title {
        font-size: 1rem;
      }
      .maintenance-hero p,
      .maintenance-card p,
      .maintenance-card .form-text {
        font-size: 0.88rem;
      }
    }
    @media (max-width: 1024px) {
      .maintenance-hero {
        padding: 0.95rem;
      }
      .maintenance-hero .d-flex {
        gap: 0.75rem !important;
      }
    }
    @media (max-width: 991.98px) {
      body.maintenance-page .content-wrapper {
        padding-bottom: 5.5rem;
      }
    }
</style>
<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <h1 class="mb-1">Maintenance Mode</h1>
            <p class="mb-0 text-muted">Control the branded KODUS maintenance experience, redirects, and logging behavior from one place.</p>
          </div>
          <ol class="breadcrumb float-sm-right mb-0 mt-2 mt-sm-0">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>home">Home</a></li>
            <li class="breadcrumb-item">Administration</li>
            <li class="breadcrumb-item active">Maintenance Mode</li>
          </ol>
        </div>
      </div>
    </section>

    <section class="content pb-4">
      <div class="container-fluid">
        <div class="maintenance-hero">
          <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 1rem;">
            <div>
              <div class="text-uppercase font-weight-bold mb-2" style="letter-spacing: 0.12em; opacity: 0.75;">KODUS Resilience Controls</div>
              <h2 class="h4 mb-2">Service gate, branded error page, redirect timer, and audit logging</h2>
              <p class="mb-0">When enabled, non-admin users get a countdown warning first, then the KODUS 503 maintenance experience after the grace period ends while admins keep access.</p>
            </div>
            <div class="maintenance-status-badge <?= $maintenanceSettingEnabled ? 'enabled' : '' ?>">
              <i class="fas <?= $maintenanceSettingEnabled ? 'fa-tools' : 'fa-check-circle' ?>"></i>
              <span>
                <?php
                if (!$maintenanceSettingEnabled) {
                    echo 'Maintenance Disabled';
                } elseif (($maintenanceState['phase'] ?? 'inactive') === 'pending') {
                    echo 'Maintenance Countdown Active';
                } else {
                    echo 'Maintenance Active';
                }
                ?>
              </span>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-lg-7">
            <div class="card maintenance-card">
              <div class="card-header">
                <h3 class="card-title mb-0">Maintenance Settings</h3>
              </div>
              <form action="<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>admin/save_maintenance_settings.php" method="post" id="maintenanceSettingsForm">
                <div class="card-body">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                  <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success mb-4">
                    <input type="checkbox" class="custom-control-input" id="maintenance_enabled" name="maintenance_enabled" value="1" <?= $maintenanceSettingEnabled ? 'checked' : '' ?>>
                    <label class="custom-control-label font-weight-bold" for="maintenance_enabled">Enable maintenance mode</label>
                  </div>

                  <div class="form-group">
                    <label for="maintenance_warning_message">Warning message for logged-in non-admin users</label>
                    <textarea class="form-control" rows="4" id="maintenance_warning_message" name="maintenance_warning_message" maxlength="1000" required><?= htmlspecialchars($warningMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <small class="form-text text-muted">This appears in the non-blocking countdown banner so users can finish or save active work before maintenance begins.</small>
                  </div>

                  <div class="form-group">
                    <label for="maintenance_warning_seconds">Grace period before maintenance starts</label>
                    <input type="number" class="form-control" id="maintenance_warning_seconds" name="maintenance_warning_seconds" min="0" max="7200" value="<?= (int) $warningSeconds ?>" required>
                    <small class="form-text text-muted">Set to `0` to activate maintenance immediately. Otherwise, logged-in non-admin users get this many seconds to finish their work first.</small>
                  </div>

                  <div class="form-group">
                    <label for="maintenance_message">User-facing maintenance message</label>
                    <textarea class="form-control" rows="5" id="maintenance_message" name="maintenance_message" maxlength="1000" required><?= htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <small class="form-text text-muted">This appears on the branded 503 page and is also included in the audit log details.</small>
                  </div>

                  <div class="form-group">
                    <label for="maintenance_redirect_seconds">Auto-redirect countdown on maintenance page</label>
                    <input type="number" class="form-control" id="maintenance_redirect_seconds" name="maintenance_redirect_seconds" min="0" max="60" value="<?= (int) $redirectSeconds ?>" required>
                    <small class="form-text text-muted">Set to `0` to keep visitors on the maintenance page without an automatic redirect.</small>
                  </div>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center" style="gap: 0.75rem;">
                  <span class="text-muted">Changes are logged to `audit_logs` automatically.</span>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i>Save Maintenance Settings
                  </button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card maintenance-card">
              <div class="card-header">
                <h3 class="card-title mb-0">Live Preview</h3>
              </div>
              <div class="card-body">
                <div class="maintenance-preview">
                  <div class="d-flex align-items-center mb-3" style="gap: 0.85rem;">
                    <img src="<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>dist/img/kodus.png" alt="KODUS logo" style="width: 56px; height: 56px; object-fit: contain;">
                    <div>
                      <div class="text-uppercase font-weight-bold" style="font-size: 0.78rem; letter-spacing: 0.1em; opacity: 0.7;">503 Preview</div>
                      <div class="h5 mb-0">Service Unavailable</div>
                    </div>
                  </div>
                  <p class="mb-2" id="maintenancePreviewMessage"><?= htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="mb-2 text-muted"><strong>Countdown warning:</strong> <span id="maintenancePreviewWarningMessage"><?= htmlspecialchars($warningMessage, ENT_QUOTES, 'UTF-8') ?></span></p>
                  <p class="mb-2 text-muted">Warning window: <span id="maintenancePreviewWarningTimer"><?= (int) $warningSeconds ?></span>s</p>
                  <p class="mb-0 text-muted">Redirect countdown: <span id="maintenancePreviewTimer"><?= (int) $redirectSeconds ?></span>s</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.body.classList.add('maintenance-page');
  const form = document.getElementById('maintenanceSettingsForm');
  const warningMessage = document.getElementById('maintenance_warning_message');
  const warningSeconds = document.getElementById('maintenance_warning_seconds');
  const message = document.getElementById('maintenance_message');
  const countdown = document.getElementById('maintenance_redirect_seconds');
  const previewMessage = document.getElementById('maintenancePreviewMessage');
  const previewWarningMessage = document.getElementById('maintenancePreviewWarningMessage');
  const previewWarningTimer = document.getElementById('maintenancePreviewWarningTimer');
  const previewTimer = document.getElementById('maintenancePreviewTimer');
  const submitButton = form ? form.querySelector('button[type="submit"]') : null;

  function syncPreview() {
    previewMessage.textContent = message.value.trim() || 'Routine maintenance is underway.';
    previewWarningMessage.textContent = warningMessage.value.trim() || 'Scheduled maintenance will begin soon. Please save or complete your current work before the countdown ends.';
    previewWarningTimer.textContent = warningSeconds.value || '0';
    previewTimer.textContent = countdown.value || '0';
  }

  warningMessage.addEventListener('input', syncPreview);
  warningSeconds.addEventListener('input', syncPreview);
  message.addEventListener('input', syncPreview);
  countdown.addEventListener('input', syncPreview);
  syncPreview();

  form.addEventListener('submit', function () {
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    }

    if (window.Swal && typeof window.Swal.fire === 'function') {
      Swal.fire({
        title: 'Updating maintenance mode...',
        text: 'KODUS is saving the new service availability settings.',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
          Swal.showLoading();
        }
      });
    }
  });
});
</script>
<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
</body>
</html>
