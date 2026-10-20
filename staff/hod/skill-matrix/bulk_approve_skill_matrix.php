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

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['fullname']) || !canApproveSkillMatrix()) {
    echo json_encode(['message' => 'error', 'error' => 'Not authorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['evaluation_ids']) || !is_array($_POST['evaluation_ids'])) {
    echo json_encode(['message' => 'error', 'error' => 'No evaluations were selected.']);
    exit();
}

$hodId = (int) $_SESSION['id'];
$evaluationIds = array_values(array_unique(array_filter(array_map('intval', $_POST['evaluation_ids']))));

if (empty($evaluationIds)) {
    echo json_encode(['message' => 'error', 'error' => 'No valid evaluations were selected.']);
    exit();
}

// Same ownership/eligibility rule as the single-record approve in
// evaluation-matrix.php: only evaluations created by staff who report to
// this HOD (or are on the skill-matrix whitelist), still PENDING. The
// IN (...) list is bound as individual integer params, not interpolated,
// so a tampered id list can only ever touch rows this WHERE clause allows -
// it cannot approve anything outside this HOD's own scope.
$placeholders = implode(',', array_fill(0, count($evaluationIds), '?'));
$sql = "UPDATE skill_matrix_evaluations sme
        INNER JOIN user creator ON creator.id = sme.created_by
        SET sme.approval_status = 'APPROVED',
            sme.approved_by = ?,
            sme.approved_at = NOW()
        WHERE sme.id IN ($placeholders)
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
        )";

$creatorDesignation = "MANAGER (AM/HOS & ABOVE)";
$types = 'i' . str_repeat('i', count($evaluationIds)) . 'is';
$params = array_merge([$hodId], $evaluationIds, [$hodId, $creatorDesignation]);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['message' => 'error', 'error' => 'Unable to prepare the approval statement.']);
    exit();
}
$stmt->bind_param($types, ...$params);
$stmt->execute();

echo json_encode(['message' => 'ok', 'approved' => $stmt->affected_rows]);
?>
