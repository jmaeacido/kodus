<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/../project_variable_helpers.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../');
    exit;
}

$stmt = $conn->prepare("SELECT userType FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userType);
$stmt->fetch();
$stmt->close();

if ($userType !== 'admin') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'You are not authorized to manage project variables.',
      }).then(() => window.location.href = '../');
    </script>";
    exit;
}

$flash = $_SESSION['project_variables_flash'] ?? null;
unset($_SESSION['project_variables_flash']);

$configs = project_variable_list_all($conn);
$catalog = project_variable_catalog();
$selectedYear = isset($_SESSION['selected_year']) ? (int) $_SESSION['selected_year'] : null;
$years = array_values(array_unique(array_map(static function ($row) {
    return (int) $row['fiscal_year'];
}, $configs)));
rsort($years);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Project Variables</title>
  <style>
    :root {
      --project-page-bg:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.16), transparent 24%),
        radial-gradient(circle at left bottom, rgba(13, 110, 253, 0.12), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #f4f6f9 100%);
      --project-panel-bg: #ffffff;
      --project-panel-soft: rgba(255, 255, 255, 0.88);
      --project-hero-bg: linear-gradient(135deg, #ffffff 0%, #fffaf0 100%);
      --project-border: rgba(13, 110, 253, 0.12);
      --project-border-strong: rgba(255, 193, 7, 0.18);
      --project-text: #1f2d3d;
      --project-muted: #607080;
      --project-pill-bg: rgba(255, 193, 7, 0.18);
      --project-pill-text: #8a5b00;
      --project-stat-bg: rgba(13, 110, 253, 0.04);
      --project-key: #0d6efd;
      --project-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
      --project-hero-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
      --project-empty-bg: rgba(255, 255, 255, 0.72);
    }

    body[data-theme="dark"] {
      --project-page-bg:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.1), transparent 22%),
        radial-gradient(circle at left bottom, rgba(13, 110, 253, 0.18), transparent 24%),
        linear-gradient(180deg, #111827 0%, #0f172a 100%);
      --project-panel-bg: #111c2d;
      --project-panel-soft: rgba(17, 28, 45, 0.92);
      --project-hero-bg: linear-gradient(135deg, #162033 0%, #1d2940 100%);
      --project-border: rgba(125, 196, 255, 0.18);
      --project-border-strong: rgba(255, 193, 7, 0.16);
      --project-text: #e8eef5;
      --project-muted: #9fb0c2;
      --project-pill-bg: rgba(255, 193, 7, 0.14);
      --project-pill-text: #ffd978;
      --project-stat-bg: rgba(13, 110, 253, 0.1);
      --project-key: #7dc4ff;
      --project-shadow: 0 18px 42px rgba(0, 0, 0, 0.3);
      --project-hero-shadow: 0 22px 48px rgba(0, 0, 0, 0.3);
      --project-empty-bg: rgba(17, 28, 45, 0.76);
    }

    .project-variables-page .content-wrapper {
      background: var(--project-page-bg);
    }
    .project-hero {
      border-radius: 1rem;
      background: var(--project-hero-bg);
      border: 1px solid var(--project-border-strong);
      box-shadow: var(--project-hero-shadow);
      padding: 1.2rem 1.3rem;
      margin-bottom: 1rem;
    }
    .project-hero h2 {
      margin: 0 0 0.35rem;
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--project-text);
    }
    .project-hero p {
      margin: 0;
      color: var(--project-muted);
      max-width: 780px;
    }
    .project-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border-radius: 999px;
      padding: 0.55rem 0.85rem;
      background: var(--project-pill-bg);
      color: var(--project-pill-text);
      font-weight: 700;
      font-size: 0.88rem;
    }
    .project-card {
      border: 0;
      border-radius: 1rem;
      box-shadow: var(--project-shadow);
      overflow: hidden;
      background: var(--project-panel-bg);
      color: var(--project-text);
    }
    .project-card .card-header {
      background: var(--project-panel-bg);
      border-bottom: 1px solid var(--project-border);
      color: var(--project-text);
    }
    .project-form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1rem;
    }
    .project-stat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1rem;
    }
    .project-stat {
      border: 1px solid var(--project-border);
      border-radius: 0.95rem;
      padding: 1rem;
      background: var(--project-stat-bg);
      height: 100%;
    }
    .project-stat-label {
      display: block;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--project-muted);
      margin-bottom: 0.4rem;
    }
    .project-stat-value {
      display: block;
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--project-text);
    }
    .project-table thead th {
      white-space: nowrap;
    }
    .project-empty {
      border: 1px dashed rgba(108, 117, 125, 0.35);
      border-radius: 1rem;
      padding: 2rem 1rem;
      text-align: center;
      color: var(--project-muted);
      background: var(--project-empty-bg);
    }
    .project-catalog-list {
      display: grid;
      gap: 0.75rem;
    }
    .project-catalog-item {
      border: 1px solid var(--project-border);
      border-radius: 0.9rem;
      padding: 0.9rem 1rem;
      background: var(--project-panel-soft);
    }
    .project-catalog-item h4 {
      margin: 0 0 0.25rem;
      font-size: 0.98rem;
      font-weight: 700;
      color: var(--project-text);
    }
    .project-catalog-item p {
      margin: 0;
      color: var(--project-muted);
      font-size: 0.88rem;
    }
    .project-key {
      font-family: Consolas, monospace;
      font-size: 0.84rem;
      color: var(--project-key);
    }
    .project-variables-page .content-header h1,
    .project-variables-page .breadcrumb-item.active,
    .project-variables-page label,
    .project-variables-page .text-muted,
    .project-variables-page .card-title,
    .project-variables-page .table {
      color: var(--project-text) !important;
    }
    .project-variables-page .text-muted,
    .project-variables-page .breadcrumb-item a,
    .project-variables-page .project-card small.text-muted {
      color: var(--project-muted) !important;
    }
    .project-variables-page .form-control,
    .project-variables-page .custom-select,
    .project-variables-page textarea,
    .project-variables-page select {
      background: var(--project-panel-soft);
      border-color: var(--project-border);
      color: var(--project-text);
    }
    .project-variables-page .form-control:focus,
    .project-variables-page .custom-select:focus,
    .project-variables-page textarea:focus,
    .project-variables-page select:focus {
      background: var(--project-panel-bg);
      color: var(--project-text);
      border-color: #7dc4ff;
      box-shadow: 0 0 0 0.2rem rgba(125, 196, 255, 0.14);
    }
    .project-variables-page .form-control::placeholder,
    .project-variables-page textarea::placeholder {
      color: var(--project-muted);
    }
    .project-variables-page .table thead th {
      border-color: var(--project-border);
      background: rgba(13, 110, 253, 0.06);
      color: var(--project-text);
    }
    .project-variables-page .table td,
    .project-variables-page .table th {
      border-color: var(--project-border);
      vertical-align: middle;
    }
    .project-variables-page .table-striped tbody tr:nth-of-type(odd) {
      background: rgba(255, 255, 255, 0.02);
    }
    .project-variables-page .table-striped tbody tr:nth-of-type(even) {
      background: rgba(13, 110, 253, 0.03);
    }
    .project-variables-page .table-responsive {
      border-radius: 0.9rem;
    }
    body[data-theme="dark"] .project-variables-page .table-striped tbody tr:nth-of-type(odd) {
      background: rgba(255, 255, 255, 0.015);
    }
    body[data-theme="dark"] .project-variables-page .table-striped tbody tr:nth-of-type(even) {
      background: rgba(125, 196, 255, 0.04);
    }
    body[data-theme="dark"] .project-variables-page .table thead th {
      background: rgba(125, 196, 255, 0.08);
    }
    body[data-theme="dark"] .project-variables-page .alert {
      border-color: var(--project-border);
    }
    .project-variable-key-hint {
      font-family: Consolas, monospace;
      font-size: 0.78rem;
      color: var(--project-key);
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed project-variables-page">
<div class="wrapper">
  <div class="content-wrapper">
    <br><br>
    <div class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">
        <div>
          <h1 class="m-0">Project Variables</h1>
          <p class="mb-0 text-muted">Manage year-based input data used by project pages, summaries, and computations.</p>
        </div>
        <ol class="breadcrumb float-sm-right mb-0 mt-2 mt-sm-0">
          <li class="breadcrumb-item"><a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/home">Home</a></li>
          <li class="breadcrumb-item active">Project Variables</li>
        </ol>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="project-hero d-flex justify-content-between align-items-start flex-wrap">
          <div class="mr-3 mb-3 mb-lg-0">
            <h2>Shared admin-managed project data</h2>
            <p>This registry is meant for values that change over time or differ by fiscal year, like payout wage rates and other operational inputs. Pages can read these records instead of relying on hardcoded values.</p>
          </div>
          <div class="project-pill">
            <i class="fas fa-database"></i>
            <span><?= $selectedYear ? 'Selected fiscal year: ' . $selectedYear : 'No fiscal year selected' ?></span>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?= htmlspecialchars((string) ($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <div class="row">
          <div class="col-xl-5">
            <div class="card project-card mb-4">
              <div class="card-header">
                <h3 class="card-title mb-0">Variable Editor</h3>
              </div>
              <div class="card-body">
                <p class="mb-3 text-muted">Create, edit, and remove variables through modal editors so changes are scoped to one record at a time.</p>
                <button type="button" id="openCreateVariableModal" class="btn btn-primary">
                  <i class="fas fa-plus mr-1"></i> Add Project Variable
                </button>
              </div>
            </div>

            <div class="project-stat-grid mb-4">
              <div class="project-stat">
                <span class="project-stat-label">Configured Entries</span>
                <span class="project-stat-value"><?= number_format(count($configs)) ?></span>
              </div>
              <div class="project-stat">
                <span class="project-stat-label">Configured Years</span>
                <span class="project-stat-value"><?= number_format(count($years)) ?></span>
              </div>
            </div>

            <div class="card project-card mb-4">
              <div class="card-header">
                <h3 class="card-title mb-0">Known Variable Keys</h3>
              </div>
              <div class="card-body">
                <div class="project-catalog-list">
                  <?php foreach ($catalog as $key => $meta): ?>
                    <div class="project-catalog-item">
                      <h4><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></h4>
                      <p class="project-key mb-2"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></p>
                      <p><?= htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-7">
            <div class="card project-card mb-4">
              <div class="card-header">
                <h3 class="card-title mb-0">Configured Data</h3>
              </div>
              <div class="card-body">
                <?php if (!$configs): ?>
                  <div class="project-empty">
                    <i class="fas fa-database fa-2x mb-3"></i>
                    <p class="mb-0">No project variables found yet. Add a year-based value to start using dynamic project inputs.</p>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-bordered table-striped project-table mb-0">
                      <thead>
                        <tr>
                          <th>Fiscal Year</th>
                          <th>Key</th>
                          <th>Label</th>
                          <th>Value</th>
                          <th>Unit</th>
                          <th>Notes</th>
                          <th>Updated</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($configs as $config): ?>
                          <?php
                            $valueDisplay = $config['value_type'] === 'number'
                              ? number_format((float) ($config['value_number'] ?? 0), 4)
                              : $config['value_text'];
                          ?>
                          <tr>
                            <td><?= htmlspecialchars((string) $config['fiscal_year'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="project-key"><?= htmlspecialchars($config['variable_key'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($config['variable_label'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $valueDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($config['unit'] !== '' ? $config['unit'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($config['notes'] !== '' ? $config['notes'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($config['updated_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime((string) $config['updated_at'])), ENT_QUOTES, 'UTF-8') : 'Unknown' ?></td>
                            <td>
                              <div class="d-flex flex-wrap" style="gap:0.45rem;">
                                <button
                                  type="button"
                                  class="btn btn-outline-primary btn-sm js-load-variable"
                                  data-id="<?= htmlspecialchars((string) $config['id'], ENT_QUOTES, 'UTF-8') ?>"
                                  data-year="<?= htmlspecialchars((string) $config['fiscal_year'], ENT_QUOTES, 'UTF-8') ?>"
                                  data-key="<?= htmlspecialchars($config['variable_key'], ENT_QUOTES, 'UTF-8') ?>"
                                  data-label="<?= htmlspecialchars($config['variable_label'], ENT_QUOTES, 'UTF-8') ?>"
                                  data-type="<?= htmlspecialchars($config['value_type'], ENT_QUOTES, 'UTF-8') ?>"
                                  data-number="<?= htmlspecialchars($config['value_number'] !== null ? (string) $config['value_number'] : '', ENT_QUOTES, 'UTF-8') ?>"
                                  data-text="<?= htmlspecialchars($config['value_text'], ENT_QUOTES, 'UTF-8') ?>"
                                  data-unit="<?= htmlspecialchars($config['unit'], ENT_QUOTES, 'UTF-8') ?>"
                                  data-notes="<?= htmlspecialchars($config['notes'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                  <i class="fas fa-pen mr-1"></i> Edit
                                </button>
                                <form method="post" action="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/admin/delete_project_variable.php" class="js-delete-variable mb-0">
                                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                  <input type="hidden" name="record_id" value="<?= htmlspecialchars((string) $config['id'], ENT_QUOTES, 'UTF-8') ?>">
                                  <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                  </button>
                                </form>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/dist/js/adminlte.min.js"></script>
<script>
  (function () {
    const selectedYear = <?= json_encode($selectedYear ? (string) $selectedYear : '') ?>;
    const openCreateBtn = document.getElementById('openCreateVariableModal');
    const csrfToken = window.KODUS_CSRF_TOKEN || '';
    const saveUrl = <?= json_encode($base_url . 'kodus/admin/save_project_variable.php') ?>;
    const deleteUrl = <?= json_encode($base_url . 'kodus/admin/delete_project_variable.php') ?>;

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    let activeEditorRecordId = '';

    function buildEditorHtml(config) {
      const variable = config || {};
      const isEdit = Boolean(variable.id);
      const isNumber = (variable.type || 'number') === 'number';
      const keyAttributes = isEdit ? 'readonly' : '';

      return `
        <form id="projectVariableForm" class="kodus-edit-shell">
          <input type="hidden" id="project-variable-record-id" name="record_id" value="${escapeHtml(variable.id || '')}">

          <div class="kodus-edit-header">
            <h3 class="kodus-edit-header-title">${isEdit ? escapeHtml(variable.label || variable.key || 'Project Variable') : 'New Project Variable'}</h3>
            <p class="kodus-edit-header-note">Manage year-based inputs in the same editor pattern used across Implementation Status. Save values once, then let pages compute from the stored data.</p>
          </div>

            <div class="kodus-edit-section">
              <h6 class="kodus-edit-section-title">Variable Identity</h6>
              <div class="kodus-edit-grid">
                <div class="kodus-edit-field">
                  <label>Fiscal Year</label>
                  <input id="project-variable-year" type="number" min="2000" max="2100" class="form-control" value="${escapeHtml(variable.year || selectedYear || '')}">
                </div>
                <div class="kodus-edit-field kodus-edit-field--full">
                  <label>Label</label>
                  <input id="project-variable-label" type="text" class="form-control" value="${escapeHtml(variable.label || '')}" placeholder="Human-readable name">
                  <span class="kodus-edit-help">You can safely update the label later without changing the variable key.</span>
                </div>
                <div class="kodus-edit-field">
                  <label>Variable Key</label>
                  <input id="project-variable-key" type="text" class="form-control" value="${escapeHtml(variable.key || '')}" placeholder="e.g. daily_wage_rate" ${keyAttributes}>
                  <span class="kodus-edit-help project-variable-key-hint">Use lowercase letters, numbers, and underscores only.</span>
                  <span class="kodus-edit-help">${isEdit ? 'The key is locked once the variable has been created.' : 'Set the key when adding the variable. It becomes locked after creation.'}</span>
                </div>
              </div>
            </div>

          <div class="kodus-edit-section">
            <h6 class="kodus-edit-section-title">Stored Value</h6>
            <div class="kodus-edit-grid">
              <div class="kodus-edit-field">
                <label>Value Type</label>
                <select id="project-variable-type" class="form-control">
                  <option value="number" ${isNumber ? 'selected' : ''}>Number</option>
                  <option value="text" ${!isNumber ? 'selected' : ''}>Text</option>
                </select>
              </div>
              <div class="kodus-edit-field project-variable-number-field" ${isNumber ? '' : 'style="display:none;"'}>
                <label>Numeric Value</label>
                <input id="project-variable-number" type="number" step="0.0001" class="form-control" value="${escapeHtml(variable.number ?? '')}" placeholder="e.g. 435.00">
              </div>
              <div class="kodus-edit-field project-variable-text-field" ${isNumber ? 'style="display:none;"' : ''}>
                <label>Text Value</label>
                <input id="project-variable-text" type="text" class="form-control" value="${escapeHtml(variable.text || '')}" placeholder="Enter text value">
              </div>
              <div class="kodus-edit-field">
                <label>Unit</label>
                <input id="project-variable-unit" type="text" class="form-control" value="${escapeHtml(variable.unit || '')}" placeholder="e.g. PHP/day or days">
              </div>
              <div class="kodus-edit-field kodus-edit-field--full">
                <label>Notes</label>
                <textarea id="project-variable-notes" class="form-control" rows="3" placeholder="Optional description or usage note.">${escapeHtml(variable.notes || '')}</textarea>
              </div>
            </div>
          </div>
        </form>
      `;
    }

    function bindEditorFieldBehavior() {
      const typeField = document.getElementById('project-variable-type');
      const numberField = document.querySelector('.project-variable-number-field');
      const textField = document.querySelector('.project-variable-text-field');
      const labelField = document.getElementById('project-variable-label');
      const keyField = document.getElementById('project-variable-key');
      const recordIdField = document.getElementById('project-variable-record-id');
      const isEdit = Boolean(recordIdField && recordIdField.value.trim());
      let keyManuallyEdited = false;

      function slugifyKey(value) {
        return String(value == null ? '' : value)
          .toLowerCase()
          .trim()
          .replace(/[^a-z0-9]+/g, '_')
          .replace(/^_+|_+$/g, '')
          .replace(/_+/g, '_');
      }

      function syncValueFields() {
        const isNumber = typeField.value === 'number';
        if (numberField) {
          numberField.style.display = isNumber ? '' : 'none';
        }
        if (textField) {
          textField.style.display = isNumber ? 'none' : '';
        }
      }

      if (typeField) {
        typeField.addEventListener('change', syncValueFields);
        syncValueFields();
      }

      if (!isEdit && labelField && keyField) {
        keyManuallyEdited = keyField.value.trim() !== '';

        labelField.addEventListener('input', function () {
          if (keyManuallyEdited) {
            return;
          }

          keyField.value = slugifyKey(labelField.value);
        });

        keyField.addEventListener('input', function () {
          const generatedKey = slugifyKey(labelField.value);
          const currentKey = keyField.value.trim();
          keyManuallyEdited = currentKey !== '' && currentKey !== generatedKey;
        });
      }
    }

    function openVariableEditor(config) {
      const variable = config || {};
      const isEdit = Boolean(variable.id);
      activeEditorRecordId = variable.id ? String(variable.id) : '';

      Swal.fire({
        title: isEdit ? 'Edit Project Variable' : 'Add Project Variable',
        width: 720,
        kodusHeroHeader: false,
        customClass: {
          popup: 'kodus-edit-popup'
        },
        html: buildEditorHtml(variable),
        showCancelButton: true,
        confirmButtonText: isEdit ? '<i class="fas fa-save"></i>' : '<i class="fas fa-plus"></i>',
        cancelButtonText: '<i class="fas fa-times"></i>',
        didOpen: () => {
          bindEditorFieldBehavior();
          const firstInput = document.getElementById('project-variable-year');
          if (firstInput) {
            firstInput.focus();
          }
        },
        preConfirm: () => {
          const hiddenRecordId = document.getElementById('project-variable-record-id');
          const recordId = (activeEditorRecordId || (hiddenRecordId ? hiddenRecordId.value.trim() : '')).trim();
          const fiscalYear = document.getElementById('project-variable-year').value.trim();
          const variableKey = document.getElementById('project-variable-key').value.trim();
          const variableLabel = document.getElementById('project-variable-label').value.trim();
          const valueType = document.getElementById('project-variable-type').value;
          const valueNumber = document.getElementById('project-variable-number').value.trim();
          const valueText = document.getElementById('project-variable-text').value.trim();
          const unit = document.getElementById('project-variable-unit').value.trim();
          const notes = document.getElementById('project-variable-notes').value.trim();

          if (!fiscalYear) {
            Swal.showValidationMessage('Fiscal year is required.');
            return false;
          }

          if (!variableKey || !/^[a-z0-9_]+$/.test(variableKey)) {
            Swal.showValidationMessage('Variable key must use lowercase letters, numbers, and underscores only.');
            return false;
          }

          if (!variableLabel) {
            Swal.showValidationMessage('Variable label is required.');
            return false;
          }

          if (valueType === 'number' && (valueNumber === '' || Number.isNaN(Number(valueNumber)))) {
            Swal.showValidationMessage('A numeric value is required for number variables.');
            return false;
          }

          if (valueType === 'text' && valueText === '') {
            Swal.showValidationMessage('A text value is required for text variables.');
            return false;
          }

          const formData = new FormData();
          formData.append('csrf_token', csrfToken);
          formData.append('record_id', recordId);
          formData.append('fiscal_year', fiscalYear);
          formData.append('variable_key', variableKey);
          formData.append('variable_label', variableLabel);
          formData.append('value_type', valueType);
          formData.append('value_number', valueType === 'number' ? valueNumber : '');
          formData.append('value_text', valueType === 'text' ? valueText : '');
          formData.append('unit', unit);
          formData.append('notes', notes);

          return fetch(saveUrl, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            body: formData
          })
          .then(async (response) => {
            const data = await response.json().catch(() => ({ success: false, message: 'Could not save this variable.' }));
            if (!response.ok || !data.success) {
              throw new Error(data.message || 'Could not save this variable.');
            }
            return data;
          })
          .catch((error) => {
            Swal.showValidationMessage(error.message || 'Could not save this variable.');
          });
        }
      }).then((result) => {
        activeEditorRecordId = '';
        if (result.isConfirmed) {
          Swal.fire({
            icon: 'success',
            title: 'Saved',
            text: result.value?.message || 'Project variable saved successfully.',
            timer: 1400,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        }
      });
    }

    openCreateBtn.addEventListener('click', function () {
      openVariableEditor({
        year: selectedYear || '',
        type: 'number'
      });
    });

    document.querySelectorAll('.js-load-variable').forEach(function (button) {
      button.addEventListener('click', function () {
        openVariableEditor({
          id: this.dataset.id || '',
          year: this.dataset.year || '',
          key: this.dataset.key || '',
          label: this.dataset.label || '',
          type: this.dataset.type || 'number',
          number: this.dataset.number || '',
          text: this.dataset.text || '',
          unit: this.dataset.unit || '',
          notes: this.dataset.notes || ''
        });
      });
    });

    document.querySelectorAll('.js-delete-variable').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Delete this variable?',
          text: 'This will remove the selected project variable record for that fiscal year.',
          showCancelButton: true,
          confirmButtonText: 'Delete',
          confirmButtonColor: '#dc3545'
        }).then(function (result) {
          if (result.isConfirmed) {
            const formData = new FormData(form);

            fetch(deleteUrl, {
              method: 'POST',
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
              },
              body: formData
            })
            .then(async (response) => {
              const data = await response.json().catch(() => ({ success: false, message: 'Could not delete this variable.' }));
              if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not delete this variable.');
              }
              return data;
            })
            .then((data) => {
              Swal.fire({
                icon: 'success',
                title: 'Deleted',
                text: data.message || 'Project variable deleted successfully.',
                timer: 1400,
                showConfirmButton: false
              }).then(() => {
                window.location.reload();
              });
            })
            .catch((error) => {
              Swal.fire('Error', error.message || 'Could not delete this variable.', 'error');
            });
          }
        });
      });
    });
  })();
</script>
</body>
</html>
