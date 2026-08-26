<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Kuala_Lumpur');

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This reservation maintenance job can only be run from PHP CLI.\n");
}

require_once __DIR__ . '/../db.php';

function nextID(mysqli $c, string $table, string $column, string $prefix): string {
    $start = strlen($prefix) + 1;
    $like = $prefix . '%';
    $s = $c->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING($column,$start) AS UNSIGNED)),0)+1 n FROM $table WHERE $column LIKE ?");
    $s->bind_param('s', $like);
    $s->execute();
    $n = (int)$s->get_result()->fetch_assoc()['n'];
    $s->close();
    return sprintf($prefix . '%03d', $n);
}

function managerForReservation(mysqli $c, string $transactionID): string {
    $s = $c->prepare("SELECT userID FROM systemlog
                      WHERE refType='Transaction' AND refID=? AND actionType='Approve'
                      ORDER BY timeLogged DESC LIMIT 1");
    $s->bind_param('s', $transactionID);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!empty($row['userID'])) return $row['userID'];

    $row = $c->query("SELECT userID FROM user WHERE role='Manager' AND status='Active' ORDER BY dateCreated ASC,userID ASC LIMIT 1")->fetch_assoc();
    return $row['userID'] ?? '';
}

function addNotification(mysqli $c, string $userID, string $title, string $content): void {
    $id = nextID($c, 'notification', 'notificationID', 'N');
    $s = $c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss', $id, $userID, $title, $content);
    $s->execute();
    $s->close();
}

function addLog(mysqli $c, string $userID, string $transactionID, string $description): void {
    $id = nextID($c, 'systemlog', 'logID', 'LOG');
    $s = $c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description)
                      VALUES (?,?,'Update','Transaction',?,?)");
    $s->bind_param('ssss', $id, $userID, $transactionID, $description);
    $s->execute();
    $s->close();
}

function addStatusHistory(mysqli $c, string $managerID, string $assetID, string $old, string $new, string $reason): void {
    if ($old === $new) return;
    $id = nextID($c, 'assetstatushistory', 'historyID', 'H');
    $s = $c->prepare("INSERT INTO assetstatushistory
                      (historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason)
                      VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss', $id, $assetID, $managerID, $old, $new, $reason);
    $s->execute();
    $s->close();
}

function addAttentionOnce(mysqli $c, string $managerID, string $transactionID, string $assetName, string $availability): bool {
    if ($managerID === '') return false;

    $marker = "%Transaction $transactionID:%";
    $s = $c->prepare("SELECT 1 FROM notification
                      WHERE userID=? AND title='Reservation Protection Attention' AND content LIKE ?
                      LIMIT 1");
    $s->bind_param('ss', $managerID, $marker);
    $s->execute();
    $exists = (bool)$s->get_result()->fetch_row();
    $s->close();
    if ($exists) return false;

    addNotification(
        $c,
        $managerID,
        'Reservation Protection Attention',
        "Transaction $transactionID: The 7-day protection window for $assetName has started, but the asset is currently $availability. Review the current borrowing/asset status before pickup."
    );
    return true;
}

function protectedReservation(mysqli $c, string $assetID): ?array {
    $s = $c->prepare("SELECT tr.transactionID,b.borrowDate,b.expectedReturnDate
                      FROM transactionrequests tr
                      JOIN borrowings b ON b.transactionID=tr.transactionID
                      WHERE tr.assetID=?
                        AND tr.requestStatus='Approved'
                        AND b.borrowingStatus='Approved'
                        AND b.actualReturnDate IS NULL
                        AND DATE_SUB(DATE(b.borrowDate),INTERVAL 7 DAY) <= CURDATE()
                        AND CURDATE() <= DATE(b.expectedReturnDate)
                      ORDER BY DATE(b.borrowDate) ASC,tr.transactionID ASC
                      LIMIT 1");
    $s->bind_param('s', $assetID);
    $s->execute();
    $row = $s->get_result()->fetch_assoc() ?: null;
    $s->close();
    return $row;
}

$c = $conn;
$c->set_charset('utf8mb4');

$lock = $c->query("SELECT GET_LOCK('circulapu_reservation_protection_sync',0) locked")->fetch_assoc();
if ((int)($lock['locked'] ?? 0) !== 1) {
    exit("Reservation maintenance sync is already running.\n");
}

$reservedCount = 0;
$expiredCount = 0;
$releasedCount = 0;
$attentionCount = 0;
$skippedCount = 0;

try {
    
    $expiredRows = $c->query("SELECT tr.transactionID,tr.assetID,tr.requesterID,tr.managerRemark,
                                    a.assetName,a.availabilityStatus,
                                    b.borrowID,b.borrowDate,b.expectedReturnDate,b.actualReturnDate,b.borrowingStatus
                             FROM transactionrequests tr
                             JOIN borrowings b ON b.transactionID=tr.transactionID
                             JOIN assets a ON a.assetID=tr.assetID
                             WHERE tr.requestStatus='Approved'
                               AND b.borrowingStatus='Approved'
                               AND b.actualReturnDate IS NULL
                               AND DATE(b.borrowDate) < CURDATE()
                             ORDER BY DATE(b.borrowDate) ASC,tr.transactionID ASC")
                     ->fetch_all(MYSQLI_ASSOC);

    foreach ($expiredRows as $r) {
        $managerID = managerForReservation($c, $r['transactionID']);
        if ($managerID === '') {
            fwrite(STDERR, "Skipped expiry {$r['transactionID']}: no Manager user could be resolved for audit records.\n");
            $skippedCount++;
            continue;
        }

        $c->begin_transaction();
        try {
            $s = $c->prepare("SELECT tr.requestStatus,tr.managerRemark,tr.requesterID,
                                     a.availabilityStatus,
                                     b.borrowingStatus,b.actualReturnDate,b.borrowDate,b.expectedReturnDate
                              FROM transactionrequests tr
                              JOIN borrowings b ON b.transactionID=tr.transactionID
                              JOIN assets a ON a.assetID=tr.assetID
                              WHERE tr.transactionID=? AND b.borrowID=? FOR UPDATE");
            $s->bind_param('ss', $r['transactionID'], $r['borrowID']);
            $s->execute();
            $fresh = $s->get_result()->fetch_assoc();
            $s->close();

            $today = date('Y-m-d');
            $pickupDate = !empty($fresh['borrowDate']) ? date('Y-m-d', strtotime($fresh['borrowDate'])) : '';
            $stillExpired = $fresh
                && $fresh['requestStatus'] === 'Approved'
                && $fresh['borrowingStatus'] === 'Approved'
                && empty($fresh['actualReturnDate'])
                && $pickupDate !== ''
                && $pickupDate < $today;

            if (!$stillExpired) {
                $c->rollback();
                $skippedCount++;
                continue;
            }

            $autoReason = 'System: Reservation cancelled automatically because the approved pickup date passed without pickup confirmation.';
            $remark = trim((string)($fresh['managerRemark'] ?? ''));
            $newRemark = substr(trim($remark . ($remark !== '' ? ' ' : '') . $autoReason), 0, 255);

            $s = $c->prepare("UPDATE transactionrequests SET requestStatus='Cancelled',managerRemark=? WHERE transactionID=?");
            $s->bind_param('ss', $newRemark, $r['transactionID']);
            $s->execute();
            $s->close();

            $s = $c->prepare("UPDATE borrowings SET borrowingStatus='Cancelled' WHERE borrowID=?");
            $s->bind_param('s', $r['borrowID']);
            $s->execute();
            $s->close();

            $availability = $fresh['availabilityStatus'];
            $nextProtected = null;

            if ($availability === 'Reserved') {
                $nextProtected = protectedReservation($c, $r['assetID']);
                if (!$nextProtected) {
                    $s = $c->prepare("UPDATE assets SET availabilityStatus='Available',updatedDate=NOW()
                                      WHERE assetID=? AND availabilityStatus='Reserved'");
                    $s->bind_param('s', $r['assetID']);
                    $s->execute();
                    $changed = $s->affected_rows === 1;
                    $s->close();

                    if ($changed) {
                        addStatusHistory(
                            $c,
                            $managerID,
                            $r['assetID'],
                            'Reserved',
                            'Available',
                            "Missed-pickup reservation {$r['transactionID']} released automatically"
                        );
                        $releasedCount++;
                    }
                }
            }

            $logText = "Approved pickup was missed for {$r['assetName']}; transaction automatically cancelled";
            if ($availability === 'Reserved' && $nextProtected) {
                $logText .= "; asset remains Reserved for protected reservation {$nextProtected['transactionID']}";
            } elseif ($availability === 'Reserved') {
                $logText .= '; asset released to Available';
            } else {
                $logText .= "; asset status left as $availability";
            }
            addLog($c, $managerID, $r['transactionID'], $logText);

            addNotification(
                $c,
                $fresh['requesterID'],
                'Reservation Cancelled',
                "Your approved reservation {$r['transactionID']} for {$r['assetName']} was automatically cancelled because pickup was not confirmed on " . date('d M Y', strtotime($fresh['borrowDate'])) . ". Please submit a new request if you still need the asset."
            );
            addNotification(
                $c,
                $managerID,
                'Reservation Auto-Cancelled',
                "Transaction {$r['transactionID']}: The approved pickup for {$r['assetName']} on " . date('d M Y', strtotime($fresh['borrowDate'])) . " passed without pickup confirmation. The reservation was automatically cancelled."
            );

            $c->commit();
            $expiredCount++;
            echo "Cancelled missed pickup {$r['transactionID']} for {$r['assetID']}.\n";
        } catch (Throwable $e) {
            try { $c->rollback(); } catch (Throwable $ignore) {}
            throw $e;
        }
    }

    
    $rows = $c->query("SELECT tr.transactionID,tr.assetID,a.assetName,a.availabilityStatus,
                              b.borrowID,b.borrowDate,b.expectedReturnDate,b.actualReturnDate,b.borrowingStatus
                       FROM transactionrequests tr
                       JOIN borrowings b ON b.transactionID=tr.transactionID
                       JOIN assets a ON a.assetID=tr.assetID
                       WHERE tr.requestStatus='Approved'
                         AND b.borrowingStatus='Approved'
                         AND b.actualReturnDate IS NULL
                         AND DATE_SUB(DATE(b.borrowDate),INTERVAL 7 DAY) <= CURDATE()
                         AND CURDATE() <= DATE(b.expectedReturnDate)
                       ORDER BY DATE(b.borrowDate) ASC,tr.transactionID ASC")
              ->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $r) {
        $managerID = managerForReservation($c, $r['transactionID']);
        if ($managerID === '') {
            fwrite(STDERR, "Skipped {$r['transactionID']}: no Manager user could be resolved for audit records.\n");
            $skippedCount++;
            continue;
        }

        if ($r['availabilityStatus'] !== 'Available') {
            if (in_array($r['availabilityStatus'], ['Borrowed','Unavailable'], true)) {
                $c->begin_transaction();
                try {
                    if (addAttentionOnce($c, $managerID, $r['transactionID'], $r['assetName'], $r['availabilityStatus'])) {
                        $attentionCount++;
                    }
                    $c->commit();
                } catch (Throwable $e) {
                    $c->rollback();
                    throw $e;
                }
            }
            $skippedCount++;
            continue;
        }

        $c->begin_transaction();
        try {
            $s = $c->prepare("SELECT a.availabilityStatus,tr.requestStatus,b.borrowingStatus,b.actualReturnDate,b.borrowDate,b.expectedReturnDate
                              FROM transactionrequests tr
                              JOIN borrowings b ON b.transactionID=tr.transactionID
                              JOIN assets a ON a.assetID=tr.assetID
                              WHERE tr.transactionID=? AND b.borrowID=? FOR UPDATE");
            $s->bind_param('ss', $r['transactionID'], $r['borrowID']);
            $s->execute();
            $fresh = $s->get_result()->fetch_assoc();
            $s->close();

            $today = date('Y-m-d');
            $protectedFrom = !empty($fresh['borrowDate']) ? date('Y-m-d', strtotime($fresh['borrowDate'] . ' -7 days')) : '';
            $expectedReturn = !empty($fresh['expectedReturnDate']) ? date('Y-m-d', strtotime($fresh['expectedReturnDate'])) : '';
            $stillEligible = $fresh
                && $fresh['requestStatus'] === 'Approved'
                && $fresh['borrowingStatus'] === 'Approved'
                && empty($fresh['actualReturnDate'])
                && $fresh['availabilityStatus'] === 'Available'
                && $protectedFrom !== ''
                && $expectedReturn !== ''
                && $today >= $protectedFrom
                && $today <= $expectedReturn;

            if (!$stillEligible) {
                $c->rollback();
                $skippedCount++;
                continue;
            }

            $s = $c->prepare("UPDATE assets SET availabilityStatus='Reserved',updatedDate=NOW()
                              WHERE assetID=? AND availabilityStatus='Available'");
            $s->bind_param('s', $r['assetID']);
            $s->execute();
            $changed = $s->affected_rows === 1;
            $s->close();

            if (!$changed) {
                $c->rollback();
                $skippedCount++;
                continue;
            }

            addStatusHistory(
                $c,
                $managerID,
                $r['assetID'],
                'Available',
                'Reserved',
                "Automatic 7-day reservation protection activated for {$r['transactionID']}"
            );
            addLog($c, $managerID, $r['transactionID'], "Automatic 7-day reservation protection activated for {$r['assetName']}");

            $c->commit();
            $reservedCount++;
            echo "Reserved {$r['assetID']} for {$r['transactionID']} (pickup " . date('Y-m-d', strtotime($r['borrowDate'])) . ").\n";
        } catch (Throwable $e) {
            try { $c->rollback(); } catch (Throwable $ignore) {}
            throw $e;
        }
    }

    echo "Done. Reserved: $reservedCount; missed-pickup cancellations: $expiredCount; released: $releasedCount; attention notifications: $attentionCount; skipped/no change: $skippedCount.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Reservation maintenance sync failed: {$e->getMessage()}\n");
    exit(1);
} finally {
    try { $c->query("SELECT RELEASE_LOCK('circulapu_reservation_protection_sync')"); } catch (Throwable $ignore) {}
}
