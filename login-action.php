<?php
    include "dbconn.php";
    session_start();

    if (isset($_POST['username']) && isset($_POST['password'])) {
    	$username = $_POST['username'];
    	$password = md5($_POST['password']);

        $sql = "SELECT * FROM user WHERE staffno='$username' AND password='$password'";
        $result = mysqli_query($conn, $sql);
    	if (mysqli_num_rows($result) == 1) {
    		$row = mysqli_fetch_assoc($result);
            // A NULL role / user type means the same as blank. Store it as ''
            // so access checks that start with isset($_SESSION['role'], ...)
            // don't fail for these staff (a NULL roletype hid the skill matrix
            // from HOD M0639).
            $row['roletype'] = (string) $row['roletype'];
            $row['usertype'] = (string) $row['usertype'];
            if ($row['roletype'] == 'ADMIN') {
                $_SESSION['id'] = $row['id'];
                $_SESSION['fullname'] = $row['staffname'];
                $_SESSION['role'] = $row['roletype'];
                $_SESSION['usertype'] = $row['usertype'];
                $_SESSION['department'] = $row['department'];
                $_SESSION['designation'] = $row['designation'];
                $_SESSION['hodid'] = $row['hodid'];
                $_SESSION['staffno'] = $row['staffno'];
            }else if ($row['roletype'] == 'CLERK') {
                $_SESSION['id'] = $row['id'];
                $_SESSION['fullname'] = $row['staffname'];
                $_SESSION['role'] = $row['roletype'];
                $_SESSION['usertype'] = $row['usertype'];
                $_SESSION['department'] = $row['department'];
                $_SESSION['designation'] = $row['designation'];
                $_SESSION['hodid'] = $row['hodid'];
                $_SESSION['staffno'] = $row['staffno'];
            }else if ($row['roletype'] == '') {
                $_SESSION['id'] = $row['id'];
                $_SESSION['fullname'] = $row['staffname'];
                $_SESSION['role'] = $row['roletype'];
				$_SESSION['designation'] = $row['designation'];
                $_SESSION['usertype'] = $row['usertype'];
                $_SESSION['department'] = $row['department'];
                $_SESSION['hodid'] = $row['hodid'];
                $_SESSION['staffno'] = $row['staffno'];
            }
            $smCheck = mysqli_query($conn, "SELECT 1 FROM skill_matrix_whitelist WHERE staffno='" . mysqli_real_escape_string($conn, $row['staffno']) . "' LIMIT 1");
            $_SESSION['is_sm_user'] = ($smCheck && mysqli_num_rows($smCheck) > 0) ? 1 : 0;
            echo json_encode(['message' => 'login']);
        }else {
            echo json_encode(['message' => 'notlogin']);
        }
    }
?>
