<?php
session_start();

if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'ADMIN')) {

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
        <link rel="stylesheet" type="text/css"
            href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css" />
        <script type="text/javascript"
            src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js">
            </script>
    </head>

    <style>
        .panel-dashboard {
            display: flex;
            align-items: center;
            column-gap: 20px;
        }
        .panel-dashboard-2{
            display: flex;
            align-items: center;
            column-gap: 20px;
        }

        .tna-submission, .tna-summary {
            width: 100%;
            /* overflow-x: hidden; */
        }

        .tna {
            width: 100%;
        }

        .panel-body2 {
            height: 600px;
            /* Adjust the height as needed to match other table heights */
            overflow-y: auto;
            overflow-x: hidden
            /* Add vertical scrolling if content exceeds height */
        }

        /* TNA Submission / TNA Status tables: grow with their rows instead
           of scrolling inside a fixed 600px box. */
        .panel-body.panel-body2 {
            height: auto;
            min-height: 600px;
            overflow: visible;
        }

        /* Keep side-by-side panels the same height when one grows. */
        .panel-dashboard {
            align-items: stretch;
        }

        .panel-dashboard > .row > .col-md-12,
        .panel-dashboard > .row > .col-md-12 > .panel {
            height: calc(100% - 20px);
        }

        .panel-dashboard > .row > .col-md-12 {
            height: 100%;
        }

        /* TNA dashboard */
        .dash-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .dash-head h4 {
            margin: 0;
            font-weight: bold;
        }

        .dash-head small {
            color: #52514e;
        }

        .kpi-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 15px;
        }

        .kpi {
            flex: 1 1 160px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px 15px;
        }

        .kpi-label {
            color: #52514e;
            font-size: 13px;
        }

        .kpi-label i {
            margin-right: 4px;
        }

        .kpi-value {
            font-size: 30px;
            font-weight: bold;
            color: #0b0b0b;
            line-height: 1.2;
        }

        .kpi-sub {
            color: #52514e;
            font-size: 12px;
        }

        .kpi-good i { color: #0ca30c; }
        .kpi-warning i { color: #c98500; }
        .kpi-none i { color: #8a8880; }

        .status-bar {
            display: flex;
            height: 14px;
            border-radius: 4px;
            overflow: hidden;
            background: #ecebe7;
            gap: 2px;
        }

        .status-bar div {
            height: 100%;
        }

        .status-legend {
            margin-top: 8px;
            font-size: 13px;
            color: #52514e;
        }

        .status-legend span {
            margin-right: 18px;
            white-space: nowrap;
        }

        .swatch {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 5px;
        }

        .chart-box {
            position: relative;
            height: 320px;
        }

        .chart-note {
            color: #52514e;
            font-size: 12px;
            margin: 0 0 8px;
        }

    </style>

    <body onload="startTime()" style="background-image:url('../../asset/image/bg-try.png');zoom: 75%;">
        <br>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10">
                    <img src="../../asset/image/lndlogo.gif" height="50" width="290">
                </div>
                <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;">

                </div>
            </div>
            <nav class="navbar navbar-inverse">
                <div class="container-fluid ">
                    <ul class="nav navbar-nav">
                        <li><a href="../dashboard.php">HOME</a></li>
                        <li><a href="../staff/staff.php">STAFF LIST</a></li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span
                                    class="label label-pill label-danger count"></span> ALL TRAINING </a>
                            <ul class="dropdown-menu">
                                <li><a href="../training/public/training.php">PUBLIC/INHOUSE</a></li>
                                <li><a href="../training/ojt/training_ojt.php">OJT</a></li>
                                <li><a href="../training/departmental/training_dept.php">DEPARTMENTAL</a></li>
                            </ul>
                        </li>
                        <li><a href="../attendance/training.php">MY TRAINING</a></li>
                        <li><a href="tna_list.php">TNA</a></li>
                        <li><a href="../tni/tni_list.php">TNI</a></li>
						<li><a href="tna_summary.php">TNA SUMMARY</a></li>
						<li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li>
                        <li><a href="../organization/org.php">ORGANIZATION</a></li>
                        <li><a href="../archive/export.php">EXPORT</a></li>
                        <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right">
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span
                                    class="label label-pill label-danger count"></span> <?php echo $_SESSION['fullname'] ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="../../logout.php">LOGOUT</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
            <br>

            <!-- TNA DASHBOARD -->
            <div class="panel panel-default">
                <div class="panel-heading dash-head">
                    <div>
                        <h4>TNA Dashboard &ndash; FY <span id="dashyear"></span></h4>
                        <small>Training Needs Analysis progress for the planning year</small>
                    </div>
                    <a href="tna_summary_export.php" class="btn btn-success"><i class="fas fa-file-excel"></i> Export Excel</a>
                </div>
                <div class="panel-body">
                    <div class="kpi-row">
                        <div class="kpi">
                            <div class="kpi-label"><i class="fas fa-users"></i> Staff</div>
                            <div class="kpi-value" id="kpi-eligible">&ndash;</div>
                            <div class="kpi-sub">expected to fill TNA</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label"><i class="fas fa-file-signature"></i> TNA Submitted</div>
                            <div class="kpi-value" id="kpi-submitted">&ndash;</div>
                            <div class="kpi-sub" id="kpi-completion">&nbsp;</div>
                        </div>
                        <div class="kpi kpi-good">
                            <div class="kpi-label"><i class="fas fa-check-circle"></i> Approved</div>
                            <div class="kpi-value" id="kpi-approved">&ndash;</div>
                            <div class="kpi-sub" id="kpi-approval">&nbsp;</div>
                        </div>
                        <div class="kpi kpi-warning">
                            <div class="kpi-label"><i class="fas fa-hourglass-half"></i> Pending Approval</div>
                            <div class="kpi-value" id="kpi-pending">&ndash;</div>
                            <div class="kpi-sub">waiting for HOD</div>
                        </div>
                        <div class="kpi kpi-none">
                            <div class="kpi-label"><i class="fas fa-minus-circle"></i> Not Submitted</div>
                            <div class="kpi-value" id="kpi-notsubmitted">&ndash;</div>
                            <div class="kpi-sub">no TNA yet</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label"><i class="fas fa-list-ul"></i> Training Needs</div>
                            <div class="kpi-value" id="kpi-items">&ndash;</div>
                            <div class="kpi-sub" id="kpi-gap">&nbsp;</div>
                        </div>
                    </div>
                    <div class="status-bar" id="statusbar"></div>
                    <div class="status-legend">
                        <span><i class="swatch" style="background:#0ca30c"></i>Approved <b id="leg-approved"></b></span>
                        <span><i class="swatch" style="background:#fab219"></i>Pending approval <b id="leg-pending"></b></span>
                        <span><i class="swatch" style="background:#c3c2b7"></i>Not submitted <b id="leg-notsubmitted"></b></span>
                        <span id="leg-grade"></span>
                    </div>
                </div>
            </div>

            <div class="panel-dashboard">
                <div class="row tna-submission">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-md-11" style="margin-top: 10px;">
                                        <strong>TNA Submission (by Department)</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-body panel-body2" align="center">
                                <div class="row">
                                    <div class="col-sm-12 table-responsive">
                                        <table id="submissionlist" class="table table-bordered table-striped" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>No.</th>
                                                    <th>Department</th>
                                                    <th>Quantity</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row tna">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-md-11" style="margin-top: 10px;">
                                        <strong>TNA Status (by Department)</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-body panel-body2" align="center">
                                <div class="row">
                                    <div class="col-sm-12 table-responsive">
                                        <table id="tnastatuslist" class="table table-bordered table-striped" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>No.</th>
                                                    <th>Department</th>
                                                    <th>Head of Department</th>
                                                    <th>Filled / Staff</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                            </tbody>
                                        </table>
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
                        <div class="panel-heading"><strong>TNA Completion by Department</strong></div>
                        <div class="panel-body">
                            <p class="chart-note">Share of each department's staff, sorted by completion. Hover a bar for staff counts.</p>
                            <div class="panel-body2" style="height:560px;">
                                <div class="chart-box" id="deptchartbox">
                                    <canvas id="deptcompletion"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- TNA DASHBOARD -->

<div class="panel-dashboard">
                <div class="row tna-submission">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-md-11" style="margin-top: 10px;">
                                        <strong>Summary of Section</strong>
                                    </div>
                                </div>
                            </div>
                         
				
				 <div class="panel-body3 tna-summary" align="center">
                    <div class="row">
                        <div class="col-md-12">
                            <canvas id="summarytna" style="width:100%;max-width:700px"></canvas>
                        </div>
                    </div>
                </div>
                        </div>
                    </div>
                </div>

                <div class="row tna">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-md-11" style="margin-top: 10px;">
                                        <strong>Summary of Training Method</strong>
                                    </div>
                                </div>
                            </div>
                           <div class="panel-body3 tna-summary" align="center">
								<div class="row">
									<div class="col-md-12">
										<canvas id="methodtna" style="width:100%;max-width:700px"></canvas>
									</div>
								</div>
							</div>
                        </div>
                    </div>
                </div>
            </div>
			


            <!-- NEW ADDED DASHBOARD TABLE  -->

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Planned Training by Month</strong></div>
                        <div class="panel-body">
                            <p class="chart-note">Number of training needs by the month staff plan to attend.</p>
                            <div class="chart-box"><canvas id="monthtna"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Skill Gap by Section</strong></div>
                        <div class="panel-body">
                            <p class="chart-note">Average current vs target skill level (1&ndash;5) across all training needs.</p>
                            <div class="chart-box"><canvas id="skilltna"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Top 10 Requested Trainings</strong></div>
                        <div class="panel-body">
                            <div class="chart-box" style="height:380px;"><canvas id="toptraining"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Training Needs by Department</strong></div>
                        <div class="panel-body">
                            <div class="chart-box" style="height:380px;"><canvas id="deptneeds"></canvas></div>
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
                        </br>All Rights Reserved. | Web design by PHN IT Department
                    </h6>
                </div>
                <div class="col-md-2"></div>
            </div>
        </div>
    </footer>
    <script>
        function startTime() {
            var today = new Date();
            var h = today.getHours();
            var m = today.getMinutes();
            var s = today.getSeconds();
            // add a zero in front of numbers<10
            h = checkTime(h);
            m = checkTime(m);
            s = checkTime(s);
            document.getElementById('txt').innerHTML = h + ":" + m + ":" + s;
            t = setTimeout(function () {
                startTime()
            }, 500);
        }

        function checkTime(i) {
            if (i < 10) {
                i = "0" + i;
            }
            return i;
        }

        var userid = <?php echo $_SESSION['id'] ?>;
        var newdept = '';

        $(function () {
            $.post("fetch_tna.php", {
                action: "load_department"
            }, function (data) {
                // console.log("department load: ", data)
                $('#department').html(data);
                $('#department').val(dept);
            });
        });

        $(function () {
            $.post("fetch_tna.php", {
                action: "all_department",
            }, function (data) {
                // Parse the returned JSON data
                var parsedData = JSON.parse(data);

                var gradedataTable = $('#submissionlist').DataTable({
                    "paging": true,
                    "lengthChange": true,
                    "searching": true,
                    "ordering": true,
                    "responsive": true,
                    "pageLength": 10,
                    "info": true,
                    "data": parsedData, // Use parsed data
                    "columns": [
                        {
                            "data": "id",
                            render: function (data, type, row, meta) {
                                return meta.row + meta.settings._iDisplayStart + 1;
                            }
                        },
                        {
                            "data": "department"
                        },
                        {
                            "data": "quantity"
                        }
                    ],
                    "columnDefs": [
                        {
                            className: 'text-left',
                            targets: [0, 1]
                        }
                    ]
                });
            });
        });

        $(function () {
            $.post("fetch_tna.php", {
                action: "tna_status",
            }, function (data) {
                var parsedData = JSON.parse(data);

                var gradedataTable = $('#tnastatuslist').DataTable({
                    "paging": true,
                    "lengthChange": true,
                    "searching": true,
                    "ordering": true,
                    "responsive": true,
                    "pageLength": 10,
                    "info": true,
                    "data": parsedData, // Use parsed data
                    "columns": [
                        {
                            "data": "id",
                            render: function (data, type, row, meta) {
                                return meta.row + meta.settings._iDisplayStart + 1;
                            }
                        },
                        {
                            "data": "department"
                        },
                        {
                            "data": "hod"
                        },
                        {
                            "data": "filled",
                            // Sort by share filled, not by the "x / y" text.
                            render: function (data, type, row) {
                                if (type === 'sort') {
                                    return row.staff > 0 ? row.filled / row.staff : -1;
                                }
                                return row.filled + ' / ' + row.staff;
                            }
                        },
                        {
                            "data": "status"
                        }
                    ],
                    "columnDefs": [
                        {
                            className: 'text-left',
                            targets: [0, 1, 2, 3, 4]
                        }
                    ]
                });
            });
        });

        var tnaSummaryPieChart;
        var canvasTnasummary = document.getElementById("summarytna");

        function makepiecharttna() {
            $.ajax({
                url: "fetch_tna.php",
                method: "POST",
                data: {
                    action: 'fetch_summary_tna',
                },
                dataType: "JSON",
                success: function (data) {
                    console.log("data pie chart: ", data);
                    var ctxTnasummary = canvasTnasummary.getContext('2d');
                    var section = [];
                    var no = [];
                    var percentage = [];
                    var colorstatus = [];
                    var label = [];

                    for (var count = 0; count < data.length; count++) {
                        section.push(data[count].section);
                        no.push(data[count].no);
                        percentage.push(data[count].percentage);
                        colorstatus.push(data[count].colorstatus);
                        label.push(data[count].label);
                    }

                    tnaSummaryPieChart = new Chart(ctxTnasummary, {
                        type: 'pie',
                        data: {
                            labels: label,
                            datasets: [{
                                data: no,
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
                                tooltip: {
                                    callbacks: {
                                        label: function (tooltipItem) {
                                            let dataset = tooltipItem.dataset;
                                            let index = tooltipItem.dataIndex;

                                            let label = section[index]; // Section name
                                            let count = dataset.data[index]; // No (count)
                                            let percent = percentage[index]; // Percentage

                                            return `${label}: ${count} (${percent}%)`;
                                        }
                                    }
                                },
                                labels: {
                                    render: function (args) {
                                        return `${percentage[args.index]}%`; // Value + Percentage inside the pie
                                    },
                                    fontColor: '#fff',
                                }
                            },
                            legend: {
                                display: true
                            }
                        }
                    });
                }
            })
        }
        makepiecharttna();

        var methodPieChart;
        var canvasMethod =document.getElementById("methodtna");

        function makeMethodPiechart() {
            $.ajax({
                url: "fetch_tna.php",
                method: "POST",
                data: {
                    action: 'fetch_method_tna',
                },
                dataType: "JSON",
                success: function (data) {
                    console.log("data pie  method: ", data);
                    var ctxMethod = canvasMethod.getContext('2d');
                    var section = [];
                    var no = [];
                    var percentage = [];
                    var color = [];
                    var label = [];

                    for (var count = 0; count < data.length; count++) {
                        section.push(data[count].section);
                        no.push(data[count].no);
                        percentage.push(data[count].percentage);
                        color.push(data[count].color);
                        label.push(data[count].label);
                    }

                    methodPieChart = new Chart(ctxMethod, {
                        type: 'pie',
                        data: {
                            labels: label,
                            datasets: [{
                                data: no,
                                backgroundColor: color
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                display: true
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function (tooltipItem) {
                                            let dataset = tooltipItem.dataset;
                                            let index = tooltipItem.dataIndex;

                                            let label = section[index]; // Section name
                                            let count = dataset.data[index]; // No (count)
                                            let percent = percentage[index]; // Percentage

                                            return `${label}: ${count} (${percent}%)`;
                                        }
                                    }
                                },
                                labels: {
                                    render: function (args) {
                                        return `${percentage[args.index]}%`; // Value + Percentage inside the pie
                                    },
                                    fontColor: '#fff',
                                }
                            },
                            legend: {
                                display: true
                            }
                        }
                    });
                }
            })
        }
        makeMethodPiechart();

        // ---- TNA dashboard ----
        var DASH = {
            approved: '#0ca30c',
            pending: '#fab219',
            none: '#c3c2b7',
            blue: '#2a78d6',
            orange: '#eb6834',
            ink: '#52514e',
            grid: 'rgba(0, 0, 0, 0.06)'
        };

        function fmt(n) {
            return Number(n).toLocaleString();
        }

        function shorten(text, max) {
            return text.length > max ? text.substr(0, max - 1) + '…' : text;
        }

        // Shared look for the bar charts: recessive grid, no value labels from
        // chartjs-plugin-labels (that plugin is only meant for the pies).
        function barOptions(horizontal, extra) {
            var valueAxis = {
                gridLines: { color: DASH.grid, zeroLineColor: DASH.grid },
                ticks: { beginAtZero: true, precision: 0, fontColor: DASH.ink }
            };
            var catAxis = {
                gridLines: { display: false },
                ticks: { fontColor: DASH.ink }
            };
            return $.extend(true, {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                plugins: { labels: false },
                scales: {
                    xAxes: [horizontal ? valueAxis : catAxis],
                    yAxes: [horizontal ? catAxis : valueAxis]
                }
            }, extra || {});
        }

        function renderKpis(data) {
            var k = data.kpi;
            $('#dashyear').text(data.year);
            $('#kpi-eligible').text(fmt(k.eligible));
            $('#kpi-submitted').text(fmt(k.submitted));
            $('#kpi-completion').text(k.completion + '% completion');
            $('#kpi-approved').text(fmt(k.approved));
            $('#kpi-approval').text(k.approval + '% of submitted');
            $('#kpi-pending').text(fmt(k.pending));
            $('#kpi-notsubmitted').text(fmt(k.not_submitted));
            $('#kpi-items').text(fmt(k.items));
            $('#kpi-gap').text('avg skill gap ' + k.avg_gap);

            var parts = [
                ['approved', k.approved, DASH.approved, 'Approved'],
                ['pending', k.pending, DASH.pending, 'Pending approval'],
                ['notsubmitted', k.not_submitted, DASH.none, 'Not submitted']
            ];
            var bar = $('#statusbar').empty();
            $.each(parts, function (i, p) {
                var pct = k.eligible > 0 ? p[1] * 100 / k.eligible : 0;
                $('#leg-' + p[0]).text(fmt(p[1]) + ' (' + pct.toFixed(1) + '%)');
                if (p[1] > 0) {
                    $('<div>').css({ width: pct + '%', background: p[2], minWidth: '3px' })
                        .attr('title', p[3] + ': ' + fmt(p[1]) + ' staff (' + pct.toFixed(1) + '%)')
                        .appendTo(bar);
                }
            });
            $('#leg-grade').text(k.grade_groups > 0 ? '+ ' + fmt(k.grade_groups) + ' grade TNA (non-executive groups)' : '');
        }

        function renderDeptCompletion(departments) {
            var rows = departments.filter(function (d) { return d.eligible > 0; });
            rows.sort(function (a, b) {
                return (b.completion - a.completion) || (b.eligible - a.eligible);
            });
            $('#deptchartbox').css('height', Math.max(320, rows.length * 24 + 60) + 'px');

            function share(d, n) {
                return d.eligible > 0 ? Math.round(n * 1000 / d.eligible) / 10 : 0;
            }
            var series = [
                ['Approved', 'approved', DASH.approved],
                ['Pending approval', 'pending', DASH.pending],
                ['Not submitted', 'not_submitted', DASH.none]
            ];
            new Chart(document.getElementById('deptcompletion'), {
                type: 'horizontalBar',
                data: {
                    labels: rows.map(function (d) { return shorten(d.department, 30); }),
                    datasets: series.map(function (s) {
                        return {
                            label: s[0],
                            data: rows.map(function (d) { return share(d, d[s[1]]); }),
                            backgroundColor: s[2],
                            borderColor: '#fff',
                            borderWidth: { right: 2 },
                            barPercentage: 0.75
                        };
                    })
                },
                options: barOptions(true, {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, fontColor: DASH.ink } },
                    scales: {
                        xAxes: [{ stacked: true, ticks: { max: 100, callback: function (v) { return v + '%'; } } }],
                        yAxes: [{ stacked: true }]
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function (items) {
                                var d = rows[items[0].index];
                                return d.department + ' (' + fmt(d.eligible) + ' staff)';
                            },
                            label: function (item, chart) {
                                var s = series[item.datasetIndex];
                                var d = rows[item.index];
                                return s[0] + ': ' + fmt(d[s[1]]) + ' staff (' + item.xLabel + '%)';
                            }
                        }
                    }
                })
            });
        }

        function renderMonths(months) {
            new Chart(document.getElementById('monthtna'), {
                type: 'bar',
                data: {
                    labels: months.map(function (m) { return m.month; }),
                    datasets: [{
                        label: 'Training needs',
                        data: months.map(function (m) { return m.no; }),
                        backgroundColor: DASH.blue,
                        barPercentage: 0.6
                    }]
                },
                options: barOptions(false)
            });
        }

        function renderSkills(skills) {
            new Chart(document.getElementById('skilltna'), {
                type: 'bar',
                data: {
                    // One word per line so long section names aren't cut off.
                    labels: skills.map(function (s) { return s.section.split(' '); }),
                    datasets: [{
                        label: 'Avg current skill',
                        data: skills.map(function (s) { return s.current; }),
                        backgroundColor: DASH.blue,
                        borderColor: '#fff',
                        borderWidth: { left: 1, right: 1 }
                    }, {
                        label: 'Avg target skill',
                        data: skills.map(function (s) { return s.target; }),
                        backgroundColor: DASH.orange,
                        borderColor: '#fff',
                        borderWidth: { left: 1, right: 1 }
                    }]
                },
                options: barOptions(false, {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, fontColor: DASH.ink } },
                    scales: {
                        yAxes: [{ ticks: { max: 5, stepSize: 1 } }]
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function (items) { return skills[items[0].index].section; },
                            afterBody: function (items) {
                                var s = skills[items[0].index];
                                return ['Avg gap: ' + s.gap, 'Training needs: ' + fmt(s.no)];
                            }
                        }
                    }
                })
            });
        }

        function renderHorizontalTop(canvasId, rows, label, valueKey, maxLen) {
            new Chart(document.getElementById(canvasId), {
                type: 'horizontalBar',
                data: {
                    labels: rows.map(function (r) { return shorten(r[label], maxLen); }),
                    datasets: [{
                        label: 'Training needs',
                        data: rows.map(function (r) { return r[valueKey]; }),
                        backgroundColor: DASH.blue,
                        barPercentage: 0.7
                    }]
                },
                options: barOptions(true, {
                    tooltips: {
                        callbacks: {
                            title: function (items) { return rows[items[0].index][label]; }
                        }
                    }
                })
            });
        }

        $(function () {
            $.post("fetch_tna.php", { action: "dashboard_tna" }, function (data) {
                renderKpis(data);
                renderDeptCompletion(data.departments);
                renderMonths(data.months);
                renderSkills(data.skills);
                renderHorizontalTop('toptraining', data.top_trainings, 'training', 'no', 40);

                var byNeeds = data.departments.filter(function (d) { return d.items > 0; });
                byNeeds.sort(function (a, b) { return b.items - a.items; });
                renderHorizontalTop('deptneeds', byNeeds.slice(0, 10), 'department', 'items', 30);
            }, "json");
        });

        var dept = sessionStorage.getItem("setdeptnext");
        console.log("dept: ", dept);
        if (dept != null) {
            newdept = dept;
            fetch_data('load_staff', userid, dept);
            fetch_data1('load_grade', userid, dept);
        }


  
    </script>

    </html>
    <?php
} else {
    header("Location: ../../login.php");
    exit();
}
?>
