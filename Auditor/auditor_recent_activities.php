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
        $activityRecords = [];
        $databaseError = '';

        // Filter Values
        $search = trim($_GET['search'] ?? '');
        $refTypeFilter = $_GET['refType'] ?? '';
        $actionTypeFilter = $_GET['actionType'] ?? '';

        // Connect to Database
        $conn = new mysqli("localhost", "root", "", "circulapu_db");
        if ($conn->connect_error){
            $databaseError = "Unable to connect to the database.";
        }else {
            // (UTF-8) allows the database to handle different languages
            $conn->set_charset("utf8mb4");

            // Get all system activity logs with the user who performed them
            $sql = "SELECT sl.logID, sl.description, sl.actionType, sl.refType, sl.refID, sl.timeLogged, u.name AS performedBy
            FROM systemlog sl
            INNER JOIN user u ON sl.userID = u.userID
            WHERE sl.refType IN('Mission', 'Asset', 'Reward', 'Badge', 'Tier', 'Verification')";
    
            // Search (filter)
            if ($search !== '') {
                $safeSearch = $conn->real_escape_string($search);
                $sql .= " AND (sl.logID LIKE '%$safeSearch%'
                        OR sl.description LIKE '%$safeSearch%'
                        OR u.name LIKE '%$safeSearch%'
                        OR sl.refType LIKE '%$safeSearch%'
                        OR sl.actionType LIKE '%$safeSearch%')";
            }
    
            // Filter by Related To (refType)
            if ($refTypeFilter !== '') {
                $safeRefType = $conn->real_escape_string($refTypeFilter);
                $sql .= " AND sl.refType = '$safeRefType'";
            }
    
            // Filter by Action Type
            if ($actionTypeFilter !== '') {
                $safeActionType = $conn->real_escape_string($actionTypeFilter);
                $sql .= " AND sl.actionType = '$safeActionType'";
            }
    
            $sql .= " ORDER BY sl.timeLogged DESC";
    
            $result = $conn->query($sql);
    
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $activityRecords[] = $row;
                }
            } else {
                $databaseError = "Unable to retrieve activity records.";
            }
    
            $conn->close();
        }

        // Get Action Type function
        function getActionClass(string $actionType): string {
            switch ($actionType) {
                case 'Approve':
                    return 'action-approved';
                case 'Reject':
                    return 'action-rejected';
                case 'Submit':
                    return 'action-submitted';
                case 'Create':
                    return 'action-created';
                case 'Update':
                    return 'action-updated';
                case 'Delete':
                    return 'action-deleted';
                case 'Redeem':
                    return 'action-redeemed';
                case 'Assign':
                    return 'action-assigned';
                default:
                    return 'action-default';
            }
        }

        // Date Display function
        function formatActivityDate(?string $date): string {
            if (empty($date)) {
                return '-';
            }
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return '-';
            }
            return date('d M Y, h:i A', $timestamp);
        }

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Activity Log - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">

    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="page-body auditor-verification-page">

                <!-- Breadcrumb -->
                <nav class="breadcrumb">
                    <a href="auditor_dashboard.php">Sustainabiliy Auditor Dashboard Management</a>
                    &gt;
                    <span class="breadcrumb-current">Recent Activities</span>
                </nav>
                
                <!-- Page Heading -->
                <section class="verification-heading">
                    <h1>System Activity Log</h1>
                    <p>View all recorded sustainability system activities</p>
                </section>

                <!-- Database Error -->
                <?php if (!empty($databaseError)): ?>
                    <div class="auditor-error-message">
                        <?php echo htmlspecialchars($databaseError); ?>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <form class="verification-filter-panel" method="get" action="auditor_activity_log.php">
                    <div class="verification-filter-group verification-search-group">
                        <label for="activitySearch">Search Activity Log</label>
                        <input type="text" id="activitySearch" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by log id, description, user, type...">
                    </div>
 
                    <!-- Filter by Related To -->
                    <div class="verification-filter-group">
                        <label for="refTypeFilter">Related To</label>
                        <select id="refTypeFilter" name="refType">
                            <option value="">All Types</option>
                            <?php
                                $refTypes = ['Mission', 'Asset', 'Reward', 'Badge', 'Tier', 'Verification'];
                                foreach ($refTypes as $type):
                            ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($refTypeFilter === $type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
 
                    <!-- Filter by Action Type -->
                    <div class="verification-filter-group">
                        <label for="actionTypeFilter">Action</label>
                        <select id="actionTypeFilter" name="actionType">
                            <option value="">All Actions</option>
                            <?php
                                $actionTypes = ['Create', 'Submit', 'Approve', 'Reject', 'Redeem', 'Assign', 'Update', 'Delete'];
                                foreach ($actionTypes as $action):
                            ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo ($actionTypeFilter === $action) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($action); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
 
                    <!-- Filter buttons - Filter/Reset -->
                    <div class="verification-filter-buttons">
                        <button type="submit" class="verification-filter-btn">
                            <img src="assets/icons/filter.png" alt="Filter">
                            Filter
                        </button>
                        <a href="auditor_activity_log.php" class="verification-reset-btn">
                            <img src="assets/icons/reset.png" alt="Reset">
                            Reset
                        </a>
                    </div>
                </form>
 
                <!-- Activity Log List -->
                <section class="verification-record-panel">
                    <div class="verification-record-header">
                        <h2>Activity Log</h2>
                        <strong>
                            Total Records:
                            <?php echo count($activityRecords); ?>
                        </strong>
                    </div>
 
                    <div class="verification-table-wrapper">
                        <table class="verification-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Log ID</th>
                                    <th>Activity</th>
                                    <th>Description</th>
                                    <th>Related To</th>
                                    <th>Performed By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
 
                            <tbody>
                                <?php if (!empty($activityRecords)): ?>
                                    <?php
                                        $number = 1;
                                        foreach ($activityRecords as $activity):
                                    ?>
                                        <tr>
                                            <td><?php echo $number; ?></td>
 
                                            <td><?php echo htmlspecialchars($activity['logID']); ?></td>
 
                                            <td>
                                                <span class="auditor-action-badge <?php echo htmlspecialchars(getActionClass($activity['actionType'])); ?>">
                                                    <?php echo htmlspecialchars($activity['actionType']); ?>
                                                </span>
                                            </td>
 
                                            <td class="activity-description">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </td>
 
                                            <td>
                                                <div class="auditor-reference">
                                                    <strong><?php echo htmlspecialchars($activity['refType']); ?></strong>
                                                    <?php if (!empty($activity['refID'])): ?>
                                                        <span><?php echo htmlspecialchars($activity['refID']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
 
                                            <td><?php echo htmlspecialchars($activity['performedBy']); ?></td>
 
                                            <td><?php echo htmlspecialchars(formatActivityDate($activity['timeLogged'])); ?></td>
                                        </tr>
                                        <?php $number++; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="verification-empty-state">
                                            No activity records found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
 
                    <!-- Footer for activity table -->
                    <div class="verification-table-footer">
                        <span>
                            Showing
                            <?php echo count($activityRecords); ?>
                            record(s)
                        </span>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>