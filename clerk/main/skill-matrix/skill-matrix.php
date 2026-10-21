<?php
session_start();
include "../../../skill_matrix_period.php";
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
list($previousYear, $previousQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);

$canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
    isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
    && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
    && (int) $_SESSION['hodid'] != 0
    && $_SESSION['role'] == 'CLERK'
    && $_SESSION['usertype'] == 'MAIN'
);
include_once __DIR__ . '/../../../skill_matrix_department.php';
$canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

if (isset($_SESSION['fullname']) && $canViewSkillMatrix) {

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
        <link rel="stylesheet" type="text/css"
            href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css" />
        <script type="text/javascript"
            src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js">
            </script>
    </head>

    <body onload="startTime()" style="background-image:url('../../../asset/image/bg-try.png');zoom: 75%;">
        <br>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10">
                    <img src="../../../asset/image/lndlogo.gif" height="50" width="290">
                </div>
                <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;">

                </div>
            </div>
            <nav class="navbar navbar-inverse">
                <div class="container-fluid ">
                    <ul class="nav navbar-nav">
                        <li><a href="../dashboard.php">HOME</a></li>
                        <li><a href="../staff/staff.php">CONTRACT STAFF LIST</a></li>
                        <li><a href="../training/training_ojt.php">ALL TRAINING</a></li>
                        <li><a href="../attendance/training.php">MY TRAINING</a></li>
                        <li><a href="../attendance/pme.php">PME</a></li>
                        <li><a href="../tna/tna_list.php">TNA</a></li>
                        <li class="active"><a href="skill-matrix.php">SKILL MATRIX</a></li>
                        <li><a href="../../../admin/archive/export.php">EXPORT</a></li>
                        <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right">
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span
                                    class="label label-pill label-danger count"></span> <?php echo $_SESSION['fullname'] ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="../../../logout.php">LOGOUT</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="alert alert-info">
                <strong>Evaluation Quarter: Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?></strong>
                (<?php echo skillMatrixQuarterMonths($currentQuarter); ?> <?php echo $currentYear; ?>)
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-8" style="margin-top: 10px;">
                                    <strong>Department</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <a href="matrix-chart.php" id="matrix_chart_btn" class="btn btn-info btn-md">
                                        <i class="fa fa-chart-bar"></i> Matrix Chart
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="text" id="department" class="form-control"
                                        value="<?php echo $_SESSION['department']; ?>" readonly>
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
                                <div class="col-md-8" style="margin-top: 10px;">
                                    <strong>Staff List</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <a href="export_skill_matrix_list.php" class="btn btn-success btn-md">
                                        <i class="fa fa-file-excel"></i> Export Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <ul class="nav nav-tabs" id="period_tabs" style="margin-bottom: 15px;">
                                <li class="active"><a href="#" data-period="current">Evaluation Quarter (Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?>)</a></li>
                                <li><a href="#" data-period="previous">Previous Quarter (Q<?php echo $previousQuarter; ?> <?php echo $previousYear; ?>)</a></li>
                            </ul>
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <table id="skillmatrixlist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Staff No.</th>
                                                <th>Employee Name</th>
                                                <th>Department</th>
                                                <th>Section</th>
                                                <th>Grade</th>
                                                <th>Status</th>
                                                <th>Approval Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>

    <script>
        function startTime() {
            var today = new Date();
            var h = today.getHours();
            var m = today.getMinutes();
            var s = today.getSeconds();
            m = checkTime(m);
            s = checkTime(s);
            document.getElementById('txt').innerHTML = h + ":" + m + ":" + s;
            setTimeout(startTime, 500);
        }

        function checkTime(i) {
            if (i < 10) {
                i = "0" + i;
            }
            return i;
        }

        // Which tab the staff table is showing: the evaluation quarter or the one before it.
        var skillMatrixPeriod = /[?&]period=previous(&|$)/.test(window.location.search) ? 'previous' : 'current';

        var skillMatrixTable = $('#skillmatrixlist').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "responsive": true,
            "pageLength": 10,
            "info": true,
            "ajax": {
                url: "fetch_skill_matrix.php",
                type: "POST",
                dataSrc: '',
                data: function (data) {
                    data.action = "load_non_executive_staff";
                    data.period = skillMatrixPeriod;
                }
            },
            "columns": [
                {
                    "data": null
                },
                {
                    "data": "staffno"
                },
                {
                    "data": "staffname"
                },
                {
                    "data": "department"
                },
                {
                    "data": "section"
                },
                {
                    "data": "grade"
                },
                {
                    "data": "status"
                },
                {
                    "data": "approval_status"
                },
                {
                    "data": "action"
                }
            ],
            "columnDefs": [{
                className: 'text-center',
                targets: [0, 1, 5, 6, 7, 8]
            },
            {
                orderable: false,
                searchable: false,
                targets: [0]
            }],
            "drawCallback": function () {
                var api = this.api();
                var pageInfo = api.page.info();

                api.column(0, {
                    page: 'current'
                }).nodes().each(function (cell, index) {
                    cell.innerHTML = pageInfo.start + index + 1;
                });
            },
            "language": {
                "emptyTable": "No non executive staff records found."
            }
        });

        $('#skillmatrixlist').on('error.dt', function () {
            swal("Unable to load staff list", "Please refresh the page or contact system administrator.", "error");
        });


        // The Matrix Chart button opens the chart on the same quarter tab.
        function syncMatrixChartLink() {
            $('#matrix_chart_btn').attr('href', 'matrix-chart.php' + (skillMatrixPeriod == 'previous' ? '?period=previous' : ''));
        }

        $('#period_tabs li').removeClass('active');
        $('#period_tabs a[data-period="' + skillMatrixPeriod + '"]').parent().addClass('active');
        syncMatrixChartLink();

        $('#period_tabs a').click(function (e) {
            e.preventDefault();
            skillMatrixPeriod = $(this).data('period');
            $('#period_tabs li').removeClass('active');
            $(this).parent().addClass('active');
            syncMatrixChartLink();
            skillMatrixTable.ajax.reload();
        });

    </script>

    </html>
    <?php
} else {
    header("Location: ../../../login.php");
    exit();
}
?>
