<?php
session_start();
include('base_url.php');
require_once __DIR__ . '/theme_helpers.php';

$themePreference = theme_current_preference();
$isDarkTheme = $themePreference === 'dark';
$pageBodyClass = $isDarkTheme ? 'hold-transition dark-mode login-page' : 'hold-transition login-page';

if (isset($_SESSION['user_id'])) {
    header('Location: home');
    exit;
}

$baseYear = 2025;
$currentYear = max($baseYear, (int) date('Y'));
$yearOptions = [];

for ($year = $currentYear; $year >= $baseYear; $year--) {
    $yearOptions[] = (string) $year;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedYear = isset($_POST['year']) ? (string) $_POST['year'] : '';

    if (in_array($selectedYear, $yearOptions, true)) {
        $_SESSION['selected_year'] = $selectedYear;
        header("Location: ./");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="google" content="notranslate">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Select Year</title>
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="dist/css/custom.css">
  <link rel="stylesheet" href="plugins/sweetalert2/sweetalert2.min.css">
  <link rel="shortcut icon" href="<?php echo $base_url;?>kodus/favicon.ico" type="image/x-icon">
  <?php include __DIR__ . '/page_loader.php'; ?>
  <style>
    :root {
      --year-bg-start: #f4f7fb;
      --year-bg-end: #e8f1ff;
      --year-panel-bg: rgba(255, 255, 255, 0.92);
      --year-panel-border: rgba(13, 110, 253, 0.12);
      --year-card-border: rgba(13, 110, 253, 0.14);
      --year-card-hover: rgba(13, 110, 253, 0.08);
      --year-card-active: linear-gradient(135deg, #0d6efd, #3bb0ff);
      --year-text-muted: #5b6472;
      --year-shadow: 0 24px 60px rgba(31, 60, 136, 0.14);
    }

    body.dark-mode,
    body[data-theme="dark"] {
      --year-bg-start: #0f172a;
      --year-bg-end: #172554;
      --year-panel-bg: rgba(15, 23, 42, 0.9);
      --year-panel-border: rgba(148, 163, 184, 0.16);
      --year-card-border: rgba(148, 163, 184, 0.2);
      --year-card-hover: rgba(96, 165, 250, 0.12);
      --year-text-muted: #cbd5e1;
      --year-shadow: 0 24px 60px rgba(2, 6, 23, 0.45);
    }

    body {
      min-height: 100vh;
      margin: 0;
      background:
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.7), transparent 35%),
        linear-gradient(160deg, var(--year-bg-start), var(--year-bg-end));
    }

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
      align-items: center;
      min-height: 100vh;
      padding: 2rem 1rem 8rem;
      margin: 0;
    }
    .kodus-logo {
      width: 88px;
      height: 88px;
      border-radius: 24px;
      background: rgba(255, 255, 255, 0.85);
      box-shadow: 0 14px 35px rgba(31, 60, 136, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.25rem;
    }
    .year-shell {
      width: 100%;
      max-width: 680px;
    }
    .year-panel {
      background: var(--year-panel-bg);
      border: 1px solid var(--year-panel-border);
      border-radius: 28px;
      box-shadow: var(--year-shadow);
      backdrop-filter: blur(14px);
      padding: 2rem;
    }
    .year-kicker {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .45rem .8rem;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.1);
      color: #0d6efd;
      font-weight: 600;
      font-size: .9rem;
      margin-bottom: 1rem;
    }
    .year-title {
      font-size: clamp(1.9rem, 2vw + 1.2rem, 2.7rem);
      font-weight: 700;
      margin-bottom: .75rem;
    }
    .year-subtitle {
      color: var(--year-text-muted);
      font-size: 1rem;
      margin-bottom: 1.75rem;
    }
    .year-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
      margin-bottom: 1.75rem;
    }
    .year-option input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    .year-card {
      position: relative;
      display: block;
      min-height: 170px;
      padding: 1.25rem;
      border-radius: 22px;
      border: 1px solid var(--year-card-border);
      background: rgba(255, 255, 255, 0.72);
      cursor: pointer;
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
      overflow: hidden;
    }
    body.dark-mode .year-card,
    body[data-theme="dark"] .year-card {
      background: rgba(15, 23, 42, 0.7);
    }
    .year-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 18px 38px rgba(31, 60, 136, 0.16);
      background: var(--year-card-hover);
    }
    .year-option input:focus + .year-card {
      outline: 0;
      box-shadow: 0 0 0 .25rem rgba(13, 110, 253, 0.2);
    }
    .year-option input:checked + .year-card {
      color: #fff;
      border-color: transparent;
      background: var(--year-card-active);
      box-shadow: 0 18px 40px rgba(13, 110, 253, 0.28);
    }
    .year-card-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: rgba(13, 110, 253, 0.1);
      color: #0d6efd;
      margin-bottom: 1rem;
      font-size: 1rem;
    }
    .year-option input:checked + .year-card .year-card-badge {
      background: rgba(255, 255, 255, 0.18);
      color: #fff;
    }
    .year-value {
      font-size: 2rem;
      font-weight: 700;
      line-height: 1;
      margin-bottom: .45rem;
    }
    .year-copy {
      color: var(--year-text-muted);
      max-width: 18rem;
      margin-bottom: 0;
    }
    .year-option input:checked + .year-card .year-copy,
    .year-option input:checked + .year-card .year-select-text {
      color: rgba(255, 255, 255, 0.88);
    }
    .year-select-text {
      position: absolute;
      right: 1.25rem;
      bottom: 1.1rem;
      font-weight: 600;
      color: #0d6efd;
    }
    .year-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .year-help {
      color: var(--year-text-muted);
      margin: 0;
    }
    .year-submit {
      min-width: 180px;
      border-radius: 999px;
      padding: .85rem 1.4rem;
      font-weight: 600;
      box-shadow: 0 14px 28px rgba(13, 110, 253, 0.18);
    }
    @media (max-width: 576px) {
      .year-panel {
        padding: 1.35rem;
        border-radius: 24px;
      }
      .year-grid {
        grid-template-columns: 1fr;
      }
      .year-card {
        min-height: 150px;
      }
      .year-actions {
        flex-direction: column;
        align-items: stretch;
      }
      .year-submit {
        width: 100%;
      }
    }
  </style>
</head>
<body class="<?= htmlspecialchars($pageBodyClass, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
    <main class="content">
      <section class="year-shell">
        <div class="year-panel">
          <div class="kodus-logo">
            <img src="<?php echo $base_url;?>kodus/dist/img/kodus.png" alt="KODUSLogo" height="64" width="64">
          </div>

          <div class="text-center mb-4">
            <div class="year-kicker"><i class="fas fa-calendar-alt"></i> Fiscal Year Access</div>
            <h1 class="year-title">Choose the year you want to work on</h1>
            <p class="year-subtitle">Pick a fiscal year to load the right dashboard, records, and reports. You can switch again later if needed.</p>
          </div>

          <form method="POST">
            <div class="year-grid" role="radiogroup" aria-label="Select year to access">
              <?php foreach ($yearOptions as $index => $year): ?>
                <label class="year-option mb-0">
                  <input type="radio" name="year" value="<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                  <span class="year-card">
                    <span class="year-card-badge"><i class="fas fa-folder-open"></i></span>
                    <div class="year-value"><?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></div>
                    <p class="year-copy">Open records, reports, and beneficiary tracking for fiscal year <?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>.</p>
                    <span class="year-select-text">Select</span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="year-actions">
              <p class="year-help">Your selection will be used for this session.</p>
              <button type="submit" class="btn btn-primary year-submit">Continue to KODUS</button>
            </div>
          </form>
        </div>
      </section>
    </main>

    <script src="plugins/sweetalert2/sweetalert2.min.js"></script>
    <?php
      $logoutReason = isset($_GET['logout']) ? (string) $_GET['logout'] : '';
      $logoutStatus = isset($_GET['status']) ? (string) $_GET['status'] : 'success';
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
        text: <?= json_encode($logoutNotice['text']) ?>
      });
    </script>
    <?php endif; ?>
    <script>
      console.group('KODUS Session Info');
      console.log('User Type:', <?= json_encode($_SESSION['user_type'] ?? null) ?>);
      console.log('Selected Fiscal Year:', <?= json_encode($_SESSION['selected_year'] ?? null) ?>);
      console.groupEnd();
    </script>
</body>
</html>
