<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
if(!isset($_SESSION['user_id'])||$_SESSION['user_role']!=='Manager'){header('Location: ../login.php');exit();}

require_once '../db.php';

function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function d($v,bool $time=false):string{return $v&&strtotime($v)?date($time?'d M Y, h:i A':'d M Y',strtotime($v)):'-';}
function nid(mysqli $c,string $t,string $col,string $p):string{
    $start=strlen($p)+1;$like=$p.'%';
    $s=$c->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING($col,$start) AS UNSIGNED)),0)+1 n FROM $t WHERE $col LIKE ?");
    $s->bind_param('s',$like);$s->execute();
    $n=(int)$s->get_result()->fetch_assoc()['n'];$s->close();
    return sprintf($p.'%03d',$n);
}
function logTx(mysqli $c,string $uid,string $action,string $ref,string $text):void{
    $x=nid($c,'systemlog','logID','LOG');
    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,?,'Transaction',?,?)");
    $s->bind_param('sssss',$x,$uid,$action,$ref,$text);$s->execute();$s->close();
}
function notify(mysqli $c,string $uid,string $title,string $text):void{
    $x=nid($c,'notification','notificationID','N');
    $s=$c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$x,$uid,$title,$text);$s->execute();$s->close();
}
function assetStatus(mysqli $c,string $asset,string $manager,string $old,string $new,string $why):void{
    if($old===$new)return;
    $s=$c->prepare("UPDATE assets SET availabilityStatus=?,updatedDate=NOW() WHERE assetID=?");
    $s->bind_param('ss',$new,$asset);$s->execute();$s->close();
    $x=nid($c,'assetstatushistory','historyID','H');
    $s=$c->prepare("INSERT INTO assetstatushistory(historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$x,$asset,$manager,$old,$new,$why);$s->execute();$s->close();
}
function protectionDate(string $pickup):string{
    return date('Y-m-d',strtotime($pickup.' -7 days'));
}
function scheduleCheck(mysqli $c,string $asset,string $currentTx,?array $b):array{
    $result=['issue'=>'','detail'=>'No conflicting approved or active booking detected.','conflict'=>null];
    if(!$b||empty($b['borrowDate'])||empty($b['expectedReturnDate']))return $result;

    $pickup=date('Y-m-d',strtotime($b['borrowDate']));
    $return=date('Y-m-d',strtotime($b['expectedReturnDate']));
    $protectedFrom=protectionDate($pickup);

    $s=$c->prepare("
        SELECT t.transactionID,t.requestStatus,b.borrowDate,b.expectedReturnDate,b.borrowingStatus,
               u.name borrowerName
        FROM borrowings b
        JOIN transactionrequests t ON t.transactionID=b.transactionID
        JOIN user u ON u.userID=b.borrowerID
        WHERE b.assetID=? AND b.transactionID<>?
          AND b.actualReturnDate IS NULL
          AND t.requestStatus IN ('Approved','In Progress')
          AND b.borrowingStatus IN ('Approved','Borrowed','Active','Overdue')
        ORDER BY DATE(b.borrowDate) ASC");
    $s->bind_param('ss',$asset,$currentTx);$s->execute();
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

    foreach($rows as $x){
        if(empty($x['borrowDate'])||empty($x['expectedReturnDate']))continue;
        $existingPickup=date('Y-m-d',strtotime($x['borrowDate']));
        $existingReturn=date('Y-m-d',strtotime($x['expectedReturnDate']));
        $existingProtectedFrom=protectionDate($existingPickup);
        $isActive=in_array($x['borrowingStatus'],['Borrowed','Active','Overdue'],true);
        $isOverdue=$isActive&&$existingReturn<date('Y-m-d');

        if($isOverdue){
            $result['issue']="Approval blocked: this asset is still with an overdue borrower from transaction {$x['transactionID']}.";
            $result['detail']="Transaction {$x['transactionID']} is overdue and the asset has not been returned.";
            $result['conflict']=$x;
            return $result;
        }

        if($protectedFrom<=$existingReturn&&$return>=$existingProtectedFrom){
            $result['issue']="Approval blocked: requested dates conflict with transaction {$x['transactionID']} or its 7-day protection window.";
            $result['detail']="Conflicts with {$x['transactionID']} ({$existingPickup} to {$existingReturn}); protection begins {$existingProtectedFrom}.";
            $result['conflict']=$x;
            return $result;
        }
    }
    return $result;
}
function approvalIssue(array $t,?array $b,bool $blocked,array $summary,string $status,array $schedule):string{
    if($status!=='Pending')return 'Only Pending requests can be approved.';
    if(($t['requesterID']??'')===($t['ownerID']??''))return 'Approval blocked: members cannot borrow their own asset.';
    if($blocked)return ($summary['overdue']??0)>0
        ?'Approval blocked: requester has an overdue unreturned asset.'
        :'Approval blocked: requester already has 5 active borrowings or reservations.';
    if(!$b||empty($b['borrowDate'])||empty($b['expectedReturnDate']))
        return 'Approval blocked: the member request is missing pickup or return date.';
    $pickup=date('Y-m-d',strtotime($b['borrowDate']));
    $return=date('Y-m-d',strtotime($b['expectedReturnDate']));
    if($pickup<date('Y-m-d'))return 'Approval blocked: the requested pickup date has already passed.';
    if($return<=$pickup)return 'Approval blocked: requested return date must be after the pickup date.';
    if($t['listingStatus']!=='Published'||$t['verificationStatus']!=='Approved')
        return 'Approval blocked: this asset is not Published and Auditor-approved.';
    if($t['availabilityStatus']==='Unavailable')
        return 'Approval blocked: this asset is operationally unavailable.';
    if($schedule['issue'])return $schedule['issue'];
    return '';
}
function validationChecks(array $t,?array $b,array $summary,string $status,array $schedule):array{
    $active=(int)($summary['active']??0);
    $overdue=(int)($summary['overdue']??0);
    $pickup=$b&&!empty($b['borrowDate'])?date('Y-m-d',strtotime($b['borrowDate'])):'';
    $return=$b&&!empty($b['expectedReturnDate'])?date('Y-m-d',strtotime($b['expectedReturnDate'])):'';

    $dateOk=(bool)($pickup&&$return&&$pickup>=date('Y-m-d')&&$return>$pickup);
    $dateDetail='Pickup and return dates are valid.';
    $dateReason='';
    if(!$pickup||!$return){
        $dateDetail='Pickup or return date is missing.';
        $dateReason='The borrowing request is missing a valid pickup or return date.';
    }elseif($pickup<date('Y-m-d')){
        $dateDetail='The requested pickup date has already passed.';
        $dateReason='The requested pickup date has already passed. Please submit a new request with a valid pickup date.';
    }elseif($return<=$pickup){
        $dateDetail='The return date must be after the pickup date.';
        $dateReason='The requested return date must be after the pickup date.';
    }

    $scheduleReason='';
    if($schedule['issue']){
        $scheduleReason=str_contains($schedule['issue'],'overdue borrower')
            ?'This asset is still with an overdue borrower and cannot be safely reserved yet. Please choose another asset or submit a new request after it is returned.'
            :'The requested borrowing period conflicts with an existing reservation or its 7-day protection window. Please submit a new request with different dates.';
    }

    return [
        [
            'label'=>'Asset ownership',
            'ok'=>($t['requesterID']??'')!==($t['ownerID']??''),
            'detail'=>(($t['requesterID']??'')!==($t['ownerID']??''))?'Requester is not the owner of this asset.':'Requester is the owner of this asset.',
            'reason'=>(($t['requesterID']??'')!==($t['ownerID']??''))?'':'You cannot borrow your own asset. Please select an asset listed by another Community Member.'
        ],
        [
            'label'=>'Current overdue borrowing',
            'ok'=>$overdue===0,
            'detail'=>$overdue===0?'Requester has no overdue unreturned asset.':"$overdue overdue unreturned borrowing(s) detected for this requester.",
            'reason'=>$overdue===0?'':'You currently have an overdue borrowing that has not been returned. Please return the overdue asset before submitting another borrowing request.'
        ],
        [
            'label'=>'Maximum active borrowings',
            'ok'=>$active<5,
            'detail'=>"$active / 5 active borrowings or reservations.",
            'reason'=>$active<5?'':'You have reached the maximum limit of 5 active borrowings or reservations. Please complete an existing borrowing before submitting another request.'
        ],
        [
            'label'=>'Requested borrowing dates',
            'ok'=>$dateOk,
            'detail'=>$dateDetail,
            'reason'=>$dateReason
        ],
        [
            'label'=>'Reservation schedule & 7-day protection',
            'ok'=>!$schedule['issue'],
            'detail'=>$schedule['detail'],
            'reason'=>$scheduleReason
        ],
        [
            'label'=>'Asset listing eligibility',
            'ok'=>$t['listingStatus']==='Published'&&$t['verificationStatus']==='Approved',
            'detail'=>($t['listingStatus']==='Published'&&$t['verificationStatus']==='Approved')
                ?'Asset is Published and Auditor-approved.'
                :'Asset is not currently Published and Auditor-approved.',
            'reason'=>($t['listingStatus']==='Published'&&$t['verificationStatus']==='Approved')
                ?'':'This asset is not currently eligible for borrowing.'
        ],
        [
            'label'=>'Asset operational status',
            'ok'=>$t['availabilityStatus']!=='Unavailable',
            'detail'=>$t['availabilityStatus']==='Unavailable'
                ?'Asset is currently Unavailable.'
                :'Current status: '.$t['availabilityStatus'].'. Schedule validation determines whether the requested dates are safe.',
            'reason'=>$t['availabilityStatus']==='Unavailable'
                ?'This asset is currently unavailable for borrowing. Please select another asset or try again when it becomes available.'
                :''
        ]
    ];
}
function autoRejectReason(array $checks):string{
    $reasons=[];
    foreach($checks as $check){
        if(!$check['ok']&&!empty($check['reason']))$reasons[]=$check['reason'];
    }
    return implode(' ',$reasons);
}
function pickupIssue(mysqli $c,array $t,?array $b,string $status,string $id):string{
    if($status!=='Approved'||!$b)return 'Pickup can only be confirmed for an approved reservation.';
    if(($b['borrowingStatus']??'')!=='Approved')return 'Pickup can only be confirmed when the borrowing status is Approved.';
    if(empty($b['borrowDate'])||empty($b['expectedReturnDate']))return 'Pickup cannot be confirmed because the reservation dates are incomplete.';

    $pickup=date('Y-m-d',strtotime($b['borrowDate']));
    $return=date('Y-m-d',strtotime($b['expectedReturnDate']));
    $earliest=$pickup;
    $today=date('Y-m-d');

    if($today<$earliest)return 'Pickup is too early. Pickup can be confirmed from '.date('d M Y',strtotime($earliest)).'.';
    if($today>$return)return 'Pickup cannot be confirmed because the approved borrowing period has already ended.';

    if($t['availabilityStatus']==='Available')
        return 'Pickup cannot be confirmed until the asset enters its 7-day protection period and becomes Reserved.';
    if($t['availabilityStatus']==='Unavailable')
        return 'Pickup cannot be confirmed because the asset is currently Unavailable.';
    if($t['availabilityStatus']==='Borrowed')
        return 'Pickup cannot be confirmed because the asset is already marked Borrowed.';
    if($t['availabilityStatus']!=='Reserved')
        return 'Pickup cannot be confirmed because the asset is not Reserved for this approved request.';

    $s=$c->prepare("
        SELECT b.transactionID,b.expectedReturnDate,u.name borrowerName
        FROM borrowings b
        JOIN transactionrequests tr ON tr.transactionID=b.transactionID
        JOIN user u ON u.userID=b.borrowerID
        WHERE b.assetID=? AND b.transactionID<>?
          AND b.actualReturnDate IS NULL
          AND b.borrowingStatus IN ('Borrowed','Active','Overdue')
        ORDER BY DATE(b.expectedReturnDate) ASC LIMIT 1");
    $s->bind_param('ss',$t['assetID'],$id);$s->execute();
    $hold=$s->get_result()->fetch_assoc();$s->close();

    if($hold){
        $due=!empty($hold['expectedReturnDate'])?date('d M Y',strtotime($hold['expectedReturnDate'])):'unknown';
        return "Pickup cannot be confirmed because the previous borrower has not returned this asset yet (transaction {$hold['transactionID']}, expected return $due).";
    }
    return '';
}

function go(string $id,string $nav=''):never{header('Location: manager_transaction_view.php?transactionID='.rawurlencode($id).$nav.'&updated=1');exit();}
function returnTo(string $url,array $q):never{
    header('Location: '.$url.(str_contains($url,'?')?'&':'?').http_build_query($q));exit();
}

$manager=$_SESSION['user_id'];$id=trim($_GET['transactionID']??'');$from=$_GET['from']??'';$back=trim($_GET['back']??'');$source=$_GET['source']??'transactions';
$backStatus=in_array($from,['Pending','Approved','In Progress','Overdue','Completed','Cancelled','Rejected'],true)?$from:($from==='pending'?'Pending':'');
$backURL=$source==='reports'?'manager_reports.php'.($back?'?'.$back:''):($back?'manager_transactions.php?'.$back:($from==='pickup'?'manager_transactions.php?pickup=1':($backStatus?'manager_transactions.php?requestStatus='.rawurlencode($backStatus):'manager_transactions.php')));
$backLabel=$source==='reports'?'Reports':'Transaction Management';
$navParam=($source==='reports'?'&source=reports':'').($back?'&back='.rawurlencode($back):'').($from?'&from='.rawurlencode($from):'');
if(!$id){header('Location: manager_transactions.php');exit();}

$t=$b=null;$summary=[];$past=$eco=$logs=$validationChecks=[];$schedule=['issue'=>'','detail'=>'No conflicting approved or active booking detected.','conflict'=>null];$autoRejectReason=$pickupIssue='';$error='';

try{
    $c=$conn;$c->set_charset('utf8mb4');

    $s=$c->prepare("
        SELECT t.*,a.assetName,a.assetCondition,a.availabilityStatus,a.listingStatus,a.verificationStatus,
               cat.categoryName,r.name requesterName,r.email requesterEmail,r.ecopoint,
               o.userID ownerID,o.name ownerName
        FROM transactionrequests t
        JOIN assets a ON t.assetID=a.assetID
        JOIN categories cat ON a.categoryID=cat.categoryID
        JOIN user r ON t.requesterID=r.userID
        JOIN user o ON a.ownerID=o.userID
        WHERE t.transactionID=? LIMIT 1");
    $s->bind_param('s',$id);$s->execute();$t=$s->get_result()->fetch_assoc();$s->close();

    if(!$t)$error='The selected transaction could not be found.';
    else{
        $s=$c->prepare("SELECT * FROM borrowings WHERE transactionID=? ORDER BY borrowDate DESC LIMIT 1");
        $s->bind_param('s',$id);$s->execute();$b=$s->get_result()->fetch_assoc();$s->close();

        $s=$c->prepare("
            SELECT u.ecopoint,
              (SELECT COUNT(*) FROM transactionrequests x WHERE x.requesterID=u.userID) requests,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND x.actualReturnDate IS NULL AND x.borrowingStatus IN ('Approved','Borrowed','Active','Overdue')) active,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND x.actualReturnDate IS NULL AND DATE(x.expectedReturnDate)<CURDATE() AND x.borrowingStatus IN ('Borrowed','Active','Overdue')) overdue,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND x.actualReturnDate IS NOT NULL) completed,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND DATE(x.actualReturnDate)>DATE(x.expectedReturnDate)) late
            FROM user u WHERE u.userID=?");
        $s->bind_param('s',$t['requesterID']);$s->execute();$summary=$s->get_result()->fetch_assoc();$s->close();

        $s=$c->prepare("SELECT b.transactionID,a.assetName,b.expectedReturnDate,b.actualReturnDate,b.borrowingStatus FROM borrowings b JOIN assets a ON b.assetID=a.assetID WHERE b.borrowerID=? AND b.transactionID<>? ORDER BY COALESCE(b.actualReturnDate,b.borrowDate) DESC LIMIT 5");
        $s->bind_param('ss',$t['requesterID'],$id);$s->execute();$past=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

        $s=$c->prepare("SELECT points,sourceType,referenceID,transactionDate FROM ecopointstransaction WHERE userID=? ORDER BY transactionDate DESC LIMIT 5");
        $s->bind_param('s',$t['requesterID']);$s->execute();$eco=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

        $borrowed=$b&&!$b['actualReturnDate']&&in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true);
        $overdue=$borrowed&&$b['expectedReturnDate']&&date('Y-m-d',strtotime($b['expectedReturnDate']))<date('Y-m-d');
        $status=in_array($t['requestStatus'],['Completed','Rejected','Cancelled'],true)?$t['requestStatus']:($overdue?'Overdue':$t['requestStatus']);
        $blocked=($summary['active']??0)>=5||($summary['overdue']??0)>0;
        $schedule=scheduleCheck($c,$t['assetID'],$id,$b);
        $approvalIssue=approvalIssue($t,$b,$blocked,$summary,$status,$schedule);
        $validationChecks=validationChecks($t,$b,$summary,$status,$schedule);
        $autoRejectReason=autoRejectReason($validationChecks);
        $pickupIssue=pickupIssue($c,$t,$b,$status,$id);

        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=$_POST['action']??'';$remark=trim($_POST['managerRemark']??'');

            if($action==='approve'){
                $pickup=$b['borrowDate']??'';$return=$b['expectedReturnDate']??'';
                if($approvalIssue)$error=$approvalIssue;
                else{
                    $pickupDate=date('Y-m-d',strtotime($pickup));
                    $protectedFrom=protectionDate($pickupDate);
                    $insideProtection=date('Y-m-d')>=$protectedFrom;
                    $reserveNow=$insideProtection&&$t['availabilityStatus']==='Available';

                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Approved',managerRemark=?,decisionDate=NOW() WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Approved' WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();

                        if($reserveNow){
                            assetStatus($c,$t['assetID'],$manager,'Available','Reserved',"7-day reservation protection activated for request $id");
                        }

                        $stateText=$reserveNow?'asset reserved inside the 7-day protection window':'future reservation confirmed; asset not locked early';
                        logTx($c,$manager,'Approve',$id,"Approved borrowing request for {$t['assetName']}; $stateText");
                        notify(
                            $c,$t['requesterID'],'Reservation Confirmed',
                            "Your request $id for {$t['assetName']} was approved. Pickup: ".d($pickup).
                            ". Return by: ".d($return).
                            ". Reservation protection begins ".d($protectedFrom)."."
                        );
                        $c->commit();returnTo($backURL,['reviewDone'=>'approve']);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='reject'){
                $managerNote=trim($_POST['managerNote']??'');
                $remark=trim($autoRejectReason.($managerNote?($autoRejectReason?' Manager note: ':'').$managerNote:''));
                $remark=substr($remark,0,255);

                if($status!=='Pending')$error='Only Pending requests can be rejected.';
                elseif(!$remark)$error='No automatic validation issue was detected. Please enter a Manager rejection reason.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Rejected',managerRemark=?,decisionDate=NOW() WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        if($b){$s=$c->prepare("UPDATE borrowings SET borrowingStatus='Cancelled' WHERE borrowID=?");$s->bind_param('s',$b['borrowID']);$s->execute();$s->close();}
                        logTx($c,$manager,'Reject',$id,"Rejected borrowing request for {$t['assetName']}. Reason: $remark");
                        notify($c,$t['requesterID'],'Borrowing Request Rejected',"Your request $id for {$t['assetName']} was rejected. Reason: $remark");
                        $c->commit();returnTo($backURL,['reviewDone'=>'reject']);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='start'){
                $pickupIssue=pickupIssue($c,$t,$b,$status,$id);
                if($pickupIssue)$error=$pickupIssue;
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='In Progress' WHERE transactionID=?");
                        $s->bind_param('s',$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Borrowed' WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();
                        assetStatus($c,$t['assetID'],$manager,'Reserved','Borrowed',"Asset picked up for $id");
                        logTx($c,$manager,'Assign',$id,"Pickup confirmed; borrowing started for {$t['assetName']}");
                        notify($c,$t['requesterID'],'Pickup Confirmed',"Pickup for {$t['assetName']} was confirmed. Your borrowing has started. Return by: ".d($b['expectedReturnDate']).'.');
                        $c->commit();
                        returnTo($backURL,['pickupDone'=>$id]);
                        exit();
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='cancel'){
                if($status!=='Approved'||!$b)$error='Only an approved reservation can be cancelled before pickup.';
                elseif(!$remark)$error='Please enter a cancellation reason.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Cancelled',managerRemark=? WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Cancelled' WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();
                        $currentProtected=$b&&!empty($b['borrowDate'])&&!empty($b['expectedReturnDate'])
                            &&date('Y-m-d')>=protectionDate(date('Y-m-d',strtotime($b['borrowDate'])))
                            &&date('Y-m-d')<=date('Y-m-d',strtotime($b['expectedReturnDate']));
                        if($t['availabilityStatus']==='Reserved'&&$currentProtected){
                            assetStatus($c,$t['assetID'],$manager,'Reserved','Available',"Cancelled protected reservation $id");
                        }
                        logTx($c,$manager,'Update',$id,"Cancelled reservation before pickup. Reason: $remark");
                        notify($c,$t['requesterID'],'Reservation Cancelled',"The reservation for transaction $id was cancelled before pickup. Reason: $remark");
                        $c->commit();go($id,$navParam);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }
        }

        $s=$c->prepare("SELECT sl.actionType,sl.description,sl.timeLogged,u.name userName FROM systemlog sl LEFT JOIN user u ON sl.userID=u.userID WHERE sl.refType='Transaction' AND sl.refID=? ORDER BY sl.timeLogged DESC");
        $s->bind_param('s',$id);$s->execute();$logs=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
    }
}catch(Throwable $z){error_log($z->getMessage());$error='Unable to process the transaction. Please try again.';}

if($t){
    $borrowed=$b&&!$b['actualReturnDate']&&in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true);
    $overdue=$borrowed&&$b['expectedReturnDate']&&date('Y-m-d',strtotime($b['expectedReturnDate']))<date('Y-m-d');
    $status=in_array($t['requestStatus'],['Completed','Rejected','Cancelled'],true)?$t['requestStatus']:($overdue?'Overdue':$t['requestStatus']);
    $blocked=($summary['active']??0)>=5||($summary['overdue']??0)>0;
    $schedule=scheduleCheck($conn,$t['assetID'],$id,$b);
    $approvalIssue=approvalIssue($t,$b,$blocked,$summary,$status,$schedule);
    $validationChecks=validationChecks($t,$b,$summary,$status,$schedule);
    $autoRejectReason=autoRejectReason($validationChecks);
    $pickupIssue=pickupIssue($conn,$t,$b,$status,$id);
    $pickupEarliest=$b&&!empty($b['borrowDate'])?date('Y-m-d',strtotime($b['borrowDate'])):'';
    $today=date('Y-m-d');
    $pickupDate=$b&&!empty($b['borrowDate'])?date('Y-m-d',strtotime($b['borrowDate'])):'';
    $returnDate=$b&&!empty($b['expectedReturnDate'])?date('Y-m-d',strtotime($b['expectedReturnDate'])):'';
    $protectionStarts=$pickupDate?protectionDate($pickupDate):'';
    $protectionActive=$status==='Approved'&&$protectionStarts&&$returnDate&&$today>=$protectionStarts&&$today<=$returnDate;
    $protectionPending=$status==='Approved'&&$protectionStarts&&$today<$protectionStarts;
    $requestPriorityLabel=$requestPriorityClass='';
    if($status==='Pending'&&$pickupDate){
        $tomorrow=date('Y-m-d',strtotime('+1 day'));
        if($pickupDate===$today){$requestPriorityLabel='URGENT — Pickup Today';$requestPriorityClass='urgent';}
        elseif($pickupDate===$tomorrow){$requestPriorityLabel='Pickup Tomorrow';$requestPriorityClass='tomorrow';}
        elseif($pickupDate<$today){$requestPriorityLabel='Pickup Date Passed';$requestPriorityClass='passed';}
    }
    $personLabel=in_array($status,['Pending','Rejected','Cancelled'],true)?'Requester':'Borrower';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Transaction Details - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css"><link rel="stylesheet" href="manager_mobile.css">
<style>
.tx-dialog{position:fixed;inset:0;margin:auto;width:min(760px,calc(100% - 32px));max-height:85vh;overflow:auto;border:1px solid #bfe4fb;border-radius:12px;padding:24px}
.tx-dialog-small{width:min(520px,calc(100% - 32px))}
.tx-dialog::backdrop{background:rgba(0,0,0,.55)}
.tx-dialog h2{margin-top:0}
#borrowerHistory h2{margin-bottom:8px}
#borrowerHistory>p{margin:0;color:#475569;line-height:1.5}
.tx-side-actions button:disabled{
    background:#d9dee5;
    border-color:#c7cdd4;
    color:#6f7782;
    cursor:not-allowed;
    opacity:1;
}
.tx-dialog .asset-form-row{align-items:start}
.tx-dialog .category-form-actions{margin-top:18px}
.tx-top{display:grid;grid-template-columns:minmax(0,1fr) 210px;gap:24px;align-items:start;margin-bottom:24px}
.tx-support{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
.tx-top .content-card,.tx-support .content-card,.tx-activity{margin:0}
.tx-profile .category-form-actions{justify-content:flex-start;margin-top:8px}
.tx-profile .management-filter-btn{padding:7px 12px;font-size:13px}
.tx-activity{padding:24px}
.tx-activity .card-header{margin:0 0 12px}
.tx-activity .table-wrapper{margin-top:0}
.tx-side-actions{display:flex;flex-direction:column;gap:12px}
.tx-side-actions form{margin:0}
.tx-side-actions a,.tx-side-actions button{width:100%;justify-content:center}
.tx-validation{margin:14px 0;padding:12px;border:1px solid #dbe7ef;border-radius:10px;background:#f8fbfd}
.tx-validation h3{margin:0 0 9px;font-size:14px}
.tx-validation-row{display:grid;grid-template-columns:1fr auto;gap:10px;padding:8px 0;border-top:1px solid #e8eef3}
.tx-validation-row:first-of-type{border-top:0}
.tx-validation-row strong{display:block;font-size:13px}
.tx-validation-row small{display:block;margin-top:2px;color:#64748b;line-height:1.35}
.tx-validation-state{align-self:start;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700}
.tx-validation-state.ok{background:#dcfce7;color:#166534}
.tx-validation-state.fail{background:#fee2e2;color:#991b1b}
.tx-reject-reason{margin:12px 0;padding:12px 14px;border:1px solid #fecaca;border-radius:8px;background:#fff1f2;color:#991b1b;line-height:1.45}
.tx-reject-pass{margin:12px 0;padding:10px 12px;border:1px solid #bbf7d0;border-radius:8px;background:#f0fdf4;color:#166534;font-size:13px}
.tx-auto-note{margin:6px 0 0;color:#64748b;font-size:12px;line-height:1.4}
.tx-priority-alert{margin-bottom:14px;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:700}
.tx-priority-alert.urgent{border:1px solid #fecaca;background:#fff1f2;color:#b91c1c}
.tx-priority-alert.tomorrow{border:1px solid #fed7aa;background:#fff7ed;color:#c2410c}
@media print{
@page{size:A4 landscape;margin:10mm}
.sidebar,.app-header,.breadcrumb,.management-heading p,.tx-side-actions,.tx-dialog,.flow-dialog,.tx-profile .category-form-actions{display:none!important}
.main-content{width:100%!important;margin-left:0!important}
.page-body{padding:0!important}
.management-heading{margin:0 0 10px!important}
.management-heading h1{font-size:20px!important;margin:0!important}
.alert,.asset-form-note{box-shadow:none!important}
.tx-top{display:block!important;margin-bottom:10px!important}
.tx-support{grid-template-columns:1fr 1fr!important;gap:10px!important;margin-bottom:10px!important}
.tx-top .content-card,.tx-support .content-card,.tx-activity{padding:12px!important;box-shadow:none!important;border:1px solid #cbd5e1!important;break-inside:avoid}
.category-info-card h2,.tx-activity h2{font-size:14px!important;margin:0 0 8px!important}
.category-info-fields{gap:5px!important}
.category-info-row{grid-template-columns:125px 1fr!important;gap:8px!important;font-size:10px!important}
.tx-activity .data-table th,.tx-activity .data-table td{padding:5px 7px!important;font-size:9px!important}
.tx-activity .table-wrapper{overflow:visible!important}
}
@media(max-width:850px){.tx-top,.tx-support{grid-template-columns:1fr}.tx-side-actions{flex-direction:row;flex-wrap:wrap}.tx-side-actions a,.tx-side-actions button{width:auto}}
</style>
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?>
<main class="page-body">

<section class="management-heading">
<div class="breadcrumb"><a href="<?=e($backURL)?>"><?=e($backLabel)?></a><span>›</span><span class="breadcrumb-current">Transaction Details</span></div>
<h1>Transaction Details</h1><p>Review the request, borrower background and borrowing progress.</p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<?php if(($_GET['updated']??'')==='1'):?><div class="alert alert-success">Transaction updated successfully.</div><?php endif;?>

<?php if($t):?>
<?php if($overdue):?>
<div class="alert alert-error"><strong>OVERDUE</strong> · Return was due <?=e(d($b['expectedReturnDate']))?></div>
<?php elseif($status==='Pending'&&$approvalIssue):?>
<div class="alert alert-error"><strong>Approval Blocked</strong> · <?=e(preg_replace('/^Approval blocked:\s*/i','',$approvalIssue))?></div>
<?php elseif($status==='Pending'&&$requestPriorityLabel):?>
<div class="tx-priority-alert <?=e($requestPriorityClass)?>"><?=e($requestPriorityLabel)?> · This request should be reviewed according to its requested pickup date. Normal approval validation still applies.</div>
<?php elseif($status==='Completed'):?>
<div class="alert alert-success"><strong>Completed</strong> · Asset returned <?=e(d($b['actualReturnDate']??null,true))?></div>
<?php elseif($status==='Approved'&&$t['availabilityStatus']==='Reserved'):?>
<div class="asset-form-note"><strong>7-Day Protection Active</strong><p>This asset is Reserved for this approved request. Pickup can be confirmed from <?=e(d($pickupDate))?> when the member physically collects the asset.</p></div>
<?php elseif($status==='Approved'&&$protectionPending):?>
<div class="asset-form-note"><strong>Future Reservation Confirmed</strong><p>The asset is not locked early. It remains operationally available until <?=e(d($protectionStarts))?>, when the scheduled protection process will change it to Reserved automatically.</p></div>
<?php elseif($status==='Approved'&&$pickupIssue):?>
<div class="asset-form-note"><strong>Reservation Protection Attention</strong><p><?=e($pickupIssue)?></p></div>
<?php elseif($status==='Approved'):?>
<div class="asset-form-note"><strong>Pickup Window Open</strong><p>The 7-day protection window is active. Pickup may be confirmed when the member physically collects the asset.</p></div>
<?php else:?>
<div class="asset-form-note"><strong>Status: <?=e($status)?></strong></div>
<?php endif;?>

<div class="tx-top">
<article class="content-card category-info-card">
<h2>Transaction Information</h2><div class="category-info-fields">
<?php foreach([
'Transaction ID'=>$id,'Request Date'=>d($t['requestDate'],true),
'Requested Pickup'=>d($b['borrowDate']??null),'Requested Return'=>d($b['expectedReturnDate']??null),
'Actual Return'=>d($b['actualReturnDate']??null,true),
'Borrowing Status'=>$b['borrowingStatus']??'-',
'Purpose / Notes'=>$t['requestMessage']?:'-','Manager Remark'=>$t['managerRemark']?:'-'
] as $k=>$v):?><div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div><?php endforeach;?>
</div></article>
<div class="tx-side-actions">
<button type="button" class="category-form-back" onclick="window.print()">Print Details</button>
<a href="<?=e($backURL)?>" class="category-form-back">Back</a>
<?php if($status==='Pending'):?>
<button type="button" class="category-form-reset" onclick="rejectBox.showModal()">Reject Request</button>
<button type="button" class="category-form-submit"
<?=$approvalIssue?'disabled title="'.e($approvalIssue).'"':''?>
onclick="approveBox.showModal()">Approve Reservation</button>
<?php elseif($status==='Approved'):?>
<button type="button" class="category-form-reset" onclick="cancelBox.showModal()">Cancel Reservation</button>
<button type="button" class="category-form-submit"
<?=$pickupIssue?'disabled title="'.e($pickupIssue).'"':''?>
onclick="pickupBox.showModal()">Confirm Pickup</button>
<?php endif;?>
</div>
</div>

<div class="tx-support">
<article class="content-card category-info-card">
<h2>Asset Information</h2><div class="category-info-fields">
<?php foreach([
'Asset ID'=>$t['assetID'],'Asset Name'=>$t['assetName'],'Category'=>$t['categoryName'],
'Condition'=>$t['assetCondition'],'Availability'=>$t['availabilityStatus'],'Owner'=>$t['ownerName'].' ('.$t['ownerID'].')'
] as $k=>$v):?><div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div><?php endforeach;?>
</div></article>
<section class="content-card tx-profile category-info-card">
<h2><?=e($personLabel)?> Profile</h2>
<div class="category-info-fields">
<div class="category-info-row"><strong>Member</strong><span><?=e($t['requesterName'].' ('.$t['requesterID'].')')?></span></div>
<div class="category-info-row"><strong>Email</strong><span><?=e($t['requesterEmail'])?></span></div>
<div class="category-info-row"><strong>EcoPoints</strong><span><?=e($summary['ecopoint']??$t['ecopoint'])?></span></div>
<div class="category-info-row"><strong>Active Borrowings</strong><span><?=e(($summary['active']??0).' / 5')?></span></div>
<div class="category-info-row"><strong>Current Overdue</strong><span><?=e($summary['overdue']??0)?></span></div>
</div>
<div class="category-form-actions">
<button type="button" class="management-filter-btn" onclick="borrowerHistory.showModal()">
<img src="../assets/icons/view.png" alt=""> View <?=e($personLabel)?> History
</button>
</div>
</section>
</div>

<section class="content-card tx-activity">
<div class="card-header"><h2>Transaction Activity<?= $logs?' ('.count($logs).')':''?></h2></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Date & Time</th><th>Action</th><th>Done By</th><th>Activity</th></tr></thead><tbody>
<?php if(!$logs):?><tr><td colspan="4" class="dashboard-empty-state">No activity recorded for this transaction yet.</td></tr>
<?php else:foreach($logs as $x):?><tr><td><?=e(d($x['timeLogged'],true))?></td><td><?=e($x['actionType'])?></td><td><?=e($x['userName']?:'System')?></td><td><?=e($x['description'])?></td></tr><?php endforeach;endif;?>
</tbody></table></div>
</section>

<dialog id="borrowerHistory" class="content-card tx-dialog">
<h2><?=e($personLabel)?> History</h2>
<p>Review this member's borrowing background and recent activity.</p>

<div class="table-wrapper">
<table class="data-table">
<thead>
<tr><th>Requester Detail</th><th>Information</th></tr>
</thead>
<tbody>
<tr><th>Member</th><td><?=e($t['requesterName'].' ('.$t['requesterID'].')')?></td></tr>
<tr><th>Email</th><td><?=e($t['requesterEmail'])?></td></tr>
<tr><th>EcoPoints</th><td><?=e($summary['ecopoint']??$t['ecopoint'])?></td></tr>
<tr><th>Active Borrowings</th><td><?=e(($summary['active']??0).' / 5')?></td></tr>
<tr><th>Current Overdue</th><td><?=e($summary['overdue']??0)?></td></tr>
<tr><th>Completed Borrowings</th><td><?=e($summary['completed']??0)?></td></tr>
<tr><th>Previous Late Returns</th><td><?=e($summary['late']??0)?></td></tr>
<tr><th>Total Requests</th><td><?=e($summary['requests']??0)?></td></tr>
</tbody>
</table>
</div>

<div class="card-header"><h3>Recent Borrowings</h3></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Transaction</th><th>Asset</th><th>Due</th><th>Returned</th><th>Status</th></tr></thead><tbody>
<?php if(!$past):?><tr><td colspan="5" class="dashboard-empty-state">No previous borrowing records.</td></tr>
<?php else:foreach($past as $x):?><tr><td><?=e($x['transactionID'])?></td><td><?=e($x['assetName'])?></td><td><?=e(d($x['expectedReturnDate']))?></td><td><?=e(d($x['actualReturnDate']))?></td><td><?=e($x['borrowingStatus'])?></td></tr><?php endforeach;endif;?>
</tbody></table></div>

<div class="card-header"><h3>Recent EcoPoint Activity</h3></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Date</th><th>Points</th><th>Source</th><th>Reference</th></tr></thead><tbody>
<?php if(!$eco):?><tr><td colspan="4" class="dashboard-empty-state">No EcoPoint activity.</td></tr>
<?php else:foreach($eco as $x):?><tr><td><?=e(d($x['transactionDate']))?></td><td><?=e($x['points'])?></td><td><?=e($x['sourceType'])?></td><td><?=e($x['referenceID']?:'-')?></td></tr><?php endforeach;endif;?>
</tbody></table></div>

<form method="dialog" class="category-form-actions"><button class="category-form-back">Close</button></form>
</dialog>

<?php if($status==='Pending'):?>
<dialog id="approveBox" class="flow-dialog">
<h2>Confirm Request Approval</h2>
<p>Approve this request and confirm the requested borrowing period for this member?</p>
<?php if($b&&!empty($b['borrowDate'])):$approveProtection=protectionDate(date('Y-m-d',strtotime($b['borrowDate'])));?>
<div class="asset-form-note" style="margin-bottom:14px"><strong>7-Day Reservation Rule</strong><p><?=date('Y-m-d')>=$approveProtection?'The pickup is already inside the protection window, so the asset will be Reserved immediately after approval.':'The asset will remain available before '.e(d($approveProtection)).'. A scheduled process will reserve it automatically when the 7-day protection window begins.'?></p></div>
<?php endif;?>
<div class="flow-dialog-summary">
<div><strong>Transaction ID</strong><span><?=e($id)?></span></div>
<div><strong>Asset</strong><span><?=e($t['assetName'])?></span></div>
<div><strong>Requester</strong><span><?=e($t['requesterName'].' ('.$t['requesterID'].')')?></span></div>
<div><strong>Pickup Date</strong><span><?=e(d($b['borrowDate']??null))?></span></div>
<div><strong>Return Date</strong><span><?=e(d($b['expectedReturnDate']??null))?></span></div>
<div><strong>Protection Begins</strong><span><?=e($b&&!empty($b['borrowDate'])?d(protectionDate(date('Y-m-d',strtotime($b['borrowDate'])))):'-')?></span></div>
</div>
<form method="POST" class="flow-dialog-actions">
<input type="hidden" name="action" value="approve">
<button type="button" class="category-form-back" onclick="approveBox.close()">No</button>
<button class="category-form-submit">Yes, Approve Reservation</button>
</form>
</dialog>

<dialog id="rejectBox" class="flow-dialog">
<h2>Reject Request</h2>
<p>CirculAPU automatically validates the request against borrowing, scheduling and asset eligibility rules to support the Manager's decision.</p>

<div class="tx-validation">
<h3>System Validation</h3>
<?php foreach($validationChecks as $check):?>
<div class="tx-validation-row">
<div>
<strong><?=e($check['label'])?></strong>
<small><?=e($check['detail'])?></small>
</div>
<span class="tx-validation-state <?=$check['ok']?'ok':'fail'?>"><?=$check['ok']?'Passed':'Issue'?></span>
</div>
<?php endforeach;?>
</div>

<form method="POST">
<input type="hidden" name="action" value="reject">

<?php if($autoRejectReason):?>
<label>System-Generated Rejection Reason</label>
<div class="tx-reject-reason"><?=e($autoRejectReason)?></div>
<p class="tx-auto-note">This reason will be sent to the requester automatically.</p>
<?php else:?>
<div class="tx-reject-pass"><strong>All system validations passed.</strong> Enter a Manager rejection reason only if you still need to reject this request.</div>
<?php endif;?>

<div class="asset-form-row asset-form-textarea" style="grid-template-columns:1fr">
<label for="managerNote"><?=$autoRejectReason?'Additional Manager Note (Optional)':'Manager Rejection Reason *'?></label>
<textarea id="managerNote" name="managerNote" maxlength="<?=$autoRejectReason?'120':'255'?>" rows="3"
          placeholder="<?=$autoRejectReason?'Add any extra explanation for the requester if needed.':'Enter the reason for rejecting this request.'?>"
          <?=$autoRejectReason?'':'required'?>></textarea>
</div>

<div class="flow-dialog-actions">
<button type="button" class="category-form-back" onclick="rejectBox.close()">Cancel</button>
<button class="category-form-submit">Yes, Reject</button>
</div>
</form>
</dialog>
<?php elseif($status==='Approved'):?>
<dialog id="pickupBox" class="flow-dialog">
<h2>Confirm Asset Pickup</h2>
<p>Confirm only when the member physically collects the asset. Borrowing will start immediately.</p>

<div class="flow-dialog-summary">
<div><strong>Transaction ID</strong><span><?=e($id)?></span></div>
<div><strong>Asset</strong><span><?=e($t['assetName'])?></span></div>
<div><strong>Borrower</strong><span><?=e($t['requesterName'].' ('.$t['requesterID'].')')?></span></div>
<div><strong>Requested Pickup</strong><span><?=e(d($b['borrowDate']??null))?></span></div>
<div><strong>Earliest Confirmation</strong><span><?=e($pickupEarliest?d($pickupEarliest):'-')?></span></div>
<div><strong>Return Date</strong><span><?=e(d($b['expectedReturnDate']??null))?></span></div>
<div><strong>Current Asset Status</strong><span><?=e($t['availabilityStatus'])?></span></div>
</div>

<form method="POST" class="flow-dialog-actions">
<input type="hidden" name="action" value="start">
<button type="button" class="category-form-back" onclick="pickupBox.close()">No</button>
<button class="category-form-submit">Yes, Confirm Pickup</button>
</form>
</dialog>

<dialog id="cancelBox" class="flow-dialog">
<h2>Cancel Reservation</h2>
<p>Enter a clear reason explaining why this approved reservation needs to be cancelled before pickup.</p>

<form method="POST">
<input type="hidden" name="action" value="cancel">

<div class="asset-form-row asset-form-textarea" style="grid-template-columns:1fr">
<label for="cancelReason">Cancellation Reason *</label>
<textarea id="cancelReason" name="managerRemark" maxlength="255" rows="4"
          placeholder="Example: The reservation needs to be cancelled because the asset is no longer available for pickup."
          required></textarea>
</div>

<div class="flow-dialog-actions">
<button type="button" class="category-form-back" onclick="cancelBox.close()">Cancel</button>
<button class="category-form-submit">Yes, Cancel Reservation</button>
</div>
</form>
</dialog>
<?php endif;?>

<?php endif;?>
</main>
</div></div>
</body></html>
