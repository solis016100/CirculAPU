<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') { header('Location: ../login.php'); exit(); }

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function nextID(mysqli $c,string $t,string $col,string $p): string {
    $n=(int)$c->query("SELECT COALESCE(MAX(CAST(SUBSTRING($col,4) AS UNSIGNED)),0)+1 n FROM $t")->fetch_assoc()['n'];
    return sprintf($p.'%03d',$n);
}

$manager=$_SESSION['user_id'];$id=trim($_GET['assetID']??'');$back=trim($_GET['back']??'');$source=$_GET['source']??'assets';$backURL=($source==='reports'?'manager_reports.php':'manager_assets.php').($back?'?'.$back:'');$navParam=($source==='reports'?'&source=reports':'').($back?'&back='.rawurlencode($back):'');
if(!$id){header('Location: manager_assets.php');exit();}

$conditions=['New','Like New','Good','Fair','Poor'];$asset=null;$categories=[];$error='';$dbError='';$newFile='';

try{
    $c=$conn;$c->set_charset('utf8mb4');

    $s=$c->prepare("
        SELECT a.*,c.categoryName,u.name ownerName
        FROM assets a JOIN categories c ON a.categoryID=c.categoryID JOIN user u ON a.ownerID=u.userID
        WHERE a.assetID=? LIMIT 1");
    $s->bind_param('s',$id);$s->execute();$asset=$s->get_result()->fetch_assoc();$s->close();

    if(!$asset)$dbError='The selected asset record could not be found.';
    elseif($asset['listingStatus']==='Pending Review'){header('Location: review_asset_submission.php?assetID='.rawurlencode($id).$navParam);exit();}
    elseif($asset['listingStatus']==='Removed')$dbError='Removed assets cannot be edited.';
    else{
        $s=$c->prepare("SELECT categoryID,categoryName FROM categories WHERE status='Active' OR categoryID=? ORDER BY categoryName");
        $s->bind_param('s',$asset['categoryID']);$s->execute();$categories=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

        $locked=true;

        if($_SERVER['REQUEST_METHOD']==='POST'){
            $name=trim($_POST['assetName']??'');$category=trim($_POST['categoryID']??'');
            $condition=$asset['assetCondition'];
            $description=trim($_POST['description']??'');

            if(!$name)$error='Asset Name is required.';
            elseif(strlen($name)>150)$error='Asset Name must not exceed 150 characters.';
            elseif(strlen($description)>255)$error='Description must not exceed 255 characters.';
            elseif(!in_array($condition,$conditions,true))$error='Please select a valid asset condition.';

            if(!$error){
                $s=$c->prepare("SELECT 1 FROM categories WHERE categoryID=? AND (status='Active' OR categoryID=?) LIMIT 1");
                $s->bind_param('ss',$category,$asset['categoryID']);$s->execute();
                if(!$s->get_result()->fetch_row())$error='Please select a valid category.';
                $s->close();
            }

            $image=$asset['imageURL'];
            if(!$error&&isset($_FILES['assetImage'])&&$_FILES['assetImage']['error']!==UPLOAD_ERR_NO_FILE){
                $f=$_FILES['assetImage'];
                if($f['error']!==UPLOAD_ERR_OK)$error='Unable to upload the selected image.';
                elseif($f['size']>5*1024*1024)$error='Image size must not exceed 5MB.';
                else{
                    $info=getimagesize($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png'];
                    if(!$info||!isset($types[$info['mime']]))$error='Only JPG and PNG images are allowed.';
                    else{
                        $dir=dirname(__DIR__).'/assets/managerfile/';
                        if(!is_dir($dir)&&!mkdir($dir,0775,true))$error='Unable to create the image upload folder.';
                        if(!$error){
                            $file=strtolower($id).'_'.time().'.'.$types[$info['mime']];$newFile=$dir.$file;
                            if(!move_uploaded_file($f['tmp_name'],$newFile))$error='Unable to save the uploaded image.';
                            else $image='assets/managerfile/'.$file;
                        }
                    }
                }
            }

            if(!$error){
                $c->begin_transaction();
                try{
                    $s=$c->prepare("UPDATE assets SET categoryID=?,assetName=?,description=?,assetCondition=?,imageURL=?,updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
                    $s->bind_param('ssssss',$category,$name,$description,$condition,$image,$id);$s->execute();$s->close();

                    $log=nextID($c,'systemlog','logID','LOG');$text="Updated asset information: $name";
                    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,'Update','Asset',?,?)");
                    $s->bind_param('ssss',$log,$manager,$id,$text);$s->execute();$s->close();

                    $c->commit();
                    header('Location: manager_asset_view.php?assetID='.rawurlencode($id).$navParam.'&updated=1');exit();
                }catch(Throwable $z){
                    $c->rollback();if($newFile&&file_exists($newFile))unlink($newFile);throw $z;
                }
            }

            $asset['assetName']=$name;$asset['categoryID']=$category;$asset['description']=$description;
            if(!$locked)$asset['assetCondition']=$condition;
        }
    }
}catch(Throwable $z){error_log($z->getMessage());$dbError='Unable to process the asset information. Please try again.';}

$locked=(bool)$asset;
$imagePath='';
if($asset&&!empty($asset['imageURL'])){
    $url=str_replace('\\','/',trim($asset['imageURL']));
    $imagePath=preg_match('/^https?:\/\//i',$url)?$url:'../'.ltrim(preg_replace('#^(\.\./)+#','',$url),'/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Edit Asset - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css?v=<?=filemtime(__DIR__.'/manager_style.css')?>"><link rel="stylesheet" href="manager_mobile.css">
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?>
<main class="page-body">

<section class="management-heading">
<div class="breadcrumb"><a href="<?=e($backURL)?>">Asset Management</a><span>›</span><a href="manager_asset_view.php?assetID=<?=e($id)?><?=e($navParam)?>">Asset Details</a><span>›</span><span class="breadcrumb-current">Edit Asset</span></div>
<h1>Edit Asset Information</h1><p>Update listing information while preserving transaction, review and verification status.</p>
</section>

<?php if($dbError):?>
<div class="dashboard-error-message"><?=e($dbError)?></div>
<a href="<?=e($backURL)?>" class="management-create-btn">Back to Asset Management</a>
<?php elseif($asset):?>
<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<div class="asset-form-note"><strong>Condition Protection</strong><p>Asset condition is controlled through the review/verification workflow and Return Inspection. It cannot be changed from general Edit Asset.</p></div>

<section class="content-card asset-form-card">
<form method="POST" enctype="multipart/form-data" id="editAssetForm">
<div class="asset-form-layout">
<div class="asset-form-fields">

<div class="asset-form-row"><label>Asset ID</label><input value="<?=e($asset['assetID'])?>" readonly></div>
<div class="asset-form-row"><label for="assetName">Asset Name *</label><input id="assetName" name="assetName" maxlength="150" value="<?=e($asset['assetName'])?>" required></div>

<div class="asset-form-row"><label for="categoryID">Category *</label>
<select id="categoryID" name="categoryID" required>
<?php foreach($categories as $x):?><option value="<?=e($x['categoryID'])?>" <?=$asset['categoryID']===$x['categoryID']?'selected':''?>><?=e($x['categoryName'])?></option><?php endforeach;?>
</select></div>

<div class="asset-form-row"><label for="assetCondition">Condition</label>
<input id="assetCondition" value="<?=e($asset['assetCondition'])?>" readonly>
</div>

<div class="asset-form-row asset-form-textarea"><label for="description">Description</label><textarea id="description" name="description" maxlength="255" rows="4"><?=e($asset['description']??'')?></textarea></div>
<div class="asset-form-row"><label>Availability</label><input value="<?=e($asset['availabilityStatus'])?>" readonly></div>
<div class="asset-form-row"><label>Submitted By</label><input value="<?=e($asset['ownerName'].' ('.$asset['ownerID'].')')?>" readonly></div>
<div class="asset-form-row"><label>Listing Status</label><input value="<?=e($asset['listingStatus'])?>" readonly></div>
<div class="asset-form-row"><label>Verification Status</label><input value="<?=e($asset['verificationStatus'])?>" readonly></div>
<div class="asset-form-row"><label>Date Listed</label><input value="<?=e(date('d M Y, h:i A',strtotime($asset['createdDate'])))?>" readonly></div>
</div>

<aside class="asset-form-side">
<div class="asset-form-image-section"><h2>Current Image</h2>
<?php if($imagePath):?><div class="asset-form-image-preview"><img src="<?=e($imagePath)?>" alt="<?=e($asset['assetName'])?>"></div><p><?=e(basename($asset['imageURL']))?></p>
<?php else:?><div class="asset-image-empty">No image uploaded</div><?php endif;?>
</div>

<div class="asset-form-upload"><label for="assetImage">Replace Image</label><input type="file" id="assetImage" name="assetImage" accept=".jpg,.jpeg,.png,image/jpeg,image/png"><small>Optional. JPG or PNG only, maximum 5MB.</small></div>

<div class="asset-form-note"><strong>Important</strong><p>Availability, Listing Status and Verification Status are controlled by their proper workflows and cannot be changed here.</p></div>

<div class="asset-form-actions">
<button type="button" class="asset-form-submit" onclick="openEditAssetConfirm()"><img src="../assets/icons/edit.png" alt=""> Save Changes</button>
<button type="reset" class="asset-form-reset"><img src="../assets/icons/reset.png" alt=""> Reset</button>
<a href="manager_asset_view.php?assetID=<?=e($id)?><?=e($navParam)?>" class="asset-form-back">Back</a>
</div>
</aside>

</div>
</form>
</section>

<dialog id="editAssetConfirm" class="flow-dialog">
<h2>Confirm Asset Update</h2>
<p>Save these changes to this asset listing?</p>

<div class="flow-dialog-summary">
<div><strong>Asset ID</strong><span><?=e($asset['assetID'])?></span></div>
<div><strong>Asset Name</strong><span id="confirmAssetName"></span></div>
<div><strong>Category</strong><span id="confirmAssetCategory"></span></div>
<div><strong>Condition</strong><span><?=e($asset['assetCondition'])?></span></div>
<div><strong>Description</strong><span id="confirmAssetDescription"></span></div>
</div>

<div class="flow-dialog-actions">
<button type="button" class="category-form-back" onclick="editAssetConfirm.close()">No</button>
<button type="button" class="category-form-submit" onclick="editAssetForm.submit()">Yes, Save Changes</button>
</div>
</dialog>

<script>
const editAssetForm=document.getElementById('editAssetForm');
function openEditAssetConfirm(){
    if(!editAssetForm.checkValidity()){editAssetForm.reportValidity();return}
    confirmAssetName.textContent=editAssetForm.assetName.value.trim();
    confirmAssetCategory.textContent=editAssetForm.categoryID.options[editAssetForm.categoryID.selectedIndex].text;
    confirmAssetDescription.textContent=editAssetForm.description.value.trim()||'-';
    editAssetConfirm.showModal();
}
</script>
<?php endif;?>

</main>
</div></div>
</body></html>
