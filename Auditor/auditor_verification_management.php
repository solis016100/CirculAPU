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

    // Connect to Database
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");

        // Get all verification records with user name
        $sql = "SELECT vr.verificationID, vr.userID, u.name AS userName, vr.targetID, vr.targetType, vr.status, vr.verifiedDate
        FROM verificationrecords vr
        INNER JOIN user u ON vr.userID = u.userID WHERE 1=1";


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

        $sql .= " ORDER BY vr.verifiedDate DESC";

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
                            <?php echo count($verificationRecords); ?>
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
                                        <tr>
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
                                                    <button
                                                        type="button"
                                                        class="management-action-btn"
                                                        title="View">
                                                        <img src="../assets/icons/view.png" alt="View">
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="management-action-btn"
                                                        title="Edit">
                                                        <img src="../assets/icons/edit.png" alt="Edit">
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="management-action-btn"
                                                        title="Print">
                                                        <img src="../assets/icons/print.png" alt="Print">
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="management-action-btn delete"
                                                        title="Delete">
                                                        <img src="../assets/icons/delete.png" alt="Delete">
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <?php $number++; ?>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="7" class="management-empty-state">
                                            No verification records found.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                    <!-- Temporary Footer for verification table -->
                    <div class="management-table-footer">
                        <span>
                            Showing
                            <?php echo count($verificationRecords); ?>
                            record(s)
                        </span>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>