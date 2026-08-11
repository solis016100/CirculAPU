<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$refType = trim($_GET['refType'] ?? '');
$actionType = trim($_GET['actionType'] ?? '');

$refTypes = ['Asset', 'Category', 'Transaction', 'Borrowing'];
$actionTypes = ['Create', 'Submit', 'Approve', 'Reject', 'Update', 'Delete'];

$activities = [];
$error = '';
$conn = null;

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    $sql = "
    SELECT sl.logID, sl.userID, u.name userName, u.role userRole,
        sl.actionType, sl.refType, sl.refID,
        sl.description, sl.timeLogged
        FROM systemlog sl
        LEFT JOIN user u ON sl.userID = u.userID
        WHERE sl.refType IN ('Asset', 'Category', 'Transaction', 'Borrowing')
    ";

    $types = '';
    $params = [];

    if ($search !== '') {
        $sql .= "
            AND (
                sl.refID LIKE ?
                OR sl.description LIKE ?
                OR sl.userID LIKE ?
                OR u.name LIKE ?
            )
        ";

        $keyword = "%$search%";
        $types .= 'ssss';
        array_push($params, $keyword, $keyword, $keyword, $keyword);
    }

    if (in_array($refType, $refTypes, true)) {
        $sql .= " AND sl.refType = ?";
        $types .= 's';
        $params[] = $refType;
    }

    if (in_array($actionType, $actionTypes, true)) {
        $sql .= " AND sl.actionType = ?";
        $types .= 's';
        $params[] = $actionType;
    }

    $sql .= " ORDER BY sl.timeLogged DESC";

    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) $activities[] = $row;

    $stmt->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load recent activities. Please try again.';
}

if ($conn instanceof mysqli) $conn->close();

function activityIcon(string $action): string {
    return match ($action) {
        'Create' => 'create.png',
        'Update' => 'edit.png',
        'Delete' => 'delete.png',
        'Approve' => 'mark_as_read.png',
        'Reject' => 'delete.png',
        default => 'notification.png'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recent Activities - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
</head>

<body class="role-manager">

<div class="dashboard-container">

<?php include '../sidebar.php'; ?>

<div class="main-content">

<?php include '../header.php'; ?>

<main class="page-body">

    <!-- 1. Heading -->
    <section class="management-heading">

        <div class="breadcrumb">
            <a href="manager_dashboard.php">Dashboard</a>
            <span>›</span>
            <span class="breadcrumb-current">Recent Activities</span>
        </div>

        <h1>Recent Activities</h1>

        <p>
            View activity records related to asset, category,
            transaction and borrowing management.
        </p>

    </section>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

<!-- 2. Filters -->
<section class="management-filter">

    <form method="GET">

        <div class="management-filter-group search">
            <label for="search">Search Activity</label>

            <div class="management-search-input">
                <img src="../assets/icons/search.png" alt="">

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Reference ID, user or activity"
                >
            </div>
        </div>

        <div class="management-filter-group">
            <label for="refType">Module</label>

            <select id="refType" name="refType">
                <option value="">All Modules</option>

                <?php foreach ($refTypes as $type): ?>
                    <option value="<?= e($type) ?>"
                        <?= $refType === $type ? 'selected' : '' ?>>
                        <?= e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="management-filter-group">
            <label for="actionType">Action</label>

            <select id="actionType" name="actionType">
                <option value="">All Actions</option>

                <?php foreach ($actionTypes as $action): ?>
                    <option value="<?= e($action) ?>"
                        <?= $actionType === $action ? 'selected' : '' ?>>
                        <?= e($action) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="management-filter-buttons">

            <button type="submit" class="management-filter-btn">
                <img src="../assets/icons/filter.png" alt="">
                <span>Filter</span>
            </button>

            <a href="manager_recent_activities.php"
               class="management-reset-btn">
                <img src="../assets/icons/reset.png" alt="">
                <span>Reset</span>
            </a>

        </div>

    </form>

</section>

    <!-- 3. Activity History -->
    <section class="content-card">

        <div class="card-header">
            <h2>Activity History</h2>
        </div>

        <div class="table-wrapper">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Reference</th>
                        <th>Performed By</th>
                        <th>Details</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$activities): ?>

                    <tr>
                        <td colspan="5" class="dashboard-empty-state">
                            No activities found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($activities as $a): ?>

                        <tr>

                            <td>
                                <div class="activity-name activity-flex">

                                    <span class="activity-icon">
                                        <img
                                            src="../assets/icons/<?= e(activityIcon($a['actionType'])) ?>"
                                            alt=""
                                        >
                                    </span>

                                    <strong>
                                        <?= e($a['actionType']) ?>
                                        <?= e($a['refType']) ?>
                                    </strong>

                                </div>
                            </td>

                            <td>
                                <?= e($a['refID'] ?: '-') ?>
                            </td>
                            <td>
                                <?= e(($a['userName'] ?: 'Unknown User') . ' (' . $a['userID'] . ')') ?>
                                <span class="role-badge"><?= e($a['userRole'] ?: 'Unknown') ?></span>
                            </td>

                            <td>
                                <?= e($a['description'] ?: '-') ?>
                            </td>

                            <td>
                                <?= e(date(
                                    'd M Y, h:i A',
                                    strtotime($a['timeLogged'])
                                )) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</div>
</div>

</body>
</html>