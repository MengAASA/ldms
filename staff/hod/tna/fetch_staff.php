<?php
include "../../../dbconn.php";
include_once __DIR__ . '/../../../planning_year.php';

if ($_POST["action"] == "load_staff") {
    $output = array();
    $userid = $_POST["userid"];

    $sql = "SELECT 
					user.id,
					staffno,
					staffname,
					designation,
					usertype,
					user.section,
					IFNULL(MAX(tablea.status), 'not') AS status
				FROM user
				LEFT JOIN (
					SELECT DISTINCT(userid) AS userid, status 
					FROM tna 
					WHERE year = '" . ldmsPlanningYear() . "'
				) tablea ON user.id = tablea.userid
				WHERE hodid = '$userid' 
				  AND designation = 'NON EXECUTIVE' 
				  AND (usertype IS NULL OR usertype != '')
				  AND UPPER(COALESCE(user.status, '')) <> 'RESIGN'
				GROUP BY user.id, staffno, staffname, designation, usertype, user.section

				UNION

				SELECT 
					user.id,
					staffno,
					staffname,
					designation,
					usertype,
					user.section,
					IFNULL(MAX(tablea.status), 'not') AS status
				FROM user
				LEFT JOIN (
					SELECT DISTINCT(userid) AS userid, status 
					FROM tna 
					WHERE year = '" . ldmsPlanningYear() . "'
				) tablea ON user.id = tablea.userid
				WHERE hodid = '$userid' 
				  AND designation IN ('MANAGER (AM/HOS & ABOVE)', 'EXECUTIVE') 
				  AND COALESCE(usertype, '') != 'HOD'
				  AND UPPER(COALESCE(user.status, '')) <> 'RESIGN'
				GROUP BY user.id, staffno, staffname, designation, usertype, user.section;
				";

    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        if ($row['status'] == 'not') {
            $status = '<span class="label label-pill label-info">NOT SUBMITTED</span>';
        } else if ($row['status'] == "1") { // handle both NULL and empty
            $status = '<span class="label label-pill label-warning">WAITING APPROVAL</span>';
        } else if ($row['status'] == 'APPROVE') {
            $status = '<span class="label label-pill label-success">TNA APPROVED</span>';
        }

        $buttonview = '<button type="submit" id="' . $row['id'] . '" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button>';

        $output[] = array(
            'id' => $row['id'],
            'staffno' => $row['staffno'],
            'staffname' => $row['staffname'],
            'section' => $row['section'],
            'status' => $status,
            'btnedit' => $buttonview
        );
    }

    echo json_encode($output);
} else if ($_POST["action"] == "load_grade") {
    $output = array();
    $userid = $_POST["userid"];

    $sql = "select department from user where id = '$userid';";
    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        $department = $row['department'];
    }

    // Headcount = non-executives the grade TNA covers (usertype ''). Staff with
    // a usertype (e.g. MAIN clerks) fill their own TNA and are already in the
    // staff list, so they aren't counted again here. Left join so a grade TNA
    // that was filled still shows (and can be approved) with headcount 0.
    $sql = "select tablea.grade,id,status,coalesce(headcount,0) as headcount from (select distinct(grade) as grade,CONCAT(grade, '/', department) as id,status from tna where department = '$department' and year = '" . ldmsPlanningYear() . "' and userid is null and grade is not null)tablea left join (select grade,count(*) as headcount from user where department = '$department' and grade != 0 and designation = 'NON EXECUTIVE' and usertype = '' and UPPER(COALESCE(status, '')) <> 'RESIGN' group by grade)tableb on tablea.grade = tableb.grade";
    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        if ($row['status'] == '' || $row['status'] == 1) {
            $status = '<span class="label label-pill label-warning">WAITING APPROVAL</span>';
            $buttonview = '<button type="submit" id="' . $row['id'] . '" class="btn btn-info btn-sm viewgrade" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button>';
        } else if ($row['status'] == 'APPROVE') {
            $status = '<span class="label label-pill label-success">TNA APPROVED</span>';
            $buttonview = '<button type="submit" id="' . $row['id'] . '" class="btn btn-info btn-sm viewgrade" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button>';
        }

        $output[] = array(
            'id' => $row['id'],
            'grade' => $row['grade'],
            'status' => $status,
            'headcount' => $row['headcount'],
            'btnedit' => $buttonview
        );



    }
    echo json_encode($output);
} else if ($_POST["action"] == "gettna") {
    $userid = $_POST["userid"];

    // $sql = "select count(*) as tnarecord,status from tna where userid = '$userid' and year = '" . ldmsPlanningYear() . "';";
    $sql = "SELECT status, COUNT(*) AS tnarecord FROM tna WHERE userid = '$userid' AND year = '" . ldmsPlanningYear() . "' GROUP BY status;";

    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        $tna = $row['tnarecord'];
        $status = $row['status'];
    }

    $sql1 = "select ifnull(sum(totalday*totalhour),0) as sumhour from (select ((DATEDIFF(enddate, startdate)) + 1) as totalday,ROUND((TIME_TO_SEC(TIMEDIFF(endtime,starttime))/60)/60,2) as totalhour from training join participation on training.id = trainingid where userid = '$userid' and year(startdate) = year(curdate()))tablea;";
    $query1 = mysqli_query($conn, $sql1);
    while ($row1 = mysqli_fetch_assoc($query1)) {
        $publichour = $row1['sumhour'];
    }

    $sql2 = "select ifnull(sum(totalday*totalhour),0) as sumhour from (select ((DATEDIFF(enddate, startdate)) + 1) as totalday,ROUND((TIME_TO_SEC(TIMEDIFF(endtime,starttime))/60)/60,2) as totalhour from ojt join participateojt on ojt.id = ojtid where userid = '$userid' and year(startdate) = year(curdate()))tablea;";
    $query2 = mysqli_query($conn, $sql2);
    while ($row2 = mysqli_fetch_assoc($query2)) {
        $ojthour = $row2['sumhour'];
    }

    $sql3 = "select staffname from user where id = '$userid'";
    $query3 = mysqli_query($conn, $sql3);
    while ($row3 = mysqli_fetch_assoc($query3)) {
        $staffname = $row3['staffname'];
    }

    $output[] = array(
        'tna' => $tna,
        'status' => $status,
        'publichour' => $publichour,
        'ojthour' => $ojthour,
        'staffname' => $staffname,
        'totalhour' => $publichour + $ojthour,
    );

    echo json_encode($output);
} else if ($_POST["action"] == "getlisttna") {
    $userid = $_POST["userid"];

    $sql = "select * from tna where userid = '$userid' and year = '" . ldmsPlanningYear() . "';";
    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        $output[] = array(
            'task' => $row['task'],
            'training' => $row['training'],
            'othertr' => $row['othertr'],
            'targetskill' => $row['targetskill'],
            'currentskill' => $row['currentskill'],
            'gap' => $row['gap'],
            'trainingtype' => $row['trainingtype'],
            'monthapply' => $row['monthapply'],
            'section' => $row['section'],
            'status' => $row['status'],
        );
    }

    echo json_encode($output);
} else if ($_POST["action"] == "gettnagrade") {
    $userid = explode('/', $_POST["userid"], 2);
    $grade = $userid[0];
    $department = $userid[1];

    // $sql = "select count(*) as tnarecord,status from tna where department = '$department' and grade = '$grade' and year = '" . ldmsPlanningYear() . "';";
    $sql = "SELECT status, COUNT(*) AS tnarecord FROM tna WHERE department = '$department' and grade = '$grade' AND year = '" . ldmsPlanningYear() . "' GROUP BY status;";

    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        $tna = $row['tnarecord'];
        $status = $row['status'];
    }

    $output[] = array(
        'tna' => $tna,
        'status' => $status,
    );

    echo json_encode($output);
} else if ($_POST["action"] == "getlisttnagrade") {
    $userid = explode('/', $_POST["userid"], 2);
    $grade = $userid[0];
    $department = $userid[1];

    $sql = "select * from tna where grade = '$grade' and department = '$department' and year = '" . ldmsPlanningYear() . "';";
    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        $output[] = array(
            'task' => $row['task'],
            'training' => $row['training'],
            'othertr' => $row['othertr'],
            'targetskill' => $row['targetskill'],
            'currentskill' => $row['currentskill'],
            'gap' => $row['gap'],
            'trainingtype' => $row['trainingtype'],
            'monthapply' => $row['monthapply'],
            'section' => $row['section'],
            'status' => $row['status'],
        );
    }

    echo json_encode($output);
}
?>