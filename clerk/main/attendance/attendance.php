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

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'CLERK') && ($_SESSION['usertype'] == 'MAIN')) {

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
    </head>

    <style>
        #spinner-div {
            position: fixed;
            display: none;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 2;
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
            <div id="spinner-div">
                <img src="../../../asset/image/loading.gif" id="ajaxSpinnerImage" title="working..." style="margin-top: 350px;"/>
            </div>
            <div class="row">
                <div class="col-md-12">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-1">
                                    <button type="button" name="back_training" id="back_training" class="btn btn-success btn-md"><i class="far fa-arrow-alt-circle-left"></i> Back to Training List</button>
                                </div>
                                <div class="col-md-11">
                                    
                                </div>
                            </div>
                        </div>
					</div>
				</div>
			</div>
            <div class="row">
                <div class="col-md-12">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-11">
                                    <strong id="title">Attendance & Post Evaluation</strong>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-2">

                                </div>
                                <div class="col-md-8">
                                    <form method="post" id="attendance_form">
                                        <fieldset style="border-radius:10px;">
                                            <div id=exec>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <legend id="leg1">SECTION A: Training Content & Quality of Training</legend>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label>Please assess the training program using the scale below, kindly rate the scale below</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label>(5 - Excellent 4 - Good 3 - Average 2 - Fair 1 - Poor)</label>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>1. Knowledge / skill presented in the training is suitable for job application (Pengetahuan/kemahiran yang disampaikan dalam kursus ini sesuai diaplikasikan di  tempat  kerja) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans1" name="ans1" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans1" name="ans1" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans1" name="ans1" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans1" name="ans1" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans1" name="ans1" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>2. The objective of this course is clearly stated in the beginning of the course (Tujuan kursus ini dinyatakan dengan jelas pada permulaan kursus) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans2" name="ans2" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans2" name="ans2" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans2" name="ans2" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans2" name="ans2" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans2" name="ans2" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>3. Training content provided is complete and suitable (Bahan latihan yang digunakan adalah lengkap dan bersesuaian) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans3" name="ans3" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans3" name="ans3" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans3" name="ans3" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans3" name="ans3" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans3" name="ans3" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>4. Activities carried out such as case study, simulation and group discussion is suitable and effective (Aktiviti yang dijalankan seperti kajian kes, simulasi dan perbincangan kumpulan adalah sesuai dan berkesan) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans4" name="ans4" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans4" name="ans4" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans4" name="ans4" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans4" name="ans4" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans4" name="ans4" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>5. Training duration is sufficient (Jangkamasa kursus adalah sesuai) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans5" name="ans5" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans5" name="ans5" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans5" name="ans5" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans5" name="ans5" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans5" name="ans5" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>6. Quality and cleanliness of food preparation throughout the course (Kualiti dan kebersihan makanan yang disediakan sepanjang kursus adalah baik) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans6" name="ans6" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans6" name="ans6" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans6" name="ans6" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans6" name="ans6" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans6" name="ans6" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>7. Complete training facilities (Kemudahan kursus adalah lengkap) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans7" name="ans7" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans7" name="ans7" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans7" name="ans7" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans7" name="ans7" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans7" name="ans7" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>8. Overall evaluation (Penilaian secara keseluruhan) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans8" name="ans8" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans8" name="ans8" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans8" name="ans8" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans8" name="ans8" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans8" name="ans8" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <legend id="leg1">SECTION B: Facilitator / Trainer</legend>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label>Please assess the training facilitator/trainer using the scale below, kindly rate the scale below </label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label>(5 - Excellent 4 - Good 3 - Average 2 - Fair 1 - Poor)</label>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>1. Knowledge on the subject (Pengetahuan berkaitan tajuk kursus) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans9" name="ans9" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans9" name="ans9" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans9" name="ans9" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans9" name="ans9" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans9" name="ans9" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>2. Methods and manner of presentation (Cara dan gaya penyampaian) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans10" name="ans10" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans10" name="ans10" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans10" name="ans10" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans10" name="ans10" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans10" name="ans10" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>3. Overall evaluation (Penilaian secara keseluruhan) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans11" name="ans11" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans11" name="ans11" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans11" name="ans11" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans11" name="ans11" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="ans11" name="ans11" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <legend id="leg1">SECTION C: Recommendation & Suggestion</legend>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>1. Would you recommend the course to other employees? (Adakah anda akan mensyorkan kursus ini kepada pekerja lain?) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-10">
                                                        <div class="col-md-6">
                                                            <input type="radio" id="ans12" name="ans12" value="Yes" required>
                                                            <label>Yes</label><br>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <input type="radio" id="ans12" name="ans12" value="No">
                                                            <label>No</label><br>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>2. What have you benefited from the course? (Apakah faedah-faedah yang telah anda perolehi daripada kursus ini?)</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <textarea required id="ans13" name="ans13" rows="2" cols="160"></textarea>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>3. What is your plan for improvement based on the knowledge gained from this course? (Short term - within 6 months) Apakah perancangan anda untuk pembaikan berdasarkan pengetahuan diperolehi dari kursus ini? (Jangkamasa pendek - dalam tempoh 6 bulan)</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <textarea required id="ans14" name="ans14" rows="2" cols="160"></textarea>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>4. What is your plan for improvement based on the knowledge gained from this course? ( Long term within more than 1 year)  Apakah perancangan anda untuk pembaikan berdasarkan pengetahuan diperolehi dari kursus ini? (Jangkamasa panjang - dalam tempoh lebih 1 tahun)</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <textarea required id="ans15" name="ans15" rows="2" cols="160"></textarea>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>5. Please write your comments/suggestions for improvement, problem encountered and other recommendations regarding the course  (Sila nyatakan cadangan-cadangan untuk kemajuan, masalah-masalah yang timbul dan lain-lain cadangan berkenaan kursus tersebut)</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <textarea required id="ans16" name="ans16" rows="2" cols="160"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <input type="hidden" name="id" id="id" value="<?php echo $_SESSION['id']?> " />
								                <input type="hidden" name="btn_action" id="btn_action" value="editattendance" />
                                                <input type="submit" name="action" id="action" class="btn btn-info" style="width:100px;" value="Save" />
                                            </div>
                                        </fieldset>
                                    </form>
                                </div>
                                <div class="col-md-2">

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

        $('#back_training').click(function(){
            window.location = "training.php";
        });

        var id = localStorage.getItem("setid");

        $('#startdate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

        $('#enddate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

        $('#starttime').timepicker({
            minuteStep: 1,
            showSeconds: false,
            showMeridian: false
        });

        $('#endtime').timepicker({
            minuteStep: 1,
            showSeconds: false,
            showMeridian: false
        });

        $(function(){
            $.post("fetch_training.php",{action:"load_staff"},function(data){
                $('#internalname').html(data);
            });
        });

        $(document).on('submit', '#attendance_form', function(event){
            event.preventDefault();
            $("#spinner-div").show();
            $('#id').val(id);
            var form_data = $(this).serialize();
            $.ajax({
                url:"attendance_action.php",
                method:"POST",
                data:form_data,
                success:function(data)
                {
                    var response = JSON.parse(data)
                    if((response.message) == 'insert_public') {
                        swal(
                            'Saved!',
                            'Your attendance has been recorded.',
                            'success'
                        ).then(function() {
                            window.location = "training.php";
        				})
                    }
                    else if((response.message) == 'insert_ojt') {
                        swal(
                            'Saved!',
                            'Your attendance has been recorded.',
                            'success'
                        ).then(function() {
                            window.location = "training.php";
        				})
                    }else if((response.message) == 'error') {
                        swal(
                            'Failed!',
                            'The operation cannot be done. Please refer to IT',
                            'error'
                        ).then(function() {
                            location.reload();
        				})
                    }
                },
                complete: function () {
                    $("#spinner-div").hide();
                }
            })
        });
    </script>
</html>
<?php
    }else{
        header("Location: ../login.php");
        exit();
    }
?>
