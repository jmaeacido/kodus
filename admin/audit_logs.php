<?php
include('../header.php');
include('../sidenav.php');

function audit_logs_bind_params(mysqli_stmt $stmt, string $types, array &$values): bool
{
    if ($types === '' || $values === []) {
        return true;
    }

    $references = [$types];
    foreach ($values as $index => &$value) {
        $references[] = &$value;
    }

    return call_user_func_array([$stmt, 'bind_param'], $references);
}

function audit_logs_display_name(array $row): string
{
    $parts = array_filter([
        trim((string) ($row['first_name'] ?? '')),
        trim((string) ($row['middle_name'] ?? '')),
        trim((string) ($row['last_name'] ?? '')),
    ], static fn(string $value): bool => $value !== '');

    $fullName = trim(ucwords(strtolower(implode(' ', $parts))));
    if (!empty($row['ext'])) {
        $fullName .= ' ' . trim((string) $row['ext']);
    }

    if ($fullName !== '') {
        return $fullName;
    }

    $username = trim((string) ($row['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
    return $userId > 0 ? 'User #' . $userId : 'System';
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../');
    exit;
}

$adminStmt = $conn->prepare('SELECT userType FROM users WHERE id = ?');
$adminStmt->bind_param('i', $userId);
$adminStmt->execute();
$admin = db_stmt_fetch_one_assoc($adminStmt);
$adminStmt->close();

if (($admin['userType'] ?? '') !== 'admin') {
    echo "<script src='../plugins/sweetalert2/sweetalert2.min.js'></script>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'You are not authorized to view this page.'
      }).then(() => window.location.href = '../');
    </script>";
    exit;
}

$selectedUserId = trim((string) ($_GET['user'] ?? ''));
$selectedAction = trim((string) ($_GET['action'] ?? ''));
$selectedDateFrom = trim((string) ($_GET['date_from'] ?? ''));
$selectedDateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($selectedUserId !== '' && !ctype_digit($selectedUserId)) {
    $selectedUserId = '';
}

if ($selectedDateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateFrom)) {
    $selectedDateFrom = '';
}

if ($selectedDateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateTo)) {
    $selectedDateTo = '';
}

$filterUsers = [];
$filterUserStmt = $conn->prepare("
    SELECT DISTINCT
        a.user_id,
        u.username,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.ext
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE a.user_id IS NOT NULL
    ORDER BY u.first_name ASC, u.last_name ASC, u.username ASC, a.user_id ASC
");
if ($filterUserStmt) {
    $filterUserStmt->execute();
    foreach (db_stmt_fetch_all_assoc($filterUserStmt) as $row) {
        $key = (string) ((int) ($row['user_id'] ?? 0));
        if ($key === '0' || isset($filterUsers[$key])) {
            continue;
        }

        $filterUsers[$key] = audit_logs_display_name($row);
    }
    $filterUserStmt->close();
    asort($filterUsers, SORT_NATURAL | SORT_FLAG_CASE);
}

$filterActions = [];
$filterActionStmt = $conn->prepare("
    SELECT DISTINCT action
    FROM audit_logs
    WHERE action IS NOT NULL AND action <> ''
    ORDER BY action ASC
");
if ($filterActionStmt) {
    $filterActionStmt->execute();
    foreach (db_stmt_fetch_all_assoc($filterActionStmt) as $row) {
        $action = trim((string) ($row['action'] ?? ''));
        if ($action !== '') {
            $filterActions[] = $action;
        }
    }
    $filterActionStmt->close();
}

$whereClauses = [];
$bindTypes = '';
$bindValues = [];

if ($selectedUserId !== '') {
    $whereClauses[] = 'a.user_id = ?';
    $bindTypes .= 'i';
    $bindValues[] = (int) $selectedUserId;
}

if ($selectedAction !== '') {
    $whereClauses[] = 'a.action = ?';
    $bindTypes .= 's';
    $bindValues[] = $selectedAction;
}

if ($selectedDateFrom !== '') {
    $whereClauses[] = 'DATE(a.created_at) >= ?';
    $bindTypes .= 's';
    $bindValues[] = $selectedDateFrom;
}

if ($selectedDateTo !== '') {
    $whereClauses[] = 'DATE(a.created_at) <= ?';
    $bindTypes .= 's';
    $bindValues[] = $selectedDateTo;
}

$logsSql = "
    SELECT
        a.id,
        a.user_id,
        a.action,
        a.details,
        a.ip_address,
        a.created_at,
        u.username,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.ext
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
";

if ($whereClauses !== []) {
    $logsSql .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$logsSql .= ' ORDER BY a.created_at DESC, a.id DESC';

$logs = [];
$logsStmt = $conn->prepare($logsSql);
if ($logsStmt) {
    audit_logs_bind_params($logsStmt, $bindTypes, $bindValues);
    $logsStmt->execute();
    foreach (db_stmt_fetch_all_assoc($logsStmt) as $row) {
        $row['display_user'] = audit_logs_display_name($row);
        $logs[] = $row;
    }
    $logsStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Audit Logs</title>
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <style>
    .audit-logs-page {
      --audit-page-bg:
        radial-gradient(circle at top right, rgba(23, 162, 184, 0.14), transparent 28%),
        linear-gradient(180deg, #15202b 0%, #0f1720 100%);
      --audit-panel-bg: rgba(31, 41, 55, 0.98);
      --audit-panel-soft: linear-gradient(135deg, #1f2937 0%, #243140 100%);
      --audit-border: rgba(255, 255, 255, 0.09);
      --audit-text: #f8f9fa;
      --audit-muted: #b8c2cc;
      --audit-table-head-bg: #243140;
      --audit-table-head-text: #dbe7f3;
      --audit-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
    }
    body[data-theme="light"] .audit-logs-page {
      --audit-page-bg:
        radial-gradient(circle at top right, rgba(23, 162, 184, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, #f4f6f9 100%);
      --audit-panel-bg: #ffffff;
      --audit-panel-soft: linear-gradient(135deg, #ffffff 0%, #f5fbfc 100%);
      --audit-border: rgba(15, 23, 42, 0.08);
      --audit-text: #1f2d3d;
      --audit-muted: #5c6773;
      --audit-table-head-bg: #f7f9fc;
      --audit-table-head-text: #495057;
      --audit-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
    }
    .audit-logs-page .content-wrapper {
      background: var(--audit-page-bg);
    }
    .audit-logs-page .audit-hero,
    .audit-logs-page .audit-filter-card,
    .audit-logs-page .audit-table-card {
      border-radius: 1rem;
      background: var(--audit-panel-bg);
      border: 1px solid var(--audit-border);
      box-shadow: var(--audit-shadow);
      color: var(--audit-text);
    }
    .audit-logs-page .audit-hero {
      padding: 1.2rem 1.25rem;
      margin-bottom: 1rem;
      background: var(--audit-panel-soft);
    }
    .audit-logs-page .audit-hero h2,
    .audit-logs-page .audit-filter-card h3,
    .audit-logs-page .audit-table-card .card-title {
      color: var(--audit-text);
    }
    .audit-logs-page .audit-hero p,
    .audit-logs-page .audit-filter-card p,
    .audit-logs-page .form-text {
      color: var(--audit-muted);
    }
    .audit-logs-page .audit-filter-card {
      padding: 1rem 1.1rem;
      margin-bottom: 1rem;
    }
    .audit-logs-page .audit-table-card .card-header {
      background: color-mix(in srgb, var(--audit-panel-bg) 90%, transparent);
      border-bottom: 1px solid var(--audit-border);
    }
    .audit-logs-page .form-control,
    .audit-logs-page .custom-select {
      min-height: 38px;
    }
    .audit-logs-page .summary-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border-radius: 999px;
      padding: 0.45rem 0.8rem;
      background: rgba(23, 162, 184, 0.15);
      color: inherit;
      font-weight: 700;
      font-size: 0.88rem;
    }
    .audit-logs-page .audit-table-card table thead th {
      background: var(--audit-table-head-bg);
      color: var(--audit-table-head-text);
      white-space: nowrap;
    }
    .audit-logs-page .details-cell {
      min-width: 280px;
      max-width: 480px;
      white-space: normal;
      word-break: break-word;
    }
    .audit-logs-page .meta-cell {
      white-space: nowrap;
    }
    .audit-logs-page .dataTables_filter,
    .audit-logs-page .dataTables_length,
    .audit-logs-page .dataTables_info,
    .audit-logs-page .dataTables_paginate {
      color: var(--audit-text);
    }
    .audit-logs-page .dataTables_filter input,
    .audit-logs-page .dataTables_length select {
      background-color: rgba(255, 255, 255, 0.96);
    }
    body:not(.dark-mode) .audit-logs-page .dataTables_filter input,
    body[data-theme="light"] .audit-logs-page .dataTables_filter input,
    body:not(.dark-mode) .audit-logs-page .dataTables_length select,
    body[data-theme="light"] .audit-logs-page .dataTables_length select {
      background-color: #fff;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed audit-logs-page">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Audit Logs</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $app_root; ?>home">Home</a></li>
              <li class="breadcrumb-item">Administration</li>
              <li class="breadcrumb-item active">Audit Logs</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="audit-hero d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
          <div>
            <h2 class="mb-2">Activity trail for administrative review</h2>
            <p class="mb-0">Review recorded actions, trace account activity, and narrow results by user, action, or date range.</p>
          </div>
          <div class="mt-3 mt-lg-0">
            <span class="summary-badge" id="auditLogRecordCount">
              <i class="fas fa-history"></i>
              <?php echo number_format(count($logs)); ?> record<?php echo count($logs) === 1 ? '' : 's'; ?>
            </span>
          </div>
        </div>

        <div class="audit-filter-card">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
            <div>
              <h3 class="h5 mb-1">Filter Logs</h3>
              <p class="mb-0">Use the filters below, then the table search box for a quick text lookup.</p>
            </div>
          </div>
          <form method="get" action="" class="mb-0">
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="auditUserFilter">User</label>
                <select class="custom-select" id="auditUserFilter" name="user">
                  <option value="">All users</option>
                  <?php foreach ($filterUsers as $filterUserKey => $filterUserLabel): ?>
                    <option value="<?php echo htmlspecialchars($filterUserKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedUserId === $filterUserKey ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($filterUserLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label for="auditActionFilter">Action</label>
                <select class="custom-select" id="auditActionFilter" name="action">
                  <option value="">All actions</option>
                  <?php foreach ($filterActions as $filterAction): ?>
                    <option value="<?php echo htmlspecialchars($filterAction, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedAction === $filterAction ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($filterAction, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-2">
                <label for="auditDateFrom">From</label>
                <input type="date" class="form-control" id="auditDateFrom" name="date_from" value="<?php echo htmlspecialchars($selectedDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="form-group col-md-2">
                <label for="auditDateTo">To</label>
                <input type="date" class="form-control" id="auditDateTo" name="date_to" value="<?php echo htmlspecialchars($selectedDateTo, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="form-group col-md-2 d-flex align-items-end">
                <div class="btn-group w-100">
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter mr-1"></i>Apply
                  </button>
                  <a href="<?php echo $app_root; ?>admin/audit_logs" class="btn btn-outline-secondary">
                    <i class="fas fa-undo mr-1"></i>Reset
                  </a>
                </div>
              </div>
            </div>
          </form>
        </div>

        <div class="card audit-table-card">
          <div class="card-header">
            <h3 class="card-title">Audit Log Records</h3>
          </div>
          <div class="card-body">
            <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1220px;">
              <table id="auditLogsTable" class="table table-bordered table-striped" style="width: 100%; table-layout: auto;">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Created At</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function () {
  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function renderText(value, type) {
    if (type !== 'display') {
      return value == null ? '' : value;
    }

    return escapeHtml(value);
  }

  function renderDetails(value, type) {
    if (type !== 'display') {
      return value == null ? '' : value;
    }

    return escapeHtml(value).replace(/\n/g, '<br>');
  }

  function updateRecordCount(count) {
    const total = Number(count || 0);
    $('#auditLogRecordCount').html(
      '<i class="fas fa-history"></i> ' +
      total.toLocaleString() +
      ' record' +
      (total === 1 ? '' : 's')
    );
  }

  const table = $('#auditLogsTable').DataTable({
    ajax: {
      url: '<?php echo $app_root; ?>admin/audit_logs_data.php',
      data: function (params) {
        params.user = <?php echo json_encode($selectedUserId, JSON_UNESCAPED_SLASHES); ?>;
        params.action = <?php echo json_encode($selectedAction, JSON_UNESCAPED_SLASHES); ?>;
        params.date_from = <?php echo json_encode($selectedDateFrom, JSON_UNESCAPED_SLASHES); ?>;
        params.date_to = <?php echo json_encode($selectedDateTo, JSON_UNESCAPED_SLASHES); ?>;
      },
      dataSrc: function (json) {
        const rows = Array.isArray(json.data) ? json.data : [];
        updateRecordCount(rows.length);
        return rows;
      }
    },
    columns: [
      { data: 'id', className: 'meta-cell', render: renderText },
      { data: 'user', className: 'meta-cell', render: renderText },
      { data: 'action', className: 'meta-cell', render: renderText },
      { data: 'details', className: 'details-cell', render: renderDetails },
      { data: 'ip_address', className: 'meta-cell', render: renderText },
      {
        data: 'created_at',
        className: 'meta-cell',
        render: function (value, type, row) {
          if (type === 'sort' || type === 'type') {
            return row.created_at_sort || 0;
          }

          return renderText(value, type);
        }
      }
    ],
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: false,
    scrollX: true,
    pageLength: 10,
    lengthMenu: [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, 'All']],
    order: [[5, 'desc'], [0, 'desc']]
  });

  if (window.KODUSLiveRefresh) {
    window.KODUSLiveRefresh.watchDataTable({
      table: table,
      socket: {
        key: 'admin-audit-logs',
        channel: 'kodus.audit_logs',
        events: ['audit_logs.changed']
      }
    });
  }
});
</script>
</body>
</html>
