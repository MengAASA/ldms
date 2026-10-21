<?php
    include "../../dbconn.php";
    include_once __DIR__ . '/../../planning_year.php';

    if($_POST["action"] == "load_tni"){
        $output= array();
        
        $sql = "select id,staffname,tnia.* from user join (select department,count(*) as tnicount from " . ldmsPlanningYearTable('tni') . " tni group by department)tnia on user.department = tnia.department where usertype = 'HOD' and UPPER(COALESCE(user.status, '')) <> 'RESIGN';";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $output[]= array(
                'id' => $row['id'],
                'staffname' => $row['staffname'],
                'department' => $row['department'],
                'btnaction' => '<button type="submit" id="'.$row['id'].'" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNI</button>',
            );
        }

        echo json_encode($output);
    }else if($_POST["action"] == "gettni"){
        $userid = $_POST["userid"];

        $sql = "select department from user where id = '$userid'";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $department = $row['department'];
        }

        $sql1 = "select count(*) as tnirecord from tni where department = '$department' and year = '" . ldmsPlanningYear() . "';";
        $query1 = mysqli_query($conn,$sql1);
        while($row1 = mysqli_fetch_assoc($query1))
        {
            $tni = $row1['tnirecord'];
        }

        $sql1 = "select tablea.department,ifnull(sumtotalhour,0) as sumhour from (select department from user where id = '$userid')tablea left join (select department,sum(totaldays*totalhours*totalman) as sumtotalhour from (select training.id as trainingid,participation.id as partid, (datediff(enddate,startdate)) + 1 as totaldays,round(TIME_TO_SEC(timediff(endtime,starttime))/3600,2) as totalhours,department,1 as totalman from training join participation on training.id = trainingid join user on userid = user.id where year(startdate) = '2023' and attendance = 'COMPLETED')tablea group by department)tableb on tablea.department = tableb.department order by department;";
        $query1 = mysqli_query($conn,$sql1);
        while($row1 = mysqli_fetch_assoc($query1))
        {
            $publichour = $row1['sumhour'];
        }

        $sql2 = "select tablea.department,ifnull(sumtotalhour,0) as sumhour from (select department from user where id = '$userid')tablea left join (select department,sum(totaldays*totalhours*totalman) as sumtotalhour from (select ojt.id as trainingid,participateojt.id as partid, (datediff(enddate,startdate)) + 1 as totaldays,round(TIME_TO_SEC(timediff(endtime,starttime))/3600,2) as totalhours,user.department,participateojt.totalman from ojt join participateojt on ojt.id = ojtid join user on userid = user.id where year(startdate) = '2023' and attendance in ('COMPLETEDOJT'))tablea group by department)tableb on tablea.department = tableb.department order by department;";
        $query2 = mysqli_query($conn,$sql2);
        while($row2 = mysqli_fetch_assoc($query2))
        {
            $ojthour = $row2['sumhour'];
        }

        $output[]= array(
            'tni' => $tni,
            'publichour' => $publichour,
            'ojthour' => $ojthour,
            'totalhour' => $publichour+$ojthour,
        );

        echo json_encode($output);
    }else if($_POST["action"] == "getlisttni"){
        $userid = $_POST["userid"];

        $sql = "select department from user where id = '$userid'";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $department = $row['department'];
        }
        
        $sql = "select * from tni where department = '$department' and year = '" . ldmsPlanningYear() . "' order by id";
        $query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
            $output[]= array(
                'training' => $row['training'],
                'expected' => $row['expected'],
                'actual' => $row['actual'],
                'gap' => $row['gap'],
                'method' => $row['method'],
                'cause' => $row['cause'],
                'ask' => $row['ask'],
                'evaluation' => $row['evaluation'],
            );
        }

        echo json_encode($output);
    }
?>