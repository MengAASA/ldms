<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";
include "../../../skill_matrix_score.php";

// HOD copy of admin/skill-matrix/fetch_skill_matrix.php: the same staff, filters and
// figures for every department, so skill-matrix.php and skill-matrix-breakdown.php
// show the admin view. Only the Action column differs: VIEW (and the approve tick)
// is offered just for matrices submitted to this HOD, the same scope as
// evaluation-matrix.php and bulk_approve_skill_matrix.php.
header('Content-Type: application/json; charset=utf-8');

function skillMatrixRespondJson($data)
{
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data);
    exit();
}

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

function skillMatrixBindParams($stmt, $types, $params)
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// The department(s) this HOD heads, for department=MY (the My Department tab).
function hodDepartmentNames($conn, $hodId)
{
    $names = array();
    $stmt = $conn->prepare("SELECT name FROM departments WHERE hod_user_id = ? ORDER BY name");
    $stmt->bind_param("i", $hodId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $names[] = $row['name'];
    }
    return $names;
}

// WHERE conditions (on user u, departments dp, sections s) for the staff the skill
// matrix covers: non executive / contract, not resigned, in a switched-on department,
// narrowed by the department / section / plant filter. department=MY keeps the
// department(s) this HOD heads. Returns array($sql, $types, $params).
function skillMatrixStaffFilter($department, $section, $plant)
{
    global $conn;

    $sql = "u.designation IN (?, ?)
            AND u.status != ?";

    // Only departments switched on in Organization > Skill Matrix Config.
    $sql .= skillMatrixEnabledDepartmentSql(false);

    $types = "sss";
    $params = ["NON EXECUTIVE", "CONTRACT", "RESIGN"];

    if ($department == "MY") {
        // Two IN() lists rather than COALESCE(dp.name, u.department): the two columns
        // carry different collations.
        $names = hodDepartmentNames($conn, (int) $_SESSION['id']);
        if (count($names) == 0) {
            $names = array('');
        }
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $sql .= " AND (dp.name IN ($placeholders) OR u.department IN ($placeholders))";
        $types .= str_repeat('s', count($names) * 2);
        $params = array_merge($params, $names, $names);
    } else if ($department != "" && $department != "ALL") {
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

// Matrices submitted to this HOD in a quarter, as staff id => array(evaluation id => status),
// newest first. Same rule as evaluation-matrix.php: the creator reports to this HOD and
// may fill skill matrices.
function hodSubmittedEvaluations($conn, $hodId, $year, $quarter)
{
    $stmt = $conn->prepare("SELECT sme.id, sme.staffid, sme.approval_status
                            FROM skill_matrix_evaluations sme
                            INNER JOIN user creator ON creator.id = sme.created_by
                            WHERE creator.hodid = ?
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
                            AND sme.approval_status IS NOT NULL
                            AND sme.eval_year = ?
                            AND sme.eval_quarter = ?
                            ORDER BY sme.evaluation_date DESC, sme.id DESC");
    $creatorDesignation = "MANAGER (AM/HOS & ABOVE)";
    $stmt->bind_param("isii", $hodId, $creatorDesignation, $year, $quarter);
    $stmt->execute();
    $result = $stmt->get_result();

    $submitted = array();
    while ($row = $result->fetch_assoc()) {
        $submitted[(int) $row['staffid']][(int) $row['id']] = $row['approval_status'];
    }
    return $submitted;
}

if (!isset($_SESSION['fullname']) || !isset($_POST["action"]) || !canApproveSkillMatrix()) {
    skillMatrixRespondJson(array());
}

if ($_POST["action"] == "load_departments") {
    // Department filter <option>s, as {options: html}: departments switched on in
    // Organization > Skill Matrix Config.
    $sql = "SELECT dp.name
            FROM departments dp
            WHERE 1 = 1" . skillMatrixEnabledDepartmentSql(false) . "
            ORDER BY dp.name";
    $options = '<option value="" hidden>-- Select Department --</option><option value="ALL">All Department</option>';
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $name = htmlspecialchars($row['name'], ENT_QUOTES);
        $options .= '<option value="' . $name . '">' . $name . '</option>';
    }
    skillMatrixRespondJson(array('options' => $options));
}

if ($_POST["action"] == "load_sections_by_department") {
    $department = isset($_POST["department"]) ? $_POST["department"] : "ALL";

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
    $hodId = (int) $_SESSION['id'];
    $department = isset($_POST["department"]) ? $_POST["department"] : "ALL";
    $section = isset($_POST["section"]) ? $_POST["section"] : "ALL";
    $plant = isset($_POST["plant"]) ? $_POST["plant"] : "ALL";
    list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
    // period=previous lists the quarter before the evaluation quarter instead.
    $viewingPrevious = isset($_POST['period']) && $_POST['period'] == 'previous';
    if ($viewingPrevious) {
        list($currentYear, $currentQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);
    }
    $output = array();
    $submitted = hodSubmittedEvaluations($conn, $hodId, $currentYear, $currentQuarter);

    list($filterSql, $filterTypes, $filterParams) = skillMatrixStaffFilter($department, $section, $plant);

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
        // Previous quarter tab: only staff who have a matrix for it.
        if ($viewingPrevious && !$row['has_current_quarter_evaluation']) {
            continue;
        }

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

        // The matrix this HOD can open: the latest one if it was submitted to them,
        // otherwise their newest submitted one for this staff.
        $viewEvaluationId = null;
        $staffSubmitted = isset($submitted[(int) $row['id']]) ? $submitted[(int) $row['id']] : array();
        if ($row['evaluation_id'] !== null && isset($staffSubmitted[(int) $row['evaluation_id']])) {
            $viewEvaluationId = (int) $row['evaluation_id'];
        } else if (count($staffSubmitted) > 0) {
            $viewEvaluationId = (int) key($staffSubmitted);
        }

        if ($viewEvaluationId !== null) {
            $action = '<a href="evaluation-matrix.php?evaluation_id=' . $viewEvaluationId . ($viewingPrevious ? '&period=previous' : '') .
                '" class="btn btn-info btn-sm"><i class="fa fa-search"></i> VIEW</a>';
        } else {
            $action = '<span class="text-muted">-</span>';
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
            // Set when the matrix is waiting for this HOD's approval (Approve Selected tick).
            'approve_evaluation_id' => $viewEvaluationId !== null && $staffSubmitted[$viewEvaluationId] == 'PENDING' ? $viewEvaluationId : null,
        );

        if ($row['approval_status'] == 'APPROVED') {
            $approvedOutputIndex[(int) $row['evaluation_id']] = count($output) - 1;
        }
    }

    // Overall score for the dashboard and Skill Score column, approved matrices only.
    foreach (skillMatrixOverallScores($conn, array_keys($approvedOutputIndex)) as $evaluationId => $score) {
        $output[$approvedOutputIndex[$evaluationId]]['overall_score'] = $score;
    }

    skillMatrixRespondJson($output);
}

// Every matrix waiting for this HOD's approval in the quarter, for Approve All Pending.
// outside_list marks staff the staff list doesn't cover (executives, resigned staff,
// switched-off departments), so the page can still offer VIEW for them.
if ($_POST["action"] == "load_pending_approvals") {
    list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
    $viewingPrevious = isset($_POST['period']) && $_POST['period'] == 'previous';
    if ($viewingPrevious) {
        list($currentYear, $currentQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);
    }
    $pendingIds = array();
    foreach (hodSubmittedEvaluations($conn, (int) $_SESSION['id'], $currentYear, $currentQuarter) as $staffEvaluations) {
        foreach ($staffEvaluations as $evaluationId => $approvalStatus) {
            if ($approvalStatus == 'PENDING') {
                $pendingIds[] = $evaluationId;
            }
        }
    }

    $pending = array();
    if (count($pendingIds) > 0) {
        list($listSql, $listTypes, $listParams) = skillMatrixStaffFilter('ALL', 'ALL', 'ALL');
        $placeholders = implode(',', array_fill(0, count($pendingIds), '?'));
        $stmt = $conn->prepare("SELECT
                                    sme.id,
                                    u.staffno,
                                    u.staffname,
                                    COALESCE(dp.name, u.department) AS department,
                                    ($listSql) AS in_list
                                FROM skill_matrix_evaluations sme
                                INNER JOIN user u ON u.id = sme.staffid
                                LEFT JOIN departments dp ON u.department_id = dp.id
                                LEFT JOIN sections s ON u.section_id = s.id
                                WHERE sme.id IN ($placeholders)
                                ORDER BY u.staffname");
        skillMatrixBindParams($stmt, $listTypes . str_repeat('i', count($pendingIds)), array_merge($listParams, $pendingIds));
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $pending[] = array(
                'id' => (int) $row['id'],
                'staffno' => $row['staffno'],
                'staffname' => $row['staffname'],
                'department' => $row['department'],
                'outside_list' => !$row['in_list'],
                'view_url' => 'evaluation-matrix.php?evaluation_id=' . (int) $row['id'] . ($viewingPrevious ? '&period=previous' : ''),
            );
        }
    }
    skillMatrixRespondJson($pending);
}

// Dashboard "Average Skill Score by Quarter", for the same staff and filter as the staff list.
if ($_POST["action"] == "load_quarter_scores") {
    $department = isset($_POST["department"]) ? $_POST["department"] : "ALL";
    $section = isset($_POST["section"]) ? $_POST["section"] : "ALL";
    $plant = isset($_POST["plant"]) ? $_POST["plant"] : "ALL";

    list($filterSql, $filterTypes, $filterParams) = skillMatrixStaffFilter($department, $section, $plant);

    skillMatrixRespondJson(skillMatrixQuarterScores($conn, $filterSql, $filterTypes, $filterParams));
}

skillMatrixRespondJson(array());
?>
