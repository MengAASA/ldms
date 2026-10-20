<?php
// Shared gate for showing/using the SKILL MATRIX nav link and pages.
// Requires $conn (see dbconn.php) to be available before calling canApproveSkillMatrix().
if (!function_exists('canApproveSkillMatrix')) {
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
}
