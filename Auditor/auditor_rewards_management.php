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

    require_once '../db.php';
    require_once '../delete.php';
    require_once '../print.php';

    // Delete reward
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reward'])) {
        $deleteID = $_POST['rewardID'] ?? '';
        if ($deleteID !== '') {
            // Get reward information before deleting
            $stmt = $conn->prepare("SELECT rewardName, rewardImage FROM rewards WHERE rewardID = ?");

            $stmt->bind_param("s", $deleteID);
            $stmt->execute();

            $rewardData = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($rewardData) {
                // Delete reward
                $stmt = $conn->prepare("DELETE FROM rewards WHERE rewardID = ?");

                $stmt->bind_param("s", $deleteID);

                if ($stmt->execute()) {
                    $stmt->close();

                    // Delete reward image
                    if (!empty($rewardData['rewardImage']) && file_exists("../assets/images/" . $rewardData['rewardImage'])) {
                        unlink("../assets/images/" . $rewardData['rewardImage']);
                    }

                    // Create Delete System Log
                    createDeleteLog($conn, $_SESSION['user_id'], 'Reward', $deleteID, 'Deleted reward: ' . $rewardData['rewardName']);

                    // Successful delete
                    $returnQuery = $_POST['returnQuery'] ?? '';
                    header("Location: auditor_rewards_management.php?deleted=1" . ($returnQuery !== '' ? '&' . $returnQuery : ''));
                    exit();
                }else {
                    $stmt->close();
                    header("Location: auditor_rewards_management.php?delete_error=1");
                    exit();
                }
            }else {
                header("Location: auditor_rewards_management.php?delete_error=1");
                exit();
            }
        }
    }

    // Connect to Database
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

        // Keep current filters when navigating to other pages
        $returnParams = $_GET;
        $returnQuery = http_build_query($returnParams);

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
            $conditions[] = "(rewardName LIKE '%$safeSearch%' OR ecoPointsCost LIKE '%$safeSearch%' OR quantity LIKE '%$safeSearch%' OR availability LIKE '%$safeSearch%')";
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
            if ($quantity === 'low') {
                // Low Stock = quantity 1 or 2
                $conditions[] = "quantity > 0 AND quantity < 3";
            }elseif ($quantity === 'out_of_stock') {
                // Out of Stock = quantity 0
                $conditions[] = "quantity = 0";
            }else {
                // Normal quantity filter
                $safeQuantity = mysqli_real_escape_string($conn, $quantity);
                $conditions[] = "quantity = '$safeQuantity'";
            }
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
        $countQuery = "SELECT COUNT(*) AS total FROM rewards $whereClause";
        $countResult = mysqli_query($conn, $countQuery);
        $countRow = mysqli_fetch_assoc($countResult);
        $totalRewards = $countRow['total'];

        // Pagination
        $totalPages = max(1, ceil($totalRewards / $recordsPerPage));
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $recordsPerPage;

        // Get Rewards
        $query = "SELECT * FROM rewards $whereClause ORDER BY rewardID ASC LIMIT $recordsPerPage OFFSET $offset";
        $result = mysqli_query($conn, $query);

        // Get Reward Names for Dropdown
        $rewardNameQuery = "SELECT DISTINCT rewardName FROM rewards ORDER BY rewardName ASC";
        $rewardNameResult = mysqli_query($conn, $rewardNameQuery);

        // Get EcoPoints Costs for Dropdown
        $ecoPointsQuery = "SELECT DISTINCT ecoPointsCost FROM rewards ORDER BY ecoPointsCost ASC";
        $ecoPointsResult = mysqli_query($conn, $ecoPointsQuery);

        // Get Quantities for Dropdown
        $quantityQuery = "SELECT DISTINCT quantity FROM rewards ORDER BY quantity ASC";
        $quantityResult = mysqli_query($conn, $quantityQuery);

        // Get Availability for Dropdown
        $availabilityQuery = "SELECT DISTINCT availability FROM rewards ORDER BY availability ASC";
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
    <link rel="stylesheet" href="auditor_style.css">
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

                    <div class="management-heading-buttons">
                        <a href="auditor_reward_redemption_history.php" class="management-create-btn">
                            <img src="../assets/icons/history.png" alt="RedemptionHistory">
                            Redemption History
                        </a>

                        <a href="auditor_reward_create.php" class="management-create-btn">
                            <img src="../assets/icons/create.png" alt="Create">
                            Create New Reward
                        </a>
                    </div>
                </div>

                <!-- Green bar showing Success Creation -->
                <?php if (isset($_GET['created'])): ?>
                    <div class="alert alert-success">
                        Reward created successfully.
                    </div>
                <?php endif; ?>

                <!-- Green bar showing Success Delete -->
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">
                        Reward deleted successfully.
                    </div>
                <?php endif; ?>

                <!-- Green bar showing Success Updation -->
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">
                        Reward updated successfully.
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="management-filter">
                    <form method="GET" action="auditor_rewards_management.php">
                        <!-- Search Reward -->
                        <div class="management-filter-group search">
                            <label for="search">Search Reward</label>
                            <div class="management-search-input">
                                <img src="../assets/icons/search.png" alt="Search">
                                <input type="text" id="search" name="search" placeholder="Search by reward name, ecopoints cost, quantity, availability..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>

                        <!-- Filter by Reward Name -->
                        <div class="management-filter-group">
                            <label for="rewardName">Reward Name</label>
                            <select id="rewardName" name="rewardName">
                                <option value="">All Reward</option>
                                <?php while ($row = mysqli_fetch_assoc($rewardNameResult)) { ?>
                                    <option value="<?php echo htmlspecialchars($row['rewardName']); ?>" <?php if ($rewardName === $row['rewardName']) {echo 'selected';}?>>
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
                                    <option value="<?php echo $row['ecoPointsCost']; ?>" <?php if ($ecoPoints == $row['ecoPointsCost']) {echo 'selected';}?>>
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
                                <!-- Low Stock -->
                                <option value="low"
                                    <?php echo ($quantity === 'low') ? 'selected' : ''; ?>>
                                    Low Stock (1-2)
                                </option>

                                <!-- Out of Stock -->
                                <option value="out_of_stock"
                                    <?php echo ($quantity === 'out_of_stock') ? 'selected' : ''; ?>>
                                    Out of Stock (0)
                                </option>

                                <!-- Individual Quantities -->
                                <?php while ($row = mysqli_fetch_assoc($quantityResult)) { ?>
                                    <option value="<?php echo htmlspecialchars($row['quantity']); ?>"
                                        <?php if ($quantity == $row['quantity']) { echo 'selected';}?>
                                    >
                                        <?php echo htmlspecialchars($row['quantity']); ?>
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
                                    <option value="<?php echo htmlspecialchars($row['availability']); ?>" <?php if ($availability === $row['availability']) {echo 'selected';}?>>
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
                        <p>Total Rewards: <?php echo $totalRewards; ?></p>
                    </div>

                    <!-- Reward List Table -->
                    <div class="management-table-wrapper">
                        <table class="management-table reward-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Reward ID</th>
                                    <th>Reward Image</th>
                                    <th>Reward Name</th>
                                    <th>EcoPoints Cost</th>
                                    <th>Quantity</th>
                                    <th>Availability</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php $number = $offset + 1; while ($row = mysqli_fetch_assoc($result)) { ?>
                                    <tr ondblclick="window.location.href='auditor_reward_view.php?rewardID=<?= urlencode($row['rewardID']) ?>&from=management&<?= htmlspecialchars($returnQuery) ?>'">
                                        <td>
                                            <?php echo $number; ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($row['rewardID']); ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($row['rewardImage'])): ?>
                                                <img src="../assets/images/<?php echo htmlspecialchars($row['rewardImage']); ?>" alt="<?php echo htmlspecialchars($row['rewardName']); ?>" class="management-table-image">
                                            <?php else: ?>
                                                <span>No Image</span>
                                            <?php endif; ?>
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
                                                <a href="auditor_reward_view.php?rewardID=<?php echo urlencode($row['rewardID']); ?>&from=management&<?php echo htmlspecialchars($returnQuery); ?>" class="management-action-btn" title="View Reward">
                                                    <img src="../assets/icons/view.png" alt="View">
                                                </a>

                                                <!-- Edit -->
                                                <a href="auditor_reward_edit.php?rewardID=<?php echo urlencode($row['rewardID']); ?>&from=management&<?php echo htmlspecialchars($returnQuery); ?>" class="management-action-btn" title="Edit Reward">
                                                    <img src="../assets/icons/edit.png" alt="Edit">
                                                </a>

                                                <!-- Print -->
                                                <a href="auditor_reward_view.php?rewardID=<?php echo urlencode($row['rewardID']); ?>&print=1&from=management&<?php echo htmlspecialchars($returnQuery); ?>" class="management-action-btn" title="Print Reward">
                                                    <img src="../assets/icons/print.png" alt="Print">
                                                </a>

                                                <!-- Delete -->
                                                <button type="button" class="management-action-btn delete" title="Delete" onclick="openDeletePopout('<?php echo htmlspecialchars($row['rewardID']); ?>')">
                                                    <img src="../assets/icons/delete.png" alt="Delete">
                                                </button>
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
                        <p>
                            Showing <?php echo $totalRewards > 0 ? $offset + 1 : 0; ?>
                            to <?php echo min($offset + $recordsPerPage, $totalRewards); ?>
                            of <?php echo $totalRewards; ?>
                            entries
                        </p>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php $paginationParams = $_GET;?>

                            <!-- Previous -->
                            <?php if ($page > 1): ?>
                                <?php $paginationParams['page'] = $page - 1; ?>
                                <a href="?<?php echo http_build_query($paginationParams); ?>">
                                    &lsaquo;
                                </a>
                            <?php else: ?>
                                <a href="#" class="disabled">
                                    &lsaquo;
                                </a>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php $paginationParams['page'] = $i; ?>
                                <a href="?<?php echo http_build_query($paginationParams); ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next -->
                            <?php if ($page < $totalPages): ?>
                                <?php $paginationParams['page'] = $page + 1; ?>
                                <a href="?<?php echo http_build_query($paginationParams); ?>">
                                    &rsaquo;
                                </a>
                            <?php else: ?>
                                <a href="#" class="disabled">
                                    &rsaquo;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Popout -->
    <div id="deletePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Delete Reward</h2>
            <p>Are you sure you want to delete this reward?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST">
                    <input type="hidden" name="rewardID" id="deleteRewardID">
                    <input type="hidden" name="returnQuery" value="<?php echo htmlspecialchars($returnQuery); ?>">

                    <button type="submit" name="delete_reward" class="auditor-popout-confirm">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeletePopout(rewardID) {
            document.getElementById('deleteRewardID').value = rewardID;
            document.getElementById('deletePopout').classList.add('show');
        }

        function closeDeletePopout() {
            document.getElementById('deletePopout').classList.remove('show');
        }

        document.querySelectorAll('.management-table tbody tr').forEach(row => {
            row.addEventListener('click', () => {
                if (window.innerWidth <= 768) row.ondblclick?.();
            });
        });
    </script>

</body>
</html>
