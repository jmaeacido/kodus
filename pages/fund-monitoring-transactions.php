<?php
require_once '../security.php';
security_bootstrap_session();
security_require_method(['GET']);

require_once '../config.php';
require_once __DIR__ . '/../fund_monitoring_helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['selected_year'])) {
    http_response_code(403);
    exit;
}

if ((string) ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(403);
    exit;
}

$year = (int) $_SESSION['selected_year'];
$month = isset($_GET['month']) ? (int) $_GET['month'] : 0;
$month = max(1, min(12, $month));
$items = fund_monitoring_list_items_with_entries($conn, $year);

function fm_transaction_currency(float $value): string
{
    return number_format($value, 2);
}

foreach ($items as $item) {
    $totalObligations = 0.0;
    $totalDisbursement = 0.0;

    foreach ($item['monthly'] as $monthlyValues) {
        $totalObligations += (float) ($monthlyValues['obligations'] ?? 0);
        $totalDisbursement += (float) ($monthlyValues['disbursement'] ?? 0);
    }

    $item['total_obligations'] = $totalObligations;
    $item['total_disbursement'] = $totalDisbursement;
    $monthData = $item['monthly'][$month] ?? ['obligations' => 0, 'disbursement' => 0];
    ?>
    <tr
      class="fm-transaction-row"
      data-item-id="<?= (int) $item['id'] ?>"
      data-object-code="<?= htmlspecialchars($item['object_code_name'], ENT_QUOTES, 'UTF-8') ?>"
      data-search="<?= htmlspecialchars(strtolower($item['object_code_name'] . ' ' . $item['saro_number']), ENT_QUOTES, 'UTF-8') ?>"
      data-base-total-obligations="<?= htmlspecialchars(number_format((float) $item['total_obligations'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
      data-base-total-disbursement="<?= htmlspecialchars(number_format((float) $item['total_disbursement'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
      data-base-month-obligations="<?= htmlspecialchars(number_format((float) $monthData['obligations'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
      data-base-month-disbursement="<?= htmlspecialchars(number_format((float) $monthData['disbursement'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
    >
      <td>
        <?= htmlspecialchars($item['saro_number'], ENT_QUOTES, 'UTF-8') ?>
        <input type="hidden" name="item_id[]" value="<?= (int) $item['id'] ?>">
      </td>
      <td><?= htmlspecialchars($item['object_code_name'], ENT_QUOTES, 'UTF-8') ?></td>
      <td><input type="number" step="0.01" min="0" class="form-control fm-transaction-obligations" name="obligations[]" value="<?= htmlspecialchars(number_format((float) $monthData['obligations'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"></td>
      <td><input type="number" step="0.01" min="0" class="form-control fm-transaction-disbursement" name="disbursement[]" value="<?= htmlspecialchars(number_format((float) $monthData['disbursement'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"></td>
      <td>
        <div class="fm-total-stack">
          <span class="fm-total-label">Annual obligation</span>
          <span class="fm-total-value fm-transaction-total-obligations"><?= fm_transaction_currency((float) $item['total_obligations']) ?></span>
        </div>
      </td>
      <td>
        <div class="fm-total-stack">
          <span class="fm-total-label">Annual disbursement</span>
          <span class="fm-total-value fm-transaction-total-disbursement"><?= fm_transaction_currency((float) $item['total_disbursement']) ?></span>
        </div>
      </td>
      <td>PHP <?= fm_transaction_currency((float) $item['adjusted_appropriation']) ?></td>
    </tr>
    <?php
}
