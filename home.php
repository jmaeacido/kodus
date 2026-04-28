<?php
include('header.php');
include('sidenav.php');
require_once __DIR__ . '/fund_monitoring_helpers.php';

$selectedYear = (string) ($_SESSION['selected_year'] ?? date('Y'));
$selectedYearInt = (int) $selectedYear;
$fullName = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = (string) ($_SESSION['username'] ?? 'User');
}
$isFirstLogin = !empty($_SESSION['is_first_login']);
$welcomeHeading = $isFirstLogin
    ? 'Welcome to KODUS, ' . $fullName
    : 'Welcome back, ' . $fullName;
$welcomeMessage = $isFirstLogin
    ? 'Your account is ready. Start by reviewing the dashboard and opening the workflows you need for your first session.'
    : 'Track beneficiaries, review coverage, and jump into the workflows your team uses every day.';
$authNotice = is_array($_SESSION['auth_notice'] ?? null) ? $_SESSION['auth_notice'] : null;
unset($_SESSION['auth_notice']);
$profileReviewLoginPrompt = is_array($_SESSION['profile_review_login_prompt'] ?? null) ? $_SESSION['profile_review_login_prompt'] : null;
unset($_SESSION['profile_review_login_prompt']);
$lowRecoveryCodesNotice = is_array($_SESSION['low_recovery_codes_notice'] ?? null) ? $_SESSION['low_recovery_codes_notice'] : null;
unset($_SESSION['low_recovery_codes_notice']);
$currentUserType = auth_current_user_type();
$isAdminDashboard = $currentUserType === 'admin';
$isEditorDashboard = $currentUserType === 'editor';
$isUserDashboard = $currentUserType === 'user';
$canViewOperations = auth_can_view_operations();
$canViewBeneficiaryProfile = $currentUserType !== 'user';

$dashboardRoutes = [
    'partner_beneficiaries' => $app_root . 'pages/data-tracking-meb',
    'calendar' => $app_root . 'pages/calendar',
    'summary' => $app_root . 'pages/summary/sectoral',
    'incoming' => $app_root . 'pages/data-tracking-in',
    'outgoing' => $app_root . 'pages/data-tracking-out',
    'inbox' => $app_root . 'messenger/index',
    'fund_monitoring' => $app_root . 'pages/fund-monitoring',
    'program_targets' => $app_root . 'implementation-status/program-targets',
    'program_activities' => $app_root . 'implementation-status/program-activities',
    'project_variables' => $app_root . 'admin/project_variables',
];

$heroActions = [];
if ($canViewOperations) {
    $heroActions[] = ['key' => 'partner_beneficiaries', 'label' => 'Partner-Beneficiaries', 'class' => 'btn btn-primary mr-2 mb-2'];
    if ($isEditorDashboard) {
        $heroActions[] = ['key' => 'program_targets', 'label' => 'Program Targets', 'class' => 'btn btn-success mr-2 mb-2'];
        $heroActions[] = ['key' => 'program_activities', 'label' => 'Program Activities', 'class' => 'btn btn-outline-success mr-2 mb-2'];
        if (auth_can_manage_project_variables()) {
            $heroActions[] = ['key' => 'project_variables', 'label' => 'Project Variables', 'class' => 'btn btn-outline-info mr-2 mb-2'];
        }
    } else {
        $heroActions[] = ['key' => 'fund_monitoring', 'label' => 'Fund Monitoring', 'class' => 'btn btn-success mr-2 mb-2'];
    }
}
$heroActions[] = ['key' => 'calendar', 'label' => 'Calendar', 'class' => 'btn btn-outline-primary mr-2 mb-2'];
$heroActions[] = ['key' => 'summary', 'label' => 'Summary Report', 'class' => 'btn btn-outline-secondary mb-2'];

$quickActions = [];
if ($canViewOperations) {
    $quickActions[] = ['key' => 'partner_beneficiaries', 'title' => 'Partner-Beneficiaries', 'description' => 'Update MEB records', 'icon' => 'fas fa-chevron-right text-primary'];
    if ($isEditorDashboard) {
        $quickActions[] = ['key' => 'program_targets', 'title' => 'Program Targets', 'description' => 'Manage target matrices', 'icon' => 'fas fa-chevron-right text-success'];
        $quickActions[] = ['key' => 'program_activities', 'title' => 'Program Activities', 'description' => 'Manage implementation records', 'icon' => 'fas fa-chevron-right text-success'];
        if (auth_can_manage_project_variables()) {
            $quickActions[] = ['key' => 'project_variables', 'title' => 'Project Variables', 'description' => 'Manage fiscal-year variables', 'icon' => 'fas fa-chevron-right text-info'];
        }
    } else {
        $quickActions[] = ['key' => 'fund_monitoring', 'title' => 'Fund Monitoring', 'description' => 'Review utilization and monthly updates', 'icon' => 'fas fa-chevron-right text-success'];
    }
    $quickActions[] = ['key' => 'incoming', 'title' => 'Incoming Tracking', 'description' => 'Review new entries', 'icon' => 'fas fa-chevron-right text-primary'];
    $quickActions[] = ['key' => 'outgoing', 'title' => 'Outgoing Tracking', 'description' => 'Follow processed releases', 'icon' => 'fas fa-chevron-right text-primary'];
}
$quickActions[] = ['key' => 'calendar', 'title' => 'Calendar', 'description' => 'Upcoming schedules', 'icon' => 'fas fa-chevron-right text-primary'];
$quickActions[] = ['key' => 'summary', 'title' => 'Sectoral Report', 'description' => 'Review dashboard-linked summaries', 'icon' => 'fas fa-chevron-right text-secondary'];
$quickActions[] = ['key' => 'inbox', 'title' => 'Inbox', 'description' => 'Unread messages', 'icon' => 'fas fa-chevron-right text-primary'];

$fundSummary = [
    'items' => 0,
    'adjusted' => 0.0,
    'obligations' => 0.0,
    'disbursement' => 0.0,
    'utilization' => 0.0,
];

if ($canViewOperations) {
    $fundItems = fund_monitoring_list_items_with_entries($conn, $selectedYearInt);
    if ($fundItems !== []) {
        foreach ($fundItems as $fundItem) {
            $adjusted = (float) ($fundItem['adjusted_appropriation'] ?? 0);
            $fundSummary['items']++;
            $fundSummary['adjusted'] += $adjusted;

            foreach (($fundItem['monthly'] ?? []) as $monthlyValues) {
                $fundSummary['obligations'] += (float) ($monthlyValues['obligations'] ?? 0);
                $fundSummary['disbursement'] += (float) ($monthlyValues['disbursement'] ?? 0);
            }
        }

        if ($fundSummary['adjusted'] > 0) {
            $fundSummary['utilization'] = ($fundSummary['obligations'] / $fundSummary['adjusted']) * 100;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Home</title>
  <style>
    .content-wrapper .content-header { padding: .75rem 0 0; }
    .content-wrapper .content { padding-top: .15rem; }
    .content-wrapper .content .container-fluid { padding-left: .75rem; padding-right: .75rem; }
    .row { --bs-gutter-y: .85rem; }
    .hero-card { border: 0; background: linear-gradient(135deg, rgba(13,110,253,.12), rgba(32,201,151,.16)); }
    .metric-card { border-radius: 1rem; transition: transform .2s ease, box-shadow .2s ease; }
    .metric-card:hover { transform: translateY(-2px); box-shadow: 0 .75rem 1.5rem rgba(0,0,0,.08); }
    .metric-card .info-box-icon { border-radius: .9rem; margin: .75rem; }
    .hero-actions { display:flex; flex-wrap:wrap; gap:.6rem; }
    .hero-summary-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:.65rem; margin-top:.85rem; }
    .hero-summary-tile { padding:.8rem .9rem; border-radius:1rem; background:rgba(255,255,255,.58); border:1px solid rgba(255,255,255,.45); }
    .hero-summary-tile small { display:block; color:#6c757d; margin-bottom:.25rem; }
    .hero-summary-tile strong { display:block; font-size:1.2rem; line-height:1.1; }
    .quick-link { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.9rem 1rem; border:1px solid rgba(0,0,0,.08); border-radius:.9rem; color:inherit; }
    .quick-link:hover { text-decoration:none; color:inherit; background:rgba(13,110,253,.06); }
    .mini-tile { padding:.85rem .95rem; border-radius:1rem; background:rgba(13,110,253,.06); height:100%; }
    .mini-tile-refresh { display:flex; justify-content:space-between; align-items:center; gap:1rem; }
    .fund-summary-card { border-radius:1rem; border:1px solid rgba(25,135,84,.14); background:linear-gradient(135deg, rgba(25,135,84,.08), rgba(13,110,253,.08)); }
    .fund-summary-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.65rem; }
    .fund-summary-kpi { padding:.72rem .85rem; border-radius:.9rem; background:rgba(255,255,255,.6); }
    .fund-summary-kpi small { display:block; color:#6c757d; margin-bottom:.2rem; }
    .fund-summary-kpi strong { font-size:1rem; }
    .dashboard-section-label { display:inline-flex; align-items:center; gap:.45rem; padding:.4rem .8rem; border-radius:999px; background:rgba(13,110,253,.08); color:#0d6efd; font-size:.78rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .dashboard-stack { display:grid; gap:.85rem; }
    .dashboard-side-grid { display:grid; gap:.85rem; }
    .card { margin-bottom: .85rem; }
    .card .card-header { padding: .72rem .9rem; }
    .card .card-body { padding: .9rem; }
    .top-sector-card { position: relative; z-index: 0; overflow: hidden; }
    .top-sector-card #topSectorList { position: relative; z-index: 0; margin-bottom: 0; }
    .top-sector-card #topSectorList .list-group-item { position: relative; z-index: auto; }
    .info-box { margin-bottom: 0; min-height: 110px; }
    .info-box .info-box-content { padding: .85rem .3rem .85rem 0; }
    .knob-wrap { gap: .75rem; }
    .knob-item { min-width: 118px; }
    body.dark-mode .mini-tile, body[data-theme="dark"] .mini-tile, body.dark-mode .quick-link, body[data-theme="dark"] .quick-link { background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.08); }
    body.dark-mode .fund-summary-card, body[data-theme="dark"] .fund-summary-card { border-color:rgba(124,252,155,.16); background:linear-gradient(135deg, rgba(25,135,84,.14), rgba(13,110,253,.12)); }
    body.dark-mode .fund-summary-kpi, body[data-theme="dark"] .fund-summary-kpi { background:rgba(17,24,39,.5); }
    body.dark-mode .fund-summary-kpi small, body[data-theme="dark"] .fund-summary-kpi small { color:#9fb0c2; }
    body.dark-mode .hero-summary-tile, body[data-theme="dark"] .hero-summary-tile { background:rgba(17,24,39,.42); border-color:rgba(255,255,255,.08); }
    body.dark-mode .hero-summary-tile small, body[data-theme="dark"] .hero-summary-tile small { color:#c3d1de; }
    body.dark-mode .dashboard-section-label, body[data-theme="dark"] .dashboard-section-label { background:rgba(125,196,255,.14); color:#9dd7ff; }
    .chart-box canvas { min-height:280px; height:280px; max-height:280px; max-width:100%; }
    .knob-wrap { display:flex; justify-content:center; flex-wrap:wrap; gap:1rem; }
    .knob-item { min-width:130px; text-align:center; }
    .skeleton { color:transparent !important; position:relative; overflow:hidden; }
    .skeleton::after { content:""; position:absolute; inset:0; transform:translateX(-100%); background:linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent); animation:shimmer 1.2s infinite; }
    @keyframes shimmer { 100% { transform:translateX(100%); } }
    @media (max-width: 1366px) {
      .hero-card .card-body { padding: 1rem !important; }
      .hero-summary-tile { padding: .72rem .8rem; }
      .mini-tile { padding: .75rem .82rem; }
      .content-wrapper .content .container-fluid { padding-left: .7rem; padding-right: .7rem; }
      .chart-box canvas { min-height: 260px; height: 260px; max-height: 260px; }
    }
    @media (max-width: 1024px) {
      .dashboard-section-label { font-size: .72rem; padding: .34rem .68rem; }
      .hero-summary-grid { gap: .55rem; }
      .chart-box canvas { min-height: 240px; height: 240px; max-height: 240px; }
    }
    @media (max-width: 767.98px) {
      .content-wrapper .content-header { padding-top: .55rem; }
      .hero-card .card-body { padding: 1rem !important; }
      .hero-actions { gap: .5rem; }
      .hero-actions .btn { flex: 1 1 100%; margin-right: 0 !important; }
      .hero-summary-grid { grid-template-columns:1fr; }
      .quick-link { align-items: flex-start; }
      .quick-link i { margin-top: .2rem; }
      .chart-box canvas { min-height: 240px; height: 240px; max-height: 240px; }
      .knob-item { min-width: 112px; }
    }
    @media (max-width: 575.98px) {
      .mini-tile-refresh { flex-direction: column; align-items: flex-start; }
      .mini-tile-refresh .btn { width: 100%; }
      .fund-summary-grid { grid-template-columns:1fr; }
      .chart-box .card-header,
      .card .card-header { display: block; }
      .chart-box .card-tools { margin-top: .5rem; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini"><br><br>
<div class="wrapper">

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Dashboard</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $app_root; ?>home">Home</a></li>
              <li class="breadcrumb-item active">Dashboard</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card hero-card shadow-sm">
          <div class="card-body p-4">
            <?php if ($lowRecoveryCodesNotice): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
              <strong>Recovery codes running low:</strong>
              You have <?= (int) ($lowRecoveryCodesNotice['remaining'] ?? 0) ?> recovery code(s) left. Open <a href="<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>settings">Settings</a> to regenerate and print a new set.
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <?php endif; ?>
            <div class="row align-items-center">
              <div class="col-lg-8">
                <span class="dashboard-section-label"><i class="fas fa-compass"></i>Workspace Overview</span>
                <span class="badge badge-primary mr-2">Fiscal Year <?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge badge-light"><?php echo htmlspecialchars(ucfirst($currentUserType), ENT_QUOTES, 'UTF-8'); ?></span>
                <h2 class="mt-3 mb-2"><?php echo htmlspecialchars($welcomeHeading, ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="mb-3 text-muted"><?php echo htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="hero-actions mt-3">
                  <?php foreach ($heroActions as $action): ?>
                  <a href="<?php echo htmlspecialchars($dashboardRoutes[$action['key']], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($action['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                  <?php endforeach; ?>
                </div>
                <div class="hero-summary-grid">
                  <?php if ($canViewOperations): ?>
                  <div class="hero-summary-tile">
                    <small>Tracked Fund Items</small>
                    <strong><?php echo number_format((int) $fundSummary['items']); ?></strong>
                    <span class="text-muted small">Objects in the annual matrix</span>
                  </div>
                  <div class="hero-summary-tile">
                    <small>Adjusted Appropriation</small>
                    <strong>PHP <?php echo number_format((float) $fundSummary['adjusted'], 2); ?></strong>
                    <span class="text-muted small">Current budget alignment</span>
                  </div>
                  <div class="hero-summary-tile">
                    <small>Fund Utilization</small>
                    <strong><?php echo number_format((float) $fundSummary['utilization'], 2); ?>%</strong>
                    <span class="text-muted small">Based on obligations</span>
                  </div>
                  <?php else: ?>
                  <div class="hero-summary-tile">
                    <small>Accessible Reports</small>
                    <strong><?php echo number_format($canViewBeneficiaryProfile ? 4 : 3); ?></strong>
                    <span class="text-muted small">Views available for your role</span>
                  </div>
                  <div class="hero-summary-tile">
                    <small>Primary Workspace</small>
                    <strong>Sectoral Summary</strong>
                    <span class="text-muted small">Use reports and calendar shortcuts</span>
                  </div>
                  <div class="hero-summary-tile">
                    <small>Messages</small>
                    <strong>Inbox Ready</strong>
                    <span class="text-muted small">Stay updated through mail notifications</span>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="row">
                  <div class="col-6 mb-3"><div class="mini-tile"><small class="text-muted">Coverage</small><div id="coverageRate" class="h3 mb-1 skeleton">0%</div><small class="text-muted">Listahanan and LSWDO tracked</small></div></div>
                  <div class="col-6 mb-3"><div class="mini-tile"><small class="text-muted">Female share</small><div id="femaleShare" class="h3 mb-1 skeleton">0%</div><small class="text-muted">of beneficiaries</small></div></div>
                  <div class="col-12"><div class="mini-tile mini-tile-refresh"><div><small class="text-muted d-block">Last refresh</small><strong id="dashboardRefreshTime">Waiting for data</strong></div><button id="refreshDashboardBtn" type="button" class="btn btn-sm btn-outline-primary">Refresh</button></div></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box metric-card">
              <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-users"></i></span>
              <div class="info-box-content"><span class="info-box-text">Partner-Beneficiaries</span><span id="beneCount" class="info-box-number skeleton">0</span><span class="text-muted small">Current fiscal year records</span></div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box metric-card">
              <span class="info-box-icon bg-success elevation-1"><i class="fas fa-map-marker-alt"></i></span>
              <div class="info-box-content"><span class="info-box-text">Barangays</span><span id="barCount" class="info-box-number skeleton">0</span><span class="text-muted small">Distinct communities</span></div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box metric-card">
              <span class="info-box-icon bg-info elevation-1"><i class="fas fa-city"></i></span>
              <div class="info-box-content"><span class="info-box-text">Municipalities</span><span id="muniCount" class="info-box-number skeleton">0</span><span class="text-muted small">LGU reach</span></div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box metric-card">
              <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-landmark"></i></span>
              <div class="info-box-content"><span class="info-box-text">Provinces</span><span id="provCount" class="info-box-number skeleton">0</span><span class="text-muted small">Regional footprint</span></div>
            </div>
          </div>
        </div>

        <div class="row">
          <div id="dashboardSideColumn" class="<?php echo $isUserDashboard ? 'col-12' : 'col-lg-4'; ?>">
            <div class="dashboard-side-grid">
            <div class="card card-outline card-primary">
              <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
              <div class="card-body">
                <?php foreach ($quickActions as $index => $action): ?>
                <div class="<?php echo $index === array_key_last($quickActions) ? '' : 'mb-2'; ?>"><a class="quick-link" href="<?php echo htmlspecialchars($dashboardRoutes[$action['key']], ENT_QUOTES, 'UTF-8'); ?>"><span><strong><?php echo htmlspecialchars($action['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($action['description'], ENT_QUOTES, 'UTF-8'); ?></small></span><i class="<?php echo htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></a></div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php if ($canViewOperations && !$isEditorDashboard): ?>
            <div class="card fund-summary-card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Fund Monitoring</h3>
                <a href="<?php echo htmlspecialchars($dashboardRoutes['fund_monitoring'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-success">Open</a>
              </div>
              <div class="card-body">
                <p class="text-muted mb-3">Quick summary for fiscal year <?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>.</p>
                <div class="fund-summary-grid">
                  <div class="fund-summary-kpi"><small>Tracked Items</small><strong><?php echo number_format((int) $fundSummary['items']); ?></strong></div>
                  <div class="fund-summary-kpi"><small>Adjusted Appropriation</small><strong>PHP <?php echo number_format((float) $fundSummary['adjusted'], 2); ?></strong></div>
                  <div class="fund-summary-kpi"><small>Total Obligations</small><strong>PHP <?php echo number_format((float) $fundSummary['obligations'], 2); ?></strong></div>
                  <div class="fund-summary-kpi"><small>Total Disbursement</small><strong>PHP <?php echo number_format((float) $fundSummary['disbursement'], 2); ?></strong></div>
                </div>
                <div class="mt-3">
                  <small class="text-muted d-block mb-1">Utilization Rate</small>
                  <div class="progress" style="height: .85rem; border-radius: 999px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo htmlspecialchars((string) max(0, min(100, round((float) $fundSummary['utilization'], 2))), ENT_QUOTES, 'UTF-8'); ?>%;" aria-valuenow="<?php echo htmlspecialchars((string) round((float) $fundSummary['utilization'], 2), ENT_QUOTES, 'UTF-8'); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <strong class="d-block mt-2"><?php echo number_format((float) $fundSummary['utilization'], 2); ?>%</strong>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($canViewOperations && $isEditorDashboard): ?>
            <div class="card fund-summary-card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Implementation Management</h3>
                <a href="<?php echo htmlspecialchars($dashboardRoutes['program_targets'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-success">Open</a>
              </div>
              <div class="card-body">
                <p class="text-muted mb-3">Editor tools for fiscal year <?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>.</p>
                <div class="fund-summary-grid">
                  <div class="fund-summary-kpi"><small>Targets</small><strong><a href="<?php echo htmlspecialchars($dashboardRoutes['program_targets'], ENT_QUOTES, 'UTF-8'); ?>">Program Targets</a></strong></div>
                  <div class="fund-summary-kpi"><small>Activities</small><strong><a href="<?php echo htmlspecialchars($dashboardRoutes['program_activities'], ENT_QUOTES, 'UTF-8'); ?>">Program Activities</a></strong></div>
                  <div class="fund-summary-kpi"><small>Variables</small><strong><?php if (auth_can_manage_project_variables()): ?><a href="<?php echo htmlspecialchars($dashboardRoutes['project_variables'], ENT_QUOTES, 'UTF-8'); ?>">Project Variables</a><?php else: ?>Not available<?php endif; ?></strong></div>
                  <div class="fund-summary-kpi"><small>Access</small><strong>Editor Workspace</strong></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <div id="dashboardSnapshotCard" class="card card-outline card-secondary">
              <div class="card-header"><h3 class="card-title">Snapshot</h3></div>
              <div class="card-body">
                <div class="row">
                  <div class="col-6 mb-3"><div class="mini-tile"><small class="text-muted">Listahanan 3</small><div id="nhtsPoorCount" class="h4 mb-1 skeleton">0</div><small class="text-muted">Poor (P)</small></div></div>
                  <div class="col-6 mb-3"><div class="mini-tile"><small class="text-muted">LSWDO</small><div id="nhtsNonPoorCount" class="h4 mb-1 skeleton">0</div><small class="text-muted">Assessment (NON)</small></div></div>
                  <div class="col-6"><div class="mini-tile"><small class="text-muted">Female</small><div id="femaleCountSummary" class="h4 mb-1 skeleton">0</div><small class="text-muted">Beneficiaries</small></div></div>
                  <div class="col-6"><div class="mini-tile"><small class="text-muted">Male</small><div id="maleCountSummary" class="h4 mb-1 skeleton">0</div><small class="text-muted">Beneficiaries</small></div></div>
                </div>
              </div>
            </div>
            </div>
          </div>
          <div id="dashboardMainColumn" class="<?php echo $isUserDashboard ? 'col-12' : 'col-lg-8'; ?>">
            <div class="dashboard-stack">
              <div class="card card-outline card-primary chart-box">
                <div class="card-header"><h3 class="card-title">Sex Distribution</h3><div class="card-tools"><a href="<?php echo htmlspecialchars($dashboardRoutes['summary'], ENT_QUOTES, 'UTF-8'); ?>">View Report</a></div></div>
                <div class="card-body"><canvas id="sexChart"></canvas></div>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="card card-outline card-info chart-box">
                    <div class="card-header"><h3 class="card-title">Listahanan vs LSWDO</h3><div class="card-tools"><a href="<?php echo htmlspecialchars($dashboardRoutes['summary'], ENT_QUOTES, 'UTF-8'); ?>">View Report</a></div></div>
                    <div class="card-body"><canvas id="donutChart"></canvas></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card card-outline card-secondary">
                    <div class="card-header"><h3 class="card-title">Listahanan vs LSWDO by Sex</h3></div>
                    <div class="card-body">
                      <div class="knob-wrap mb-3">
                        <div class="knob-item"><input id="nhts1FemaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#e83e8c" data-readonly="true" data-max="1" disabled><div class="knob-label">Listahanan Female</div></div>
                        <div class="knob-item"><input id="nhts1MaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#0d6efd" data-readonly="true" data-max="1" disabled><div class="knob-label">Listahanan Male</div></div>
                      </div>
                      <div class="knob-wrap">
                        <div class="knob-item"><input id="nhts2FemaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#fd7e14" data-readonly="true" data-max="1" disabled><div class="knob-label">LSWDO Female</div></div>
                        <div class="knob-item"><input id="nhts2MaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#20c997" data-readonly="true" data-max="1" disabled><div class="knob-label">LSWDO Male</div></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div id="sectorChartCard" class="card card-outline card-info chart-box">
                <div class="card-header"><h3 class="card-title">Sectoral Data Disaggregation</h3><div class="card-tools"><a href="<?php echo htmlspecialchars($dashboardRoutes['summary'], ENT_QUOTES, 'UTF-8'); ?>">View Report</a></div></div>
                <div class="card-body"><canvas id="sectorChart"></canvas></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card card-outline card-success top-sector-card">
              <div class="card-header"><h3 class="card-title">Top Sector Priorities</h3></div>
              <div class="card-body p-0"><ul class="list-group list-group-flush" id="topSectorList"><li class="list-group-item text-muted">Loading sector data...</li></ul></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/chart.js/Chart.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/jquery-knob/jquery.knob.min.js"></script>
<script>
  window.addEventListener('load', function () {
    if (window.KodusPageLoader) {
      window.KodusPageLoader.hide();
    }

    let chain = Promise.resolve();

    <?php if ($authNotice): ?>
    chain = chain.then(function () {
      return Swal.fire({
        icon: <?= json_encode($authNotice['icon'] ?? 'success') ?>,
        title: <?= json_encode($authNotice['title'] ?? 'Welcome') ?>,
        text: <?= json_encode($authNotice['text'] ?? '') ?>,
        timer: 1800,
        showConfirmButton: false
      });
    });
    <?php endif; ?>

    <?php if ($profileReviewLoginPrompt): ?>
    chain = chain.then(function () {
      return Swal.fire({
        icon: 'info',
        title: <?= json_encode($profileReviewLoginPrompt['title'] ?? 'Review Your Profile Information') ?>,
        text: <?= json_encode($profileReviewLoginPrompt['message'] ?? '') ?>,
        showCloseButton: true,
        showCancelButton: true,
        confirmButtonText: 'Go to Settings',
        cancelButtonText: 'Remind me later',
        reverseButtons: true
      }).then(function (result) {
        if (result.isConfirmed) {
          window.location.href = <?= json_encode($profileReviewLoginPrompt['settings_url'] ?? ($app_root . 'settings')) ?>;
        }
      });
    });
    <?php endif; ?>
  }, { once: true });
</script>
<script>
  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  window.addEventListener('pageshow', function () {
    window.scrollTo(0, 0);
  });

  window.addEventListener('load', function () {
    window.scrollTo(0, 0);
  });
</script>
<script>
  const charts = {};
  const isAdminDashboard = <?= $isAdminDashboard ? 'true' : 'false' ?>;
  function n(v){ return new Intl.NumberFormat().format(Number(v || 0)); }
  function p(v){ return `${Number(v || 0).toFixed(1)}%`; }
  function dark(){ return document.body.dataset.theme === 'dark' || document.body.classList.contains('dark-mode'); }
  function textColor(){ return dark() ? '#f8f9fa' : '#212529'; }
  function gridColor(){ return dark() ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.08)'; }
  function setValue(id, value){ $(id).text(value).removeClass('skeleton'); }
  function putChart(key, id, config){ if (charts[key]) charts[key].destroy(); charts[key] = new Chart(document.getElementById(id).getContext('2d'), config); }
  function alignAdminSectorChartHeight(){
    if (!isAdminDashboard || window.innerWidth < 992) {
      const sectorCanvas = document.getElementById('sectorChart');
      if (sectorCanvas) {
        sectorCanvas.style.height = '';
        sectorCanvas.style.minHeight = '';
        sectorCanvas.style.maxHeight = '';
      }
      if (charts.sector) {
        charts.sector.resize();
      }
      return;
    }

    const snapshotCard = document.getElementById('dashboardSnapshotCard');
    const sectorCanvas = document.getElementById('sectorChart');
    const sectorCard = document.getElementById('sectorChartCard');
    if (!snapshotCard || !sectorCanvas || !sectorCard) {
      return;
    }

    sectorCanvas.style.height = '';
    sectorCanvas.style.minHeight = '';
    sectorCanvas.style.maxHeight = '';

    const bottomGap = Math.round(snapshotCard.getBoundingClientRect().bottom - sectorCard.getBoundingClientRect().bottom);
    const currentCanvasHeight = Math.round(sectorCanvas.getBoundingClientRect().height || 280);
    if (bottomGap > 0) {
      const nextCanvasHeight = currentCanvasHeight + bottomGap;
      sectorCanvas.style.height = `${nextCanvasHeight}px`;
      sectorCanvas.style.minHeight = `${nextCanvasHeight}px`;
      sectorCanvas.style.maxHeight = `${nextCanvasHeight}px`;
    }

    if (charts.sector) {
      charts.sector.resize();
    }
  }
  function knobDraw(){ if (this.$.data('skin') !== 'tron') return; let a=this.angle(this.cv), sa=this.startAngle, sat=this.startAngle, ea, eat=sat+a; this.g.lineWidth=this.lineWidth; if(this.o.cursor){ sat=eat-.3; eat=eat+.3; } if(this.o.displayPrevious){ ea=this.startAngle+this.angle(this.value); if(this.o.cursor){ sa=ea-.3; ea=ea+.3; } this.g.beginPath(); this.g.strokeStyle=this.previousColor; this.g.arc(this.xy,this.xy,this.radius-this.lineWidth,sa,ea,false); this.g.stroke(); } this.g.beginPath(); this.g.strokeStyle=this.o.fgColor; this.g.arc(this.xy,this.xy,this.radius-this.lineWidth,sat,eat,false); this.g.stroke(); this.g.lineWidth=2; this.g.beginPath(); this.g.strokeStyle=this.o.fgColor; this.g.arc(this.xy,this.xy,this.radius-this.lineWidth+1+this.lineWidth*2/3,0,2*Math.PI,false); this.g.stroke(); return false; }
  function buildTopSectors(r){ const sectors=[['4Ps',r.fourPs_count],['Farmers (F)',r.farmer_count],['Fisher-folks (FF)',r.fisherfolk_count],['Informal Sector (IS)',r.is_count],['Indigenous People (IP)',r.ip_count],['Senior Citizen (SC)',r.sc_count],['Solo Parent (SP)',r.sp_count],['Lactating Women (LW)',r.lw_count],['Pregnant Women (PW)',r.pw_count],['PWD',r.pwd_count],['OSY',r.osy_count],['FR',r.fr_count],['YB/PWUD',r.ybDs_count],['LGBTQIA+',r.lgbtqia_count]].map(([label,count]) => [label, Number(count || 0)]).sort((a,b) => b[1]-a[1]).slice(0,5); const list = $('#topSectorList').empty(); sectors.forEach(([label,count], index) => list.append(`<li class="list-group-item d-flex justify-content-between align-items-center"><span>${index+1}. ${label}</span><span class="badge badge-primary badge-pill">${n(count)}</span></li>`)); }
  function updateKnobs(r){ const max = Math.max(r.female_nhts1_count||0, r.male_nhts1_count||0, r.female_nhts2_count||0, r.male_nhts2_count||0, 1); ['#nhts1FemaleKnob','#nhts1MaleKnob','#nhts2FemaleKnob','#nhts2MaleKnob'].forEach(id => $(id).trigger('configure',{max})); $('#nhts1FemaleKnob').val(r.female_nhts1_count||0).trigger('change'); $('#nhts1MaleKnob').val(r.male_nhts1_count||0).trigger('change'); $('#nhts2FemaleKnob').val(r.female_nhts2_count||0).trigger('change'); $('#nhts2MaleKnob').val(r.male_nhts2_count||0).trigger('change'); }
  function render(r){ const bene = Number(r.beneficiary_count||0), female = Number(r.female_count||0), male = Number(r.male_count||0), poor = Number(r.nhts1_count||0), nonPoor = Number(r.nhts2_count||0); setValue('#beneCount', n(bene)); setValue('#barCount', n(r.barangay_count||0)); setValue('#muniCount', n(r.municipality_count||0)); setValue('#provCount', n(r.province_count||0)); setValue('#nhtsPoorCount', n(poor)); setValue('#nhtsNonPoorCount', n(nonPoor)); setValue('#femaleCountSummary', n(female)); setValue('#maleCountSummary', n(male)); setValue('#coverageRate', p(bene ? ((poor + nonPoor) / bene) * 100 : 0)); setValue('#femaleShare', p(bene ? (female / bene) * 100 : 0)); $('#dashboardRefreshTime').text(new Date().toLocaleString()); putChart('sex','sexChart',{ type:'doughnut', data:{ labels:['Female','Male'], datasets:[{ data:[female,male], backgroundColor:['#e83e8c','#0d6efd'], borderWidth:0 }] }, options:{ maintainAspectRatio:false, responsive:true, plugins:{ legend:{ position:'bottom', labels:{ color:textColor() }}}}}); putChart('class','donutChart',{ type:'doughnut', data:{ labels:['Listahanan 3 (P)','LSWDO Assessment (NON)'], datasets:[{ data:[poor,nonPoor], backgroundColor:['#fd7e14','#20c997'], borderWidth:0 }] }, options:{ maintainAspectRatio:false, responsive:true, plugins:{ legend:{ position:'bottom', labels:{ color:textColor() }}}}}); putChart('sector','sectorChart',{ type:'bar', data:{ labels:['4Ps','Farmers (F)','Fisher-folks (FF)','Informal Sector (IS)','Indigenous People (IP)','Senior Citizen (SC)','Solo Parent (SP)','Lactating Women (LW)','Pregnant Women (PW)','PWD','OSY','FR','YB/PWUD','LGBTQIA+'], datasets:[{ label:'Beneficiaries', data:[r.fourPs_count||0,r.farmer_count||0,r.fisherfolk_count||0,r.is_count||0,r.ip_count||0,r.sc_count||0,r.sp_count||0,r.lw_count||0,r.pw_count||0,r.pwd_count||0,r.osy_count||0,r.fr_count||0,r.ybDs_count||0,r.lgbtqia_count||0], backgroundColor:['#0d6efd','#20c997','#ffc107','#6610f2','#6f42c1','#fd7e14','#198754','#e83e8c','#dc3545','#17a2b8','#0dcaf0','#d63384','#1982c4','#2a9d8f'], borderRadius:8 }] }, options:{ maintainAspectRatio:false, responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:textColor(), maxRotation:35, minRotation:20 }, grid:{ display:false } }, y:{ beginAtZero:true, ticks:{ color:textColor() }, grid:{ color:gridColor() } } } }}); updateKnobs(r); buildTopSectors(r); requestAnimationFrame(() => alignAdminSectorChartHeight()); }
  function loadDashboard(){ $('#dashboardRefreshTime').text('Refreshing...'); $.getJSON('get_data.php').done(function(r){ if(r.error){ $('#dashboardRefreshTime').text(r.error); return; } render(r); }).fail(function(){ $('#dashboardRefreshTime').text('Unable to load dashboard data'); }); }
  $(function(){ $('.knob').knob({ draw: knobDraw }); loadDashboard(); $('#refreshDashboardBtn').on('click', loadDashboard); $(window).on('resize', function(){ requestAnimationFrame(() => alignAdminSectorChartHeight()); }); });
</script>
</body>
</html>
