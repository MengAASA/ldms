<?php
    session_start();

    $canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
        isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && (
            ($_SESSION['role'] == '' && $_SESSION['usertype'] == '') ||
            ($_SESSION['role'] == 'CLERK' && $_SESSION['usertype'] == 'MAIN')
        )
    );
    include_once __DIR__ . '/../../../skill_matrix_department.php';
    $canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == '')) {

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
        #traininglist {
            width: 100% !important;
        }
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
                        <li><a href="training.php">MY TRAINING</a></li>
                        <li><a href="pme.php">PME</a></li>
                        <li><a href="../tna/tna.php">TNA</a></li>
                        <?php if ($canViewSkillMatrix) { ?><li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li><?php } ?>
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
                                <div class="col-md-6" style="margin-top: 10px;">
                                    <strong id="title">Training Records</strong>
                                </div>
                                <div class="col-md-6" align="right">
                                    <button type="button" name="add_ojt" id="add_ojt" class="btn btn-success btn-md">Add OJT <i class="far fa-arrow-alt-circle-right"></i> </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="row">
                                <div class="col-sm-4">
                                    <input type="text" name="startdate" id="startdate" class="form-control" placeholder="Insert Start Date" autocomplete="off" />
                                </div>
                                <div class="col-sm-4">
                                    <input type="text" name="enddate" id="enddate" class="form-control" placeholder="Insert End Date" autocomplete="off" />
                                </div>
                                <div class="col-sm-4" align="left" style="white-space:nowrap;">
                                    <button type="button" name="filter_date" id="filter_date" class="btn btn-info btn-md">Filter <i class="fa fa-search"></i> </button>
                                    <button type="button" name="clear_filter" id="clear_filter" class="btn btn-info btn-md">Clear <i class="fa fa-times"></i> </button>
                                </div>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <table id="traininglist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Venue</th>
                                                <th>Status</th>
                                                <th>Training Hour</th>
                                                <th width="130px">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                        <tfoot>
                                            <th colspan="8">Total Training Hour</th>
                                            <th><span id="totalhourall"></span></th>
                                        </tfoot>
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

        var contracttype = "<?php echo $_SESSION['designation']?>";

        if (contracttype == 'CONTRACT') {
            $('#add_ojt').hide();
        }

        $('#startdate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

        $('#enddate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

        var userid = '<?php echo $_SESSION['id']?>';

        $('#add_ojt').click(function(){
            localStorage.setItem("setaction", 'addojt');
            window.location = "attendance_ojt.php";
        });

        function fetch_data(action, userid, startdate, enddate) {
            var trainingdataTable = $('#traininglist').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "responsive": true,
                "autoWidth": false,
                "pageLength": 10,
                "info": true,
                "ajax": {
                    url: "fetch_training.php",
                    type: "POST",
                    dataSrc: '',
                    data: { action: action, userid: userid, startdate: startdate, enddate: enddate },
                },
                "columns": [
                    {
                        "data": "id",
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    {
                        "data": "title"
                    },
                    {
                        "data": "type"
                    },
                    {
                        "data": "startdate"
                    },
                    {
                        "data": "enddate"
                    },
                    {
                        "data": "venue"
                    },
                    {
                        "data": "status"
                    },
                    {
                        "data": "totalhour"
                    },
                    {
                        "data": "btnedit"
                    }
                ],
                "columnDefs": [
                    { className: 'text-center', targets: [0, 3, 4, 6, 7, 8] }
                ],
                "order": [[3, 'desc']],
                fnFooterCallback: function (nRow, aaData, iStart, iEnd, aiDisplay) {
                    var api = this.api(), data;

                    // converting to integer to find total
                    var intVal = function (i) {
                        return typeof i === 'string' ? i.replace(/[\$,]/g, '') * 1 : typeof i === 'number' ? i : 0;
                    };

                    // computing column Total of the complete result
                    var totalhourall = api.column(7).data().reduce(function (a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);

                    // Update footer by showing the total with the reference of the column index
                    $(api.column(7).footer()).html(totalhourall);
                }
            });
        }
        fetch_data('load_training',userid,'','');

        $('#filter_date').click(function(){
            var startdate = $('#startdate').val();
            var enddate = $('#enddate').val();

            $('#traininglist').DataTable().destroy();
            fetch_data('filter_training',userid,startdate,enddate);
        });

        $('#clear_filter').click(function(){
            $('#traininglist').DataTable().destroy();
            fetch_data('load_training',userid,'','');
            $('#startdate').val('');
            $('#enddate').val('');
        });

        $(document).on('click', '.attendance', function(){
            var id = $(this).attr("id");
            localStorage.setItem("setaction", 'editattendance');
            localStorage.setItem("setid", id);
            window.location = "attendance.php";
        });

        $(document).on('click', '.editownojt', function(){
            var id = $(this).attr("id");
            localStorage.setItem("setaction", 'viewojtattendance');
            localStorage.setItem("setid", id);
            window.location = "attendance_ojt.php";
        });

        $(document).on('click', '.attendance_ojt', function(){
            var id = $(this).attr("id");
            localStorage.setItem("setaction", 'addojtattendance');
            localStorage.setItem("setid", id);
            window.location = "attend_ojt.php";
        });

        $(document).on('click', '.deleteownojt', function(){
            var id = $(this).attr("id");
            var btn_action = 'deleteojt';
            swal({
                title: "Delete OJT?",
                text: "Are you sure?",
                icon: "warning",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Confirm"]
            })
            .then((isConfirm) => {
                if (isConfirm) {
                    $.ajax({
                        url:"attendance_action.php",
                        method:"POST",
                        data:{id:id, btn_action:btn_action},
                        success:function(data)
                        {
                            var response = JSON.parse(data)
                            if((response.message) == 'deleteojt') {
                                swal(
                                    'Deleted!',
                                    'The ojt has been deleted.',
                                    'success'
                                )
                                $('#traininglist').DataTable().destroy();
                                fetch_data('load_training',userid,'','');
                            }
                            else if((response.message) == 'error') {
                                swal(
                                    'Not Deleted!',
                                    'The training cannot be deleted. Please refer the IT.',
                                    'error'
                                )
                                $('#traininglist').DataTable().destroy();
                                fetch_data('load_training',userid,'','');
                            }
                        }
                    });
                }else{
                    swal("Cancelled", "The training has not been deleted", "error");
                }
            })
        });

    </script>
</html>
<?php
    }else{
        header("Location: ../../login.php");
        exit();
    }
?>