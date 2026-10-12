<?php 
    include "../../../dbconn.php";
    include_once __DIR__ . '/../../../division_department_section.php';
    include_once __DIR__ . '/../../../admin/staff/delete_impact.php';

    if (isset($_POST['btn_action'])) {
        if ($_POST['btn_action'] == 'adduser') {
            $staffno = strtoupper($_POST['staffno']);
            $staffname = strtoupper($_POST['staffname']);
            $gender = $_POST['gender'];
            $department = $_POST['department'];
            $division = $_POST['division'];
			$section = $_POST['section'];
            $status = $_POST['status'];
            $division_id = getDivisionIdByName($division);
            $department_id = getDepartmentIdByName($division_id, $department);
            $section_id = getSectionIdByName($department_id, $section);

            // HOD users should not have section_id and section should be blank
            if ($designation === 'HOD' || (isset($_POST['usertype']) && $_POST['usertype'] === 'HOD')) {
                $section = '';
                $section_id = null;
            }

            // hodid follows the department's assigned HOD (org.php > Assign HOD),
            // the single source of truth - see getDepartmentHodId().
            $hodid = getDepartmentHodId($department_id) ?: 0;

            $sectionIdValue = is_null($section_id) ? 'NULL' : "'$section_id'";
            $password = md5('P@ss1234');
            $sql = "INSERT INTO `user` (`staffno`,`password`,`staffname`,`gender`,`designation`,`department`,`division`,`section`,`division_id`,`department_id`,`section_id`,`status`,`hodid`) values ('$staffno','$password', '$staffname', '$gender', 'CONTRACT', '$department', '$division', '$section', '$division_id', '$department_id', $sectionIdValue, '$status', '$hodid')";
            if(mysqli_query($conn, $sql)){
                echo json_encode(['message' => 'insert']);
            }else {
                echo json_encode(['message' => 'error']);
            } 
        }else if ($_POST['btn_action'] == 'edituser') {
            $staffno = strtoupper($_POST['staffno']);
            $staffname = strtoupper($_POST['staffname']);
            $gender = $_POST['gender'];
            $department = $_POST['department'];
            $division = $_POST['division'];
			$section = $_POST['section'];
            $id = $_POST['id'];
			$status = $_POST['status'];
            $division_id = getDivisionIdByName($division);
            $department_id = getDepartmentIdByName($division_id, $department);
            $section_id = getSectionIdByName($department_id, $section);

            // HOD users should not have section_id and section should be blank
            if ($designation === 'HOD' || (isset($_POST['usertype']) && $_POST['usertype'] === 'HOD')) {
                $section = '';
                $section_id = null;
            }

            // hodid follows the department's assigned HOD (org.php > Assign HOD),
            // the single source of truth - see getDepartmentHodId(). Guarded so a
            // department's own HOD is never recorded as reporting to themselves.
            $hodnew = getDepartmentHodId($department_id) ?: 0;
            if ($hodnew === (int) $id) {
                $hodnew = 0;
            }

            $sectionIdValue = is_null($section_id) ? 'NULL' : "'$section_id'";
            $sql = "UPDATE `user` SET `staffno` = '$staffno', `staffname` = '$staffname', `gender` = '$gender', `designation` = 'CONTRACT', `department` = '$department', `division` = '$division', `section` = '$section', `division_id` = '$division_id', `department_id` = '$department_id', `section_id` = $sectionIdValue, `status` = '$status', `hodid` = '$hodnew' WHERE `id` = '$id'";
            if(mysqli_query($conn, $sql)){
                echo json_encode(['message' => 'update']);
            }else {
                echo json_encode(['message' => 'error']);
            } 
        }else if ($_POST['btn_action'] == 'delete_impact') {
            // Asked before the confirmation dialog, so the clerk is told what
            // deleting actually destroys instead of a bare "Are you sure?".
            $impact = staffDeleteImpact($conn, $_POST['id']);
            if ($impact === null) {
                echo json_encode(['message' => 'error', 'detail' => 'Staff not found.']);
            } else {
                echo json_encode([
                    'message' => 'ok',
                    'staffno' => $impact['staffno'],
                    'staffname' => $impact['staffname'],
                    'warnings' => staffDeleteImpactWarnings($impact),
                    'blockers' => staffDeleteBlockers($impact),
                    'has_approved_matrix' => $impact['matrix_approved'] > 0,
                ]);
            }
        }else if ($_POST['btn_action'] == 'deleteuser') {
            // Refuses when the delete would orphan or destroy history, and
            // clears dangling reporting links when it does proceed.
            $result = staffDeleteSafely($conn, $_POST['id']);
            if ($result['ok']) {
                echo json_encode(['message' => 'delete', 'cleared' => $result['cleared']]);
            } else {
                echo json_encode([
                    'message' => $result['blocked'] ? 'blocked' : 'error',
                    'detail'  => $result['detail'],
                ]);
            }
        }
    }
?>