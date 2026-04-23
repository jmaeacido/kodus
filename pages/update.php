<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
security_configure_runtime_for_web();
security_require_method(['POST']);
security_require_csrf_token();
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
include('../config.php');

const MEB_CHECKMARK = "\u{2713}";

function meb_update_is_ajax_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function meb_update_is_marked($value): bool
{
    $normalized = strtoupper(trim((string) $value));
    return in_array($normalized, [MEB_CHECKMARK, 'âœ“', 'Ã¢Å“â€œ', 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“', 'YES', 'Y', 'TRUE', '1'], true);
}

function meb_update_normalize_fourps($value): string
{
    $normalized = strtoupper(trim((string) $value));

    if ($normalized === 'G') {
        return 'G';
    }

    if ($normalized === 'M' || meb_update_is_marked($value)) {
        return 'M';
    }

    return '';
}

function meb_update_post_value(string $field, int $id, string $default = ''): string
{
    $values = $_POST[$field] ?? null;

    if (!is_array($values) || !array_key_exists($id, $values)) {
        return $default;
    }

    $value = $values[$id];
    return is_scalar($value) ? (string) $value : $default;
}

function meb_update_calculate_age(?string $birthDate): ?int
{
    $birthDate = trim((string) $birthDate);
    if ($birthDate === '') {
        return null;
    }

    $birthdate = date_create($birthDate);
    $today = new DateTimeImmutable('today');

    if (!$birthdate instanceof DateTimeInterface) {
        return null;
    }

    $age = date_diff($birthdate, $today)->y;
    return $age >= 0 ? $age : null;
}

function meb_update_finish(bool $success, string $message, string $returnTo): void
{
    if (meb_update_is_ajax_request()) {
        security_send_json([
            'success' => $success,
            'message' => $message,
            'redirect' => $returnTo,
        ], $success ? 200 : 400);
    }

    $_SESSION['meb_update_flash'] = [
        'type' => $success ? 'success' : 'error',
        'message' => $message,
    ];

    header('Location: ' . $returnTo);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied. Admins only.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ids'])) {
    $ids = $_POST['ids'];
    $updateSuccess = true;
    $returnTo = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['return_to'] ?? 'data-tracking-meb');
    $latestHistoryId = 0;

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $normalizedIds = array_values(array_filter(array_map('intval', (array) $ids)));

    if (empty($normalizedIds)) {
        $conn->close();
        meb_update_finish(false, 'No valid records were selected for update.', $returnTo);
    }

    $selectStmt = $conn->prepare('SELECT * FROM meb WHERE id = ?');
    $updateStmt = $conn->prepare(
        "UPDATE meb SET
            `lastName` = ?, `firstName` = ?, `middleName` = ?,
            `ext` = ?, `purok` = ?, `barangay` = ?, `birthDate` = ?,
            `age` = ?, `sex` = ?, `civilStatus` = ?, `nhts1` = ?, `nhts2` = ?,
            `fourPs` = ?, `F` = ?, `FF` = ?, `IS` = ?, `IP` = ?, `SC` = ?, `SP` = ?, `LW` = ?,
            `PW` = ?, `PWD` = ?, `OSY` = ?, `FR` = ?, `ybDs` = ?, `lgbtqia` = ?, `editReason` = ?
        WHERE `id` = ?"
    );

    if (!$selectStmt || !$updateStmt) {
        if ($selectStmt) {
            $selectStmt->close();
        }
        if ($updateStmt) {
            $updateStmt->close();
        }
        $conn->close();
        meb_update_finish(false, 'Failed to prepare the update request.', $returnTo);
    }

    foreach ($normalizedIds as $id) {
        $oldData = [];
        $selectStmt->bind_param('i', $id);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        if ($result instanceof mysqli_result) {
            $oldData = $result->fetch_assoc() ?: [];
            $result->free();
        }

        if (empty($oldData)) {
            $updateSuccess = false;
            continue;
        }

        $lastName = meb_update_post_value('lastName', $id);
        $firstName = meb_update_post_value('firstName', $id);
        $middleName = meb_update_post_value('middleName', $id);
        $ext = meb_update_post_value('ext', $id);
        $purok = meb_update_post_value('purok', $id);
        $barangay = meb_update_post_value('barangay', $id);
        $rawBirthDate = meb_update_post_value('birthDate', $id);
        $birthDate = $rawBirthDate;
        $computedAge = meb_update_calculate_age($rawBirthDate);
        $age = $computedAge ?? intval(meb_update_post_value('age', $id, '0'));
        $sex = meb_update_post_value('sex', $id);
        $civilStatus = meb_update_post_value('civilStatus', $id);
        $nhts1 = meb_update_post_value('nhts1', $id);
        $nhts2 = meb_update_post_value('nhts2', $id);
        $fourPs = meb_update_normalize_fourps(meb_update_post_value('fourPs', $id));
        $F = meb_update_post_value('F', $id);
        $FF = meb_update_post_value('FF', $id);
        $IS = meb_update_post_value('IS', $id);
        $IP = meb_update_post_value('IP', $id);
        $SC = $age >= 60 ? MEB_CHECKMARK : '';
        $SP = meb_update_post_value('SP', $id);
        $LW = meb_update_post_value('LW', $id);
        $PW = meb_update_post_value('PW', $id);
        $PWD = meb_update_post_value('PWD', $id);
        $OSY = meb_update_post_value('OSY', $id);
        $FR = meb_update_post_value('FR', $id);
        $ybDs = meb_update_post_value('ybDs', $id);
        $lgbtqia = meb_update_post_value('lgbtqia', $id);
        $editReason = meb_update_post_value('editReason', $id);

        $updateStmt->bind_param(
            'sssssssisssssssssssssssssssi',
            $lastName,
            $firstName,
            $middleName,
            $ext,
            $purok,
            $barangay,
            $birthDate,
            $age,
            $sex,
            $civilStatus,
            $nhts1,
            $nhts2,
            $fourPs,
            $F,
            $FF,
            $IS,
            $IP,
            $SC,
            $SP,
            $LW,
            $PW,
            $PWD,
            $OSY,
            $FR,
            $ybDs,
            $lgbtqia,
            $editReason,
            $id
        );

        if ($updateStmt->execute()) {
            $newData = [
                'lastName' => $lastName,
                'firstName' => $firstName,
                'middleName' => $middleName,
                'ext' => $ext,
                'purok' => $purok,
                'barangay' => $barangay,
                'birthDate' => $birthDate,
                'age' => $age,
                'sex' => $sex,
                'civilStatus' => $civilStatus,
                'nhts1' => $nhts1,
                'nhts2' => $nhts2,
                'fourPs' => $fourPs,
                'F' => $F,
                'FF' => $FF,
                'IS' => $IS,
                'IP' => $IP,
                'SC' => $SC,
                'SP' => $SP,
                'LW' => $LW,
                'PW' => $PW,
                'PWD' => $PWD,
                'OSY' => $OSY,
                'FR' => $FR,
                'ybDs' => $ybDs,
                'lgbtqia' => $lgbtqia,
                'editReason' => $editReason,
            ];

            $changes = [];
            foreach ($newData as $field => $newValue) {
                $oldValue = $oldData[$field] ?? '';
                if ($oldValue != $newValue) {
                    $changes[] = "$field: '$oldValue' -> '$newValue'";
                }
            }

            $details = "Updated MEB record ID: $id | Reason: $editReason";
            if (!empty($changes)) {
                $details .= ' | Changes: ' . implode(', ', $changes);
            }

            $historyId = meb_change_history_create(
                $conn,
                $id,
                $userId,
                $editReason,
                $oldData,
                array_merge($oldData, $newData)
            );
            if ($historyId > 0) {
                $latestHistoryId = $historyId;
            }

            audit_log($conn, $userId, 'Update MEB Record', $details, $ipAddress);
        } else {
            $updateSuccess = false;
        }
    }

    $selectStmt->close();
    $updateStmt->close();

    if ($updateSuccess) {
        $recordCount = count($normalizedIds);
        app_notification_create($conn, [
            'category' => 'meb',
            'title' => $recordCount === 1 ? 'MEB record updated' : 'MEB records updated',
            'message' => app_notification_actor_name_from_session() . ' updated ' . $recordCount . ' MEB ' . ($recordCount === 1 ? 'record' : 'records') . '.',
            'url' => $recordCount === 1 && $latestHistoryId > 0
                ? app_notification_build_url('pages/meb-change-review.php?id=' . rawurlencode((string) $latestHistoryId))
                : app_notification_build_url('pages/data-tracking-meb'),
            'icon_class' => 'fas fa-user-edit',
            'color_class' => 'text-info',
            'actor_user_id' => $userId,
            'actor_name' => app_notification_actor_name_from_session(),
        ]);
        kodus_socket_broadcast('kodus.meb', 'meb.changed', [
            'action' => 'updated',
            'ids' => $normalizedIds,
            'actor_id' => $userId,
        ]);
        kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
            'action' => 'updated',
            'ids' => $normalizedIds,
            'actor_id' => $userId,
        ]);
    }

    $conn->close();
    meb_update_finish(
        $updateSuccess,
        $updateSuccess ? 'Changes have been saved successfully.' : 'Failed to save changes.',
        $returnTo
    );
}
?>
