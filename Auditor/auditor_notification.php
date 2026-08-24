<?php
    session_start();
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);

    if(!isset($_SESSION['user_id'])||$_SESSION['user_role']!='Auditor'){
        header("Location: ../login.php");exit;
    }
    require_once '../db.php';

    function e($x){return htmlspecialchars($x??'',ENT_QUOTES,'UTF-8');}

    $uid=$_SESSION['user_id'];
    $search=trim($_GET['search']??'');
    $type=$_GET['type']??'';
    $status=$_GET['status']??'';
    $page=max(1,(int)($_GET['page']??1));
    $limit=8;

    // Total notifications
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notification
        WHERE userID = ?
    ");

    $stmt->bind_param("s", $uid);
    $stmt->execute();

    $result = $stmt->get_result();
    $totalNotifications = $result->fetch_assoc()['total'];


    // Unread notifications
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unread
        FROM notification
        WHERE userID = ?
        AND status = 'Unread'
    ");

    $stmt->bind_param("s", $uid);
    $stmt->execute();

    $result = $stmt->get_result();
    $unreadCount = $result->fetch_assoc()['unread'];

    // Change notification status from unread to read
    if(isset($_GET['read']) && empty($_SESSION['simulation_read_only'])){
        $s=$conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=? AND status='Unread'");
        $s->bind_param('ss',$_GET['read'],$uid);
        $s->execute();

        $q=$_GET;
        unset($q['read']);
        header("Location: auditor_notification.php?".http_build_query($q));
        exit;
    }

    // Action Buttons
    if($_SERVER['REQUEST_METHOD']=='POST'){
        $a=$_POST['action']??'';

        if($a=='selected'&&!empty($_POST['ids'])){
            $s=$conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=?");
            foreach($_POST['ids'] as $x){$s->bind_param('ss',$x,$uid);$s->execute();}
        }

        if($a=='all'){
            $s=$conn->prepare("UPDATE notification SET status='Read' WHERE userID=? AND status='Unread'");
            $s->bind_param('s',$uid);$s->execute();
        }

        if($a=='delete'&&!empty($_POST['ids'])){
            $s=$conn->prepare("DELETE FROM notification WHERE notificationID=? AND userID=?");
            foreach($_POST['ids'] as $x){$s->bind_param('ss',$x,$uid);$s->execute();}
        }

        header("Location: auditor_notification.php");exit;
    }


    // Notification records
    $s=$conn->prepare("SELECT notificationID,title,content,status,createdDate FROM notification WHERE userID=? ORDER BY createdDate DESC");
    $s->bind_param('s',$uid);$s->execute();
    $data=$s->get_result()->fetch_all(MYSQLI_ASSOC);

    // Related to
    $r=$conn->query("SELECT assetID id,assetName name,'Verification' type FROM assets
        UNION ALL SELECT missionID,missionName,'Mission' FROM missions
        UNION ALL SELECT rewardID,rewardName,'Reward' FROM rewards
        UNION ALL SELECT achieveBadgeID,achieveBadgeName,'Badge' FROM achievementbadges");
    $related=$r->fetch_all(MYSQLI_ASSOC);

    // Type & Related to
    foreach($data as &$n){
        $text=$n['title'].' '.$n['content']; $n['type']='System'; $n['related']='-';

        if(stripos($text,'Borrow')!==false)$n['type']='Borrowing';

        foreach($related as $r){
            if(stripos($text,$r['id'])!==false||stripos($text,$r['name'])!==false){
                $n['type']=$r['type'];
                $n['related']=$r['id'];
                break;
            }
        }
    }
    unset($n);

    // Filter
    $data=array_values(array_filter($data,function($n)use($search,$type,$status){
        if($type&&$n['type']!=$type)return false;
        if($status&&$n['status']!=$status)return false;
        return !$search||stripos($n['title'].' '.$n['content'].' '.$n['type'].' '.$n['related'],$search)!==false;
    }));

    // Pagination
    $total=count($data);
    $pages=max(1,ceil($total/$limit));
    $page=min($page,$pages);
    $rows=array_slice($data,($page-1)*$limit,$limit);
    $start=$total?($page-1)*$limit+1:0;
    $end=min($page*$limit,$total);
    $q=http_build_query(array_filter(['search'=>$search,'type'=>$type,'status'=>$status]));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Auditor Notification Center - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>
        <div class="main-content">
            <?php include '../header.php'; ?>
            <main class="page-body auditor-notification-page">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="auditor_dashboard.php">
                        Sustainability Auditor Dashboard
                    </a>
                    &gt;
                    Notifications
                </div>

                <div class="management-heading management-heading-action">
                    <div class="management-heading-text">
                        <h1>Notification Center</h1>
                        <p>View and manage all system notifications.</p>
                    </div>
                </div>

                <section class="stats-grid">
                    <!-- Total Notifications -->
                    <a href="auditor_notification.php" class="stat-card">
                        <div class="stat-content">
                            <h3>Total Notifications</h3>

                            <strong class="stat-value">
                                <?= $totalNotifications ?>
                            </strong>

                            <p class="stat-description">
                                All auditor notifications
                            </p>
                        </div>
                    </a>

                    <!-- Unread -->
                    <a href="auditor_notification.php?status=Unread" class="stat-card">
                        <div class="stat-content">
                            <h3>Unread</h3>

                            <strong class="stat-value">
                                <?= $unreadCount ?>
                            </strong>

                            <p class="stat-description">
                                Require your attention
                            </p>
                        </div>
                    </a>
                </section>

                <!-- Filter -->
                <section class="management-filter">
                    <form method="GET">
                        <div class="management-filter-group search">
                            <label>Search Notification</label>
                            <div class="management-search-input">
                                <img src="../assets/icons/search.png">
                                <input name="search" value="<?=e($search)?>" placeholder="Search by title, asset ID, exchange ID...">
                            </div>
                        </div>

                        <div class="management-filter-group">
                            <label>Type</label>
                            <select name="type">
                                <option value="">All Types</option><?php foreach(['Verification','Mission','Reward','Badge','System'] as $x): ?>
                                <option <?= $type==$x?'selected':'' ?>><?=$x?></option>
                            <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="management-filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="Unread" <?=$status=='Unread'?'selected':''?>>Unread</option>
                                <option value="Read" <?=$status=='Read'?'selected':''?>>Read</option>
                            </select>
                        </div>

                        <div class="management-filter-buttons">
                            <button class="management-filter-btn">
                                <img src="../assets/icons/filter.png">
                                Filter
                            </button>
                            <a href="auditor_notification.php" class="management-reset-btn">
                                <img src="../assets/icons/reset.png">
                                Reset
                            </a>
                        </div>
                    </form>
                </section>
        
                <!-- Notification table -->
                <section class="management-list">
                    <form method="POST" id="notificationForm">
                        <input type="hidden" name="action" id="action">
                        <div class="management-list-header">
                            <h2>Notification List</h2>
                            <button type="button"
                                    class="management-create-btn"
                                    onclick="act('all')">
                                <img src="../assets/icons/mark_as_read.png" alt="">
                                Mark All as Read
                            </button>
                        </div>

                        <div class="management-table-wrapper">
                            <table class="management-table notification-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Notification</th>
                                        <th>Type</th>
                                        <th>Related To</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if($rows): foreach($rows as $r): ?>
                                    <tr class="<?=$r['status']=='Unread'?'unread':'read'?>">

                                        <td>
                                            <input type="checkbox"
                                                name="ids[]"
                                                class="notification-checkbox"
                                                value="<?=e($r['notificationID'])?>">
                                        </td>

                                        <td>
                                            <strong><?=e($r['title'])?></strong><br>
                                            <small><?=e($r['content'])?></small>
                                        </td>

                                        <td><?=$r['type']?></td>

                                        <td><?=$r['related']?></td>

                                        <td>
                                            <?=date('d M Y',strtotime($r['createdDate']))?><br>
                                            <?=date('h:i A',strtotime($r['createdDate']))?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?=$r['status']=='Read'?'approved':'rejected'?>">
                                                <?=$r['status']?>
                                            </span>
                                        </td>

                                        <td>
                                            <a href="?read=<?=urlencode($r['notificationID'])?>">
                                                <img src="../assets/icons/view.png" width="18">
                                            </a>
                                        </td>

                                    </tr>

                                    <?php endforeach; else: ?>

                                    <tr>
                                        <td colspan="7">No notifications found.</td>
                                    </tr>

                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Automatic Page Number -->
                            <?php
                                if ($total > 0) {
                                    $startRecord = ($page - 1) * $limit + 1;
                                    $endRecord = min($page * $limit, $total);
                                }else {
                                    $startRecord = 0;
                                    $endRecord = 0;
                                }

                                $paginationParams = [];

                                if ($search !== '') $paginationParams['search'] = $search;
                                if ($type !== '') $paginationParams['type'] = $type;
                                if ($status !== '') $paginationParams['status'] = $status;
                            ?>

                            <div class="management-table-footer">
                                <p>
                                    Showing <?= $startRecord ?> to <?= $endRecord ?>
                                    of <?= $total ?> entries
                                </p>

                                <div class="pagination">
                                    <!-- Previous -->
                                    <?php if ($page > 1):$paginationParams['page'] = $page - 1;?>
                                        <a href="?<?= http_build_query($paginationParams) ?>">&lt;</a>
                                    <?php endif; ?>

                                    <!-- Page Numbers -->
                                    <?php for ($i = 1; $i <= $pages; $i++):$paginationParams['page'] = $i;?>
                                        <a href="?<?= http_build_query($paginationParams) ?>" class="<?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <!-- Next -->
                                    <?php if ($page < $pages):$paginationParams['page'] = $page + 1;?>
                                        <a href="?<?= http_build_query($paginationParams) ?>">&gt;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        function act(a){
            let x=document.querySelectorAll('.notification-checkbox:checked');

            if(a!='all'&&!x.length){
                alert('Please select at least one notification.');
                return;
            }

            if(a=='delete'&&!confirm('Delete selected notifications?'))return;
            if(a=='all'&&!confirm('Mark all notifications as read?'))return;

            document.getElementById('action').value=a;
            document.getElementById('notificationForm').submit();
        }
    </script>


</body>
</html>
