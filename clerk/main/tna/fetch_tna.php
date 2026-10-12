<?php
    include "../../../dbconn.php";
include_once __DIR__ . '/../../../planning_year.php';

    if($_POST["action"] == "load_staff"){
        $output= array();
        $userid = $_POST["userid"];

        $sql = "select department from user where id = '$userid';";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $department = $row['department'];
        }

        $sql = "select user.id,staffno,staffname,user.section,ifnull(tablea.status,'not') as status from user left join (select distinct(userid) as userid,status from tna where year = '" . ldmsPlanningYear() . "')tablea on user.id = tablea.userid where department = '$department' and user.id != '$userid' and UPPER(COALESCE(user.status, '')) <> 'RESIGN'";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            if ($row['status'] == 'not') {
                $status = '<span class="label label-pill label-info">NOT SUBMITTED</span>';
                $buttonview = '';
            }else if ($row['status'] == ''){
                $status = '<span class="label label-pill label-warning">WAITING APPROVAL</span>';
                $buttonview = '<button type="submit" id="'.$row['id'].'" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> View Tna</button>';
            }else if ($row['status'] == 'APPROVE'){
                $status = '<span class="label label-pill label-success">TNA APPROVED</span>';
                $buttonview = '<button type="submit" id="'.$row['id'].'" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> View Tna</button>';
            }

            $output[]= array(
                'id' => $row['id'],
                'staffno' => $row['staffno'],
                'staffname' => $row['staffname'],
                'section' => $row['section'],
                'status' => $status,
                'btnedit' => $buttonview
            ); 
        }
        echo json_encode($output);
    }else if($_POST["action"] == "gettna"){
        $userid = $_POST["userid"];

        // $sql = "select count(*) as tnarecord,status from tna where userid = '$userid' and year = '" . ldmsPlanningYear() . "';";
		$sql = "SELECT status, COUNT(*) AS tnarecord FROM tna WHERE userid = '$userid' AND year = '" . ldmsPlanningYear() . "' GROUP BY status;";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $tna = $row['tnarecord'];
            $status = $row['status'];
        }

        $sql1 = "select ifnull(sum(totalday*totalhour),0) as sumhour from (select ((DATEDIFF(enddate, startdate)) + 1) as totalday,ROUND((TIME_TO_SEC(TIMEDIFF(endtime,starttime))/60)/60,2) as totalhour from training join participation on training.id = trainingid where userid = '$userid')tablea;";
        $query1 = mysqli_query($conn,$sql1);
        while($row1 = mysqli_fetch_assoc($query1))
        {
            $publichour = $row1['sumhour'];
        }

        $sql2 = "select ifnull(sum(totalday*totalhour),0) as sumhour from (select ((DATEDIFF(enddate, startdate)) + 1) as totalday,ROUND((TIME_TO_SEC(TIMEDIFF(endtime,starttime))/60)/60,2) as totalhour from ojt join participateojt on ojt.id = ojtid where userid = '$userid')tablea;";
        $query2 = mysqli_query($conn,$sql2);
        while($row2 = mysqli_fetch_assoc($query2))
        {
            $ojthour = $row2['sumhour'];
        }

        $sql3 = "select staffname from user where id = '$userid'";
        $query3 = mysqli_query($conn,$sql3);
        while($row3 = mysqli_fetch_assoc($query3))
        {
            $staffname = $row3['staffname'];
        }

        $output[]= array(
            'tna' => $tna,
            'status' => $status,
            'publichour' => $publichour,
            'ojthour' => $ojthour,
            'staffname' => $staffname,
        );

        echo json_encode($output);
    }else if($_POST["action"] == "getlisttna"){
        $userid = $_POST["userid"];
        
        $sql = "select * from tna where userid = '$userid' and year = '" . ldmsPlanningYear() . "';";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $output[]= array(
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
    }else if($_POST["action"] == "gettnagrade"){
        $userid = explode('/',$_POST["userid"], 2);
        $grade = $userid[0];
        $department = $userid[1];

        // $sql = "select count(*) as tnarecord,status from tna where department = '$department' and grade = '$grade' and year = '" . ldmsPlanningYear() . "';";
        $sql = "SELECT status, COUNT(*) AS tnarecord FROM tna WHERE department = '$department' and grade = '$grade' AND year = '" . ldmsPlanningYear() . "' GROUP BY status;";

        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $tna = $row['tnarecord'];
            $status = $row['status'];
        }

        $output[]= array(
            'tna' => $tna,
            'status' => $status,
        );

        echo json_encode($output);
    }else if($_POST["action"] == "getlisttnagrade"){
        $userid = explode('/',$_POST["userid"], 2);
        $grade = $userid[0];
        $department = $userid[1];
        
        $sql = "select * from tna where grade = '$grade' and department = '$department' and year = '" . ldmsPlanningYear() . "';";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $output[]= array(
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