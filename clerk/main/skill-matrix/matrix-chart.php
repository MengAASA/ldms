<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";

function matrixPieLevel($score)
{
    if ($score >= 100) {
        return 100;
    }

    if ($score >= 75) {
        return 75;
    }

    if ($score >= 50) {
        return 50;
    }

    if ($score >= 25) {
        return 25;
    }

    return 0;
}

$matrixFixedLevels = array(
    100 => array('label' => 'Highly Skilled', 'sub' => 'Able to Supervise others'),
    75 => array('label' => 'Competent', 'sub' => ''),
    50 => array('label' => 'Medium Competency', 'sub' => ''),
    25 => array('label' => 'Novice', 'sub' => 'Basic Knowledge'),
    0 => array('label' => 'Minimal Competency', 'sub' => '')
);

$canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
    isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
    && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
    && (int) $_SESSION['hodid'] != 0
    && $_SESSION['role'] == 'CLERK'
    && $_SESSION['usertype'] == 'MAIN'
);
include_once __DIR__ . '/../../../skill_matrix_department.php';
$canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

if (isset($_SESSION['fullname']) && $canViewSkillMatrix) {
    $department = $_SESSION['department'];
    list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
    $staffRows = array();
    $topicColumns = array();

    $stmt = $conn->prepare("SELECT
                                sme.id AS evaluation_id,
                                u.id AS staff_id,
                                u.staffno,
                                u.staffname,
                                u.designation,
                                u.grade,
                                creator.staffname AS evaluated_by,
                                verifier.staffname AS verified_by,
                                approver.staffname AS approved_by,
                                COALESCE(dp.name, u.department) AS department,
                                COALESCE(s.name, u.section) AS section
                            FROM skill_matrix_evaluations sme
                            INNER JOIN user u ON u.id = sme.staffid
                            LEFT JOIN user creator ON creator.id = sme.created_by
                            LEFT JOIN user verifier ON verifier.id = creator.hodid
                            LEFT JOIN user approver ON approver.id = sme.approved_by
                            LEFT JOIN departments dp ON u.department_id = dp.id
                            LEFT JOIN sections s ON u.section_id = s.id
                            WHERE u.department = ?
                            AND sme.eval_year = ?
                            AND sme.eval_quarter = ?
                            AND sme.approval_status IN ('APPROVED', 'PENDING')
                            ORDER BY u.staffname");
    $stmt->bind_param("sii", $department, $currentYear, $currentQuarter);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $staffRows[$row['evaluation_id']] = array(
            'staffno' => $row['staffno'],
            'staffname' => $row['staffname'],
            'designation_grade' => $row['designation'] . ' / ' . $row['grade'],
            'evaluated_by' => $row['evaluated_by'],
            'verified_by' => $row['verified_by'],
            'approved_by' => $row['approved_by'],
            'scores' => array(),
            'overall_total' => 0,
            'overall_count' => 0
        );
    }

    if (count($staffRows) > 0) {
        $evaluationIds = array_keys($staffRows);
        $placeholders = implode(',', array_fill(0, count($evaluationIds), '?'));
        $types = str_repeat('i', count($evaluationIds));
        $topicSql = "SELECT
                        t.evaluation_id,
                        t.section_type,
                        t.topic_name,
                        MIN(t.sort_order) AS topic_sort_order,
                        SUM(i.rating) AS total_rating,
                        COUNT(i.id) AS item_count
                    FROM skill_matrix_topics t
                    INNER JOIN skill_matrix_items i ON i.topic_id = t.id
                    WHERE t.evaluation_id IN ($placeholders)
                    GROUP BY t.evaluation_id, t.section_type, t.topic_name
                    ORDER BY FIELD(t.section_type, 'knowledge', 'skill', 'ability'), topic_sort_order";
        $topicStmt = $conn->prepare($topicSql);
        $topicStmt->bind_param($types, ...$evaluationIds);
        $topicStmt->execute();
        $topicResult = $topicStmt->get_result();

        while ($topicRow = $topicResult->fetch_assoc()) {
            $topicKey = $topicRow['section_type'] . '|' . $topicRow['topic_name'];
            $percentage = $topicRow['item_count'] > 0 ? ($topicRow['total_rating'] / ($topicRow['item_count'] * 5)) * 100 : 0;

            if (!isset($topicColumns[$topicKey])) {
                $topicColumns[$topicKey] = array(
                    'section_type' => $topicRow['section_type'],
                    'topic_name' => $topicRow['topic_name']
                );
            }

            $staffRows[$topicRow['evaluation_id']]['scores'][$topicKey] = $percentage;
            $staffRows[$topicRow['evaluation_id']]['overall_total'] += $percentage;
            $staffRows[$topicRow['evaluation_id']]['overall_count']++;
        }
    }

    $evaluatedByNames = array();
    $verifiedByNames = array();
    $approvedByNames = array();
    foreach ($staffRows as $staffRow) {
        if (!empty($staffRow['evaluated_by'])) {
            $evaluatedByNames[$staffRow['evaluated_by']] = true;
        }
        if (!empty($staffRow['verified_by'])) {
            $verifiedByNames[$staffRow['verified_by']] = true;
        }
        if (!empty($staffRow['approved_by'])) {
            $approvedByNames[$staffRow['approved_by']] = true;
        }
    }
    $reportEvaluatedBy = implode(', ', array_keys($evaluatedByNames));
    $reportVerifiedBy = implode(', ', array_keys($verifiedByNames));
    $reportApprovedBy = implode(', ', array_keys($approvedByNames));

    if (isset($_GET['export']) && $_GET['export'] == 'excel') {
        require_once "../../../skill_matrix_chart_excel.php";
        outputMatrixChartExcel($staffRows, $topicColumns, $matrixFixedLevels, $currentQuarter, $currentYear, array(), array($department), $reportEvaluatedBy, $reportVerifiedBy, $reportApprovedBy);
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Learning and Development Management System</title>
        <script src="../../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../../asset/css/bootstrap.min.css" />
        <script src="../../../asset/js/bootstrap.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
    </head>

    <style>
        .matrix-table {
            background-color: #ffffff;
            font-size: 12px;
            min-width: 1300px;
        }

        .matrix-table th,
        .matrix-table td {
            border: 1px solid #dddddd !important;
            text-align: center;
            vertical-align: middle !important;
        }

        .matrix-table th {
            background-color: #eef6fb;
            color: #31708f;
            font-weight: bold;
        }

        .matrix-table thead tr:first-child th {
            background-color: #337ab7;
            color: #ffffff;
        }

        .matrix-table .staff-name {
            text-align: left;
            min-width: 190px;
        }

        .matrix-table .topic-header {
            min-width: 105px;
            white-space: normal;
        }

        .matrix-table .matrix-ability-cell {
            vertical-align: top !important;
        }

        .matrix-cell-topics {
            border-collapse: collapse;
            color: #31708f;
            font-size: 10px;
            font-weight: bold;
            margin: 4px 0 0 0;
            width: 100%;
        }

        .matrix-cell-topics td {
            border: none !important;
            padding: 1px 0;
            vertical-align: top !important;
            white-space: normal;
        }

        .matrix-cell-topic-name {
            text-align: left !important;
        }

        .matrix-cell-topic-score {
            padding-left: 4px !important;
            text-align: right !important;
            white-space: nowrap;
        }

        .matrix-score {
            align-items: center;
            display: flex;
            gap: 6px;
            justify-content: center;
            white-space: nowrap;
        }

        .matrix-pie {
            border: 1px solid #555555;
            border-radius: 50%;
            display: inline-block;
            height: 34px;
            position: relative;
            width: 34px;
        }

        .matrix-pie.level-100 {
            background-color: #337ab7;
        }

        .matrix-pie.level-75 {
            background: conic-gradient(#337ab7 0 75%, #ffffff 75% 100%);
        }

        .matrix-pie.level-50 {
            background: conic-gradient(#337ab7 0 50%, #ffffff 50% 100%);
        }

        .matrix-pie.level-25 {
            background: conic-gradient(#337ab7 0 25%, #ffffff 25% 100%);
        }

        .matrix-pie.level-0 {
            background-color: #ffffff;
        }

        .matrix-pie:before,
        .matrix-pie:after {
            background-color: #555555;
            content: "";
            position: absolute;
        }

        .matrix-pie:before {
            height: 1px;
            left: 0;
            top: 16px;
            width: 34px;
        }

        .matrix-pie:after {
            height: 34px;
            left: 16px;
            top: 0;
            width: 1px;
        }

        .matrix-remarks {
            background-color: #ffffff;
            border: 1px solid #dddddd;
            margin-top: 15px;
            min-width: 900px;
            padding: 12px 0;
        }

        .matrix-remarks-title {
            font-weight: bold;
            padding-left: 8px;
        }

        .matrix-remarks-items {
            align-items: flex-start;
            display: flex;
            gap: 35px;
            justify-content: center;
            text-align: center;
        }

        .matrix-remarks-item {
            min-width: 140px;
        }

        .matrix-remarks-circle {
            border: 1px solid #555555;
            border-radius: 50%;
            display: inline-block;
            height: 42px;
            margin-bottom: 5px;
            position: relative;
            width: 42px;
        }

        .matrix-remarks-circle:before,
        .matrix-remarks-circle:after {
            background-color: #555555;
            content: "";
            position: absolute;
        }

        .matrix-remarks-circle:before {
            height: 1px;
            left: 0;
            top: 20px;
            width: 42px;
        }

        .matrix-remarks-circle:after {
            height: 42px;
            left: 20px;
            top: 0;
            width: 1px;
        }

        .matrix-remarks-circle.level-100 {
            background-color: #4f81bd;
        }

        .matrix-remarks-circle.level-75 {
            background: conic-gradient(#4f81bd 0 75%, #ffffff 75% 100%);
        }

        .matrix-remarks-circle.level-50 {
            background: conic-gradient(#4f81bd 0 50%, #ffffff 50% 100%);
        }

        .matrix-remarks-circle.level-25 {
            background: conic-gradient(#4f81bd 0 25%, #ffffff 25% 100%);
        }

        .matrix-remarks-circle.level-0 {
            background-color: #ffffff;
        }

        .matrix-remarks-label {
            font-weight: bold;
        }

        .matrix-remarks-sub {
            font-size: 11px;
            font-weight: bold;
        }

        .matrix-remarks-percent {
            margin-top: 4px;
        }

        .matrix-signoff-table {
            background-color: #ffffff;
            margin-top: 15px;
            min-width: 900px;
        }

        .matrix-signoff-table th,
        .matrix-signoff-table td {
            border: 1px solid #dddddd !important;
            text-align: center;
            vertical-align: middle !important;
        }

        .matrix-signoff-table th {
            background-color: #eef6fb;
            color: #31708f;
            font-weight: bold;
            width: 33.33%;
        }

        .download-report-btn {
            background-color: #337ab7;
            border-color: #2e6da4;
            color: #ffffff;
        }

        .download-report-btn:hover,
        .download-report-btn:focus {
            background-color: #286090;
            border-color: #204d74;
            color: #ffffff;
        }
    </style>

    <body onload="startTime()" style="background-image:url('../../../asset/image/bg-try.png');zoom: 75%;">
        <br>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10">
                    <img src="../../../asset/image/lndlogo.gif" height="50" width="290">
                </div>
                <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;"></div>
            </div>
            <nav class="navbar navbar-inverse">
                <div class="container-fluid ">
                    <ul class="nav navbar-nav">
                        <li><a href="../dashboard.php">HOME</a></li>
                        <li><a href="../staff/staff.php">CONTRACT STAFF LIST</a></li>
                        <li><a href="../training/training_ojt.php">ALL TRAINING</a></li>
                        <li><a href="../attendance/training.php">MY TRAINING</a></li>
                        <li><a href="../attendance/pme.php">PME</a></li>
                        <li><a href="../tna/tna_list.php">TNA</a></li>
                        <li class="active"><a href="skill-matrix.php">SKILL MATRIX</a></li>
                        <li><a href="../../../admin/archive/export.php">EXPORT</a></li>
                        <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right">
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span
                                    class="label label-pill label-danger count"></span> <?php echo $_SESSION['fullname'] ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="../../../logout.php">LOGOUT</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-8" style="margin-top: 10px;">
                                    <strong>Matrix Chart</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <button type="button" class="btn download-report-btn btn-md" id="download_matrix_report">
                                        <i class="fa fa-download"></i> Download Report
                                    </button>
                                    <a href="skill-matrix.php" class="btn btn-success btn-md">
                                        <i class="far fa-arrow-alt-circle-left"></i> BACK TO SKILL MATRIX
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="alert alert-info">
                                Evaluation Quarter: Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?>
                            </div>

                            <?php if (count($staffRows) == 0 || count($topicColumns) == 0) { ?>
                                <div class="alert alert-warning">No completed skill matrix records found for this quarter.</div>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table matrix-table" id="matrix_report_table">
                                        <thead>
                                            <tr>
                                                <th colspan="4"></th>
                                                <th colspan="<?php echo count($matrixFixedLevels); ?>">ABILITY DESCRIPTION</th>
                                                <th>TOTAL</th>
                                            </tr>
                                            <tr>
                                                <th colspan="4">No.</th>
                                                <?php $columnNo = 1; ?>
                                                <?php foreach ($matrixFixedLevels as $level) { ?>
                                                    <th><?php echo $columnNo; ?></th>
                                                    <?php $columnNo++; ?>
                                                <?php } ?>
                                                <th></th>
                                            </tr>
                                            <tr>
                                                <th width="45">NO.</th>
                                                <th width="80">EMP. NO</th>
                                                <th class="staff-name">NAME</th>
                                                <th width="140">DESIGNATION / GRADE</th>
                                                <?php foreach ($matrixFixedLevels as $levelValue => $level) { ?>
                                                    <th class="topic-header">
                                                        <?php echo htmlspecialchars($level['label']); ?>
                                                        <?php if ($level['sub'] != '') { ?>
                                                            <div class="matrix-remarks-sub"><?php echo htmlspecialchars($level['sub']); ?></div>
                                                        <?php } ?>
                                                        (<?php echo $levelValue; ?>%)
                                                    </th>
                                                <?php } ?>
                                                <th width="95">AVERAGE</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $rowNo = 1; ?>
                                            <?php foreach ($staffRows as $staffRow) { ?>
                                                <?php
                                                $overallAverage = $staffRow['overall_count'] > 0 ? $staffRow['overall_total'] / $staffRow['overall_count'] : 0;
                                                $levelTopics = array(100 => array(), 75 => array(), 50 => array(), 25 => array(), 0 => array());
                                                foreach ($staffRow['scores'] as $topicKey => $score) {
                                                    $levelTopics[matrixPieLevel($score)][] = array(
                                                        'name' => $topicColumns[$topicKey]['topic_name'],
                                                        'score' => $score
                                                    );
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo $rowNo; ?>.</td>
                                                    <td><?php echo htmlspecialchars($staffRow['staffno']); ?></td>
                                                    <td class="staff-name"><?php echo htmlspecialchars($staffRow['staffname']); ?></td>
                                                    <td><?php echo htmlspecialchars($staffRow['designation_grade']); ?></td>
                                                    <?php foreach ($matrixFixedLevels as $levelValue => $level) { ?>
                                                        <td class="matrix-ability-cell">
                                                            <span class="matrix-pie level-<?php echo $levelValue; ?>"></span>
                                                            <?php if (!empty($levelTopics[$levelValue])) { ?>
                                                                <table class="matrix-cell-topics">
                                                                    <?php foreach ($levelTopics[$levelValue] as $index => $topicInfo) { ?>
                                                                        <tr>
                                                                            <td class="matrix-cell-topic-name"><?php echo ($index + 1); ?>. <?php echo htmlspecialchars($topicInfo['name']); ?></td>
                                                                            <td class="matrix-cell-topic-score"><?php echo number_format($topicInfo['score'], 0); ?>%</td>
                                                                        </tr>
                                                                    <?php } ?>
                                                                </table>
                                                            <?php } ?>
                                                        </td>
                                                    <?php } ?>
                                                    <td><strong><?php echo number_format($overallAverage, 2); ?>%</strong></td>
                                                </tr>
                                                <?php $rowNo++; ?>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="table-responsive">
                                    <div class="matrix-remarks">
                                        <div class="matrix-remarks-title">REMARKS:</div>
                                        <div class="matrix-remarks-items">
                                            <div class="matrix-remarks-item">
                                                <span class="matrix-remarks-circle level-100"></span>
                                                <div class="matrix-remarks-label">Highly Skilled</div>
                                                <div class="matrix-remarks-sub">(Able to Supervise others)</div>
                                                <div class="matrix-remarks-percent">100%</div>
                                            </div>
                                            <div class="matrix-remarks-item">
                                                <span class="matrix-remarks-circle level-75"></span>
                                                <div class="matrix-remarks-label">Competent</div>
                                                <div class="matrix-remarks-percent">75%</div>
                                            </div>
                                            <div class="matrix-remarks-item">
                                                <span class="matrix-remarks-circle level-50"></span>
                                                <div class="matrix-remarks-label">Medium Competency</div>
                                                <div class="matrix-remarks-percent">50%</div>
                                            </div>
                                            <div class="matrix-remarks-item">
                                                <span class="matrix-remarks-circle level-25"></span>
                                                <div class="matrix-remarks-label">Novice</div>
                                                <div class="matrix-remarks-sub">(Basic Knowledge)</div>
                                                <div class="matrix-remarks-percent">25%</div>
                                            </div>
                                            <div class="matrix-remarks-item">
                                                <span class="matrix-remarks-circle level-0"></span>
                                                <div class="matrix-remarks-label">Minimal Competency</div>
                                                <div class="matrix-remarks-percent">0%</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table matrix-signoff-table" id="matrix_signoff_table">
                                        <thead>
                                            <tr>
                                                <th>EVALUATED BY</th>
                                                <th>VERIFIED BY</th>
                                                <th>APPROVED BY</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reportEvaluatedBy); ?></td>
                                                <td><?php echo htmlspecialchars($reportVerifiedBy); ?></td>
                                                <td><?php echo htmlspecialchars($reportApprovedBy); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>

    <script>
        function startTime() {
            var today = new Date();
            var h = today.getHours();
            var m = today.getMinutes();
            var s = today.getSeconds();
            m = checkTime(m);
            s = checkTime(s);
            document.getElementById('txt').innerHTML = h + ":" + m + ":" + s;
            setTimeout(startTime, 500);
        }

        function checkTime(i) {
            if (i < 10) {
                i = "0" + i;
            }
            return i;
        }

        $('#download_matrix_report').click(function () {
            var table = document.getElementById('matrix_report_table');
            if (!table) {
                swal("No report", "No matrix chart data is available to download.", "warning");
                return;
            }

            window.location = "matrix-chart.php?export=excel";
        });
    </script>

    </html>
    <?php
} else {
    header("Location: ../../../login.php");
    exit();
}
?>
