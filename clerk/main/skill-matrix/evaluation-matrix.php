<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";

function skillMatrixRatingLabel($rating)
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
    $staffid = isset($_GET['staffid']) ? $_GET['staffid'] : '';
    $staff = null;
    $evaluationDate = date('d/m/Y');
    $displayEvaluationDate = $evaluationDate;
    $saveMessage = '';
    $saveError = '';
    $department = $_SESSION['department'];
    list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
    // ?period=previous shows the quarter before the evaluation quarter, view only.
    $viewingPrevious = isset($_GET['period']) && $_GET['period'] == 'previous';
    if ($viewingPrevious) {
        list($currentYear, $currentQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);
    }
    $hasCurrentQuarterEvaluation = false;
    $currentApprovalStatus = null;
    $editRequested = !$viewingPrevious && isset($_GET['edit']) && $_GET['edit'] == '1';
    $viewEvaluationId = null;
    $viewTopics = array(
        'knowledge' => array(),
        'skill' => array(),
        'ability' => array()
    );
    $signOff = array(
        'evaluated_by' => isset($_SESSION['fullname']) ? $_SESSION['fullname'] : '',
        'verified_by' => '',
        'approved_by' => ''
    );
    $repopulateData = array(
        'knowledge' => array(),
        'skill' => array(),
        'ability' => array()
    );

    if (isset($_SESSION['hodid']) && (int) $_SESSION['hodid'] > 0) {
        $hodNameStmt = $conn->prepare("SELECT staffname FROM user WHERE id = ? LIMIT 1");
        if ($hodNameStmt) {
            $sessionHodId = (int) $_SESSION['hodid'];
            $hodNameStmt->bind_param("i", $sessionHodId);
            $hodNameStmt->execute();
            $hodNameRow = $hodNameStmt->get_result()->fetch_assoc();
            if ($hodNameRow) {
                $signOff['verified_by'] = $hodNameRow['staffname'];
            }
        }
    }

    if ($staffid != '') {
        $stmt = $conn->prepare("SELECT
                                    u.staffno,
                                    u.staffname,
                                    u.designation,
                                    u.grade,
                                    COALESCE(dp.name, u.department) AS department,
                                    COALESCE(s.name, u.section) AS section
                                FROM user u
                                LEFT JOIN departments dp ON u.department_id = dp.id
                                LEFT JOIN sections s ON u.section_id = s.id
                                WHERE u.id = ?
                                AND u.department = ?");
        $stmt->bind_param("is", $staffid, $department);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();

        if ($staff) {
            $quarterStmt = $conn->prepare("SELECT id, evaluation_date, created_by, approved_by, approval_status FROM skill_matrix_evaluations WHERE staffid = ? AND eval_year = ? AND eval_quarter = ? ORDER BY evaluation_date DESC, id DESC LIMIT 1");
            $quarterStmt->bind_param("iii", $staffid, $currentYear, $currentQuarter);
            $quarterStmt->execute();
            $quarterResult = $quarterStmt->get_result()->fetch_assoc();
            $hasCurrentQuarterEvaluation = $quarterResult ? true : false;

            if ($hasCurrentQuarterEvaluation) {
                $currentApprovalStatus = $quarterResult['approval_status'];
                $viewEvaluationId = (int) $quarterResult['id'];
                $displayEvaluationDate = date('d/m/Y', strtotime($quarterResult['evaluation_date']));
                $signOffStmt = $conn->prepare("SELECT
                                                    creator.staffname AS evaluated_by,
                                                    verifier.staffname AS verified_by,
                                                    approver.staffname AS approved_by
                                                FROM skill_matrix_evaluations sme
                                                LEFT JOIN user creator ON creator.id = sme.created_by
                                                LEFT JOIN user verifier ON verifier.id = creator.hodid
                                                LEFT JOIN user approver ON approver.id = sme.approved_by
                                                WHERE sme.id = ?");
                if ($signOffStmt) {
                    $signOffStmt->bind_param("i", $viewEvaluationId);
                    $signOffStmt->execute();
                    $signOffRow = $signOffStmt->get_result()->fetch_assoc();
                    if ($signOffRow) {
                        $signOff['evaluated_by'] = $signOffRow['evaluated_by'] ? $signOffRow['evaluated_by'] : '';
                        $signOff['verified_by'] = $signOffRow['verified_by'] ? $signOffRow['verified_by'] : '';
                        $signOff['approved_by'] = $signOffRow['approved_by'] ? $signOffRow['approved_by'] : '';
                    }
                }
                $viewStmt = $conn->prepare("SELECT
                                                t.id AS topic_id,
                                                t.section_type,
                                                t.topic_name,
                                                t.sort_order AS topic_sort_order,
                                                i.evaluation_text,
                                                i.rating,
                                                i.sort_order AS item_sort_order
                                            FROM skill_matrix_topics t
                                            LEFT JOIN skill_matrix_items i ON i.topic_id = t.id
                                            WHERE t.evaluation_id = ?
                                            ORDER BY FIELD(t.section_type, 'knowledge', 'skill', 'ability'), t.sort_order, i.sort_order");
                $viewStmt->bind_param("i", $viewEvaluationId);
                $viewStmt->execute();
                $viewResult = $viewStmt->get_result();

                while ($viewRow = $viewResult->fetch_assoc()) {
                    $sectionType = $viewRow['section_type'];
                    $topicId = $viewRow['topic_id'];

                    if (!isset($viewTopics[$sectionType][$topicId])) {
                        $viewTopics[$sectionType][$topicId] = array(
                            'topic_name' => $viewRow['topic_name'],
                            'items' => array()
                        );
                    }

                    if ($viewRow['evaluation_text'] !== null) {
                        $viewTopics[$sectionType][$topicId]['items'][] = array(
                            'evaluation_text' => $viewRow['evaluation_text'],
                            'rating' => $viewRow['rating']
                        );
                    }
                }
            }

            $canEditExisting = $hasCurrentQuarterEvaluation && $editRequested && $currentApprovalStatus === null;

            if ($canEditExisting && $_SERVER['REQUEST_METHOD'] != 'POST') {
                foreach ($viewTopics as $sectionKey => $sectionTopics) {
                    foreach ($sectionTopics as $topic) {
                        $items = array();

                        foreach ($topic['items'] as $topicItem) {
                            $items[] = array(
                                'evaluation_text' => $topicItem['evaluation_text'],
                                'rating' => $topicItem['rating']
                            );
                        }

                        if (count($items) == 0) {
                            $items[] = array('evaluation_text' => '', 'rating' => '');
                        }

                        $repopulateData[$sectionKey][] = array(
                            'topic_name' => $topic['topic_name'],
                            'items' => $items
                        );
                    }
                }
            }
        }
    }

    $canEditExisting = isset($canEditExisting) ? $canEditExisting : false;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$viewingPrevious && $staff) {
        $formAction = isset($_POST['form_action']) && $_POST['form_action'] == 'submit' ? 'submit' : 'save';
        $sections = array('knowledge', 'skill', 'ability');
        $topicsToSave = array();
        $sectionTopicCounts = array(
            'knowledge' => 0,
            'skill' => 0,
            'ability' => 0
        );
        $errors = array();

        if ($hasCurrentQuarterEvaluation && !$canEditExisting) {
            $errors[] = 'Skill matrix already submitted for Q' . $currentQuarter . ' ' . $currentYear . '.';
        }

        if (!$hasCurrentQuarterEvaluation || $canEditExisting) {
            foreach ($sections as $section) {
                $topics = isset($_POST[$section . '_topic']) ? $_POST[$section . '_topic'] : array();
                $evaluations = isset($_POST[$section . '_evaluation']) ? $_POST[$section . '_evaluation'] : array();
                $ratings = isset($_POST[$section . '_rating']) ? $_POST[$section . '_rating'] : array();
                $topicSortOrder = 1;

                foreach ($topics as $topicIndex => $topicName) {
                    $topicName = trim($topicName);
                    $topicEvaluations = isset($evaluations[$topicIndex]) ? $evaluations[$topicIndex] : array();
                    $topicRatings = isset($ratings[$topicIndex]) ? $ratings[$topicIndex] : array();
                    $items = array();
                    $hasAnyValue = $topicName != '';

                    foreach ($topicEvaluations as $rowIndex => $evaluationText) {
                        $evaluationText = trim($evaluationText);
                        $rating = isset($topicRatings[$rowIndex]) ? trim($topicRatings[$rowIndex]) : '';

                        if ($evaluationText != '' || $rating != '') {
                            $hasAnyValue = true;
                        }

                        if ($evaluationText == '' && $rating == '') {
                            continue;
                        }

                        if ($evaluationText == '' || $rating == '' || !in_array((int) $rating, array(1, 2, 3, 4, 5))) {
                            $errors[] = ucfirst($section) . ' topic ' . $topicSortOrder . ' has an incomplete evaluation row.';
                            continue;
                        }

                        $items[] = array(
                            'evaluation_text' => $evaluationText,
                            'rating' => (int) $rating,
                            'sort_order' => count($items) + 1
                        );
                    }

                    if (!$hasAnyValue) {
                        continue;
                    }

                    if ($topicName == '') {
                        $errors[] = ucfirst($section) . ' topic ' . $topicSortOrder . ' requires a topic name.';
                        continue;
                    }

                    if (count($items) == 0) {
                        $errors[] = ucfirst($section) . ' topic "' . $topicName . '" requires at least one complete evaluation.';
                        continue;
                    }

                    $topicsToSave[] = array(
                        'section_type' => $section,
                        'topic_name' => $topicName,
                        'sort_order' => $topicSortOrder,
                        'items' => $items
                    );
                    $sectionTopicCounts[$section]++;

                    $topicSortOrder++;
                }
            }

            foreach ($sections as $section) {
                if ($sectionTopicCounts[$section] == 0) {
                    $errors[] = 'Please fill in all section.';
                    break;
                }
            }
        }

        if (count($errors) > 0) {
            foreach ($sections as $section) {
                $topics = isset($_POST[$section . '_topic']) ? $_POST[$section . '_topic'] : array();
                $evaluations = isset($_POST[$section . '_evaluation']) ? $_POST[$section . '_evaluation'] : array();
                $ratings = isset($_POST[$section . '_rating']) ? $_POST[$section . '_rating'] : array();

                foreach ($topics as $topicIndex => $topicName) {
                    $topicEvaluations = isset($evaluations[$topicIndex]) ? $evaluations[$topicIndex] : array();
                    $topicRatings = isset($ratings[$topicIndex]) ? $ratings[$topicIndex] : array();
                    $items = array();

                    foreach ($topicEvaluations as $rowIndex => $evaluationText) {
                        $items[] = array(
                            'evaluation_text' => $evaluationText,
                            'rating' => isset($topicRatings[$rowIndex]) ? $topicRatings[$rowIndex] : ''
                        );
                    }

                    if (count($items) == 0) {
                        $items[] = array('evaluation_text' => '', 'rating' => '');
                    }

                    $repopulateData[$section][] = array(
                        'topic_name' => $topicName,
                        'items' => $items
                    );
                }
            }
        }

        if (count($errors) == 0) {
            mysqli_begin_transaction($conn);

            try {
                if ($canEditExisting) {
                    $evaluationId = $viewEvaluationId;

                    $deleteItemsStmt = $conn->prepare("DELETE i FROM skill_matrix_items i INNER JOIN skill_matrix_topics t ON t.id = i.topic_id WHERE t.evaluation_id = ?");
                    $deleteItemsStmt->bind_param("i", $evaluationId);
                    $deleteItemsStmt->execute();

                    $deleteTopicsStmt = $conn->prepare("DELETE FROM skill_matrix_topics WHERE evaluation_id = ?");
                    $deleteTopicsStmt->bind_param("i", $evaluationId);
                    $deleteTopicsStmt->execute();

                    if ($formAction == 'submit' && $currentApprovalStatus != 'PENDING') {
                        $submitStmt = $conn->prepare("UPDATE skill_matrix_evaluations SET approval_status = 'PENDING' WHERE id = ?");
                        $submitStmt->bind_param("i", $evaluationId);
                        $submitStmt->execute();
                    }
                } else {
                    $evaluationDateForDb = date('Y-m-d');
                    $createdBy = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
                    $initialApprovalStatus = $formAction == 'submit' ? 'PENDING' : null;

                    $evaluationStmt = $conn->prepare("INSERT INTO skill_matrix_evaluations (staffid, evaluation_date, eval_year, eval_quarter, created_by, approval_status) VALUES (?, ?, ?, ?, ?, ?)");
                    $evaluationStmt->bind_param("isiiis", $staffid, $evaluationDateForDb, $currentYear, $currentQuarter, $createdBy, $initialApprovalStatus);
                    $evaluationStmt->execute();
                    $evaluationId = $conn->insert_id;
                }

                $topicStmt = $conn->prepare("INSERT INTO skill_matrix_topics (evaluation_id, section_type, topic_name, sort_order) VALUES (?, ?, ?, ?)");
                $itemStmt = $conn->prepare("INSERT INTO skill_matrix_items (topic_id, evaluation_text, rating, sort_order) VALUES (?, ?, ?, ?)");

                foreach ($topicsToSave as $topic) {
                    $topicStmt->bind_param("issi", $evaluationId, $topic['section_type'], $topic['topic_name'], $topic['sort_order']);
                    $topicStmt->execute();
                    $topicId = $conn->insert_id;

                    foreach ($topic['items'] as $item) {
                        $itemStmt->bind_param("isii", $topicId, $item['evaluation_text'], $item['rating'], $item['sort_order']);
                        $itemStmt->execute();
                    }
                }

                mysqli_commit($conn);
                $redirectUrl = "evaluation-matrix.php?staffid=" . urlencode($staffid) . "&saved=1&submitted=" . ($formAction == 'submit' ? '1' : '0');
                if ($formAction == 'save') {
                    $redirectUrl .= "&edit=1";
                }
                header("Location: " . $redirectUrl);
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $saveError = 'Unable to save evaluation. Please try again.';
            }
        } else {
            $saveError = implode("\n", $errors);
        }
    }

    if (isset($_GET['saved']) && $_GET['saved'] == '1') {
        $saveMessage = (isset($_GET['submitted']) && $_GET['submitted'] == '1')
            ? 'Evaluation submitted for approval successfully.'
            : 'Evaluation saved as draft. It will not be sent for approval until you submit it.';
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
        <link rel="stylesheet" href="../../../asset/css/datepicker.css">
        <script src="../../../asset/js/bootstrap-datepicker1.js"></script>
    </head>

    <style>
        .evaluation-section {
            margin-top: 20px;
        }

        .topic-panel {
            margin-bottom: 15px;
        }

        .topic-actions {
            margin-top: 10px;
        }

        .section-toggle {
            color: inherit;
            display: block;
            text-decoration: none;
        }

        .section-toggle:hover,
        .section-toggle:focus {
            color: inherit;
            text-decoration: none;
        }

        .matrix-summary-row th,
        .matrix-summary-row td {
            background-color: #d9edf7;
            color: #31708f;
            font-weight: bold;
        }

        .staff-info-table th {
            background-color: #d9edf7;
            color: #31708f;
            vertical-align: middle !important;
        }

        .staff-info-table td {
            background-color: #f5faff;
            color: #333333;
            vertical-align: middle !important;
        }
    </style>

    <body onload="startTime()" style="background-image:url('../../../asset/image/bg-try.png');zoom: 75%;">
        <br>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10">
                    <img src="../../../asset/image/lndlogo.gif" height="50" width="290">
                </div>
                <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;">

                </div>
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
                                    <strong>Evaluation Matrix</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <a href="skill-matrix.php<?php echo $viewingPrevious ? '?period=previous' : ''; ?>" class="btn btn-success btn-md">
                                        <i class="far fa-arrow-alt-circle-left"></i> BACK TO SKILL MATRIX
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php if ($viewingPrevious) { ?>
                                <div class="alert alert-warning">
                                    <strong>Previous Quarter: Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?></strong>
                                    (<?php echo skillMatrixQuarterMonths($currentQuarter); ?> <?php echo $currentYear; ?>) - view only
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-info">
                                    <strong>Evaluation Quarter: Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?></strong>
                                    (<?php echo skillMatrixQuarterMonths($currentQuarter); ?> <?php echo $currentYear; ?>)
                                </div>
                            <?php } ?>

                            <?php if ($saveMessage != '') { ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($saveMessage); ?></div>
                            <?php } ?>

                            <?php if ($saveError != '') { ?>
                                <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($saveError)); ?></div>
                            <?php } ?>

                            <?php if ($staff) { ?>
                                <table class="table table-bordered staff-info-table">
                                    <tr>
                                        <th width="15%">Staff No.</th>
                                        <td><?php echo $staff['staffno']; ?></td>
                                        <th width="15%">Staff Name</th>
                                        <td><?php echo $staff['staffname']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Department</th>
                                        <td><?php echo $staff['department']; ?></td>
                                        <th>Section</th>
                                        <td><?php echo $staff['section']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Designation</th>
                                        <td><?php echo $staff['designation']; ?></td>
                                        <th>Grade</th>
                                        <td><?php echo $staff['grade']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Evaluation Date</th>
                                        <td colspan="3"><?php echo $displayEvaluationDate; ?></td>
                                    </tr>
                                </table>

                                <?php if ($hasCurrentQuarterEvaluation && !$canEditExisting) { ?>
                                    <?php
                                    $sectionTitles = array(
                                        'knowledge' => 'A. Knowledge Section',
                                        'skill' => 'B. Skill Section',
                                        'ability' => 'C. Abilities Section'
                                    );
                                    ?>

                                    <?php foreach ($sectionTitles as $sectionKey => $sectionTitle) { ?>
                                        <div class="panel panel-default evaluation-section">
                                            <div class="panel-heading">
                                                <strong><?php echo $sectionTitle; ?></strong>
                                            </div>
                                            <div class="panel-body">
                                                <?php if (count($viewTopics[$sectionKey]) > 0) { ?>
                                                    <?php foreach ($viewTopics[$sectionKey] as $topic) { ?>
                                                        <?php
                                                        $topicTotal = 0;
                                                        $topicCount = count($topic['items']);

                                                        foreach ($topic['items'] as $topicItem) {
                                                            $topicTotal += (int) $topicItem['rating'];
                                                        }

                                                        $topicAverage = $topicCount > 0 ? $topicTotal / $topicCount : 0;
                                                        $topicPercentage = $topicCount > 0 ? ($topicTotal / ($topicCount * 5)) * 100 : 0;
                                                        ?>
                                                        <div class="panel panel-default topic-panel">
                                                            <div class="panel-heading">
                                                                <strong><?php echo htmlspecialchars($topic['topic_name']); ?></strong>
                                                            </div>
                                                            <div class="panel-body">
                                                                <div class="table-responsive">
                                                                    <table class="table table-bordered table-striped">
                                                                        <thead>
                                                                            <tr>
                                                                                <th width="5%">No.</th>
                                                                                <th width="65%">Evaluation</th>
                                                                                <th width="30%">Rating</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($topic['items'] as $itemIndex => $item) { ?>
                                                                                <tr>
                                                                                    <td class="text-center"><?php echo $itemIndex + 1; ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['evaluation_text']); ?></td>
                                                                                    <td><?php echo htmlspecialchars(skillMatrixRatingLabel($item['rating'])); ?></td>
                                                                                </tr>
                                                                            <?php } ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                                <table class="table table-bordered">
                                                                    <tr class="matrix-summary-row">
                                                                        <th width="25%">Average</th>
                                                                        <td><?php echo number_format($topicAverage, 2); ?></td>
                                                                        <th width="25%">Percentage</th>
                                                                        <td><?php echo number_format($topicPercentage, 2); ?>%</td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <div class="alert alert-warning">No records found.</div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    <table class="table table-bordered staff-info-table">
                                        <tr>
                                            <th width="15%">Evaluated By</th>
                                            <td><?php echo htmlspecialchars($signOff['evaluated_by']); ?></td>
                                            <th width="15%">Verified By</th>
                                            <td><?php echo htmlspecialchars($signOff['verified_by']); ?></td>
                                            <th width="15%">Approved By</th>
                                            <td><?php echo htmlspecialchars($signOff['approved_by']); ?></td>
                                        </tr>
                                    </table>
                                <?php } else if ($viewingPrevious) { ?>
                                    <div class="alert alert-warning">This staff has no skill matrix for Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?>.</div>
                                <?php } else { ?>
                                <form method="post" id="evaluation_form">
                                    <input type="hidden" name="staffid" value="<?php echo $staffid; ?>">
                                    <input type="hidden" name="evaluation_date" value="<?php echo $evaluationDate; ?>">
                                    <input type="hidden" name="form_action" id="form_action_field" value="save">

                                    <div class="panel panel-default evaluation-section">
                                        <div class="panel-heading">
                                            <div class="row">
                                                <div class="col-md-8" style="margin-top: 7px;">
                                                    <a href="#knowledge_section" class="section-toggle" data-toggle="collapse">
                                                        <strong>A. Knowledge Section</strong>
                                                        <i class="fa fa-chevron-down"></i>
                                                    </a>
                                                </div>
                                                <div class="col-md-4" align="right">
                                                    <button type="button" class="btn btn-info btn-sm add-topic" data-section="knowledge">
                                                        <i class="fa fa-plus"></i> TOPIC
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="knowledge_section" class="panel-collapse collapse in">
                                            <div class="panel-body">
                                            <div id="knowledge_topics" class="topic-list" data-section="knowledge"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="panel panel-default evaluation-section">
                                        <div class="panel-heading">
                                            <div class="row">
                                                <div class="col-md-8" style="margin-top: 7px;">
                                                    <a href="#skill_section" class="section-toggle" data-toggle="collapse">
                                                        <strong>B. Skill Section</strong>
                                                        <i class="fa fa-chevron-down"></i>
                                                    </a>
                                                </div>
                                                <div class="col-md-4" align="right">
                                                    <button type="button" class="btn btn-info btn-sm add-topic" data-section="skill">
                                                        <i class="fa fa-plus"></i> TOPIC
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="skill_section" class="panel-collapse collapse in">
                                            <div class="panel-body">
                                            <div id="skill_topics" class="topic-list" data-section="skill"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="panel panel-default evaluation-section">
                                        <div class="panel-heading">
                                            <div class="row">
                                                <div class="col-md-8" style="margin-top: 7px;">
                                                    <a href="#ability_section" class="section-toggle" data-toggle="collapse">
                                                        <strong>C. Abilities Section</strong>
                                                        <i class="fa fa-chevron-down"></i>
                                                    </a>
                                                </div>
                                                <div class="col-md-4" align="right">
                                                    <button type="button" class="btn btn-info btn-sm add-topic" data-section="ability">
                                                        <i class="fa fa-plus"></i> TOPIC
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="ability_section" class="panel-collapse collapse in">
                                            <div class="panel-body">
                                            <div id="ability_topics" class="topic-list" data-section="ability"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <table class="table table-bordered staff-info-table">
                                        <tr>
                                            <th width="15%">Evaluated By</th>
                                            <td><?php echo htmlspecialchars($signOff['evaluated_by']); ?></td>
                                            <th width="15%">Verified By</th>
                                            <td><?php echo htmlspecialchars($signOff['verified_by']); ?></td>
                                            <th width="15%">Approved By</th>
                                            <td><?php echo htmlspecialchars($signOff['approved_by']); ?></td>
                                        </tr>
                                    </table>

                                    <div align="right">
                                        <button type="submit" class="btn btn-default btn-md">
                                            SAVE <i class="fa fa-save"></i>
                                        </button>
                                        <button type="button" id="submit_for_approval_btn" class="btn btn-success btn-md">
                                            SUBMIT FOR APPROVAL <i class="fa fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                                <?php } ?>
                            <?php } else { ?>
                                <div class="alert alert-warning">Staff record not found.</div>
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

        var topicCounts = {
            knowledge: 0,
            skill: 0,
            ability: 0
        };

        function sectionLabel(section) {
            if (section == 'knowledge') {
                return 'Knowledge';
            }

            if (section == 'skill') {
                return 'Skill';
            }

            return 'Ability';
        }

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : value).html().replace(/"/g, '&quot;');
        }

        function ratingOptions(selectedRating) {
            selectedRating = String(selectedRating == null ? '' : selectedRating);
            var options = [
                ['', '-- Select Rating --'],
                ['1', '1 - Beginner'],
                ['2', '2 - Basic'],
                ['3', '3 - Competent'],
                ['4', '4 - Advanced'],
                ['5', '5 - Expert']
            ];
            var html = '';

            for (var i = 0; i < options.length; i++) {
                var value = options[i][0];
                var label = options[i][1];
                var selected = (value === selectedRating) ? ' selected' : '';
                html += '<option value="' + value + '"' + selected + '>' + label + '</option>';
            }

            return html;
        }

        function addEvaluationRow(section, topicIndex, evaluationText, rating) {
            var tbody = $('#' + section + '_topic_' + topicIndex + ' tbody');
            if (tbody.find('tr').length >= 5) {
                swal("Maximum reached", "Only 5 evaluations are allowed per topic.", "warning");
                return;
            }

            var rowNo = tbody.find('tr').length + 1;
            var row = '<tr>' +
                '<td class="text-center evaluation-no">' + rowNo + '</td>' +
                '<td><input type="text" name="' + section + '_evaluation[' + topicIndex + '][]" class="form-control" value="' + escapeHtml(evaluationText) + '"></td>' +
                '<td><select name="' + section + '_rating[' + topicIndex + '][]" class="form-control">' + ratingOptions(rating) + '</select></td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-danger btn-sm remove-evaluation"><i class="fa fa-trash"></i></button>' +
                '</td>' +
                '</tr>';

            tbody.append(row);
        }

        function refreshEvaluationNumbers(tbody) {
            tbody.find('.evaluation-no').each(function (index) {
                $(this).text(index + 1);
            });
        }

        function addTopic(section, topicName) {
            topicCounts[section]++;
            var topicIndex = topicCounts[section];
            var label = sectionLabel(section);
            var topic = '<div class="panel panel-default topic-panel" id="' + section + '_topic_' + topicIndex + '">' +
                '<div class="panel-heading">' +
                '<div class="row">' +
                '<div class="col-md-8" style="margin-top: 7px;">' +
                '<strong>' + label + ' Topic</strong>' +
                '</div>' +
                '<div class="col-md-4" align="right">' +
                '<button type="button" class="btn btn-info btn-sm add-evaluation" data-section="' + section + '" data-topic="' + topicIndex + '">' +
                '<i class="fa fa-plus"></i> EVALUATION' +
                '</button> ' +
                '<button type="button" class="btn btn-danger btn-sm remove-topic">' +
                '<i class="fa fa-trash"></i>' +
                '</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="panel-body">' +
                '<div class="form-group">' +
                '<label>Topic</label>' +
                '<input type="text" name="' + section + '_topic[' + topicIndex + ']" class="form-control" value="' + escapeHtml(topicName) + '">' +
                '</div>' +
                '<div class="table-responsive">' +
                '<table class="table table-bordered table-striped">' +
                '<thead>' +
                '<tr>' +
                '<th width="5%">No.</th>' +
                '<th width="55%">Evaluation</th>' +
                '<th width="30%">Rating</th>' +
                '<th width="10%">Action</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody></tbody>' +
                '</table>' +
                '</div>' +
                '</div>' +
                '</div>';

            $('#' + section + '_topics').append(topic);

            return topicIndex;
        }

        $('.add-topic').click(function () {
            var section = $(this).data('section');
            var topicIndex = addTopic(section);
            addEvaluationRow(section, topicIndex);
        });

        $(document).on('click', '.add-evaluation', function () {
            addEvaluationRow($(this).data('section'), $(this).data('topic'));
        });

        $(document).on('click', '.remove-evaluation', function () {
            var tbody = $(this).closest('tbody');
            $(this).closest('tr').remove();
            refreshEvaluationNumbers(tbody);
        });

        $(document).on('click', '.remove-topic', function () {
            $(this).closest('.topic-panel').remove();
        });

        $('#submit_for_approval_btn').click(function () {
            swal({
                title: "Submit for Approval?",
                text: "Once submitted, this skill matrix cannot be edited until it has been reviewed. Are you sure you want to submit?",
                icon: "warning",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Submit"]
            })
            .then((isConfirm) => {
                if (isConfirm) {
                    $('#form_action_field').val('submit');
                    $('#evaluation_form').get(0).submit();
                }
            });
        });

        setTimeout(function () {
            $('.alert-success, .alert-danger').fadeOut();
        }, 3000);

        <?php if (isset($_GET['saved']) && $_GET['saved'] == '1') { ?>
            swal({
                title: "<?php echo (isset($_GET['submitted']) && $_GET['submitted'] == '1') ? 'Submitted' : 'Saved'; ?>",
                text: "<?php echo addslashes($saveMessage); ?>",
                icon: "success",
                timer: 3000,
                buttons: false
            });
        <?php } ?>

        <?php if ($saveError != '') { ?>
            swal({
                title: "Incomplete evaluation",
                text: "<?php echo str_replace(array("\r", "\n"), '\n', addslashes($saveError)); ?>",
                icon: "warning",
                timer: 3000,
                buttons: false
            });
        <?php } ?>

        var repopulateData = <?php echo json_encode($repopulateData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        $.each(['knowledge', 'skill', 'ability'], function (i, section) {
            var topics = repopulateData[section];

            if (topics && topics.length > 0) {
                $.each(topics, function (j, topic) {
                    var topicIndex = addTopic(section, topic.topic_name);

                    $.each(topic.items, function (k, item) {
                        addEvaluationRow(section, topicIndex, item.evaluation_text, item.rating);
                    });
                });
            } else {
                var topicIndex = addTopic(section);
                addEvaluationRow(section, topicIndex);
            }
        });
    </script>

    </html>
    <?php
} else {
    header("Location: ../../../login.php");
    exit();
}
?>
