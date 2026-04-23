<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/env_helpers.php';
require_once __DIR__ . '/app_location_helpers.php';
include('base_url.php');
require_once __DIR__ . '/theme_helpers.php';
require_once __DIR__ . '/koda_helpers.php';

app_load_environment();
app_apply_current_timezone();

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
$kodaIdentity = koda_resolve_identity();
$kodaPalette = $kodaIdentity['palette'];
$kodaFace = $kodaIdentity['face'];
$sessionSelectedYear = isset($_SESSION['selected_year']) ? (string) $_SESSION['selected_year'] : '';

for ($year = $currentYear; $year >= $baseYear; $year--) {
    $yearOptions[] = (string) $year;
}

if (!in_array($sessionSelectedYear, $yearOptions, true)) {
    $sessionSelectedYear = $yearOptions[0] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    security_require_csrf_token();
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
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>dist/css/adminlte.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>dist/css/custom.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.css">
  <link rel="shortcut icon" href="<?php echo $app_root; ?>favicon.ico" type="image/x-icon">
  <?php include __DIR__ . '/page_loader.php'; ?>
  <script>
    window.KODUS_CSRF_TOKEN = <?php echo json_encode(security_get_csrf_token()); ?>;
    window.KODUS_LOCATION_CONTEXT = <?php echo json_encode([
        'endpoint' => $app_root . 'save_location_context.php',
        'csrfToken' => security_get_csrf_token(),
        'reloadOnChange' => true,
        'maxAgeSeconds' => 1800,
        'session' => app_location_session_snapshot(),
    ], JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script src="<?php echo $app_root; ?>dist/js/kodus-location-context.js"></script>
  <style>
    :root {
      --year-bg-start: #f4f7fb;
      --year-bg-end: #e8f1ff;
      --year-panel-bg: rgba(255, 255, 255, 0.92);
      --year-panel-border: rgba(13, 110, 253, 0.12);
      --year-panel-highlight: rgba(255, 255, 255, 0.56);
      --year-card-border: rgba(13, 110, 253, 0.14);
      --year-card-hover: rgba(13, 110, 253, 0.08);
      --year-card-active: linear-gradient(135deg, #0d6efd, #3bb0ff);
      --year-card-muted-surface: rgba(255, 255, 255, 0.72);
      --year-text-muted: #5b6472;
      --year-text-strong: #15304b;
      --year-accent-soft: rgba(13, 110, 253, 0.08);
      --year-success-soft: rgba(32, 201, 151, 0.14);
      --year-shadow: 0 24px 60px rgba(31, 60, 136, 0.14);
      --year-koda-stage: linear-gradient(180deg, rgba(255, 214, 153, 0.6), rgba(255, 184, 120, 0.4));
      --year-koda-card: linear-gradient(135deg, rgba(255,255,255,0.88), rgba(236,244,255,0.92));
      --year-koda-border: rgba(13, 110, 253, 0.12);
    }

    body.dark-mode,
    body[data-theme="dark"] {
      --year-bg-start: #0f172a;
      --year-bg-end: #172554;
      --year-panel-bg: rgba(15, 23, 42, 0.9);
      --year-panel-border: rgba(148, 163, 184, 0.16);
      --year-panel-highlight: rgba(255, 255, 255, 0.04);
      --year-card-border: rgba(148, 163, 184, 0.2);
      --year-card-hover: rgba(96, 165, 250, 0.12);
      --year-card-muted-surface: rgba(15, 23, 42, 0.7);
      --year-text-muted: #cbd5e1;
      --year-text-strong: #f8fbff;
      --year-accent-soft: rgba(96, 165, 250, 0.12);
      --year-success-soft: rgba(16, 185, 129, 0.18);
      --year-shadow: 0 24px 60px rgba(2, 6, 23, 0.45);
      --year-koda-stage: linear-gradient(180deg, rgba(255, 192, 120, 0.32), rgba(255, 128, 86, 0.18));
      --year-koda-card: linear-gradient(135deg, rgba(17,24,39,0.88), rgba(15,23,42,0.96));
      --year-koda-border: rgba(96, 165, 250, 0.18);
    }

    body {
      min-height: 100vh;
      margin: 0;
      color: var(--year-text-strong);
      background:
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.7), transparent 35%),
        radial-gradient(circle at bottom right, rgba(59, 176, 255, 0.14), transparent 26%),
        linear-gradient(160deg, var(--year-bg-start), var(--year-bg-end));
      background-repeat: no-repeat;
      background-size: cover;
      background-attachment: fixed;
      background-color: var(--year-bg-end);
    }
    body.login-page,
    body.register-page {
      display: block;
      height: auto;
      min-height: 100vh;
      justify-content: flex-start;
      align-items: stretch;
      padding: 0;
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
      box-sizing: border-box;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 3.25rem 1rem 10rem;
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
      position: relative;
      overflow: hidden;
    }
    .year-panel::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 168px;
      background: linear-gradient(180deg, var(--year-panel-highlight), transparent);
      pointer-events: none;
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
      font-size: .96rem;
      margin-bottom: 1rem;
    }
    .year-koda-card {
      position: relative;
      display: grid;
      grid-template-columns: 180px 1fr;
      gap: 1.2rem;
      align-items: center;
      padding: 1rem 1.05rem;
      border-radius: 24px;
      border: 1px solid var(--year-koda-border);
      background: var(--koda-scene-bg, var(--year-koda-card));
      box-shadow: 0 18px 36px rgba(31, 60, 136, 0.08);
      margin-bottom: 1rem;
      overflow: hidden;
    }
    .year-koda-card::after {
      content: "";
      position: absolute;
      top: 18px;
      left: 18px;
      width: 54px;
      height: 54px;
      border-radius: 50%;
      background: radial-gradient(circle, var(--koda-scene-orb, rgba(255, 240, 140, 0.95)), transparent 68%);
      box-shadow: 0 0 34px var(--koda-scene-glow, rgba(255, 205, 96, 0.35));
    }
    .year-koda-card::before {
      content: "";
      position: absolute;
      inset: auto 0 0 0;
      height: 42%;
      background: var(--koda-scene-overlay, linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02)));
      pointer-events: none;
    }
    .year-koda-stage {
      position: relative;
      min-height: 150px;
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }
    .year-koda-track {
      position: absolute;
      left: 14px;
      right: 14px;
      bottom: 10px;
      height: 18px;
      border-radius: 999px;
      background: var(--koda-scene-shore, rgba(232, 207, 198, 0.72));
      filter: blur(0.2px);
    }
    .year-koda-scenery {
      position: absolute;
      inset: 0;
      pointer-events: none;
      overflow: hidden;
    }
    .year-koda-scene-cloud,
    .year-koda-scene-cloud::before,
    .year-koda-scene-cloud::after {
      position: absolute;
      background: rgba(255,255,255,0.7);
      border-radius: 999px;
      content: "";
    }
    .year-koda-scene-cloud {
      width: 42px;
      height: 14px;
      top: 22px;
      left: 86px;
      animation: yearSceneDrift 8s ease-in-out infinite;
    }
    .year-koda-scene-cloud::before {
      width: 18px;
      height: 18px;
      left: 6px;
      top: -8px;
    }
    .year-koda-scene-cloud::after {
      width: 20px;
      height: 20px;
      right: 5px;
      top: -10px;
    }
    .year-koda-scene-rain {
      position: absolute;
      top: 34px;
      width: 3px;
      height: 16px;
      border-radius: 999px;
      background: rgba(255,255,255,0.52);
      animation: yearSceneRain 1s linear infinite;
    }
    .year-koda-scene-rain.r1 { left: 98px; }
    .year-koda-scene-rain.r2 { left: 118px; animation-delay: .2s; }
    .year-koda-scene-rain.r3 { left: 138px; animation-delay: .4s; }
    .year-koda-scene-star {
      position: absolute;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: rgba(255,255,255,0.9);
      box-shadow: 0 0 10px rgba(255,255,255,0.4);
      animation: yearSceneTwinkle 2.4s ease-in-out infinite;
    }
    .year-koda-scene-star.s1 { top: 22px; left: 100px; }
    .year-koda-scene-star.s2 { top: 42px; left: 138px; animation-delay: .6s; }
    .year-koda-scene-star.s3 { top: 18px; left: 152px; width: 4px; height: 4px; animation-delay: 1s; }
    .year-koda-scene-bolt {
      position: absolute;
      top: 34px;
      left: 126px;
      width: 14px;
      height: 28px;
      clip-path: polygon(48% 0, 100% 0, 62% 44%, 100% 44%, 22% 100%, 40% 58%, 10% 58%);
      background: linear-gradient(180deg, #ffe082, #ffb300);
      animation: yearSceneFlash 1.8s ease-in-out infinite;
    }
    .year-koda-scene-bubble {
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,0.34);
      animation: yearSceneBubble 4.5s ease-in-out infinite;
    }
    .year-koda-scene-bubble.b1 { width: 10px; height: 10px; left: 132px; bottom: 28px; }
    .year-koda-scene-bubble.b2 { width: 6px; height: 6px; left: 146px; bottom: 42px; animation-delay: .8s; }
    .year-koda {
      position: relative;
      width: 136px;
      height: 156px;
      animation: yearKodaBob 3s ease-in-out infinite;
      transform-origin: center bottom;
    }
    .year-koda-svg {
      width: 136px;
      height: 156px;
      overflow: visible;
    }
    .year-koda-svg .koda-shadow {
      fill: #e8cfc6;
    }
    .year-koda-svg .koda-body {
      fill: #4fc3f7;
    }
    .year-koda-svg .koda-core {
      fill: #0288d1;
    }
    .year-koda-svg .koda-highlight {
      fill: #ffffff;
      opacity: 0.7;
    }
    .year-koda-svg .koda-leaf {
      fill: #8bc34a;
      transform-origin: 104px 22px;
      animation: yearKodaLeafSway 2.8s ease-in-out infinite;
    }
    .year-koda-svg .koda-leaf.back {
      fill: #7cb342;
      transform-origin: 96px 24px;
      animation-delay: .18s;
    }
    .year-koda-svg .koda-leaf.front {
      animation-delay: .25s;
    }
    .year-koda-svg .koda-droplet {
      fill: #4fc3f7;
      animation: yearKodaOrbFloat 2.3s ease-in-out infinite;
    }
    .year-koda-svg .koda-droplet.small {
      animation-delay: .35s;
      animation-duration: 2.8s;
    }
    .year-koda-svg .koda-arm,
    .year-koda-svg .koda-finger,
    .year-koda-svg .koda-leg,
    .year-koda-svg .koda-foot {
      fill: none;
      stroke-linecap: round;
    }
    .year-koda-svg .koda-arm,
    .year-koda-svg .koda-finger {
      stroke: #795548;
    }
    .year-koda-svg .koda-arm {
      stroke-width: 5;
    }
    .year-koda-svg .koda-arm.left {
      transform-origin: 56px 138px;
      animation: yearKodaHelloWave 1.35s ease-in-out infinite;
    }
    .year-koda-svg .koda-arm.right {
      transform-origin: 144px 138px;
      animation: yearKodaRelaxArm 3s ease-in-out infinite;
    }
    .year-koda-svg .koda-finger {
      stroke-width: 3.5;
    }
    .year-koda-svg .koda-leg.left {
      transform-origin: 90px 178px;
      animation: yearKodaStandLeft 3s ease-in-out infinite;
    }
    .year-koda-svg .koda-leg.right {
      transform-origin: 110px 178px;
      animation: yearKodaStandRight 3s ease-in-out infinite;
    }
    .year-koda-svg .koda-leg,
    .year-koda-svg .koda-foot {
      stroke: #4caf50;
    }
    .year-koda-svg .koda-leg {
      stroke-width: 6;
    }
    .year-koda-svg .koda-foot {
      stroke-width: 5;
    }
    .year-koda-svg .koda-eye {
      fill: #e1f5fe;
      transform-origin: center;
      animation: yearKodaBlink 4.2s ease-in-out infinite;
    }
    .year-koda-svg .koda-mouth {
      stroke: #e1f5fe;
      stroke-width: 4;
      fill: none;
      stroke-linecap: round;
    }
    .year-koda-copy {
      position: relative;
      z-index: 1;
      padding: .85rem 1rem;
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(255,255,255,0.72), rgba(255,255,255,0.48));
      border: 1px solid rgba(255,255,255,0.35);
      backdrop-filter: blur(10px);
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
      text-shadow: 0 1px 0 rgba(255,255,255,0.18);
    }
    body.dark-mode .year-koda-copy,
    body[data-theme="dark"] .year-koda-copy {
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.56));
      border-color: rgba(255,255,255,0.12);
      box-shadow: 0 14px 30px rgba(2, 6, 23, 0.28);
      text-shadow: none;
    }
    .year-koda-tag {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .35rem .65rem;
      border-radius: 999px;
      background: rgba(255,255,255,0.58);
      color: #0f3354;
      font-weight: 700;
      font-size: .76rem;
      margin-bottom: .55rem;
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.28);
    }
    body.dark-mode .year-koda-tag,
    body[data-theme="dark"] .year-koda-tag {
      background: rgba(255,255,255,0.1);
      color: #f8fbff;
      box-shadow: none;
    }
    .year-koda-title {
      font-size: 1rem;
      font-weight: 700;
      margin-bottom: .2rem;
      color: #0f2740;
    }
    body.dark-mode .year-koda-title,
    body[data-theme="dark"] .year-koda-title {
      color: #f8fbff;
    }
    .year-koda-text {
      color: #244564;
      margin: 0;
      font-size: .92rem;
      font-weight: 600;
    }
    body.dark-mode .year-koda-text,
    body[data-theme="dark"] .year-koda-text {
      color: rgba(248, 251, 255, 0.88);
    }
    .year-selection-panel {
      position: relative;
      z-index: 1;
      margin-top: 0;
      padding: 1rem;
      border-radius: 24px;
      border: 1px solid var(--year-panel-border);
      background: linear-gradient(180deg, rgba(255,255,255,0.46), rgba(255,255,255,0.16));
    }
    body.dark-mode .year-selection-panel,
    body[data-theme="dark"] .year-selection-panel {
      background: linear-gradient(180deg, rgba(30, 41, 59, 0.58), rgba(15, 23, 42, 0.32));
    }
    .year-selection-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: .75rem;
    }
    .year-selection-title {
      font-size: 1rem;
      font-weight: 700;
      margin: 0;
      color: var(--year-text-strong);
    }
    .year-current-pill {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .45rem .75rem;
      border-radius: 999px;
      background: var(--year-accent-soft);
      border: 1px solid var(--year-panel-border);
      color: var(--year-text-strong);
      font-weight: 700;
      white-space: nowrap;
      flex: 0 0 auto;
      font-size: .84rem;
    }
    .year-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
      margin-bottom: .85rem;
    }
    .year-option input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    .year-card {
      position: relative;
      display: block;
      min-height: 134px;
      padding: 1rem;
      border-radius: 22px;
      border: 1px solid var(--year-card-border);
      background: var(--year-card-muted-surface);
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
    .year-card::before {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 5px;
      background: linear-gradient(180deg, rgba(13, 110, 253, 0.3), rgba(59, 176, 255, 0.12));
      opacity: .65;
      transition: opacity .18s ease, transform .18s ease;
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
    .year-option input:checked + .year-card::before {
      background: rgba(255,255,255,0.86);
      opacity: 1;
      transform: scaleY(1);
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
    .year-card-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: .75rem;
      margin-bottom: .55rem;
    }
    .year-card-meta {
      display: flex;
      align-items: center;
      gap: .45rem;
      flex-wrap: wrap;
      margin-bottom: .45rem;
    }
    .year-card-tag {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .35rem .65rem;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.08);
      color: #0d6efd;
      font-size: .74rem;
      font-weight: 700;
    }
    .year-card-tag.secondary {
      background: rgba(15, 23, 42, 0.06);
      color: var(--year-text-muted);
    }
    body.dark-mode .year-card-tag.secondary,
    body[data-theme="dark"] .year-card-tag.secondary {
      background: rgba(255, 255, 255, 0.08);
    }
    .year-option input:checked + .year-card .year-card-tag,
    .year-option input:checked + .year-card .year-card-tag.secondary {
      background: rgba(255, 255, 255, 0.16);
      color: #fff;
    }
    .year-value {
      font-size: 1.8rem;
      font-weight: 700;
      line-height: 1;
      margin-bottom: .2rem;
    }
    .year-copy {
      color: var(--year-text-muted);
      margin-bottom: 0;
      line-height: 1.4;
      font-size: .9rem;
    }
    .year-option input:checked + .year-card .year-copy,
    .year-option input:checked + .year-card .year-select-text {
      color: rgba(255, 255, 255, 0.88);
    }
    .year-select-text {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      margin-top: .7rem;
      font-weight: 700;
      color: #0d6efd;
      font-size: .88rem;
    }
    .year-option input:checked + .year-card .year-select-text {
      color: #fff;
    }
    .year-check {
      width: 2rem;
      height: 2rem;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--year-card-border);
      color: var(--year-text-muted);
      background: rgba(255,255,255,0.52);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
      transition: all .18s ease;
      flex: 0 0 auto;
    }
    body.dark-mode .year-check,
    body[data-theme="dark"] .year-check {
      background: rgba(15, 23, 42, 0.58);
    }
    .year-option input:checked + .year-card .year-check {
      border-color: rgba(255,255,255,0.36);
      color: #0d6efd;
      background: rgba(255,255,255,0.92);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
    }
    .year-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      padding-top: .85rem;
      border-top: 1px solid var(--year-panel-border);
    }
    .year-help {
      color: var(--year-text-muted);
      margin: 0;
      font-size: .9rem;
    }
    .year-submit {
      min-width: 180px;
      border-radius: 999px;
      padding: .85rem 1.4rem;
      font-weight: 600;
      box-shadow: 0 14px 28px rgba(13, 110, 253, 0.18);
    }
    @keyframes yearKodaBob {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-4px); }
    }
    @keyframes yearKodaHelloWave {
      0%, 100% { transform: rotate(0deg); }
      20% { transform: rotate(-16deg); }
      40% { transform: rotate(-34deg); }
      60% { transform: rotate(-16deg); }
      80% { transform: rotate(-30deg); }
    }
    @keyframes yearKodaRelaxArm {
      0%, 100% { transform: rotate(0deg); }
      50% { transform: rotate(4deg); }
    }
    @keyframes yearKodaStandLeft {
      0%, 100% { transform: rotate(0deg); }
      50% { transform: rotate(2deg); }
    }
    @keyframes yearKodaStandRight {
      0%, 100% { transform: rotate(0deg); }
      50% { transform: rotate(-2deg); }
    }
    @keyframes yearKodaOrbFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }
    @keyframes yearKodaBlink {
      0%, 44%, 100% { transform: scaleY(1); }
      47%, 49% { transform: scaleY(0.18); }
    }
    @keyframes yearKodaLeafSway {
      0%, 100% { transform: rotate(0deg) scale(1); }
      50% { transform: rotate(5deg) scale(1.04); }
    }
    @keyframes yearSceneDrift {
      0%, 100% { transform: translateX(0); }
      50% { transform: translateX(10px); }
    }
    @keyframes yearSceneRain {
      from { transform: translateY(0); opacity: .8; }
      to { transform: translateY(18px); opacity: 0; }
    }
    @keyframes yearSceneTwinkle {
      0%, 100% { transform: scale(1); opacity: .85; }
      50% { transform: scale(1.4); opacity: 1; }
    }
    @keyframes yearSceneFlash {
      0%, 72%, 100% { opacity: .18; }
      76%, 82% { opacity: 1; }
    }
    @keyframes yearSceneBubble {
      0%, 100% { transform: translateY(0); opacity: .25; }
      50% { transform: translateY(-12px); opacity: .55; }
    }
    @media (max-width: 576px) {
      body.login-page,
      body.register-page {
        min-height: 100dvh;
      }
      .content {
        padding-top: 1.5rem;
        padding-bottom: 6rem;
      }
      .year-panel {
        padding: 1.35rem;
        border-radius: 24px;
      }
      .year-koda-card {
        grid-template-columns: 1fr;
        text-align: center;
        gap: .8rem;
      }
      .year-koda-stage {
        min-height: 140px;
      }
      .year-grid {
        grid-template-columns: 1fr;
      }
      .year-selection-head {
        flex-direction: column;
      }
      .year-card {
        min-height: 126px;
      }
      .year-card-header {
        align-items: center;
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
            <img src="<?php echo $app_root; ?>dist/img/kodus.png" alt="KODUSLogo" height="64" width="64">
          </div>

          <div
            class="year-koda-card scene-<?= htmlspecialchars($kodaIdentity['scene'], ENT_QUOTES, 'UTF-8') ?>"
            aria-label="KODA guide"
            style="
              --koda-scene-bg: <?= htmlspecialchars($kodaPalette['card_background'], ENT_QUOTES, 'UTF-8') ?>;
              --koda-scene-orb: <?= htmlspecialchars($kodaPalette['sun_orb'], ENT_QUOTES, 'UTF-8') ?>;
              --koda-scene-glow: <?= htmlspecialchars($kodaPalette['glow'], ENT_QUOTES, 'UTF-8') ?>;
              --koda-scene-shore: <?= htmlspecialchars($kodaPalette['shore'], ENT_QUOTES, 'UTF-8') ?>;
              --koda-scene-accent: <?= htmlspecialchars($kodaPalette['text_accent'], ENT_QUOTES, 'UTF-8') ?>;
              --koda-scene-prompt: <?= htmlspecialchars($kodaPalette['prompt_accent'], ENT_QUOTES, 'UTF-8') ?>;
              --koda-scene-copy: <?= htmlspecialchars($kodaPalette['copy_text'], ENT_QUOTES, 'UTF-8') ?>;
              --koda-scene-overlay: <?= htmlspecialchars($kodaPalette['overlay_css'], ENT_QUOTES, 'UTF-8') ?>;
            "
          >
            <div class="year-koda-stage">
              <div class="year-koda-scenery" aria-hidden="true">
                <?php if ($kodaIdentity['scene'] === 'cloudy'): ?>
                  <span class="year-koda-scene-cloud"></span>
                <?php elseif ($kodaIdentity['scene'] === 'rainy'): ?>
                  <span class="year-koda-scene-cloud"></span>
                  <span class="year-koda-scene-rain r1"></span>
                  <span class="year-koda-scene-rain r2"></span>
                  <span class="year-koda-scene-rain r3"></span>
                <?php elseif ($kodaIdentity['scene'] === 'stormy'): ?>
                  <span class="year-koda-scene-cloud"></span>
                  <span class="year-koda-scene-bolt"></span>
                <?php elseif ($kodaIdentity['scene'] === 'night'): ?>
                  <span class="year-koda-scene-star s1"></span>
                  <span class="year-koda-scene-star s2"></span>
                  <span class="year-koda-scene-star s3"></span>
                <?php else: ?>
                  <span class="year-koda-scene-bubble b1"></span>
                  <span class="year-koda-scene-bubble b2"></span>
                <?php endif; ?>
              </div>
              <div class="year-koda-track"></div>
              <div class="year-koda" aria-hidden="true">
                <svg class="year-koda-svg" viewBox="0 0 200 220" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="KODA mascot">
                  <path class="koda-leaf front" d="M104 22 C118 2, 138 10, 132 30 C128 42, 115 46, 104 40 C110 34, 112 28, 104 22 Z"/>
                  <path class="koda-leaf back" d="M96 24 C84 8, 68 18, 74 34 C79 42, 90 43, 98 37 C93 33, 92 28, 96 24 Z"/>
                  <path class="koda-body" d="M100 14 C140 52, 170 100, 155 150 C140 198, 60 198, 45 150 C30 100, 60 52, 100 14 Z"/>
                  <ellipse class="koda-core" cx="100" cy="138" rx="55" ry="50"/>
                  <ellipse class="koda-highlight" cx="75" cy="72" rx="15" ry="25" transform="rotate(-30 75 72)"/>
                  <circle class="koda-droplet" cx="150" cy="42" r="8"/>
                  <circle class="koda-droplet small" cx="164" cy="27" r="5"/>
                  <path class="koda-arm left" d="M56 138 Q40 132 30 146 Q25 154 34 160"/>
                  <path class="koda-arm right" d="M144 138 Q160 132 170 146 Q175 154 166 160"/>
                  <path class="koda-finger left-top" d="M30 146 L24 142"/>
                  <path class="koda-finger left-bottom" d="M31 152 L24 154"/>
                  <path class="koda-finger right-top" d="M170 146 L176 142"/>
                  <path class="koda-finger right-bottom" d="M169 152 L176 154"/>
                  <path class="koda-leg left" d="M90 178 Q84 194 90 206"/>
                  <path class="koda-leg right" d="M110 178 Q116 194 110 206"/>
                  <path class="koda-foot left" d="M84 206 Q90 212 96 206"/>
                  <path class="koda-foot right" d="M104 206 Q110 212 116 206"/>
                  <?php if (($kodaFace['eyes'] ?? 'round') === 'happy'): ?>
                    <path class="koda-mouth" d="M72 138 Q80 128 88 138"/>
                    <path class="koda-mouth" d="M112 138 Q120 128 128 138"/>
                  <?php elseif (($kodaFace['eyes'] ?? 'round') === 'focused'): ?>
                    <path class="koda-mouth" d="M74 138 L86 138"/>
                    <path class="koda-mouth" d="M114 138 L126 138"/>
                  <?php elseif (($kodaFace['eyes'] ?? 'round') === 'sleepy'): ?>
                    <path class="koda-mouth" d="M74 140 Q80 136 86 140"/>
                    <path class="koda-mouth" d="M114 140 Q120 136 126 140"/>
                  <?php elseif (($kodaFace['eyes'] ?? 'round') === 'spark'): ?>
                    <path class="koda-mouth" d="M76 132 L84 144 M84 132 L76 144"/>
                    <path class="koda-mouth" d="M116 132 L124 144 M124 132 L116 144"/>
                  <?php else: ?>
                    <circle class="koda-eye left" cx="80" cy="138" r="6"/>
                    <circle class="koda-eye right" cx="120" cy="138" r="6"/>
                  <?php endif; ?>
                  <?php if (!empty($kodaFace['brows'])): ?>
                    <path class="koda-mouth" d="M72 128 L86 124"/>
                    <path class="koda-mouth" d="M114 124 L128 128"/>
                  <?php endif; ?>
                  <?php if (($kodaFace['mouth'] ?? '') === 'circle'): ?>
                    <circle cx="100" cy="165" r="8" fill="#E1F5FE"/>
                  <?php else: ?>
                    <path class="koda-mouth" d="<?= htmlspecialchars((string) ($kodaFace['mouth'] ?? 'M85 158 Q100 173 115 158'), ENT_QUOTES, 'UTF-8') ?>"/>
                  <?php endif; ?>
                </svg>
              </div>
            </div>
            <div class="year-koda-copy">
              <div class="year-koda-tag"><i class="fas fa-seedling"></i> KODA, Sprout Guardian - <?= htmlspecialchars($kodaIdentity['scene_label'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="year-koda-title">Ready when you are.</div>
              <p class="year-koda-text">Pick a year below.</p>
            </div>
          </div>

          <form method="POST">
            <?= security_csrf_input() ?>
            <div class="year-selection-panel">
              <div class="year-selection-head">
                <div>
                  <h2 class="year-selection-title">Available Years</h2>
                </div>
                <div class="year-current-pill">
                  <i class="fas fa-thumbtack"></i>
                  <?= htmlspecialchars($sessionSelectedYear, ENT_QUOTES, 'UTF-8') ?>
                </div>
              </div>
            <div class="year-grid" role="radiogroup" aria-label="Select year to access">
              <?php foreach ($yearOptions as $index => $year): ?>
                <label class="year-option mb-0">
                  <input type="radio" name="year" value="<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>" <?= $year === $sessionSelectedYear ? 'checked' : '' ?> required>
                  <span class="year-card">
                    <span class="year-card-header">
                      <span class="year-card-badge"><i class="fas fa-folder-open"></i></span>
                      <span class="year-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                    </span>
                    <div class="year-card-meta">
                      <span class="year-card-tag"><i class="fas fa-calendar-week"></i> FY</span>
                      <span class="year-card-tag secondary"><?= $index === 0 ? 'Newest dataset' : 'Archived access' ?></span>
                    </div>
                    <div class="year-value"><?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></div>
                    <p class="year-copy"><?= $index === 0 ? 'Latest records' : 'Previous records' ?></p>
                    <span class="year-select-text"><i class="fas fa-arrow-right"></i> Select</span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="year-actions">
              <p class="year-help">Used for this session.</p>
              <button type="submit" class="btn btn-primary year-submit">Continue to KODUS</button>
            </div>
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
</body>
</html>
