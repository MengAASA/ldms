<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
list($previousYear, $previousQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);

include_once __DIR__ . '/../../../skill_matrix_department.php';

// HOD copy of admin/skill-matrix/skill-matrix.php: the same view of every department.
// Data comes from this folder's fetch_skill_matrix.php, where VIEW and approval
// cover only the matrices submitted to this HOD.
function canApproveSkillMatrix()
{
    global $conn;

    if (!skillMatrixDepartmentEnabled()) {
        return false;
    }

    if (isset($_SESSION['id']) && (!isset($_SESSION['designation']) || !isset($_SESSION['hodid']))) {
        $sessionUserId = (int) $_SESSION['id'];
        $sessionUserQuery = mysqli_query($conn, "SELECT designation, hodid FROM user WHERE id = '$sessionUserId' LIMIT 1");
        if ($sessionUserQuery && $sessionUserRow = mysqli_fetch_assoc($sessionUserQuery)) {
            $_SESSION['designation'] = $sessionUserRow['designation'];
            $_SESSION['hodid'] = $sessionUserRow['hodid'];
        }
    }

    // A department head's own hodid is intentionally left at 0 by
    // trg_departments_hod_update (self-loop guard) - it is not a signal
    // of whether they head a department. Check departments.hod_user_id
    // directly instead.
    if (isset($_SESSION['id']) && !isset($_SESSION['is_department_hod'])) {
        $sessionUserId = (int) $_SESSION['id'];
        $deptHodQuery = mysqli_query($conn, "SELECT 1 FROM departments WHERE hod_user_id = '$sessionUserId' LIMIT 1");
        $_SESSION['is_department_hod'] = ($deptHodQuery && mysqli_num_rows($deptHodQuery) > 0) ? 1 : 0;
    }

    return isset($_SESSION['fullname'], $_SESSION['role'], $_SESSION['designation'], $_SESSION['usertype'])
        && $_SESSION['role'] == ''
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && !empty($_SESSION['is_department_hod'])
        && $_SESSION['usertype'] == 'HOD';
}

if (isset($_SESSION['fullname']) && canApproveSkillMatrix()) {
    // The department(s) this HOD heads, for the My Department tab.
    $myDepartments = array();
    $myDepartmentStmt = $conn->prepare("SELECT name FROM departments WHERE hod_user_id = ? ORDER BY name");
    $hodId = (int) $_SESSION['id'];
    $myDepartmentStmt->bind_param("i", $hodId);
    $myDepartmentStmt->execute();
    $myDepartmentResult = $myDepartmentStmt->get_result();
    while ($myDepartmentRow = $myDepartmentResult->fetch_assoc()) {
        $myDepartments[] = $myDepartmentRow['name'];
    }

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

            /* Overview tiles link to skill-matrix-breakdown.php. */
            a.dash-tile-link {
                display: block;
                color: inherit;
                text-decoration: none;
                cursor: pointer;
                transition: box-shadow 0.15s, background 0.15s;
            }

            a.dash-tile-link:hover,
            a.dash-tile-link:focus {
                background: #fff;
                box-shadow: 0 1px 6px rgba(0, 0, 0, 0.18);
                text-decoration: none;
                outline: none;
            }

            .dash-tile-hint {
                font-size: 10px;
                color: #2a78d6;
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
                        <li><a href="../attendance/training.php">MY TRAINING</a></li>
                        <li><a href="../pme/pme.php">PME</a></li>
                        <li><a href="../tna/staff_list.php">TNA</a></li>
                        <li><a href="../tni/tni.php">TNI</a></li>
                        <li class="active"><a href="skill-matrix.php">SKILL MATRIX</a></li>
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
                                    <strong>Department Filter</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <a href="matrix-chart.php" id="hod_matrix_chart_btn" class="btn btn-info btn-md" title="Matrix chart of the skill matrices submitted to you">
                                        <i class="fa fa-chart-bar"></i> Matrix Chart
                                    </a>
                                </div>
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
                <li><a href="#tab-mydepartment" data-toggle="tab"><i class="fa fa-building"></i> My Department</a></li>
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
                                    <a class="dash-tile dash-tile-link" data-breakdown="total" href="skill-matrix-breakdown.php?tile=total">
                                        <div class="dash-tile-value" id="kpi-total">0</div>
                                        <div class="dash-tile-label">Total Staff</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </a>
                                </div>
                                <div class="dash-tiles-item">
                                    <a class="dash-tile dash-tile-link" data-breakdown="completion" href="skill-matrix-breakdown.php?tile=completion" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-completion">0%</div>
                                        <div class="dash-tile-label">Completion Rate</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </a>
                                </div>
                                <div class="dash-tiles-item">
                                    <a class="dash-tile dash-tile-link" data-breakdown="approved" href="skill-matrix-breakdown.php?tile=approved" style="border-left-color: var(--status-good);">
                                        <div class="dash-tile-value" id="kpi-approved">0</div>
                                        <div class="dash-tile-label">Approved</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </a>
                                </div>
                                <div class="dash-tiles-item">
                                    <a class="dash-tile dash-tile-link" data-breakdown="waiting" href="skill-matrix-breakdown.php?tile=waiting" style="border-left-color: var(--status-warning);">
                                        <div class="dash-tile-value" id="kpi-waiting">0</div>
                                        <div class="dash-tile-label">Waiting Approval</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </a>
                                </div>
                                <div class="dash-tiles-item">
                                    <a class="dash-tile dash-tile-link" data-breakdown="draft" href="skill-matrix-breakdown.php?tile=draft" style="border-left-color: var(--status-serious);">
                                        <div class="dash-tile-value" id="kpi-draft">0</div>
                                        <div class="dash-tile-label">Draft</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </a>
                                </div>
                                <div class="dash-tiles-item">
                                    <a class="dash-tile dash-tile-link" data-breakdown="notsubmitted" href="skill-matrix-breakdown.php?tile=notsubmitted" style="border-left-color: var(--status-critical);">
                                        <div class="dash-tile-value" id="kpi-notsubmitted">0</div>
                                        <div class="dash-tile-label">Not Submitted</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </a>
                                </div>
                                <div class="dash-tiles-item">
                                    <a class="dash-tile dash-tile-link" data-breakdown="below50" href="skill-matrix-breakdown.php?tile=below50" style="border-left-color: var(--status-critical);" title="Staff whose approved skill matrix scores below 50% (Novice or Minimal Competency)">
                                        <div class="dash-tile-value" id="kpi-below50">0</div>
                                        <div class="dash-tile-label">Score Below 50%</div>
                                        <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                    </a>
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
                                <div class="col-md-4" style="margin-top: 10px;">
                                    <strong>Staff List</strong>
                                </div>
                                <div class="col-md-8" align="right">
                                    <button type="button" id="export_excel_btn" class="btn btn-success btn-md">
                                        <i class="fa fa-file-excel"></i> Export Excel
                                    </button>
                                    <button type="button" id="approve_selected_btn" class="btn btn-primary btn-md" disabled>
                                        <i class="fa fa-check"></i> Approve Selected (<span id="approve_selected_count">0</span>)
                                    </button>
                                    <button type="button" id="approve_all_btn" class="btn btn-warning btn-md" disabled title="Every skill matrix waiting for your approval in this quarter">
                                        <i class="fa fa-check-double"></i> Approve All Pending (<span id="approve_all_count">0</span>)
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="alert alert-warning" id="pending_outside_list" style="display:none;"></div>
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <table id="skillmatrixlist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th class="text-center"><input type="checkbox" id="approve_select_all" title="Select all waiting your approval"></th>
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
            <div class="tab-pane" id="tab-mydepartment">
            <?php
            // My Department: the department(s) this HOD heads, for the quarter picked above.
            // Filled by renderMyDepartment() from department=MY.
            $myTiles = array(
                array('total', 'Total Staff', '', '0', ''),
                array('completion', 'Completion Rate', 'var(--status-good)', '0%', ''),
                array('approved', 'Approved', 'var(--status-good)', '0', ''),
                array('waiting', 'Waiting Approval', 'var(--status-warning)', '0', ''),
                array('draft', 'Draft', 'var(--status-serious)', '0', ''),
                array('notsubmitted', 'Not Submitted', 'var(--status-critical)', '0', ''),
                array('below50', 'Score Below 50%', 'var(--status-critical)', '0', 'Staff whose approved skill matrix scores below 50% (Novice or Minimal Competency)'),
            );
            ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>My Department: <?php echo htmlspecialchars(implode(', ', $myDepartments)); ?></strong>
                            <span class="text-muted" style="font-size:12px;">&mdash; the department<?php echo count($myDepartments) == 1 ? '' : 's'; ?> you head, for the quarter selected above. The Department, Section and Plant filters don't apply here.</span>
                        </div>
                        <div class="panel-body">
                            <div class="dash-tiles">
                                <?php foreach ($myTiles as $myTile) { ?>
                                    <div class="dash-tiles-item">
                                        <a class="dash-tile dash-tile-link mydept-tile" data-breakdown="<?php echo $myTile[0]; ?>" href="skill-matrix-breakdown.php?tile=<?php echo $myTile[0]; ?>&amp;department=MY"<?php
                                            echo $myTile[2] != '' ? ' style="border-left-color: ' . $myTile[2] . ';"' : '';
                                            echo $myTile[4] != '' ? ' title="' . $myTile[4] . '"' : ''; ?>>
                                            <div class="dash-tile-value" id="mydept-kpi-<?php echo $myTile[0]; ?>"><?php echo $myTile[3]; ?></div>
                                            <div class="dash-tile-label"><?php echo $myTile[1]; ?></div>
                                            <div class="dash-tile-hint"><i class="fa fa-list-ul"></i> View breakdown</div>
                                        </a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong id="mydept-score-group-title">Average Skill Score by Section</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Average overall skill matrix percentage. Approved matrices only. Red bars average below 50%.</div>
                            <div class="dash-score-chart">
                                <canvas id="mydept-score-group-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="mydept-score-group-empty" style="display:none;">
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
                            <strong id="mydept-below50-title">Staff Below 50% by Section</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="text-muted" style="font-size:11px;margin-bottom:8px;">Number of staff whose skill matrix scores below 50% (Novice or Minimal Competency). Approved matrices only.</div>
                            <div class="dash-score-chart">
                                <canvas id="mydept-below50-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="mydept-below50-empty" style="display:none;">
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
                                <canvas id="mydept-score-quarter-chart"></canvas>
                                <div class="text-muted dash-chart-empty" id="mydept-score-quarter-empty" style="display:none;">
                                    No approved skill matrix yet.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row" id="mydept-chart-row" style="display:none;">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Status by Department</strong>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="dash-chart-scroll">
                                <canvas id="mydept-status-chart"></canvas>
                            </div>
                            <div class="text-muted" id="mydept-empty-state" style="display:none;text-align:center;padding:30px 0;">
                                No staff records found.
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
                                <canvas id="mydept-section-chart"></canvas>
                            </div>
                            <div class="text-muted" id="mydept-section-empty" style="display:none;text-align:center;padding:30px 0;">
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
                                <canvas id="mydept-plant-chart"></canvas>
                            </div>
                            <div class="text-muted" id="mydept-plant-empty" style="display:none;text-align:center;padding:30px 0;">
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
                            <div class="row">
                                <div class="col-md-8" style="margin-top: 10px;">
                                    <strong>My Department Staff</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <button type="button" id="mydept_export_btn" class="btn btn-success btn-md">
                                        <i class="fa fa-file-excel"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table id="mydeptlist" class="table table-bordered table-striped" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Staff No.</th>
                                            <th>Employee Name</th>
                                            <?php if (count($myDepartments) > 1) { ?>
                                                <th>Department</th>
                                            <?php } ?>
                                            <th>Section</th>
                                            <th>Plant</th>
                                            <th>Grade</th>
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

        // The filter of the staff table's last request, which the overview tiles pass on. Declared
        // before the table, whose first request runs while it is set up.
        var dashboardFilter = null;

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
                    // The filter this load is for, which the overview tiles pass on.
                    dashboardFilter = {
                        department: data.department,
                        section: data.section,
                        plant: data.plant,
                        period: data.period,
                        include_off: data.include_off
                    };
                }
            },
            "columns": [
                {
                    // Tick to approve: only matrices waiting for this HOD's approval.
                    "data": "approve_evaluation_id",
                    "render": function (id, type) {
                        if (type != 'display') {
                            return id ? 1 : 0;
                        }
                        return id ? '<input type="checkbox" class="approve-check" value="' + id + '" title="Select to approve">' : '';
                    }
                },
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
                targets: [0, 1, 2, 7, 8, 9, 10, 11]
            },
            {
                orderable: false,
                searchable: false,
                targets: [0, 1, 11]
            }],
            "drawCallback": function () {
                var api = this.api();
                var pageInfo = api.page.info();

                api.column(1, {
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
            updateTileLinks();
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

        // ===== Overview tiles open skill-matrix-breakdown.php for the filter the dashboard is showing =====

        function updateTileLinks() {
            if (!dashboardFilter) {
                return;
            }
            $('#tab-dashboard .dash-tile-link').each(function () {
                $(this).attr('href', 'skill-matrix-breakdown.php?' + $.param($.extend({ tile: $(this).data('breakdown') }, dashboardFilter)));
            });
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
            }, function (quarters) {
                // Wrapped: jQuery would pass its "success" status as the chart prefix.
                renderSkillMatrixQuarterChart(quarters);
            }, 'json');
        }

        skillMatrixTable.on('xhr', function (e, settings, json) {
            renderDashboard(json);
            loadQuarterScores();
            loadPendingApprovals();
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
            updateHodMatrixChartButton();
            skillMatrixTable.ajax.reload();
            myDepartmentTable.ajax.reload();
        });

        // Dashboard / Staff List tabs. Charts and the table are drawn while their tab
        // may be hidden (zero width), so resize them once their tab is shown.
        $('#view_tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr('href');
            if (target == '#tab-dashboard' || target == '#tab-mydepartment') {
                resizeSkillMatrixCharts();
            }
            if (target == '#tab-stafflist') {
                skillMatrixTable.columns.adjust();
            }
            // Not yet set when the last tab is reopened on load; it is drawn visible then.
            if (target == '#tab-mydepartment' && myDepartmentTable) {
                myDepartmentTable.columns.adjust();
            }
            try {
                sessionStorage.setItem('hodSkillMatrixViewTab', target);
            } catch (err) { }
        });

        // Reopen the last tab used, so coming back from a staff's matrix lands on the Staff List.
        try {
            var lastViewTab = sessionStorage.getItem('hodSkillMatrixViewTab');
            if (lastViewTab) {
                $('#view_tabs a[href="' + lastViewTab + '"]').tab('show');
            }
        } catch (err) { }

        // ===== My Department tab: the department(s) this HOD heads (department=MY), for the
        // quarter picked; the Department / Section / Plant filters don't apply =====
        var myDepartmentCount = <?php echo count($myDepartments); ?>;

        function myDepartmentParams() {
            return { department: 'MY', section: 'ALL', plant: 'ALL', period: skillMatrixPeriod, include_off: '0' };
        }

        function renderMyDepartment(rows) {
            rows = rows || [];
            var counts = emptyStatusCounts();
            var below50 = 0;
            rows.forEach(function (r) {
                if (counts.hasOwnProperty(r.approval_status_raw)) {
                    counts[r.approval_status_raw]++;
                }
                if (r.overall_score !== null && r.overall_score !== undefined && parseFloat(r.overall_score) < 50) {
                    below50++;
                }
            });

            $('#mydept-kpi-total').text(rows.length);
            $('#mydept-kpi-completion').text(rows.length > 0 ? Math.round((counts['APPROVED'] / rows.length) * 100) + '%' : '0%');
            $('#mydept-kpi-approved').text(counts['APPROVED']);
            $('#mydept-kpi-waiting').text(counts['WAITING APPROVAL']);
            $('#mydept-kpi-draft').text(counts['DRAFT']);
            $('#mydept-kpi-notsubmitted').text(counts['NOT SUBMITTED']);
            $('#mydept-kpi-below50').text(below50);

            $('.mydept-tile').each(function () {
                $(this).attr('href', 'skill-matrix-breakdown.php?' + $.param($.extend({ tile: $(this).data('breakdown') }, myDepartmentParams())));
            });

            // One department: the charts break it down by section.
            renderSkillMatrixCharts(rows, myDepartmentCount > 1, 'mydept');
        }

        var myDepartmentColumns = [
            { "data": null },
            { "data": "staffno" },
            { "data": "staffname" }
        ];
        if (myDepartmentCount > 1) {
            myDepartmentColumns.push({ "data": "department" });
        }
        myDepartmentColumns.push(
            { "data": "section" },
            { "data": "plant" },
            { "data": "grade" },
            { "data": "approval_status" },
            {
                // Same as the Staff List: approved matrices only, sorted by the number.
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
            { "data": "action" }
        );
        var myDepartmentLast = myDepartmentColumns.length - 1;

        var myDepartmentTable = $('#mydeptlist').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "autoWidth": false,
            "pageLength": 10,
            "info": true,
            "order": [[2, 'asc']],
            "ajax": {
                url: "fetch_skill_matrix.php",
                type: "POST",
                dataSrc: '',
                data: function (data) {
                    $.extend(data, myDepartmentParams(), { action: "load_non_executive_staff" });
                }
            },
            "columns": myDepartmentColumns,
            "columnDefs": [{
                className: 'text-center',
                targets: [0, 1, myDepartmentLast - 3, myDepartmentLast - 2, myDepartmentLast - 1, myDepartmentLast]
            },
            {
                orderable: false,
                searchable: false,
                targets: [0, myDepartmentLast]
            }],
            "drawCallback": function () {
                var api = this.api();
                var pageInfo = api.page.info();
                api.column(0, { page: 'current' }).nodes().each(function (cell, index) {
                    cell.innerHTML = pageInfo.start + index + 1;
                });
            },
            "language": {
                "emptyTable": "No non executive staff records found."
            }
        });

        myDepartmentTable.on('xhr', function (e, settings, json) {
            renderMyDepartment(json);
            $.post("fetch_skill_matrix.php", { action: "load_quarter_scores", department: "MY" }, function (quarters) {
                renderSkillMatrixQuarterChart(quarters, 'mydept');
            }, 'json');
        });

        $('#mydept_export_btn').click(function () {
            window.location.href = 'export_skill_matrix_list.php?' + $.param(myDepartmentParams());
        });

        // ===== Matrix Chart: the matrices submitted to this HOD, for the quarter picked =====
        function updateHodMatrixChartButton() {
            $('#hod_matrix_chart_btn').attr('href', 'matrix-chart.php' + (skillMatrixPeriod == 'previous' ? '?period=previous' : ''));
        }
        updateHodMatrixChartButton();

        // ===== Approve selected / all pending: matrices submitted to this HOD only =====
        // bulk_approve_skill_matrix.php applies the same scope, so a tampered id can't
        // approve anything else.
        var approveAllIds = [];

        // Every matrix waiting for this HOD in the quarter, whatever the filter shows.
        // Those for staff the list doesn't cover (e.g. executives) are listed above
        // the table so they can still be opened.
        function loadPendingApprovals() {
            $.post("fetch_skill_matrix.php", {
                action: "load_pending_approvals",
                period: skillMatrixPeriod
            }, function (pending) {
                pending = pending || [];
                approveAllIds = pending.map(function (p) { return p.id; });
                $('#approve_all_count').text(approveAllIds.length);
                $('#approve_all_btn').prop('disabled', approveAllIds.length === 0);

                var outside = pending.filter(function (p) { return p.outside_list; });
                var html = '';
                if (outside.length > 0) {
                    html = '<strong>' + outside.length + ' skill matri' + (outside.length == 1 ? 'x is' : 'ces are') +
                        ' waiting for your approval but not in the list below</strong> (it covers non executive and contract staff only):' +
                        '<ul style="margin:6px 0 0;">';
                    outside.forEach(function (p) {
                        html += '<li style="margin-bottom:4px;">' + $('<div>').text(p.staffname + ' (' + p.staffno + ') - ' + p.department).html() +
                            ' <a href="' + $('<div>').text(p.view_url).html() + '" class="btn btn-info btn-xs"><i class="fa fa-search"></i> VIEW</a></li>';
                    });
                    html += '</ul>';
                }
                $('#pending_outside_list').html(html).toggle(outside.length > 0);
            }, 'json');
        }

        // Ticks live on every page of the table, so they are read through the
        // DataTables API rather than the visible DOM. Only rows matching the
        // current search count, so a hidden row is never approved by accident.
        function approveChecks(onlyTicked) {
            var boxes = $(skillMatrixTable.rows({ search: 'applied' }).nodes()).find('.approve-check');
            return onlyTicked ? boxes.filter(':checked') : boxes;
        }

        function refreshApproveSelected() {
            var all = approveChecks(false);
            var ticked = approveChecks(true);
            $('#approve_selected_count').text(ticked.length);
            $('#approve_selected_btn').prop('disabled', ticked.length === 0);
            $('#approve_select_all')
                .prop('disabled', all.length === 0)
                .prop('checked', all.length > 0 && ticked.length === all.length)
                .prop('indeterminate', ticked.length > 0 && ticked.length < all.length);
        }

        $('#approve_select_all').on('change', function () {
            approveChecks(false).prop('checked', this.checked);
            refreshApproveSelected();
        });

        $('#skillmatrixlist').on('change', '.approve-check', refreshApproveSelected);
        skillMatrixTable.on('draw', refreshApproveSelected);

        function approveEvaluations(ids, title, text, confirmLabel, button) {
            swal({
                title: title,
                text: text,
                icon: "warning",
                buttons: ["Cancel", confirmLabel],
                dangerMode: true
            }).then(function (willApprove) {
                if (!willApprove) {
                    return;
                }

                button.prop('disabled', true);

                $.ajax({
                    url: "bulk_approve_skill_matrix.php",
                    method: "POST",
                    data: { evaluation_ids: ids },
                    dataType: "json",
                    success: function (response) {
                        if (response.message === 'ok') {
                            swal({
                                title: "Approved!",
                                text: response.approved + " skill matrix evaluation(s) approved.",
                                icon: "success",
                                timer: 2500,
                                buttons: false
                            });
                            // Reloads the list, dashboard and pending count with the same filter.
                            skillMatrixTable.ajax.reload();
                            myDepartmentTable.ajax.reload();
                        } else {
                            swal("Failed", response.error || "Unable to approve the selected records.", "error");
                            button.prop('disabled', false);
                        }
                    },
                    error: function () {
                        swal("Failed", "The server could not process the request. Please refer to IT.", "error");
                        button.prop('disabled', false);
                    }
                });
            });
        }

        $('#approve_selected_btn').on('click', function () {
            var ids = approveChecks(true).map(function () { return this.value; }).get();
            if (ids.length === 0) {
                return;
            }
            approveEvaluations(ids,
                "Approve " + ids.length + " selected skill matrix evaluation(s)?",
                "This cannot be undone from here - the ticked evaluations will be marked APPROVED.",
                "Approve Selected", $(this));
        });

        $('#approve_all_btn').on('click', function () {
            if (approveAllIds.length === 0) {
                return;
            }
            approveEvaluations(approveAllIds,
                "Approve all " + approveAllIds.length + " pending skill matrix evaluation(s)?",
                "This cannot be undone from here - every evaluation waiting for your approval in " +
                $('#period_tabs li.active a').text() + " will be marked APPROVED.",
                "Approve All", $(this));
        });

    </script>

    </html>
    <?php
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>
