<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../base_url.php';

auth_handle_page_access($conn);
auth_apply_security_headers();

$historyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$history = $historyId > 0 ? meb_change_history_find($conn, $historyId) : null;

include('../header.php');
include('../sidenav.php');

function meb_review_label_map(): array
{
    return [
        'lastName' => 'Last Name',
        'firstName' => 'First Name',
        'middleName' => 'Middle Name',
        'ext' => 'Extension',
        'purok' => 'Purok',
        'barangay' => 'Barangay',
        'birthDate' => 'Birthdate',
        'age' => 'Age',
        'sex' => 'Sex',
        'civilStatus' => 'Civil Status',
        'nhts1' => 'NHTS-PR Listahanan 3',
        'nhts2' => 'LSWDO Assessment',
        'fourPs' => '4Ps',
        'F' => 'Farmer',
        'FF' => 'Fisherfolk',
        'IS' => 'Informal Sector',
        'IP' => 'Indigenous People',
        'SC' => 'Senior Citizen',
        'SP' => 'Solo Parent',
        'LW' => 'Lactating Woman',
        'PW' => 'Pregnant Woman',
        'PWD' => 'PWD',
        'OSY' => 'Out of School Youth',
        'FR' => 'Former Rebel',
        'ybDs' => 'YB/PWUD',
        'lgbtqia' => 'LGBTQIA+',
        'editReason' => 'Reason for Edit',
    ];
}

function meb_review_field_order(): array
{
    return [
        'lastName',
        'firstName',
        'middleName',
        'ext',
        'purok',
        'barangay',
        'birthDate',
        'age',
        'sex',
        'civilStatus',
        'nhts1',
        'nhts2',
        'fourPs',
        'F',
        'FF',
        'IS',
        'IP',
        'SC',
        'SP',
        'LW',
        'PW',
        'PWD',
        'OSY',
        'FR',
        'ybDs',
        'lgbtqia',
        'editReason',
    ];
}

function meb_review_group_map(): array
{
    return [
        'Identity' => [
            'lastName',
            'firstName',
            'middleName',
            'ext',
        ],
        'Location' => [
            'purok',
            'barangay',
        ],
        'Demographics' => [
            'birthDate',
            'age',
            'sex',
            'civilStatus',
        ],
        'Poverty Tags' => [
            'nhts1',
            'nhts2',
            'fourPs',
        ],
        'Sectors' => [
            'F',
            'FF',
            'IS',
            'IP',
            'SC',
            'SP',
            'LW',
            'PW',
            'PWD',
            'OSY',
            'FR',
            'ybDs',
            'lgbtqia',
        ],
        'Edit Details' => [
            'editReason',
        ],
    ];
}

function meb_review_normalize_value($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    $stringValue = trim((string) $value);
    return $stringValue === '' ? '[empty]' : $stringValue;
}

function meb_review_display_value(string $field, $value): string
{
    $normalized = trim((string) $value);

    if ($normalized === '') {
        return '[empty]';
    }

    if ($field === 'nhts1' || $field === 'nhts2') {
        return $normalized === '✓' ? 'Selected' : $normalized;
    }

    if ($field === 'fourPs') {
        $upper = strtoupper($normalized);
        if ($upper === 'M') {
            return 'M - Member';
        }
        if ($upper === 'G') {
            return 'G - Graduated';
        }
        if ($normalized === '✓') {
            return 'Legacy checkmark';
        }
        return $normalized;
    }

    $sectorFields = ['F', 'FF', 'IS', 'IP', 'SC', 'SP', 'LW', 'PW', 'OSY', 'FR', 'ybDs', 'lgbtqia'];
    if (in_array($field, $sectorFields, true)) {
        return $normalized === '✓' ? 'Checked' : $normalized;
    }

    if ($field === 'PWD') {
        $pwdLabels = [
            'A' => 'A - Multiple Disabilities',
            'B' => 'B - Intellectual Disability',
            'C' => 'C - Learning Disability',
            'D' => 'D - Mental Disability',
            'E' => 'E - Physical Disability (Orthopedic)',
            'F' => 'F - Psychosocial Disability',
            'G' => 'G - Non-apparent Visual Disability',
            'H' => 'H - Non-apparent Speech and Language Impairment',
            'I' => 'I - Non-apparent Cancer',
            'J' => 'J - Non-apparent Rare Disease',
            'K' => 'K - Deaf/Hard of Hearing Disability',
            '✓' => 'Checked',
        ];

        $upper = strtoupper($normalized);
        return $pwdLabels[$upper] ?? $normalized;
    }

    return $normalized;
}

$labelMap = meb_review_label_map();
$groupedChangeRows = [];

if ($history) {
    foreach (meb_review_group_map() as $groupLabel => $fields) {
        $rows = [];

        foreach ($fields as $field) {
            $beforeRaw = $history['before'][$field] ?? null;
            $afterRaw = $history['after'][$field] ?? null;
            $beforeValue = meb_review_display_value($field, meb_review_normalize_value($beforeRaw));
            $afterValue = meb_review_display_value($field, meb_review_normalize_value($afterRaw));

            if ($beforeValue === $afterValue) {
                continue;
            }

            $rows[] = [
                'label' => $labelMap[$field] ?? $field,
                'before' => $beforeValue,
                'after' => $afterValue,
            ];
        }

        if ($rows !== []) {
            $groupedChangeRows[$groupLabel] = $rows;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | MEB Change Review</title>
  <style>
    :root {
      --meb-review-card-border: rgba(13, 110, 253, 0.12);
      --meb-review-card-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
      --meb-review-hero-bg: linear-gradient(135deg, #0d6efd, #0f172a);
      --meb-review-hero-text: #ffffff;
      --meb-review-meta-bg: rgba(255, 255, 255, 0.09);
      --meb-review-meta-border: rgba(255, 255, 255, 0.12);
      --meb-review-group-border: rgba(15, 23, 42, 0.08);
      --meb-review-group-title-bg: rgba(13, 110, 253, 0.08);
      --meb-review-group-title-text: #0b5ed7;
      --meb-review-group-title-border: rgba(15, 23, 42, 0.08);
      --meb-review-table-bg: #ffffff;
      --meb-review-table-head-bg: #f8f9fa;
      --meb-review-table-text: #1f2937;
      --meb-review-before-bg: rgba(220, 53, 69, 0.06);
      --meb-review-after-bg: rgba(25, 135, 84, 0.06);
    }

    body[data-theme="dark"] {
      --meb-review-card-border: rgba(148, 163, 184, 0.16);
      --meb-review-card-shadow: 0 18px 46px rgba(0, 0, 0, 0.28);
      --meb-review-hero-bg: linear-gradient(135deg, #1d4ed8, #0f172a);
      --meb-review-hero-text: #e5eef9;
      --meb-review-meta-bg: rgba(255, 255, 255, 0.06);
      --meb-review-meta-border: rgba(255, 255, 255, 0.1);
      --meb-review-group-border: rgba(148, 163, 184, 0.18);
      --meb-review-group-title-bg: rgba(37, 99, 235, 0.18);
      --meb-review-group-title-text: #c7dcff;
      --meb-review-group-title-border: rgba(148, 163, 184, 0.16);
      --meb-review-table-bg: #2b3441;
      --meb-review-table-head-bg: #344050;
      --meb-review-table-text: #e5eef9;
      --meb-review-before-bg: rgba(220, 53, 69, 0.16);
      --meb-review-after-bg: rgba(25, 135, 84, 0.16);
    }

    .meb-review-card {
      border-radius: 18px;
      border: 1px solid var(--meb-review-card-border);
      box-shadow: var(--meb-review-card-shadow);
      background: var(--meb-review-table-bg);
      color: var(--meb-review-table-text);
    }

    .meb-review-hero {
      padding: 1.35rem 1.5rem;
      border-radius: 18px 18px 0 0;
      background: var(--meb-review-hero-bg);
      color: var(--meb-review-hero-text);
    }

    .meb-review-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.9rem;
      margin-top: 1rem;
    }

    .meb-review-meta-item {
      padding: 0.85rem 1rem;
      border-radius: 14px;
      background: var(--meb-review-meta-bg);
      border: 1px solid var(--meb-review-meta-border);
    }

    .meb-review-meta-label {
      display: block;
      margin-bottom: 0.25rem;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      opacity: 0.75;
    }

    .meb-review-meta-value {
      font-size: 0.96rem;
      font-weight: 600;
      line-height: 1.45;
      word-break: break-word;
    }

    .meb-review-table td,
    .meb-review-table th {
      vertical-align: top;
    }

    .meb-review-group {
      margin-bottom: 1.1rem;
      border: 1px solid var(--meb-review-group-border);
      border-radius: 16px;
      overflow: hidden;
      background: var(--meb-review-table-bg);
    }

    .meb-review-group:last-child {
      margin-bottom: 0;
    }

    .meb-review-group-title {
      padding: 0.95rem 1.1rem;
      margin: 0;
      font-size: 0.96rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      background: var(--meb-review-group-title-bg);
      color: var(--meb-review-group-title-text);
      border-bottom: 1px solid var(--meb-review-group-title-border);
    }

    .meb-review-before {
      background: var(--meb-review-before-bg);
    }

    .meb-review-after {
      background: var(--meb-review-after-bg);
    }

    .meb-review-table {
      color: var(--meb-review-table-text);
      background: var(--meb-review-table-bg);
    }

    .meb-review-table thead th {
      background: var(--meb-review-table-head-bg);
      color: var(--meb-review-table-text);
    }

    .meb-review-table td,
    .meb-review-table th {
      border-color: var(--meb-review-group-border);
    }

    body[data-theme="dark"] .alert-warning {
      background: rgba(245, 158, 11, 0.14);
      border-color: rgba(245, 158, 11, 0.26);
      color: #fde68a;
    }

    body[data-theme="dark"] .alert-info {
      background: rgba(13, 202, 240, 0.14);
      border-color: rgba(13, 202, 240, 0.26);
      color: #b6effb;
    }
    @media (max-width: 1600px) {
      .meb-review-hero {
        padding: 1.15rem 1.25rem;
      }
      .meb-review-meta-item,
      .meb-review-group-title {
        padding: 0.8rem 0.9rem;
      }
    }
    @media (max-width: 1366px) {
      .meb-review-meta {
        gap: 0.75rem;
      }
      .meb-review-meta-value {
        font-size: 0.88rem;
      }
      .meb-review-group-title {
        font-size: 0.88rem;
      }
      .meb-review-table {
        font-size: 0.86rem;
      }
      .meb-review-table td,
      .meb-review-table th {
        padding: 0.6rem 0.7rem;
      }
    }
    @media (max-width: 1280px) {
      .meb-review-hero h3 {
        font-size: 1.08rem;
      }
      .meb-review-hero p,
      .meb-review-meta-label {
        font-size: 0.8rem;
      }
    }
    @media (max-width: 1024px) {
      .meb-review-hero {
        padding: 1rem;
      }
      .meb-review-card .card-body {
        padding: 0.9rem;
      }
    }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">MEB Change Review</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $app_root; ?>home">Home</a></li>
              <li class="breadcrumb-item"><a href="<?php echo $app_root; ?>pages/data-tracking-meb">MEB</a></li>
              <li class="breadcrumb-item active">Change Review</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <?php if (!$history): ?>
          <div class="alert alert-warning">
            The requested MEB change history could not be found.
          </div>
        <?php else: ?>
          <div class="card meb-review-card">
            <div class="meb-review-hero">
              <div class="d-flex flex-wrap align-items-start justify-content-between">
                <div>
                  <div class="text-uppercase small font-weight-bold" style="letter-spacing: 0.08em; opacity: 0.75;">Before and After</div>
                  <h3 class="mb-2">MEB record #<?php echo (int) $history['meb_id']; ?></h3>
                  <p class="mb-0" style="opacity: 0.86;">Review the exact values before and after the edit.</p>
                </div>
                <a href="<?php echo htmlspecialchars($app_root . 'pages/data-tracking-meb?focus_id=' . (int) $history['meb_id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light btn-sm mt-2 mt-sm-0">
                  Open Record
                </a>
              </div>

              <div class="meb-review-meta">
                <div class="meb-review-meta-item">
                  <span class="meb-review-meta-label">Edited By</span>
                  <span class="meb-review-meta-value"><?php echo htmlspecialchars(trim((string) ($history['first_name'] ?? '') . ' ' . (string) ($history['last_name'] ?? '')) ?: (string) ($history['username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="meb-review-meta-item">
                  <span class="meb-review-meta-label">Edited At</span>
                  <span class="meb-review-meta-value"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime((string) $history['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="meb-review-meta-item">
                  <span class="meb-review-meta-label">Reason</span>
                  <span class="meb-review-meta-value"><?php echo htmlspecialchars((string) ($history['edit_reason'] ?? '[empty]'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
              </div>
            </div>

            <div class="card-body">
              <?php if ($groupedChangeRows === []): ?>
                <div class="alert alert-info mb-0">No field differences were captured for this update.</div>
              <?php else: ?>
                <?php foreach ($groupedChangeRows as $groupLabel => $rows): ?>
                  <div class="meb-review-group">
                    <h4 class="meb-review-group-title"><?php echo htmlspecialchars((string) $groupLabel, ENT_QUOTES, 'UTF-8'); ?></h4>
                    <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 900px;">
                      <table class="table table-bordered meb-review-table mb-0">
                        <thead>
                          <tr>
                            <th style="width: 22%;">Field</th>
                            <th style="width: 39%;">Before</th>
                            <th style="width: 39%;">After</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($rows as $row): ?>
                            <tr>
                              <th><?php echo htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                              <td class="meb-review-before"><?php echo htmlspecialchars((string) $row['before'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td class="meb-review-after"><?php echo htmlspecialchars((string) $row['after'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
</body>
</html>
