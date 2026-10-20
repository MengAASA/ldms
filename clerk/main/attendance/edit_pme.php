<?php
    session_start();

    $canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
        isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && $_SESSION['role'] == 'CLERK'
        && $_SESSION['usertype'] == 'MAIN'
    );
    include_once __DIR__ . '/../../../skill_matrix_department.php';
    $canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'CLERK')) {

	//Connection to database
    include "../../../dbconn.php";

	if (!empty($_GET['userid']) && !empty($_GET['participationid'])) {
		$userid = $_GET['userid'];
		$participationid = $_GET['participationid'];

		$sql = "SELECT participation.userid, participation.id AS participationid, user.staffname, user.staffno, 
               		user.department, training.title, user.designation, user.hodid, 
					pme.level_rating, pme.level_percent, pme.level_remark, 
					pme.level_rating2, pme.level_percent2, pme.level_remark2, 
					pme.behavioral_rating, pme.behavioral_percent, pme.behavioral_remark, 
					pme.result_rating, pme.result_percent, pme.result_remark, pme.ojt, 
					pme.from_date, pme.to_date 
				FROM participation 
				INNER JOIN pme ON participation.userid = pme.userid AND participation.id = pme.participationid
				INNER JOIN user ON participation.userid = user.id  
				INNER JOIN training ON participation.trainingid = training.id 
				WHERE pme.userid = '$userid' AND pme.participationid = '$participationid'";
	
				$query = mysqli_query($conn, $sql);
			
				if (mysqli_num_rows($query) > 0) {
					$row = mysqli_fetch_assoc($query);
					$staffname = $row['staffname'];
					$staffno = $row['staffno'];
					$department = $row['department'];
					$training_title = $row['title'];
					$designation = $row['designation'];
					$ojt = $row['ojt'];
					$level_rating = $row['level_rating'];
					$level_percent = $row['level_percent'];
					$level_remark = $row['level_remark'];
					$level_rating2 = $row['level_rating2'];
					$level_percent2 = $row['level_percent2'];
					$level_remark2 = $row['level_remark2'];
					$behavioral_rating = $row['behavioral_rating'];
					$behavioral_percent = $row['behavioral_percent'];
					$behavioral_remark = $row['behavioral_remark'];
					$result_rating = $row['result_rating'];
					$result_percent = $row['result_percent'];
					$result_remark = $row['result_remark'];
					$from_date = $row['from_date'];
					$to_date = $row['to_date'];
					
				} else {
					echo "No PME data found.";
					exit;
				}
			} else {
				echo "Invalid request.";
				exit;
			}
	
?>

<!DOCTYPE html>
<html lang="en">
	<head>
        <title>Learning and Development Management System</title>
        
        <script src="../../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../../asset/css/bootstrap.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.css" />
        <script src="../../../asset/js/bootstrap.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.js"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" href="../../../asset/css/datepicker.css">
        <script src="../../../asset/js/bootstrap-datepicker1.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
    </head>

        <style>
			.form-container {
				max-width: 1500px;
				margin: 20px auto;
				padding: 20px;
				background: #f9f9f9;
				border-radius: 8px;
				box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
			}
			.form-group {
				margin-bottom: 15px;
			}
			.panel-heading {
				text-align: center;
				font-weight: bold;
				font-size: 18px;
				padding: 15px;
			}
			.panel {
				width: 100%;
				margin: 0 auto;
			}
			.section-title {
				font-size: 25px;
				font-weight: bold;
				margin-top: 30px;
				margin-bottom: 15px;
				border-bottom: 2px solid #ccc;
				padding-bottom: 5px;
			}
			h4 {
				margin-top: 25px;
				margin-bottom: 30px;
			}
			.rating-container {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.rating-container select,
			.rating-container input {
				flex: 1;
			}
			.rating-container {
				display: flex;
				flex-direction: column;
				align-items: flex-start;
			}
			.form-check {
				display: flex;
				align-items: center;
				gap: 10px; /* Adjust this value for spacing */
			}
			.form-check-label {
				padding-left: 5px; /* Adjust if needed */
				padding-top: 10px;
			}
			.form-check-input {
				width: 30px; /* Adjust size if necessary */
				height: 20px;
			}
			.required-star {
				color: red; /* Makes the asterisk red */
				font-size: 1.2em; /* Adjust size if needed */
				margin-left: 3px; /* Adds a small space between the label and the asterisk */
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
                        <li><a href="../staff/staff.php">CONTRACT STAFF LIST</a></li>
                        <li><a href="../training/training_ojt.php">ALL TRAINING</a></li>
                        <li><a href="training.php">MY TRAINING</a></li>
                        <li><a href="pme.php">PME</a></li>
                        <li><a href="../tna/tna_list.php">TNA</a></li>
						<?php if ($canViewSkillMatrix) { ?><li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li><?php } ?>
						<li><a href="../../../admin/archive/export.php">EXPORT</a></li>
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

			<!-- Form Section -->
			<div class="container-fluid">
			<div class="row">
				<div class="col-md-12">

					<div class="panel panel-default">
						<div class="panel-heading">
							<div class="row align-items-center">
								<!-- Left-aligned button -->
								<div class="col-md-2" align="left">
									<button type="button" name="back_pme" class="btn btn-success btn-md" onclick="window.location.href='pme.php'">
										<i class="far fa-arrow-alt-circle-left"></i> BACK TO PME LIST
									</button>
								</div>

								<!-- Centered Title -->
								<div class="col-md-8" style="margin-top:10px" align="center">
									<strong>Performance Monitoring Form</strong>
								</div>
							</div>
						</div>
					</div>

					<div class="form-container">
						<form id="formSubmit" action="update_pme.php" method="post">
						
							<!-- Employee Information Section -->
							<div class="section-title">Employee Information</div>
							<div class="form-group">
								<label for="">Employee Name</label>
								<input type="text" class="form-control" id="staffname" name="staffname" value="<?= $row['staffname']?>"  readonly>
							</div>
							<div class="form-group">
								<label for="">Staff No</label>
								<input type="text" class="form-control" id="staffno" name="staffno" value="<?= $row['staffno']?>" readonly>
							</div>
							<div class="form-group">
								<label for="">Department</label>
								<input type="text" class="form-control" id="department" name="department" value="<?= $row['department']?>" readonly>
							</div>
							<div class="form-group">
								<label for="">Training Title</label>
								<input type="text" class="form-control" id="training_title" name="training_title" value="<?= $row['title']?>" readonly>
							</div>
							<div class="form-group row">	
								<div class="col-md-6">
									<label for="">Evaluation Period Start</label>
									<input type="date" class="form-control date-input" id="from_date" name="from_date" value="<?= $row['from_date']?>" placeholder="From Date" readonly>
								</div>
								<div class="col-md-6">
									<label for="">Evaluation Period End </label>
									<input type="date" class="form-control date-input" id="to_date" name="to_date" value="<?= $row['to_date']?>" placeholder="To Date" readonly>
								</div>
							</div>


							<!-- 1. Learning Section -->
							<div class="section-title">Learning Level</div>
							<h4>1. Evaluate employees Knowledge Sharing Sessions (KSS) and On-the-Job Training (OJT) conducted for their teams after attending training<span class="required-star">*</span></h4>
							<div class="form-group rating-container">
								<?php 
								$options = [
									"Excellent" => "> 90 % : Excellent - Excellent understanding of the topics learned and can explain to others",
									"Very Good" => "80 % - 89 % : Very Good - Very good understanding and can explain to others",
									"Good" => "70 % - 79 % : Good - Good understanding and can explain to others",
									"Satisfactory" => "60 % - 69 % : Satisfactory - Can understand most of the topics learned",
									"Fair" => "50 % - 59 % : Fair - Understand only some of the topics learned",
									"Poor" => "< 50 % : Poor - Very weak - understanding and requires further explanation"
								];

								foreach ($options as $value => $label) {
									$checked = ($row['level_rating'] == $value) ? "checked" : "";
									$disabled = ($row['level_rating'] != $value) ? "disabled" : ""; 
									echo "
									<div class='form-check'>
										<input class='form-check-input' type='radio' name='level_rating' value='$value' $checked $disabled>
										<label class='form-check-label'>$label</label>
									</div>";
								}
								?>
							</div>
							
							<div style="margin-bottom: 20px;">
								<input type="number" class="form-control" id="level_percent" name="level_percent" value="<?= $row['level_percent']?>" required readonly>
							</div>
							
							<div class="form-group">
									<label for="remark_level">Remarks : <span class="required-star">*</span></label><br>
									<label>Please confirm whether the On-the-Job Training (OJT) has been conducted.</label>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input class="form-check-input" type="radio" name="ojt" value="yes" id="ojtYes" 
											<?= ($row['ojt'] == 'yes') ? 'checked' : '' ?> disabled>
										<label class="form-check-label" for="ojtYes">Yes</label>

										<input class="form-check-input" type="radio" name="ojt" value="no" id="ojtNo" 
											<?= ($row['ojt'] == 'no') ? 'checked' : '' ?> disabled>
										<label class="form-check-label" for="ojtNo">No</label>
									</div>
									<label style="margin-top: 10px; display: block;">if yes, please specify the date, time, and location of the training. " If not, kindly provide the reason.</label>
									<textarea class="form-control" id="level_remark" name="level_remark" rows="2" readonly><?= $row['level_remark'] ?></textarea>
								</div>


							<!-- 2. Learning Section -->
							<div class="section-title">Learning Level</div>
							<h4>2. Did the employee learn what he / she is are supposed to learn from the training attended ?<span class="required-star">*</span></h4>
							<div class="form-group rating-container">
								<?php 
								$options = [
									"Excellent" => "> 90 % : Excellent - Excellent understanding of the topics learned and can explain to others",
									"Very Good" => "80 % - 89 % : Very Good - Very good understanding and can explain to others",
									"Good" => "70 % - 79 % : Good - Good understanding and can explain to others",
									"Satisfactory" => "60 % - 69 % : Satisfactory - Can understand most of the topics learned",
									"Fair" => "50 % - 59 % : Fair - Understand only some of the topics learned",
									"Poor" => "< 50 % : Poor - Very weak - understanding and requires further explanation"
								];

								foreach ($options as $value => $label) {
									$checked = ($row['level_rating2'] == $value) ? "checked" : "";
									$disabled = ($row['level_rating2'] != $value) ? "disabled" : ""; 
									echo "
									<div class='form-check'>
										<input class='form-check-input' type='radio' name='level_rating2' value='$value' $checked $disabled>
										<label class='form-check-label'>$label</label>
									</div>";
								}
								?>
							</div>
									
							<div style="margin-bottom: 20px;">
								<input type="number" class="form-control" id="level_percent2" name="level_percent2" value="<?= $row['level_percent2']?>" required readonly>
							</div>
							
							<div class="form-group">
								<label for="remark_level">Remarks :</label>
								<textarea class="form-control" id="level_remark2" name="level_remark2" rows="2" readonly> <?= $row['level_remark2']?> </textarea>
							</div>

							
							<!-- Behavioral Section -->
							<div class="section-title">Behavioral Change</div>
							<h4>3. Did the employee apply his / her newly acquired skills and knowledge to his / her jobs ?<span class="required-star">*</span></h4>
							<div class="form-group rating-container">
								<?php 
								$options = [
									"Excellent" => "> 90 % : Excellent - Able to apply all knowledge and skills acquired and can train others",
									"Very Good" => "80 % - 89 % : Very Good - Able to apply all knowledge and skills acquired and can train others",
									"Good" => "70 % - 79 % : Good - Able to apply most of knowledge and skills acquired and can train others",
									"Satisfactory" => "60 % - 69 % : Satisfactory - Able to apply most of knowledge and skills acquired",
									"Fair" => "50 % - 59 % : Fair - Able to apply only some of knowledge and skills acquired",
									"Poor" => "< 50 % : Poor - Very Weak - Not able to apply knowledge and skills acquired"
								];

								foreach ($options as $value => $label) {
									$checked = ($row['behavioral_rating'] == $value) ? "checked" : "";
									$disabled = ($row['behavioral_rating'] != $value) ? "disabled" : ""; 
									echo "
									<div class='form-check'>
										<input class='form-check-input' type='radio' name='behavioral_rating' value='$value' $checked $disabled>
										<label class='form-check-label'>$label</label>
									</div>";
								}
								?>
							</div>
									
							<div style="margin-bottom: 20px;">
								<input type="number" class="form-control" id="behavioral_percent" name="behavioral_percent" value="<?= $row['behavioral_percent']?>" required readonly>
							</div>
								
							<div class="form-group">
								<label for="remark_level">Remarks :</label>
								<textarea class="form-control" id="behavioral_remark" name="behavioral_remark" rows="2" readonly> <?= $row['behavioral_remark']?> </textarea>
							</div>
							
							<!-- Result Section -->
							<div class="section-title">Result Training Attended<span class="required-star">*</span></div>
							<h4>4. Did the training has any measurable business impact ?</h4>
							<div class="form-group rating-container">
								<?php 
								$options = [
									"Excellent" => "> 90 % : Excellent - Improvement exceeds target",
									"Very Good" => "80 % - 89 % : Very Good - Improvement exceeds target",
									"Good" => "70 % - 79 % : Good - Improvement as per the target",
									"Satisfactory" => "60 % - 69 % : Improvement at 80% of the target",
									"Fair" => "50 % - 59 % : Fair - Improvement at 50% of the target",
									"Poor" => "< 50 % : Poor - Very Weak - No improvement"
								];

								foreach ($options as $value => $label) {
									$checked = ($row['result_rating'] == $value) ? "checked" : "";
									$disabled = ($row['result_rating'] != $value) ? "disabled" : ""; 
									echo "
									<div class='form-check'>
										<input class='form-check-input' type='radio' name='result_rating' value='$value' $checked $disabled>
										<label class='form-check-label'>$label</label>
									</div>";
								}
								?>
							</div>
									
							<div style="margin-bottom: 20px;">
								<input type="number" class="form-control" id="result_percent" name="result_percent" value="<?= $row['result_percent']?>" required readonly>
							</div>
								
								
							<div class="form-group">
								<label for="remark_level">Remarks :</label>
								<textarea class="form-control" id="result_remark" name="result_remark" rows="2" value="<?= $row['result_remark']?>" readonly> <?= $row['result_remark']?> </textarea>
							</div>

			 					<div>
									<input type="hidden" name="userid" value="<?php echo $userid; ?>">
									<input type="hidden" name="participationid" value="<?php echo $participationid; ?>">
									<input type="hidden" name="status" value="completed">
									<button type="submit" class="btn btn-success btn-block">Agree</button>
								</div>
						</form>
					</div>
				</div>
			</div>
		</div>
			
                                     
                                <div class="col-sm-2"></div>
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

		// Define rating categories and their valid percentage ranges
		const ratingRanges = {
					"Excellent": [90, 100],
					"Very Good": [80, 89],
					"Good": [70, 79],
					"Satisfactory": [60, 69],
					"Fair": [50, 59],
					"Poor": [1, 49]
				};

				document.addEventListener("DOMContentLoaded", function () {
					// Rating groups and their associated input fields
					const ratingGroups = [
						{ radios: document.getElementsByName("level_rating"), input: document.getElementById("level_percent")},
						{ radios: document.getElementsByName("level_rating2"), input: document.getElementById("level_percent2")},
						{ radios: document.getElementsByName("behavioral_rating"), input: document.getElementById("behavioral_percent")},
						{ radios: document.getElementsByName("result_rating"), input: document.getElementById("result_percent")}
					];

					ratingGroups.forEach(({ radios, input, error }) => {
						radios.forEach(radio => {
							radio.addEventListener("change", function () {
								const [min, max] = ratingRanges[this.value]; // Get min & max range
								input.disabled = false; // Enable the percentage field
								input.value = ""; // Clear previous input
								input.min = min;
								input.max = max;
								error.style.display = "none"; // Hide any previous error
							});
						});

						input.addEventListener("input", function () {
							const min = parseInt(this.min);
							const max = parseInt(this.max);
							const value = parseInt(this.value);

							if (value < min || value > max) {
								error.style.display = "inline"; // Show error message
							} else {
								error.style.display = "none"; // Hide error message
							}
						});
					});
				});

				document.addEventListener("DOMContentLoaded", function () {
					const ratingGroups = [
						{ name: "level_rating", percentId: "level_percent" },
						{ name: "level_rating2", percentId: "level_percent2" },
						{ name: "behavioral_rating", percentId: "behavioral_percent" },
						{ name: "result_rating", percentId: "result_percent" }
					];

					ratingGroups.forEach(group => {
						const radios = document.getElementsByName(group.name);
						const percentInput = document.getElementById(group.percentId);

						function enablePercentInput() {
							percentInput.removeAttribute("readonly");
						}

						radios.forEach(radio => {
							radio.addEventListener("change", enablePercentInput);
						});
					});
				});

				document.addEventListener("DOMContentLoaded", function() {
					// Force open the date picker when clicking the input
					document.querySelectorAll(".date-input").forEach(input => {
						input.addEventListener("click", function() {
							this.showPicker();  // Opens the date picker
						});
					});
				});

				document.getElementById("formSubmit").addEventListener("submit", function (event) {
					event.preventDefault(); // Prevent immediate form submission

					if (!this.checkValidity()) {
						swal("Error!", "Please correct the errors before submitting.", "error");
						return;
					}

					// Show confirmation popup
					swal({
						title: "Are you sure?",
						text: "Do you agree with this evaluation?",
						icon: "warning",
						buttons: ["Cancel", "YES"], // Two buttons: Cancel and OK
						dangerMode: true, // Highlight the OK button as a warning
					}).then((willSubmit) => {
						if (willSubmit) {
							// Show success message
							swal({
								title: "Agreed!",
								text: "Evaluation Agreed successfully!",
								icon: "success",
								timer: 2000, // Auto-close after 2 seconds
								buttons: false,
							});

							// Submit form after 2 seconds to allow animation to be seen
							setTimeout(() => {
								event.target.submit();
							}, 2000);
						} else {
							// If canceled, show an info message (optional)
							swal("Cancelled", "You did not agree with the evaluation.", "info");
						}
					});
				});

    </script>
</html>
<?php
    }else{
        header("Location: ../../login.php");
        exit();
    }
?>
