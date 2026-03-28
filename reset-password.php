<?php
include('header.php');
include('config.php'); 

$invalid_token = false;
$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $_SESSION['pending_reset_token'] = null;
}

if (isset($_GET['token'])) {
    $token = (string) $_GET['token'];
    $user = security_find_user_by_token($conn, 'reset_token', $token, "reset_token_expiry > NOW()");
    if (!$user) {
        $invalid_token = true;
    } else {
        $_SESSION['pending_reset_token'] = $token;
    }
} elseif (!empty($_SESSION['pending_reset_token']) && is_string($_SESSION['pending_reset_token'])) {
    $token = $_SESSION['pending_reset_token'];
    $user = security_find_user_by_token($conn, 'reset_token', $token, "reset_token_expiry > NOW()");
    $invalid_token = !$user;
    if ($invalid_token) {
        $_SESSION['pending_reset_token'] = null;
    }
} else {
    $invalid_token = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>KODUS | Reset Password</title>
  <link rel="shortcut icon" href="<?php echo $base_url; ?>kodus/favicon.ico" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <style>
    .main-footer {
      margin-left: 0 !important;
    }
  </style>
</head>
<body class="hold-transition dark-mode login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>KODUS</b></a>
  </div>
  <div class="card">
    <div class="card-body login-card-body">
      <?php if ($invalid_token): ?>
        <div class="alert alert-danger text-center" role="alert">
          <i class="fas fa-exclamation-triangle"></i> Invalid or expired token.
        </div>
      <?php else: ?>
        <p class="login-box-msg">Enter your new password</p>
        <form action="update-password.php" method="post">
          <input type="hidden" name="reset_submitted" value="1">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="input-group mb-3">
            <input type="password" class="form-control" name="password" placeholder="New Password" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>
          <div class="input-group mb-3">
            <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-12">
              <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </div>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!$invalid_token): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (isset($_SESSION['success'])): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?php echo $_SESSION['success']; ?>',
    confirmButtonColor: '#3085d6'
  });
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
  Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?php echo $_SESSION['error']; ?>',
    confirmButtonColor: '#d33'
  });
</script>
<?php unset($_SESSION['error']); endif; ?>
<?php endif; ?>

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
