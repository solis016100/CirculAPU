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

    // Connect to Database
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");

        // Get all verification records with user name
        $sql = "SELECT vr.verificationID, vr.userID, u.name AS userName, vr.targetID, vr.targetType, vr.status, vr.verifiedDate FROM verificationrecords vr
        INNER JOIN user u ON vr.userID = u.userID
        ORDER BY vr.verifiedDate DESC";

        $result = $conn->query($sql);

        if ($result){
            while ($row = $result->fetch_assoc()){
                $verificationRecords[] = $row;
            }
        }else {
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



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Verification Management Dashboard - CirculAPU</title>

    <link rel="stylesheet" href="style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">
        <div class="dashboard-container">

        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            
            <?php include 'header.php'; ?>

            <main class="page-body auditor-verification-page">


                // Page Heading
                <section class="verification-heading">
                    <h1>Verification Management Dashboard</h1>
                    <p>Manage all verification records on the platform</p>
                </section>


                // Database Error
                <?php if (!empty($databaseError)): ?>
                    <div class="auditor-error-message">
                        <?php echo htmlspecialchars($databaseError); ?>
                    </div>
                <?php endif; ?>


                // Search and Filter
                <section class="verification-filter-panel">
                    <div class="verification-filter-group verification-search-group">
                        <label for="verificationSearch">Search Verification Record</label>
                        <input type="text" id="verificationSearch" placeholder="Search by user, type, status...">
                    </div>

                    // Filter by User
                    <div class="verification-filter-group">
                        <label for="userFilter">User</label>
                        <select id="userFilter">
                            <option value="">All Users</option>
                        </select>
                    </div>

                    // Filter by Type of verification record
                    <div class="verification-filter-group">
                        <label for="typeFilter">Type</label>
                        <select id="typeFilter">
                            <option value="">All Type</option>
                            <option value="Asset">Asset</option>
                            <option value="MissionSubmission">Mission</option>
                        </select>
                    </div>

                    // Filter by Status
                    <div class="verification-filter-group">
                        <label for="statusFilter">Status</label>
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    // Filter buttons - Filter/Reset
                    <div class="verification-filter-buttons">
                        <button type="button" class="verification-filter-btn">Filter</button>
                        <button type="button" class="verification-reset-btn">Reset</button>
                    </div>
                </section>


                // Verification Record List
                <section class="verification-record-panel">
                    <div class="verification-record-header">
                        <h2>Verification Record List</h2>
                        <strong>
                            Total Records:
                            <?php echo count($verificationRecords); ?>
                        </strong>
                    </div>

                    <div class="verification-table-wrapper">

                        <table class="verification-table">

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

                                                <div class="verification-actions">

                                                    <button
                                                        type="button"
                                                        class="verification-action-btn"
                                                        title="View">

                                                        View

                                                    </button>


                                                    <button
                                                        type="button"
                                                        class="verification-action-btn"
                                                        title="Edit">

                                                        Edit

                                                    </button>


                                                    <button
                                                        type="button"
                                                        class="verification-action-btn"
                                                        title="Print">

                                                        Print

                                                    </button>


                                                    <button
                                                        type="button"
                                                        class="verification-action-btn delete"
                                                        title="Delete">

                                                        Delete

                                                    </button>

                                                </div>

                                            </td>

                                        </tr>

                                        <?php $number++; ?>

                                    <?php endforeach; ?>


                                <?php else: ?>

                                    <tr>

                                        <td colspan="7" class="verification-empty-state">
                                            No verification records found.
                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- Temporary Footer -->
                    <div class="verification-table-footer">

                        <span>
                            Showing
                            <?php echo count($verificationRecords); ?>
                            record(s)
                        </span>

                    </div>

                </section>
                </section>
            </main>
        </div>
    </div>
</body>
</html>