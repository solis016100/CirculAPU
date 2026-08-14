<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') { header('Location: ../login.php'); exit(); }

date_default_timezone_set('Asia/Kuala_Lumpur');

require_once '../print.php';
require_once '../db.php';

function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function vd($v):bool{if($v==='')return true;$d=DateTime::createFromFormat('Y-m-d',$v);return $d&&$d->format('Y-m-d')===$v;}
function q(mysqli $c,string $sql,string $types='',array $p=[]):array{$s=$c->prepare($sql);if($p)$s->bind_param($types,...$p);$s->execute();$r=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $r;}
function one(mysqli $c,string $sql,string $types='',array $p=[]):array{return q($c,$sql,$types,$p)[0]??[];}
function wdate(array &$w,array &$p,string &$t,string $col,string $from,string $to):void{
    if($from){$w[]="DATE($col)>=?";$p[]=$from;$t.='s';}
    if($to){$w[]="DATE($col)<=?";$p[]=$to;$t.='s';}
}
function donut(array $rows):string{
    $total=array_sum(array_map(fn($x)=>(int)$x['total'],$rows)); if(!$total)return '#e5e7eb 0 100%';
    $colors=['#334155','#64748b','#94a3b8','#cbd5e1','#0ea5e9','#7dd3fc','#bae6fd'];
    $at=0;$parts=[];
    foreach($rows as $i=>$r){$next=$at+((int)$r['total']/$total*100);$parts[]=$colors[$i%count($colors)]." $at% $next%";$at=$next;}
    return implode(',',$parts);
}
function maxv(array $rows):int{
    if(!$rows)return 1;
    return max(array_map(fn($x)=>(int)$x['total'],$rows));
}

$report=$_GET['report']??'asset';
if(!in_array($report,['asset','transaction','category'],true))$report='asset';
$viewAll=($_GET['viewAll']??'')==='1';
$from=trim($_GET['dateFrom']??'');$to=trim($_GET['dateTo']??'');
$category=trim($_GET['category']??'');$condition=trim($_GET['condition']??'');
$availability=trim($_GET['availability']??'');$listing=trim($_GET['listingStatus']??'');
$status=trim($_GET['requestStatus']??'');
$conditions=['New','Like New','Good','Fair','Poor'];
$availabilities=['Available','Reserved','Borrowed','Unavailable'];
$listings=['Draft','Pending Review','Ready for Verification','Published','Rejected','Removed'];
$txStatuses=['Pending','Approved','In Progress','Completed','Cancelled','Rejected','Overdue'];
$error='';$categories=[];$selectedCategoryName='All';$stats=[];$chart1=$chart2=$chart3=$rows=$allRows=[];

if(!vd($from)||!vd($to))$error='Please enter a valid date.';
elseif($from&&$to&&$from>$to)$error='Date From cannot be later than Date To.';

try{
    $c=$conn;$c->set_charset('utf8mb4');
    $categories=q($c,"SELECT categoryID,categoryName FROM categories ORDER BY categoryName");
    if($category){
        foreach($categories as $cat){
            if($cat['categoryID']===$category){
                $selectedCategoryName=$cat['categoryName'];
                break;
            }
        }
    }

    if(!$error&&$report==='asset'){
        $w=["a.listingStatus<>'Removed'"];$p=[];$t='';wdate($w,$p,$t,'a.createdDate',$from,$to);
        if($category){$w[]='a.categoryID=?';$p[]=$category;$t.='s';}
        if(in_array($condition,$conditions,true)){$w[]='a.assetCondition=?';$p[]=$condition;$t.='s';}
        if(in_array($availability,$availabilities,true)){$w[]='a.availabilityStatus=?';$p[]=$availability;$t.='s';}
        if($listing&&in_array($listing,$listings,true)){$w[]='a.listingStatus=?';$p[]=$listing;$t.='s';}
        $ws='WHERE '.implode(' AND ',$w);

        $stats=one($c,"SELECT COUNT(*) total,
            COALESCE(SUM(a.availabilityStatus='Available'),0) available,
            COALESCE(SUM(a.availabilityStatus='Borrowed'),0) borrowed,
            COALESCE(SUM(a.availabilityStatus='Unavailable'),0) unavailable,
            COALESCE(SUM(a.listingStatus='Pending Review'),0) pending
            FROM assets a $ws",$t,$p);

        $chart1=q($c,"SELECT a.listingStatus label,COUNT(*) total FROM assets a $ws GROUP BY a.listingStatus ORDER BY total DESC",$t,$p);
        $chart2=q($c,"SELECT a.assetCondition label,COUNT(*) total FROM assets a $ws GROUP BY a.assetCondition ORDER BY total DESC",$t,$p);
        $chart3=q($c,"SELECT a.availabilityStatus label,COUNT(*) total FROM assets a $ws GROUP BY a.availabilityStatus ORDER BY total DESC",$t,$p);
        $allRows=q($c,"SELECT a.assetID,a.assetName,c.categoryName,a.assetCondition,a.availabilityStatus,a.listingStatus,u.name ownerName,a.createdDate
            FROM assets a JOIN categories c ON c.categoryID=a.categoryID JOIN user u ON u.userID=a.ownerID
            $ws ORDER BY a.createdDate DESC",$t,$p);
        $rows=$viewAll?$allRows:array_slice($allRows,0,8);
    }

    if(!$error&&$report==='transaction'){
        $od="EXISTS(SELECT 1 FROM borrowings b WHERE b.transactionID=tr.transactionID AND b.actualReturnDate IS NULL AND DATE(b.expectedReturnDate)<CURDATE() AND b.borrowingStatus IN ('Borrowed','Active','Overdue'))";
        $w=['1=1'];$p=[];$t='';wdate($w,$p,$t,'tr.requestDate',$from,$to);
        if($category){$w[]='a.categoryID=?';$p[]=$category;$t.='s';}
        if($status==='Overdue')$w[]=$od;
        elseif($status==='In Progress')$w[]="tr.requestStatus='In Progress' AND NOT $od";
        elseif($status&&in_array($status,$txStatuses,true)){$w[]='tr.requestStatus=?';$p[]=$status;$t.='s';}
        $ws='WHERE '.implode(' AND ',$w);

        $stats=one($c,"SELECT COUNT(*) total,
            COALESCE(SUM(tr.requestStatus='Completed'),0) completed,
            COALESCE(SUM(tr.requestStatus='Pending'),0) pending,
            COUNT(DISTINCT tr.requesterID) users
            FROM transactionrequests tr JOIN assets a ON a.assetID=tr.assetID $ws",$t,$p);

        /* These cards must respect the SAME transaction filters as the table/charts. */
        $stats['active']=one($c,"SELECT COUNT(*) total
            FROM transactionrequests tr
            JOIN assets a ON a.assetID=tr.assetID
            $ws
            AND EXISTS(
                SELECT 1 FROM borrowings b
                WHERE b.transactionID=tr.transactionID
                AND b.actualReturnDate IS NULL
                AND b.borrowingStatus IN ('Borrowed','Active','Overdue')
            )",$t,$p)['total']??0;

        $stats['overdue']=one($c,"SELECT COUNT(*) total
            FROM transactionrequests tr
            JOIN assets a ON a.assetID=tr.assetID
            $ws
            AND EXISTS(
                SELECT 1 FROM borrowings b
                WHERE b.transactionID=tr.transactionID
                AND b.actualReturnDate IS NULL
                AND DATE(b.expectedReturnDate)<CURDATE()
                AND b.borrowingStatus IN ('Borrowed','Active','Overdue')
            )",$t,$p)['total']??0;

        $chart1=q($c,"SELECT DATE_FORMAT(tr.requestDate,'%d %b') label,COUNT(*) total
            FROM transactionrequests tr JOIN assets a ON a.assetID=tr.assetID $ws
            GROUP BY DATE(tr.requestDate),DATE_FORMAT(tr.requestDate,'%d %b') ORDER BY DATE(tr.requestDate) DESC LIMIT 10",$t,$p);
        $chart1=array_reverse($chart1);
        $chart2=q($c,"SELECT CASE WHEN $od THEN 'Overdue' ELSE tr.requestStatus END label,COUNT(*) total
            FROM transactionrequests tr JOIN assets a ON a.assetID=tr.assetID $ws GROUP BY label ORDER BY total DESC",$t,$p);
        $chart3=q($c,"SELECT c.categoryName label,COUNT(*) total FROM transactionrequests tr
            JOIN assets a ON a.assetID=tr.assetID JOIN categories c ON c.categoryID=a.categoryID
            $ws GROUP BY c.categoryID,c.categoryName ORDER BY total DESC LIMIT 6",$t,$p);
        $allRows=q($c,"SELECT tr.transactionID,a.assetName,c.categoryName,u.name requesterName,
            CASE WHEN $od THEN 'Overdue' ELSE tr.requestStatus END requestStatus,tr.requestDate,
            (SELECT MAX(b.actualReturnDate) FROM borrowings b WHERE b.transactionID=tr.transactionID) completedDate
            FROM transactionrequests tr JOIN assets a ON a.assetID=tr.assetID JOIN categories c ON c.categoryID=a.categoryID JOIN user u ON u.userID=tr.requesterID
            $ws ORDER BY tr.requestDate DESC",$t,$p);
        $rows=$viewAll?$allRows:array_slice($allRows,0,8);
    }

    if(!$error&&$report==='category'){
        $w=["a.listingStatus<>'Removed'"];$p=[];$t='';wdate($w,$p,$t,'a.createdDate',$from,$to);
        if($category){$w[]='a.categoryID=?';$p[]=$category;$t.='s';}
        if(in_array($condition,$conditions,true)){$w[]='a.assetCondition=?';$p[]=$condition;$t.='s';}
        $ws='WHERE '.implode(' AND ',$w);

        $assetStats=one($c,"SELECT COUNT(*) total,COALESCE(SUM(a.availabilityStatus='Borrowed'),0) borrowed FROM assets a $ws",$t,$p);
        $tw=['1=1'];$tp=[];$tt='';wdate($tw,$tp,$tt,'tr.requestDate',$from,$to);
        if($category){$tw[]='a.categoryID=?';$tp[]=$category;$tt.='s';}
        if(in_array($condition,$conditions,true)){$tw[]='a.assetCondition=?';$tp[]=$condition;$tt.='s';}
        $tws='WHERE '.implode(' AND ',$tw);
        $txStats=one($c,"SELECT COUNT(*) total,COUNT(DISTINCT tr.requesterID) users,COALESCE(SUM(tr.requestStatus='Completed'),0) completed
            FROM transactionrequests tr JOIN assets a ON a.assetID=tr.assetID $tws",$tt,$tp);
        $categoryCount=one($c,"SELECT COUNT(*) total FROM categories ".($category?"WHERE categoryID=?":""),
            $category?'s':'',$category?[$category]:[]);
        $stats=[
            'categories'=>$categoryCount['total']??0,
            'assets'=>$assetStats['total']??0,
            'transactions'=>$txStats['total']??0,
            'users'=>$txStats['users']??0,
            'borrowed'=>$assetStats['borrowed']??0
        ];

        $chart1=q($c,"SELECT c.categoryName label,COUNT(*) total FROM assets a JOIN categories c ON c.categoryID=a.categoryID $ws GROUP BY c.categoryID,c.categoryName ORDER BY total DESC LIMIT 6",$t,$p);
        $chart2=q($c,"SELECT c.categoryName label,COUNT(*) total FROM transactionrequests tr JOIN assets a ON a.assetID=tr.assetID JOIN categories c ON c.categoryID=a.categoryID $tws GROUP BY c.categoryID,c.categoryName ORDER BY total DESC LIMIT 6",$tt,$tp);
        /* Keep current-borrowing chart aligned with the filtered asset metrics. */
        $chart3=q($c,"SELECT c.categoryName label,COUNT(*) total
            FROM assets a
            JOIN categories c ON c.categoryID=a.categoryID
            $ws AND a.availabilityStatus='Borrowed'
            GROUP BY c.categoryID,c.categoryName
            ORDER BY total DESC LIMIT 6",$t,$p);

        /*
         * Category table must use the same filters as the cards/charts.
         * Asset metrics use asset created date + condition.
         * Transaction metrics use request date + the selected asset condition.
         */
        $assetExtra='';
        $assetParams=[];$assetTypes='';
        if($from){$assetExtra.=" AND DATE(x.createdDate)>=?";$assetParams[]=$from;$assetTypes.='s';}
        if($to){$assetExtra.=" AND DATE(x.createdDate)<=?";$assetParams[]=$to;$assetTypes.='s';}
        if(in_array($condition,$conditions,true)){$assetExtra.=" AND x.assetCondition=?";$assetParams[]=$condition;$assetTypes.='s';}

        $txExtra='';
        $txParams=[];$txTypes='';
        if($from){$txExtra.=" AND DATE(tr.requestDate)>=?";$txParams[]=$from;$txTypes.='s';}
        if($to){$txExtra.=" AND DATE(tr.requestDate)<=?";$txParams[]=$to;$txTypes.='s';}
        if(in_array($condition,$conditions,true)){$txExtra.=" AND x.assetCondition=?";$txParams[]=$condition;$txTypes.='s';}

        $rowSql="SELECT c.categoryID,c.categoryName,
            (SELECT COUNT(*) FROM assets x
             WHERE x.categoryID=c.categoryID AND x.listingStatus<>'Removed' $assetExtra) totalAssets,
            (SELECT COUNT(*) FROM assets x
             WHERE x.categoryID=c.categoryID AND x.listingStatus<>'Removed'
             AND x.availabilityStatus='Available' $assetExtra) availableAssets,
            (SELECT COUNT(*) FROM assets x
             WHERE x.categoryID=c.categoryID AND x.listingStatus<>'Removed'
             AND x.availabilityStatus='Borrowed' $assetExtra) borrowedAssets,
            (SELECT COUNT(*) FROM transactionrequests tr
             JOIN assets x ON x.assetID=tr.assetID
             WHERE x.categoryID=c.categoryID $txExtra) totalTransactions,
            (SELECT COUNT(DISTINCT tr.requesterID) FROM transactionrequests tr
             JOIN assets x ON x.assetID=tr.assetID
             WHERE x.categoryID=c.categoryID $txExtra) uniqueRequesters
            FROM categories c ".($category?"WHERE c.categoryID=?":"")."
            ORDER BY totalTransactions DESC,totalAssets DESC";

        /* assetExtra is repeated 3 times and txExtra 2 times in the SQL. */
        $rowParams=array_merge(
            $assetParams,$assetParams,$assetParams,
            $txParams,$txParams,
            $category?[$category]:[]
        );
        $rowTypes=$assetTypes.$assetTypes.$assetTypes.$txTypes.$txTypes.($category?'s':'');
        $allRows=q($c,$rowSql,$rowTypes,$rowParams);
        $rows=$viewAll?$allRows:array_slice($allRows,0,8);
    }
}catch(Throwable $z){error_log($z->getMessage());$error='Unable to generate report data. Please try again.';}

$title=['asset'=>'Asset Report','transaction'=>'Transaction Report','category'=>'Category Performance Report'][$report];
$desc=['asset'=>'View and analyze asset listings across the platform.','transaction'=>'View and analyze borrowing transaction activity and progress.','category'=>'Analyze category performance using assets, transactions and requester activity.'][$report];
$currentQuery=$_GET;
$allQuery=$currentQuery;$allQuery['viewAll']='1';
$previewQuery=$currentQuery;unset($previewQuery['viewAll']);
$viewAllURL='manager_reports.php?'.http_build_query($allQuery);
$previewURL='manager_reports.php?'.http_build_query($previewQuery);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($title)?> - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css"><link rel="stylesheet" href="manager_mobile.css">
<style>
.report-tabs{display:flex;gap:0;margin:10px 0 12px}.report-tabs a{padding:8px 14px;border:1px solid #cbd5e1;background:#fff;color:#222;text-decoration:none;font-size:13px}.report-tabs a:first-child{border-radius:6px 0 0 6px}.report-tabs a:last-child{border-radius:0 6px 6px 0}.report-tabs a.active{background:var(--badge-bg);border-color:var(--primary-color);color:var(--primary-color);font-weight:600}
.report-stats{grid-template-columns:repeat(5,minmax(0,1fr))}
.report-chart-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin:16px 0;align-items:start}
.report-chart{padding:18px;min-height:0}.report-chart h2{margin:0 0 12px;font-size:15px}
.report-chart .bar-chart,.report-chart .donut-wrap{margin-top:2px}
.bar-chart{display:flex;flex-direction:column;gap:12px;padding:4px 0}
.bar-item{display:grid;grid-template-columns:minmax(110px,1fr) minmax(140px,2.2fr) 34px;gap:10px;align-items:center;text-align:left}
.bar-item small{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar-track{height:12px;background:#e8eef5;border-radius:999px;overflow:hidden}
.bar{height:100%;min-width:4px;background:var(--primary-color);border-radius:999px}
.bar-item strong{font-size:11px;text-align:right}
.donut-wrap{display:flex;align-items:center;gap:18px}.donut{width:128px;height:128px;border-radius:50%;position:relative;flex-shrink:0}.donut:after{content:"";position:absolute;inset:27px;background:#fff;border-radius:50%}.legend{display:flex;flex-direction:column;gap:6px;font-size:11px}.legend span{display:flex;justify-content:space-between;gap:12px}.report-list{margin-top:14px}
.report-chart-empty{height:160px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:13px;text-align:center}
.print-report{display:none}
.report-list-link{color:var(--primary-color);font-size:13px;font-weight:600;text-decoration:none}
.report-list-link:hover{text-decoration:underline}
@media(max-width:1150px){.report-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.report-tabs{flex-direction:column}.report-tabs a{border-radius:6px!important}.report-stats,.report-chart-grid{grid-template-columns:1fr}}
@media print{
.report-tabs,.management-filter,.management-heading .management-create-btn,.report-stats,.report-chart-grid,.screen-report{display:none!important}
.print-report{display:block!important}
.print-report h1{font-size:20px;margin:0 0 4px}
.print-meta{margin:0 0 12px;font-size:10px;line-height:1.5}
.print-report table{width:100%;border-collapse:collapse}
.print-report th,.print-report td{border:1px solid #999;padding:5px 6px;font-size:9px;text-align:left}
.print-report th{font-weight:700;background:#f3f4f6}
}
</style>
</head>
<body class="role-manager"><div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?><main class="page-body">

<section class="management-heading management-heading-action">
<div class="management-heading-text">
<div class="breadcrumb"><a href="manager_dashboard.php">Dashboard</a><span>›</span><span>Reports</span><span>›</span><span class="breadcrumb-current"><?=e($title)?></span></div>
<h1><?=e($title)?></h1><p><?=e($desc)?></p>
<div class="report-tabs">
<a href="?report=asset" class="<?=$report==='asset'?'active':''?>">Asset Report</a>
<a href="?report=transaction" class="<?=$report==='transaction'?'active':''?>">Transaction Report</a>
<a href="?report=category" class="<?=$report==='category'?'active':''?>">Category Performance Report</a>
</div>
</div>
<?php printButton('Print / Export','../assets/icons/print.png','management-create-btn');?>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>


<section class="mobile-report-ui">
<details class="mobile-filter-card" <?=($from||$to||$category||$condition||$availability||$listing||$status)?'open':''?>>
<summary><span>Filter Report</span><?php if($from||$to||$category||$condition||$availability||$listing||$status):?><small>Filters active</small><?php endif;?></summary>
<form method="GET" class="mobile-filter-form">
<input type="hidden" name="report" value="<?=e($report)?>">
<div class="mobile-filter-grid">
<label><span>Date From</span><input type="date" name="dateFrom" value="<?=e($from)?>"></label>
<label><span>Date To</span><input type="date" name="dateTo" value="<?=e($to)?>"></label>
</div>
<label><span>Category</span><select name="category">
<option value="">All Categories</option>
<?php foreach($categories as $x):?><option value="<?=e($x['categoryID'])?>" <?=$category===$x['categoryID']?'selected':''?>><?=e($x['categoryName'])?></option><?php endforeach;?>
</select></label>

<?php if($report==='asset'):?>
<div class="mobile-filter-grid">
<label><span>Condition</span><select name="condition"><option value="">All</option><?php foreach($conditions as $x):?><option <?=$condition===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></label>
<label><span>Availability</span><select name="availability"><option value="">All</option><?php foreach($availabilities as $x):?><option <?=$availability===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></label>
</div>
<label><span>Listing Status</span><select name="listingStatus"><option value="">All</option><?php foreach($listings as $x):?><option <?=$listing===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></label>
<?php elseif($report==='transaction'):?>
<label><span>Status</span><select name="requestStatus"><option value="">All</option><?php foreach($txStatuses as $x):?><option <?=$status===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></label>
<?php else:?>
<label><span>Condition</span><select name="condition"><option value="">All</option><?php foreach($conditions as $x):?><option <?=$condition===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></label>
<?php endif;?>

<div class="mobile-filter-actions">
<a href="manager_reports.php?report=<?=e($report)?>">Reset</a>
<button>Apply Filters</button>
</div>
</form>
</details>
</section>

<section class="management-filter desktop-report-filter"><form method="GET">
<input type="hidden" name="report" value="<?=e($report)?>">
<div class="management-filter-group"><label>Date From</label><input type="date" name="dateFrom" value="<?=e($from)?>"></div>
<div class="management-filter-group"><label>Date To</label><input type="date" name="dateTo" value="<?=e($to)?>"></div>
<div class="management-filter-group"><label>Category</label><select name="category"><option value="">All Categories</option><?php foreach($categories as $x):?><option value="<?=e($x['categoryID'])?>" <?=$category===$x['categoryID']?'selected':''?>><?=e($x['categoryName'])?></option><?php endforeach;?></select></div>
<?php if($report==='asset'):?>
<div class="management-filter-group"><label>Condition</label><select name="condition"><option value="">All Conditions</option><?php foreach($conditions as $x):?><option <?=$condition===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
<div class="management-filter-group"><label>Availability</label><select name="availability"><option value="">All Status</option><?php foreach($availabilities as $x):?><option <?=$availability===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
<div class="management-filter-group"><label>Listing Status</label><select name="listingStatus"><option value="">All Status</option><?php foreach($listings as $x):?><option <?=$listing===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
<?php elseif($report==='transaction'):?>
<div class="management-filter-group"><label>Status</label><select name="requestStatus"><option value="">All Status</option><?php foreach($txStatuses as $x):?><option <?=$status===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
<?php else:?>
<div class="management-filter-group"><label>Condition</label><select name="condition"><option value="">All Conditions</option><?php foreach($conditions as $x):?><option <?=$condition===$x?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
<?php endif;?>
<div class="management-filter-buttons"><button class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button><a href="manager_reports.php?report=<?=e($report)?>" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a></div>
</form></section>

<?php if(!$error):?>
<section class="stats-grid report-stats">
<?php
$cards=$report==='asset'?[
['Total Assets',$stats['total']??0,'Matching asset listings','totalassets.png'],
['Available',$stats['available']??0,'Ready to be requested','borrowed-assets.png'],
['Borrowed',$stats['borrowed']??0,'Currently with borrowers','active-transactions.png'],
['Pending Review',$stats['pending']??0,'Awaiting manager review','pendingVerification.png'],
['Unavailable',$stats['unavailable']??0,'Not available to borrow','notification.png']
]:($report==='transaction'?[
['Total Requests',$stats['total']??0,'Matching borrowing requests','active-transactions.png'],
['Pending Requests',$stats['pending']??0,'Awaiting manager decision','pendingVerification.png'],
['Active Borrowings',$stats['active']??0,'Currently with borrowers','borrowed-assets.png'],
['Overdue Returns',$stats['overdue']??0,'Past due and unreturned','notification.png'],
['Completed',$stats['completed']??0,'Finished transactions','mark_as_read.png']
]:[
['Categories',$stats['categories']??0,'Categories in scope','pendingVerification.png'],
['Total Assets',$stats['assets']??0,'Assets across categories','totalassets.png'],
['Transactions',$stats['transactions']??0,'Borrowing requests','active-transactions.png'],
['Unique Requesters',$stats['users']??0,'Distinct members','totalassets.png'],
['Current Borrowings',$stats['borrowed']??0,'Assets currently borrowed','borrowed-assets.png']
]);
foreach($cards as $x):?>
<div class="stat-card"><div class="stat-icon"><img src="../assets/icons/<?=e($x[3])?>" alt=""></div><div class="stat-content"><h3><?=e($x[0])?></h3><strong class="stat-value"><?=e($x[1])?></strong><p class="stat-description"><?=e($x[2])?></p></div></div>
<?php endforeach;?>
</section>

<?php if($chart1||$chart2||$chart3):?>
<section class="report-chart-grid">

<?php if($chart1):?>
<article class="content-card report-chart">
<h2><?=e($report==='transaction'?'Request Activity':($report==='category'?'Assets by Category':'Listing Workflow Status'))?></h2>
<div class="bar-chart"><?php $m=maxv($chart1);foreach($chart1 as $x):$w=max(4,(int)$x['total']/$m*100);?>
<div class="bar-item"><small title="<?=e($x['label'])?>"><?=e($x['label'])?></small><div class="bar-track"><div class="bar" style="width:<?=$w?>%"></div></div><strong><?=e($x['total'])?></strong></div>
<?php endforeach;?></div>
</article>
<?php endif;?>

<?php if($chart2):?>
<article class="content-card report-chart">
<h2><?=e($report==='asset'?'Assets by Condition':($report==='transaction'?'Request Status Distribution':'Transactions by Category'))?></h2>
<?php if($report==='transaction'):?>
<div class="bar-chart"><?php $m=maxv($chart2);foreach($chart2 as $x):$w=max(4,(int)$x['total']/$m*100);?>
<div class="bar-item"><small title="<?=e($x['label'])?>"><?=e($x['label'])?></small><div class="bar-track"><div class="bar" style="width:<?=$w?>%"></div></div><strong><?=e($x['total'])?></strong></div>
<?php endforeach;?></div>
<?php else:?>
<div class="donut-wrap"><div class="donut" style="background:conic-gradient(<?=e(donut($chart2))?>)"></div><div class="legend"><?php foreach($chart2 as $x):?><span><b><?=e($x['label'])?></b><em><?=e($x['total'])?></em></span><?php endforeach;?></div></div>
<?php endif;?>
</article>
<?php endif;?>

<?php if($chart3):?>
<article class="content-card report-chart">
<h2><?=e($report==='asset'?'Asset Availability Status':($report==='transaction'?'Top Requested Categories':'Current Borrowings by Category'))?></h2>
<?php if($report==='asset'):?>
<div class="donut-wrap"><div class="donut" style="background:conic-gradient(<?=e(donut($chart3))?>)"></div><div class="legend"><?php foreach($chart3 as $x):?><span><b><?=e($x['label'])?></b><em><?=e($x['total'])?></em></span><?php endforeach;?></div></div>
<?php else:?>
<div class="bar-chart"><?php $m=maxv($chart3);foreach($chart3 as $x):$w=max(4,(int)$x['total']/$m*100);?>
<div class="bar-item"><small title="<?=e($x['label'])?>"><?=e($x['label'])?></small><div class="bar-track"><div class="bar" style="width:<?=$w?>%"></div></div><strong><?=e($x['total'])?></strong></div>
<?php endforeach;?></div>
<?php endif;?>
</article>
<?php endif;?>

</section>
<?php endif;?>


<section class="mobile-report-list">
<div class="mobile-section-heading">
<div>
<h2><?=e($report==='asset'?'Asset List':($report==='transaction'?'Transaction List':'Category Performance'))?></h2>
<p><?=count($rows)?> of <?=count($allRows)?> matching records</p>
</div>
<a class="mobile-view-link" href="<?=e($viewAll?$previewURL:$viewAllURL)?>"><?=$viewAll?'Preview':'View All'?></a>
</div>

<?php if(!$rows):?>
<div class="mobile-empty-card">No report data found.</div>
<?php elseif($report==='asset'):foreach($rows as $x):?>
<article class="mobile-report-card">
<div class="mobile-report-top">
<div><span class="mobile-record-id"><?=e($x['assetID'])?></span><h3><?=e($x['assetName'])?></h3><p><?=e($x['categoryName'])?></p></div>
<span class="mobile-status-pill"><?=e($x['listingStatus'])?></span>
</div>
<div class="mobile-report-meta three">
<div><span>Condition</span><strong><?=e($x['assetCondition'])?></strong></div>
<div><span>Availability</span><strong><?=e($x['availabilityStatus'])?></strong></div>
<div><span>Listed</span><strong><?=e(date('d M Y',strtotime($x['createdDate'])))?></strong></div>
</div>
<div class="mobile-report-owner">Owner: <strong><?=e($x['ownerName'])?></strong></div>
<div class="mobile-card-actions"><a class="primary" href="manager_asset_view.php?assetID=<?=e($x['assetID'])?>">View Asset</a></div>
</article>
<?php endforeach;elseif($report==='transaction'):foreach($rows as $x):?>
<article class="mobile-report-card">
<div class="mobile-report-top">
<div><span class="mobile-record-id"><?=e($x['transactionID'])?></span><h3><?=e($x['assetName'])?></h3><p><?=e($x['categoryName'])?> · <?=e($x['requesterName'])?></p></div>
<span class="mobile-status-pill <?=$x['requestStatus']==='Overdue'?'mobile-overdue':''?>"><?=e($x['requestStatus'])?></span>
</div>
<div class="mobile-report-meta">
<div><span>Requested</span><strong><?=e(date('d M Y',strtotime($x['requestDate'])))?></strong></div>
<div><span>Completed</span><strong><?=e($x['completedDate']?date('d M Y',strtotime($x['completedDate'])):'-')?></strong></div>
</div>
<div class="mobile-card-actions"><a class="primary" href="manager_transaction_view.php?transactionID=<?=e($x['transactionID'])?>">View Transaction</a></div>
</article>
<?php endforeach;else:foreach($rows as $x):?>
<article class="mobile-report-card">
<div class="mobile-report-top">
<div><span class="mobile-record-id"><?=e($x['categoryID'])?></span><h3><?=e($x['categoryName'])?></h3></div>
</div>
<div class="mobile-report-metrics">
<div><strong><?=e($x['totalAssets'])?></strong><span>Assets</span></div>
<div><strong><?=e($x['availableAssets'])?></strong><span>Available</span></div>
<div><strong><?=e($x['borrowedAssets'])?></strong><span>Borrowed</span></div>
<div><strong><?=e($x['totalTransactions'])?></strong><span>Transactions</span></div>
<div><strong><?=e($x['uniqueRequesters'])?></strong><span>Requesters</span></div>
</div>
<div class="mobile-card-actions"><a class="primary" href="manager_category_view.php?categoryID=<?=e($x['categoryID'])?>">View Category</a></div>
</article>
<?php endforeach;endif;?>
</section>

<section class="management-list report-list screen-report desktop-report-list">
<div class="management-list-header">
<h2><?=e($report==='asset'?'Asset List':($report==='transaction'?'Transaction List':'Category Performance'))?></h2>
<div>
<span><?= $viewAll?'Showing all '.count($rows).' matching records':'Showing latest '.count($rows).' of '.count($allRows).' matching records' ?></span>
&nbsp; · &nbsp;
<a class="report-list-link" href="<?=e($viewAll?$previewURL:$viewAllURL)?>"><?=$viewAll?'Show Preview':'View All'?></a>
</div>
</div>
<div class="management-table-wrapper"><table class="management-table">
<?php if($report==='asset'):?>
<thead><tr><th>#</th><th>Asset ID</th><th>Asset Name</th><th>Category</th><th>Condition</th><th>Availability</th><th>Listing Status</th><th>Owner</th><th>Date Listed</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $i=>$x):?><tr><td><?=$i+1?></td><td><?=e($x['assetID'])?></td><td><?=e($x['assetName'])?></td><td><?=e($x['categoryName'])?></td><td><?=e($x['assetCondition'])?></td><td><?=e($x['availabilityStatus'])?></td><td><?=e($x['listingStatus'])?></td><td><?=e($x['ownerName'])?></td><td><?=e(date('d M Y',strtotime($x['createdDate'])))?></td><td><a class="management-action-btn" href="manager_asset_view.php?assetID=<?=e($x['assetID'])?>"><img src="../assets/icons/view.png" alt=""></a></td></tr><?php endforeach;?>
<?php elseif($report==='transaction'):?>
<thead><tr><th>#</th><th>Transaction ID</th><th>Asset</th><th>Category</th><th>Requester</th><th>Status</th><th>Request Date</th><th>Completed Date</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $i=>$x):?><tr><td><?=$i+1?></td><td><?=e($x['transactionID'])?></td><td><?=e($x['assetName'])?></td><td><?=e($x['categoryName'])?></td><td><?=e($x['requesterName'])?></td><td><?=e($x['requestStatus'])?></td><td><?=e(date('d M Y',strtotime($x['requestDate'])))?></td><td><?=e($x['completedDate']?date('d M Y',strtotime($x['completedDate'])):'-')?></td><td><a class="management-action-btn" href="manager_transaction_view.php?transactionID=<?=e($x['transactionID'])?>"><img src="../assets/icons/view.png" alt=""></a></td></tr><?php endforeach;?>
<?php else:?>
<thead><tr><th>#</th><th>Category</th><th>Total Assets</th><th>Available</th><th>Borrowed</th><th>Total Transactions</th><th>Unique Requesters</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $i=>$x):?><tr><td><?=$i+1?></td><td><?=e($x['categoryName'])?></td><td><?=e($x['totalAssets'])?></td><td><?=e($x['availableAssets'])?></td><td><?=e($x['borrowedAssets'])?></td><td><?=e($x['totalTransactions'])?></td><td><?=e($x['uniqueRequesters'])?></td><td><a class="management-action-btn" href="manager_category_view.php?categoryID=<?=e($x['categoryID'])?>"><img src="../assets/icons/view.png" alt=""></a></td></tr><?php endforeach;?>
<?php endif;?>
<?php if(!$rows):?><tr><td colspan="10" class="management-empty-state">No report data found.</td></tr><?php endif;?>
</tbody></table></div>
</section>

<section class="print-report">
<h1><?=e($title)?></h1>
<p class="print-meta">
<strong>Generated:</strong> <?=e(date('d M Y, h:i A'))?><br>
<strong>Period:</strong> <?=e(($from?:'All records').' to '.($to?:($from?'Today':'All records')))?><br>
<strong>Report Data:</strong> <?=($from||$to||$category||$condition||$availability||$listing||$status)?'Filtered records':'Default / all matching records'?><br>
<strong>Filters:</strong>
Category <?=e($selectedCategoryName)?><?= $report!=='transaction'?' · Condition '.e($condition?:'All'):'' ?>
<?= $report==='asset'?' · Availability '.e($availability?:'All').' · Listing '.e($listing?:'All'):'' ?>
<?= $report==='transaction'?' · Status '.e($status?:'All'):'' ?>
</p>
<table>
<?php if($report==='asset'):?>
<thead><tr><th>#</th><th>Asset ID</th><th>Asset Name</th><th>Category</th><th>Condition</th><th>Availability</th><th>Listing Status</th><th>Owner</th><th>Date Listed</th></tr></thead><tbody>
<?php foreach($allRows as $i=>$x):?><tr><td><?=$i+1?></td><td><?=e($x['assetID'])?></td><td><?=e($x['assetName'])?></td><td><?=e($x['categoryName'])?></td><td><?=e($x['assetCondition'])?></td><td><?=e($x['availabilityStatus'])?></td><td><?=e($x['listingStatus'])?></td><td><?=e($x['ownerName'])?></td><td><?=e(date('d M Y',strtotime($x['createdDate'])))?></td></tr><?php endforeach;?>
<?php elseif($report==='transaction'):?>
<thead><tr><th>#</th><th>Transaction ID</th><th>Asset</th><th>Category</th><th>Requester</th><th>Status</th><th>Request Date</th><th>Completed Date</th></tr></thead><tbody>
<?php foreach($allRows as $i=>$x):?><tr><td><?=$i+1?></td><td><?=e($x['transactionID'])?></td><td><?=e($x['assetName'])?></td><td><?=e($x['categoryName'])?></td><td><?=e($x['requesterName'])?></td><td><?=e($x['requestStatus'])?></td><td><?=e(date('d M Y',strtotime($x['requestDate'])))?></td><td><?=e($x['completedDate']?date('d M Y',strtotime($x['completedDate'])):'-')?></td></tr><?php endforeach;?>
<?php else:?>
<thead><tr><th>#</th><th>Category</th><th>Total Assets</th><th>Available</th><th>Borrowed</th><th>Total Transactions</th><th>Unique Requesters</th></tr></thead><tbody>
<?php foreach($allRows as $i=>$x):?><tr><td><?=$i+1?></td><td><?=e($x['categoryName'])?></td><td><?=e($x['totalAssets'])?></td><td><?=e($x['availableAssets'])?></td><td><?=e($x['borrowedAssets'])?></td><td><?=e($x['totalTransactions'])?></td><td><?=e($x['uniqueRequesters'])?></td></tr><?php endforeach;?>
<?php endif;?>
<?php if(!$allRows):?><tr><td colspan="9">No matching records.</td></tr><?php endif;?>
</tbody></table>
</section>
<?php endif;?>
</main>
<nav class="manager-mobile-nav" aria-label="Manager mobile navigation">
<a href="manager_dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Home</span></a>
<a href="manager_assets.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg><span>Assets</span></a>
<a href="manager_categories.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/></svg><span>Categories</span></a>
<a href="manager_transactions.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m17 1 4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/></svg><span>Transactions</span></a>
<a href="manager_reports.php" class="active" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg><span>Reports</span></a>
</nav>
</div></div></body></html>
