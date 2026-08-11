<?php
   // Generate System Log ID
    function generateLogID(mysqli $conn): string{
        $result = $conn->query("SELECT MAX(CAST(SUBSTRING(logID, 4)AS UNSIGNED)) AS maxIDFROM systemlog");

        $row = $result->fetch_assoc();

        $nextNumber = ((int) ($row['maxID'] ?? 0)) + 1;

        return sprintf("LOG%03d", $nextNumber);
    }


    // Create Delete System Log
    function createDeleteLog(mysqli $conn, string $userID, string $refType, string $refID, string $description): void {
        $logID = generateLogID($conn);

        $statement = $conn->prepare("INSERT INTO systemlog (logID, userID, actionType, refType, refID, description) VALUES (?, ?, 'Delete', ?, ?, ?)");

        $statement->bind_param("sssss", $logID, $userID, $refType, $refID, $description);

        $statement->execute();

        $statement->close();
    }
?>