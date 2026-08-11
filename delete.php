<?php

function generateLogID(mysqli $conn): string {
    $row = $conn->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)), 0) + 1 AS nextID
        FROM systemlog
    ")->fetch_assoc();

    return sprintf('LOG%03d', (int)$row['nextID']);
}

function createDeleteLog(
    mysqli $conn,
    string $userID,
    string $refType,
    string $refID,
    string $description
): void {
    $logID = generateLogID($conn);

    $stmt = $conn->prepare("
        INSERT INTO systemlog
            (logID, userID, actionType, refType, refID, description)
        VALUES (?, ?, 'Delete', ?, ?, ?)
    ");

    $stmt->bind_param('sssss', $logID, $userID, $refType, $refID, $description);
    $stmt->execute();
    $stmt->close();
}