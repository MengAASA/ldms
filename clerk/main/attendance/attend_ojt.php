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

    <body onload="startTime()" style="background-image:url('../../../asset/image/bg-new.png');zoom: 75%;">
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
                                    <strong id="title">Attendance & Feedback</strong>
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
                                            <legend id="leg1">Training Evaluation : <span id="traintitle"></span></legend>
                                            <div id="ojt">
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>1. Please highlight what have you learned from the OJT <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <input type="text" name="que1" id="que1" class="form-control" placeholder="Your Answer" autocomplete="off" />
                                                    </div>
                                                </div>
                                                <br>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>Pre & Post Evaluation</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label>Please assess the training program using the scale below, kindly rate the scale below</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label>(5 - Excellent > 90%) (4 - Good 80% - 89%) (3 - Average 70% - 79%) (2 - Fair 50% - 69%) (1 - Poor < 49%)</label>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>1. Your knowledge / skill before training  (Pengetahuan/kemahiran sebelum training) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que2" name="que2" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que2" name="que2" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que2" name="que2" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que2" name="que2" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que2" name="que2" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-md-12">
                                                        <label>2. Your knowledge / skill after training (Pengetahuan/kemahiran selepas latihan) <span style="color:red;">*</span></label>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que3" name="que3" value="1" required>
                                                        <label>1</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que3" name="que3" value="2">
                                                        <label>2</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que3" name="que3" value="3">
                                                        <label>3</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que3" name="que3" value="4">
                                                        <label>4</label><br>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <input type="radio" id="que3" name="que3" value="5">
                                                        <label>5</label><br>
                                                    </div>
                                                    <div class="col-md-1"></div>
                                                </div>
                                                <br>
                                            </div>
                                            <div class="form-group">
                                                <input type="hidden" name="userid" id="userid" value="<?php echo $_SESSION['id']?> " />
								                <input type="hidden" name="btn_action" id="btn_action" />
                                                <input type="hidden" name="ojtid" id="ojtid" />
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
        var action = localStorage.getItem("setaction");

        if (action == 'addojtattendance') {
            $('#ojtid').val(id);
            $('#btn_action').val(action);
            $.ajax({
                url:"fetch_training.php",
                method:"POST",
                data:{id:id,action:'gettitle'},
                dataType:"json",
                success:function(data) {
                    $('#traintitle').text(data[0].title);
                }
            })
        }

        $(document).on('submit', '#attendance_form', function(event){
            event.preventDefault();
            $("#spinner-div").show();
            var form_data = $(this).serialize();
            $.ajax({
                url:"attendance_action.php",
                method:"POST",
                data:form_data,
                success:function(data)
                {
                    var response = JSON.parse(data)
                    if((response.message) == 'insert_ojttrain') {
                        swal(
                            'Saved!',
                            'Your evaluation has been recorded.',
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
