<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require_once '../db.php';

    $conn->set_charset("utf8mb4");

    $search = trim($_GET['search'] ?? '');
    $rewardName = trim($_GET['rewardName'] ?? '');
    $redemptionDate = trim($_GET['redemptionDate'] ?? '');

    $limit = 8;
    $page = max(1, (int)($_GET['page'] ?? 1));

    $where = " WHERE 1=1";
    $params = [];
    $types = '';

    if ($search !== '') {
        $where .= " AND (rr.redemptionID LIKE ? OR rr.userID LIKE ? OR u.name LIKE ? OR rr.rewardID LIKE ? OR r.rewardName LIKE ? OR rr.redemptionDate LIKE ?)";
        $s = "%$search%";
        array_push($params, $s, $s, $s, $s, $s, $s);
        $types .= "ssssss";
    }

    if ($rewardName !== '') {
        $where .= " AND r.rewardName = ?";
        $params[] = $rewardName;
        $types .= "s";
    }

    if ($redemptionDate !== '') {
        $where .= " AND DATE(rr.redemptionDate) = ?";
        $params[] = $redemptionDate;
        $types .= "s";
    }

    $rewardList = [];
    $rewardResult = $conn->query("SELECT rewardName FROM rewards ORDER BY rewardName ASC");

    while ($reward = $rewardResult->fetch_assoc()) {
        $rewardList[] = $reward['rewardName'];
    }

    $countSQL = "SELECT COUNT(*) AS total FROM rewardredemption rr LEFT JOIN user u ON rr.userID = u.userID LEFT JOIN rewards r ON rr.rewardID = r.rewardID $where";
    $countStmt = $conn->prepare($countSQL);
    if ($params) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();

    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $pages = max(1, ceil($total / $limit));
    $page = min($page, $pages);
    $offset = ($page - 1) * $limit;

    $sql = "SELECT rr.redemptionID, rr.userID, u.name AS memberName, rr.rewardID, r.rewardName, rr.redemptionDate FROM rewardredemption rr LEFT JOIN user u ON rr.userID = u.userID LEFT JOIN rewards r ON rr.rewardID = r.rewardID $where ORDER BY rr.redemptionDate DESC LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
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
                         
                        <div class="management-filter-group search">
                            <label for="search">Search History</label>
                            <div class="management-search-input">
                                <img src="../assets/icons/search.png" alt="Search">
                                <input type="text" id="search" name="search" placeholder="Search by reward name or redemption date." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                         
                        <div class="management-filter-group">
                            <label for="Reward Name">
                                Reward Name
                            </label>
                            <select name="rewardName" id="rewardName">
                                <option value="">
                                    All Rewards
                                </option>
                                <?php foreach ($rewardList as $reward): ?>
                                    <option value="<?= htmlspecialchars($reward) ?>" <?= $rewardName === $reward ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($reward) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                         
                        <div class="management-filter-group">
                            <label for="redemptionDate">
                                Redemption Date
                            </label>
                            <input type="date" id="redemptionDate" name="redemptionDate" value="<?php echo htmlspecialchars($redemptionDate); ?>">
                        </div>

                         
                        <div class="management-filter-buttons">
                            <button type="submit" class="management-filter-btn">
                                <img src="../assets/icons/filter.png" alt="Filter">
                                Filter
                            </button>

                             
                            <a href="auditor_reward_redemption_history.php" class="management-reset-btn">
                                <img src="../assets/icons/reset.png" alt="Reset">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="management-list">
                     
                    <div class="management-list-header">
                        <h2>Reward Redemption</h2>
                        <p>Total Redemptions: <?php echo $total; ?></p>
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
                            Showing <?php echo $total > 0 ? $offset + 1 : 0; ?>
                            to <?php echo min($offset + $limit, $total); ?>
                            of <?php echo $total; ?>
                            entries
                        </p>

                        <div class="pagination">
                            <?php $paginationParams = $_GET; ?>
                             
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

                             
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <?php $paginationParams['page'] = $i; ?>
                                <a href="?<?php echo http_build_query($paginationParams);?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                             
                            <?php if ($page < $pages): ?>
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