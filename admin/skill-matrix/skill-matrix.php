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
                                    <button type="button" id="export_excel_btn" class="btn btn-success btn-md">
                                        <i class="fa fa-file-excel"></i> Export Excel
                                    </button>
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
                                                <th>Plant</th>
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

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Skill Matrix Overview</strong>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-2 col-sm-4 col-xs-6">
                                    <div class="dash-tile">
                                        <div class="dash-tile-value" id="kpi-total">0</div>
                                        <div class="dash-tile-label">Total Staff</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-xs-6">
                                    <div class="dash-tile" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-completion">0%</div>
                                        <div class="dash-tile-label">Completion Rate</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-xs-6">
                                    <div class="dash-tile" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-approved">0</div>
                                        <div class="dash-tile-label">Approved</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-xs-6">
                                    <div class="dash-tile" style="border-left-color: var(--status-warning);">
                                        <div class="dash-tile-value" id="kpi-waiting">0</div>
                                        <div class="dash-tile-label">Waiting Approval</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-xs-6">
                                    <div class="dash-tile" style="border-left-color: var(--status-serious);">
                                        <div class="dash-tile-value" id="kpi-draft">0</div>
                                        <div class="dash-tile-label">Draft</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-xs-6">
                                    <div class="dash-tile" style="border-left-color: var(--status-critical);">
                                        <div class="dash-tile-value" id="kpi-notsubmitted">0</div>
                                        <div class="dash-tile-label">Not Submitted</div>
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
                    "data": "action"
                }
            ],
            "columnDefs": [{
                className: 'text-center',
                targets: [0, 1, 6, 7, 8, 9]
            },
            {
                orderable: false,
                searchable: false,
                targets: [0, 9]
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
        var charts = { status: null, section: null, plant: null };

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
        }

        skillMatrixTable.on('xhr', function (e, settings, json) {
            renderDashboard(json);
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
            $('#matrix_chart_btn')
                .attr('href', '#')
                .addClass('disabled')
                .css({
                    'pointer-events': 'none',
                    'opacity': '0.65'
                });
        }

        function enableMatrixChartButton(department, section, plant) {
            $('#matrix_chart_btn')
                .attr('href', 'matrix-chart.php?department=' + encodeURIComponent(department || 'ALL') +
                    '&section=' + encodeURIComponent(section || 'ALL') +
                    '&plant=' + encodeURIComponent(plant || 'ALL') +
                    (includeOffDepartments() === '1' ? '&include_off=1' : ''))
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
            skillMatrixTable.ajax.reload();
        });

    </script>

    </html>
    <?php
} else {
    header("Location: ../../login.php");
    exit();
}
?>
