<?php
    session_start();

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == '')) {

	//Connection to database
    include "../../../dbconn.php";

    include_once __DIR__ . "/../includes/skill_matrix_access.php";
    $showSkillMatrixNav = canApproveSkillMatrix();

	//Declare Variable from input
	$id = (int) $_GET['id'];

	//SQL Statement
	$stmt = $conn->prepare("SELECT * FROM pme WHERE id=?");
	$stmt->bind_param('i', $id);

	//Execute to get the data
	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result -> fetch_assoc();
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
                        <li><a href="training.php">MY TRAINING</a></li>
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
								<input type="text" class="form-control" id="staffname" name="staffname" value="<?= htmlspecialchars($row['staffname'] ?? '') ?>"  readonly>
							</div>
							<div class="form-group">
								<label for="">Staff No</label>
								<input type="text" class="form-control" id="staffno" name="staffno" value="<?= htmlspecialchars($row['staffno'] ?? '') ?>" readonly>
							</div>
							<div class="form-group">
								<label for="">Department</label>
								<input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($row['department'] ?? '') ?>" readonly>
							</div>
							<div class="form-group">
								<label for="">Training Title</label>
								<input type="text" class="form-control" id="training_title" name="training_title" value="<?= htmlspecialchars($row['training_title'] ?? '') ?>" readonly>
							</div>
							<div class="form-group row">	
								<div class="col-md-6">
									<label for="">Evaluation Period Start</label>
									<input type="date" class="form-control date-input" id="from_date" name="from_date" value="<?= htmlspecialchars($row['from_date'] ?? '') ?>" placeholder="From Date" readonly>
								</div>
								<div class="col-md-6">
									<label for="">Evaluation Period End </label>
									<input type="date" class="form-control date-input" id="to_date" name="to_date" value="<?= htmlspecialchars($row['to_date'] ?? '') ?>" placeholder="To Date" readonly>
								</div>
							</div>


							<!-- 1. Learning Section -->
							<div class="section-title">Learning Level</div>
							<h4>1. Evaluate employees Knowledge Sharing Sessions (KSS) and On-the-Job Training (OJT) conducted for their teams after attending training <span class="required-star">*</span></h4>
							<div class="form-group rating-container">
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating" value="Excellent" onclick="enablePercentInput()" <?php if ($row['level_rating'] == "Excellent") echo "checked"; ?> required>
									<label class="form-check-label">
										>  90 % : Excellent  -  Excellent understanding of the topics learned and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating" value="Very Good" onclick="enablePercentInput()" <?php if ($row['level_rating'] == "Very Good") echo "checked"; ?> required>
									<label class="form-check-label">
										80 %  -  89 %  :  Very Good  -  Very good understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating" value="Good" onclick="enablePercentInput()" <?php if ($row['level_rating'] == "Good") echo "checked"; ?> required>
									<label class="form-check-label">
										70 %  -  79 %  :  Good  -  Good  understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating" value="Satisfactory" onclick="enablePercentInput()" <?php if ($row['level_rating'] == "Satisfactory") echo "checked"; ?> required> 
									<label class="form-check-label">
										60 %  -  69 %  :  Satisfactory  -  Can understand most of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating" value="Fair" onclick="enablePercentInput()" <?php if ($row['level_rating'] == "Fair") echo "checked"; ?> required>
									<label class="form-check-label">
										 50 %  -  59 %  :  Fair  -  Understand only some of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating" value="Poor" onclick="enablePercentInput()" <?php if ($row['level_rating'] == "Poor") echo "checked"; ?> required>
									<label class="form-check-label">
										<  50 %  :  Poor  - Very weak understanding and requires further explanation
									</label>
								</div>
									
									<input type="number" class="form-control" id="level_percent" name="level_percent" value="<?= htmlspecialchars($row['level_percent'] ?? '') ?>"  required placeholder="Please specify the rating criteria, eg 85% = Very Good " readonly>
								</div>
								
								<div class="form-group">
									<label for="remark_level">Remarks : <span class="required-star">*</span></label><br>
									<label>Please confirm whether the On-the-Job Training (OJT) has been conducted.</label>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input class="form-check-input" type="radio" name="ojt" value="yes" id="ojtYes" 
											<?= ($row['ojt'] == 'yes') ? 'checked' : '' ?>>
										<label class="form-check-label" for="ojtYes">Yes</label>

										<input class="form-check-input" type="radio" name="ojt" value="no" id="ojtNo" 
											<?= ($row['ojt'] == 'no') ? 'checked' : '' ?>>
										<label class="form-check-label" for="ojtNo">No</label>
									</div>
									<label style="margin-top: 10px; display: block;">if yes, please specify the date, time, and location of the training. " If not, kindly provide the reason.</label>
									<textarea class="form-control" id="level_remark" name="level_remark" rows="2" required><?= htmlspecialchars($row['level_remark'] ?? '') ?></textarea>
								</div>

							<!-- 2. Learning Section -->
							<div class="section-title">Learning Level</div>
							<h4>2. Did the employee learn what he / she is are supposed to learn from the training attended ? <span class="required-star">*</span></h4>
							<div class="form-group rating-container">
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating2" value="Excellent" onclick="enablePercentInput()"  <?php if ($row['level_rating2'] == "Excellent") echo "checked"; ?> required>
									<label class="form-check-label">
										>  90 % : Excellent  -  Excellent understanding of the topics learned and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating2" value="Very Good" onclick="enablePercentInput()" <?php if ($row['level_rating2'] == "Very Good") echo "checked"; ?> required>
									<label class="form-check-label">
										80 %  -  89 %  :  Very Good  -  Very good understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating2" value="Good" onclick="enablePercentInput()" <?php if ($row['level_rating2'] == "Good") echo "checked"; ?> required>
									<label class="form-check-label">
										70 %  -  79 %  :  Good  -  Good  understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating2" value="Satisfactory" onclick="enablePercentInput()" <?php if ($row['level_rating2'] == "Satisfactory") echo "checked"; ?> required>
									<label class="form-check-label">
										60 %  -  69 %  :  Satisfactory  -  Can understand most of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating2" value="Fair" onclick="enablePercentInput()" <?php if ($row['level_rating2'] == "Fair") echo "checked"; ?> required>
									<label class="form-check-label">
										 50 %  -  59 %  :  Fair  -  Understand only some of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="level_rating2" value="Poor" onclick="enablePercentInput()" <?php if ($row['level_rating2'] == "Poor") echo "checked"; ?> required>
									<label class="form-check-label">
										<  50 %  :  Poor  - Very weak understanding and requires further explanation
									</label>
								</div>
									
									<input type="number" class="form-control" id="level_percent2" name="level_percent2" value="<?= htmlspecialchars($row['level_percent2'] ?? '') ?>" required placeholder="Please specify the rating criteria, eg 85% = Very Good " readonly>
								</div>
								
								<div class="form-group">
									<label for="remark_level">Remarks :</label>
									<textarea class="form-control" id="level_remark2" name="level_remark2" rows="2"><?= htmlspecialchars($row['level_remark2'] ?? '') ?></textarea>
								</div>

							
							<!-- Behavioral Section -->
							<div class="section-title">Behavioral Change</div>
							<h4>3. Did the employee apply his / her newly acquired skills and knowledge to his / her jobs ? <span class="required-star">*</span></h4>
							<div class="form-group rating-container">
								<div class="form-check">
									<input class="form-check-input" type="radio" name="behavioral_rating" value="Excellent" onclick="enablePercentInput()" <?php if ($row['behavioral_rating'] == "Excellent") echo "checked"; ?> required>
									<label class="form-check-label">
										>  90 %  :  Excellent  - Excellent understanding of the topics learned and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="behavioral_rating" value="Very Good" onclick="enablePercentInput()" <?php if ($row['behavioral_rating'] == "Very Good") echo "checked"; ?> required>
									<label class="form-check-label">
										80 %  -  89 %  :  Very Good  -  Very good understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="behavioral_rating" value="Good" onclick="enablePercentInput()" <?php if ($row['behavioral_rating'] == "Good") echo "checked"; ?> required>
									<label class="form-check-label">
										70 %  -  79 %  :  Good  -  Good understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="behavioral_rating" value="Satisfactory" onclick="enablePercentInput()" <?php if ($row['behavioral_rating'] == "Satisfactory") echo "checked"; ?> required>
									<label class="form-check-label">
										60 %  -  69 %  :  Satisfactory  -  Can understand most of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="behavioral_rating" value="Fair" onclick="enablePercentInput()" <?php if ($row['behavioral_rating'] == "Fair") echo "checked"; ?> required>
									<label class="form-check-label">
										50 %  -  59 %  :  Fair  -  Understand only some of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="behavioral_rating" value="Poor" onclick="enablePercentInput()" <?php if ($row['behavioral_rating'] == "Poor") echo "checked"; ?> required>
									<label class="form-check-label">
										<  50 %  :  Poor  -  Very weak understanding and requires further explanation
									</label>
								</div>
									
									<input type="number" class="form-control" id="behavioral_percent" name="behavioral_percent" value="<?= htmlspecialchars($row['behavioral_percent'] ?? '') ?>" required placeholder="Please specify the rating criteria, eg 85% = Very Good " readonly>
								</div>
								
								<div class="form-group">
									<label for="remark_level">Remarks :</label>
									<textarea class="form-control" id="behavioral_remark" name="behavioral_remark" rows="2"><?= htmlspecialchars($row['behavioral_remark'] ?? '') ?></textarea>
								</div>
							
							<!-- Result Section -->
							<div class="section-title">Result Training Attended</div>
							<h4>4. Did the training has any measurable business impact ? <span class="required-star">*</span></h4>
							<div class="form-group rating-container">
								<div class="form-check">
									<input class="form-check-input" type="radio" name="result_rating" value="Excellent" onclick="enablePercentInput()" <?php if ($row['result_rating'] == "Excellent") echo "checked"; ?> required>
									<label class="form-check-label">
										>  90 %  :  Excellent  -  Excellent understanding of the topics learned and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="result_rating" value="Very Good" onclick="enablePercentInput()" <?php if ($row['result_rating'] == "Very Good") echo "checked"; ?> required>
									<label class="form-check-label">
										80 %  -  89 %  :  Very Good  -  Very good understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="result_rating" value="Good" onclick="enablePercentInput()" <?php if ($row['result_rating'] == "Good") echo "checked"; ?> required>
									<label class="form-check-label">
										70%  -  79 %  :  Good  -  Good understanding and can explain to others
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="result_rating" value="Satisfactory" onclick="enablePercentInput()" <?php if ($row['result_rating'] == "Satisfactory") echo "checked"; ?> required>
									<label class="form-check-label">
										60 %  -  69 %  :  Satisfactory  -  Can understand most of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="result_rating" value="Fair" onclick="enablePercentInput()" <?php if ($row['result_rating'] == "Fair") echo "checked"; ?> required>
									<label class="form-check-label">
										50 %  -  59 %  :  Fair  -  Understand only some of the topics learned
									</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="radio" name="result_rating" value="Poor" onclick="enablePercentInput()" <?php if ($row['result_rating'] == "Poor") echo "checked"; ?> required>
									<label class="form-check-label">
										< 50 %  :  Poor  -  Very weak understanding and requires further explanation
									</label>
								</div>
									
									<input type="number" class="form-control" id="result_percent" name="result_percent" value="<?= htmlspecialchars($row['result_percent'] ?? '') ?>" required placeholder="Please specify the rating criteria, eg 85% = Very Good " readonly>
								</div>
								
								<div class="form-group">
									<label for="remark_level">Remarks :</label>
									<textarea class="form-control" id="result_remark" name="result_remark" rows="2"><?= htmlspecialchars($row['result_remark'] ?? '') ?></textarea>
								</div>

			 					<div>
			 					  <input type="hidden" name="id" value="<?= htmlspecialchars($row['id'] ?? '') ?>">
								  <input type="submit" value="submit" class="btn btn-success btn-block">
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
					if (!this.checkValidity()) {
						event.preventDefault(); // Stop form submission if invalid
						swal("Error!", "Please correct the errors before submitting.", "error");
					} else {
						event.preventDefault(); // Prevent default submission to show the popup

						// Show success message with animation
						swal({
							title: "Approve!",
							text: "Evaluation Approve successfully!",
							icon: "success",
							timer: 2000, 
							buttons: false,
						});

						// Submit form after 2 seconds (to allow animation to be seen)
						setTimeout(() => {
							this.submit(); // Now the form will actually be submitted
						}, 2000);
					}
				});

				document.addEventListener("DOMContentLoaded", function () {
					const radioButtons = document.querySelectorAll("input[name='level_rating']");
					const remarkField = document.getElementById("level_remark");

					// Function to enable remark field when a rating is selected
					function enableRemark() {
						remarkField.required = true; // Make it required
					}

					// Attach event listener to each radio button
					radioButtons.forEach(radio => {	
						radio.addEventListener("change", enableRemark);
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