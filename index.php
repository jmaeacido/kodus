<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/theme_helpers.php';
require_once __DIR__ . '/sso_helpers.php';
require_once __DIR__ . '/app_location_helpers.php';
require_once __DIR__ . '/app_meta.php';

$logoutReason = isset($_GET['logout']) ? (string) $_GET['logout'] : '';
$logoutStatus = isset($_GET['status']) ? (string) $_GET['status'] : 'success';
$hasLogoutNotice = $logoutReason !== '';

if (!isset($_SESSION['selected_year']) && !$hasLogoutNotice) {
    header("Location: select_year");
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: home');
    exit;
}

$themePreference = theme_current_preference();
$isDarkTheme = $themePreference === 'dark';
$loginBodyClass = $isDarkTheme ? 'hold-transition dark-mode login-page' : 'hold-transition login-page';
$loginFormOld = is_array($_SESSION['login_form_old'] ?? null) ? $_SESSION['login_form_old'] : [];
unset($_SESSION['login_form_old']);
$oldUsername = (string) ($loginFormOld['username'] ?? '');
$oldPassword = (string) ($loginFormOld['password'] ?? '');
$loginError = isset($_SESSION['login_error']) ? (string) $_SESSION['login_error'] : '';
unset($_SESSION['login_error'], $_SESSION['login_debug_error']);
$ssoConfigured = sso_is_configured();
$showPasswordLogin = !$ssoConfigured || $loginError !== '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Login</title>
  <link rel="shortcut icon" href="<?php echo $app_root; ?>favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>dist/css/adminlte.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>dist/css/custom.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.css">
  <?php include __DIR__ . '/page_loader.php'; ?>
  <script>
    window.KODUS_CSRF_TOKEN = <?php echo json_encode(security_get_csrf_token()); ?>;
    window.KODUS_LOCATION_CONTEXT = <?php echo json_encode([
        'endpoint' => $app_root . 'save_location_context.php',
        'csrfToken' => security_get_csrf_token(),
        'reloadOnChange' => false,
        'maxAgeSeconds' => 1800,
        'session' => function_exists('app_location_session_snapshot') ? app_location_session_snapshot() : [],
    ], JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script src="<?php echo $app_root; ?>dist/js/kodus-location-context.js"></script>
  <style>
    @media (max-height: 600px) {
      .main-footer {
        position: static;
      }
    }
    .main-footer {
      width: 100%;
      left: 0;
      right: 0;
      margin-left: 0 !important;
    }
    .content {
      display: flex;
      justify-content: center;
      height: 100%;
      padding-bottom: 300px;
      margin-bottom: 500px;
    }
    .login-box {
      position: absolute;
      top: 100px;
      opacity: 0.95;
    }
    .login-card-body {
      min-height: 320px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .kodus-logo {
      position: absolute;
      top: 210px;
    }
    .password-toggle {
      cursor: pointer;
    }
    .login-divider {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin: 1rem 0;
      color: #6c757d;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .login-divider::before,
    .login-divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: rgba(108, 117, 125, 0.35);
    }
    .btn-sso {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      border: 1px solid rgba(13, 110, 253, 0.2);
      background: #f8fbff;
      color: #0d6efd;
      font-weight: 600;
    }
    body.dark-mode .btn-sso,
    body[data-theme="dark"] .btn-sso {
      background: rgba(255, 255, 255, 0.04);
      color: #8cbcff;
      border-color: rgba(140, 188, 255, 0.25);
    }
    .sso-pane {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 0.5rem 0 0.25rem;
      gap: 0.9rem;
    }
    .sso-pane__badge {
      width: 72px;
      height: 72px;
      border-radius: 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.85rem;
      color: #0d6efd;
      background:
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.35)),
        linear-gradient(135deg, rgba(13, 110, 253, 0.18), rgba(59, 176, 255, 0.26));
      box-shadow: 0 18px 35px rgba(13, 110, 253, 0.16);
    }
    .sso-pane__title {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 700;
      color: #15304b;
    }
    .sso-pane__text {
      margin: 0;
      max-width: 260px;
      font-size: 0.93rem;
      line-height: 1.55;
      color: #5f6b7a;
    }
    .btn-sso.btn-sso-primary {
      min-height: 50px;
      border-radius: 14px;
      border-color: rgba(13, 110, 253, 0.22);
      background: linear-gradient(135deg, #f8fbff, #eef6ff);
      box-shadow: 0 12px 24px rgba(13, 110, 253, 0.12);
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .btn-sso.btn-sso-primary:hover,
    .btn-sso.btn-sso-primary:focus {
      color: #0b5ed7;
      border-color: rgba(13, 110, 253, 0.35);
      box-shadow: 0 16px 28px rgba(13, 110, 253, 0.16);
      transform: translateY(-1px);
      text-decoration: none;
    }
    .sso-pane__hint {
      margin: 0;
      font-size: 0.82rem;
      color: #7a8696;
    }
    body.dark-mode .sso-pane__badge,
    body[data-theme="dark"] .sso-pane__badge {
      color: #8cbcff;
      background:
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
        linear-gradient(135deg, rgba(96, 165, 250, 0.2), rgba(59, 130, 246, 0.1));
      box-shadow: 0 18px 35px rgba(2, 6, 23, 0.35);
    }
    body.dark-mode .sso-pane__title,
    body[data-theme="dark"] .sso-pane__title {
      color: #e7f1ff;
    }
    body.dark-mode .sso-pane__text,
    body[data-theme="dark"] .sso-pane__text {
      color: #b9c7d8;
    }
    body.dark-mode .btn-sso.btn-sso-primary,
    body[data-theme="dark"] .btn-sso.btn-sso-primary {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.06), rgba(140, 188, 255, 0.08));
      border-color: rgba(140, 188, 255, 0.24);
      box-shadow: 0 12px 24px rgba(2, 6, 23, 0.32);
    }
    body.dark-mode .btn-sso.btn-sso-primary:hover,
    body.dark-mode .btn-sso.btn-sso-primary:focus,
    body[data-theme="dark"] .btn-sso.btn-sso-primary:hover,
    body[data-theme="dark"] .btn-sso.btn-sso-primary:focus {
      color: #c4ddff;
      border-color: rgba(140, 188, 255, 0.38);
      box-shadow: 0 16px 30px rgba(2, 6, 23, 0.42);
    }
    body.dark-mode .sso-pane__hint,
    body[data-theme="dark"] .sso-pane__hint {
      color: #8fa3bc;
    }
  </style>
</head>
<body class="<?= htmlspecialchars($loginBodyClass, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
  <div class="content">
    <div class="kodus-logo">
      <img src="<?php echo $app_root; ?>dist/img/kodus.png" alt="KODUSLogo" height="200" width="200">
    </div>
    <div class="login-box">
      <div class="login-logo">
        <b>KODUS</b> Login
      </div>
      <div class="card">
        <div class="card-body login-card-body">
          <p class="login-box-msg">Sign in to start your session</p>
          <?php if ($ssoConfigured): ?>
          <div class="sso-pane">
            <div class="sso-pane__badge" aria-hidden="true">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="sso-pane__title">Continue with Caraga Connect</h3>
            <p class="sso-pane__text">
              Sign in securely with your My Portal account to access KODUS.
            </p>
            <a href="<?php echo $app_root; ?>login-sso" class="btn btn-sso btn-sso-primary">
              <i class="fas fa-sign-in-alt"></i>
              <span>Sign In with Caraga-Connect SSO</span>
            </a>
            <p class="sso-pane__hint">Single sign-on is the recommended login method.</p>
          </div>
          <?php endif; ?>
          <?php if ($showPasswordLogin): ?>
          <?php if ($ssoConfigured): ?>
          <div class="login-divider">SSO Fallback</div>
          <p class="text-muted text-center mt-2" style="font-size: 0.85rem;">
            SSO is currently unavailable. You can use your local KODUS credentials below.
          </p>
          <?php endif; ?>
          <form id="login-form" action="<?php echo $app_root; ?>login" method="post" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="input-group mb-3">
              <input type="text" name="username" id="username" class="form-control" placeholder="Username" required autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8') ?>">
              <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
            </div>
            <div class="input-group mb-3">
              <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="current-password" value="<?= htmlspecialchars($oldPassword, ENT_QUOTES, 'UTF-8') ?>">
              <div class="input-group-append"><div class="input-group-text password-toggle"><span class="fas fa-eye" id="togglePassword"></span>&nbsp;<span class="fas fa-lock"></span></div></div>
            </div>
            <div class="row">
              <div class="col-8">
                <div class="icheck-primary">
                  <input type="checkbox" id="remember" name="remember">
                  <label for="remember">Remember Me</label>
                </div>
              </div>
              <div class="col-4">
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
              </div>
            </div>
          </form>
          <p class="mb-1">
            <a href="<?php echo $app_root; ?>forgot-password">I forgot my password</a>
          </p>
          <p class="mb-0">
            <a href="<?php echo $app_root; ?>register" class="text-center">Register a new membership</a>
          </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
  <script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
  <script src="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.js"></script>

  <?php if ($loginError !== ''): ?>
  <script>
  window.addEventListener('load', function () {
    if (window.KodusPageLoader) {
      window.KodusPageLoader.hide();
    }

    Swal.fire({
      icon: 'error',
      title: 'Login Failed',
      text: <?= json_encode($loginError) ?>,
      confirmButtonText: 'OK',
      allowOutsideClick: true,
      allowEscapeKey: true
    });
  }, { once: true });
  </script>
  <?php endif; ?>

  <?php
    $logoutMap = [
      'timeout' => ['icon' => 'warning', 'title' => 'Session Expired', 'text' => 'You were logged out due to 1 hour of inactivity.'],
      'role_changed' => ['icon' => 'info', 'title' => 'Role Updated', 'text' => 'You were signed out so your updated role can take effect.'],
      'deactivated' => ['icon' => 'warning', 'title' => 'Account Deactivated', 'text' => 'Your account has been deactivated. Please contact your administrator if you think this is a mistake.'],
      'maintenance' => ['icon' => 'info', 'title' => 'Maintenance Mode Enabled', 'text' => 'KODUS is temporarily unavailable while maintenance is in progress. Please try again later.'],
      'manual' => ['icon' => $logoutStatus === 'error' ? 'error' : 'success', 'title' => $logoutStatus === 'error' ? 'Logout Completed With Issues' : 'Logged Out', 'text' => $logoutStatus === 'error' ? 'You were signed out, but part of the cleanup failed. Please sign in again if needed.' : 'You have successfully logged out.'],
    ];
    $logoutNotice = $logoutMap[$logoutReason] ?? null;
  ?>
  <?php if ($logoutNotice): ?>
  <script>
  Swal.fire({
    icon: <?= json_encode($logoutNotice['icon']) ?>,
    title: <?= json_encode($logoutNotice['title']) ?>,
    text: <?= json_encode($logoutNotice['text']) ?>,
    timer: 1800,
    showConfirmButton: false,
    showCancelButton: false,
    showCloseButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false
  }).then(() => {
    window.location.href = <?php echo json_encode($app_root . 'select_year'); ?>;
  });
  </script>
  <?php endif; ?>

  <script>
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function() {
      if (window.KodusPageLoader) {
        window.KodusPageLoader.show('Signing you in securely...');
      }

      const submitButton = this.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Signing In...';
      }

      Swal.fire({
        icon: 'info',
        title: 'Signing in...',
        text: 'Please wait while we verify your credentials.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
    });
  }

  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#password');
  if (togglePassword && password) {
    togglePassword.addEventListener('click', function () {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      this.classList.toggle('fa-eye-slash');
    });
  }
  </script>

  <!-- Main Footer -->
  <footer class="main-footer fixed-bottom">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
      Version <?= htmlspecialchars(app_version_label(), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(app_release_label(), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <!-- Default to the left -->
    <strong>KliMalasakit Online Document Updating System</a>.</strong>
  </footer>
</body>
</html>
