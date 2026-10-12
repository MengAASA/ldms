<?php
include "../../dbconn.php";
include_once __DIR__ . '/../../planning_year.php';
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// print_r($_POST);

if (isset($_POST["action"]) && $_POST["action"] === "all_department") {
    echo json_encode(allDepartment());
}

if (isset($_POST["action"]) && $_POST["action"] === "tna_status") {
    echo json_encode(tnaStatus());
}

if (isset($_POST["action"]) && $_POST["action"] === "fetch_summary_tna") {
    echo json_encode(tnaSummaryPieChart());
}

if (isset($_POST["action"]) && $_POST["action"] === "fetch_method_tna") {
    echo json_encode(tnaMethodPieChart());
}

if ($_POST["action"] == "load_department") {
    echo loadDepartment();
} else if ($_POST["action"] == "load_staff") {
    $output = array();
    $userid = $_POST["userid"];
    $department = $_POST['department'];

    $sql = "SELECT user.id, staffno, staffname, designation, usertype, user.section, IFNULL(tablea.status, 'not') AS status
			FROM user 
			LEFT JOIN (
				SELECT userid, MAX(status) AS status 
				FROM tna 
				WHERE year = '" . ldmsPlanningYear() . "' 
				GROUP BY userid
			) tablea 
			ON user.id = tablea.userid 
			WHERE department = '$department' 
			AND designation = 'NON EXECUTIVE' 
			AND (usertype IS NULL OR usertype != '')
			AND hodid != 0
			AND UPPER(COALESCE(user.status, '')) <> 'RESIGN'

			UNION 

			SELECT user.id, staffno, staffname, designation, usertype, user.section, IFNULL(tablea.status, 'not') AS status
			FROM user 
			LEFT JOIN (
				SELECT userid, MAX(status) AS status 
				FROM tna 
				WHERE year = '" . ldmsPlanningYear() . "' 
				GROUP BY userid
			) tablea 
			ON user.id = tablea.userid 
			WHERE department = '$department' 
			AND designation IN ('MANAGER (AM/HOS & ABOVE)', 'EXECUTIVE') 
			AND hodid != 0
			AND UPPER(COALESCE(user.status, '')) <> 'RESIGN';
			";
    $query = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($query)) {
        if ($row['status'] == 'not') {
            $status = '<span class="label label-pill label-info">NOT SUBMITTED</span>';
            $buttonview = '<button type="submit" id="' . $row['id'] . '" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button>';
        } else if ($row['status'] == '') {
            $status = '<span class="label label-pill label-warning">WAITING APPROVAL</span>';
            $buttonview = '<button type="submit" id="' . $row['id'] . '" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button>';
        } else if ($row['status'] == 'APPROVE') {
            $status = '<span class="label label-pill label-success">TNA APPROVED</span>';
            $buttonview = '<button type="submit" id="' . $row['id'] . '" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button>';
        }

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
    $department = $_POST['department'];

    $stmt = $conn->prepare("
        SELECT tablea.grade, tablea.id, tablea.status, tableb.headcount
        FROM (
            SELECT DISTINCT(grade) AS grade, CONCAT(grade, '/', department) AS id, status
            FROM tna
            WHERE department = ? AND year = '" . ldmsPlanningYear() . "'
        ) tablea
        JOIN (
            SELECT grade, COUNT(*) AS headcount
            FROM user
            WHERE department = ? AND grade != 0
            AND UPPER(COALESCE(status, '')) <> 'RESIGN'
            GROUP BY grade
        ) tableb ON tablea.grade = tableb.grade
    ");
    $stmt->bind_param("ss", $department, $department);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == '') {
            $status = '<span class="label label-pill label-warning">WAITING APPROVAL</span>';
        } else if ($row['status'] == 'APPROVE') {
            $status = '<span class="label label-pill label-success">TNA APPROVED</span>';
        } else {
            $status = $row['status']; // fallback
        }

        $buttonview = '<button type="submit" id="' . htmlspecialchars($row['id']) . '" class="btn btn-info btn-sm viewgrade" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button>';

        $output[] = array(
            'id' => $row['id'],
            'grade' => $row['grade'],
            'headcount' => $row['headcount'],
            'status' => $status,
            'btnedit' => $buttonview
        );
    }

    echo json_encode($output);
} else if ($_POST["action"] == "gettna") {
    $userid = $_POST["userid"];

    $sql = "SELECT COUNT(*) AS tnarecord, status 
            FROM tna 
            WHERE userid = '$userid' AND year = '" . ldmsPlanningYear() . "'
            GROUP BY status;
            ";
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

    // Include debug info in JSON
    $output = [
        'debug' => [
            'raw_post_userid' => $_POST["userid"],
            'exploded_userid' => $userid,
            'grade' => $grade,
            'department' => $department,
        ]
    ];

    $sql = "SELECT status, COUNT(*) AS tna 
            FROM tna 
            WHERE department = '$department' 
            AND grade = '$grade' 
            AND year = '" . ldmsPlanningYear() . "' 
            GROUP BY status;";
    $query = mysqli_query($conn, $sql);

    $rows = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $rows[] = $row;
    }

    echo json_encode($rows);
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

function loadDepartment()
{
    global $conn;
    $sql = "SELECT DISTINCT(`department`) AS department FROM `user` ORDER BY department";
    $query = mysqli_query($conn, $sql);
    $options = '<option value="" hidden>-- Select Department --</option><option value="ALL">All Department</option>';
    if (mysqli_num_rows($query) > 0) {
        // output data of each row

        while ($row = mysqli_fetch_assoc($query)) {
            $options .= '<option value="' . $row['department'] . '">' . $row['department'] . '</option>';
        }
    }
    return $options;
}

function allDepartment()
{
    global $conn;
    $sql = "select u.department, count(t.id) as quantity from user u left join " . ldmsPlanningYearTable() . " t on u.id = t.userid where t.department is not null group by u.department order by u.department;";
    $query = mysqli_query($conn, $sql);
    $output = [];
    $index = 1;  // Start a counter for row indexing
    if (mysqli_num_rows($query) > 0) {
        while ($row = mysqli_fetch_assoc($query)) {
            $output[] = array(
                'id' => $index++,  // Assign an incremental ID
                'department' => $row['department'],
                'quantity' => $row['quantity'],
            );
        }
    }
    return $output;
}

function tnaStatus()
{
    global $conn;
    $sql = 'SELECT DISTINCT u.department, hod.staffname AS hod_name, MAX(t.status) AS tna_status FROM user u LEFT JOIN user hod ON u.hodid = hod.id LEFT JOIN ' . ldmsPlanningYearTable() . ' t ON u.id = t.userid WHERE hod.staffname IS NOT NULL AND UPPER(COALESCE(u.status, \'\')) <> \'RESIGN\' AND u.id NOT IN (SELECT hodid FROM user WHERE hodid IS NOT NULL) GROUP BY u.department, u.hodid, hod.staffname;';
    $query = mysqli_query($conn, $sql);
    $output = [];
    $index = 1;

    if (mysqli_num_rows($query) > 0) {
        while ($row = mysqli_fetch_assoc($query)) {
            $status = strtolower($row['tna_status']) === 'approve' ? '<span class="label label-pill label-success">COMPLETED</span>' : '<span class="label label-pill label-warning">PENDING</span>';
            $output[] = array(
                'id' => $index++,
                'department' => $row['department'],
                'hod' => $row['hod_name'],
                'status' => $status
            );
        }
    }
    return $output;
}

function tnaSummaryPieChart()
{
    global $conn;
    $sql = "SELECT section, COUNT(id) AS no, ROUND((COUNT(id) * 100.0 / (SELECT COUNT(id) FROM " . ldmsPlanningYearTable() . " tna WHERE section IN ('esgaware', 'selfaware', 'leadaware', 'busiaware', 'dataaware', 'functional', 'special'))), 2) AS percentage FROM " . ldmsPlanningYearTable() . " tna WHERE section IN ('esgaware', 'selfaware', 'leadaware', 'busiaware', 'dataaware', 'functional', 'special') GROUP BY section;";
    $query = mysqli_query($conn, $sql);
    $data = array();
    while ($row = mysqli_fetch_assoc($query)) {
        $label = '';
        $color = '';

        switch ($row['section']) {
            case 'esgaware':
                $label = 'ESG';
                $color = '#4CAF50';
                break;
            case 'selfaware':
                $label = 'Soft Skill';
                $color = '#FFC107';
                break;
            case 'leadaware':
                $label = 'Leadership Awareness';
                $color = '#2196F3';
                break;
            case 'busiaware':
                $label = 'Digital Transformation';
                $color = '#FF5722';
                break;
            case 'dataaware':
                $label = 'Data Driven';
                $color = '#673AB7';
                break;
            case 'functional':
                $label = 'Functional Awareness';
                $color = '#607D8B';
                break;
            case 'special':
                $label = 'Special Project';
                $color = '#E91E63';
                break;
        }

        $data[] = array(
            'section' => $row['section'],
            'label' => $label,
            'no' => $row['no'],
            'percentage' => $row['percentage'],
            'colorstatus' => $color,
        );
    }

    return $data;
}

function tnaMethodPieChart()
{
    global $conn;
    $sql = "SELECT trainingtype, COUNT(id) AS no, ROUND((COUNT(id) * 100.0 / (SELECT COUNT(id) FROM " . ldmsPlanningYearTable() . " tna WHERE trainingtype IN (1, 2, 3) AND trainingtype IS NOT NULL)), 2) AS percentage FROM " . ldmsPlanningYearTable() . " tna WHERE trainingtype IN (1, 2, 3) AND trainingtype IS NOT NULL GROUP BY trainingtype;";
    $query = mysqli_query($conn, $sql);
    $data = array();
    while ($row = mysqli_fetch_assoc($query)) {
        $label = '';
        $color = '';
        switch ($row['trainingtype']) {
            case 1:
                $label = 'On Job Training';
                $color = '#4CAF50';
                break;
            case 2:
                $label = 'Coaching';
                $color = '#FFC107';
                break;
            case 3:
                $label = 'External/In-House';
                $color = '#2196F3';
                break;
        }

        $data[] = array(
            'trainingtype' => $row['trainingtype'],
            'no' => $row['no'],
            'percentage' => $row['percentage'],
            'label' => $label,
            'color' => $color
        );
    }

    return $data;

}


?>