<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";

header('Content-Type: application/json; charset=utf-8');

$output = array();

function respondJson($data)
{
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data);
    exit();
}

$isSmWhitelisted = !empty($_SESSION['is_sm_user']) && isset($_SESSION['fullname']);
$isRegularManager = isset($_SESSION['fullname'], $_SESSION['role'], $_SESSION['usertype'], $_SESSION['designation'], $_SESSION['hodid'])
    && $_SESSION['role'] == ''
    && $_SESSION['usertype'] == ''
    && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
    && (int) $_SESSION['hodid'] != 0;
include_once __DIR__ . '/../../../skill_matrix_department.php';
if (!skillMatrixDepartmentEnabled()) {
    $isSmWhitelisted = $isRegularManager = false;
}

if (
    (!$isSmWhitelisted && !$isRegularManager) ||
    !isset($_POST["action"]) ||
    $_POST["action"] != "load_non_executive_staff"
) {
    respondJson($output);
}

$department = isset($_SESSION['department']) ? $_SESSION['department'] : '';
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

if ($department == '' && isset($_SESSION['id'])) {
    $stmtDepartment = $conn->prepare("SELECT department FROM user WHERE id = ?");
    if ($stmtDepartment) {
        $stmtDepartment->bind_param("i", $_SESSION['id']);
        $stmtDepartment->execute();
        $departmentResult = $stmtDepartment->get_result()->fetch_assoc();
        if ($departmentResult) {
            $department = $departmentResult['department'];
        }
    }
}

if ($department == '') {
    respondJson($output);
}

$sql = "SELECT
            u.id,
            u.staffno,
            u.staffname,
            u.grade,
            u.status,
            COALESCE(dp.name, u.department) AS department,
            COALESCE(s.name, u.section) AS section,
            (
                SELECT sme.approval_status
                FROM skill_matrix_evaluations sme
                WHERE sme.staffid = u.id
                AND sme.eval_year = ?
                AND sme.eval_quarter = ?
                ORDER BY sme.evaluation_date DESC, sme.id DESC
                LIMIT 1
            ) AS approval_status,
            EXISTS (
                SELECT 1
                FROM skill_matrix_evaluations sme
                WHERE sme.staffid = u.id
                AND sme.eval_year = ?
                AND sme.eval_quarter = ?
            ) AS has_current_quarter_evaluation
        FROM user u
        LEFT JOIN departments dp ON u.department_id = dp.id
        LEFT JOIN sections s ON u.section_id = s.id
        WHERE u.designation IN (?, ?)
        AND u.status != ?
        AND u.department = ?
        ORDER BY u.staffname";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    respondJson($output);
}

$designation1 = "NON EXECUTIVE";
$designation2 = "CONTRACT";
$inactiveStatus = "RESIGN";
$stmt->bind_param("iiiissss", $currentYear, $currentQuarter, $currentYear, $currentQuarter, $designation1, $designation2, $inactiveStatus, $department);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['approval_status'] == 'APPROVED') {
        $approvalStatus = '<span class="label label-pill label-success">APPROVED</span>';
    } else if ($row['approval_status'] == 'PENDING') {
        $approvalStatus = '<span class="label label-pill label-warning">WAITING APPROVAL</span>';
    } else if ($row['has_current_quarter_evaluation']) {
        $approvalStatus = '<span class="label label-pill label-default">DRAFT</span>';
    } else {
        $approvalStatus = '<span class="label label-pill label-default">NOT SUBMITTED</span>';
    }

    if ($row['has_current_quarter_evaluation']) {
        $isSubmitted = $row['approval_status'] == 'PENDING' || $row['approval_status'] == 'APPROVED';
        $action = '<div style="display: flex; gap: 6px; justify-content: center; white-space: nowrap;">';
        if ($isSubmitted) {
            $action .= '<a href="evaluation-matrix.php?staffid=' . $row['id'] . '" class="btn btn-default btn-sm"><i class="fa fa-search"></i> VIEW</a>';
        } else {
            $action .= '<a href="evaluation-matrix.php?staffid=' . $row['id'] . '&edit=1" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> EDIT</a>';
        }
        $action .= '<a href="duplicate-matrix.php?staffid=' . $row['id'] . '" class="btn btn-warning btn-sm"><i class="fa fa-copy"></i> DUPLICATE</a>';
        $action .= '</div>';
    } else {
        $action = '<a href="evaluation-matrix.php?staffid=' . $row['id'] . '" class="btn btn-info btn-sm"><i class="fa fa-edit"></i> FILL SKILL MATRIX</a>';
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
        $action .= '<a href="duplicate-matrix.php?staffid=' . $row['id'] . '&period=previous" class="btn btn-warning btn-sm"><i class="fa fa-copy"></i> DUPLICATE</a>';
        $action .= '</div>';
    }

    $output[] = array(
        'id' => $row['id'],
        'staffno' => $row['staffno'],
        'staffname' => $row['staffname'],
        'department' => $row['department'],
        'section' => $row['section'],
        'grade' => $row['grade'],
        'status' => '<span class="label label-pill label-success">ACTIVE</span>',
        'approval_status' => $approvalStatus,
        'action' => $action,
    );
}

respondJson($output);
?>
