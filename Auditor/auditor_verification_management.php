<?php
    session_start();

    // Access Control Guard for Sustainability Auditor Role
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit();
    }

    // Get Auditor's information from login
    $auditorID= $_SESSION['user_id'];
    $auditorName = $_SESSION['user_name'] ?? 'Auditor';

    // Default Values
    $verificationRecords = [];
    $databaseError = '';

    // Filter Values
    $search = trim($_GET['search'] ?? '');
    $userFilter = $_GET['user'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $statusFilter = $_GET['status'] ?? '';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $recordsPerPage = 8;

    $filter = http_build_query(['search' => $search, 'user' => $userFilter, 'type' => $typeFilter, 'status' => $statusFilter]);

    // Connect to Database
    require_once '../db.php';
    require_once '../delete.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteID'])) {
        $id = $_POST['deleteID'];

        $q = $conn->prepare("
            DELETE FROM verificationrecords
            WHERE verificationID = ?
        ");

        $q->bind_param("s", $id);
        $q->execute();

        createDeleteLog(
            $conn,
            $_SESSION['user_id'],
            'Verification',
            $id,
            "Deleted verification record: $id"
        );

        header("Location: auditor_verification_management.php?deleted=1");
        exit();
    }

    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");

        // Get all verification records with user name
        $sql = "SELECT vr.verificationID, vr.userID, u.name AS userName, vr.targetID, vr.targetType, vr.status, vr.verifiedDate, a.assetCondition
        FROM verificationrecords vr
        INNER JOIN user u ON vr.userID = u.userID
        LEFT JOIN assets a
        ON vr.targetType = 'Asset'
        AND vr.targetID = a.assetID
        WHERE 1=1";


        // Search (filter)
        if ($search !== '') {
            $safeSearch = $conn->real_escape_string($search);
            $sql .= " AND (vr.verificationID LIKE '%$safeSearch%'
                    OR u.name LIKE '%$safeSearch%'
                    OR vr.targetType LIKE '%$safeSearch%'
                    OR vr.status LIKE '%$safeSearch%')";
        }


        // Filter by User
        if ($userFilter !== '') {
            $safeUser = $conn->real_escape_string($userFilter);
            $sql .= " AND vr.userID = '$safeUser'";
        }

        $users = [];

        $userSql = "SELECT DISTINCT u.userID, u.name FROM verificationrecords vr
                    INNER JOIN user u ON vr.userID = u.userID
                    ORDER BY u.name ASC";

        $userResult = $conn->query($userSql);

        if ($userResult) {
            while ($user = $userResult->fetch_assoc()) {
                $users[] = $user;
            }
        }


        // Filter by Type
        if ($typeFilter !== '') {
            $safeType = $conn->real_escape_string($typeFilter);
            $sql .= " AND vr.targetType = '$safeType'";
        }


        // Filter by Status
        if ($statusFilter !== '') {
            $safeStatus = $conn->real_escape_string($statusFilter);
            $sql .= " AND vr.status = '$safeStatus'";
        }

        $sql .= " ORDER BY vr.verificationID DESC";

        $countSql = "SELECT COUNT(*) AS total
             FROM verificationrecords vr
             INNER JOIN user u ON vr.userID = u.userID
             WHERE 1=1";

        if ($search !== '') {
            $safeSearch = $conn->real_escape_string($search);
            $countSql .= " AND (
                vr.verificationID LIKE '%$safeSearch%'
                OR u.name LIKE '%$safeSearch%'
                OR vr.targetType LIKE '%$safeSearch%'
                OR vr.status LIKE '%$safeSearch%'
            )";
        }

        if ($userFilter !== '') {
            $safeUser = $conn->real_escape_string($userFilter);
            $countSql .= " AND vr.userID = '$safeUser'";
        }

        if ($typeFilter !== '') {
            $safeType = $conn->real_escape_string($typeFilter);
            $countSql .= " AND vr.targetType = '$safeType'";
        }

        if ($statusFilter !== '') {
            $safeStatus = $conn->real_escape_string($statusFilter);
            $countSql .= " AND vr.status = '$safeStatus'";
        }

        $totalResult = $conn->query($countSql);
        $totalRecords = $totalResult->fetch_assoc()['total'];

        $totalPages = max(1, ceil($totalRecords / $recordsPerPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $recordsPerPage;

        $sql .= " LIMIT $offset, $recordsPerPage";

        $result = $conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $verificationRecords[] = $row;
            }
        } else {
            $databaseError = "Unable to retrieve verification records.";
        }

        $conn->close();
    }

    // Format verification date
    function formatVerificationDate(?string $date): string{
        if (empty($date)){
            return '-';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false){
            return '-';
        }

        return date('d M Y', $timestamp);
    }
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Verification Management Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="page-body auditor-verification-page">


                <!-- Page Heading -->
                <section class="management-heading">
                    <h1>Verification Management Dashboard</h1>
                    <p>Manage all verification records on the platform</p>
                </section>


                <!-- Database Error -->
                <?php if (!empty($databaseError)): ?>
                    <div class="auditor-error-message">
                        <?php echo htmlspecialchars($databaseError); ?>
                    </div>
                <?php endif; ?>

                <!-- Green bar showing Success Deletion -->
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">
                        Verification record deleted successfully.
                    </div>
                <?php endif; ?>

                <!-- Green Bar showing Success Updation -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        Verification updated successfully.
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <form class="management-filter" method="get" action="auditor_verification_management.php">
                    <div class="management-filter-group search">
                        <label for="verificationSearch">Search Verification Record</label>
                        <input type="text" id="verificationSearch" name="search" value="<?php echo htmlspecialchars($search); ?>"placeholder="Search by verifiction id, user, type, status...">
                    </div>

                    <!-- Filter by User -->
                    <div class="management-filter-group">
                        <label for="userFilter">User</label>
                        <select id="userFilter" name="user">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['userID']); ?>"
                                    <?php echo ($userFilter === $user['userID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter by Type of verification record -->
                    <div class="management-filter-group">
                        <label for="typeFilter">Type</label>
                        <select id="typeFilter" name="type">
                            <option value="">
                                All Type
                            </option>

                            <option value="Asset" <?php echo ($typeFilter === 'Asset') ? 'selected' : ''; ?>>
                                Asset 
                            </option>

                            <option value="MissionSubmission" <?php echo ($typeFilter === 'MissionSubmission') ? 'selected' : ''; ?>>
                                Mission
                            </option>
                        </select>
                    </div>

                    <!-- Filter by Status -->
                    <div class="management-filter-group">
                        <label for="statusFilter">Status</label>
                        <select id="statusFilter" name="status">
                            <option value="">
                                All Status
                            </option>

                            <option value="Pending" <?php echo ($statusFilter === 'Pending') ?'selected' : ''; ?>>
                                Pending
                            </option>

                            <option value="Approved" <?php echo ($statusFilter === 'Approved') ? 'selected' : ''; ?>>
                                Approved
                            </option>

                            <option value="Rejected" <?php echo ($statusFilter === 'Rejected') ? 'selected' : ''; ?>>
                                Rejected
                            </option>
                        </select>
                    </div>

                    <!-- Filter buttons - Filter/Reset -->
                    <div class="management-filter-buttons">
                        <button type="submit" class="management-filter-btn">
                            <img src="../assets/icons/filter.png" alt="Filter">
                            Filter
                        </button>
                        <a href="auditor_verification_management.php" class="management-reset-btn">
                            <img src="../assets/icons/reset.png" alt="Reset">
                            Reset
                        </a>
                    </div>
                </form>


                <!-- Verification Record List -->
                <section class="management-list">
                    <div class="management-list-header">
                        <h2>Verification Record List</h2>
                        <strong>
                            Total Records:
                            <?php echo $totalRecords; ?>
                        </strong>
                    </div>

                    <div class="management-table-wrapper">
                        <table class="management-table verification-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Verification ID</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Condition</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (!empty($verificationRecords)): ?>
                                    <?php
                                        $number = 1;
                                        foreach ($verificationRecords as $record):
                                    ?>
                                        <tr ondblclick="window.location.href='auditor_verification_view.php?verificationID=<?= urlencode($record['verificationID']) ?>&from=management&<?= $filter ?>'">
                                            <td>
                                                <?php echo $number; ?>
                                            </td>

                                            <td>
                                                <?php
                                                    echo htmlspecialchars(
                                                        $record['verificationID']
                                                    );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                    echo htmlspecialchars(
                                                        $record['userName']
                                                    );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                    if ($record['targetType'] === 'MissionSubmission') {
                                                        echo 'Mission';
                                                    } else {
                                                        echo htmlspecialchars(
                                                            $record['targetType']
                                                        );
                                                    }
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                    if ($record['targetType'] === 'Asset') {
                                                        echo htmlspecialchars($record['assetCondition'] ?? '-');
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>

                                            <td>
                                                <span class="verification-status 
                                                    status-<?php echo strtolower(
                                                        $record['status']
                                                    ); ?>">

                                                    <?php
                                                        echo htmlspecialchars(
                                                            $record['status']
                                                        );
                                                    ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php
                                                    echo formatVerificationDate(
                                                        $record['verifiedDate']
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <div class="management-actions">
                                                    <a href="auditor_verification_view.php?verificationID=<?= urlencode($record['verificationID']) ?>&from=management&<?= $filter ?>" class="management-action-btn" title="View">
                                                        <img src="../assets/icons/view.png" alt="View">
                                                    </a>

                                                    <a href="auditor_verification_edit.php?verificationID=<?= urlencode($record['verificationID']) ?>&from=management" class="management-action-btn" title="Edit">
                                                        <img src="../assets/icons/edit.png" alt="Edit">
                                                    </a>

                                                    <a href="auditor_verification_view.php?verificationID=<?= urlencode($record['verificationID']) ?>&from=management&print=1&<?= $filter ?>" class="management-action-btn" title="Print">
                                                        <img src="../assets/icons/print.png" alt="Print">
                                                    </a>

                                                    <button type="button" class="management-action-btn" title="Delete" onclick="openDeletePopout('<?= htmlspecialchars($record['verificationID']) ?>')">
                                                        <img src="../assets/icons/delete.png" alt="Delete">
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <?php $number++; ?>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="8" class="management-empty-state">
                                            No verification records found.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                    <!-- Temporary Footer for verification table -->
                    <div class="management-table-footer">
                        <div class="showing-records">
                            <?php if ($totalRecords > 0) {
                                $start = $offset + 1; $end = min($offset + $recordsPerPage, $totalRecords); echo "Showing $start to $end of $totalRecords entries";
                                }else {
                                    echo "Showing 0 to 0 of 0 entries";
                                }
                            ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(['search' => $search, 'user' => $userFilter, 'type' => $typeFilter, 'status' => $statusFilter, 'page' => $page - 1]) ?>">
                                        &laquo;
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?<?= http_build_query(['search' => $search, 'user' => $userFilter, 'type' => $typeFilter, 'status' => $statusFilter, 'page' => $i]) ?>" class="<?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?= http_build_query(['search' => $search, 'user' => $userFilter, 'type' => $typeFilter, 'status' => $statusFilter, 'page' => $page + 1]) ?>">
                                        &raquo;
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        function openDeletePopout(id) {
            document.getElementById('deleteID').value = id;
            document.getElementById('deletePopout').style.display = 'flex';
        }

        function closeDeletePopout() {
            document.getElementById('deletePopout').style.display = 'none';
        }
    </script>


    <div id="deletePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Delete Verification Confirmation</h2>
            <p>Are you sure you want to delete this verification record?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST" action="auditor_verification_management.php">
                    <input type="hidden" name="deleteID" id="deleteID">

                    <button type="submit" class="auditor-popout-confirm">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
