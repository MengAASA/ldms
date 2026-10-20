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

if (!isset($_SESSION['fullname']) || !isset($_POST["action"]) || ($_SESSION['role'] != 'ADMIN' && !skillMatrixUserCanUse())) {
    skillMatrixRespondJson(array());
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
            skillMatrixRespondJson($output);
        }
    }

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
                ) AS approval_status
            FROM user u
            LEFT JOIN departments dp ON u.department_id = dp.id
            LEFT JOIN sections s ON u.section_id = s.id
            WHERE u.designation IN (?, ?)
            AND u.status != ?";

    $designation1 = "NON EXECUTIVE";
    $designation2 = "CONTRACT";
    $inactiveStatus = "RESIGN";

    $types = "iiiisss";
    $params = [$currentYear, $currentQuarter, $currentYear, $currentQuarter, $designation1, $designation2, $inactiveStatus];

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

    $sql .= " ORDER BY department, u.staffname";

    $stmt = $conn->prepare($sql);
    skillMatrixBindParams($stmt, $types, $params);

    $stmt->execute();
    $result = $stmt->get_result();

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
        );
    }

    skillMatrixRespondJson($output);
}

skillMatrixRespondJson(array());
?>
