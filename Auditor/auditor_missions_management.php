<?php
    session_start();

    // Access Control Guard for Sustainability Auditor Role
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit();
    }

    require '../db.php';
    require_once '../delete.php';
    require_once '../print.php';

    // Get Auditor's information from login
    $auditorID= $_SESSION['user_id'];
    $auditorName = $_SESSION['user_name'] ?? 'Auditor';

    // Connect to Database
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");
    }

    $missionID = $_GET['missionID'] ?? '';

    /* Delete Mission */
    if (isset($_POST['deleteID'])) {

        $id = $_POST['deleteID'];

        $stmt = $conn->prepare("SELECT missionName FROM missions WHERE missionID = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $mission = $stmt->get_result()->fetch_assoc();

        if ($mission) {

            $stmt = $conn->prepare("DELETE FROM missions WHERE missionID = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();

            $r = $conn->query("
                SELECT COALESCE(MAX(CAST(SUBSTRING(logID,4) AS UNSIGNED)),0)+1 n
                FROM systemlog
            ");

            $logID = 'LOG' . str_pad(
                $r->fetch_assoc()['n'],
                3,
                '0',
                STR_PAD_LEFT
            );

            $description = "Deleted mission: " . $mission['missionName'];

            $log = $conn->prepare("
                INSERT INTO systemlog
                (logID,userID,actionType,refType,refID,description)
                VALUES (?,?,'Delete','Mission',?,?)
            ");

            $log->bind_param(
                "ssss",
                $logID,
                $_SESSION['user_id'],
                $id,
                $description
            );

            $log->execute();

            header("Location: auditor_missions_management.php?deleted=1");
            exit();
        }
    }

    // Get filter values from URL
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $missionName = isset($_GET['missionName']) ? trim($_GET['missionName']) : '';
    $ecoPoints = isset($_GET['ecoPoints']) ? trim($_GET['ecoPoints']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    $filterQuery = http_build_query(['search' => $search, 'missionName' => $missionName, 'ecoPoints' => $ecoPoints, 'status' => $status]);

    // Store filter conditions
    $conditions = [];

    // Search Mission
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(missionName LIKE '%$safeSearch%'OR ecoPointsReward LIKE '%$safeSearch%')";
    }

    // Filter by Mission Name
    if ($missionName !== '') {
        $safeMissionName = mysqli_real_escape_string($conn, $missionName);
        $conditions[] = "missionName = '$safeMissionName'";
    }

    // Filter by EcoPoints
    if ($ecoPoints !== '') {
        $safeEcoPoints = (int) $ecoPoints;
        $conditions[] = "ecoPointsReward = $safeEcoPoints";
    }

    // Filter by Status
    if ($status !== '') {
        $safeStatus = mysqli_real_escape_string($conn, $status);
        $conditions[] = "status = '$safeStatus'";
    }

    // Combine all conditions
    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }


    // Number of missions displayed per page
    $recordsPerPage = 8;

    // Get current page number
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

    // Prevent invalid page number
    if ($page < 1) {
        $page = 1;
    }

    // Count total missions
    $countQuery = "SELECT COUNT(*) AS total FROM missions $whereClause";
    $countResult = mysqli_query($conn, $countQuery);
    $countRow = mysqli_fetch_assoc($countResult);

    $totalMissions = $countRow['total'];

    // Calculate total pages
    // ceil = round to nearest whole number
    $totalPages = ceil($totalMissions / $recordsPerPage);

    // Calculate starting record
    $offset = ($page - 1) * $recordsPerPage;

    // Retrieve missions for current page
    $query = "SELECT missionID, missionName, ecoPointsReward, status
          FROM missions
          $whereClause
          ORDER BY missionID ASC
          LIMIT $recordsPerPage OFFSET $offset";

    $result = mysqli_query($conn, $query);


    // Get Mission Names for dropdown from database
    $missionNameQuery = "SELECT DISTINCT missionName FROM missions ORDER BY missionName ASC";
    $missionNameResult = mysqli_query($conn, $missionNameQuery);

    // Get EcoPoints values for dropdown from database
    $ecoPointsQuery = "SELECT DISTINCT ecoPointsReward FROM missions ORDER BY ecoPointsReward ASC";
    $ecoPointsResult = mysqli_query($conn, $ecoPointsQuery);

    // Get Status values for dropdown from database
    $statusQuery = "SELECT DISTINCT status FROM missions ORDER BY status ASC";
    $statusResult = mysqli_query($conn, $statusQuery);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Mission Management Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

                <main class="page-body auditor-mission-page">

                    <div class="management-heading management-heading-action">
                        <div class="management-heading-text">
                            <h1>Mission Management</h1>
                            <p>Manage all Missions on the platform</p>
                        </div>

                        <a href="auditor_mission_create.php" class="management-create-btn">
                            <img src="../assets/icons/create.png" alt="Create">
                            Create New Mission
                        </a>
                    </div>

                    <!-- Green bar showing Success Creation -->
                    <?php if (isset($_GET['created'])): ?>
                        <div class="alert alert-success">
                            Mission created successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Green bar showing Success Deletion -->
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success">
                            Mission deleted successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filter -->
                    <div class="management-filter">
                        <form method="GET" action="auditor_missions_management.php">

                            <!-- Search Mission -->
                            <div class="management-filter-group search">
                                <label for="search">Search Mission</label>
                                <div class="management-search-input">
                                    <img src="../assets/icons/search.png" alt="Search">
                                    <input
                                        type="text"
                                        id="search"
                                        name="search"
                                        placeholder="Search by mission name, ecopoints..."
                                        value="<?php echo htmlspecialchars($search); ?>"
                                    >
                                </div>
                            </div>


                            <!-- Filter by Mission Name -->
                            <div class="management-filter-group">
                                <label for="missionName">Mission Name</label>
                                <select id="missionName" name="missionName">
                                    <option value="">All Mission</option>
                                    <?php while ($row = mysqli_fetch_assoc($missionNameResult)) { ?>
                                        <option
                                            value="<?php echo htmlspecialchars($row['missionName']); ?>"
                                            <?php
                                                if ($missionName === $row['missionName']) {
                                                    echo 'selected';
                                                }
                                            ?>
                                        >
                                            <?php echo htmlspecialchars($row['missionName']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>


                            <!-- Filter by EcoPoints -->
                            <div class="management-filter-group">
                                <label for="ecoPoints">EcoPoints Awarded</label>
                                <select id="ecoPoints" name="ecoPoints">
                                    <option value="">All EcoPoints</option>
                                    <?php while ($row = mysqli_fetch_assoc($ecoPointsResult)) { ?>
                                        <option
                                            value="<?php echo $row['ecoPointsReward']; ?>"
                                            <?php
                                                if ($ecoPoints == $row['ecoPointsReward']) {
                                                    echo 'selected';
                                                }
                                            ?>
                                        >
                                            <?php echo $row['ecoPointsReward']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>


                            <!-- Filter by Status -->
                            <div class="management-filter-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All Status</option>
                                    <?php while ($row = mysqli_fetch_assoc($statusResult)) { ?>
                                        <option
                                            value="<?php echo htmlspecialchars($row['status']); ?>"
                                            <?php
                                                if ($status === $row['status']) {
                                                    echo 'selected';
                                                }
                                            ?>
                                        >
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>


                            <!-- Filter Buttons -->
                            <div class="management-filter-buttons">
                                <button type="submit" class="management-filter-btn">
                                    <img src="../assets/icons/filter.png" alt="Filter">
                                    Filter
                                </button>

                                <a href="auditor_missions_management.php" class="management-reset-btn">
                                    <img src="../assets/icons/reset.png" alt="Reset">
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Mission Table Header -->
                    <div class="management-list">
                        <div class="management-list-header">
                            <h2>Mission List</h2>
                            <p>Total Mission: <?php echo $totalMissions; ?></p>
                        </div>

                        <div class="management-table-wrapper">
                            <table class="management-table mission-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Mission ID</th>
                                        <th>Mission Name</th>
                                        <th>EcoPoints Awarded</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php $number = $offset + 1; while ($mission = mysqli_fetch_assoc($result)): ?>
                                    <tr ondblclick="window.location.href='auditor_mission_view.php?missionID=<?= urlencode($mission['missionID']) ?>&from=management&<?= $filterQuery ?>'">
                                        <td><?= $number ?></td>
                                        <td><?= htmlspecialchars($mission['missionID']) ?></td>
                                        <td><?= htmlspecialchars($mission['missionName']) ?></td>
                                        <td><?= htmlspecialchars($mission['ecoPointsReward']) ?></td>
                                        <td>
                                            <span class="mission-status <?= strtolower($mission['status']) ?>">
                                                <?= htmlspecialchars($mission['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="management-actions">
                                                <a href="auditor_mission_view.php?missionID=<?= urlencode($mission['missionID']) ?>&from=management&<?= $filterQuery ?>" class="management-action-btn">
                                                    <img src="../assets/icons/view.png" alt="View">
                                                </a>

                                                <a href="auditor_mission_edit.php?missionID=<?= urlencode($mission['missionID']) ?>&from=management&<?= $filterQuery ?>" class="management-action-btn" title="Edit">
                                                    <img src="../assets/icons/edit.png" alt="Edit">
                                                </a>

                                                <a href="auditor_mission_view.php?missionID=<?= urlencode($mission['missionID']) ?>&from=management&print=1&<?= $filterQuery ?>" class="management-action-btn" title="Print">
                                                    <img src="../assets/icons/print.png" alt="Print">
                                                </a>

                                                <button type="button" class="management-action-btn" title="Delete" onclick="openDeletePopout('<?= htmlspecialchars($mission['missionID']) ?>')">
                                                    <img src="../assets/icons/delete.png" alt="Delete">
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $number++; endwhile; ?>
                                </tbody>
                            </table>

                            <!-- Automatic Page Buttons -->
                            <?php
                                if ($totalMissions > 0) {
                                    $startRecord = $offset + 1;
                                    $endRecord = min($offset + $recordsPerPage, $totalMissions);
                                } else {
                                    $startRecord = 0;
                                    $endRecord = 0;
                                }
                            ?>

                                <div class="management-table-footer">
                                    <p>
                                        Showing <?php echo $startRecord; ?>
                                        to <?php echo $endRecord; ?>
                                        of <?php echo $totalMissions; ?> entries
                                    </p>

                                    <?php
                                        // Keep filter values when changing pages
                                        $paginationParams = [];

                                        if ($search !== '') {
                                            $paginationParams['search'] = $search;
                                        }

                                        if ($missionName !== '') {
                                            $paginationParams['missionName'] = $missionName;
                                        }

                                        if ($ecoPoints !== '') {
                                            $paginationParams['ecoPoints'] = $ecoPoints;
                                        }

                                        if ($status !== '') {
                                            $paginationParams['status'] = $status;
                                        }
                                    ?>

                                    <div class="pagination">
                                        <!-- Previous -->
                                        <?php if ($page > 1) {
                                            $previousParams = $paginationParams;
                                            $previousParams['page'] = $page - 1;
                                        ?>
                                            <a href="?<?php echo http_build_query($previousParams); ?>">
                                                &lt;
                                            </a>
                                        <?php } ?>

                                        <!-- Page Numbers -->
                                        <?php for ($i = 1; $i <= $totalPages; $i++) {
                                            $pageParams = $paginationParams;
                                            $pageParams['page'] = $i;
                                        ?>
                                            <a
                                                href="?<?php echo http_build_query($pageParams); ?>"
                                                class="<?php echo ($i == $page) ? 'active' : ''; ?>"
                                            >
                                                <?php echo $i; ?>
                                            </a>

                                        <?php } ?>

                                        <!-- Next -->
                                        <?php if ($page < $totalPages) {
                                            $nextParams = $paginationParams;
                                            $nextParams['page'] = $page + 1;
                                        ?>
                                            <a href="?<?php echo http_build_query($nextParams); ?>">
                                                &gt;
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                        </div>
                    </div>
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
            <h2>Delete Mission Confirmation</h2>
            <p>Are you sure you want to delete this mission?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST" action="auditor_missions_management.php">
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
