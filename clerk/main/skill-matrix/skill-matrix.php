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
        <script src="../../../asset/js/skill-matrix-dashboard.js?v=<?php echo filemtime(__DIR__ . "/../../../asset/js/skill-matrix-dashboard.js"); ?>"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" href="../../../asset/css/datepicker.css">
        <script src="../../../asset/js/bootstrap-datepicker1.js"></script>
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

            .dash-chart-scroll-sm {
                max-height: 260px;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .dash-score-chart {
                position: relative;
                height: 360px;
            }

            /* "Nothing to show" message, centred in the chart's space so the panel keeps its height. */
            .dash-chart-empty {
                position: absolute;
                top: 50%;
                left: 0;
                right: 0;
                transform: translateY(-50%);
                text-align: center;
            }

            /* Side-by-side panels in this row stretch to the same height. */
            .dash-equal-row {
                display: flex;
                flex-wrap: wrap;
            }

            .dash-equal-row:before,
            .dash-equal-row:after {
                display: none;
            }

            .dash-equal-row > [class*="col-"] {
                display: flex;
                flex-direction: column;
            }

            .dash-equal-row > [class*="col-"] > .panel {
                flex: 1;
            }

            @media (max-width: 991px) {
                .dash-equal-row > [class*="col-"] {
                    width: 100%;
                }
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

            .skill-score-level {
                display: block;
                font-size: 11px;
                color: #777;
            }

            .skill-score-low {
                color: #d03b3b;
                font-weight: bold;
            }
        </style>
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
                                        value="<?php echo htmlspecialchars($_SESSION['department']); ?>" readonly>
                                </div>
                            </div>
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
                                No staff records found.
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
                                No staff records found.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong id="dashboard-score-group-title">Average Skill Score by Section</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage. Approved matrices only. Red bars average below 50%.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-group-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="dashboard-score-group-empty" style="display:none;">
                                    No approved skill matrix yet.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row dash-equal-row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong id="dashboard-below50-title">Staff Below 50% by Section</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Number of staff whose skill matrix scores below 50% (Novice or Minimal Competency). Approved matrices only.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-below50-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="dashboard-below50-empty" style="display:none;">
                                    No staff below 50%.
                                </div>
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
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage for each quarter, up to the last 8. Approved matrices only. Red bars average below 50%. Not affected by the quarter selected above.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-quarter-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="dashboard-score-quarter-empty" style="display:none;">
                                    No approved skill matrix yet.
                                </div>
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
                                    <a href="export_skill_matrix_list.php" class="btn btn-success btn-md">
                                        <i class="fa fa-file-excel"></i> Export Excel
                                    </a>
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

        // Which quarter the page is showing: the evaluation quarter or the one before it.
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
                targets: [0, 1, 5, 6, 7, 8, 9]
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

        // ===== Dashboard: tiles and charts for this department, from the staff list data =====
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

            renderSkillMatrixCharts(rows, false);
        }

        skillMatrixTable.on('xhr', function (e, settings, json) {
            renderDashboard(json);
        });

        // The quarter chart spans every quarter, so it is loaded once, not per quarter tab.
        $.post("fetch_skill_matrix.php", { action: "load_quarter_scores" }, renderSkillMatrixQuarterChart, 'json');

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

        // Dashboard / Staff List tabs. Charts and the table are drawn while their tab
        // may be hidden (zero width), so resize them once their tab is shown.
        $('#view_tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr('href');
            if (target == '#tab-dashboard') {
                resizeSkillMatrixCharts();
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
    header("Location: ../../../login.php");
    exit();
}
?>
