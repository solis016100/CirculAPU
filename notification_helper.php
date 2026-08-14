<?php
function notificationID(mysqli $conn): string {
    $row=$conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(notificationID,2) AS UNSIGNED)),0)+1 n FROM notification")->fetch_assoc();
    return sprintf('N%03d',(int)$row['n']);
}

function notifyUser(mysqli $conn,string $userID,string $title,string $content): void {
    $id=notificationID($conn);
    $s=$conn->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$id,$userID,$title,$content);$s->execute();$s->close();
}

function notifyRole(mysqli $conn,string $role,string $title,string $content): void {
    $s=$conn->prepare("SELECT userID FROM user WHERE role=? AND status='Active'");
    $s->bind_param('s',$role);$s->execute();$r=$s->get_result();
    while($u=$r->fetch_assoc()) notifyUser($conn,$u['userID'],$title,$content);
    $s->close();
}
