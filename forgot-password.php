<?php
  include ('header.php');
  error_reporting(E_ERROR | E_PARSE);
  ini_set('display_errors', '0');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Forgot Password</title>
  <link rel="shortcut icon" href="<?php echo $base_url; ?>kodus/favicon.ico" type="image/x-icon">

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/fonts.googleapis.com/css/fontfamily.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="dist/css/custom.css">
  <!-- SweetAlert2 -->
  <script src="cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    html {
      overflow-y: scroll;
    }

    #loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 9999;
      background-color: rgba(0, 0, 0, 0.85);
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      display: none;
    }
    #loading-overlay img {
      animation: spin 1.5s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    #loading-overlay p {
      color: white;
      margin-top: 20px;
      font-size: 1.2rem;
    }
    @media (max-height: 600px) {
      .main-footer {
        position: static !important;
        margin-top: 10px !important;
        margin-bottom: -110px !important;
      }
      .one {
        display: none;
      }
    }
    .main-footer {
      width: 100%;
      left: 0;
      right: 0;
      margin-left: 0 !important;
    }
  </style>
</head>
<body class="hold-transition dark-mode login-page">

  <!-- Loading Screen -->
  <div id="loading-overlay">
    <img src="dist/img/kodus.png" alt="Loading" width="100" height="100">
    <p>Sending reset link...</p>
  </div>

  <div class="login-box">
    <div class="login-logo">
      <a href="./"><b>KODUS</b></a>
    </div>
    <div class="card">
      <div class="card-body login-card-body">

        <p class="login-box-msg">You forgot your password? Here you can easily retrieve a new password.</p>

        <form id="forgot-password-form" action="send-reset-link.php" method="post" data-no-loader="true">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="input-group mb-3">
            <input type="email" class="form-control" name="email" placeholder="Email" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-envelope"></span>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-12">
              <button type="submit" class="btn btn-primary btn-block">Request new password</button>
            </div>
          </div>
        </form>

        <p class="mt-3 mb-1">
          <a href="./">Login</a>
        </p>
        <p class="mb-0">
          <a href="register" class="text-center">Register a new membership</a>
        </p>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE App -->
  <script src="dist/js/adminlte.min.js"></script>

  <!-- Show loading overlay on form submit -->
  <script>
    document.getElementById('forgot-password-form').addEventListener('submit', function () {
      document.getElementById('loading-overlay').style.display = 'flex';
    });
  </script>

  <!-- SweetAlert2 notification -->
  <?php if (isset($_SESSION['status']) && isset($_SESSION['status_type'])): ?>
  <script>
    Swal.fire({
      icon: '<?php echo $_SESSION['status_type']; ?>',
      title: '<?php echo $_SESSION['status_type'] === "success" ? "Success!" : "Oops..."; ?>',
      text: '<?php echo $_SESSION['status']; ?>',
      confirmButtonColor: '#3085d6'
    }).then((result) => {
      <?php if ($_SESSION['status_type'] === "success"): ?>
        if (result.isConfirmed) {
          window.location.href = "index"; // Redirect after success
        }
      <?php endif; ?>
    });
  </script>
  <?php 
    unset($_SESSION['status']);
    unset($_SESSION['status_type']);
  endif; ?>
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
