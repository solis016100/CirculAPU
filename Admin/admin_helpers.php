<?php




function adminNextIdentifier(mysqli $conn, string $kind): string
{
    $definitions = [
        'log' => ['systemlog', 'logID', 'LOG', 4],
        'ecopoint' => ['ecopointstransaction', 'transactionID', 'ET', 3],
        'user' => ['user', 'userID', 'U', 2],
        'tier' => ['tiers', 'tierID', 'T', 2],
    ];

    if (!isset($definitions[$kind])) {
        throw new InvalidArgumentException('Unsupported identifier type.');
    }

    [$table, $column, $prefix, $substring_start] = $definitions[$kind];
    $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING($column, $substring_start) AS UNSIGNED)), 0) + 1 AS nextNumber FROM $table";
    $next = (int)$conn->query($sql)->fetch_assoc()['nextNumber'];

    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function adminWriteSystemLog(
    mysqli $conn,
    string $admin_id,
    string $action_type,
    string $reference_type,
    ?string $reference_id,
    string $description
): void {
    $log_id = adminNextIdentifier($conn, 'log');
    $stmt = $conn->prepare(
        'INSERT INTO systemlog (logID, userID, actionType, refType, refID, description)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssss', $log_id, $admin_id, $action_type, $reference_type, $reference_id, $description);
    $stmt->execute();
    $stmt->close();
}

function adminWriteEcoPointTransaction(
    mysqli $conn,
    string $user_id,
    int $points,
    string $admin_id
): void {
    if ($points === 0) {
        return;
    }

    $transaction_id = adminNextIdentifier($conn, 'ecopoint');
    $transaction_type = $points > 0 ? 'Earned' : 'Deducted';
    $source_type = 'AdminAdjustment';
    $stmt = $conn->prepare(
        'INSERT INTO ecopointstransaction
         (transactionID, userID, points, transactionType, sourceType, referenceID)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'ssisss',
        $transaction_id,
        $user_id,
        $points,
        $transaction_type,
        $source_type,
        $admin_id
    );
    $stmt->execute();
    $stmt->close();
}

function adminCsrfToken(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function adminVerifyCsrfToken(string $token): bool
{
    return isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);
}
