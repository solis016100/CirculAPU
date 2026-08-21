<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require_once '../db.php';
    $conn->set_charset("utf8mb4");

    $search = trim($_GET['search'] ?? '');
    $badgeName = trim($_GET['badgeName'] ?? '');
    $awardedDate = trim($_GET['awardedDate'] ?? '');

    $limit = 8;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $where = " WHERE 1=1";
    $params = [];
    $types = '';

    if ($search !== '') {
        $where .= " AND (uab.userID LIKE ? OR u.name LIKE ? OR uab.achieveBadgeID LIKE ? OR ab.achieveBadgeName LIKE ?)";
        $s = "%$search%";
        array_push($params, $s, $s, $s, $s);
        $types .= "ssss";
    }

    if ($badgeName !== '') {
        $where .= " AND ab.achieveBadgeName LIKE ?";
        $params[] = "%$badgeName%";
        $types .= "s";
    }

    if ($awardedDate !== '') {
        $where .= " AND DATE(uab.awardedDate) = ?";
        $params[] = $awardedDate;
        $types .= "s";
    }

    /* Count */
    $stmt = $conn->prepare("SELECT COUNT(*) total FROM `user achievement badge` uab LEFT JOIN user u ON uab.userID=u.userID LEFT JOIN achievementbadges ab ON uab.achieveBadgeID=ab.achieveBadgeID $where");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $pages = max(1, ceil($total / $limit));
    $page = min($page, $pages);
    $offset = ($page - 1) * $limit;

    /* Records */
    $sql = "SELECT uab.*, u.name memberName, ab.achieveBadgeName FROM `user achievement badge` uab LEFT JOIN user u ON uab.userID=u.userID LEFT JOIN achievementbadges ab ON uab.achieveBadgeID=ab.achieveBadgeID $where ORDER BY uab.awardedDate DESC LIMIT ? OFFSET ?";

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
    <title>Auditor User Badges Record - CirculAPU</title>
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
                    <a href="auditor_achievement_badges_management.php">
                        Achievement Badges Management
                    </a>
                    &gt; User Badges Record
                </div>

                <div class="management-heading management-heading-action">
                    <div class="management-heading-text">
                        <h1>User Badges Record</h1>
                        <p>View all achievement badges awarded to members.</p>
                    </div>
                </div>

                <div class="management-filter">
                    <form method="GET" class="management-filter-form">
                        <!-- Search Reward -->
                        <div class="management-filter-group search">
                            <label>Search User Badge Records</label>
                            <div class="management-search-input">
                                <img src="../assets/icons/search.png">
                                <input name="search" placeholder="Search by badge name or awarded date..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>

                        <!-- Badge Name -->
                        <div class="management-filter-group">
                            <label>Badge Name</label>
                            <input name="badgeName" placeholder="Search by badge name..."
                            value="<?= htmlspecialchars($badgeName) ?>">
                        </div>

                        <!-- Awarded Date -->
                        <div class="management-filter-group">
                            <label>Awarded Date</label>
                            <input type="date" name="awardedDate"
                            value="<?= htmlspecialchars($awardedDate) ?>">
                        </div>

                        <!-- Filter -->
                        <div class="management-filter-buttons">
                            <button type="submit" class="management-filter-btn">
                                <img src="../assets/icons/filter.png" alt="Filter">
                                Filter
                            </button>

                            <!-- Reset -->
                            <a href="auditor_user_badge_record.php" class="management-reset-btn">
                                <img src="../assets/icons/reset.png" alt="Reset">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="management-list">
                    <!-- Table Headings -->
                    <div class="management-list-header">
                        <h2>User Badges</h2>
                        <p>Total User Badges: <?php echo $total; ?></p>
                    </div>

                    <div class="management-table-wrapper">
                        <table class="management-table user-badge-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User Badge ID</th>
                                    <th>Member ID</th>
                                    <th>Member Name</th>
                                    <th>Badge ID</th>
                                    <th>Badge Name</th>
                                    <th>Awarded Date</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php $n=$offset+1; while($r=$result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $n++ ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['userBadgeID']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['userID']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['memberName'] ?? 'Unknown') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['achieveBadgeID']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['achieveBadgeName'] ?? 'Unknown') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['awardedDate']) ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="management-table-footer">
                        <p>
                            Showing <?= $total ? $offset+1 : 0 ?>
                            to <?= min($offset+$limit,$total) ?>
                            of <?= $total ?>
                            entries
                        </p>

                        <div class="pagination">
                            <?php $q=$_GET; ?>
                            <?php if($page>1): $q['page']=$page-1; ?>
                                <a href="?<?= http_build_query($q) ?>">&lsaquo;</a>
                                <?php else: ?>
                                <a class="disabled">&lsaquo;</a>
                            <?php endif; ?>

                            <?php for($i=1;$i<=$pages;$i++): $q['page']=$i; ?>
                                <a href="?<?= http_build_query($q) ?>" class="<?= $i==$page?'active':'' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if($page<$pages): $q['page']=$page+1; ?>
                                <a href="?<?= http_build_query($q) ?>">&rsaquo;</a>
                                    <?php else: ?>
                                <a class="disabled">&rsaquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>