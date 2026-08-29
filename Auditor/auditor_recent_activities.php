<?php
    session_start();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

     
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header('Location: ../login.php');
        exit();
    }

    $auditorID = $_SESSION['user_id'];

    require_once '../db.php';

    function e($value): string{
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    function activityLabel(string $action, string $ref): string{
        if ($action === 'Delete'){
            return $ref === 'Asset' ? 'Removed Asset' : "Deleted $ref";
        }

        $labels = [
            'Create'  => 'Created',
            'Approve' => 'Approved',
            'Reject'  => 'Rejected',
            'Redeem'  => 'Redeemed',
            'Update'  => 'Updated'
        ];
        return ($labels[$action] ?? $action) . " $ref";
    }

     
    function activityIcon(string $action): string
    {
        return match ($action) {
            'Create' => 'create.png',
            'Update', 'Assign' => 'edit.png',
            'Delete', 'Reject' => 'delete.png',
            'Approve' => 'mark_as_read.png',
            'Submit' => 'pendingVerification.png',
            'Redeem' => 'reward.png',
            default => 'notification.png'
        };
    }


     
    $search = trim($_GET['search'] ?? '');
    $refType = trim($_GET['refType'] ?? '');
    $actionType = trim($_GET['actionType'] ?? '');

     
    $refTypes = [
        'Mission',
        'Reward',
        'Badge',
        'Verification'
    ];

    $actionTypes = [
        'Create',
        'Approve',
        'Reject',
        'Update',
        'Delete'
    ];

    $activities = [];
    $error = '';

    try {

        $conn->set_charset('utf8mb4');

        




        $sql = "
        SELECT
            sl.logID,
            sl.userID,
            u.name AS userName,
            u.role AS userRole,
            sl.actionType,
            sl.refType,
            sl.refID,
            sl.description,
            sl.timeLogged
        FROM systemlog sl
        LEFT JOIN user u
            ON sl.userID = u.userID
        WHERE sl.userID = ?
        AND sl.refType IN (
            'Mission',
            'Reward',
            'Badge',
            'Verification'
        )
    ";

        $types = 's';
        $params = [$auditorID];

         
        if ($search !== '') {

            $sql .= "
                AND (
                    sl.logID LIKE ?
                    OR sl.refID LIKE ?
                    OR sl.description LIKE ?
                    OR sl.userID LIKE ?
                    OR u.name LIKE ?
                    OR sl.refType LIKE ?
                    OR sl.actionType LIKE ?
                )
            ";

            $like = "%{$search}%";

            array_push(
                $params,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like
            );

            $types .= 'sssssss';
        }

         
        if (in_array($refType, $refTypes, true)) {

            $sql .= " AND sl.refType = ?";

            $params[] = $refType;
            $types .= 's';
        }

         
        if (in_array($actionType, $actionTypes, true)) {

            $sql .= " AND sl.actionType = ?";

            $params[] = $actionType;
            $types .= 's';
        }

         
        $sql .= " ORDER BY sl.timeLogged DESC";

        $stmt = $conn->prepare($sql);

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $activities = $stmt
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

    } catch (Throwable $e) {

        error_log($e->getMessage());

        $error = 'Unable to load recent activities. Please try again.';
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Recent Activities - CirculAPU</title>

    <link
        rel="stylesheet"
        href="../style.css"
    >

    <link
        rel="stylesheet"
        href="auditor_style.css"
    >

</head>

<body class="role-auditor">

<div class="dashboard-container">

    <?php include '../sidebar.php'; ?>

    <div class="main-content">

        <?php include '../header.php'; ?>

        <main class="page-body">

             

            <section class="management-heading">

                <div class="breadcrumb">

                    <a href="auditor_dashboard.php">
                        Sustainability Auditor Dashboard
                    </a>

                    <span>›</span>

                    <span class="breadcrumb-current">
                        Recent Activities
                    </span>

                </div>

                <h1>Recent Activities</h1>

                <p>
                    View activity records related to verification record, mission, reward and badge management.
                </p>

            </section>


             

            <?php if ($error): ?>

                <div class="alert alert-error">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            



            <section class="auditor-mobile-recent-activities">

                <details
                    class="auditor-mobile-filter-card"
                    <?= ($search || $refType || $actionType) ? 'open' : '' ?>
                >

                    <summary>

                        <span>Filter Activities</span>

                        <?php if ($search || $refType || $actionType): ?>

                            <small>Filters active</small>

                        <?php endif; ?>

                    </summary>


                    <form
                        method="GET"
                        class="auditor-mobile-filter-form"
                    >

                        <label>

                            <span>Search</span>

                            <input
                                type="text"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Reference ID, user or activity"
                            >

                        </label>


                        <div class="auditor-mobile-filter-grid">

                            <label>

                                <span>Module</span>

                                <select name="refType">

                                    <option value="">
                                        All Modules
                                    </option>

                                    <?php foreach ($refTypes as $type): ?>

                                        <option
                                            value="<?= e($type) ?>"
                                            <?= $refType === $type ? 'selected' : '' ?>
                                        >
                                            <?= e($type) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </label>


                            <label>

                                <span>Action</span>

                                <select name="actionType">

                                    <option value="">
                                        All Actions
                                    </option>

                                    <?php foreach ($actionTypes as $action): ?>

                                        <option
                                            value="<?= e($action) ?>"
                                            <?= $actionType === $action ? 'selected' : '' ?>
                                        >
                                            <?= e($action) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </label>

                        </div>


                        <div class="auditor-mobile-filter-actions">

                            <a href="auditor_recent_activities.php">
                                Reset
                            </a>

                            <button type="submit">
                                Apply Filters
                            </button>

                        </div>

                    </form>

                </details>


                 

                <div class="auditor-mobile-section-heading">

                    <div>

                        <h2>Activity History</h2>

                        <p>
                            <?= count($activities) ?>
                            record<?= count($activities) === 1 ? '' : 's' ?>
                        </p>

                    </div>

                </div>


                 

                <?php if (!$activities): ?>

                    <div class="auditor-mobile-empty-card">
                        No activities found.
                    </div>

                <?php else: ?>

                    <?php foreach ($activities as $activity): ?>

                        <article class="auditor-mobile-activity-card">

                            <div class="auditor-mobile-activity-top">

                                <div class="auditor-mobile-activity-title">

                                    <span class="activity-icon">

                                        <img
                                            src="../assets/icons/<?= e(activityIcon($activity['actionType'])) ?>"
                                            alt=""
                                        >

                                    </span>


                                    <div>

                                        <h3>
                                            <?= e(
                                                activityLabel(
                                                    $activity['actionType'],
                                                    $activity['refType']
                                                )
                                            ) ?>
                                        </h3>

                                        <p>
                                            <?= e($activity['refID'] ?: '-') ?>
                                            ·
                                            <?= e($activity['refType']) ?>
                                        </p>

                                    </div>

                                </div>


                                <span class="role-badge">
                                    <?= e($activity['userRole'] ?: 'Unknown') ?>
                                </span>

                            </div>


                            <div class="auditor-mobile-activity-meta">

                                <div>

                                    <span>Performed By</span>

                                    <strong>
                                        <?= e(
                                            ($activity['userName'] ?: 'Unknown User')
                                            . ' (' .
                                            $activity['userID']
                                            . ')'
                                        ) ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>Date & Time</span>

                                    <strong>
                                        <?= e(
                                            date(
                                                'd M Y, h:i A',
                                                strtotime($activity['timeLogged'])
                                            )
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <div class="auditor-mobile-activity-details">

                                <span>Details</span>

                                <p>
                                    <?= e(
                                        $activity['description'] ?: '-'
                                    ) ?>
                                </p>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            



            <section class="management-filter desktop-auditor-activity-filter">

                <form method="GET">

                    <div class="management-filter-group search">

                        <label for="search">
                            Search Activity
                        </label>

                        <div class="management-search-input">

                            <img
                                src="../assets/icons/search.png"
                                alt=""
                            >

                            <input
                                id="search"
                                type="text"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Reference ID, user or activity"
                            >

                        </div>

                    </div>


                    <div class="management-filter-group">

                        <label for="refType">
                            Module
                        </label>

                        <select
                            id="refType"
                            name="refType"
                        >

                            <option value="">
                                All Modules
                            </option>

                            <?php foreach ($refTypes as $type): ?>

                                <option
                                    value="<?= e($type) ?>"
                                    <?= $refType === $type ? 'selected' : '' ?>
                                >
                                    <?= e($type) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="management-filter-group">

                        <label for="actionType">
                            Action
                        </label>

                        <select
                            id="actionType"
                            name="actionType"
                        >

                            <option value="">
                                All Actions
                            </option>

                            <?php foreach ($actionTypes as $action): ?>

                                <option
                                    value="<?= e($action) ?>"
                                    <?= $actionType === $action ? 'selected' : '' ?>
                                >
                                    <?= e($action) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="management-filter-buttons">

                        <button
                            type="submit"
                            class="management-filter-btn"
                        >

                            <img
                                src="../assets/icons/filter.png"
                                alt=""
                            >

                            Filter

                        </button>


                        <a
                            href="auditor_recent_activities.php"
                            class="management-reset-btn"
                        >

                            <img
                                src="../assets/icons/reset.png"
                                alt=""
                            >

                            Reset

                        </a>

                    </div>

                </form>

            </section>


            



            <section class="content-card desktop-auditor-activity-history">

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

                                <td
                                    colspan="5"
                                    class="dashboard-empty-state"
                                >
                                    No activities found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($activities as $activity): ?>

                                <tr>

                                     

                                    <td>

                                        <div class="activity-name activity-flex">

                                            <span class="activity-icon">

                                                <img
                                                    src="../assets/icons/<?= e(activityIcon($activity['actionType'])) ?>"
                                                    alt=""
                                                >

                                            </span>


                                            <strong>

                                                <?= e(
                                                    activityLabel(
                                                        $activity['actionType'],
                                                        $activity['refType']
                                                    )
                                                ) ?>

                                            </strong>

                                        </div>

                                    </td>


                                     

                                    <td>

                                        <?= e(
                                            $activity['refID'] ?: '-'
                                        ) ?>

                                    </td>


                                     

                                    <td>

                                        <?= e(
                                            ($activity['userName'] ?: 'Unknown User')
                                            . ' (' .
                                            $activity['userID']
                                            . ')'
                                        ) ?>

                                        <span class="role-badge">

                                            <?= e(
                                                $activity['userRole']
                                                ?: 'Unknown'
                                            ) ?>

                                        </span>

                                    </td>


                                     

                                    <td>

                                        <?= e(
                                            $activity['description']
                                            ?: '-'
                                        ) ?>

                                    </td>


                                     

                                    <td>

                                        <?= e(
                                            date(
                                                'd M Y, h:i A',
                                                strtotime(
                                                    $activity['timeLogged']
                                                )
                                            )
                                        ) ?>

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
