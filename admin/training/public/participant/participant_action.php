<?php 
    include "../../../../dbconn.php";

    if (isset($_POST['btn_action'])) {
        if ($_POST['btn_action'] == 'addparticipant') {
            $trainingid = $_POST['trainingid'];
            $participant = $_POST['participant'];

            $sql = "select id from user where staffno = '$participant'";
            $query = mysqli_query($conn,$sql);
            while($row = mysqli_fetch_assoc($query)) {
                $participantid = mysqli_real_escape_string($conn, $row['id']);
            }
            
            $sql = "INSERT INTO `participation` (`trainingid`,`userid`) values ('$trainingid','$participantid');";
            if(mysqli_query($conn, $sql)){
                echo json_encode(['message' => 'insert']);
            }else {
                echo json_encode(['message' => 'error']);
            } 
        }else if ($_POST['btn_action'] == 'deleteparticipant') {
            $id = $_POST['id'];
            // $sql = "DELETE FROM `participation` WHERE `id` = '$id'";
            $sql = "DELETE precord, pmerecord
                    FROM participation precord
                    LEFT JOIN pme pmerecord ON pmerecord.participationid = precord.id
                    WHERE precord.id = '$id'";

            if(mysqli_query($conn, $sql)){
                echo json_encode(['message' => 'delete']);
            }else {
                echo json_encode(['message' => 'error']);
            } 
        }else if ($_POST['btn_action'] == 'absent') {
            $id = (int) $_POST['id'];
            // An absent participant has nothing for the HOD to evaluate, so a still-pending
            // PME is closed as not_applicable (drops it from the HOD list and reminders).
            // PMEs a HOD already approved/completed are left as they are.
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare("UPDATE participation SET attendance = 'ABSENT' WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt = $conn->prepare("UPDATE pme SET status = 'not_applicable' WHERE participationid = ? AND status = 'pending'");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $conn->commit();
                echo json_encode(['message' => 'delete']);
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                echo json_encode(['message' => 'error']);
            }
        }else if ($_POST['btn_action'] == 'undoabsent') {
            $id = (int) $_POST['id'];
            // Attendance goes back to COMPLETED if the participant had already submitted the
            // feedback form (q1 filled), otherwise back to not-yet-marked.
            // The PME reopens as pending only if the staff is evaluable - same rule as the
            // after_participation_insert trigger / scripts/deploy_pme_not_applicable_20260915.php -
            // so a PME that was not_applicable for another reason (HOD, resigned, ...) stays closed.
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare("UPDATE participation
                                        SET attendance = IF(q1 IS NOT NULL AND q1 <> '', 'COMPLETED', NULL)
                                        WHERE id = ? AND attendance = 'ABSENT'");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt = $conn->prepare("UPDATE pme p
                                        JOIN user u ON u.id = p.userid
                                        JOIN user h ON h.id = p.hodid
                                        SET p.status = 'pending'
                                        WHERE p.participationid = ?
                                          AND p.status = 'not_applicable'
                                          AND u.status != 'RESIGN'
                                          AND (
                                              p.designation = 'Executive'
                                              OR (
                                                  p.designation = 'MANAGER (AM/HOS & ABOVE)'
                                                  AND u.usertype != 'HOD'
                                                  AND NOT EXISTS (SELECT 1 FROM divisions dv WHERE dv.head_user_id = u.id)
                                              )
                                          )");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $conn->commit();
                echo json_encode(['message' => 'undo']);
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                echo json_encode(['message' => 'error']);
            }
        }
    }
?>