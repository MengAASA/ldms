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
        <script src="../../asset/js/skill-matrix-dashboard.js?v=<?php echo filemtime(__DIR__ . "/../../asset/js/skill-matrix-dashboard.js"); ?>"></script>
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

            /* Overview tiles open a breakdown modal when clicked. */
            .dash-tile-link {
                cursor: pointer;
                transition: box-shadow 0.15s, background 0.15s;
            }

            .dash-tile-link:hover,
            .dash-tile-link:focus {
                background: #fff;
                box-shadow: 0 1px 6px rgba(0, 0, 0, 0.18);
                outline: none;
            }

            .dash-tile-hint {
                font-size: 10px;
                color: #2a78d6;
                margin-top: 4px;
            }

            #breakdown_modal .modal-dialog {
                width: 90%;
                max-width: 1300px;
            }

            #breakdown_body th:not(.text-center) {
                text-align: left;
            }

            #breakdown_body .breakdown-group-row {
                cursor: pointer;
            }

            #breakdown_body .breakdown-group-row > td:first-child {
                white-space: nowrap;
            }

            /* A big department's staff list scrolls on its own, so the other rows stay in reach. */
            #breakdown_body .breakdown-staff-list {
                max-height: 420px;
                overflow-y: auto;
            }

            #breakdown_body .breakdown-group-row:hover > td {
                background: #eef4fc;
            }

            #breakdown_body .breakdown-group-row .fa {
                color: #2a78d6;
                width: 12px;
            }

            #breakdown_body .breakdown-staff-row > td {
                background: #f7f7f5;
                padding: 4px 10px 12px 34px;
            }

            #breakdown_body .breakdown-staff-row table {
                margin: 6px 0 0;
                background: #fff;
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
                                    <div class="dash-tile dash-tile-link" data-breakdown="total" role="button" tabindex="0">
                                        <div class="dash-tile-value" id="kpi-total">0</div>
                                        <div class="dash-tile-label">Total Staff</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile dash-tile-link" data-breakdown="completion" role="button" tabindex="0" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-completion">0%</div>
                                        <div class="dash-tile-label">Completion Rate</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile dash-tile-link" data-breakdown="approved" role="button" tabindex="0" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-approved">0</div>
                                        <div class="dash-tile-label">Approved</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile dash-tile-link" data-breakdown="waiting" role="button" tabindex="0" style="border-left-color: var(--status-warning);">
                                        <div class="dash-tile-value" id="kpi-waiting">0</div>
                                        <div class="dash-tile-label">Waiting Approval</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile dash-tile-link" data-breakdown="draft" role="button" tabindex="0" style="border-left-color: var(--status-serious);">
                                        <div class="dash-tile-value" id="kpi-draft">0</div>
                                        <div class="dash-tile-label">Draft</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile dash-tile-link" data-breakdown="notsubmitted" role="button" tabindex="0" style="border-left-color: var(--status-critical);">
                                        <div class="dash-tile-value" id="kpi-notsubmitted">0</div>
                                        <div class="dash-tile-label">Not Submitted</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </div>
                                </div>
                                <div class="dash-tiles-item">
                                    <div class="dash-tile dash-tile-link" data-breakdown="below50" role="button" tabindex="0" style="border-left-color: var(--status-critical);" title="Staff whose approved skill matrix scores below 50% (Novice or Minimal Competency)">
                                        <div class="dash-tile-value" id="kpi-below50">0</div>
                                        <div class="dash-tile-label">Score Below 50%</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
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
                            <strong id="dashboard-score-group-title">Average Skill Score by Department</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage. Approved matrices only. Red bars average below 50%.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-group-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="dashboard-score-group-empty" style="display:none;">
                                    No approved skill matrix for the current filter.
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
                            <strong>Average Skill Score by Plant</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage. Approved matrices only. Red bars average below 50%.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-plant-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="dashboard-score-plant-empty" style="display:none;">
                                    No approved skill matrix for the current filter.
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
                            <strong id="dashboard-below50-title">Staff Below 50% by Department</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Number of staff whose skill matrix scores below 50% (Novice or Minimal Competency). Approved matrices only.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-below50-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="dashboard-below50-empty" style="display:none;">
                                    No staff below 50% for the current filter.
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
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage for each quarter, up to the last 8. Approved matrices only. Red bars average below 50%. Follows the department filter, not the quarter selected above.</div>
                            <div class="dash-score-chart">
                                <canvas id="dashboard-score-quarter-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="dashboard-score-quarter-empty" style="display:none;">
                                    No approved skill matrix for the current filter.
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

        <!-- Breakdown behind an overview tile, filled by showTileBreakdown(). -->
        <div class="modal fade" id="breakdown_modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                        <h4 class="modal-title" id="breakdown_title"></h4>
                        <small class="text-muted" id="breakdown_subtitle"></small>
                    </div>
                    <div class="modal-body" id="breakdown_body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
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

        function renderDashboard(rows) {
            rows = rows || [];
            dashboardRows = rows;
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

            // A department picked: the charts break it down by section instead.
            var departmentFilterActive = $('#department').val() && $('#department').val() != 'ALL';
            renderSkillMatrixCharts(rows, !departmentFilterActive);
        }

        // ===== Overview tiles: click one to see its count by department (by section
        // when a department is picked, like the charts), with the staff behind it =====
        var dashboardRows = [];

        function escapeHtml(text) {
            return $('<div>').text(text === null || text === undefined ? '' : text).html();
        }

        function hasStatus(status) {
            return function (r) { return r.approval_status_raw == status; };
        }

        function isBelow50(r) {
            return r.overall_score !== null && r.overall_score !== undefined && parseFloat(r.overall_score) < 50;
        }

        // count: the staff the tile counts. list: the staff listed when a group is
        // expanded, if not the counted ones (Completion Rate lists who is still outstanding).
        var TILE_BREAKDOWNS = {
            total: { title: 'Total Staff', count: function () { return true; }, statusColumns: true, showStatus: true },
            completion: { title: 'Completion Rate', count: hasStatus('APPROVED'), list: function (r) { return r.approval_status_raw != 'APPROVED'; }, rate: true, showStatus: true },
            approved: { title: 'Approved', count: hasStatus('APPROVED'), showScore: true },
            waiting: { title: 'Waiting Approval', count: hasStatus('WAITING APPROVAL') },
            draft: { title: 'Draft', count: hasStatus('DRAFT') },
            notsubmitted: { title: 'Not Submitted', count: hasStatus('NOT SUBMITTED') },
            below50: { title: 'Score Below 50%', count: isBelow50, showScore: true }
        };

        // The groups in the open modal, for building a staff list when one is expanded.
        var breakdownGroups = [];
        var breakdownTile = null;
        var breakdownByDepartment = true;

        function percentText(part, whole) {
            return whole > 0 ? Math.round((part / whole) * 100) + '%' : '-';
        }

        function showTileBreakdown(key) {
            var tile = TILE_BREAKDOWNS[key];
            if (!tile) {
                return;
            }
            breakdownTile = tile;
            breakdownByDepartment = !($('#department').val() && $('#department').val() != 'ALL');
            var groupLabel = breakdownByDepartment ? 'Department' : 'Section';
            var listFn = tile.list || tile.count;

            var groupsByName = {};
            var totalCount = 0;
            dashboardRows.forEach(function (r) {
                var name = ((breakdownByDepartment ? r.department : r.section) || '').toString().trim() || '(Unspecified)';
                if (!groupsByName[name]) {
                    groupsByName[name] = { name: name, total: 0, count: 0, statuses: emptyStatusCounts(), staff: [] };
                }
                var group = groupsByName[name];
                group.total++;
                if (group.statuses.hasOwnProperty(r.approval_status_raw)) {
                    group.statuses[r.approval_status_raw]++;
                }
                if (tile.count(r)) {
                    group.count++;
                    totalCount++;
                }
                if (listFn(r)) {
                    group.staff.push(r);
                }
            });

            // Total and Completion Rate show every group; the others only groups with staff in that count.
            breakdownGroups = $.map(groupsByName, function (group) { return group; }).filter(function (group) {
                return tile.statusColumns || tile.rate || group.count > 0;
            });
            breakdownGroups.sort(function (a, b) {
                if (tile.rate) {
                    var diff = a.count / a.total - b.count / b.total;
                    if (diff !== 0) {
                        return diff;
                    }
                } else if (b.count !== a.count) {
                    return b.count - a.count;
                }
                return a.name.localeCompare(b.name);
            });

            $('#breakdown_title').text(tile.title + ' by ' + groupLabel);
            $('#breakdown_subtitle').text($('#period_tabs li.active a').text() +
                (breakdownByDepartment ? '' : ' — ' + $('#department').val()));

            var html;
            if (breakdownGroups.length === 0) {
                html = '<div class="text-muted text-center" style="padding:30px 0;">No staff for the current filter.</div>';
            } else {
                var groupWord = groupLabel.toLowerCase() + (breakdownGroups.length == 1 ? '' : 's');
                var summary;
                if (tile.rate) {
                    summary = '<strong>' + totalCount + '</strong> of <strong>' + dashboardRows.length + '</strong> staff approved (' +
                        percentText(totalCount, dashboardRows.length) + '). Lowest completion first. Click a ' + groupLabel.toLowerCase() +
                        ' to see the staff not approved yet.';
                } else {
                    summary = '<strong>' + totalCount + '</strong> staff in <strong>' + breakdownGroups.length + '</strong> ' + groupWord +
                        '. Click a ' + groupLabel.toLowerCase() + ' to see the staff.';
                }

                var head = '<th class="text-center" style="width:60px;">No.</th><th>' + groupLabel + '</th>';
                if (tile.statusColumns) {
                    head += '<th class="text-center">Total Staff</th><th class="text-center">Approved</th>' +
                        '<th class="text-center">Waiting Approval</th><th class="text-center">Draft</th>' +
                        '<th class="text-center">Not Submitted</th><th class="text-center">Completion</th>';
                } else if (tile.rate) {
                    head += '<th class="text-center">Approved</th><th class="text-center">Total Staff</th>' +
                        '<th class="text-center">Completion</th>';
                } else {
                    head += '<th class="text-center">' + escapeHtml(tile.title) + '</th><th class="text-center">Total Staff</th>' +
                        '<th class="text-center">% of ' + groupLabel + '</th>';
                }
                var columnCount = $('<tr>' + head + '</tr>').children().length;

                var body = '';
                breakdownGroups.forEach(function (group, index) {
                    body += '<tr class="breakdown-group-row" data-index="' + index + '">' +
                        '<td class="text-center"><i class="fa fa-chevron-right"></i> ' + (index + 1) + '</td>' +
                        '<td>' + escapeHtml(group.name) + '</td>';
                    if (tile.statusColumns) {
                        body += '<td class="text-center"><strong>' + group.total + '</strong></td>' +
                            '<td class="text-center">' + group.statuses['APPROVED'] + '</td>' +
                            '<td class="text-center">' + group.statuses['WAITING APPROVAL'] + '</td>' +
                            '<td class="text-center">' + group.statuses['DRAFT'] + '</td>' +
                            '<td class="text-center">' + group.statuses['NOT SUBMITTED'] + '</td>' +
                            '<td class="text-center">' + percentText(group.statuses['APPROVED'], group.total) + '</td>';
                    } else if (tile.rate) {
                        body += '<td class="text-center">' + group.count + '</td>' +
                            '<td class="text-center">' + group.total + '</td>' +
                            '<td class="text-center"><strong>' + percentText(group.count, group.total) + '</strong></td>';
                    } else {
                        body += '<td class="text-center"><strong>' + group.count + '</strong></td>' +
                            '<td class="text-center">' + group.total + '</td>' +
                            '<td class="text-center">' + percentText(group.count, group.total) + '</td>';
                    }
                    body += '</tr>' +
                        '<tr class="breakdown-staff-row" style="display:none;"><td colspan="' + columnCount + '"></td></tr>';
                });

                html = '<p>' + summary + '</p>' +
                    '<table class="table table-bordered table-condensed" style="margin-bottom:0;">' +
                    '<thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table>';
            }

            $('#breakdown_body').html(html);
            $('#breakdown_modal').modal('show');
        }

        // Staff behind one group, built the first time it is expanded.
        function breakdownStaffTable(group) {
            if (group.staff.length === 0) {
                return '<div class="text-muted" style="padding-top:8px;">No staff to list.</div>';
            }
            var tile = breakdownTile;
            var head = '<th class="text-center" style="width:40px;">No.</th><th class="text-center">Staff No.</th><th>Employee Name</th>' +
                (breakdownByDepartment ? '<th>Section</th>' : '') + '<th>Plant</th>' +
                (tile.showStatus ? '<th class="text-center">Approval Status</th>' : '') +
                (tile.showScore ? '<th class="text-center">Skill Score</th>' : '') +
                '<th class="text-center">Action</th>';

            var staff = group.staff.slice().sort(function (a, b) {
                if (tile.showScore) {
                    return parseFloat(a.overall_score) - parseFloat(b.overall_score);
                }
                return String(a.staffname).localeCompare(String(b.staffname));
            });

            var body = '';
            staff.forEach(function (r, index) {
                body += '<tr><td class="text-center">' + (index + 1) + '</td>' +
                    '<td class="text-center">' + escapeHtml(r.staffno) + '</td>' +
                    '<td>' + escapeHtml(r.staffname) + '</td>' +
                    (breakdownByDepartment ? '<td>' + escapeHtml(r.section) + '</td>' : '') +
                    '<td>' + escapeHtml(r.plant) + '</td>';
                if (tile.showStatus) {
                    body += '<td class="text-center">' + r.approval_status + '</td>';
                }
                if (tile.showScore) {
                    var score = parseFloat(r.overall_score);
                    body += '<td class="text-center"><span class="' + (score < 50 ? 'skill-score-low' : '') + '">' + score.toFixed(1) + '%</span>' +
                        '<span class="skill-score-level">' + scoreLevelLabel(score) + '</span></td>';
                }
                body += '<td class="text-center">' + r.action + '</td></tr>';
            });

            return '<div class="breakdown-staff-list"><table class="table table-bordered table-condensed table-striped">' +
                '<thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table></div>';
        }

        $('.dash-tile-link').on('click', function () {
            showTileBreakdown($(this).data('breakdown'));
        }).on('keydown', function (e) {
            // Enter or Space, for keyboard users.
            if (e.which == 13 || e.which == 32) {
                e.preventDefault();
                showTileBreakdown($(this).data('breakdown'));
            }
        });

        $('#breakdown_body').on('click', '.breakdown-group-row', function () {
            var $staffRow = $(this).next('.breakdown-staff-row');
            if (!$staffRow.data('built')) {
                $staffRow.children('td').html(breakdownStaffTable(breakdownGroups[$(this).data('index')]));
                $staffRow.data('built', true);
            }
            $staffRow.toggle();
            $(this).find('.fa').toggleClass('fa-chevron-right fa-chevron-down');
        });

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
            }, renderSkillMatrixQuarterChart, 'json');
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
    header("Location: ../../login.php");
    exit();
}
?>
