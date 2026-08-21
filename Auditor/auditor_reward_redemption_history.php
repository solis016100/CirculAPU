<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require_once '../db.php';

    $conn->set_charset("utf8mb4");

    $rewardName = isset($_GET['rewardName']) ? trim($_GET['rewardName']) : '';

    $redemptionDate = isset($_GET['redemptionDate']) ? trim($_GET['redemptionDate']) : '';

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    /* =========================================================
    PAGINATION
    ========================================================= */
    $recordsPerPage = 8;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    if ($page < 1) {
        $page = 1;
    }

    /* =========================================================
    COUNT RECORDS
    ========================================================= */
    $countQuery = "SELECT COUNT(*) AS total FROM rewardredemption rr LEFT JOIN rewards r ON rr.rewardID = r.rewardID WHERE 1=1";

    $countParams = [];
    $countTypes = '';

    if ($search !== '') {
        $countQuery .= "AND (r.rewardName LIKE ? OR rr.redemptionDate LIKE ?)";

        $searchValue = "%$search%";

        $countParams[] = $searchValue;
        $countParams[] = $searchValue;

        $countTypes .= "ss";
    }

    if ($rewardName !== '') {
        $countQuery .= " AND r.rewardName LIKE ?";
        $countParams[] = "%$rewardName%";
        $countTypes .= "s";
    }

    if ($redemptionDate !== '') {
        $countQuery .= " AND DATE(rr.redemptionDate) = ?";
        $countParams[] = $redemptionDate;
        $countTypes .= "s";
    }

    $countStmt = $conn->prepare($countQuery);

    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }

    $countStmt->execute();

    $countRow = $countStmt->get_result()->fetch_assoc();

    $totalRedemptions = (int)$countRow['total'];

    $countStmt->close();

    $totalPages = max(1, ceil($totalRedemptions / $recordsPerPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $recordsPerPage;

    /* =========================================================
    GET REDEMPTION RECORDS
    ========================================================= */
    $query = "SELECT rr.redemptionID, rr.userID, u.name AS memberName, rr.rewardID, r.rewardName, rr.redemptionDate FROM rewardredemption rr LEFT JOIN user u ON rr.userID = u.userID LEFT JOIN rewards r ON rr.rewardID = r.rewardID WHERE 1=1";

    $params = [];
    $types = '';

    if ($search !== '') {
        $query .= "AND (r.rewardName LIKE ? OR rr.redemptionDate LIKE ?)";

        $searchValue = "%$search%";

        $params[] = $searchValue;
        $params[] = $searchValue;

        $types .= "ss";
    }

    if ($rewardName !== '') {
        $query .= " AND r.rewardName LIKE ?";
        $params[] = "%$rewardName%";
        $types .= "s";
    }

    if ($redemptionDate !== '') {
        $query .= " AND DATE(rr.redemptionDate) = ?";
        $params[] = $redemptionDate;
        $types .= "s";
    }

    $query .= " ORDER BY rr.redemptionDate DESC LIMIT ? OFFSET ?";

    $params[] = $recordsPerPage;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Reward Redemption History - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>
        <div class="main-content">
            <?php include '../header.php'; ?>
            <main class="page-body auditor-management-detail-page redemption-history-page">

                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="auditor_rewards_management.php">
                        Reward Management
                    </a>
                    &gt; Reward Redemption History
                </div>

                <div class="management-heading management-heading-action">
                    <div class="management-heading-text">
                        <h1>Reward Redemption History</h1>
                        <p>View all member reward redemption history.</p>
                    </div>
                </div>

                <div class="management-filter">
                    <form method="GET" class="management-filter-form">
                        <!-- Search Reward -->
                        <div class="management-filter-group search">
                            <label for="search">Search History</label>
                            <div class="management-search-input">
                                <img src="../assets/icons/search.png" alt="Search">
                                <input type="text" id="search" name="search" placeholder="Search by reward name or redemption date." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <!-- Reward Name -->
                        <div class="management-filter-group">
                            <label for="rewardName">
                                Reward Name
                            </label>
                            <input type="text" id="rewardName" name="rewardName" placeholder="Search by reward name..." value="<?php echo htmlspecialchars($rewardName); ?>">
                        </div>

                        <!-- Redemption Date -->
                        <div class="management-filter-group">
                            <label for="redemptionDate">
                                Redemption Date
                            </label>
                            <input type="date" id="redemptionDate" name="redemptionDate" value="<?php echo htmlspecialchars($redemptionDate); ?>">
                        </div>

                        <!-- Filter -->
                        <div class="management-filter-buttons">
                            <button type="submit" class="management-filter-btn">
                                <img src="../assets/icons/filter.png" alt="Filter">
                                Filter
                            </button>

                            <!-- Reset -->
                            <a href="auditor_reward_redemption_history.php" class="management-reset-btn">
                                <img src="../assets/icons/reset.png" alt="Reset">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="management-list">
                    <!-- Table Headings -->
                    <div class="management-list-header">
                        <h2>Reward Redemption</h2>
                        <p>Total Redemptions: <?php echo $totalRedemptions; ?></p>
                    </div>

                    <div class="management-table-wrapper">
                        <table class="management-table redemption-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Redemption ID</th>
                                    <th>Member ID</th>
                                    <th>Member Name</th>
                                    <th>Reward ID</th>
                                    <th>Reward Name</th>
                                    <th>Redemption Date</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php  $number = $offset + 1; while ($row = mysqli_fetch_assoc($result)):?>
                                    <tr>
                                        <td><?php echo $number; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['redemptionID']);?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['userID']);?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['memberName'] ?? 'Unknown'); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['rewardID']);?>
                                        </td>
                                         <td>
                                            <?php echo htmlspecialchars($row['rewardName'] ?? 'Unknown'); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['redemptionDate']);?>
                                        </td>
                                    </tr>
                                <?php $number++; endwhile;?>
                            </tbody>
                        </table>
                    </div>

                    <div class="management-table-footer">
                        <p>
                            Showing <?php echo $totalRedemptions > 0 ? $offset + 1 : 0; ?>
                            to <?php echo min($offset + $recordsPerPage, $totalRedemptions);?>
                            of <?php echo $totalRedemptions; ?>
                            entries
                        </p>

                        <div class="pagination">
                            <?php $paginationParams = $_GET; ?>
                            <!-- Previous -->
                            <?php if ($page > 1): ?>
                                <?php $paginationParams['page'] = $page - 1; ?>
                                <a href="?<?php echo http_build_query($paginationParams);?>">
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
                                <a href="?<?php echo http_build_query($paginationParams);?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next -->
                            <?php if ($page < $totalPages): ?>
                                <?php $paginationParams['page'] = $page + 1; ?>
                                <a href="?<?php echo http_build_query($paginationParams);?>">
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
</body>
</html>