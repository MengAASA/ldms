<?php
    session_start();
    include "../../dbconn.php";

    if (isset($_SESSION['id']) && (!isset($_SESSION['designation']) || !isset($_SESSION['hodid']))) {
        $sessionUserId = (int) $_SESSION['id'];
        $sessionUserQuery = mysqli_query($conn, "SELECT designation, hodid FROM user WHERE id = '$sessionUserId' LIMIT 1");
        if ($sessionUserQuery && $sessionUserRow = mysqli_fetch_assoc($sessionUserQuery)) {
            $_SESSION['designation'] = $sessionUserRow['designation'];
            $_SESSION['hodid'] = $sessionUserRow['hodid'];
        }
    }

    $canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
        isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && (
            ($_SESSION['role'] == '' && $_SESSION['usertype'] == '') ||
            ($_SESSION['role'] == 'CLERK' && $_SESSION['usertype'] == 'MAIN')
        )
    );
    include_once __DIR__ . '/../../skill_matrix_department.php';
    $canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'CLERK') && ($_SESSION['usertype'] == 'MAIN')) {

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Learning and Development Management System</title>
        <script src="../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../asset/css/bootstrap.min.css" />
        <script src="../../asset/js/bootstrap.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
        <script src="../../asset/js/chartjs-plugin-labels.js"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
		<link rel="stylesheet" href="../../asset/css/datepicker.css">
        <script src="../../asset/js/bootstrap-datepicker1.js"></script>
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
    </head>

    <body onload="startTime()" style="background-image:url('../../asset/image/bg-try.png');zoom: 75%;">
        <br>
        <div class="container-fluid">
            <div class="row">
				<div class="col-md-10">
    				<img src= "../../asset/image/lndlogo.gif" height="50" width="290">
				</div>
				<div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;">

				</div>
			</div>
            <nav class="navbar navbar-inverse" >
                <div class="container-fluid ">
    				<ul class="nav navbar-nav">
						<li><a href="dashboard.php">HOME</a></li>
						<li><a href="staff/staff.php">CONTRACT STAFF LIST</a></li>
						<li><a href="training/training_ojt.php">ALL TRAINING</a></li>
						<li><a href="attendance/training.php">MY TRAINING</a></li>
						<li><a href="attendance/pme.php">PME</a></li>
						<li><a href="tna/tna_list.php">TNA</a></li>
                        <?php if ($canViewSkillMatrix) { ?>
                            <li><a href="skill-matrix/skill-matrix.php">SKILL MATRIX</a></li>
                        <?php } ?>
                        <li><a href="../../admin/archive/export.php">EXPORT</a></li>
                        <li><a href="password/password.php">CHANGE PASSWORD</a></li>
    				</ul>
    				<ul class="nav navbar-nav navbar-right">
    					<li class="dropdown">
    						<a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="label label-pill label-danger count"></span> <?php echo $_SESSION['fullname']?> </a>
    						<ul class="dropdown-menu">
    							<li><a href="../../logout.php">LOGOUT</a></li>
    						</ul>
    					</li>
    				</ul>
    			</div>
            </nav>
			<div class="row">
                <div class="col-md-12">
					<div class="panel panel-default">
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-6" align="left" style="margin-top:10px;"><span id="datarecord"></span></div>
								<div class="col-md-6">
									<div class="col-md-4">
										<select class="form-control" id="mode" name="mode">
											<option value="manhour">TOTAL MAN HOUR</option>
											<option value="totalhour">AVERAGE TOTAL HOUR</option>
										</select>
									</div>
									<div class="col-md-2">
										<input type="text" name="startdate" id="startdate" class="form-control" placeholder="Insert Start Date" autocomplete="off" />
									</div>
									<div class="col-md-2">
										<input type="text" name="enddate" id="enddate" class="form-control" placeholder="Insert End Date" autocomplete="off" />
									</div>
									<div class="col-md-2" align="right">
										<button type="button" name="filter_date" id="filter_date" class="btn btn-info btn-md">Filter <i class="fa fa-search"></i> </button>
									</div>
									<div class="col-md-2" align="left">
										<button type="button" name="clear_filter" id="clear_filter" class="btn btn-info btn-md">Clear <i class="fa fa-times"></i> </button>
									</div>
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
                            <strong>Training Overview</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-3">
									<div style="background-color: #0000FF;border-radius: 5px;">
										<div class="panel-body" align="center">
											<div class="col-sm-4">
												<p></p>
												<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="currentColor" class="bi bi-qr-code" viewBox="0 0 16 16" style="color:white;">
													<path d="M2 2h2v2H2V2Z"/>
													<path d="M6 0v6H0V0h6ZM5 1H1v4h4V1ZM4 12H2v2h2v-2Z"/>
													<path d="M6 10v6H0v-6h6Zm-5 1v4h4v-4H1Zm11-9h2v2h-2V2Z"/>
													<path d="M10 0v6h6V0h-6Zm5 1v4h-4V1h4ZM8 1V0h1v2H8v2H7V1h1Zm0 5V4h1v2H8ZM6 8V7h1V6h1v2h1V7h5v1h-4v1H7V8H6Zm0 0v1H2V8H1v1H0V7h3v1h3Zm10 1h-1V7h1v2Zm-1 0h-1v2h2v-1h-1V9Zm-4 0h2v1h-1v1h-1V9Zm2 3v-1h-1v1h-1v1H9v1h3v-2h1Zm0 0h3v1h-2v1h-1v-2Zm-4-1v1h1v-2H7v1h2Z"/>
													<path d="M7 12h1v3h4v1H7v-4Zm9 2v2h-3v-1h2v-1h1Z"/>
												</svg>
											</div>
											<div class="col-sm-8" align="left">
												<p style="color:white;margin-top:7px;" align="right">Total Trainings</p>
												<h3 id="totaltraining" style="color:white;" align="right"></h3>
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div style="background-color: #FF0000;border-radius: 5px;">
										<div class="panel-body" align="center">
											<div class="col-sm-4">
												<p></p>
												<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16" style="color:white;">
													<path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
												</svg>
											</div>
											<div class="col-sm-8" align="left">
												<p style="color:white;margin-top:7px;" align="right">Total Participants</p>
												<h3 id="totaluser" style="color:white;" align="right"></h3>
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div style="background-color: #008000;border-radius: 5px;">
										<div class="panel-body" align="center">
											<div class="col-sm-4">
												<p></p>
												<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="currentColor" class="bi bi-calendar2-week" viewBox="0 0 16 16" style="color:white;">
													<path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z"/>
													<path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4zM11 7.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
												</svg>
											</div>
											<div class="col-sm-8" align="left">
												<p style="color:white;margin-top:7px;" align="right">Total Days</p>
												<h3 id="totalday" style="color:white;" align="right"></h3>
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div style="background-color: #E0115F;border-radius: 5px;">
										<div class="panel-body" align="center">
											<div class="col-sm-4">
												<p></p>
												<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="currentColor" class="bi bi-clock" viewBox="0 0 16 16" style="color:white;">
													<path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
													<path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
												</svg>
											</div>
											<div class="col-sm-8" align="left">
												<p style="color:white;margin-top:7px;" align="right">Total Hours</p>
												<h3 id="totalhour" style="color:white;" align="right"></h3>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row" style="display:flex;flex-wrap:wrap;">
                <div class="col-md-6">
					<div class="panel panel-default" style="height:100%;">
                        <div class="panel-heading">
                            <strong>Public/Inhouse vs OJT Overview (Training Hours)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<canvas id="publicojtChart" style="width:100%;max-width:700px"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="panel panel-default" style="height:100%;">
                        <div class="panel-heading">
                            <strong>Top 5 Inhouse Trainer</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<table id="trainerdata" class="table table-bordered table-striped" style="width:100%;">
										<thead>
											<tr>
												<th>No.</th>
												<th>Staff No.</th>
												<th>Name</th>
												<th>Total Hours</th>
											</tr>
										</thead>
									</table>
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
                            <strong>All Department List (<span id="modeLabelTop10">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<canvas id="top10Chart" style="width:100%;"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
                <div class="col-md-4">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Business Development Division (<span id="modeLabelBusiness">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<canvas id="businessChart" style="width:100%;max-width:700px"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>DHMSB/Subang Division (<span id="modeLabelDhmsb">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<canvas id="dhmsbChart" style="width:100%;max-width:700px"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Quality Management Division (<span id="modeLabelQuality">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<canvas id="qualityChart" style="width:100%;max-width:700px"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
                <div class="col-md-4">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Finance Division (<span id="modeLabelFinance">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<canvas id="financeChart" style="width:100%;max-width:700px"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Human Capital (<span id="modeLabelHuman">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<div class="col-md-12">
									<canvas id="humanChart" style="width:100%;max-width:700px"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>R&D And Engineering Division (<span id="modeLabelRnd">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<canvas id="rndChart" style="width:100%;max-width:700px"></canvas>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Operation Division (<span id="modeLabelOperation">Total Man Hour</span>)</strong>
                        </div>
                        <div class="panel-body" align="center">
							<div class="row">
								<canvas id="operationChart" style="width:200%;max-width:1500px"></canvas>
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

		$('#startdate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

        $('#enddate').datepicker({
            format: "yyyy-mm-dd",
            autoclose: true
        });

		var clerkid = <?php echo $_SESSION['id']?>;

		var date = new Date(), y = date.getFullYear();
		var fd = y + "-01-01";
		var ld = y + "-12-31";

		$('#datarecord').fadeIn().html('<label>Data summary : '+fd+' - '+ld+'</label');

		getOverview(clerkid,fd,ld);

		function getOverview(clerkid,startdate,enddate){
			$.ajax({
				url:"fetch_dash.php",
				type:"POST",
				data:{action:'fetch_overview',clerkid:clerkid,startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
					$('#totaltraining').text(data[0].totaltraining);
					$('#totaluser').text(data[0].totaluser);
					$('#totalday').text(data[0].totalday);
					$('#totalhour').text(data[0].totalhour);
				}
			})
		}

		var publicojtPieChart;

		var canvasPublicojt = document.getElementById("publicojtChart");

		function makechartpublicojt(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_publicojt',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxPublicojt = canvasPublicojt.getContext('2d');
                    var status = [];
					var totalstatus = [];
                    var colorstatus = [];

					for(var count = 0; count < data.length; count++) {
						status.push(data[count].status);
						totalstatus.push(data[count].totalstatus);
                        colorstatus.push(data[count].colorstatus);
					}

                    publicojtPieChart = new Chart(ctxPublicojt, {
                        type: 'pie',
                        data: {
                            labels: status,
                            datasets: [{
                                data: totalstatus,
                                backgroundColor: colorstatus
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: true
                            },
                            plugins: {
                                labels: {
                                    render: function (args) {
                                        return args.value + ' (' + args.percentage + '%)';
                                    },
                                    precision: 1,
                                    fontColor: '#fff',
                                }
                            }
                        }
                    });
				}
			})
		}

		makechartpublicojt(fd,ld);

		function destroyChartInstance(chartInstance) {
            if (chartInstance && typeof chartInstance.destroy === 'function') {
                chartInstance.destroy();
            }
        }

        function destroyTrainerDataTable() {
            if ($.fn.DataTable.isDataTable('#trainerdata')) {
                $('#trainerdata').DataTable().destroy();
            }
        }

		function destroyChartpublicojt() {
            destroyChartInstance(publicojtPieChart);
        }

		function fetch_data(action,startdate,enddate){
            var userdataTable = $('#trainerdata').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "responsive": true,
                "pageLength": 10,
                "info": true,
                "ajax":{
                    url:"fetch_dash.php",
                    type:"POST",
                    dataSrc: '',
                    data : {action:action,startdate:startdate,enddate:enddate},
                },
                "columns": [
                    {
                        "data": "id",
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    {
                        "data": "staffno"
                    },
                    {
                        "data": "staffname"
                    },
                    {
                        "data": "trainertotalhour"
                    }
                ],
                "columnDefs": [
                    { className: 'text-center', targets: [0,1,3] }
                ]
            });
        }

        fetch_data('load_top5',fd,ld);

		var canvasTop10 = document.getElementById("top10Chart");
		var top10Chart;

		function maketop10chart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_top10',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxTop10 = canvasTop10.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    top10Chart = new Chart(ctxTop10, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyChartTop10() {
            destroyChartInstance(top10Chart);
        }

		maketop10chart(fd,ld);

		var canvasBusiness = document.getElementById("businessChart");
		var businessChart;

		function makebusinesschart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_business',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxBusiness = canvasBusiness.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    businessChart = new Chart(ctxBusiness, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyChartbusiness() {
            destroyChartInstance(businessChart);
        }

		makebusinesschart(fd,ld);

		var canvasDhmsb = document.getElementById("dhmsbChart");
		var dhmsbChart;

		function makedhmsbchart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_dhmsb',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxDhmsb = canvasDhmsb.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    dhmsbChart = new Chart(ctxDhmsb, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyChartdhmsb() {
            destroyChartInstance(dhmsbChart);
        }

		makedhmsbchart(fd,ld);

		var canvasFinance = document.getElementById("financeChart");
		var financeChart;

		function makefinancechart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_finance',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxFinance = canvasFinance.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    financeChart = new Chart(ctxFinance, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyChartfinance() {
            destroyChartInstance(financeChart);
        }

		makefinancechart(fd,ld);

		var canvasHuman = document.getElementById("humanChart");
		var humanChart;

		function makehumanchart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_human',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxHuman = canvasHuman.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    humanChart = new Chart(ctxHuman, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyCharthuman() {
            destroyChartInstance(humanChart);
        }

		makehumanchart(fd,ld);

		var canvasOperation = document.getElementById("operationChart");
		var operationChart;

		function makeoperationchart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_operation',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxOperation = canvasOperation.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    operationChart = new Chart(ctxOperation, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyChartoperation() {
            destroyChartInstance(operationChart);
        }

		makeoperationchart(fd,ld);

		var canvasQuality = document.getElementById("qualityChart");
		var qualityChart;

		function makequalitychart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_quality',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxQuality = canvasQuality.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    qualityChart = new Chart(ctxQuality, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyChartquality() {
            destroyChartInstance(qualityChart);
        }

		makequalitychart(fd,ld);

		var canvasRnd = document.getElementById("rndChart");
		var rndChart;

		function makerndchart(startdate,enddate) {
			$.ajax({
				url:"fetch_dash.php",
				method:"POST",
				data:{action:'fetch_rnd',startdate:startdate,enddate:enddate},
				dataType:"JSON",
				success:function(data)
				{
                    var ctxRnd = canvasRnd.getContext('2d');
                    var mode = $('#mode').val();
                    var category = [];
					var values = [];
					var colors = [];

					for(var count = 0; count < data.length; count++) {
						category.push(data[count].category);
						values.push(mode === 'totalhour' ? data[count].totalsend1 : data[count].totalsend);
						colors.push(mode === 'totalhour' ? data[count].colorplant1 : data[count].colorplant);
					}

                    rndChart = new Chart(ctxRnd, {
                        type: 'bar',
                        data: {
                            labels: category,
                            datasets: [
                                {
                                    label: mode === 'totalhour' ? "Avg Hours" : "Total Hours",
                                    backgroundColor: colors,
                                    data: values
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: false
                            },
                            scales: {
                                yAxes:[{
									min:0,
									ticks:{
										min:0,
										stepSize: 10
									}
								}]
                            },
                            plugins: {
                                labels: {
                                    render: 'value',
                                    fontColor: '#000',
                                }
                            },
							layout: {
								padding: {
									top: 20
								}
							}
                        }
                    });
				}
			})
		}

		function destroyChartrnd() {
            destroyChartInstance(rndChart);
        }

		makerndchart(fd,ld);

		function updateModeLabels(mode) {
            var label = mode === 'totalhour' ? 'Average Total Hour' : 'Total Man Hour';
            $('#modeLabelTop10, #modeLabelBusiness, #modeLabelDhmsb, #modeLabelQuality, #modeLabelFinance, #modeLabelHuman, #modeLabelOperation, #modeLabelRnd').text(label);
        }

		$('#mode').change(function(){
            var startdate = $('#startdate').val() || fd;
            var enddate = $('#enddate').val() || ld;
            var mode = $('#mode').val();

            updateModeLabels(mode);

            destroyChartTop10();
			maketop10chart(startdate,enddate);
			destroyChartbusiness();
			makebusinesschart(startdate,enddate);
			destroyChartdhmsb();
			makedhmsbchart(startdate,enddate);
			destroyChartfinance();
			makefinancechart(startdate,enddate);
			destroyCharthuman();
			makehumanchart(startdate,enddate);
			destroyChartoperation();
			makeoperationchart(startdate,enddate);
			destroyChartquality();
			makequalitychart(startdate,enddate);
			destroyChartrnd();
			makerndchart(startdate,enddate);
        });

		$('#filter_date').click(function(){
            var startdate = $('#startdate').val();
            var enddate = $('#enddate').val();

			$('#datarecord').fadeIn().html('<label>Data summary : '+startdate+' - '+enddate+'</label');
            getOverview(clerkid,startdate,enddate);
			destroyChartpublicojt();
			makechartpublicojt(startdate,enddate);
			destroyTrainerDataTable();
			fetch_data('load_top5',startdate,enddate);
			destroyChartTop10();
			maketop10chart(startdate,enddate);
			destroyChartbusiness();
			makebusinesschart(startdate,enddate);
			destroyChartdhmsb();
			makedhmsbchart(startdate,enddate);
			destroyChartfinance();
			makefinancechart(startdate,enddate);
			destroyCharthuman();
			makehumanchart(startdate,enddate);
			destroyChartoperation();
			makeoperationchart(startdate,enddate);
			destroyChartquality();
			makequalitychart(startdate,enddate);
			destroyChartrnd();
			makerndchart(startdate,enddate);
        });

        $('#clear_filter').click(function(){
            $('#mode').val('manhour');
            updateModeLabels('manhour');
			$('#datarecord').fadeIn().html('<label>Data summary : '+fd+' - '+ld+'</label');
            getOverview(clerkid,fd,ld);
			destroyChartpublicojt();
			makechartpublicojt(fd,ld);
			destroyTrainerDataTable();
			fetch_data('load_top5',fd,ld);
			destroyChartTop10();
			maketop10chart(fd,ld);
			destroyChartbusiness();
			makebusinesschart(fd,ld);
			destroyChartdhmsb();
			makedhmsbchart(fd,ld);
			destroyChartfinance();
			makefinancechart(fd,ld);
			destroyCharthuman();
			makehumanchart(fd,ld);
			destroyChartoperation();
			makeoperationchart(fd,ld);
			destroyChartquality();
			makequalitychart(fd,ld);
			destroyChartrnd();
			makerndchart(fd,ld);
            $('#startdate').val('');
            $('#enddate').val('');
        });
    </script>
</html>
<?php
    }else{
         header("Location: ../login.php");
         exit();
    }
?>
