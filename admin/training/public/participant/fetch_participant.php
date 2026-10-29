<?php
    include "../../../../dbconn.php";

    if($_POST["action"] == "load_department"){
        echo loadDepartment();
    }else if($_POST["action"] == "load_user"){
		$department = $_POST['department'];
        echo loadParticipant($department);
    }else if($_POST["action"] == "load_participant"){
		$trainingid = $_POST['trainingid'];
        $output= array();
        $sql = "SELECT participation.id, participation.attendance, user.staffname,department,staffno FROM participation JOIN user ON participation.userid = user.`id` WHERE trainingid = '$trainingid';";
		$query = mysqli_query($conn,$sql);
        while($row = mysqli_fetch_assoc($query))
        {
			if ($row['attendance'] == '') {
				$status = '<span class="label label-pill label-warning">PENDING</span>';
				$btnedit = '<button type="submit" id="'.$row['id'].'" class="btn btn-warning btn-sm absent" style="margin-left:5px;"><i class="far fa-calendar-times"></i> ABSENT?</button><button type="submit" id="'.$row['id'].'" class="btn btn-danger btn-sm delete" style="margin-left:5px;"><i class="fa fa-trash"></i> DELETE</button>';
			}else if ($row['attendance'] == 'COMPLETED') {
				$status = '<span class="label label-pill label-success">COMPLETED</span>';
				$btnedit = '<button type="submit" id="'.$row['id'].'" class="btn btn-warning btn-sm absent" style="margin-left:5px;"><i class="far fa-calendar-times"></i> ABSENT?</button><button type="submit" id="'.$row['id'].'" class="btn btn-danger btn-sm delete" style="margin-left:5px;"><i class="fa fa-trash"></i> DELETE</button>';
			}else if ($row['attendance'] == 'ABSENT') {
				$status = '<span class="label label-pill label-danger">ABSENT</span>';
				$btnedit = '<button type="submit" id="'.$row['id'].'" class="btn btn-info btn-sm undoabsent" style="margin-left:5px;"><i class="fas fa-undo"></i> UNDO ABSENT</button><button type="submit" id="'.$row['id'].'" class="btn btn-danger btn-sm delete" style="margin-left:5px;"><i class="fa fa-trash"></i> DELETE</button>';
			}

            $output[]= array(
                'id' => $row['id'],
				'staffno' => $row['staffno'],
                'staffname' => $row['staffname'],
				'department' => $row['department'],
				'status' => $status,
                'btnedit' => $btnedit,
            ); 
        }
        echo json_encode($output);
    }else if($_POST["action"] == 'fetch_trainingtitle'){
		$trainingid = $_POST['trainingid'];
		$output= array();
		$sql = "select title from training where id = '$trainingid';";
		$query = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_assoc($query))
		{
			$title = $row['title'];
		}
	
		$output[]= array(
			'title' => $title,
		);
	
		echo json_encode($output);
	}

	function loadDepartment(){
		global $conn;
		$sql = "SELECT DISTINCT(`department`) AS department FROM `user` ORDER BY department";
		$query = mysqli_query($conn,$sql);
		$options='<option value="">-- Select Department --</option>';
		if (mysqli_num_rows($query) > 0) {
		  // output data of each row

		  while($row = mysqli_fetch_assoc($query)) {
			$options.= '<option value="'.$row['department'].'">'.$row['department'].'</option>';
		  }
		}
		return $options;
	}

	function loadParticipant($department){
		global $conn;
		$sql = "SELECT `id`,`staffname`,`staffno` FROM `user` WHERE `department` = '$department' ORDER BY `staffname`";
		$query = mysqli_query($conn,$sql);
		$options='<option value="">-- Select Participant --</option>';
		if (mysqli_num_rows($query) > 0) {
		  // output data of each row

		  while($row = mysqli_fetch_assoc($query)) {
			$options.= '<option value="'.$row['staffno'].'">'.$row['staffno'].' - '.$row['staffname'].'</option>';
		  }
		}
		return $options;
	}
?>