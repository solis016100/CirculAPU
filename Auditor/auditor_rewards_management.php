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

    // Number of rewards displayed per page
    $recordsPerPage = 8;

    // Get current page number
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

    // Prevent invalid page number
    if ($page < 1) {
        $page = 1;
    }

    // Count total rewards
    $countQuery = "SELECT COUNT(*) AS total FROM rewards";
    $countResult = mysqli_query($conn, $countQuery);
    $countRow = mysqli_fetch_assoc($countResult);
    $totalRewards = $countRow['total'];

    // Calculate total number of pages
    $totalPages = ceil($totalRewards / $recordsPerPage);

    // Prevent page number from exceeding total pages
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }

    // Calculate starting record
    $offset = ($page - 1) * $recordsPerPage;

    // Get rewards for current page
    $query = "SELECT * FROM rewards
            ORDER BY rewardID ASC
            LIMIT $recordsPerPage OFFSET $offset";

    $result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Rewards Management Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <!-- Page Title -->
            <div class="management-heading">
                <div>
                    <h1>Reward Management</h1>
                    <p>Manage all Rewards on the platform</p>
                </div>

                <a href="auditor_create_reward.php" class="management-create-btn">
                    <img src="../assets/icons/create.png" alt="Create">
                    Create New Reward
                </a>
            </div>


            <!-- Reward List -->
            <div class="management-list">

                <!-- Table Headings -->
                <div class="management-list-header">
                    <h2>Reward List</h2>
                    <h3>Total Reward: <?php echo $totalRewards; ?></h3>
                </div>


                <!-- Reward List Table -->
                <div class="management-table-wrapper">
                    <table class="management-table reward-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reward ID</th>
                                <th>Reward Name</th>
                                <th>EcoPoints Cost</th>
                                <th>Quantity</th>
                                <th>Availability</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $number = $offset + 1; while ($row = mysqli_fetch_assoc($result)) { ?>
                                <tr>
                                    <td>
                                        <?php echo $number; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['rewardID']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['rewardName']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['ecoPointsCost']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['quantity']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['availability']); ?>
                                    </td>


                                    <!-- Action Icons -->
                                    <td class="management-action-btn">
                                        <!-- View -->
                                        <a href="auditor_view_reward.php?id=<?php echo $row['rewardID']; ?>">
                                            <img src="../assets/icons/view.png" alt="View">
                                        </a>

                                        <!-- Edit -->
                                        <a href="auditor_edit_reward.php?id=<?php echo $row['rewardID']; ?>">
                                            <img src="../assets/icons/edit.png" alt="Edit">
                                        </a>

                                        <!-- Print -->
                                        <a href="auditor_print_reward.php?id=<?php echo $row['rewardID']; ?>">
                                            <img src="../assets/icons/print.png" alt="Print">
                                        </a>

                                        <!-- Delete -->
                                        <a href="auditor_delete_reward.php?id=<?php echo $row['rewardID']; ?>">
                                            <img src="../assets/icons/delete.png" alt="Delete">
                                        </a>
                                    </td>
                                </tr>

                            <?php $number++; } ?>

                        </tbody>
                    </table>
                </div>


                <!-- Table Footer -->
                <div class="management-table-footer">

                    <!-- Showing Records -->
                    <div class="showing-records">
                        <?php
                            if ($totalRewards > 0) {
                                $startRecord = $offset + 1;
                                $endRecord = min($offset + $recordsPerPage, $totalRewards);
                                echo "Showing $startRecord to $endRecord of $totalRewards entries";
                            } else {
                                echo "Showing 0 to 0 of 0 entries";
                            }
                        ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1) { ?>
                        <div class="pagination">

                            <!-- Previous Button -->
                            <?php if ($page > 1) { ?>
                                <a href="?page=<?php echo $page - 1; ?>"
                                class="pagination-btn">
                                    &lsaquo;
                                </a>
                            <?php } ?>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $totalPages; $i++) { ?>
                                <a href="?page=<?php echo $i; ?>"
                                class="pagination-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php } ?>


                            <!-- Next Button -->
                            <?php if ($page < $totalPages) { ?>
                                <a href="?page=<?php echo $page + 1; ?>"
                                class="pagination-btn">
                                    &rsaquo;
                                </a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>