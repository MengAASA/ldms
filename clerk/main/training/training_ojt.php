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
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" href="../../../asset/css/datepicker.css">
        <script src="../../../asset/js/bootstrap-datepicker1.js"></script>
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
        <style>
            #traininglist {
                width: 100% !important;
            }
        </style>
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
						<li><a href="training_ojt.php">ALL TRAINING</a></li>
						<li><a href="../attendance/training.php">MY TRAINING</a></li>
						<li><a href="../attendance/pme.php">PME</a></li>
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
            <div class="row">
                <div class="col-md-12">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-8" style="margin-top: 10px;">
                                    <strong>OJT Training List</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <div class="col-md-9">
                                        <button type="button" name="importojt" id="importojt" data-toggle="modal" data-target="#excelupload" class="btn btn-success btn-xm"><i class="fa fa-plus"></i> Import Excel</button>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" name="addojt" id="addojt" class="btn btn-success btn-md"><i class="fa fa-plus"></i> Add OJT</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="row">
                                <div class="col-md-12">
                                    <table class="table table-bordered table-striped">
                                        <th>
                                            <div class="col-md-3"></div>
                                            <div class="col-md-6">
                                                <div class="col-md-4">
                                                    <input type="text" name="startdate" id="startdate" class="form-control" placeholder="Insert Start Date" autocomplete="off" />
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" name="enddate" id="enddate" class="form-control" placeholder="Insert End Date" autocomplete="off" />
                                                </div>
                                                <div class="col-md-2" align="right">
                                                    <button type="button" name="filter_date" id="filter_date" class="btn btn-info btn-md">Filter <i class="fa fa-search"></i> </button>
                                                </div>
                                                <div class="col-md-2" align="left">
                                                    <button type="button" name="clear_filter" id="clear_filter" class="btn btn-info btn-md">Clear <i class="fa fa-times"></i> </button>
                                                </div>
                                            </div>
                                            <div class="col-md-3"></div>
                                        </th>
                                    </table>
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
                                                <th width="70px">Start Date</th>
                                                <th width="70px">End Date</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Trainer</th>
                                                <th>Total Days</th>
                                                <th>Total Hours</th>
                                                <th>Total Participant</th>
                                                <th width="145px">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="7">Summary Calculation</th>
                                                <th><span id="totalallday"></span></th>
                                                <th><span id="totalallhour"></span></th>
                                                <th><span id="totalallparty"></span></th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
					</div>
				</div>
			</div>
        </div>
        <div id="excelupload" class="modal fade">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Import Training Records</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="col-md-3"></div>
                                <div class="col-md-6">
                                    <p align="left">
                                        New here? <a href="export_ojt_template.php">Download the Excel template</a> first -
                                        it explains the format and has example rows.
                                    </p>
                                </div>
                                <div class="col-md-3"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <form id="upload_csv" align= "center" class="form-horizontal"  method="post" enctype="multipart/form-data">
                                    <div class="col-md-3"></div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <input type="file" name="import_file" class="form-control"> <br>
                                        </div>
                                        <div class="form-group">
                                            <input type="hidden" name="clerkid" id="clerkid" value="<?php echo $_SESSION['id']?>" />
                                            <input type="submit" name="action" id="action" class="btn btn-success btn-block btn-xm" value="UPLOAD OJT">
                                        </div>
                                    </div>
                                    <div class="col-md-3"></div>
								</form>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="id" id="id" />
                        <input type="hidden" name="btn_action" id="btn_action" />
                        <button type="button" class="btn btn-info btn-xm" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
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

        $('#startdate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

        $('#enddate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

        $('#addojt').click(function(){
            localStorage.setItem("setaction", 'addtraining');
            window.location = "manage_training.php";
        });

        function getTotalSummary(action,userid,startdate,enddate){
            $.ajax({
                url:"fetch_training.php",
                type:"POST",
                data:{action:action,userid:userid,startdate:startdate,enddate:enddate},
                dataType:"JSON",
                success:function(data)
                {
                    $('#totalallparty').text(data.totalmans);
                }
            })
        }

        function fetch_data(action,startdate,enddate,userid){
            var trainingdataTable = $('#traininglist').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "responsive": true,
                "autoWidth": false,
                "pageLength": 10,
                "info": true,
                "ajax":{
                    url:"fetch_training.php",
                    type:"POST",
                    dataSrc: '',
                    data : {action:action,startdate:startdate,enddate:enddate,userid:userid},
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
                        "data": "startdate"
                    },
                    {
                        "data": "enddate"
                    },
                    {
                        "data": "starttime"
                    },
                    {
                        "data": "endtime"
                    },
                    {
                        "data": "trainername"
                    },
                    {
                        "data": "totalday"
                    },
                    {
                        "data": "totalhour"
                    },
                    {
                        "data": "totalman"
                    },
                    {
                        "data": "btnmodify"
                    }
                ],
                "columnDefs": [
                    { className: 'text-center', targets: [0,2,3,4,5,7,8,9,10] }
                ],
                fnFooterCallback: function(nRow, aaData, iStart, iEnd, aiDisplay) {
                    var api = this.api(), data;
 
                    // converting to interger to find total
                    var intVal = function (i) {
                        return typeof i === 'string' ? i.replace(/[\$,]/g, '')*1 : typeof i === 'number' ? i : 0;
                    };
 
                    // computing column Total of the complete result 
                    var totalallday = api.column(7).data().reduce(function (a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);    

                    var totalallhour = api.column(8).data().reduce(function (a, b) {
                        return parseFloat(intVal(a) + intVal(b)).toFixed(2);
                    }, 0); 

                    var totalallparty = api.column(9).data().reduce(function (a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);
                    
                    // Update footer by showing the total with the reference of the column index 
	                $( api.column(7).footer() ).html(totalallday);
                    $( api.column(8).footer() ).html(totalallhour);
                    // $( api.column(9).footer() ).html(totalallparty);
                }
            });
        }

        var userid = <?php echo $_SESSION['id']?>;
        localStorage.setItem("setclerkid", userid);

        var starttrdate = localStorage.getItem("setstarttr");
        var endtrdate = localStorage.getItem("setendtr");
        if (!starttrdate || starttrdate === 'null' || starttrdate === 'undefined') {
            $('#startdate').val('');
            $('#enddate').val('');
            fetch_data('load_ojt','','',userid);
            getTotalSummary('load_ojtsummary',userid,'','');
        }else{
            $('#startdate').val(starttrdate);
            $('#enddate').val(endtrdate);
            fetch_data('load_ojt',starttrdate,endtrdate,userid);
            getTotalSummary('load_ojtsummary',userid,starttrdate,endtrdate);
        }

        $('#filter_date').click(function(){
            var startdate = $('#startdate').val();
            var enddate = $('#enddate').val();

            $('#traininglist').DataTable().destroy();
            fetch_data('load_ojt',startdate,enddate,userid);
			getTotalSummary('load_ojtsummary',userid,startdate,enddate);
        });

        $('#clear_filter').click(function(){
            $('#traininglist').DataTable().destroy();
            fetch_data('load_ojt','','',userid);
			getTotalSummary('load_ojtsummary',userid,'','');
            $('#startdate').val('');
            $('#enddate').val('');
        });

        $(document).on('click', '.edit', function(){
            var id = $(this).attr("id");
            localStorage.setItem("setaction", 'edittraining');
            localStorage.setItem("setid", id);
            window.location = "manage_training.php";
            var startdate = $('#startdate').val();
            var enddate = $('#enddate').val();

            if (startdate != '') {
                localStorage.setItem("setstart", startdate);
            }else {
                localStorage.setItem("setstart", '');
            }

            if (enddate != '') {
                localStorage.setItem("setend", enddate);
            }else {
                localStorage.setItem("setend", '');
            }
        });

        $(document).on('click', '.view', function(){
            var id = $(this).attr("id");
            localStorage.setItem("setaction", 'viewparty');
            localStorage.setItem("setid", id);
            window.location = "participant/view_participant.php";
            var startdate = $('#startdate').val();
            var enddate = $('#enddate').val();

            if (startdate != '') {
                localStorage.setItem("setstart", startdate);
            }else {
                localStorage.setItem("setstart", '');
            }

            if (enddate != '') {
                localStorage.setItem("setend", enddate);
            }else {
                localStorage.setItem("setend", '');
            }
        });

        $(document).on('click', '.delete', function(){
            var id = $(this).attr("id");
            var btn_action = 'deletetraining';
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
                        url:"training_action.php",
                        method:"POST",
                        data:{id:id,btn_action:btn_action},
                        success:function(data)
                        {
                            var response = (typeof data === 'string') ? JSON.parse(data) : data;
                            if((response.message) == 'delete') {
                                swal(
                                    'Deleted!',
                                    'The OJT has been deleted.',
                                    'success'
                                )
                                .then(function() {
                                    $('#traininglist').DataTable().destroy();
                                    fetch_data('load_ojt','','',userid);
                                })
                            }
                            else if((response.message) == 'error') {
                                swal(
                                    'Not Deleted!',
                                    'The OJT cannot be deleted. Please refer the IT.',
                                    'error'
                                )
                                .then(function() {
                                    $('#traininglist').DataTable().destroy();
                                    fetch_data('load_ojt','','',userid);
                                })
                            }
                        }
                    });
                }else{
                    swal("Cancelled", "The OJT has not been deleted", "error");
                }
            })
        });

        $(document).on("submit",'#upload_csv', function(e){
            e.preventDefault();
            var uploadForm = this;
            swal({
                title: "Import this file?",
                text: "Are you sure?",
                icon: "warning",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Confirm"]
            })
            .then((isConfirm) => {
                if (isConfirm) {
                    $.ajax({
                        url:"import_action.php",
                        method:"POST",
                        data:new FormData(uploadForm),
                        contentType:false,          // The content type used when sending data to the server.
                        cache:false,                // To unable request pages to be cached
                        processData:false,          // To send DOMDocument or non processed data file it is set to false
                        dataType:"json",
                        success:function(data)
                        {
                            if (data.message === 'ok') {
                                var summary = data.trainings_created + ' training(s) added with ' + data.participants_added + ' participant(s) in total.';
                                if (data.completed_with_feedback > 0) {
                                    summary += ' ' + data.completed_with_feedback + ' participant(s) imported as already completed with feedback.';
                                }
                                if (data.completed_short_ojt > 0) {
                                    summary += ' ' + data.completed_short_ojt + ' participant(s) auto-completed (OJT of 4 hours or less).';
                                }
                                swal(
                                    'Imported!',
                                    summary,
                                    'success'
                                ).then(function() {
                                    location.reload();
                                });
                            } else {
                                var errors = data.errors || ['The file could not be imported.'];
                                swal(
                                    'Nothing was imported',
                                    errors.join('\n'),
                                    'error'
                                );
                            }
                        },
                        error:function(xhr)
                        {
                            swal('Import failed', 'The server returned an unexpected response. Please try again.', 'error');
                            console.error('import_action.php error:', xhr.status, xhr.responseText);
                        }
                    });
                }else{
                    swal("Cancelled", "The file is not imported", "error");
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
