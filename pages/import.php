<?php
    include ('../header.php');
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    require '../vendor/autoload.php'; // Load PhpSpreadsheet library
    use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize variables for messages
$errorMsg = "";
$successMsg = "";

// Check if the import button is clicked
if (isset($_POST['import'])) {
    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'kodus_db'); // Update with your DB details
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get the latest batch_id
    $sql = "SELECT MAX(batch_id) AS latest_batch_id FROM meb";
    $result = $conn->query($sql);
    $latestBatchId = 10001; // Default starting batch_id

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!is_null($row['latest_batch_id'])) {
            $latestBatchId = intval($row['latest_batch_id']) + 1;
        }
    }

    if (strlen($latestBatchId) > 5) {
        $_SESSION['success_msg'] = "Batch ID overflow. Please reset the database.";
        header("Location: data-tracking-meb");
        exit;
    }

    $batchId = str_pad($latestBatchId, 5, '0', STR_PAD_LEFT);

    // Define expected column headers
    $expectedColumns = ['LAST NAME','FIRST NAME','MIDDLE NAME','EXT.','PUROK','BARANGAY', 'LGU', 'PROVINCE','BIRTHDATE','AGE','SEX','CIVIL STATUS','National Household Targeting System for Poverty Reduction (NHTS-PR) Poor','National Household Targeting System for Poverty Reduction (NHTS-PR) Non-poor but considered poor by LSWDO assessment','Pantawid Pamilyang Pilipino Program (4Ps)','Farmers (F)','Fisher-folks (FF)','Indigenous People (IP)','Senior Citizen (SC)','Solo Parent (SP)','Pregnant Women (PW)','Persons with Disability (PWD)','Out-of-School Youth (OSY)','Former Rebel (FR)','YAKAP Bayan/ Drug Surenderee (YB/DS)', 'LGBTQIA+'];

    // Check if a file is uploaded
    if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excelFile']['tmp_name'];
        $fileName = $_FILES['excelFile']['name'];
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, ['xls', 'xlsx'])) {
            try {
                $spreadsheet = IOFactory::load($fileTmpPath);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray();

                // Validate column headers
                $fileColumns = array_map(function ($column) {
                    return is_null($column) ? '' : trim($column);
                }, $data[0]); // Get the first row as headers

                if ($fileColumns !== $expectedColumns) {
                    $errorMsg = "Column mismatch! Expected columns: " . implode(", ", $expectedColumns) . ".";
                } else {

                    // Skip the header row and insert data into the database
                    $isFirstRow = true;
                    $rowCount = 0;

                    foreach ($data as $row) {
                        if ($isFirstRow) {
                            $isFirstRow = false;
                            continue;
                        }

                        // Map the Excel columns to database table columns
                        $lastName = isset($row[0]) ? $conn->real_escape_string($row[0]) : '';
                        $firstName = isset($row[1]) ? $conn->real_escape_string($row[1]) : '';
                        $middleName = isset($row[2]) ? $conn->real_escape_string($row[2]) : '';
                        $ext = isset($row[3]) ? $conn->real_escape_string($row[3]) : '';
                        $purok = isset($row[4]) ? $conn->real_escape_string($row[4]) : '';
                        $barangay = isset($row[5]) ? $conn->real_escape_string($row[5]) : '';
                        $lgu = isset($row[6]) ? $conn->real_escape_string($row[6]) : '';
                        $province = isset($row[7]) ? $conn->real_escape_string($row[7]) : '';
                        $birthDate = (!empty($row[8]) && strtotime($row[8])) ? date("Y-m-d", strtotime($row[8])) : "NULL";;  // Default to null if not set
                        $age = isset($row[9]) ? (int)$row[9] : 0;  // Default to 0 if not set
                        $sex = isset($row[10]) ? $conn->real_escape_string($row[10]) : '';
                        $civilStatus = isset($row[11]) ? $conn->real_escape_string($row[11]) : '';
                        $nhts1 = isset($row[12]) ? $conn->real_escape_string($row[12]) : '';
                        $nhts2 = isset($row[13]) ? $conn->real_escape_string($row[13]) : '';
                        $fourPs = isset($row[14]) ? $conn->real_escape_string($row[14]) : '';
                        $F = isset($row[15]) ? $conn->real_escape_string($row[15]) : '';
                        $FF = isset($row[16]) ? $conn->real_escape_string($row[16]) : '';
                        $IP = isset($row[17]) ? $conn->real_escape_string($row[17]) : '';
                        $SC = isset($row[18]) ? $conn->real_escape_string($row[18]) : '';
                        $SP = isset($row[19]) ? $conn->real_escape_string($row[19]) : '';
                        $PW = isset($row[20]) ? $conn->real_escape_string($row[20]) : '';
                        $PWD = isset($row[21]) ? $conn->real_escape_string($row[21]) : '';
                        $OSY = isset($row[22]) ? $conn->real_escape_string($row[22]) : '';
                        $FR = isset($row[23]) ? $conn->real_escape_string($row[23]) : '';
                        $ybDs = isset($row[24]) ? $conn->real_escape_string($row[24]) : '';
                        $lgbtqia = isset($row[25]) ? $conn->real_escape_string($row[25]) : '';

                        // Insert data into the database
                        $sql = "INSERT INTO meb (lastName, firstName, middleName, ext, purok, barangay, lgu, province, birthDate, age, sex, civilStatus, nhts1, nhts2, fourPs, F, FF, IP, SC, SP, PW, PWD, OSY, FR, ybDs, lgbtqia, batch_id) 
                            VALUES ('$lastName', '$firstName', '$middleName', '$ext', '$purok', '$barangay', '$lgu', '$province', " . ($birthDate !== "NULL" ? "'$birthDate'" : "NULL") . ", $age, '$sex', '$civilStatus', '$nhts1', '$nhts2', '$fourPs', '$F', '$FF', '$IP', '$SC', '$SP', '$PW', '$PWD', '$OSY', '$FR', '$ybDs', '$lgbtqia', $batchId)";
                        if ($conn->query($sql) === TRUE) {
                            $rowCount++;
                        }
                    }

                    if ($rowCount > 0) {
                        $successMsg = "Data imported successfully! Batch ID: $batchId";
                    } else {
                        $errorMsg = "No data was imported. Please check your file.";
                    }
                }
            } catch (Exception $e) {
                $errorMsg = "Error loading Excel file: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Invalid file type. Please upload an Excel file (.xls or .xlsx).";
        }
    } else {
        $errorMsg = "No file selected. Please choose an Excel file to import.";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Excel File</title>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <!-- <h2>Import Excel File</h2>
    <form action="import.php" method="POST" enctype="multipart/form-data">
        <label for="excelFile">Choose Excel File:</label>
        <input type="file" name="excelFile" id="excelFile" accept=".xlsx, .xls">
        <button type="submit" name="import">Import</button>
    </form> -->

    <!-- SweetAlert2 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Display SweetAlert2 message
        <?php if (!empty($errorMsg)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= htmlspecialchars($errorMsg) ?>',
            }).then(() => {
                // Reload the current page after the error message
                window.history.back();
            });
        <?php elseif (!empty($successMsg)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= htmlspecialchars($successMsg) ?>',
            }).then(() => {
                // Redirect to data-tracking-meb.php after the success message
                window.location.href = "data-tracking-meb";
            });
        <?php endif; ?>
    </script>
</body>
</html>
