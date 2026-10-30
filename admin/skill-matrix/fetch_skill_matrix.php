<?php
session_start();
include "../../dbconn.php";
include "../../skill_matrix_period.php";

header('Content-Type: application/json; charset=utf-8');

function skillMatrixRespondJson($data)
{
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data);
    exit();
}

include_once __DIR__ . '/../../skill_matrix_department.php';

function skillMatrixUserCanUse()
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

    if (!empty($_SESSION['is_sm_user']) && isset($_SESSION['fullname'])) return true;

    return isset($_SESSION['fullname'], $_SESSION['role'], $_SESSION['designation'], $_SESSION['usertype'], $_SESSION['hodid'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && (
            ($_SESSION['role'] == '' && $_SESSION['usertype'] == '') ||
            ($_SESSION['role'] == 'CLERK' && $_SESSION['usertype'] == 'MAIN')
        );
}

function skillMatrixBindParams($stmt, $types, $params)
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// WHERE conditions (on user u, departments dp, sections s) for the staff the skill
// matrix covers: non executive / contract, not resigned, in a switched-on department,
// narrowed by the department / section / plant filter. Skill matrix users only get
// their own department. Returns array($sql, $types, $params), or null when a skill
// matrix user has no department.
function skillMatrixStaffFilter($conn, $department, $section, $plant)
{
    if (skillMatrixUserCanUse()) {
        if (!isset($_SESSION['department']) || $_SESSION['department'] == '') {
            $sessionUserId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
            $departmentStmt = $conn->prepare("SELECT department FROM user WHERE id = ?");
            if ($departmentStmt) {
                $departmentStmt->bind_param("i", $sessionUserId);
                $departmentStmt->execute();
                $departmentRow = $departmentStmt->get_result()->fetch_assoc();
                if ($departmentRow) {
                    $_SESSION['department'] = $departmentRow['department'];
                }
            }
        }

        $department = isset($_SESSION['department']) ? $_SESSION['department'] : "";
        if ($department == "") {
            return null;
        }
    }

    $sql = "u.designation IN (?, ?)
            AND u.status != ?";

    // Only departments switched on in Organization > Skill Matrix Config.
    $sql .= skillMatrixEnabledDepartmentSql(skillMatrixIncludeOffDepartments());

    $types = "sss";
    $params = ["NON EXECUTIVE", "CONTRACT", "RESIGN"];

    if ($department != "" && $department != "ALL") {
        $sql .= " AND (dp.name = ? OR u.department = ?)";
        $types .= "ss";
        $params[] = $department;
        $params[] = $department;
    }

    if ($section != "" && $section != "ALL") {
        $sql .= " AND (s.name = ? OR u.section = ?)";
        $types .= "ss";
        $params[] = $section;
        $params[] = $section;
    }

    if ($plant != "" && $plant != "ALL") {
        $sql .= " AND u.plant = ?";
        $types .= "s";
        $params[] = $plant;
    }

    return array($sql, $types, $params);
}

// Overall score of each evaluation: the average of its topic percentages, worked out
// the same way as the TOTAL on matrix-chart.php. Returns array(evaluation_id => score).
function skillMatrixOverallScores($conn, $evaluationIds)
{
    $scores = array();
    if (count($evaluationIds) == 0) {
        return $scores;
    }

    $placeholders = implode(',', array_fill(0, count($evaluationIds), '?'));
    $topicSql = "SELECT
                    t.evaluation_id,
                    SUM(i.rating) AS total_rating,
                    COUNT(i.id) AS item_count
                FROM skill_matrix_topics t
                INNER JOIN skill_matrix_items i ON i.topic_id = t.id
                WHERE t.evaluation_id IN ($placeholders)
                GROUP BY t.evaluation_id, t.section_type, t.topic_name";
    $topicStmt = $conn->prepare($topicSql);
    skillMatrixBindParams($topicStmt, str_repeat('i', count($evaluationIds)), $evaluationIds);
    $topicStmt->execute();
    $topicResult = $topicStmt->get_result();

    $scoreTotals = array();
    while ($topicRow = $topicResult->fetch_assoc()) {
        $evaluationId = (int) $topicRow['evaluation_id'];
        if (!isset($scoreTotals[$evaluationId])) {
            $scoreTotals[$evaluationId] = array('total' => 0, 'count' => 0);
        }
        $scoreTotals[$evaluationId]['total'] += $topicRow['item_count'] > 0 ? ($topicRow['total_rating'] / ($topicRow['item_count'] * 5)) * 100 : 0;
        $scoreTotals[$evaluationId]['count']++;
    }

    foreach ($scoreTotals as $evaluationId => $scoreTotal) {
        $scores[$evaluationId] = round($scoreTotal['total'] / $scoreTotal['count'], 2);
    }

    return $scores;
}

if (!isset($_SESSION['fullname']) || !isset($_POST["action"]) || ($_SESSION['role'] != 'ADMIN' && !skillMatrixUserCanUse())) {
    skillMatrixRespondJson(array());
}

if ($_POST["action"] == "load_departments") {
    // Department filter <option>s, as {options: html}: only departments switched on in
    // Organization > Skill Matrix Config, unless admin ticked "Include
    // switched-off departments" (those are then marked OFF).
    $includeOff = skillMatrixIncludeOffDepartments();
    $ready = skillMatrixDepartmentsReady();
    $sql = "SELECT dp.name" . ($ready ? ", dp.skill_matrix_enabled" : ", 1 AS skill_matrix_enabled") . "
            FROM departments dp
            WHERE 1 = 1" . skillMatrixEnabledDepartmentSql($includeOff) . "
            ORDER BY dp.name";
    $options = '<option value="" hidden>-- Select Department --</option><option value="ALL">All Department</option>';
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $name = htmlspecialchars($row['name'], ENT_QUOTES);
        $label = $name . ((int) $row['skill_matrix_enabled'] === 1 ? '' : ' (OFF)');
        $options .= '<option value="' . $name . '">' . $label . '</option>';
    }
    skillMatrixRespondJson(array('options' => $options));
}

if ($_POST["action"] == "load_sections_by_department") {
    $department = isset($_POST["department"]) ? $_POST["department"] : "ALL";

    if (skillMatrixUserCanUse() && (!isset($_SESSION['department']) || $_SESSION['department'] == '')) {
        $sessionUserId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
        $departmentStmt = $conn->prepare("SELECT department FROM user WHERE id = ?");
        if ($departmentStmt) {
            $departmentStmt->bind_param("i", $sessionUserId);
            $departmentStmt->execute();
            $departmentRow = $departmentStmt->get_result()->fetch_assoc();
            if ($departmentRow) {
                $_SESSION['department'] = $departmentRow['department'];
            }
        }
    }
    if (skillMatrixUserCanUse()) {
        $department = isset($_SESSION['department']) ? $_SESSION['department'] : "";
    }

    $output = array();
    $sql = "SELECT DISTINCT COALESCE(s.name COLLATE utf8mb4_general_ci, u.section COLLATE utf8mb4_general_ci) AS section
            FROM user u
            LEFT JOIN departments dp ON u.department_id = dp.id
            LEFT JOIN sections s ON u.section_id = s.id
            WHERE COALESCE(s.name COLLATE utf8mb4_general_ci, u.section COLLATE utf8mb4_general_ci) IS NOT NULL
            AND COALESCE(s.name COLLATE utf8mb4_general_ci, u.section COLLATE utf8mb4_general_ci) != ''";
    if ($department != "" && $department != "ALL") {
        $sql .= " AND (dp.name = ? OR u.department = ?)";
    }
    $sql .= " ORDER BY section";

    $stmt = $conn->prepare($sql);
    if ($department != "" && $department != "ALL") {
        $stmt->bind_param("ss", $department, $department);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $output[] = $row['section'];
    }
    skillMatrixRespondJson($output);
}

if ($_POST["action"] == "load_non_executive_staff") {
    $department = isset($_POST["department"]) ? $_POST["department"] : "ALL";
    $section = isset($_POST["section"]) ? $_POST["section"] : "ALL";
    $plant = isset($_POST["plant"]) ? $_POST["plant"] : "ALL";
    list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
    // period=previous lists the quarter before the evaluation quarter instead.
    $viewingPrevious = isset($_POST['period']) && $_POST['period'] == 'previous';
    $fillYear = $currentYear;
    $fillQuarter = $currentQuarter;
    $fillStaffIds = array();
    if ($viewingPrevious) {
        list($currentYear, $currentQuarter) = skillMatrixPreviousPeriod($fillYear, $fillQuarter);
        $fillStaffIds = skillMatrixStaffIdsWithEvaluation($conn, $fillYear, $fillQuarter);
    }
    $output = array();

    $staffFilter = skillMatrixStaffFilter($conn, $department, $section, $plant);
    if ($staffFilter === null) {
        skillMatrixRespondJson($output);
    }
    list($filterSql, $filterTypes, $filterParams) = $staffFilter;

    $sql = "SELECT
                u.id,
                u.staffno,
                u.staffname,
                u.designation,
                u.grade,
                u.status,
                u.plant,
                COALESCE(dp.name, u.department) AS department,
                COALESCE(s.name, u.section) AS section,
                EXISTS (
                    SELECT 1
                    FROM skill_matrix_evaluations sme
                    WHERE sme.staffid = u.id
                    AND sme.eval_year = ?
                    AND sme.eval_quarter = ?
                ) AS has_current_quarter_evaluation,
                (
                    SELECT sme.approval_status
                    FROM skill_matrix_evaluations sme
                    WHERE sme.staffid = u.id
                    AND sme.eval_year = ?
                    AND sme.eval_quarter = ?
                    ORDER BY sme.evaluation_date DESC, sme.id DESC
                    LIMIT 1
                ) AS approval_status,
                (
                    SELECT sme.id
                    FROM skill_matrix_evaluations sme
                    WHERE sme.staffid = u.id
                    AND sme.eval_year = ?
                    AND sme.eval_quarter = ?
                    ORDER BY sme.evaluation_date DESC, sme.id DESC
                    LIMIT 1
                ) AS evaluation_id
            FROM user u
            LEFT JOIN departments dp ON u.department_id = dp.id
            LEFT JOIN sections s ON u.section_id = s.id
            WHERE " . $filterSql . "
            ORDER BY department, u.staffname";

    $types = "iiiiii" . $filterTypes;
    $params = array_merge([$currentYear, $currentQuarter, $currentYear, $currentQuarter, $currentYear, $currentQuarter], $filterParams);

    $stmt = $conn->prepare($sql);
    skillMatrixBindParams($stmt, $types, $params);

    $stmt->execute();
    $result = $stmt->get_result();
    // evaluation_id => position in $output, for staff whose matrix is approved.
    $approvedOutputIndex = array();

    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'RESIGN') {
            $status = '<span class="label label-pill label-danger">NOT ACTIVE</span>';
        } else {
            $status = '<span class="label label-pill label-success">ACTIVE</span>';
        }

        if ($row['approval_status'] == 'APPROVED') {
            $approvalStatusRaw = 'APPROVED';
            $approvalStatus = '<span class="label label-pill label-success">APPROVED</span>';
        } else if ($row['approval_status'] == 'PENDING') {
            $approvalStatusRaw = 'WAITING APPROVAL';
            $approvalStatus = '<span class="label label-pill label-warning">WAITING APPROVAL</span>';
        } else if ($row['has_current_quarter_evaluation']) {
            $approvalStatusRaw = 'DRAFT';
            $approvalStatus = '<span class="label label-pill label-default">DRAFT</span>';
        } else {
            $approvalStatusRaw = 'NOT SUBMITTED';
            $approvalStatus = '<span class="label label-pill label-default">NOT SUBMITTED</span>';
        }

        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN';
        $isSubmitted = $row['approval_status'] == 'PENDING' || $row['approval_status'] == 'APPROVED';
        if ($row['has_current_quarter_evaluation']) {
            $action = '<div style="display: flex; gap: 6px; justify-content: center; white-space: nowrap;">';
            if ($isSubmitted || !($isAdmin || skillMatrixUserCanUse())) {
                $action .= '<a href="evaluation-matrix.php?staffid=' . $row['id'] . '" class="btn btn-default btn-sm"><i class="fa fa-search"></i> VIEW</a>';
            } else {
                $action .= '<a href="evaluation-matrix.php?staffid=' . $row['id'] . '&edit=1" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> EDIT</a>';
            }
            if ($isAdmin || skillMatrixUserCanUse()) {
                $action .= '<a href="duplicate-matrix.php?staffid=' . $row['id'] . '" class="btn btn-warning btn-sm"><i class="fa fa-copy"></i> DUPLICATE</a>';
            }
            $action .= '</div>';
        } else if ($isAdmin || skillMatrixUserCanUse()) {
            $action = '<a href="evaluation-matrix.php?staffid=' . $row['id'] . '" class="btn btn-info btn-sm"><i class="fa fa-edit"></i> FILL SKILL MATRIX</a>';
        } else {
            $action = '<span class="label label-pill label-warning">PENDING HOS</span>';
        }

        // Previous quarter tab: only staff who have a matrix for it, view only,
        // with a shortcut to duplicate it into the evaluation quarter.
        if ($viewingPrevious) {
            if (!$row['has_current_quarter_evaluation']) {
                continue;
            }

            $action = '<div style="display: flex; gap: 6px; justify-content: center; align-items: center; white-space: nowrap;">';
            $action .= '<a href="evaluation-matrix.php?staffid=' . $row['id'] . '&period=previous" class="btn btn-default btn-sm"><i class="fa fa-search"></i> VIEW</a>';
            if (isset($fillStaffIds[(int) $row['id']])) {
                $action .= '<span class="label label-pill label-default">Q' . $fillQuarter . ' ' . $fillYear . ' STARTED</span>';
            }
            if ($isAdmin || skillMatrixUserCanUse()) {
                $action .= '<a href="duplicate-matrix.php?staffid=' . $row['id'] . '&period=previous" class="btn btn-warning btn-sm"><i class="fa fa-copy"></i> DUPLICATE</a>';
            }
            $action .= '</div>';
        }

        $output[] = array(
            'id' => $row['id'],
            'staffno' => $row['staffno'],
            'staffname' => $row['staffname'],
            'department' => $row['department'],
            'section' => $row['section'],
            'plant' => $row['plant'],
            'grade' => $row['grade'],
            'status' => $status,
            'approval_status' => $approvalStatus,
            'approval_status_raw' => $approvalStatusRaw,
            'action' => $action,
            'overall_score' => null,
        );

        if ($row['approval_status'] == 'APPROVED') {
            $approvedOutputIndex[(int) $row['evaluation_id']] = count($output) - 1;
        }
    }

    // Overall score for the dashboard, approved matrices only.
    foreach (skillMatrixOverallScores($conn, array_keys($approvedOutputIndex)) as $evaluationId => $score) {
        $output[$approvedOutputIndex[$evaluationId]]['overall_score'] = $score;
    }

    skillMatrixRespondJson($output);
}

// Dashboard "Average Skill Score by Quarter": the average overall score of approved
// matrices for each of the last 8 quarters up to the evaluation quarter, for the same
// staff and filter as the staff list. Like the list, each staff's latest matrix in a
// quarter is the one that counts.
if ($_POST["action"] == "load_quarter_scores") {
    $department = isset($_POST["department"]) ? $_POST["department"] : "ALL";
    $section = isset($_POST["section"]) ? $_POST["section"] : "ALL";
    $plant = isset($_POST["plant"]) ? $_POST["plant"] : "ALL";
    $output = array();

    $staffFilter = skillMatrixStaffFilter($conn, $department, $section, $plant);
    if ($staffFilter === null) {
        skillMatrixRespondJson($output);
    }
    list($filterSql, $filterTypes, $filterParams) = $staffFilter;

    list($fillYear, $fillQuarter) = skillMatrixFillPeriod();
    $lastPeriodIndex = $fillYear * 4 + $fillQuarter - 1;
    $firstPeriodIndex = $lastPeriodIndex - 7;

    $sql = "SELECT
                sme.id,
                sme.eval_year,
                sme.eval_quarter
            FROM skill_matrix_evaluations sme
            INNER JOIN user u ON u.id = sme.staffid
            LEFT JOIN departments dp ON u.department_id = dp.id
            LEFT JOIN sections s ON u.section_id = s.id
            WHERE sme.approval_status = 'APPROVED'
            AND sme.eval_year * 4 + sme.eval_quarter - 1 BETWEEN ? AND ?
            AND sme.id = (
                SELECT latest.id
                FROM skill_matrix_evaluations latest
                WHERE latest.staffid = sme.staffid
                AND latest.eval_year = sme.eval_year
                AND latest.eval_quarter = sme.eval_quarter
                ORDER BY latest.evaluation_date DESC, latest.id DESC
                LIMIT 1
            )
            AND " . $filterSql;

    $stmt = $conn->prepare($sql);
    skillMatrixBindParams($stmt, "ii" . $filterTypes, array_merge([$firstPeriodIndex, $lastPeriodIndex], $filterParams));
    $stmt->execute();
    $result = $stmt->get_result();

    $evaluationPeriods = array();
    while ($row = $result->fetch_assoc()) {
        $evaluationPeriods[(int) $row['id']] = (int) $row['eval_year'] * 4 + (int) $row['eval_quarter'] - 1;
    }

    $periodTotals = array();
    foreach (skillMatrixOverallScores($conn, array_keys($evaluationPeriods)) as $evaluationId => $score) {
        $periodIndex = $evaluationPeriods[$evaluationId];
        if (!isset($periodTotals[$periodIndex])) {
            $periodTotals[$periodIndex] = array('total' => 0, 'count' => 0);
        }
        $periodTotals[$periodIndex]['total'] += $score;
        $periodTotals[$periodIndex]['count']++;
    }

    // From the first quarter with a result up to the evaluation quarter, so a quarter
    // with nothing approved still shows as a gap.
    if (count($periodTotals) > 0) {
        for ($periodIndex = min(array_keys($periodTotals)); $periodIndex <= $lastPeriodIndex; $periodIndex++) {
            $year = intdiv($periodIndex, 4);
            $quarter = $periodIndex % 4 + 1;
            $hasScore = isset($periodTotals[$periodIndex]);
            $output[] = array(
                'label' => 'Q' . $quarter . ' ' . $year,
                'average' => $hasScore ? round($periodTotals[$periodIndex]['total'] / $periodTotals[$periodIndex]['count'], 2) : null,
                'count' => $hasScore ? $periodTotals[$periodIndex]['count'] : 0,
            );
        }
    }

    skillMatrixRespondJson($output);
}

skillMatrixRespondJson(array());
?>
