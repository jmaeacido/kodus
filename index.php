<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/theme_helpers.php';

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Login</title>
  <link rel="shortcut icon" href="<?php echo $base_url; ?>kodus/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="dist/css/custom.css">
  <link rel="stylesheet" href="plugins/sweetalert2/sweetalert2.min.css">
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
  </style>
</head>
<body class="<?= htmlspecialchars($loginBodyClass, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
  <div class="content">
    <div class="kodus-logo">
      <img src="<?php echo $base_url;?>kodus/dist/img/kodus.png" alt="KODUSLogo" height="200" width="200">
    </div>
    <div class="login-box">
      <div class="login-logo">
        <b>KODUS</b> Login
      </div>
      <div class="card">
        <div class="card-body login-card-body">
          <p class="login-box-msg">Sign in to start your session</p>
          <form id="login-form" action="login" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="input-group mb-3">
              <input type="text" name="username" class="form-control" placeholder="Username" required autocomplete="username">
              <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
            </div>
            <div class="input-group mb-3">
              <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="current-password">
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
            <a href="forgot-password">I forgot my password</a>
          </p>
          <p class="mb-0">
            <a href="register" class="text-center">Register a new membership</a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <script src="plugins/jquery/jquery.min.js"></script>
  <script src="dist/js/adminlte.min.js"></script>
  <script src="plugins/sweetalert2/sweetalert2.min.js"></script>

  <?php if (isset($_SESSION['login_error'])): ?>
  <script>
  Swal.fire({
    icon: 'error',
    title: 'Login Failed',
    text: <?= json_encode($_SESSION['login_error']) ?>
  });
  </script>
  <?php unset($_SESSION['login_error']); endif; ?>

  <?php
    $logoutMap = [
      'timeout' => ['icon' => 'warning', 'title' => 'Session Expired', 'text' => 'You were logged out due to 1 hour of inactivity.'],
      'role_changed' => ['icon' => 'info', 'title' => 'Role Updated', 'text' => 'You were signed out so your updated role can take effect.'],
      'deactivated' => ['icon' => 'warning', 'title' => 'Account Deactivated', 'text' => 'Your account has been deactivated. Please contact your administrator if you think this is a mistake.'],
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
    showConfirmButton: false
  }).then(() => {
    window.location.href = 'select_year';
  });
  </script>
  <?php endif; ?>

  <script>
  document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    if (window.KodusPageLoader) {
      window.KodusPageLoader.show('Signing you in securely...');
    }
    Swal.fire({
      icon: 'info',
      title: 'Signing in...',
      text: 'Please wait while we verify your credentials.',
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading();
        this.submit();
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
      version 1.0.0
    </div>
    <!-- Default to the left -->
    <strong>KliMalasakit Online Document Updating System</a>.</strong>
  </footer>
</body>
</html>
