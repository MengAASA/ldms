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
        <script src="../../../asset/js/bootstrap.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
        <script src="../../../asset/js/chartjs-plugin-labels.js"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
		<link rel="stylesheet" href="../../../asset/css/datepicker.css">
        <script src="../../../asset/js/bootstrap-datepicker1.js"></script>
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
    </head>

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
						<li><a href="../attendance/training.php">MY TRAINING</a></li>
						<li><a href="../attendance/pme.php">PME</a></li>
						<li><a href="tna_list.php">TNA</a></li>
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
            <div class="row">
                <div class="col-md-12">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-11" style="margin-top: 10px;">
                                    <strong>TNA List</strong>
                                </div>
                                <div class="col-md-1" align="right">
                                    <button type="button" name="add_tna" id="add_tna" class="btn btn-success btn-md">MY TNA <i class="far fa-arrow-alt-circle-right"></i> </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="row">
                                <div class="col-sm-12">
                                    <table id="userlist" class="table">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Job Grade</th>
                                                <th width="65px">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td>l</td>
                                                <td><button type="submit" id="1" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button></td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>ll</td>
                                                <td><button type="submit" id="2" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button></td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>lll</td>
                                                <td><button type="submit" id="3" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button></td>
                                            </tr>
                                            <tr>
                                                <td>4</td>
                                                <td>lV</td>
                                                <td><button type="submit" id="4" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button></td>
                                            </tr>
                                            <tr>
                                                <td>5</td>
                                                <td>V</td>
                                                <td><button type="submit" id="5" class="btn btn-info btn-sm view" style="margin-left:5px;"><i class="fa fa-edit"></i> VIEW TNA</button></td>
                                            </tr>
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

        var userid = <?php echo $_SESSION['id']?>;
        var department = '<?php echo $_SESSION['department']?>';

        $('#add_tna').click(function(){
            localStorage.setItem("setaction", 'addtna');
            localStorage.setItem("setid", userid);
            window.location = "manage_tna.php";
        });

        $(document).on('click', '.view', function(){
            var id = $(this).attr("id");
            localStorage.setItem("setaction", 'addtnagrade');
            localStorage.setItem("setgrade", id);
            localStorage.setItem("setid", id+'/'+department);
            window.location = "manage_grade.php";
        });
    </script>
</html>
<?php
    }else{
         header("Location: ../../login.php");
         exit();
    }
?>
