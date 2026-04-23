<?php
include('header.php');
include('config.php'); 

$invalid_token = false;
$token = '';
$enforcedReset = isset($_GET['enforced']) && $_GET['enforced'] === '1';
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
  <link rel="shortcut icon" href="<?php echo $app_root; ?>favicon.ico" type="image/x-icon">
  <script src="plugins/sweetalert2/sweetalert2.min.js"></script>
  <link rel="stylesheet" href="fonts.googleapis.com/css/fontfamily.css">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <style>
    .main-footer {
      margin-left: 0 !important;
    }
    .password-panel {
      margin-top: 0.6rem;
      margin-bottom: 1rem;
      padding: 0.85rem 1rem;
      border-radius: 0.5rem;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .password-strength-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.75rem;
      font-size: 0.9rem;
    }
    .strength-meter {
      display: flex;
      gap: 0.35rem;
      flex: 1;
    }
    .strength-meter span {
      height: 0.35rem;
      flex: 1;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      transition: background-color 0.2s ease, opacity 0.2s ease;
      opacity: 0.45;
    }
    .password-criteria {
      margin: 0;
      padding-left: 1.1rem;
      font-size: 0.85rem;
    }
    .password-criteria li {
      margin-bottom: 0.3rem;
      color: #adb5bd;
      transition: color 0.2s ease;
    }
    .password-criteria li.is-valid {
      color: #28a745;
    }
    .field-feedback {
      display: block;
      font-size: 0.8rem;
      margin-top: 0.4rem;
    }
    .field-feedback.is-invalid {
      color: #ff7675;
    }
    .field-feedback.is-valid {
      color: #28a745;
    }
    .toggle-password {
      cursor: pointer;
      min-width: 44px;
      justify-content: center;
    }
    @media (max-width: 576px) {
      .password-strength-row {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body class="hold-transition login-page">
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
        <p class="login-box-msg"><?= $enforcedReset ? 'Your password must be updated before you can sign in.' : 'Enter your new password' ?></p>
        <?php if ($enforcedReset): ?>
          <div class="alert alert-warning text-left" role="alert">
            <i class="fas fa-shield-alt"></i> Use at least <?= htmlspecialchars((string) security_password_min_length(), ENT_QUOTES, 'UTF-8') ?> characters with uppercase, lowercase, number, and symbol.
          </div>
        <?php endif; ?>
        <form id="resetPasswordForm" action="update-password.php" method="post">
          <input type="hidden" name="reset_submitted" value="1">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="input-group mb-3">
            <input type="password" class="form-control" id="password" name="password" placeholder="New Password" aria-describedby="passwordFeedback" required>
            <div class="input-group-append">
              <button type="button" class="input-group-text toggle-password" data-target="password" aria-label="Show password">
                <span class="fas fa-eye"></span>
              </button>
            </div>
          </div>
          <div class="password-panel">
            <div class="password-strength-row">
              <strong>Password strength: <span id="passwordStrengthLabel">Too weak</span></strong>
              <div class="strength-meter" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
              </div>
            </div>
            <small id="passwordFeedback" class="field-feedback is-invalid">Use at least <?= htmlspecialchars((string) security_password_min_length(), ENT_QUOTES, 'UTF-8') ?> characters with uppercase, lowercase, number, and symbol.</small>
            <ul class="password-criteria">
              <li data-rule="length">At least <?= htmlspecialchars((string) security_password_min_length(), ENT_QUOTES, 'UTF-8') ?> characters</li>
              <li data-rule="lower">At least one lowercase letter</li>
              <li data-rule="upper">At least one uppercase letter</li>
              <li data-rule="number">At least one number</li>
              <li data-rule="symbol">At least one symbol</li>
            </ul>
          </div>
          <div class="input-group mb-3">
            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" placeholder="Confirm Password" aria-describedby="confirmPasswordFeedback" required>
            <div class="input-group-append">
              <button type="button" class="input-group-text toggle-password" data-target="confirmPassword" aria-label="Show password">
                <span class="fas fa-eye"></span>
              </button>
            </div>
          </div>
          <small id="confirmPasswordFeedback" class="field-feedback"></small>
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
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>

<?php if (isset($_SESSION['password_policy_notice']) && is_array($_SESSION['password_policy_notice'])): ?>
<script>
  Swal.fire({
    icon: <?= json_encode($_SESSION['password_policy_notice']['icon'] ?? 'info') ?>,
    title: <?= json_encode($_SESSION['password_policy_notice']['title'] ?? 'Security Notice') ?>,
    text: <?= json_encode($_SESSION['password_policy_notice']['text'] ?? '') ?>,
    confirmButtonColor: '#d33'
  });
</script>
<?php unset($_SESSION['password_policy_notice']); endif; ?>

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

<script>
document.addEventListener('DOMContentLoaded', function () {
  const passwordField = document.getElementById('password');
  const confirmField = document.getElementById('confirmPassword');
  const passwordFeedback = document.getElementById('passwordFeedback');
  const confirmFeedback = document.getElementById('confirmPasswordFeedback');
  const strengthLabel = document.getElementById('passwordStrengthLabel');
  const strengthBars = document.querySelectorAll('.strength-meter span');
  const minLength = <?= json_encode(security_password_min_length()) ?>;
  const strengthConfig = [
    { rule: 'length', test: (value) => value.length >= minLength },
    { rule: 'lower', test: (value) => /[a-z]/.test(value) },
    { rule: 'upper', test: (value) => /[A-Z]/.test(value) },
    { rule: 'number', test: (value) => /\d/.test(value) },
    { rule: 'symbol', test: (value) => /[^a-zA-Z0-9]/.test(value) }
  ];
  const strengthPalette = ['#dc3545', '#fd7e14', '#ffc107', '#20c997'];
  const strengthLabels = ['Too weak', 'Weak', 'Fair', 'Strong'];

  function evaluatePassword(value) {
    const passingRules = strengthConfig.filter((item) => item.test(value));
    const score = passingRules.length;
    const meetsAll = score === strengthConfig.length;
    let bucket = 0;

    if (score >= 5) {
      bucket = 3;
    } else if (score >= 4) {
      bucket = 2;
    } else if (score >= 3) {
      bucket = 1;
    }

    return { score, meetsAll, bucket };
  }

  function updatePasswordFeedback() {
    const passwordValue = passwordField.value;
    const confirmValue = confirmField.value;
    const state = evaluatePassword(passwordValue);

    strengthConfig.forEach((item) => {
      const isValid = item.test(passwordValue);
      document.querySelector(`.password-criteria [data-rule="${item.rule}"]`)?.classList.toggle('is-valid', isValid);
    });

    strengthLabel.textContent = passwordValue ? strengthLabels[state.bucket] : 'Too weak';
    strengthBars.forEach((bar, index) => {
      const active = passwordValue && index <= state.bucket;
      bar.style.backgroundColor = active ? strengthPalette[state.bucket] : 'rgba(255, 255, 255, 0.12)';
      bar.style.opacity = active ? '1' : '0.45';
    });

    if (!passwordValue) {
      passwordFeedback.textContent = `Use at least ${minLength} characters with uppercase, lowercase, number, and symbol.`;
      passwordFeedback.classList.remove('is-valid');
      passwordFeedback.classList.add('is-invalid');
      passwordField.setCustomValidity('Password does not meet the required strength.');
    } else if (state.meetsAll) {
      passwordFeedback.textContent = 'Password meets all strength requirements.';
      passwordFeedback.classList.remove('is-invalid');
      passwordFeedback.classList.add('is-valid');
      passwordField.setCustomValidity('');
    } else {
      passwordFeedback.textContent = 'Password still needs the missing criteria below.';
      passwordFeedback.classList.remove('is-valid');
      passwordFeedback.classList.add('is-invalid');
      passwordField.setCustomValidity('Password does not meet the required strength.');
    }

    if (!confirmValue) {
      confirmFeedback.textContent = '';
      confirmFeedback.classList.remove('is-valid', 'is-invalid');
      confirmField.setCustomValidity('');
      return;
    }

    if (passwordValue === confirmValue) {
      confirmFeedback.textContent = 'Passwords match.';
      confirmFeedback.classList.remove('is-invalid');
      confirmFeedback.classList.add('is-valid');
      confirmField.setCustomValidity('');
    } else {
      confirmFeedback.textContent = 'Passwords do not match.';
      confirmFeedback.classList.remove('is-valid');
      confirmFeedback.classList.add('is-invalid');
      confirmField.setCustomValidity('Passwords do not match.');
    }
  }

  passwordField.addEventListener('input', updatePasswordFeedback);
  passwordField.addEventListener('blur', updatePasswordFeedback);
  confirmField.addEventListener('input', updatePasswordFeedback);
  confirmField.addEventListener('blur', updatePasswordFeedback);

  document.querySelectorAll('.toggle-password').forEach((button) => {
    button.addEventListener('click', function () {
      const target = document.getElementById(this.dataset.target);
      const icon = this.querySelector('span');
      const isPassword = target.type === 'password';

      target.type = isPassword ? 'text' : 'password';
      this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    });
  });

  updatePasswordFeedback();
});
</script>
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
