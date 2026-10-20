<?php
session_start();
include "../../../dbconn.php";

include_once __DIR__ . '/../../../skill_matrix_department.php';

function canApproveSkillMatrix()
{
    global $conn;

    if (!skillMatrixDepartmentEnabled()) {
        return false;
    }

    if (isset($_SESSION['id']) && (!isset($_SESSION['designation']) || !isset($_SESSION['hodid']))) {
        $sessionUserId = (int) $_SESSION['id'];
        $sessionUserQuery = mysqli_query($conn, "SELECT designation, hodid FROM user WHERE id = '$sessionUserId' LIMIT 1");
        if ($sessionUserQuery && $sessionUserRow = mysqli_fetch_assoc($sessionUserQuery)) {
            $_SESSION['designation'] = $sessionUserRow['designation'];
            $_SESSION['hodid'] = $sessionUserRow['hodid'];
        }
    }

    // A department head's own hodid is intentionally left at 0 by
    // trg_departments_hod_update (self-loop guard) - it is not a signal
    // of whether they head a department. Check departments.hod_user_id
    // directly instead.
    if (isset($_SESSION['id']) && !isset($_SESSION['is_department_hod'])) {
        $sessionUserId = (int) $_SESSION['id'];
        $deptHodQuery = mysqli_query($conn, "SELECT 1 FROM departments WHERE hod_user_id = '$sessionUserId' LIMIT 1");
        $_SESSION['is_department_hod'] = ($deptHodQuery && mysqli_num_rows($deptHodQuery) > 0) ? 1 : 0;
    }

    return isset($_SESSION['fullname'], $_SESSION['role'], $_SESSION['designation'], $_SESSION['usertype'])
        && $_SESSION['role'] == ''
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && !empty($_SESSION['is_department_hod'])
        && $_SESSION['usertype'] == 'HOD';
}

function ratingLabel($rating)
{
    $labels = array(
        1 => '1 - Beginner',
        2 => '2 - Basic',
        3 => '3 - Competent',
        4 => '4 - Advanced',
        5 => '5 - Expert'
    );

    return isset($labels[(int) $rating]) ? $labels[(int) $rating] : $rating;
}

if (isset($_SESSION['fullname']) && canApproveSkillMatrix()) {
    $hodId = (int) $_SESSION['id'];
    $evaluationId = isset($_GET['evaluation_id']) ? (int) $_GET['evaluation_id'] : 0;
    $saveMessage = '';
    $saveError = '';
    // Carried through only so BACK returns to the quarter tab the HOD came from.
    $fromPrevious = isset($_GET['period']) && $_GET['period'] == 'previous';

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['evaluation_id'])) {
        $postEvaluationId = (int) $_POST['evaluation_id'];
        $approveStmt = $conn->prepare("UPDATE skill_matrix_evaluations sme
                                       INNER JOIN user creator ON creator.id = sme.created_by
                                       SET sme.approval_status = 'APPROVED',
                                           sme.approved_by = ?,
                                           sme.approved_at = NOW()
                                       WHERE sme.id = ?
                                       AND sme.approval_status = 'PENDING'
                                       AND creator.hodid = ?
                                       AND (
                                           (
                                               creator.designation = ?
                                               AND (
                                                   (creator.roletype = '' AND creator.usertype = '')
                                                   OR (creator.roletype = 'CLERK' AND creator.usertype = 'MAIN')
                                               )
                                           )
                                           OR EXISTS (SELECT 1 FROM skill_matrix_whitelist w WHERE w.staffno = creator.staffno COLLATE utf8mb4_0900_ai_ci)
                                       )");
        $creatorDesignation = "MANAGER (AM/HOS & ABOVE)";
        $approveStmt->bind_param("iiis", $hodId, $postEvaluationId, $hodId, $creatorDesignation);
        $approveStmt->execute();

        if ($approveStmt->affected_rows > 0) {
            header("Location: evaluation-matrix.php?evaluation_id=" . urlencode($postEvaluationId) . "&approved=1" . ($fromPrevious ? '&period=previous' : ''));
            exit();
        }

        $saveError = 'Unable to approve this skill matrix.';
    }

    if (isset($_GET['approved']) && $_GET['approved'] == '1') {
        $saveMessage = 'Skill matrix approved successfully.';
    }

    $stmt = $conn->prepare("SELECT
                                sme.id,
                                sme.evaluation_date,
                                sme.approval_status,
                                target.staffno AS target_staffno,
                                target.staffname AS target_staffname,
                                target.department AS target_department,
                                target.section AS target_section,
                                target.designation AS target_designation,
                                target.grade AS target_grade,
                                creator.staffname AS created_by_name,
                                verifier.staffname AS verified_by_name,
                                approver.staffname AS approved_by_name
                            FROM skill_matrix_evaluations sme
                            INNER JOIN user target ON target.id = sme.staffid
                            INNER JOIN user creator ON creator.id = sme.created_by
                            LEFT JOIN user verifier ON verifier.id = creator.hodid
                            LEFT JOIN user approver ON approver.id = sme.approved_by
                            WHERE sme.id = ?
                            AND sme.approval_status IS NOT NULL
                            AND creator.hodid = ?
                            AND (
                                (
                                    creator.designation = ?
                                    AND (
                                        (creator.roletype = '' AND creator.usertype = '')
                                        OR (creator.roletype = 'CLERK' AND creator.usertype = 'MAIN')
                                    )
                                )
                                OR EXISTS (SELECT 1 FROM skill_matrix_whitelist w WHERE w.staffno = creator.staffno COLLATE utf8mb4_0900_ai_ci)
                            )
                            LIMIT 1");
    $creatorDesignation = "MANAGER (AM/HOS & ABOVE)";
    $stmt->bind_param("iis", $evaluationId, $hodId, $creatorDesignation);
    $stmt->execute();
    $evaluation = $stmt->get_result()->fetch_assoc();
    $topics = array();

    if ($evaluation) {
        $topicStmt = $conn->prepare("SELECT
                                        t.id AS topic_id,
                                        t.section_type,
                                        t.topic_name,
                                        i.evaluation_text,
                                        i.rating
                                    FROM skill_matrix_topics t
                                    LEFT JOIN skill_matrix_items i ON i.topic_id = t.id
                                    WHERE t.evaluation_id = ?
                                    ORDER BY FIELD(t.section_type, 'knowledge', 'skill', 'ability'), t.sort_order, i.sort_order");
        $topicStmt->bind_param("i", $evaluationId);
        $topicStmt->execute();
        $topicResult = $topicStmt->get_result();

        while ($row = $topicResult->fetch_assoc()) {
            $sectionType = $row['section_type'];
            $topicId = $row['topic_id'];
            if (!isset($topics[$sectionType])) {
                $topics[$sectionType] = array();
            }
            if (!isset($topics[$sectionType][$topicId])) {
                $topics[$sectionType][$topicId] = array(
                    'topic_name' => $row['topic_name'],
                    'items' => array()
                );
            }
            if ($row['evaluation_text'] !== null) {
                $topics[$sectionType][$topicId]['items'][] = array(
                    'evaluation_text' => $row['evaluation_text'],
                    'rating' => $row['rating']
                );
            }
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
                    <li><a href="../pme/pme.php">PME</a></li>
                    <li><a href="../tna/staff_list.php">TNA</a></li>
                    <li><a href="../tni/tni.php">TNI</a></li>
                    <li class="active"><a href="skill-matrix.php">SKILL MATRIX</a></li>
                    <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $_SESSION['fullname']; ?></a>
                        <ul class="dropdown-menu">
                            <li><a href="../../../logout.php">LOGOUT</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-8" style="margin-top: 10px;"><strong>Skill Matrix</strong></div>
                    <div class="col-md-4" align="right">
                        <a href="skill-matrix.php<?php echo $fromPrevious ? '?period=previous' : ''; ?>" class="btn btn-success btn-md"><i class="far fa-arrow-alt-circle-left"></i> BACK</a>
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

                <?php if (!$evaluation) { ?>
                    <div class="alert alert-warning">Skill matrix record not found.</div>
                <?php } else { ?>
                    <table class="table table-bordered">
                        <tr>
                            <th width="15%">Staff No.</th>
                            <td><?php echo htmlspecialchars($evaluation['target_staffno']); ?></td>
                            <th width="15%">Staff Name</th>
                            <td><?php echo htmlspecialchars($evaluation['target_staffname']); ?></td>
                        </tr>
                        <tr>
                            <th>Department</th>
                            <td><?php echo htmlspecialchars($evaluation['target_department']); ?></td>
                            <th>Section</th>
                            <td><?php echo htmlspecialchars($evaluation['target_section']); ?></td>
                        </tr>
                        <tr>
                            <th>Filled By</th>
                            <td><?php echo htmlspecialchars($evaluation['created_by_name']); ?></td>
                            <th>Approval Status</th>
                            <td>
                                <?php if ($evaluation['approval_status'] == 'APPROVED') { ?>
                                    <span class="label label-success">APPROVED</span>
                                <?php } else { ?>
                                    <span class="label label-warning">WAITING APPROVAL</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Evaluated By</th>
                            <td><?php echo htmlspecialchars($evaluation['created_by_name']); ?></td>
                            <th>Verified By</th>
                            <td><?php echo htmlspecialchars($evaluation['verified_by_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Approved By</th>
                            <td colspan="3"><?php echo htmlspecialchars($evaluation['approved_by_name']); ?></td>
                        </tr>
                    </table>

                    <?php
                    $sectionTitles = array(
                        'knowledge' => 'A. Knowledge Section',
                        'skill' => 'B. Skill Section',
                        'ability' => 'C. Abilities Section'
                    );
                    ?>
                    <?php foreach ($sectionTitles as $sectionKey => $sectionTitle) { ?>
                        <div class="panel panel-default">
                            <div class="panel-heading"><strong><?php echo $sectionTitle; ?></strong></div>
                            <div class="panel-body">
                                <?php if (isset($topics[$sectionKey]) && count($topics[$sectionKey]) > 0) { ?>
                                    <?php foreach ($topics[$sectionKey] as $topic) { ?>
                                        <h4><?php echo htmlspecialchars($topic['topic_name']); ?></h4>
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th width="5%">No.</th>
                                                    <th>Evaluation</th>
                                                    <th width="25%">Rating</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topic['items'] as $itemIndex => $item) { ?>
                                                    <tr>
                                                        <td class="text-center"><?php echo $itemIndex + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($item['evaluation_text']); ?></td>
                                                        <td><?php echo htmlspecialchars(ratingLabel($item['rating'])); ?></td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    <?php } ?>
                                <?php } else { ?>
                                    <div class="alert alert-warning">No records found.</div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ($evaluation['approval_status'] != 'APPROVED') { ?>
                        <form method="post" align="right">
                            <input type="hidden" name="evaluation_id" value="<?php echo (int) $evaluation['id']; ?>">
                            <button type="submit" class="btn btn-success btn-md">
                                APPROVE SKILL MATRIX <i class="fa fa-check"></i>
                            </button>
                        </form>
                    <?php } ?>
                <?php } ?>
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

    <?php if ($saveMessage != '') { ?>
        swal({ title: "Approved", text: "<?php echo addslashes($saveMessage); ?>", icon: "success", timer: 3000, buttons: false });
    <?php } ?>
</script>
</html>
<?php
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>
