<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$managerID = $_SESSION['user_id'];

$asset = null;
$databaseError = '';
$removeError = '';

$assetID = trim($_GET['assetID'] ?? $_POST['assetID'] ?? '');

// Asset ID Required
if ($assetID === '') {
    header("Location: manager_assets.php");
    exit();
}

$conn = null;

try {
    $conn = new mysqli(
        "127.0.0.1",
        "root",
        "",
        "circulapu_db",
        3306
    );

    $conn->set_charset("utf8mb4");

    // Retrieve Selected Asset
    $statement = $conn->prepare("
        SELECT
            a.assetID,
            a.assetName,
            a.listingStatus,
            a.availabilityStatus,
            c.categoryName
        FROM assets a
        INNER JOIN categories c
            ON a.categoryID = c.categoryID
        WHERE a.assetID = ?
        LIMIT 1
    ");

    $statement->bind_param("s", $assetID);
    $statement->execute();

    $asset = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$asset) {
        $databaseError =
            "The selected asset record could not be found.";
    }

    // Remove Asset
    if (
        $asset &&
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        if ($asset['listingStatus'] === 'Removed') {
            $removeError =
                "This asset listing has already been removed.";
        }

        if ($removeError === '') {
            try {
                $conn->begin_transaction();

                // Soft Remove Asset
                $removeStatement = $conn->prepare("
                    UPDATE assets
                    SET
                        listingStatus = 'Removed',
                        updatedDate = CURRENT_TIMESTAMP
                    WHERE assetID = ?
                ");

                $removeStatement->bind_param(
                    "s",
                    $assetID
                );

                $removeStatement->execute();
                $removeStatement->close();

                // Generate System Log ID
                $logResult = $conn->query("
                    SELECT MAX(
                        CAST(SUBSTRING(logID, 4) AS UNSIGNED)
                    ) AS maxID
                    FROM systemlog
                ");

                $logRow = $logResult->fetch_assoc();

                $nextLogNumber =
                    ((int) ($logRow['maxID'] ?? 0)) + 1;

                $logID = sprintf(
                    "LOG%03d",
                    $nextLogNumber
                );

                $logDescription =
                    "Removed asset listing: "
                    . $asset['assetName'];

                // Record Removal
                $logStatement = $conn->prepare("
                    INSERT INTO systemlog
                    (
                        logID,
                        userID,
                        actionType,
                        refType,
                        refID,
                        description
                    )
                    VALUES (
                        ?,
                        ?,
                        'Delete',
                        'Asset',
                        ?,
                        ?
                    )
                ");

                $logStatement->bind_param(
                    "ssss",
                    $logID,
                    $managerID,
                    $assetID,
                    $logDescription
                );

                $logStatement->execute();
                $logStatement->close();

                $conn->commit();

                header(
                    "Location: manager_assets.php?removed=1"
                );
                exit();

            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
        }
    }

} catch (Throwable $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to remove the asset. Please try again.";
}

if ($conn instanceof mysqli) {
    $conn->close();
}
?>