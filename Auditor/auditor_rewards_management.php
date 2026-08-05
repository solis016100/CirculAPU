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

        // Get filter values
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $rewardName = isset($_GET['rewardName']) ? trim($_GET['rewardName']) : '';
        $ecoPoints = isset($_GET['ecoPoints']) ? trim($_GET['ecoPoints']) : '';
        $quantity = isset($_GET['quantity']) ? trim($_GET['quantity']) : '';
        $availability = isset($_GET['availability']) ? trim($_GET['availability']) : '';

        // Number of rewards displayed per page
        $recordsPerPage = 8;

        // Get current page number
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        // Filter Conditions
        $conditions = [];

        // Search
        if ($search !== '') {
            $safeSearch = mysqli_real_escape_string($conn, $search);
            $conditions[] = "(
                rewardName LIKE '%$safeSearch%'
                OR ecoPointsCost LIKE '%$safeSearch%'
                OR quantity LIKE '%$safeSearch%'
                OR availability LIKE '%$safeSearch%'
            )";
        }

        // Filter by Reward Name
        if ($rewardName !== '') {
            $safeRewardName = mysqli_real_escape_string($conn, $rewardName);
            $conditions[] = "rewardName = '$safeRewardName'";
        }

        // Filter by EcoPoints Cost
        if ($ecoPoints !== '') {
            $safeEcoPoints = mysqli_real_escape_string($conn, $ecoPoints);
            $conditions[] = "ecoPointsCost = '$safeEcoPoints'";
        }

        // Filter by Quantity
        if ($quantity !== '') {
            $safeQuantity = mysqli_real_escape_string($conn, $quantity);
            $conditions[] = "quantity = '$safeQuantity'";
        }

        // Filter by Availability
        if ($availability !== '') {
            $safeAvailability = mysqli_real_escape_string($conn, $availability);
            $conditions[] = "availability = '$safeAvailability'";
        }

        // Create WHERE clause
        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = " WHERE " . implode(" AND ", $conditions);
        }

        // Count Filtered Rewards
        $countQuery = "SELECT COUNT(*) AS total
                    FROM rewards
                    $whereClause";
        $countResult = mysqli_query($conn, $countQuery);
        $countRow = mysqli_fetch_assoc($countResult);
        $totalRewards = $countRow['total'];


        // Pagination
        $totalPages = ceil($totalRewards / $recordsPerPage);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $recordsPerPage;


        // Get Rewards
        $query = "SELECT *
                FROM rewards
                $whereClause
                ORDER BY rewardID ASC
                LIMIT $recordsPerPage OFFSET $offset";
        $result = mysqli_query($conn, $query);


        // Get Reward Names for Dropdown
        $rewardNameQuery = "
            SELECT DISTINCT rewardName
            FROM rewards
            ORDER BY rewardName ASC
        ";
        $rewardNameResult = mysqli_query($conn, $rewardNameQuery);

        // Get EcoPoints Costs for Dropdown
        $ecoPointsQuery = "
            SELECT DISTINCT ecoPointsCost
            FROM rewards
            ORDER BY ecoPointsCost ASC
        ";
        $ecoPointsResult = mysqli_query($conn, $ecoPointsQuery);


        // Get Quantities for Dropdown
        $quantityQuery = "
            SELECT DISTINCT quantity
            FROM rewards
            ORDER BY quantity ASC
        ";
        $quantityResult = mysqli_query($conn, $quantityQuery);


        // Get Availability for Dropdown
        $availabilityQuery = "
            SELECT DISTINCT availability
            FROM rewards
            ORDER BY availability ASC
        ";
        $availabilityResult = mysqli_query($conn, $availabilityQuery);
    }
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

            <main class="page-body auditor-reward-page">

                <!-- Page Title -->
                <div class="management-heading management-heading-action">
                    <div class="management-heading-text">
                        <h1>Reward Management</h1>
                        <p>Manage all Rewards on the platform</p>
                    </div>

                    <a href="auditor_reward_create.php" class="management-create-btn">
                        <img src="../assets/icons/create.png" alt="Create">
                        Create New Reward
                    </a>
                </div>

                <!-- Search and Filter -->
                <div class="management-filter">
                    <form method="GET" action="auditor_rewards_management.php">

                        <!-- Search Reward -->
                        <div class="management-filter-group search">
                            <label for="search">Search Reward</label>
                            <div class="management-search-input">
                                <img src="../assets/icons/search.png" alt="Search">
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    placeholder="Search by reward name, ecopoints cost, quantity, availability..."
                                    value="<?php echo htmlspecialchars($search); ?>"
                                >
                            </div>
                        </div>


                        <!-- Filter by Reward Name -->
                        <div class="management-filter-group">
                            <label for="rewardName">Reward Name</label>
                            <select id="rewardName" name="rewardName">
                                <option value="">All Reward</option>
                                <?php while ($row = mysqli_fetch_assoc($rewardNameResult)) { ?>
                                    <option
                                        value="<?php echo htmlspecialchars($row['rewardName']); ?>"
                                        <?php
                                            if ($rewardName === $row['rewardName']) {
                                                echo 'selected';
                                            }
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($row['rewardName']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>


                        <!-- Filter by EcoPoints -->
                        <div class="management-filter-group">
                            <label for="ecoPoints">EcoPoints Cost</label>
                            <select id="ecoPoints" name="ecoPoints">
                                <option value="">All EcoPoints</option>
                                <?php while ($row = mysqli_fetch_assoc($ecoPointsResult)) { ?>
                                    <option
                                        value="<?php echo $row['ecoPointsCost']; ?>"
                                        <?php
                                            if ($ecoPoints == $row['ecoPointsCost']) {
                                                echo 'selected';
                                            }
                                        ?>
                                    >
                                        <?php echo $row['ecoPointsCost']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>


                        <!-- Filter by Quantity -->
                        <div class="management-filter-group">
                            <label for="quantity">Quantity</label>
                            <select id="quantity" name="quantity">
                                <option value="">All Quantity</option>
                                <?php while ($row = mysqli_fetch_assoc($quantityResult)) { ?>
                                    <option
                                        value="<?php echo $row['quantity']; ?>"
                                        <?php
                                            if ($quantity == $row['quantity']) {
                                                echo 'selected';
                                            }
                                        ?>
                                    >
                                        <?php echo $row['quantity']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- Filter by Availability -->
                        <div class="management-filter-group">
                            <label for="availability">Availability</label>
                            <select id="availability" name="availability">
                                <option value="">All Availability</option>
                                <?php while ($row = mysqli_fetch_assoc($availabilityResult)) { ?>
                                    <option
                                        value="<?php echo htmlspecialchars($row['availability']); ?>"
                                        <?php
                                            if ($availability === $row['availability']) {
                                                echo 'selected';
                                            }
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($row['availability']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>


                        <!-- Filter & Reset Buttons -->
                        <div class="management-filter-buttons">
                            <button type="submit" class="management-filter-btn">
                                <img src="../assets/icons/filter.png" alt="Filter">
                                Filter
                            </button>

                            <a href="auditor_rewards_management.php" class="management-reset-btn">
                                <img src="../assets/icons/reset.png" alt="Reset">
                                Reset
                            </a>
                        </div>
                    </form>
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
                                            <?php $availabilityClass = $row['availability'] === 'Available' ? 'availability-available' : 'availability-unavailable';?>
                                            <span class="reward-availability <?php echo $availabilityClass; ?>">
                                                <?php echo htmlspecialchars($row ['availability']); ?>
                                            </span>
                                        </td>


                                        <!-- Action Icons -->
                                        <td>
                                            <div class="management-actions">
                                                <!-- View -->
                                                <a href="auditor_view_reward.php?id=<?php echo urlencode($row['rewardID']); ?>" class="management-action-btn" title="View Reward">
                                                    <img src="../assets/icons/view.png" alt="View">
                                                </a>


                                                <!-- Edit -->
                                                <a href="auditor_edit_reward.php?id=<?php echo urlencode($row['rewardID']); ?>" class="management-action-btn" title="Edit Reward">
                                                    <img src="../assets/icons/edit.png" alt="Edit">
                                                </a>


                                                <!-- Print -->
                                                <a href="auditor_print_reward.php?id=<?php echo urlencode($row['rewardID']); ?>" class="management-action-btn" title="Print Reward">
                                                    <img src="../assets/icons/print.png" alt="Print">
                                                </a>


                                                <!-- Delete -->
                                                <a href="auditor_delete_reward.php?id=<?php echo urlencode($row['rewardID']); ?>" class="management-action-btn delete" title="Delete Reward">
                                                    <img src="../assets/icons/delete.png" alt="Delete">
                                                </a>
                                            </div>
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
                        <?php $paginationParams = $_GET;?>

                        <?php if ($page > 1) {$paginationParams['page'] = $page - 1; ?>
                                <a href="?<?php echo http_build_query($paginationParams); ?>" class="pagination-btn">
                                    &lsaquo;
                                </a>
                            <?php } ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalPages; $i++) {$paginationParams['page'] = $i; ?>
                                    <a href="?<?php echo http_build_query($paginationParams); ?>"
                                    class="pagination-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php } ?>


                                <!-- Next Button -->
                                <?php if ($page < $totalPages) {$paginationParams['page'] = $page + 1; ?>
                                    <a href="?<?php echo http_build_query($paginationParams); ?>"
                                    class="pagination-btn">
                                        &rsaquo;
                                    </a>
                                <?php } ?>
                            </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>