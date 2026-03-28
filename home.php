<?php
include('header.php');
include('sidenav.php');

$selectedYear = (string) ($_SESSION['selected_year'] ?? date('Y'));
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

$dashboardRoutes = [
    'partner_beneficiaries' => $app_root . 'pages/data-tracking-meb',
    'calendar' => $app_root . 'pages/calendar',
    'summary' => $app_root . 'pages/summary/sectoral',
    'incoming' => $app_root . 'pages/data-tracking-in',
    'outgoing' => $app_root . 'pages/data-tracking-out',
    'inbox' => $app_root . 'inbox/index',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Home</title>
  <style>
    .hero-card { border: 0; background: linear-gradient(135deg, rgba(13,110,253,.12), rgba(32,201,151,.16)); }
    .metric-card { border-radius: 1rem; transition: transform .2s ease, box-shadow .2s ease; }
    .metric-card:hover { transform: translateY(-2px); box-shadow: 0 .75rem 1.5rem rgba(0,0,0,.08); }
    .metric-card .info-box-icon { border-radius: .9rem; margin: .75rem; }
    .hero-actions { display:flex; flex-wrap:wrap; gap:.75rem; }
    .quick-link { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.9rem 1rem; border:1px solid rgba(0,0,0,.08); border-radius:.9rem; color:inherit; }
    .quick-link:hover { text-decoration:none; color:inherit; background:rgba(13,110,253,.06); }
    .mini-tile { padding:1rem; border-radius:1rem; background:rgba(13,110,253,.06); height:100%; }
    .mini-tile-refresh { display:flex; justify-content:space-between; align-items:center; gap:1rem; }
    body.dark-mode .mini-tile, body[data-theme="dark"] .mini-tile, body.dark-mode .quick-link, body[data-theme="dark"] .quick-link { background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.08); }
    .chart-box canvas { min-height:280px; height:280px; max-height:280px; max-width:100%; }
    .knob-wrap { display:flex; justify-content:center; flex-wrap:wrap; gap:1rem; }
    .knob-item { min-width:130px; text-align:center; }
    .skeleton { color:transparent !important; position:relative; overflow:hidden; }
    .skeleton::after { content:""; position:absolute; inset:0; transform:translateX(-100%); background:linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent); animation:shimmer 1.2s infinite; }
    @keyframes shimmer { 100% { transform:translateX(100%); } }
    @media (max-width: 767.98px) {
      .hero-card .card-body { padding: 1.25rem !important; }
      .hero-actions { gap: .5rem; }
      .hero-actions .btn { flex: 1 1 100%; margin-right: 0 !important; }
      .quick-link { align-items: flex-start; }
      .quick-link i { margin-top: .2rem; }
      .chart-box canvas { min-height: 240px; height: 240px; max-height: 240px; }
      .knob-item { min-width: 112px; }
    }
    @media (max-width: 575.98px) {
      .mini-tile-refresh { flex-direction: column; align-items: flex-start; }
      .mini-tile-refresh .btn { width: 100%; }
      .chart-box .card-header,
      .card .card-header { display: block; }
      .chart-box .card-tools { margin-top: .5rem; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Dashboard</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>kodus/home">Home</a></li>
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
            <div class="row align-items-center">
              <div class="col-lg-8">
                <span class="badge badge-primary mr-2">Fiscal Year <?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge badge-light"><?php echo htmlspecialchars(ucfirst((string) ($_SESSION['user_type'] ?? 'user')), ENT_QUOTES, 'UTF-8'); ?></span>
                <h2 class="mt-3 mb-2"><?php echo htmlspecialchars($welcomeHeading, ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="mb-3 text-muted"><?php echo htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="hero-actions mt-3">
                  <a href="<?php echo htmlspecialchars($dashboardRoutes['partner_beneficiaries'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary mr-2 mb-2">Partner-Beneficiaries</a>
                  <a href="<?php echo htmlspecialchars($dashboardRoutes['calendar'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary mr-2 mb-2">Calendar</a>
                  <a href="<?php echo htmlspecialchars($dashboardRoutes['summary'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary mb-2">Summary Report</a>
                </div>
              </div>
              <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="row">
                  <div class="col-6 mb-3"><div class="mini-tile"><small class="text-muted">Coverage</small><div id="coverageRate" class="h3 mb-1 skeleton">0%</div><small class="text-muted">Poor and non-poor tracked</small></div></div>
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
          <div class="col-lg-4">
            <div class="card card-outline card-primary">
              <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
              <div class="card-body">
                <div class="mb-2"><a class="quick-link" href="<?php echo htmlspecialchars($dashboardRoutes['partner_beneficiaries'], ENT_QUOTES, 'UTF-8'); ?>"><span><strong>Partner-Beneficiaries</strong><br><small class="text-muted">Update MEB records</small></span><i class="fas fa-chevron-right text-primary"></i></a></div>
                <div class="mb-2"><a class="quick-link" href="<?php echo htmlspecialchars($dashboardRoutes['incoming'], ENT_QUOTES, 'UTF-8'); ?>"><span><strong>Incoming Tracking</strong><br><small class="text-muted">Review new entries</small></span><i class="fas fa-chevron-right text-primary"></i></a></div>
                <div class="mb-2"><a class="quick-link" href="<?php echo htmlspecialchars($dashboardRoutes['outgoing'], ENT_QUOTES, 'UTF-8'); ?>"><span><strong>Outgoing Tracking</strong><br><small class="text-muted">Follow processed releases</small></span><i class="fas fa-chevron-right text-primary"></i></a></div>
                <div class="mb-2"><a class="quick-link" href="<?php echo htmlspecialchars($dashboardRoutes['calendar'], ENT_QUOTES, 'UTF-8'); ?>"><span><strong>Calendar</strong><br><small class="text-muted">Upcoming schedules</small></span><i class="fas fa-chevron-right text-primary"></i></a></div>
                <a class="quick-link" href="<?php echo htmlspecialchars($dashboardRoutes['inbox'], ENT_QUOTES, 'UTF-8'); ?>"><span><strong>Inbox</strong><br><small class="text-muted">Unread messages</small></span><i class="fas fa-chevron-right text-primary"></i></a>
              </div>
            </div>
            <div class="card card-outline card-secondary">
              <div class="card-header"><h3 class="card-title">Snapshot</h3></div>
              <div class="card-body">
                <div class="row">
                  <div class="col-6 mb-3"><div class="mini-tile"><small class="text-muted">Poor</small><div id="nhtsPoorCount" class="h4 mb-1 skeleton">0</div><small class="text-muted">NHTS-PR</small></div></div>
                  <div class="col-6 mb-3"><div class="mini-tile"><small class="text-muted">Non-poor</small><div id="nhtsNonPoorCount" class="h4 mb-1 skeleton">0</div><small class="text-muted">Classification</small></div></div>
                  <div class="col-6"><div class="mini-tile"><small class="text-muted">Female</small><div id="femaleCountSummary" class="h4 mb-1 skeleton">0</div><small class="text-muted">Beneficiaries</small></div></div>
                  <div class="col-6"><div class="mini-tile"><small class="text-muted">Male</small><div id="maleCountSummary" class="h4 mb-1 skeleton">0</div><small class="text-muted">Beneficiaries</small></div></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card card-outline card-primary chart-box">
              <div class="card-header"><h3 class="card-title">Sex Distribution</h3><div class="card-tools"><a href="<?php echo htmlspecialchars($dashboardRoutes['summary'], ENT_QUOTES, 'UTF-8'); ?>">View Report</a></div></div>
              <div class="card-body"><canvas id="sexChart"></canvas></div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="card card-outline card-info chart-box">
                  <div class="card-header"><h3 class="card-title">NHTS-PR Classification</h3><div class="card-tools"><a href="<?php echo htmlspecialchars($dashboardRoutes['summary'], ENT_QUOTES, 'UTF-8'); ?>">View Report</a></div></div>
                  <div class="card-body"><canvas id="donutChart"></canvas></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card card-outline card-secondary">
                  <div class="card-header"><h3 class="card-title">Poor vs Non-poor by Sex</h3></div>
                  <div class="card-body">
                    <div class="knob-wrap mb-3">
                      <div class="knob-item"><input id="nhts1FemaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#e83e8c" data-readonly="true" data-max="1" disabled><div class="knob-label">Poor Female</div></div>
                      <div class="knob-item"><input id="nhts1MaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#0d6efd" data-readonly="true" data-max="1" disabled><div class="knob-label">Poor Male</div></div>
                    </div>
                    <div class="knob-wrap">
                      <div class="knob-item"><input id="nhts2FemaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#fd7e14" data-readonly="true" data-max="1" disabled><div class="knob-label">Non-poor Female</div></div>
                      <div class="knob-item"><input id="nhts2MaleKnob" type="text" class="knob" value="0" data-skin="tron" data-thickness="0.2" data-width="90" data-height="90" data-fgColor="#20c997" data-readonly="true" data-max="1" disabled><div class="knob-label">Non-poor Male</div></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-lg-8">
            <div class="card card-outline card-info chart-box">
              <div class="card-header"><h3 class="card-title">Sectoral Data Disaggregation</h3><div class="card-tools"><a href="<?php echo htmlspecialchars($dashboardRoutes['summary'], ENT_QUOTES, 'UTF-8'); ?>">View Report</a></div></div>
              <div class="card-body"><canvas id="sectorChart"></canvas></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card card-outline card-success">
              <div class="card-header"><h3 class="card-title">Top Sector Priorities</h3></div>
              <div class="card-body p-0"><ul class="list-group list-group-flush" id="topSectorList"><li class="list-group-item text-muted">Loading sector data...</li></ul></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $base_url; ?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/chart.js/Chart.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/dist/js/adminlte.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/jquery-knob/jquery.knob.min.js"></script>
<?php if ($authNotice): ?>
<script>
  Swal.fire({
    icon: <?= json_encode($authNotice['icon'] ?? 'success') ?>,
    title: <?= json_encode($authNotice['title'] ?? 'Welcome') ?>,
    text: <?= json_encode($authNotice['text'] ?? '') ?>,
    timer: 1800,
    showConfirmButton: false
  });
</script>
<?php endif; ?>
<script>
  const charts = {};
  function n(v){ return new Intl.NumberFormat().format(Number(v || 0)); }
  function p(v){ return `${Number(v || 0).toFixed(1)}%`; }
  function dark(){ return document.body.dataset.theme === 'dark' || document.body.classList.contains('dark-mode'); }
  function textColor(){ return dark() ? '#f8f9fa' : '#212529'; }
  function gridColor(){ return dark() ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.08)'; }
  function setValue(id, value){ $(id).text(value).removeClass('skeleton'); }
  function putChart(key, id, config){ if (charts[key]) charts[key].destroy(); charts[key] = new Chart(document.getElementById(id).getContext('2d'), config); }
  function knobDraw(){ if (this.$.data('skin') !== 'tron') return; let a=this.angle(this.cv), sa=this.startAngle, sat=this.startAngle, ea, eat=sat+a; this.g.lineWidth=this.lineWidth; if(this.o.cursor){ sat=eat-.3; eat=eat+.3; } if(this.o.displayPrevious){ ea=this.startAngle+this.angle(this.value); if(this.o.cursor){ sa=ea-.3; ea=ea+.3; } this.g.beginPath(); this.g.strokeStyle=this.previousColor; this.g.arc(this.xy,this.xy,this.radius-this.lineWidth,sa,ea,false); this.g.stroke(); } this.g.beginPath(); this.g.strokeStyle=this.o.fgColor; this.g.arc(this.xy,this.xy,this.radius-this.lineWidth,sat,eat,false); this.g.stroke(); this.g.lineWidth=2; this.g.beginPath(); this.g.strokeStyle=this.o.fgColor; this.g.arc(this.xy,this.xy,this.radius-this.lineWidth+1+this.lineWidth*2/3,0,2*Math.PI,false); this.g.stroke(); return false; }
  function buildTopSectors(r){ const sectors=[['4Ps',r.fourPs_count],['Farmer',r.farmer_count],['Fisherfolk',r.fisherfolk_count],['IP',r.ip_count],['Senior Citizen',r.sc_count],['Solo Parent',r.sp_count],['Pregnant Women',r.pw_count],['PWD',r.pwd_count],['OSY',r.osy_count],['FR',r.fr_count],['YB/DS',r.ybDs_count],['LGBTQIA+',r.lgbtqia_count]].map(([label,count]) => [label, Number(count || 0)]).sort((a,b) => b[1]-a[1]).slice(0,5); const list = $('#topSectorList').empty(); sectors.forEach(([label,count], index) => list.append(`<li class="list-group-item d-flex justify-content-between align-items-center"><span>${index+1}. ${label}</span><span class="badge badge-primary badge-pill">${n(count)}</span></li>`)); }
  function updateKnobs(r){ const max = Math.max(r.female_nhts1_count||0, r.male_nhts1_count||0, r.female_nhts2_count||0, r.male_nhts2_count||0, 1); ['#nhts1FemaleKnob','#nhts1MaleKnob','#nhts2FemaleKnob','#nhts2MaleKnob'].forEach(id => $(id).trigger('configure',{max})); $('#nhts1FemaleKnob').val(r.female_nhts1_count||0).trigger('change'); $('#nhts1MaleKnob').val(r.male_nhts1_count||0).trigger('change'); $('#nhts2FemaleKnob').val(r.female_nhts2_count||0).trigger('change'); $('#nhts2MaleKnob').val(r.male_nhts2_count||0).trigger('change'); }
  function render(r){ const bene = Number(r.beneficiary_count||0), female = Number(r.female_count||0), male = Number(r.male_count||0), poor = Number(r.nhts1_count||0), nonPoor = Number(r.nhts2_count||0); setValue('#beneCount', n(bene)); setValue('#barCount', n(r.barangay_count||0)); setValue('#muniCount', n(r.municipality_count||0)); setValue('#provCount', n(r.province_count||0)); setValue('#nhtsPoorCount', n(poor)); setValue('#nhtsNonPoorCount', n(nonPoor)); setValue('#femaleCountSummary', n(female)); setValue('#maleCountSummary', n(male)); setValue('#coverageRate', p(bene ? ((poor + nonPoor) / bene) * 100 : 0)); setValue('#femaleShare', p(bene ? (female / bene) * 100 : 0)); $('#dashboardRefreshTime').text(new Date().toLocaleString()); putChart('sex','sexChart',{ type:'doughnut', data:{ labels:['Female','Male'], datasets:[{ data:[female,male], backgroundColor:['#e83e8c','#0d6efd'], borderWidth:0 }] }, options:{ maintainAspectRatio:false, responsive:true, plugins:{ legend:{ position:'bottom', labels:{ color:textColor() }}}}}); putChart('class','donutChart',{ type:'doughnut', data:{ labels:['Poor','Non-poor'], datasets:[{ data:[poor,nonPoor], backgroundColor:['#fd7e14','#20c997'], borderWidth:0 }] }, options:{ maintainAspectRatio:false, responsive:true, plugins:{ legend:{ position:'bottom', labels:{ color:textColor() }}}}}); putChart('sector','sectorChart',{ type:'bar', data:{ labels:['4Ps','Farmer','Fisherfolk','IP','Senior Citizen','Solo Parent','Pregnant Women','PWD','OSY','FR','YB/DS','LGBTQIA+'], datasets:[{ label:'Beneficiaries', data:[r.fourPs_count||0,r.farmer_count||0,r.fisherfolk_count||0,r.ip_count||0,r.sc_count||0,r.sp_count||0,r.pw_count||0,r.pwd_count||0,r.osy_count||0,r.fr_count||0,r.ybDs_count||0,r.lgbtqia_count||0], backgroundColor:['#0d6efd','#20c997','#ffc107','#6f42c1','#fd7e14','#198754','#dc3545','#6610f2','#0dcaf0','#d63384','#1982c4','#2a9d8f'], borderRadius:8 }] }, options:{ maintainAspectRatio:false, responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:textColor(), maxRotation:35, minRotation:20 }, grid:{ display:false } }, y:{ beginAtZero:true, ticks:{ color:textColor() }, grid:{ color:gridColor() } } } }}); updateKnobs(r); buildTopSectors(r); }
  function loadDashboard(){ $('#dashboardRefreshTime').text('Refreshing...'); $.getJSON('get_data.php').done(function(r){ if(r.error){ $('#dashboardRefreshTime').text(r.error); return; } render(r); }).fail(function(){ $('#dashboardRefreshTime').text('Unable to load dashboard data'); }); }
  $(function(){ $('.knob').knob({ draw: knobDraw }); loadDashboard(); $('#refreshDashboardBtn').on('click', loadDashboard); });
</script>
</body>
</html>
