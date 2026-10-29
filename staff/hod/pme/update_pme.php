<?php
include "../../../dbconn.php";

$id = (int) $_POST['id'];
$staffname = $_POST['staffname'];
$staffno = $_POST['staffno'];
$department = $_POST['department'];
$training_title = $_POST['training_title'];
$from_date = $_POST['from_date'];
$to_date = $_POST['to_date'];
$ojt = $_POST['ojt'];
$level_rating = $_POST['level_rating'];
$level_percent = $_POST['level_percent'];
$level_remark = trim($_POST['level_remark']);
$level_rating2 = $_POST['level_rating2'];
$level_percent2 = $_POST['level_percent2'];
$level_remark2 = trim($_POST['level_remark2']);
$behavioral_rating = $_POST['behavioral_rating'];
$behavioral_percent = $_POST['behavioral_percent'];
$behavioral_remark = trim($_POST['behavioral_remark']);
$result_rating = $_POST['result_rating'];
$result_percent = $_POST['result_percent'];
$result_remark = trim($_POST['result_remark']);

date_default_timezone_set('Asia/Kuala_Lumpur');
$pme_created = date('Y-m-d H:i:s'); 

$status = 'approved';

// Prepared statement so remarks containing quotes (e.g. "staff's") don't break the SQL
$sql = "UPDATE pme SET
    staffname=?,
    staffno=?,
    department=?,
    training_title=?,
    from_date=?,
    to_date=?,
    ojt=?,
    level_rating=?,
    level_percent=?,
    level_remark=?,
    level_rating2=?,
    level_percent2=?,
    level_remark2=?,
    behavioral_rating=?,
    behavioral_percent=?,
    behavioral_remark=?,
    result_rating=?,
    result_percent=?,
    result_remark=?,
    pme_created=?,
    status=?
WHERE id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssssssssssssssssssssi',
    $staffname, $staffno, $department, $training_title, $from_date, $to_date, $ojt,
    $level_rating, $level_percent, $level_remark,
    $level_rating2, $level_percent2, $level_remark2,
    $behavioral_rating, $behavioral_percent, $behavioral_remark,
    $result_rating, $result_percent, $result_remark,
    $pme_created, $status, $id);
$stmt->execute();


$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;


header("Location: ../dashboard.php?page=$page#pme-list");
exit();
?>
