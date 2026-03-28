<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/app_meta.php';
include('base_url.php');
include('config.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/notification_helpers.php';

// Suppress warnings and deprecated notices, but keep critical errors
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    security_require_csrf_token();
    $positionMap = [
        'Administrative Aide-I' => 'AAide-I',
        'Administrative Aide-II' => 'AAide-II',
        'Administrative Aide-III' => 'AAide-III',
        'Administrative Aide-IV' => 'AAide-IV',
        'Administrative Assistant-I' => 'AdAs-I',
        'Administrative Assistant-II' => 'AdAs-II',
        'Administrative Assistant-III' => 'AdAs-III',
        'Project Development Officer-I' => 'PDO-I',
        'Project Development Officer-II' => 'PDO-II',
        'Project Development Officer-III' => 'PDO-III',
        'Social Welfare Officer-I' => 'SWO-I',
        'Social Welfare Officer-II' => 'SWO-II',
        'Social Welfare Officer-III' => 'SWO-III',
    ];

    // Sanitize and validate inputs
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $ext = isset($_POST['ext']) ? trim($_POST['ext']) : "";
    $username = trim($_POST['username']);
    $position = trim($_POST['position']);
    $positionAbr = $positionMap[$position] ?? '';
    $area = trim($_POST['area']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $date_registered = date('Y-m-d H:i:s');
    
    if (
      empty($last_name) || empty($first_name) || empty($username) || 
      empty($email) || empty($password) || empty($confirm_password) ||
      empty($position) || empty($positionAbr) || empty($area)
    ) {
        echo "<script>
          Swal.fire({
            title: 'Error',
            text: 'All fields are required!',
            icon: 'error'
          }).then(() => {
            history.back();
          });
        </script>";
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<script>Swal.fire("Error", "Invalid email format!", "error").then(() => { history.back(); });</script>';
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo "<script>
          Swal.fire({
              title: 'Error',
              text: 'Passwords do not match!',
              icon: 'error'
          }).then(() => {
              history.back();
          });
      </script>";
      exit;
    }

    if (!security_validate_password_strength($password)) {
        echo "<script>
          Swal.fire({
              title: 'Weak Password',
              text: 'Use at least 8 characters with uppercase, lowercase, number, and symbol.',
              icon: 'error'
          }).then(() => {
              history.back();
          });
      </script>";
      exit;
    }
    
    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo "<script>
          Swal.fire({
            title: 'Error',
            text: 'Username already registered!',
            icon: 'error'
          }).then(() => {
            history.back();
          });
        </script>";
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo "<script>
          Swal.fire({
            title: 'Error',
            text: 'Email already registered!',
            icon: 'error'
          }).then(() => {
            history.back();
          });
        </script>";
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Insert new user securely
    $default_picture = 'default.webp';
    $stmt = $conn->prepare("INSERT INTO users (last_name, first_name, middle_name, ext, position, positionAbr, area, username, email, password, picture, date_registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssss", $last_name, $first_name, $middle_name, $ext, $position, $positionAbr, $area, $username, $email, $hashed_password, $default_picture, $date_registered);
    
    if ($stmt->execute()) {
      $userId = $stmt->insert_id;

      // Log audit
      $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
      $stmt_audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'Register', ?, ?)");
      $details = "$first_name $last_name ($username) registered.";
      $stmt_audit->bind_param("iss", $userId, $details, $ip);
      $stmt_audit->execute();
      $stmt_audit->close();

      $successScript = "<script>
        Swal.fire('Success', 'Registration successful!', 'success').then(() => {
          window.location.href = './';
        });
      </script>";
      notification_finish_response($successScript);

      $mail = new PHPMailer(true);
      $mailStatus = 'Sent';
      $mailSubject = 'Welcome to KODUS!';
      $mailBody = notification_render_email_shell(
          'Welcome',
          'Your KODUS Account Is Ready',
          'Thanks for registering. Your account has been created successfully.',
          '<p>Hello <strong>' . htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
          . '<p>Welcome to <strong>KODUS</strong>. Your account is now active.</p>'
          . notification_render_detail_rows([
              'Username' => $username,
              'Registered Email' => $email,
          ])
          . '<p>You can now sign in and start using the platform.</p>'
          . notification_render_action_button(($base_url ?? './') . 'kodus/', 'Open KODUS', '#198754'),
          '#198754'
      );

      try {
          app_configure_mailer($mail);
          $mail->addAddress($email, "$first_name $last_name");
          $mail->isHTML(true);
          $mail->Subject = $mailSubject;
          $mail->Body = $mailBody;
          $mail->send();
      } catch (Exception $e) {
          $mailStatus = 'Failed';
      }

      $stmt_mail = $conn->prepare("INSERT INTO mail_logs (recipient, subject, status, message) VALUES (?, ?, ?, ?)");
      $stmt_mail->bind_param("ssss", $email, $mailSubject, $mailStatus, $mailBody);
      $stmt_mail->execute();
      $stmt_mail->close();
  } else {
      echo '<script>Swal.fire("Error", "Registration failed!", "error");</script>';
  }
    
    $stmt->close();
    $conn->close();
    exit;
}

$themePreference = theme_current_preference();
$isDarkTheme = $themePreference === 'dark';
$registerBodyClass = $isDarkTheme ? 'hold-transition dark-mode register-page' : 'hold-transition register-page';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Registration Page</title>
  <link rel="shortcut icon" href="<?php echo $base_url; ?>kodus/favicon.ico" type="image/x-icon">

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <?php include __DIR__ . '/page_loader.php'; ?>
  <style>
    :root {
      --register-footer-clearance: calc(1in + 5.5rem);
      --register-panel-bg: #ffffff;
      --register-panel-border: rgba(13, 27, 42, 0.08);
      --register-muted: #6c757d;
      --register-card-bg: #ffffff;
      --register-card-shadow: 0 16px 50px rgba(15, 23, 42, 0.12);
      --register-text: #1f2937;
      --register-label: #334155;
      --register-input-bg: #ffffff;
      --register-input-border: rgba(13, 27, 42, 0.12);
      --register-footer-bg: #ffffff;
      --register-footer-border: rgba(13, 27, 42, 0.08);
      --register-page-bg: linear-gradient(180deg, #eef3f8 0%, #f8fafc 100%);
      --register-accent-panel: linear-gradient(135deg, rgba(13, 110, 253, 0.12), rgba(32, 201, 151, 0.08));
      --register-criteria: #6b7280;
      --register-modal-item-bg: rgba(15, 23, 42, 0.03);
      --register-modal-item-border: rgba(15, 23, 42, 0.08);
      --register-theme-badge-bg: rgba(255, 255, 255, 0.72);
      --register-theme-badge-text: #0f172a;
    }
    body[data-theme="dark"] {
      --register-panel-bg: rgba(255, 255, 255, 0.045);
      --register-panel-border: rgba(255, 255, 255, 0.12);
      --register-muted: #adb5bd;
      --register-card-bg: #343a40;
      --register-card-shadow: 0 16px 50px rgba(0, 0, 0, 0.25);
      --register-text: #f8f9fa;
      --register-label: #e9ecef;
      --register-input-bg: rgba(255, 255, 255, 0.02);
      --register-input-border: rgba(255, 255, 255, 0.09);
      --register-footer-bg: #343a40;
      --register-footer-border: rgba(255, 255, 255, 0.08);
      --register-page-bg: linear-gradient(180deg, #2f3542 0%, #1f2530 100%);
      --register-accent-panel: linear-gradient(135deg, rgba(13, 110, 253, 0.16), rgba(32, 201, 151, 0.1));
      --register-criteria: #adb5bd;
      --register-modal-item-bg: rgba(255, 255, 255, 0.04);
      --register-modal-item-border: rgba(255, 255, 255, 0.08);
      --register-theme-badge-bg: rgba(15, 23, 42, 0.5);
      --register-theme-badge-text: #f8f9fa;
    }
    body.register-page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 1.5rem 1rem var(--register-footer-clearance);
      overflow-y: auto;
      color: var(--register-text);
      background: var(--register-page-bg);
    }
    .register-box {
      flex: 1 0 auto;
      width: 100%;
      max-width: 900px;
      margin: 0 auto var(--register-footer-clearance);
    }
    .register-logo {
      margin-bottom: 0.75rem;
    }
    .register-logo a {
      letter-spacing: 0.08em;
      font-weight: 700;
    }
    .card {
      background: var(--register-card-bg);
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: var(--register-card-shadow);
    }
    .register-card-body {
      padding: 1.25rem 1.25rem var(--register-footer-clearance);
    }
    .register-intro {
      margin-bottom: 1rem;
      padding: 0.9rem 1rem;
      border-radius: 0.75rem;
      background: var(--register-accent-panel);
      border: 1px solid var(--register-panel-border);
    }
    .register-intro h1 {
      margin: 0 0 0.25rem;
      font-size: 1.2rem;
      font-weight: 700;
    }
    .register-intro-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.35rem;
    }
    .theme-preview-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      background: var(--register-theme-badge-bg);
      color: var(--register-theme-badge-text);
      font-size: 0.74rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      white-space: nowrap;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    }
    .theme-preview-dot {
      width: 0.6rem;
      height: 0.6rem;
      border-radius: 999px;
      background: linear-gradient(135deg, #0d6efd, #20c997);
      box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.16);
    }
    .register-intro p {
      margin: 0;
      color: var(--register-muted);
      font-size: 0.92rem;
    }
    .form-section {
      margin-bottom: 0.9rem;
      padding: 1rem;
      border-radius: 0.85rem;
      background: var(--register-panel-bg);
      border: 1px solid var(--register-panel-border);
    }
    .form-section-title {
      margin: 0 0 0.75rem;
      font-size: 0.78rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #ced4da;
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.75rem;
    }
    .field {
      min-width: 0;
    }
    .field.field-full {
      grid-column: 1 / -1;
    }
    .field-label {
      display: block;
      margin-bottom: 0.35rem;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--register-label);
    }
    .field-note {
      display: block;
      margin-top: 0.35rem;
      font-size: 0.76rem;
      color: var(--register-muted);
    }
    .input-group {
      margin-bottom: 0;
    }
    .input-group-text,
    .form-control {
      min-height: 42px;
    }
    .form-control,
    .input-group-text {
      border-color: var(--register-input-border);
      background: var(--register-input-bg);
      color: var(--register-text);
    }
    .form-control::placeholder {
      color: var(--register-muted);
    }
    .form-control:focus {
      background: var(--register-input-bg);
      color: var(--register-text);
    }
    .form-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-top: 0.9rem;
      padding: 0.9rem 1rem;
      border-radius: 0.85rem;
      background: rgba(255, 255, 255, 0.035);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .form-actions .icheck-primary {
      margin-bottom: 0;
    }
    .terms-link {
      font-weight: 600;
      text-decoration: underline;
      text-underline-offset: 2px;
    }
    .register-links {
      margin-top: 0.9rem;
      text-align: center;
    }
    .register-links a,
    .register-logo a,
    .terms-link {
      color: inherit;
    }
    footer.main-footer {
      position: fixed !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 100vw !important;
      max-width: 100vw !important;
      margin: 0 !important;
      margin-left: 0 !important;
      z-index: 1030;
      padding: 1rem 1.5rem;
      box-sizing: border-box;
      background: var(--register-footer-bg);
      border-top: 1px solid var(--register-footer-border);
    }
    .password-panel {
      margin-top: 0.6rem;
      margin-bottom: 0;
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
      color: var(--register-criteria);
      transition: color 0.2s ease;
    }
    .password-criteria li.is-valid {
      color: #28a745;
    }
    .password-hint,
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
    .form-control[readonly] {
      background-color: var(--register-modal-item-bg);
    }
    .terms-modal {
      text-align: left;
    }
    .terms-modal-header {
      margin-bottom: 1rem;
      padding: 1rem;
      border-radius: 0.85rem;
      background: var(--register-accent-panel);
      border: 1px solid var(--register-panel-border);
    }
    .terms-modal-header h3 {
      margin: 0 0 0.35rem;
      font-size: 1.15rem;
    }
    .terms-modal-header p {
      margin: 0;
      color: var(--register-muted);
    }
    .terms-list {
      display: grid;
      gap: 0.75rem;
      max-height: 48vh;
      padding-right: 0.35rem;
      overflow-y: auto;
    }
    .terms-item {
      padding: 0.9rem 1rem;
      border-radius: 0.85rem;
      background: var(--register-modal-item-bg);
      border: 1px solid var(--register-modal-item-border);
    }
    .terms-item h4 {
      margin: 0 0 0.35rem;
      font-size: 0.95rem;
    }
    .terms-item p {
      margin: 0;
      color: var(--register-muted);
      font-size: 0.9rem;
    }
    .terms-agreement {
      margin-top: 1rem;
      padding: 0.9rem 1rem;
      border-radius: 0.85rem;
      background: var(--register-modal-item-bg);
      border: 1px solid var(--register-modal-item-border);
    }
    @media (max-width: 576px) {
      body.register-page {
        padding: 1rem 0.75rem var(--register-footer-clearance);
      }
      .register-box {
        max-width: 100%;
        margin-bottom: var(--register-footer-clearance);
      }
      .register-card-body {
        padding: 1rem 1rem var(--register-footer-clearance);
      }
      .form-grid {
        grid-template-columns: 1fr;
      }
      .register-intro-top {
        flex-direction: column;
        align-items: flex-start;
      }
      .password-strength-row {
        flex-direction: column;
        align-items: flex-start;
      }
      .form-actions {
        flex-direction: column;
        align-items: stretch;
      }
    }
  </style>
</head>
<body class="<?= htmlspecialchars($registerBodyClass, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
<div class="register-box">
  <div class="register-logo">
    <a href="./"><b>KODUS</b></a>
  </div>

  <div class="card">
    <div class="card-body register-card-body">
      <div class="register-intro">
        <div class="register-intro-top">
          <h1>Create Your Account</h1>
          <span class="theme-preview-badge">
            <span class="theme-preview-dot"></span>
            <?= $isDarkTheme ? 'Dark Theme' : 'Light Theme' ?>
          </span>
        </div>
        <p>Fill in your details, review the password requirements, and confirm the terms to finish registration.</p>
      </div>

      <form action="register" id="registerForm" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <section class="form-section">
          <h2 class="form-section-title">Personal Information</h2>
          <div class="form-grid">
            <div class="field">
              <label class="field-label" for="lastName">Last Name</label>
              <div class="input-group">
                <input type="text" id="lastName" name="last_name" class="form-control" placeholder="Enter last name" required>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-user"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="field">
              <label class="field-label" for="firstName">First Name</label>
              <div class="input-group">
                <input type="text" id="firstName" name="first_name" class="form-control" placeholder="Enter first name" required>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-user"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="field">
              <label class="field-label" for="middleName">Middle Name</label>
              <div class="input-group">
                <input type="text" id="middleName" name="middle_name" class="form-control" placeholder="Enter middle name" required>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-user"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="field">
              <label class="field-label" for="extName">Extension</label>
              <div class="input-group">
                <select id="extName" class="form-control" name="ext" class="form-control" placeholder="Extension name">
                  <option selected disabled>Optional suffix</option>
                  <option value="Jr.">Jr.</option>
                  <option value="Sr.">Sr.</option>
                  <option value="II">II</option>
                  <option value="III">III</option>
                  <option value="IV">IV</option>
                  <option value="V">V</option>
                </select>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-id-card"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="form-section">
          <h2 class="form-section-title">Work Information</h2>
          <div class="form-grid">
            <div class="field">
              <label class="field-label" for="position">Position</label>
              <div class="input-group">
                <select id="position" class="form-control" name="position" class="form-control" placeholder="Position" required>
                  <option selected disabled>Select position</option>
                  <option value="Administrative Aide-I">Administrative Aide-I</option>
                  <option value="Administrative Aide-II">Administrative Aide-II</option>
                  <option value="Administrative Aide-III">Administrative Aide-III</option>
                  <option value="Administrative Aide-IV">Administrative Aide-IV</option>
                  <option value="Administrative Assistant-I">Administrative Assistant-I</option>
                  <option value="Administrative Assistant-II">Administrative Assistant-II</option>
                  <option value="Administrative Assistant-III">Administrative Assistant-III</option>
                  <option value="Project Development Officer-I">Project Development Officer-I</option>
                  <option value="Project Development Officer-II">Project Development Officer-II</option>
                  <option value="Project Development Officer-III">Project Development Officer-III</option>
                  <option value="Social Welfare Officer-I">Social Welfare Officer-I</option>
                  <option value="Social Welfare Officer-II">Social Welfare Officer-II</option>
                  <option value="Social Welfare Officer-III">Social Welfare Officer-III</option>
                </select>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-user-tie"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="field">
              <label class="field-label" for="positionAbr">Position Abbreviation</label>
              <div class="input-group">
                <input type="text" id="positionAbr" name="positionAbr" class="form-control" placeholder="Auto-filled abbreviation" readonly required>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-tag"></span>
                  </div>
                </div>
              </div>
              <small class="field-note">Filled automatically from the selected position.</small>
            </div>
            <div class="field field-full">
              <label class="field-label" for="area">Area of Assignment</label>
              <div class="input-group">
                <select id="area" class="form-control" name="area" class="form-control" placeholder="Area of Assignment" required>
                  <option selected disabled>Select area of assignment</option>
                  <option value="Field Office">Field Office</option>
                  <option value="Agusan del Norte">Agusan del Norte</option>
                  <option value="Agusan del Sur">Agusan del Sur</option>
                  <option value="Dinagat Islands">Dinagat Islands</option>
                  <option value="Surigao del Norte">Surigao del Norte</option>
                  <option value="Surigao del Sur">Surigao del Sur</option>
                </select>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-map-marker-alt"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="form-section">
          <h2 class="form-section-title">Account Details</h2>
          <div class="form-grid">
            <div class="field">
              <label class="field-label" for="username">Username</label>
              <div class="input-group">
                <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-user"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="field">
              <label class="field-label" for="email">Email Address</label>
              <div class="input-group">
                <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" required>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="field field-full">
              <label class="field-label" for="password">Password</label>
              <div class="input-group">
                <input type="password" id="password" name="password" class="form-control" placeholder="Create a strong password" aria-describedby="passwordFeedback" required>
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
                <small id="passwordFeedback" class="field-feedback is-invalid">Use at least 8 characters with uppercase, lowercase, number, and symbol.</small>
                <ul class="password-criteria">
                  <li data-rule="length">At least 8 characters</li>
                  <li data-rule="lower">At least one lowercase letter</li>
                  <li data-rule="upper">At least one uppercase letter</li>
                  <li data-rule="number">At least one number</li>
                  <li data-rule="symbol">At least one symbol</li>
                </ul>
              </div>
            </div>
            <div class="field field-full">
              <label class="field-label" for="confirmPassword">Confirm Password</label>
              <div class="input-group">
                <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Retype your password" aria-describedby="confirmPasswordFeedback" required>
                <div class="input-group-append">
                  <button type="button" class="input-group-text toggle-password" data-target="confirmPassword" aria-label="Show password">
                    <span class="fas fa-eye"></span>
                  </button>
                </div>
              </div>
              <small id="confirmPasswordFeedback" class="field-feedback"></small>
            </div>
          </div>
        </section>

        <div class="form-actions">
          <div class="icheck-primary">
            <input type="checkbox" id="agreeTerms" name="terms" value="agree">
            <label for="agreeTerms">
              I agree to the <a href="#" id="openTerms" class="terms-link">Terms and Conditions</a>
            </label>
          </div>
          <div>
            <button type="submit" id="registerBtn" class="btn btn-primary px-4">Create Account</button>
          </div>
        </div>
      </form>
      <div class="register-links">
        <a href="./" class="text-center">I already have a membership</a>
      </div>
    </div>
    <!-- /.form-box -->
  </div><!-- /.card -->
</div>
<!-- /.register-box -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
  const positionMap = {
    'Administrative Aide-I': 'AAide-I',
    'Administrative Aide-II': 'AAide-II',
    'Administrative Aide-III': 'AAide-III',
    'Administrative Aide-IV': 'AAide-IV',
    'Administrative Assistant-I': 'AdAs-I',
    'Administrative Assistant-II': 'AdAs-II',
    'Administrative Assistant-III': 'AdAs-III',
    'Project Development Officer-I': 'PDO-I',
    'Project Development Officer-II': 'PDO-II',
    'Project Development Officer-III': 'PDO-III',
    'Social Welfare Officer-I': 'SWO-I',
    'Social Welfare Officer-II': 'SWO-II',
    'Social Welfare Officer-III': 'SWO-III'
  };
  const strengthConfig = [
    { rule: 'length', test: (value) => value.length >= 8 },
    { rule: 'lower', test: (value) => /[a-z]/.test(value) },
    { rule: 'upper', test: (value) => /[A-Z]/.test(value) },
    { rule: 'number', test: (value) => /\d/.test(value) },
    { rule: 'symbol', test: (value) => /[^a-zA-Z0-9]/.test(value) }
  ];
  const strengthPalette = ['#dc3545', '#fd7e14', '#ffc107', '#20c997'];
  const strengthLabels = ['Too weak', 'Weak', 'Fair', 'Strong'];
  const $position = $('select[name="position"]');
  const $positionAbr = $('#positionAbr');
  const $password = $('#password');
  const $confirmPassword = $('#confirmPassword');
  const $passwordFeedback = $('#passwordFeedback');
  const $confirmFeedback = $('#confirmPasswordFeedback');
  const $strengthLabel = $('#passwordStrengthLabel');
  const $strengthBars = $('.strength-meter span');

  // Disable register button by default
  $('#registerBtn').prop('disabled', true);

  function syncPositionAbbreviation() {
    const abbreviation = positionMap[$position.val()] || '';
    $positionAbr.val(abbreviation);
  }

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
    const passwordValue = $password.val();
    const confirmValue = $confirmPassword.val();
    const state = evaluatePassword(passwordValue);

    strengthConfig.forEach((item) => {
      const isValid = item.test(passwordValue);
      $(`.password-criteria [data-rule="${item.rule}"]`).toggleClass('is-valid', isValid);
    });

    $strengthLabel.text(passwordValue ? strengthLabels[state.bucket] : 'Too weak');
    $strengthBars.each(function(index) {
      const active = passwordValue && index <= state.bucket;
      $(this).css('background-color', active ? strengthPalette[state.bucket] : 'rgba(255, 255, 255, 0.12)');
      $(this).css('opacity', active ? '1' : '0.45');
    });

    if (!passwordValue) {
      $passwordFeedback
        .text('Use at least 8 characters with uppercase, lowercase, number, and symbol.')
        .removeClass('is-valid')
        .addClass('is-invalid');
      $password[0].setCustomValidity('Password does not meet the required strength.');
    } else if (state.meetsAll) {
      $passwordFeedback
        .text('Password meets all strength requirements.')
        .removeClass('is-invalid')
        .addClass('is-valid');
      $password[0].setCustomValidity('');
    } else {
      $passwordFeedback
        .text('Password still needs the missing criteria below.')
        .removeClass('is-valid')
        .addClass('is-invalid');
      $password[0].setCustomValidity('Password does not meet the required strength.');
    }

    if (!confirmValue) {
      $confirmFeedback.text('').removeClass('is-valid is-invalid');
      $confirmPassword[0].setCustomValidity('');
      return;
    }

    if (passwordValue === confirmValue) {
      $confirmFeedback.text('Passwords match.').removeClass('is-invalid').addClass('is-valid');
      $confirmPassword[0].setCustomValidity('');
    } else {
      $confirmFeedback.text('Passwords do not match.').removeClass('is-valid').addClass('is-invalid');
      $confirmPassword[0].setCustomValidity('Passwords do not match.');
    }
  }

  $('#openTerms').click(function(e) {
    e.preventDefault();
    
    Swal.fire({
      title: 'Terms and Conditions',
      width: '760px',
      customClass: {
        popup: 'swal2-border-radius'
      },
      html: `
        <div class="terms-modal">
          <div class="terms-modal-header">
            <h3>KODUS Registration Agreement</h3>
            <p>Please review the points below before creating your account. Your registration means you agree to use the system responsibly and protect account access.</p>
          </div>
          <div class="terms-list">
            <div class="terms-item">
              <h4>1. Account Accuracy</h4>
              <p>You agree to provide complete, accurate, and current registration details. Misleading or incomplete information may delay approval or limit account access.</p>
            </div>
            <div class="terms-item">
              <h4>2. Responsible Use</h4>
              <p>Your account is for authorized KODUS-related work only. You must not use the system for disruptive, unlawful, or harmful activity.</p>
            </div>
            <div class="terms-item">
              <h4>3. Password and Access Security</h4>
              <p>You are responsible for protecting your username, password, and any activity performed under your account. Share credentials with no one.</p>
            </div>
            <div class="terms-item">
              <h4>4. Data Handling</h4>
              <p>Information inside KODUS may contain sensitive operational or personal data. Handle it carefully and only for legitimate work purposes.</p>
            </div>
            <div class="terms-item">
              <h4>5. Monitoring and Audit</h4>
              <p>System activity may be logged for security, support, and compliance. By continuing, you acknowledge that important actions may be recorded.</p>
            </div>
            <div class="terms-item">
              <h4>6. Updates to These Terms</h4>
              <p>KODUS administrators may revise these terms when workflows, security requirements, or policies change. Continued use means you accept reasonable updates.</p>
            </div>
            <div class="terms-item">
              <h4>7. Support and Questions</h4>
              <p>If anything is unclear, confirm with your administrator before completing registration or using the platform for live work.</p>
            </div>
          </div>
          <div class="terms-agreement">
            <div class="icheck-primary">
              <input type="checkbox" id="modalAgreeTerms">
              <label for="modalAgreeTerms">I have read and agree to these Terms and Conditions.</label>
            </div>
          </div>
        </div>
      `,
      confirmButtonText: 'Done',
      showCloseButton: true,
      focusConfirm: false,
      didOpen: () => {
        if ($('#agreeTerms').prop('checked')) {
          $('#modalAgreeTerms').prop('checked', true);
        }

        $('#modalAgreeTerms').change(function() {
          $('#agreeTerms').prop('checked', this.checked).trigger('change');
        });
      }
    });
  });

  $('#agreeTerms').change(function() {
    $('#registerBtn').prop('disabled', !this.checked);
  }).trigger('change');

  $position.on('change', syncPositionAbbreviation);
  $password.on('input blur', updatePasswordFeedback);
  $confirmPassword.on('input blur', updatePasswordFeedback);

  $('.toggle-password').on('click', function() {
    const target = document.getElementById($(this).data('target'));
    const icon = $(this).find('span');
    const isPassword = target.type === 'password';

    target.type = isPassword ? 'text' : 'password';
    $(this).attr('aria-label', isPassword ? 'Hide password' : 'Show password');
    icon.toggleClass('fa-eye fa-eye-slash');
  });

  syncPositionAbbreviation();
  updatePasswordFeedback();
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("registerForm");
  const positionField = document.getElementById("positionAbr");

  form.addEventListener("submit", function (e) {
    if (positionField && !positionField.value) {
      positionField.setCustomValidity("Please select a position first.");
    } else if (positionField) {
      positionField.setCustomValidity("");
    }

    if (!form.checkValidity()) {
      e.preventDefault();
      form.reportValidity();
      return;
    }

    e.preventDefault(); // Prevent default submission

    Swal.fire({
      icon: 'info',
      title: 'Registering...',
      text: 'Please wait while we process your registration.',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
        form.submit(); // Submit after alert shows
      }
    });
  });
});
</script>
  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
      Version <?= htmlspecialchars(app_version_label(), ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(app_release_label(), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <!-- Default to the left -->
    <strong>KliMalasakit Online Document Updating System</a>.</strong>
  </footer>
</body>
</html>
