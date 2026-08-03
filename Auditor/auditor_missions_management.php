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

    // Connect to Database
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");
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
    $countQuery = "SELECT COUNT(*) AS total FROM missions";
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
            ORDER BY missionID ASC
            LIMIT $recordsPerPage OFFSET $offset";

    $result = mysqli_query($conn, $query);
    ?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Mission Management Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="role-auditor">
    <div class="dashboard-container">

        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            
            <?php include 'header.php'; ?>


                <h1>Mission Management</h1>
                <p>Manage all Missions on the platform</p>

                <a href="auditor_mission_create.php">
                    <img src="assets/icons/create.png" alt="Create">
                    Create New Mission
                </a>

                <!-- Mission Table Header -->
                <div class="mission-list">
                    <div class="mission-list-header">
                        <h2>Mission List</h2>
                        <p>Total Mission: <?php echo $totalMissions; ?></p>
                    </div>

                    <table>
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

                            <?php $number = $offset + 1; while ($mission = mysqli_fetch_assoc($result)) {?>
                                <tr>
                                    <td><?php echo $number; ?></td>
                                    <td><?php echo htmlspecialchars($mission['missionID']); ?></td>
                                    <td><?php echo htmlspecialchars($mission['missionName']); ?></td>
                                    <td><?php echo htmlspecialchars($mission['ecoPointsReward']); ?></td>
                                    <td><?php echo htmlspecialchars($mission['status']); ?></td>
                                    <td>
                                        <a href="auditor_mission_view.php?id=<?php echo urlencode($mission['missionID']); ?>">
                                            <img src="assets/icons/view.png" alt="View">
                                        </a>

                                        <a href="auditor_mission_edit.php?id=<?php echo urlencode($mission['missionID']); ?>">
                                            <img src="assets/icons/edit.png" alt="Edit">
                                        </a>

                                        <a href="auditor_mission_print.php?id=<?php echo urlencode($mission['missionID']); ?>">
                                            <img src="assets/icons/print.png" alt="Print">
                                        </a>

                                        <a href="auditor_mission_delete.php?id=<?php echo urlencode($mission['missionID']); ?>">
                                            <img src="assets/icons/delete.png" alt="Delete">
                                        </a>
                                    </td>
                                </tr>

                            <?php $number++; }?>

                        </tbody>
                    </table>

        </div>
    
</body>
</html>