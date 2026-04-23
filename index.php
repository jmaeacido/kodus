<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/theme_helpers.php';
require_once __DIR__ . '/sso_helpers.php';
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
          <?php if (sso_is_configured()): ?>
          <div class="login-divider">or</div>
          <a href="<?php echo $app_root; ?>login-sso" class="btn btn-sso">
            <i class="fas fa-shield-alt"></i>
            <span>Sign In with Caraga-Connect SSO</span>
          </a>
          <p class="text-muted text-center mt-2 mb-2" style="font-size: 0.85rem;">
            Use your My Portal account to access KODUS.
          </p>
          <?php endif; ?>
          <p class="mb-1">
            <a href="<?php echo $app_root; ?>forgot-password">I forgot my password</a>
          </p>
          <p class="mb-0">
            <a href="<?php echo $app_root; ?>register" class="text-center">Register a new membership</a>
          </p>
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
  document.getElementById('login-form').addEventListener('submit', function() {
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

  // Show/Hide Password toggle
  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#password');

  togglePassword.addEventListener('click', function () {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.classList.toggle('fa-eye-slash');
  });
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
