<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";

include_once __DIR__ . '/../../../skill_matrix_department.php';

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
    $hodId = (int) $_SESSION['id'];
    list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
    // ?period=previous shows the quarter before the evaluation quarter instead.
    $viewingPrevious = isset($_GET['period']) && $_GET['period'] == 'previous';
    $fillYear = $currentYear;
    $fillQuarter = $currentQuarter;
    list($previousYear, $previousQuarter) = skillMatrixPreviousPeriod($fillYear, $fillQuarter);
    if ($viewingPrevious) {
        $currentYear = $previousYear;
        $currentQuarter = $previousQuarter;
    }
    $periodQuery = $viewingPrevious ? 'period=previous' : '';
    $records = array();

    $stmt = $conn->prepare("SELECT
                                sme.id,
                                sme.evaluation_date,
                                sme.approval_status,
                                target.id AS target_id,
                                target.staffno AS target_staffno,
                                target.staffname AS target_staffname,
                                target.department AS target_department,
                                target.section AS target_section,
                                creator.staffname AS created_by_name
                            FROM skill_matrix_evaluations sme
                            INNER JOIN user target ON target.id = sme.staffid
                            INNER JOIN user creator ON creator.id = sme.created_by
                            WHERE creator.hodid = ?
                            AND (
                                (
                                    creator.designation = ?
                                    AND (
                                        (creator.roletype = '' AND creator.usertype = '')
                                        OR (creator.roletype = 'CLERK' AND creator.usertype = 'MAIN')
                                    )
                                )
                                OR EXISTS (SELECT 1 FROM skill_matrix_whitelist w WHERE w.staffno = creator.staffno COLLATE utf8mb4_0900_ai_ci)
                            )
                            AND sme.approval_status IS NOT NULL
                            AND sme.eval_year = ?
                            AND sme.eval_quarter = ?
                            ORDER BY FIELD(sme.approval_status, 'PENDING', 'APPROVED'), sme.evaluation_date DESC, target.staffname");
    $creatorDesignation = "MANAGER (AM/HOS & ABOVE)";
    $stmt->bind_param("isii", $hodId, $creatorDesignation, $currentYear, $currentQuarter);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    // The page shows ONE list: every non-executive / contract staff in the
    // department(s) this HOD heads with their status this quarter, plus any
    // matrix in $records (submitted to this HOD for approval) whose staff is
    // outside those departments - creators reporting to this HOD can fill in
    // staff from other departments, and those still need this HOD's approval.
    // The tiles (Total Staff / Completion Rate / Approved / Waiting Approval /
    // Draft / Not Submitted) count the same list. $records alone only covers
    // submitted evaluations, so it has no way to know about staff with a
    // draft or no evaluation at all this quarter. This mirrors the same status-derivation
    // logic as admin/skill-matrix/fetch_skill_matrix.php's
    // load_non_executive_staff (APPROVED / PENDING / has-eval-but-no-status
    // = DRAFT / no-eval-at-all = NOT SUBMITTED), but scoped to the
    // department(s) this HOD actually heads rather than resolved from a
    // session var, since this page is reached by the HOD directly.
    $hodDepartments = array();
    $deptNameStmt = $conn->prepare("SELECT name FROM departments WHERE hod_user_id = ?");
    $deptNameStmt->bind_param("i", $hodId);
    $deptNameStmt->execute();
    $deptNameResult = $deptNameStmt->get_result();
    while ($deptRow = $deptNameResult->fetch_assoc()) {
        $hodDepartments[] = $deptRow['name'];
    }

    $overview = array('total' => 0, 'approved' => 0, 'waiting' => 0, 'draft' => 0, 'not_submitted' => 0);

    // One row per staff (keyed by user id while it's built) with their status
    // this quarter, listed under the tiles so the HOD can see who is behind
    // each count.
    $staffStatus = array();

    // evaluation-matrix.php only opens evaluations in this HOD's approval
    // scope, so the staff list only links to those.
    $viewableIds = array();
    // Of those, the ones still PENDING can be approved here in bulk -
    // bulk_approve_skill_matrix.php applies the same scope rule.
    $approvableIds = array();
    foreach ($records as $record) {
        $viewableIds[(int) $record['id']] = true;
        if ($record['approval_status'] == 'PENDING') {
            $approvableIds[(int) $record['id']] = true;
        }
    }

    if (count($hodDepartments) > 0) {
        $placeholders = implode(',', array_fill(0, count($hodDepartments), '?'));
        $overviewSql = "SELECT
                            u.id AS staff_id,
                            u.staffno,
                            u.staffname,
                            COALESCE(dp.name, u.department) AS department,
                            COALESCE(s.name, u.section) AS section,
                            sme.id AS evaluation_id,
                            sme.approval_status,
                            creator.staffname AS created_by_name
                        FROM user u
                        LEFT JOIN departments dp ON u.department_id = dp.id
                        LEFT JOIN sections s ON u.section_id = s.id
                        LEFT JOIN skill_matrix_evaluations sme ON sme.id = (
                            SELECT latest.id
                            FROM skill_matrix_evaluations latest
                            WHERE latest.staffid = u.id
                            AND latest.eval_year = ?
                            AND latest.eval_quarter = ?
                            ORDER BY latest.evaluation_date DESC, latest.id DESC
                            LIMIT 1
                        )
                        LEFT JOIN user creator ON creator.id = sme.created_by
                        WHERE u.designation IN ('NON EXECUTIVE', 'CONTRACT')
                        AND u.status != 'RESIGN'
                        AND (dp.name IN ($placeholders) OR u.department IN ($placeholders))";
        // Two separate IN() clauses rather than COALESCE(dp.name, u.department)
        // IN (...) - dp.name and u.department carry different collations in
        // this schema, and COALESCE-ing them together throws "Illegal mix of
        // collations". Comparing each column against the bound params
        // separately (same pattern as admin/skill-matrix/fetch_skill_matrix.php)
        // avoids that, so each department name is bound twice, once per side.
        $overviewTypes = 'ii' . str_repeat('s', count($hodDepartments) * 2);
        $overviewParams = array_merge([$currentYear, $currentQuarter], $hodDepartments, $hodDepartments);
        $overviewStmt = $conn->prepare($overviewSql);
        $overviewStmt->bind_param($overviewTypes, ...$overviewParams);
        $overviewStmt->execute();
        $overviewResult = $overviewStmt->get_result();

        while ($orow = $overviewResult->fetch_assoc()) {
            $staffStatus[(int) $orow['staff_id']] = $orow;
        }
    }

    // Submitted matrices for staff outside the HOD's departments join the
    // list; for staff already on it, make sure VIEW opens the matrix that
    // was submitted to this HOD.
    foreach ($records as $record) {
        $targetId = (int) $record['target_id'];
        if (isset($staffStatus[$targetId])) {
            if (!isset($viewableIds[(int) $staffStatus[$targetId]['evaluation_id']])) {
                $staffStatus[$targetId]['evaluation_id'] = $record['id'];
                $staffStatus[$targetId]['approval_status'] = $record['approval_status'];
                $staffStatus[$targetId]['created_by_name'] = $record['created_by_name'];
            }
        } else {
            $staffStatus[$targetId] = array(
                'staff_id' => $targetId,
                'staffno' => $record['target_staffno'],
                'staffname' => $record['target_staffname'],
                'department' => $record['target_department'],
                'section' => $record['target_section'],
                'evaluation_id' => $record['id'],
                'approval_status' => $record['approval_status'],
                'created_by_name' => $record['created_by_name'],
            );
        }
    }

    foreach ($staffStatus as $staffId => $orow) {
        $overview['total']++;
        if ($orow['approval_status'] == 'APPROVED') {
            $overview['approved']++;
            $staffStatus[$staffId]['status'] = 'APPROVED';
        } else if ($orow['approval_status'] == 'PENDING') {
            $overview['waiting']++;
            $staffStatus[$staffId]['status'] = 'WAITING APPROVAL';
        } else if ($orow['evaluation_id'] !== null) {
            $overview['draft']++;
            $staffStatus[$staffId]['status'] = 'DRAFT';
        } else {
            $overview['not_submitted']++;
            $staffStatus[$staffId]['status'] = 'NOT SUBMITTED';
        }
    }

    // What the HOD has to act on comes first (approve), then the staff they
    // still have to chase.
    $statusOrder = array('WAITING APPROVAL' => 0, 'NOT SUBMITTED' => 1, 'DRAFT' => 2, 'APPROVED' => 3);
    usort($staffStatus, function ($a, $b) use ($statusOrder) {
        if ($statusOrder[$a['status']] != $statusOrder[$b['status']]) {
            return $statusOrder[$a['status']] - $statusOrder[$b['status']];
        }
        return strcasecmp($a['staffname'], $b['staffname']);
    });

    $statusLabelClass = array(
        'APPROVED' => 'label-success',
        'WAITING APPROVAL' => 'label-warning',
        'DRAFT' => 'label-draft',
        'NOT SUBMITTED' => 'label-danger'
    );

    $overviewCompletionRate = $overview['total'] > 0 ? round(($overview['approved'] / $overview['total']) * 100) : 0;
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
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
    <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
    <style>
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

        .dash-tile[data-status] {
            cursor: pointer;
        }

        .dash-tile.active {
            background: #eef4fb;
            border-color: #337ab7;
        }

        .label-draft {
            background-color: var(--status-serious);
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
            <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;"></div>
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
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $_SESSION['fullname']; ?></a>
                        <ul class="dropdown-menu">
                            <li><a href="../../../logout.php">LOGOUT</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="alert alert-info">
            <strong>Evaluation Quarter: Q<?php echo $fillQuarter; ?> <?php echo $fillYear; ?></strong>
            (<?php echo skillMatrixQuarterMonths($fillQuarter); ?> <?php echo $fillYear; ?>)
        </div>

        <ul class="nav nav-tabs" style="margin-bottom: 15px;">
            <li<?php echo $viewingPrevious ? '' : ' class="active"'; ?>><a href="skill-matrix.php">Evaluation Quarter (Q<?php echo $fillQuarter; ?> <?php echo $fillYear; ?>)</a></li>
            <li<?php echo $viewingPrevious ? ' class="active"' : ''; ?>><a href="skill-matrix.php?period=previous">Previous Quarter (Q<?php echo $previousQuarter; ?> <?php echo $previousYear; ?>)</a></li>
        </ul>

        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-4" style="margin-top: 10px;">
                        <strong>Skill Matrix - Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?></strong>
                    </div>
                    <div class="col-md-8" align="right">
                        <a href="matrix-chart.php<?php echo $viewingPrevious ? '?' . $periodQuery : ''; ?>" class="btn btn-info btn-md">
                            <i class="fa fa-chart-bar"></i> Matrix Chart
                        </a>
                        <a href="export_skill_matrix_list.php<?php echo $viewingPrevious ? '?' . $periodQuery : ''; ?>" class="btn btn-success btn-md">
                            <i class="fa fa-file-excel"></i> Export Excel
                        </a>
                        <button type="button" id="approve_selected_btn" class="btn btn-primary btn-md" disabled>
                            <i class="fa fa-check"></i> Approve Selected (<span id="approve_selected_count">0</span>)
                        </button>
                        <button type="button" id="approve_all_btn" class="btn btn-warning btn-md" <?php echo count($approvableIds) > 0 ? '' : 'disabled'; ?>>
                            <i class="fa fa-check-double"></i> Approve All Pending (<?php echo count($approvableIds); ?>)
                        </button>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-tile active" data-status="">
                            <div class="dash-tile-value"><?php echo $overview['total']; ?></div>
                            <div class="dash-tile-label">Total Staff</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-tile" style="border-left-color: var(--status-good);">
                            <div class="dash-tile-value"><?php echo $overviewCompletionRate; ?>%</div>
                            <div class="dash-tile-label">Completion Rate</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-tile" data-status="APPROVED" style="border-left-color: var(--status-good);">
                            <div class="dash-tile-value"><?php echo $overview['approved']; ?></div>
                            <div class="dash-tile-label">Approved</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-tile" data-status="WAITING APPROVAL" style="border-left-color: var(--status-warning);">
                            <div class="dash-tile-value"><?php echo $overview['waiting']; ?></div>
                            <div class="dash-tile-label">Waiting Approval</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-tile" data-status="DRAFT" style="border-left-color: var(--status-serious);">
                            <div class="dash-tile-value"><?php echo $overview['draft']; ?></div>
                            <div class="dash-tile-label">Draft</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-tile" data-status="NOT SUBMITTED" style="border-left-color: var(--status-critical);">
                            <div class="dash-tile-value"><?php echo $overview['not_submitted']; ?></div>
                            <div class="dash-tile-label">Not Submitted</div>
                        </div>
                    </div>
                </div>

                <?php if (count($staffStatus) == 0) { ?>
                    <div class="alert alert-info">No staff found in your department, and no skill matrix has been submitted to you for Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?>.</div>
                <?php } else { ?>
                    <p class="text-muted">Click a tile above to filter the list by status.</p>
                    <div class="table-responsive">
                        <table id="staffStatusTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center">
                                        <input type="checkbox" id="approve_select_all" title="Select all waiting approval" <?php echo count($approvableIds) > 0 ? '' : 'disabled'; ?>>
                                    </th>
                                    <th>No.</th>
                                    <th>Staff No.</th>
                                    <th>Staff Name</th>
                                    <th>Department</th>
                                    <th>Section</th>
                                    <th>Filled By</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffStatus as $index => $staff) { ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if ($staff['status'] == 'WAITING APPROVAL' && isset($approvableIds[(int) $staff['evaluation_id']])) { ?>
                                                <input type="checkbox" class="approve-check" value="<?php echo (int) $staff['evaluation_id']; ?>" title="Select to approve">
                                            <?php } ?>
                                        </td>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($staff['staffno']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['staffname']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $staff['department']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $staff['section']); ?></td>
                                        <td><?php echo $staff['created_by_name'] !== null ? htmlspecialchars($staff['created_by_name']) : '-'; ?></td>
                                        <?php // Kept on one line: the tile filter matches this cell's text exactly. ?>
                                        <td class="text-center"><span class="label <?php echo $statusLabelClass[$staff['status']]; ?>"><?php echo $staff['status']; ?></span></td>
                                        <td class="text-center">
                                            <?php if ($staff['evaluation_id'] !== null && isset($viewableIds[(int) $staff['evaluation_id']])) { ?>
                                                <a href="evaluation-matrix.php?evaluation_id=<?php echo (int) $staff['evaluation_id']; ?><?php echo $viewingPrevious ? '&amp;' . $periodQuery : ''; ?>" class="btn btn-info btn-sm">
                                                    <i class="fa fa-search"></i> VIEW
                                                </a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
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

    $(document).ready(function () {
        // "order": [] keeps the server order (waiting approval first).
        var staffStatusTable = $('#staffStatusTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "pageLength": 10,
            "order": [],
            "columnDefs": [
                { className: 'text-center', targets: [0, 1, 7, 8] },
                { orderable: false, searchable: false, targets: [0] },
                { orderable: false, targets: [8] }
            ]
        });

        $('.dash-tile[data-status]').on('click', function () {
            var status = $(this).data('status');
            $('.dash-tile[data-status]').removeClass('active');
            $(this).addClass('active');
            staffStatusTable.column(7).search(status ? '^' + status + '$' : '', true, false).draw();
        });

        // ===== Approve selected / all pending =====
        // Ticks live on every page of the table, so they are read through the
        // DataTables API rather than the visible DOM. Only rows matching the
        // current search / tile filter count, so a hidden row is never
        // approved by accident.
        var approveAllIds = <?php echo json_encode(array_keys($approvableIds)); ?>;

        function approveChecks(onlyTicked) {
            var boxes = $(staffStatusTable.rows({ search: 'applied' }).nodes()).find('.approve-check');
            return onlyTicked ? boxes.filter(':checked') : boxes;
        }

        function refreshApproveSelected() {
            var all = approveChecks(false);
            var ticked = approveChecks(true);
            $('#approve_selected_count').text(ticked.length);
            $('#approve_selected_btn').prop('disabled', ticked.length === 0);
            $('#approve_select_all')
                .prop('checked', all.length > 0 && ticked.length === all.length)
                .prop('indeterminate', ticked.length > 0 && ticked.length < all.length);
        }

        $('#approve_select_all').on('change', function () {
            approveChecks(false).prop('checked', this.checked);
            refreshApproveSelected();
        });

        $('#staffStatusTable').on('change', '.approve-check', refreshApproveSelected);
        staffStatusTable.on('draw', refreshApproveSelected);

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
                            }).then(function () {
                                window.location.reload();
                            });
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
                "This cannot be undone from here - every evaluation waiting for your approval in Q<?php echo $currentQuarter; ?> <?php echo $currentYear; ?> will be marked APPROVED.",
                "Approve All", $(this));
        });
    });
</script>
</html>
<?php
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>
