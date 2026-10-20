<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";

$canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
    isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
    && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
    && (int) $_SESSION['hodid'] != 0
    && $_SESSION['role'] == ''
    && $_SESSION['usertype'] == ''
);
include_once __DIR__ . '/../../../skill_matrix_department.php';
$canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

if (isset($_SESSION['fullname']) && $canViewSkillMatrix) {
    $sourceStaffId = isset($_GET['staffid']) ? (int) $_GET['staffid'] : 0;
    $department = $_SESSION['department'];
    list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
    // ?period=previous takes the source matrix from the quarter before the
    // evaluation quarter. The copies always go into the evaluation quarter, so
    // in that mode the source staff can be one of the targets too.
    $fromPrevious = isset($_GET['period']) && $_GET['period'] == 'previous';
    $sourceYear = $currentYear;
    $sourceQuarter = $currentQuarter;
    if ($fromPrevious) {
        list($sourceYear, $sourceQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);
    }
    $excludeStaffId = $fromPrevious ? 0 : $sourceStaffId;
    $sourceStaff = null;
    $sourceEvaluation = null;
    $eligibleStaff = array();
    $saveMessage = '';
    $saveError = '';

    if (isset($_GET['copied']) && $_GET['copied'] != '') {
        $saveMessage = (int) $_GET['copied'] . ' staff matrix copied successfully.';
    }

    if ($sourceStaffId > 0) {
        $staffStmt = $conn->prepare("SELECT
                                        u.id,
                                        u.staffno,
                                        u.staffname,
                                        u.grade,
                                        COALESCE(dp.name, u.department) AS department,
                                        COALESCE(s.name, u.section) AS section
                                    FROM user u
                                    LEFT JOIN departments dp ON u.department_id = dp.id
                                    LEFT JOIN sections s ON u.section_id = s.id
                                    WHERE u.id = ?
                                    AND u.department = ?");
        $staffStmt->bind_param("is", $sourceStaffId, $department);
        $staffStmt->execute();
        $sourceStaff = $staffStmt->get_result()->fetch_assoc();

        if ($sourceStaff) {
            $evaluationStmt = $conn->prepare("SELECT id, evaluation_date
                                             FROM skill_matrix_evaluations
                                             WHERE staffid = ?
                                             AND eval_year = ?
                                             AND eval_quarter = ?
                                             ORDER BY evaluation_date DESC, id DESC
                                             LIMIT 1");
            $evaluationStmt->bind_param("iii", $sourceStaffId, $sourceYear, $sourceQuarter);
            $evaluationStmt->execute();
            $sourceEvaluation = $evaluationStmt->get_result()->fetch_assoc();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $sourceStaff && $sourceEvaluation) {
        $targetStaffIds = isset($_POST['target_staff']) ? $_POST['target_staff'] : array();
        $validTargetStaffIds = array();

        if (count($targetStaffIds) == 0) {
            $saveError = 'Please select at least one staff.';
        } else {
            foreach ($targetStaffIds as $targetStaffId) {
                $targetStaffId = (int) $targetStaffId;

                if ($targetStaffId <= 0 || $targetStaffId == $excludeStaffId) {
                    continue;
                }

                $targetStmt = $conn->prepare("SELECT u.id
                                             FROM user u
                                             WHERE u.id = ?
                                             AND u.department = ?
                                             AND u.designation IN (?, ?)
                                             AND u.status != ?
                                             AND NOT EXISTS (
                                                SELECT 1
                                                FROM skill_matrix_evaluations sme
                                                WHERE sme.staffid = u.id
                                                AND sme.eval_year = ?
                                                AND sme.eval_quarter = ?
                                             )");
                $designation1 = "NON EXECUTIVE";
                $designation2 = "CONTRACT";
                $inactiveStatus = "RESIGN";
                $targetStmt->bind_param("issssii", $targetStaffId, $department, $designation1, $designation2, $inactiveStatus, $currentYear, $currentQuarter);
                $targetStmt->execute();
                $targetResult = $targetStmt->get_result()->fetch_assoc();

                if ($targetResult) {
                    $validTargetStaffIds[] = $targetStaffId;
                }
            }

            if (count($validTargetStaffIds) == 0) {
                $saveError = 'Selected staff are not eligible for duplicate.';
            }
        }

        if ($saveError == '') {
            mysqli_begin_transaction($conn);

            try {
                $sourceTopicsStmt = $conn->prepare("SELECT id, section_type, topic_name, sort_order
                                                   FROM skill_matrix_topics
                                                   WHERE evaluation_id = ?
                                                   ORDER BY sort_order");
                $sourceTopicsStmt->bind_param("i", $sourceEvaluation['id']);
                $sourceTopicsStmt->execute();
                $sourceTopicsResult = $sourceTopicsStmt->get_result();
                $sourceTopics = array();

                while ($topicRow = $sourceTopicsResult->fetch_assoc()) {
                    $itemStmt = $conn->prepare("SELECT evaluation_text, rating, sort_order
                                               FROM skill_matrix_items
                                               WHERE topic_id = ?
                                               ORDER BY sort_order");
                    $itemStmt->bind_param("i", $topicRow['id']);
                    $itemStmt->execute();
                    $itemsResult = $itemStmt->get_result();
                    $topicRow['items'] = array();

                    while ($itemRow = $itemsResult->fetch_assoc()) {
                        $topicRow['items'][] = $itemRow;
                    }

                    $sourceTopics[] = $topicRow;
                }

                $createdBy = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
                $evaluationDateForDb = date('Y-m-d');
                $copiedCount = 0;

                $insertEvaluationStmt = $conn->prepare("INSERT INTO skill_matrix_evaluations (staffid, evaluation_date, eval_year, eval_quarter, created_by, approval_status) VALUES (?, ?, ?, ?, ?, NULL)");
                $insertTopicStmt = $conn->prepare("INSERT INTO skill_matrix_topics (evaluation_id, section_type, topic_name, sort_order) VALUES (?, ?, ?, ?)");
                $insertItemStmt = $conn->prepare("INSERT INTO skill_matrix_items (topic_id, evaluation_text, rating, sort_order) VALUES (?, ?, ?, ?)");

                foreach ($validTargetStaffIds as $targetStaffId) {
                    $insertEvaluationStmt->bind_param("isiii", $targetStaffId, $evaluationDateForDb, $currentYear, $currentQuarter, $createdBy);
                    $insertEvaluationStmt->execute();
                    $newEvaluationId = $conn->insert_id;

                    foreach ($sourceTopics as $topic) {
                        $insertTopicStmt->bind_param("issi", $newEvaluationId, $topic['section_type'], $topic['topic_name'], $topic['sort_order']);
                        $insertTopicStmt->execute();
                        $newTopicId = $conn->insert_id;

                        foreach ($topic['items'] as $item) {
                            $insertItemStmt->bind_param("isii", $newTopicId, $item['evaluation_text'], $item['rating'], $item['sort_order']);
                            $insertItemStmt->execute();
                        }
                    }

                    $copiedCount++;
                }

                mysqli_commit($conn);
                header("Location: duplicate-matrix.php?staffid=" . urlencode($sourceStaffId) . "&copied=" . $copiedCount . ($fromPrevious ? '&period=previous' : ''));
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $saveError = 'Unable to duplicate skill matrix. Please try again.';
            }
        }
    }

    if ($sourceStaff && $sourceEvaluation) {
        $eligibleStmt = $conn->prepare("SELECT
                                            u.id,
                                            u.staffno,
                                            u.staffname,
                                            u.grade,
                                            COALESCE(s.name, u.section) AS section
                                        FROM user u
                                        LEFT JOIN sections s ON u.section_id = s.id
                                        WHERE u.id != ?
                                        AND u.department = ?
                                        AND u.designation IN (?, ?)
                                        AND u.status != ?
                                        AND NOT EXISTS (
                                            SELECT 1
                                            FROM skill_matrix_evaluations sme
                                            WHERE sme.staffid = u.id
                                            AND sme.eval_year = ?
                                            AND sme.eval_quarter = ?
                                        )
                                        ORDER BY u.staffname");
        $designation1 = "NON EXECUTIVE";
        $designation2 = "CONTRACT";
        $inactiveStatus = "RESIGN";
        $eligibleStmt->bind_param("issssii", $excludeStaffId, $department, $designation1, $designation2, $inactiveStatus, $currentYear, $currentQuarter);
        $eligibleStmt->execute();
        $eligibleResult = $eligibleStmt->get_result();

        while ($row = $eligibleResult->fetch_assoc()) {
            $eligibleStaff[] = $row;
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Learning and Development Management System</title>
        <script src="../../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../../asset/css/bootstrap.min.css" />
        <script src="../../../asset/js/bootstrap.min.js"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
    </head>

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
                        <li><a href="../attendance/training.php">MY TRAINING</a></li>
                        <li><a href="../attendance/pme.php">PME</a></li>
                        <li><a href="../tna/tna.php">TNA</a></li>
                        <li class="active"><a href="skill-matrix.php">SKILL MATRIX</a></li>
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
                                    <strong>Duplicate Skill Matrix</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <a href="skill-matrix.php<?php echo $fromPrevious ? '?period=previous' : ''; ?>" class="btn btn-success btn-md">
                                        <i class="far fa-arrow-alt-circle-left"></i> BACK TO SKILL MATRIX
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php if ($saveMessage != '') { ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($saveMessage); ?></div>
                            <?php } ?>

                            <?php if ($saveError != '') { ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($saveError); ?></div>
                            <?php } ?>

                            <?php if (!$sourceStaff) { ?>
                                <div class="alert alert-warning">Source staff record not found.</div>
                            <?php } else if (!$sourceEvaluation) { ?>
                                <div class="alert alert-warning">This staff has no skill matrix for Q<?php echo $sourceQuarter; ?> <?php echo $sourceYear; ?>.</div>
                            <?php } else { ?>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="15%">Source Staff No.</th>
                                        <td><?php echo htmlspecialchars($sourceStaff['staffno']); ?></td>
                                        <th width="15%">Source Staff Name</th>
                                        <td><?php echo htmlspecialchars($sourceStaff['staffname']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Department</th>
                                        <td><?php echo htmlspecialchars($sourceStaff['department']); ?></td>
                                        <th>Section</th>
                                        <td><?php echo htmlspecialchars($sourceStaff['section']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Quarter</th>
                                        <td colspan="3">Q<?php echo $sourceQuarter; ?> <?php echo $sourceYear; ?><?php if ($fromPrevious) { ?> (previous quarter) - copies go into Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?><?php } ?></td>
                                    </tr>
                                </table>

                                <?php if (count($eligibleStaff) == 0) { ?>
                                    <div class="alert alert-info">No eligible staff found. Staff who already have a skill matrix for Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?> are excluded.</div>
                                <?php } else { ?>
                                    <form method="post" id="duplicate_form">
                                        <div class="row" style="margin-bottom: 10px;">
                                            <div class="col-md-5">
                                                <input type="text" id="staff_search" class="form-control" placeholder="Search staff no. or staff name" autocomplete="off">
                                            </div>
                                            <div class="col-md-7" align="right" style="margin-top: 7px;">
                                                <strong><span id="selected_count">0</span></strong> staff selected
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th width="5%" class="text-center">
                                                            <input type="checkbox" id="select_all">
                                                        </th>
                                                        <th>Staff No.</th>
                                                        <th>Staff Name</th>
                                                        <th>Section</th>
                                                        <th>Grade</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($eligibleStaff as $staff) { ?>
                                                        <tr class="target-staff-row" style="cursor: pointer;">
                                                            <td class="text-center">
                                                                <input type="checkbox" name="target_staff[]" value="<?php echo $staff['id']; ?>">
                                                            </td>
                                                            <td><?php echo htmlspecialchars($staff['staffno']); ?></td>
                                                            <td><?php echo htmlspecialchars($staff['staffname']); ?></td>
                                                            <td><?php echo htmlspecialchars($staff['section']); ?></td>
                                                            <td><?php echo htmlspecialchars($staff['grade']); ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                            <div id="no_staff_match" class="alert alert-warning" style="display: none;">No staff match your search.</div>
                                        </div>

                                        <div align="right">
                                            <button type="submit" class="btn btn-warning btn-md">
                                                DUPLICATE SELECTED <i class="fa fa-copy"></i>
                                            </button>
                                        </div>
                                    </form>
                                <?php } ?>
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

        // Select all only ticks the rows the search is currently showing; rows
        // ticked earlier and then filtered out stay ticked and are still submitted.
        $('#select_all').change(function () {
            $('#duplicate_form tbody tr:visible input[name="target_staff[]"]').prop('checked', $(this).prop('checked'));
        });

        $('#staff_search').on('input', function () {
            var keyword = $.trim($(this).val()).toLowerCase();
            var matches = 0;

            $('#duplicate_form tbody tr').each(function () {
                var cells = $(this).children('td');
                var text = (cells.eq(1).text() + ' ' + cells.eq(2).text()).toLowerCase();
                var show = text.indexOf(keyword) !== -1;

                $(this).toggle(show);
                matches += show ? 1 : 0;
            });

            $('#no_staff_match').toggle(matches === 0);
            $('#select_all').prop('checked', false);
        }).on('keydown', function (e) {
            if (e.which === 13) {
                e.preventDefault();
            }
        });

        $('#duplicate_form').on('click change', function () {
            $('#selected_count').text($('input[name="target_staff[]"]:checked').length);
        });

        $('.target-staff-row').click(function (e) {
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }

            var checkbox = $(this).find('input[type="checkbox"]');
            checkbox.prop('checked', !checkbox.prop('checked'));
        });

        setTimeout(function () {
            $('.alert-success, .alert-danger').fadeOut();
        }, 3000);

        <?php if ($saveMessage != '') { ?>
            swal({
                title: "Copied",
                text: "<?php echo addslashes($saveMessage); ?>",
                icon: "success",
                timer: 3000,
                buttons: false
            });
        <?php } ?>

        <?php if ($saveError != '') { ?>
            swal({
                title: "Unable to duplicate",
                text: "<?php echo addslashes($saveError); ?>",
                icon: "warning",
                timer: 3000,
                buttons: false
            });
        <?php } ?>
    </script>

    </html>
    <?php
} else {
    header("Location: ../../../login.php");
    exit();
}
?>
