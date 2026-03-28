<?php
include ('../header.php');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
  header("HTTP/1.1 403 Forbidden");
  echo "Access denied. Admins only.";
  exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ids'])) {
    $ids = $_POST['ids'];
    $update_success = true;
    $returnTo = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['return_to'] ?? 'data-tracking-meb-validation');

    $userId = $_SESSION['user_id'] ?? 0;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    foreach ($ids as $id) {
        $id = intval($id);

        // 🔎 Fetch old values before update
        $oldData = [];
        $res = $conn->query("SELECT * FROM meb WHERE id=$id");
        if ($res && $res->num_rows > 0) {
            $oldData = $res->fetch_assoc();
        }

        // New values
        $lastName = $conn->real_escape_string($_POST['lastName'][$id]);
        $firstName = $conn->real_escape_string($_POST['firstName'][$id]);
        $middleName = $conn->real_escape_string($_POST['middleName'][$id]);
        $ext = $conn->real_escape_string($_POST['ext'][$id]);
        $purok = $conn->real_escape_string($_POST['purok'][$id]);
        $barangay = $conn->real_escape_string($_POST['barangay'][$id]);
        $birthDate = $conn->real_escape_string($_POST['birthDate'][$id]);
        $age = intval($_POST['age'][$id]);
        $sex = $conn->real_escape_string($_POST['sex'][$id]);
        $civilStatus = $conn->real_escape_string($_POST['civilStatus'][$id]);
        $nhts1 = $conn->real_escape_string($_POST['nhts1'][$id] ?? '');
        $nhts2 = $conn->real_escape_string($_POST['nhts2'][$id] ?? '');
        $fourPs = $conn->real_escape_string($_POST['fourPs'][$id] ?? '');
        $F = $conn->real_escape_string($_POST['F'][$id] ?? '');
        $FF = $conn->real_escape_string($_POST['FF'][$id] ?? '');
        $IP = $conn->real_escape_string($_POST['IP'][$id] ?? '');
        $SC = $conn->real_escape_string($_POST['SC'][$id] ?? '');
        $SP = $conn->real_escape_string($_POST['SP'][$id] ?? '');
        $PW = $conn->real_escape_string($_POST['PW'][$id] ?? '');
        $PWD = $conn->real_escape_string($_POST['PWD'][$id] ?? '');
        $OSY = $conn->real_escape_string($_POST['OSY'][$id] ?? '');
        $FR = $conn->real_escape_string($_POST['FR'][$id] ?? '');
        $ybDs = $conn->real_escape_string($_POST['ybDs'][$id] ?? '');
        $lgbtqia = $conn->real_escape_string($_POST['lgbtqia'][$id] ?? '');
        $editReason = $conn->real_escape_string($_POST['editReason'][$id] ?? '');

        $sql = "UPDATE meb SET 
                lastName='$lastName', firstName='$firstName', middleName='$middleName', 
                ext='$ext', purok='$purok', barangay='$barangay', birthDate='$birthDate', 
                age=$age, sex='$sex', civilStatus='$civilStatus', nhts1='$nhts1', nhts2='$nhts2', 
                fourPs='$fourPs', F='$F', FF='$FF', IP='$IP', SC='$SC', SP='$SP', PW='$PW', 
                PWD='$PWD', OSY='$OSY', FR='$FR', ybDs='$ybDs', lgbtqia='$lgbtqia', editReason='$editReason' 
                WHERE id=$id";

        if ($conn->query($sql)) {
            // ✅ Compare old vs new
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
                'IP' => $IP,
                'SC' => $SC,
                'SP' => $SP,
                'PW' => $PW,
                'PWD' => $PWD,
                'OSY' => $OSY,
                'FR' => $FR,
                'ybDs' => $ybDs,
                'lgbtqia' => $lgbtqia
            ];

            $changes = [];
            foreach ($newData as $field => $newValue) {
                $oldValue = $oldData[$field] ?? '';
                if ($oldValue != $newValue) {
                    $changes[] = "$field: '$oldValue' → '$newValue'";
                }
            }

            $details = "Updated MEB record ID: $id | Reason: $editReason";
            if (!empty($changes)) {
                $details .= " | Changes: " . implode(", ", $changes);
            }

            // ✅ Insert into audit_logs
            $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $action = "Update MEB Record";
            $logStmt->bind_param("isss", $userId, $action, $details, $ipAddress);
            $logStmt->execute();
            $logStmt->close();
        } else {
            $update_success = false;
        }
    }

    $conn->close();

    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '" . ($update_success ? 'success' : 'error') . "',
                title: '" . ($update_success ? 'Success!' : 'Error!') . "',
                text: '" . ($update_success ? 'Changes have been saved successfully.' : 'Failed to save changes.') . "',
                background: '#343a40',
                color: '#fff',
                showConfirmButton: true
            }).then(() => {
                window.location.href = '" . $returnTo . "';
            });
        });
    </script>";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
    <style>
        body {
            background: #454d55;
        }
    </style>
</head>
<body>

</body>
</html>
