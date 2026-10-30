<?php
session_start();
include "../../dbconn.php";
include "../../skill_matrix_period.php";
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
list($previousYear, $previousQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);

if (isset($_SESSION['id']) && (!isset($_SESSION['designation']) || !isset($_SESSION['hodid']))) {
    $sessionUserId = (int) $_SESSION['id'];
    $sessionUserQuery = mysqli_query($conn, "SELECT designation, hodid FROM user WHERE id = '$sessionUserId' LIMIT 1");
    if ($sessionUserQuery && $sessionUserRow = mysqli_fetch_assoc($sessionUserQuery)) {
        $_SESSION['designation'] = $sessionUserRow['designation'];
        $_SESSION['hodid'] = $sessionUserRow['hodid'];
    }
}

$canUseSkillMatrix = (
    !empty($_SESSION['is_sm_user']) && isset($_SESSION['fullname'])
) || (
    isset($_SESSION['fullname'], $_SESSION['role'], $_SESSION['designation'], $_SESSION['usertype'], $_SESSION['hodid'])
    && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
    && (int) $_SESSION['hodid'] != 0
    && (
        ($_SESSION['role'] == '' && $_SESSION['usertype'] == '') ||
        ($_SESSION['role'] == 'CLERK' && $_SESSION['usertype'] == 'MAIN')
    )
);
include_once __DIR__ . '/../../skill_matrix_department.php';
$canUseSkillMatrix = $canUseSkillMatrix && skillMatrixDepartmentEnabled();
$isClerkMatrixUser = $canUseSkillMatrix && $_SESSION['role'] == 'CLERK';

if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'ADMIN' || $canUseSkillMatrix)) {

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
        <style>
            #skillmatrixlist {
                width: 100% !important;
            }

            :root {
                --status-good: #0ca30c;
                --status-warning: #fab219;
                --status-serious: #ec835a;
                --status-critical: #d03b3b;
            }

            .dash-tile {
                background: #fcfcfb;
                border: 1px solid #e1e0d9;
                border-left: 4px solid #c3c2b7;
                border-radius: 4px;
                padding: 12px 10px;
                text-align: center;
                margin-bottom: 15px;
            }

            .dash-tile-value {
                font-size: 26px;
                font-weight: bold;
                color: #0b0b0b;
                line-height: 1.2;
            }

            .dash-tile-label {
                font-size: 11px;
                font-weight: bold;
                color: #52514e;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                margin-top: 4px;
            }

            .dash-chart-scroll {
                max-height: 320px;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .dash-chart-scroll-sm {
                max-height: 260px;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .dash-score-chart {
                position: relative;
                height: 360px;
            }

            /* Overview tiles share the row equally, however many there are. */
            .dash-tiles {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -7px;
            }

            .dash-tiles-item {
                flex: 1 1 0;
                min-width: 140px;
                padding: 0 7px;
            }

            .skill-score-level {
                display: block;
                font-size: 11px;
                color: #777;
            }

            .skill-score-low {
                color: #d03b3b;
                font-weight: bold;
            }

            .view-tabs {
                border-bottom: none;
            }

            .view-tabs > li > a {
                background: #f5f5f5;
                color: #333;
                font-weight: bold;
                margin-right: 4px;
            }

            .view-tabs > li.active > a,
            .view-tabs > li.active > a:hover,
            .view-tabs > li.active > a:focus {
                background: #fff;
            }

            #view_tabs + .tab-content > .tab-pane > .row:first-child .panel {
                border-top-left-radius: 0;
            }
        </style>
    </head>

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
                        <?php if ($canUseSkillMatrix) { ?>
                            <?php if ($isClerkMatrixUser) { ?>
                                <li><a href="../../clerk/main/dashboard.php">HOME</a></li>
                                <li><a href="../../clerk/main/staff/staff.php">CONTRACT STAFF LIST</a></li>
                                <li><a href="../../clerk/main/training/training_ojt.php">ALL TRAINING</a></li>
                                <li><a href="../../clerk/main/attendance/training.php">MY TRAINING</a></li>
                                <li><a href="../../clerk/main/tna/tna_list.php">TNA</a></li>
                            <?php } else { ?>
                                <li><a href="../../staff/office/dashboard.php">HOME</a></li>
                                <li><a href="../../staff/office/attendance/training.php">MY TRAINING</a></li>
                                <li><a href="../../staff/office/tna/tna.php">TNA</a></li>
                            <?php } ?>
                            <li class="active"><a href="skill-matrix.php">SKILL MATRIX</a></li>
                            <?php if ($isClerkMatrixUser) { ?>
                                <li><a href="../../clerk/main/password/password.php">CHANGE PASSWORD</a></li>
                            <?php } else { ?>
                                <li><a href="../../staff/office/password/password.php">CHANGE PASSWORD</a></li>
                            <?php } ?>
                        <?php } else { ?>
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
                            <li><a href="../tna/tna_list.php">TNA</a></li>
                            <li><a href="../tni/tni_list.php">TNI</a></li>
                            <li><a href="../tna/tna_summary.php">TNA SUMMARY</a></li>
                            <li class="active"><a href="skill-matrix.php">SKILL MATRIX</a></li>
                            <li><a href="../organization/org.php">ORGANIZATION</a></li>
                            <li><a href="../archive/export.php">EXPORT</a></li>
                        <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
                        <?php } ?>
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
                                    <strong>Department Filter</strong>
                                </div>
                                <?php if (!$canUseSkillMatrix) { ?>
                                    <div class="col-md-4" align="right">
                                        <a href="#" id="matrix_chart_btn" class="btn btn-info btn-md disabled" style="pointer-events: none; opacity: 0.65;">
                                            <i class="fa fa-chart-bar"></i> Matrix Chart
                                        </a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="department" id="department" class="form-control"></select>
                                </div>
                                <div class="col-md-3">
                                    <select name="section" id="section" class="form-control" disabled>
                                        <option value="ALL">-- Select Department First --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="plant" id="plant" class="form-control">
                                        <option value="ALL">All Plants</option>
                                        <option value="ALAM IMPIAN PLANT">ALAM IMPIAN PLANT</option>
                                        <option value="ALAM MEGAH PLANT">ALAM MEGAH PLANT</option>
                                        <option value="BUKIT BERUNTUNG PLANT">BUKIT BERUNTUNG PLANT</option>
                                        <option value="FIF TANJUNG MALIM">FIF TANJUNG MALIM</option>
                                        <option value="PEGOH PLANT">PEGOH PLANT</option>
                                        <option value="PEKAN PLANT">PEKAN PLANT</option>
                                        <option value="RASA PLANT">RASA PLANT</option>
                                        <option value="SHAH ALAM 1 PLANT">SHAH ALAM 1 PLANT</option>
                                        <option value="SHAH ALAM 2 PLANT">SHAH ALAM 2 PLANT</option>
                                        <option value="TANJUNG MALIM 2">TANJUNG MALIM 2</option>
                                        <option value="WAREHOUSE BB">WAREHOUSE BB</option>
                                    </select>
                                </div>
                                <div class="col-md-3" style="white-space:nowrap;">
                                    <button type="button" name="filter_dept" id="filter_dept"
                                        class="btn btn-info btn-md" style="margin-right:8px;">FILTER <i class="fa fa-search"></i></button>
                                    <button type="button" name="reset_filter" id="reset_filter"
                                        class="btn btn-default btn-md">RESET</button>
                                </div>
                            </div>
                            <?php if ($_SESSION['role'] == 'ADMIN') { ?>
                                <div class="checkbox" style="margin:10px 0 0;">
                                    <label>
                                        <input type="checkbox" id="include_off">
                                        Include switched-off departments
                                        <small class="text-muted">&mdash; only departments switched on in Organization &gt; Skill Matrix Config are shown otherwise</small>
                                    </label>
                                </div>
                            <?php } ?>
                            <ul class="nav nav-pills" id="period_tabs" style="margin-top: 12px;">
                                <li class="active"><a href="#" data-period="current">Evaluation Quarter (Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?>)</a></li>
                                <li><a href="#" data-period="previous">Previous Quarter (Q<?php echo $previousQuarter; ?> <?php echo $previousYear; ?>)</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs view-tabs" id="view_tabs">
                <li class="active"><a href="#tab-dashboard" data-toggle="tab"><i class="fa fa-chart-bar"></i> Dashboard</a></li>
                <li><a href="#tab-stafflist" data-toggle="tab"><i class="fa fa-users"></i> Staff List</a></li>
            </ul>

            <div class="tab-content">
            <div class="tab-pane active" id="tab-dashboard">

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Skill Matrix Overview</strong>
                        </div>
                        <div class="panel-body">
                            <div class="dash-tiles">
                                <div class="dash-tiles-item">
                                    <div class="dash-tile">
                                        <div class="dash-tile-value" id="kpi-total">0</div>
                                        <div class="dash-tile-label">Total Staff</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-completion">0%</div>
                                        <div class="dash-tile-label">Completion Rate</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-approved">0</div>
                                        <div class="dash-tile-label">Approved</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile" style="border-left-color: var(--status-warning);">
                                        <div class="dash-tile-value" id="kpi-waiting">0</div>
                                        <div class="dash-tile-label">Waiting Approval</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile" style="border-left-color: var(--status-serious);">
                                        <div class="dash-tile-value" id="kpi-draft">0</div>
                                        <div class="dash-tile-label">Draft</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile" style="border-left-color: var(--status-critical);">
                                        <div class="dash-tile-value" id="kpi-notsubmitted">0</div>
                                        <div class="dash-tile-label">Not Submitted</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile" style="border-left-color: var(--status-critical);" title="Staff whose approved skill matrix scores below 50% (Novice or Minimal Competency)">
                                        <div class="dash-tile-value" id="kpi-below50">0</div>
                                        <div class="dash-tile-label">Score Below 50%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row" id="dashboard-chart-row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong id="dashboard-chart-title">Status by Department</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="dash-chart-scroll">
                                <canvas id="dashboard-status-chart"></canvas>
                            </div>
                            <div class="text-muted" id="dashboard-empty-state" style="display:none;text-align:center;padding:30px 0;">
                                No staff records match the current filter.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Status by Section</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Approval status breakdown across all sections.</div>
                            <div class="dash-chart-scroll-sm">
                                <canvas id="dashboard-section-chart"></canvas>
                            </div>
                            <div class="text-muted" id="dashboard-section-empty" style="display:none;text-align:center;padding:30px 0;">
                                No staff records match the current filter.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Status by Plant</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Approval status breakdown across all plants.</div>
                            <div class="dash-chart-scroll-sm">
                                <canvas id="dashboard-plant-chart"></canvas>
                            </div>
                            <div class="text-muted" id="dashboard-plant-empty" style="display:none;text-align:center;padding:30px 0;">
                                No staff records match the current filter.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong id="dashboard-score-group-title">Average Skill Score by Department</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage. Approved matrices only.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-group-chart"></canvas>
                            </div>
                            <div class="text-muted" id="dashboard-score-group-empty" style="display:none;text-align:center;padding:30px 0;">
                                No approved skill matrix for the current filter.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Average Skill Score by Plant</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage. Approved matrices only.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-plant-chart"></canvas>
                            </div>
                            <div class="text-muted" id="dashboard-score-plant-empty" style="display:none;text-align:center;padding:30px 0;">
                                No approved skill matrix for the current filter.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong id="dashboard-below50-title">Staff Below 50% by Department</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Number of staff whose skill matrix scores below 50% (Novice or Minimal Competency). Approved matrices only.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-below50-chart"></canvas>
                            </div>
                            <div class="text-muted" id="dashboard-below50-empty" style="display:none;text-align:center;padding:30px 0;">
                                No staff below 50% for the current filter.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Average Skill Score by Quarter</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage for each quarter, up to the last 8. Approved matrices only. Follows the department filter, not the quarter selected above.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-quarter-chart"></canvas>
                            </div>
                            <div class="text-muted" id="dashboard-score-quarter-empty" style="display:none;text-align:center;padding:30px 0;">
                                No approved skill matrix for the current filter.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>
            <div class="tab-pane" id="tab-stafflist">

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-8" style="margin-top: 10px;">
                                    <strong>Staff List</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <button type="button" id="export_excel_btn" class="btn btn-success btn-md">
                                        <i class="fa fa-file-excel"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
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
                                                <th>Plant</th>
                                                <th>Grade</th>
                                                <th>Status</th>
                                                <th>Approval Status</th>
                                                <th>Skill Score</th>
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

        // Admin's "Include switched-off departments" tick ('1'/'0'). Otherwise the
        // list, dashboard, export and matrix chart only cover departments
        // switched on in Organization > Skill Matrix Config.
        function includeOffDepartments() {
            return $('#include_off').is(':checked') ? '1' : '0';
        }

        // Which tab the staff table is showing: the evaluation quarter or the one before it.
        var skillMatrixPeriod = /[?&]period=previous(&|$)/.test(window.location.search) ? 'previous' : 'current';

        var skillMatrixTable = $('#skillmatrixlist').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "responsive": true,
            "autoWidth": false,
            "pageLength": 10,
            "info": true,
            "ajax": {
                url: "fetch_skill_matrix.php",
                type: "POST",
                dataSrc: '',
                data: function (data) {
                    data.action = "load_non_executive_staff";
                    data.period = skillMatrixPeriod;
                    data.department = $('#department').val() || "ALL";
                    data.section = $('#section').val() || "ALL";
                    data.plant = $('#plant').val() || "ALL";
                    data.include_off = includeOffDepartments();
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
                    "data": "plant"
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
                    // Approved matrices only; sorts by the number, staff without one last.
                    "data": "overall_score",
                    "render": function (score, type) {
                        if (type == 'sort' || type == 'type') {
                            return score === null ? -1 : parseFloat(score);
                        }
                        if (score === null) {
                            return '<span class="text-muted">-</span>';
                        }
                        var level = scoreLevelLabel(score);
                        if (type == 'filter') {
                            return score + '% ' + level;
                        }
                        return '<span class="' + (score < 50 ? 'skill-score-low' : '') + '">' + parseFloat(score).toFixed(1) + '%</span>' +
                            '<span class="skill-score-level">' + level + '</span>';
                    }
                },
                {
                    "data": "action"
                }
            ],
            "columnDefs": [{
                className: 'text-center',
                targets: [0, 1, 6, 7, 8, 9, 10]
            },
            {
                orderable: false,
                searchable: false,
                targets: [0, 10]
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

        var STATUS_ORDER = ['NOT SUBMITTED', 'DRAFT', 'WAITING APPROVAL', 'APPROVED'];
        var STATUS_COLORS = {
            'NOT SUBMITTED': '#d03b3b',
            'DRAFT': '#ec835a',
            'WAITING APPROVAL': '#fab219',
            'APPROVED': '#0ca30c'
        };
        var charts = { status: null, section: null, plant: null, scoreGroup: null, below50: null, scorePlant: null, scoreQuarter: null };

        // Same bands and rounding-down as matrix-chart.php (matrixPieLevel).
        var SCORE_LEVELS = [
            { min: 100, label: 'Highly Skilled' },
            { min: 75, label: 'Competent' },
            { min: 50, label: 'Medium Competency' },
            { min: 25, label: 'Novice' },
            { min: 0, label: 'Minimal Competency' }
        ];
        // One colour per bar, cycled when there are more bars than colours.
        var SCORE_BAR_COLORS = [
            '#4e79a7', '#f28e2b', '#59a14f', '#e15759', '#76b7b2',
            '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac',
            '#1f77b4', '#17becf', '#8c564b', '#bcbd22', '#7f7f7f',
            '#2ca02c', '#9467bd', '#d62728', '#ff7f0e', '#e377c2'
        ];

        // Splits a long name over several lines so the x-axis labels stay readable.
        function wrapChartLabel(text, maxChars) {
            var lines = [];
            var line = '';
            text.split(' ').forEach(function (word) {
                if (line && (line + ' ' + word).length > maxChars) {
                    lines.push(line);
                    line = word;
                } else {
                    line = line ? line + ' ' + word : word;
                }
            });
            if (line) {
                lines.push(line);
            }
            return lines;
        }

        function scoreLevelLabel(score) {
            for (var i = 0; i < SCORE_LEVELS.length; i++) {
                if (score >= SCORE_LEVELS[i].min) {
                    return SCORE_LEVELS[i].label;
                }
            }
            return SCORE_LEVELS[SCORE_LEVELS.length - 1].label;
        }

        // Average overall_score per group, ignoring staff without an approved matrix.
        function buildScoreGroups(rows, keyFn) {
            var groups = {};
            rows.forEach(function (r) {
                if (r.overall_score === null || r.overall_score === undefined) {
                    return;
                }
                var key = (keyFn(r) || '').toString().trim() || '(Unspecified)';
                if (!groups[key]) {
                    groups[key] = { sum: 0, count: 0 };
                }
                groups[key].sum += parseFloat(r.overall_score);
                groups[key].count++;
            });
            $.each(groups, function (key, g) {
                g.average = g.sum / g.count;
            });
            return groups;
        }

        // Writes the average on top of each bar (or the chart's own options.valueLabel text).
        var scoreValueLabelPlugin = {
            afterDatasetsDraw: function (chart) {
                var ctx = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                ctx.save();
                ctx.font = 'bold 11px sans-serif';
                ctx.fillStyle = '#333';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                meta.data.forEach(function (bar, index) {
                    var value = chart.data.datasets[0].data[index];
                    if (value === null) {
                        ctx.fillStyle = '#999';
                        ctx.fillText('No result', bar._model.x, chart.chartArea.bottom - 3);
                        ctx.fillStyle = '#333';
                        return;
                    }
                    var text = chart.options.valueLabel ? chart.options.valueLabel(value) : Math.round(value) + '%';
                    ctx.fillText(text, bar._model.x, bar._model.y - 3);
                });
                ctx.restore();
            }
        };

        // Staff below 50% per group: { name: { below, novice, minimal, approved } },
        // only groups that have at least one.
        function buildBelow50Groups(rows, keyFn) {
            var groups = {};
            rows.forEach(function (r) {
                if (r.overall_score === null || r.overall_score === undefined) {
                    return;
                }
                var key = (keyFn(r) || '').toString().trim() || '(Unspecified)';
                if (!groups[key]) {
                    groups[key] = { below: 0, novice: 0, minimal: 0, approved: 0 };
                }
                var score = parseFloat(r.overall_score);
                groups[key].approved++;
                if (score < 50) {
                    groups[key].below++;
                    groups[key][score >= 25 ? 'novice' : 'minimal']++;
                }
            });
            $.each(groups, function (key, g) {
                if (g.below === 0) {
                    delete groups[key];
                }
            });
            return groups;
        }

        function updateBelow50Chart(canvas, emptyEl, groups) {
            var groupNames = Object.keys(groups).sort(function (a, b) {
                return groups[b].below - groups[a].below;
            });

            if (groupNames.length === 0) {
                $(emptyEl).show();
                $(canvas).hide();
                if (charts.below50) {
                    charts.below50.destroy();
                    charts.below50 = null;
                }
                return;
            }
            $(emptyEl).hide();
            $(canvas).show();

            var chartData = {
                labels: groupNames.map(function (name) { return wrapChartLabel(name, 12); }),
                datasets: [{
                    label: 'Staff Below 50%',
                    backgroundColor: groupNames.map(function (name, index) {
                        return SCORE_BAR_COLORS[index % SCORE_BAR_COLORS.length];
                    }),
                    data: groupNames.map(function (name) { return groups[name].below; })
                }]
            };
            var chartOptions = {
                maintainAspectRatio: false,
                legend: { display: false },
                layout: { padding: { top: 20 } },
                plugins: { labels: false },
                valueLabel: function (value) { return value; },
                tooltips: {
                    callbacks: {
                        title: function (tooltipItems) {
                            return groupNames[tooltipItems[0].index];
                        },
                        label: function (tooltipItem) {
                            var group = groups[groupNames[tooltipItem.index]];
                            return group.below + ' of ' + group.approved + ' approved staff below 50% (' +
                                Math.round(group.below / group.approved * 100) + '%)';
                        },
                        afterLabel: function (tooltipItem) {
                            var group = groups[groupNames[tooltipItem.index]];
                            return 'Novice: ' + group.novice + ', Minimal Competency: ' + group.minimal;
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        barPercentage: 0.7,
                        maxBarThickness: 60,
                        gridLines: { display: false },
                        ticks: { autoSkip: false, maxRotation: 0, fontSize: 10 }
                    }],
                    yAxes: [{
                        ticks: { beginAtZero: true, precision: 0 }
                    }]
                }
            };

            if (charts.below50) {
                charts.below50.data = chartData;
                charts.below50.options = chartOptions;
                charts.below50.update();
            } else {
                charts.below50 = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: chartData,
                    options: chartOptions,
                    plugins: [scoreValueLabelPlugin]
                });
            }
        }

        // groups: { name: { average, count } }, average null for a group with no result.
        // Bars are sorted highest first unless groupNames gives the order.
        function updateScoreChart(chartKey, canvas, emptyEl, groups, groupNames) {
            groupNames = groupNames || Object.keys(groups).sort(function (a, b) {
                return groups[b].average - groups[a].average;
            });

            if (groupNames.length === 0) {
                $(emptyEl).show();
                $(canvas).hide();
                if (charts[chartKey]) {
                    charts[chartKey].destroy();
                    charts[chartKey] = null;
                }
                return;
            }
            $(emptyEl).hide();
            $(canvas).show();

            var chartData = {
                labels: groupNames.map(function (name) { return wrapChartLabel(name, 12); }),
                datasets: [{
                    label: 'Average Skill Score',
                    backgroundColor: groupNames.map(function (name, index) {
                        return SCORE_BAR_COLORS[index % SCORE_BAR_COLORS.length];
                    }),
                    data: groupNames.map(function (name) {
                        return groups[name].average === null ? null : Math.round(groups[name].average * 100) / 100;
                    })
                }]
            };
            var chartOptions = {
                maintainAspectRatio: false,
                legend: { display: false },
                // Room above the bars for the % labels on scores near 100%.
                layout: { padding: { top: 20 } },
                // chartjs-plugin-labels also draws on 'bar' charts; this chart has its own labels.
                plugins: { labels: false },
                tooltips: {
                    callbacks: {
                        title: function (tooltipItems) {
                            return groupNames[tooltipItems[0].index];
                        },
                        label: function (tooltipItem) {
                            var group = groups[groupNames[tooltipItem.index]];
                            if (group.average === null) {
                                return 'No result';
                            }
                            return 'Average: ' + group.average.toFixed(1) + '% (' + scoreLevelLabel(group.average) + ')';
                        },
                        afterLabel: function (tooltipItem) {
                            var count = groups[groupNames[tooltipItem.index]].count;
                            return count + ' approved matri' + (count == 1 ? 'x' : 'ces');
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        barPercentage: 0.7,
                        maxBarThickness: 60,
                        gridLines: { display: false },
                        ticks: { autoSkip: false, maxRotation: 0, fontSize: 10 }
                    }],
                    yAxes: [{
                        ticks: {
                            min: 0,
                            max: 100,
                            stepSize: 25,
                            callback: function (value) { return value + '%'; }
                        }
                    }]
                }
            };

            if (charts[chartKey]) {
                charts[chartKey].data = chartData;
                charts[chartKey].options = chartOptions;
                charts[chartKey].update();
            } else {
                charts[chartKey] = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: chartData,
                    options: chartOptions,
                    plugins: [scoreValueLabelPlugin]
                });
            }
        }

        function emptyStatusCounts() {
            return { 'NOT SUBMITTED': 0, 'DRAFT': 0, 'WAITING APPROVAL': 0, 'APPROVED': 0, total: 0 };
        }

        function buildGroups(rows, keyFn) {
            var groups = {};
            rows.forEach(function (r) {
                var key = (keyFn(r) || '').toString().trim() || '(Unspecified)';
                if (!groups[key]) {
                    groups[key] = emptyStatusCounts();
                }
                if (groups[key].hasOwnProperty(r.approval_status_raw)) {
                    groups[key][r.approval_status_raw]++;
                    groups[key].total++;
                }
            });
            return groups;
        }

        function sortedGroupNames(groups) {
            return Object.keys(groups).sort(function (a, b) {
                return groups[b].total - groups[a].total;
            });
        }

        function updateStackedBarChart(chartKey, canvas, emptyEl, groupNames, groups, fixedHeight) {
            if (groupNames.length === 0) {
                $(emptyEl).show();
                $(canvas).hide();
                if (charts[chartKey]) {
                    charts[chartKey].destroy();
                    charts[chartKey] = null;
                }
                return;
            }
            $(emptyEl).hide();
            $(canvas).show();
            canvas.parentElement.style.height = (fixedHeight || Math.max(140, groupNames.length * 26 + 40)) + 'px';

            var datasets = STATUS_ORDER.map(function (status) {
                return {
                    label: status,
                    backgroundColor: STATUS_COLORS[status],
                    data: groupNames.map(function (name) { return groups[name][status]; })
                };
            });

            var chartData = { labels: groupNames, datasets: datasets };
            var chartOptions = {
                maintainAspectRatio: false,
                legend: { position: 'top', labels: { boxWidth: 14 } },
                tooltips: {
                    mode: 'index',
                    callbacks: {
                        label: function (tooltipItem, data) {
                            var dataset = data.datasets[tooltipItem.datasetIndex];
                            return dataset.label + ': ' + dataset.data[tooltipItem.index];
                        }
                    }
                },
                scales: {
                    xAxes: [{ stacked: true, ticks: { beginAtZero: true, precision: 0 } }],
                    yAxes: [{ stacked: true }]
                }
            };

            if (charts[chartKey]) {
                charts[chartKey].data = chartData;
                charts[chartKey].options = chartOptions;
                charts[chartKey].update();
            } else {
                charts[chartKey] = new Chart(canvas.getContext('2d'), {
                    type: 'horizontalBar',
                    data: chartData,
                    options: chartOptions
                });
            }
        }

        function renderDashboard(rows) {
            rows = rows || [];
            var total = rows.length;
            var counts = emptyStatusCounts();
            rows.forEach(function (r) {
                if (counts.hasOwnProperty(r.approval_status_raw)) {
                    counts[r.approval_status_raw]++;
                }
            });

            $('#kpi-total').text(total);
            $('#kpi-completion').text(total > 0 ? Math.round((counts['APPROVED'] / total) * 100) + '%' : '0%');
            $('#kpi-approved').text(counts['APPROVED']);
            $('#kpi-waiting').text(counts['WAITING APPROVAL']);
            $('#kpi-draft').text(counts['DRAFT']);
            $('#kpi-notsubmitted').text(counts['NOT SUBMITTED']);
            $('#kpi-below50').text(rows.filter(function (r) {
                return r.overall_score !== null && r.overall_score !== undefined && parseFloat(r.overall_score) < 50;
            }).length);

            var deptGroups = buildGroups(rows, function (r) { return r.department; });
            var sectionGroups = buildGroups(rows, function (r) { return r.section; });
            var plantGroups = buildGroups(rows, function (r) { return r.plant; });

            // ===== Main chart: Status by Department - hidden once a department is picked, =====
            // since at that point it's showing exactly the same section
            // breakdown as the "Status by Section" chart below, just re-titled.
            var departmentFilterActive = $('#department').val() && $('#department').val() != 'ALL';
            $('#dashboard-chart-row').toggle(!departmentFilterActive);
            if (!departmentFilterActive) {
                $('#dashboard-chart-title').text('Status by Department');
                updateStackedBarChart(
                    'status',
                    document.getElementById('dashboard-status-chart'),
                    '#dashboard-empty-state',
                    sortedGroupNames(deptGroups),
                    deptGroups
                );
            }

            // ===== Status by Section =====
            updateStackedBarChart(
                'section',
                document.getElementById('dashboard-section-chart'),
                '#dashboard-section-empty',
                sortedGroupNames(sectionGroups),
                sectionGroups
            );

            // ===== Status by Plant =====
            updateStackedBarChart(
                'plant',
                document.getElementById('dashboard-plant-chart'),
                '#dashboard-plant-empty',
                sortedGroupNames(plantGroups),
                plantGroups
            );

            // ===== Average Skill Score by Department, or by Section once a department is picked =====
            $('#dashboard-score-group-title').text(departmentFilterActive ? 'Average Skill Score by Section' : 'Average Skill Score by Department');
            updateScoreChart(
                'scoreGroup',
                document.getElementById('dashboard-score-group-chart'),
                '#dashboard-score-group-empty',
                buildScoreGroups(rows, departmentFilterActive
                    ? function (r) { return r.section; }
                    : function (r) { return r.department; })
            );

            // ===== Staff Below 50% by Department, or by Section once a department is picked =====
            $('#dashboard-below50-title').text(departmentFilterActive ? 'Staff Below 50% by Section' : 'Staff Below 50% by Department');
            updateBelow50Chart(
                document.getElementById('dashboard-below50-chart'),
                '#dashboard-below50-empty',
                buildBelow50Groups(rows, departmentFilterActive
                    ? function (r) { return r.section; }
                    : function (r) { return r.department; })
            );

            // ===== Average Skill Score by Plant =====
            updateScoreChart(
                'scorePlant',
                document.getElementById('dashboard-score-plant-chart'),
                '#dashboard-score-plant-empty',
                buildScoreGroups(rows, function (r) { return r.plant; })
            );
        }

        // ===== Average Skill Score by Quarter: its own request, since it spans quarters =====
        var quarterScoresRequest = null;

        function loadQuarterScores() {
            if (quarterScoresRequest) {
                quarterScoresRequest.abort();
            }
            quarterScoresRequest = $.post("fetch_skill_matrix.php", {
                action: "load_quarter_scores",
                department: $('#department').val() || 'ALL',
                section: $('#section').val() || 'ALL',
                plant: $('#plant').val() || 'ALL',
                include_off: includeOffDepartments()
            }, function (data) {
                var groups = {};
                var quarterNames = [];
                $.each(data || [], function (i, q) {
                    groups[q.label] = { average: q.average, count: q.count };
                    quarterNames.push(q.label);
                });
                updateScoreChart(
                    'scoreQuarter',
                    document.getElementById('dashboard-score-quarter-chart'),
                    '#dashboard-score-quarter-empty',
                    groups,
                    quarterNames
                );
            }, 'json');
        }

        skillMatrixTable.on('xhr', function (e, settings, json) {
            renderDashboard(json);
            loadQuarterScores();
        });

        function loadDepartments() {
            $.post("fetch_skill_matrix.php", {
                action: "load_departments",
                include_off: includeOffDepartments()
            }, function (data) {
                $('#department').html(data.options || '');
                disableMatrixChartButton();
            }, 'json');
        }

        $(function () {
            loadDepartments();
            resetSectionFilter();
        });

        $('#include_off').change(function () {
            loadDepartments();
            resetSectionFilter();
            skillMatrixTable.ajax.reload();
        });

        function resetSectionFilter() {
            $('#section').html('<option value="ALL">-- Select Department First --</option>').prop('disabled', true);
        }

        function loadSections(department) {
            if (!department || department == 'ALL') {
                resetSectionFilter();
                return;
            }
            $.post("fetch_skill_matrix.php", {
                action: "load_sections_by_department",
                department: department
            }, function (data) {
                var options = '<option value="ALL">All Sections</option>';
                $.each(data, function (i, section) {
                    options += '<option value="' + section + '">' + section + '</option>';
                });
                $('#section').html(options).prop('disabled', false);
            }, 'json');
        }

        function disableMatrixChartButton() {
            matrixChartFilter = null;
            $('#matrix_chart_btn')
                .attr('href', '#')
                .addClass('disabled')
                .css({
                    'pointer-events': 'none',
                    'opacity': '0.65'
                });
        }

        // Last filter the chart button was enabled for, so switching the
        // quarter tab can point it at the same filter on the other quarter.
        var matrixChartFilter = null;

        function enableMatrixChartButton(department, section, plant) {
            matrixChartFilter = [department, section, plant];
            $('#matrix_chart_btn')
                .attr('href', 'matrix-chart.php?department=' + encodeURIComponent(department || 'ALL') +
                    '&section=' + encodeURIComponent(section || 'ALL') +
                    '&plant=' + encodeURIComponent(plant || 'ALL') +
                    (includeOffDepartments() === '1' ? '&include_off=1' : '') +
                    (skillMatrixPeriod == 'previous' ? '&period=previous' : ''))
                .removeClass('disabled')
                .css({
                    'pointer-events': 'auto',
                    'opacity': '1'
                });
        }

        $('#filter_dept').click(function () {
            var department = $('#department').val();
            var section = $('#section').val();
            var plant = $('#plant').val();

            var hasDepartment = department && department != 'ALL';
            var hasPlant = plant && plant != 'ALL';

            if (!hasDepartment && !hasPlant) {
                disableMatrixChartButton();
            } else {
                enableMatrixChartButton(department, section, plant);
            }

            skillMatrixTable.ajax.reload();
        });

        $('#department').change(function () {
            disableMatrixChartButton();
            loadSections($(this).val());
        });

        $('#export_excel_btn').click(function () {
            var params = new URLSearchParams({
                department: $('#department').val() || 'ALL',
                section: $('#section').val() || 'ALL',
                plant: $('#plant').val() || 'ALL',
                include_off: includeOffDepartments()
            });
            window.location.href = 'export_skill_matrix_list.php?' + params.toString();
        });

        $('#reset_filter').click(function () {
            $('#department').val('ALL');
            $('#plant').val('ALL');
            resetSectionFilter();
            disableMatrixChartButton();
            skillMatrixTable.ajax.reload();
        });


        $('#period_tabs li').removeClass('active');
        $('#period_tabs a[data-period="' + skillMatrixPeriod + '"]').parent().addClass('active');

        $('#period_tabs a').click(function (e) {
            e.preventDefault();
            skillMatrixPeriod = $(this).data('period');
            $('#period_tabs li').removeClass('active');
            $(this).parent().addClass('active');
            if (matrixChartFilter) {
                enableMatrixChartButton.apply(null, matrixChartFilter);
            }
            skillMatrixTable.ajax.reload();
        });

        // Dashboard / Staff List tabs. Charts and the table are drawn while their tab
        // may be hidden (zero width), so resize them once their tab is shown.
        $('#view_tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr('href');
            if (target == '#tab-dashboard') {
                $.each(charts, function (key, chart) {
                    if (chart) {
                        chart.resize();
                    }
                });
            } else {
                skillMatrixTable.columns.adjust();
            }
            try {
                sessionStorage.setItem('skillMatrixViewTab', target);
            } catch (err) { }
        });

        // Reopen the last tab used, so coming back from a staff's matrix lands on the Staff List.
        try {
            var lastViewTab = sessionStorage.getItem('skillMatrixViewTab');
            if (lastViewTab) {
                $('#view_tabs a[href="' + lastViewTab + '"]').tab('show');
            }
        } catch (err) { }

    </script>

    </html>
    <?php
} else {
    header("Location: ../../login.php");
    exit();
}
?>
