<?php
    session_start();
    include "../../../dbconn.php";

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == '')) {

        include_once __DIR__ . "/../includes/skill_matrix_access.php";
        $showSkillMatrixNav = canApproveSkillMatrix();

        $hodid = $_SESSION['id'];

        // Get the current page number
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20; // Number of records per page
        $offset = ($page - 1) * $limit;

        // Get total records
        $sql_count = "SELECT COUNT(*) AS total FROM pme WHERE hodid = ? AND (designation = 'Executive' OR designation = 'MANAGER (AM/HOS & ABOVE)')";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param("i", $hodid);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $row_count = $result_count->fetch_assoc();
        $total_records = $row_count['total'];
        $total_pages = ceil($total_records / $limit); // Calculate total pages

        $sql = "SELECT p.*, u.usertype,
                    ((DATEDIFF(t.enddate, t.startdate) + 1) * ROUND(TIME_TO_SEC(TIMEDIFF(t.endtime, t.starttime)) / 3600, 2)) AS training_hours
                    FROM pme p
                    JOIN user u ON p.userid = u.id
                    LEFT JOIN training t ON p.trainingid = t.id
                    WHERE p.hodid = ?
                    AND u.status != 'RESIGN'
                    -- nothing to evaluate: closed as not_applicable, or still pending for a participant marked absent
                    AND p.status != 'not_applicable'
                    AND NOT (
                        p.status = 'pending'
                        AND EXISTS (SELECT 1 FROM participation pa WHERE pa.id = p.participationid AND pa.attendance = 'ABSENT')
                    )
                    AND (
                        p.designation = 'Executive'
                        OR (
                            p.designation = 'MANAGER (AM/HOS & ABOVE)'
                            AND u.usertype != 'HOD'
                            AND NOT EXISTS (SELECT 1 FROM divisions dv WHERE dv.head_user_id = u.id)
                        )
                    )
                    AND YEAR(p.from_date) = YEAR(CURDATE())
                    ORDER BY
                        CASE
                            WHEN p.status = 'pending' AND p.to_date < CURDATE() THEN 1
                            WHEN p.status = 'pending' THEN 2
                            ELSE 3
                        END,
                        p.from_date DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $hodid);
        $stmt->execute();
        $result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Learning and Development Management System</title>
        <script src="../../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../../asset/css/bootstrap.min.css" />
        <script src="../../../asset/js/bootstrap.min.js"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
    </head>

	<style>
		.badge {
			padding: 5px 10px;
			font-size: 12px;
		}
		.bg-success { background-color: #28a745 !important; }
		.bg-warning { background-color: orangered !important; }
		#trainerTable {
			width: 100% !important;
		}
	</style>

    <body onload="startTime()" style="background-image:url('../../../asset/image/bg-try.png');zoom: 75%;">
        <br>
        <div class="container-fluid">
            <div class="row">
				<div class="col-md-10">
    				<img src= "../../../asset/image/lndlogo.gif" height="50" width="290">
				</div>
				<div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;">

				</div>
			</div>
            <nav class="navbar navbar-inverse" >
                <div class="container-fluid ">
    				<ul class="nav navbar-nav">
						<li><a href="../dashboard.php">HOME</a></li>
						<li><a href="../attendance/training.php">MY TRAINING</a></li>
						<li><a href="pme.php">PME</a></li>
						<li><a href="../tna/staff_list.php">TNA</a></li>
						<li><a href="../tni/tni.php">TNI</a></li>
						<?php if ($showSkillMatrixNav) { ?>
                        <li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li>
                        <?php } ?>
						<li><a href="../password/password.php">CHANGE PASSWORD</a></li>
    				</ul>
    				<ul class="nav navbar-nav navbar-right">
    					<li class="dropdown">
    						<a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="label label-pill label-danger count"></span> <?php echo $_SESSION['fullname']?> </a>
    						<ul class="dropdown-menu">
    							<li><a href="../../../logout.php">LOGOUT</a></li>
    						</ul>
    					</li>
    				</ul>
    			</div>
            </nav>

			<div class="row">
                <div class="col-md-12">
					<div class="panel panel-default">
						<div class="panel-heading">
								<div class="row">
									<div class="col-md-11" style="margin-top: 10px;">
										<strong>Performance Monitoring Evaluation List</strong>
									</div>
								</div>
							</div>

							<div class="panel-body" align="center">
								<div class="row">
									<div class="col-sm-12 table-responsive">
										<table id="trainerTable" class="table table-bordered table-striped">
											<thead>
												<tr>
													<th>No.</th>
													<th>Name</th>
													<th>Staff No</th>
													<th>Training Title</th>
													<th>Training Hour</th>
													<th>Evaluation Period Start</th>
													<th>Evaluation Period End</th>
													<th>Evaluation Status</th>
													<th width="65px">Action</th>
												</tr>
											</thead>
											<tbody align="center">
												<?php
												$count = $offset + 1;
												while ($row = $result->fetch_assoc()) {
													// Check the status of the evaluation
													$status = $row['status']; // Assuming 'status' column exists
													$label_class = ($status == 'approved') ? 'success' : 'danger';
													$status_text = ($status == 'approved') ? 'Complete' : 'Not Complete';
												?>
													<tr>
														<td><?= $count ?></td>
														<td><?= $row['staffname'] ?></td>
														<td><?= $row['staffno'] ?></td>
														<td><?= $row['training_title'] ?></td>
														<td><?= $row['training_hours'] !== null ? $row['training_hours'] : '-' ?></td>
														<td><?= $row['from_date']?></td>
														<td><?= $row['to_date']?></td>
														<td>
															<?php if ($row['status'] == 'approved' || $row['status'] == 'completed' || $row['status'] == 'verified'): ?>
																<span class="badge bg-success">Complete</span>
															<?php else: ?>
																<span class="badge bg-warning">Pending</span>
															<?php endif; ?>
														</td>
														<td>
															<?php
																$current_date = date("Y-m-d"); // Get today's date
																$to_date = $row['to_date']; // Get evaluation end date

																if ($current_date > $to_date): // If evaluation period ended, enable button
															?>
																<a href="edit_pme.php?id=<?= $row['id'] ?>"
																class="btn btn-info btn-sm"
																style="border-radius: 5px; padding: 8px 12px; font-weight: 500;"
																title="Evaluate Performance">
																	<i class="fa fa-edit me-2"></i> Evaluate
																</a>
															<?php else: ?>
																<button class="btn btn-warning btn-sm"
																		style="border-radius: 5px; padding: 8px 12px; font-weight: 500; cursor: not-allowed;"
																		title="Evaluation period not ended yet"
																		data-bs-toggle="tooltip">
																	<i class="fa fa-lock me-2"></i> Evaluate
																</button>
															<?php endif; ?>
														</td>

													</tr>
												<?php
													$count++;

												}
												?>
											</tbody>

										</table>

									</div>
								</div>
							</div>
					</div>
				</div>
			</div>
        </div>
    </body>
	<footer>
        <div class="col-md-12" style="margin-bottom:15px;">
			<div class="row">
				<br>
				<div class="col-md-2"></div>
                <div class="col-md-8">
					<h6 align="center" style="margin-top:15px;">
						Copyright &copy; 2023 PHN Industry Sdn. Bhd.
						Use with Google Chrome or Javascript enabled IE and Firefox.
					</br>All Rights Reserved.  |  Web design by PHN IT Department
					</h6>
				</div>
				<div class="col-md-2"></div>
			</div>
        </div>
	</footer>
    <script>
        function startTime() {
    		var today=new Date();
    		var h=today.getHours();
    		var m=today.getMinutes();
    		var s=today.getSeconds();
    		// add a zero in front of numbers<10
    		h=checkTime(h);
    		m=checkTime(m);
    		s=checkTime(s);
    		document.getElementById('txt').innerHTML=h+":"+m+":"+s;
    		t=setTimeout(function(){startTime()},500);
		}

		function checkTime(i) {
            if (i<10) {
                i="0" + i;
            }
		    return i;
		}

		$(document).ready(function () {
			$('#trainerTable').DataTable({
				"paging": true,
				"searching": true,
				"ordering": true,
				"info": true,
				"autoWidth": false,
				"lengthMenu": [10, 25, 50],
			});

			$('[data-bs-toggle="tooltip"]').tooltip();
		});
    </script>
</html>
<?php
    }else{
         header("Location: ../../../login.php");
         exit();
    }
?>
