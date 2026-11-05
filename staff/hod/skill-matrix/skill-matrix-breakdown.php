<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
list($previousYear, $previousQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);

include_once __DIR__ . '/../../../skill_matrix_department.php';

// HOD copy of admin/skill-matrix/skill-matrix-breakdown.php: the same view of every department.
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

// Breakdown behind one overview tile on skill-matrix.php: the tile's count per
// department (per section when a department is filtered), the staff behind each
// count, and an Excel download of each group's name list.
$tileTitles = array(
    'total' => 'Total Staff',
    'completion' => 'Completion Rate',
    'approved' => 'Approved',
    'waiting' => 'Waiting Approval',
    'draft' => 'Draft',
    'notsubmitted' => 'Not Submitted',
    'below50' => 'Score Below 50%',
);
$tile = isset($_GET['tile'], $tileTitles[$_GET['tile']]) ? $_GET['tile'] : 'total';

// Same filter the dashboard was showing when the tile was clicked.
$filter = array(
    'department' => isset($_GET['department']) && $_GET['department'] !== '' ? $_GET['department'] : 'ALL',
    'section' => isset($_GET['section']) && $_GET['section'] !== '' ? $_GET['section'] : 'ALL',
    'plant' => isset($_GET['plant']) && $_GET['plant'] !== '' ? $_GET['plant'] : 'ALL',
    'period' => isset($_GET['period']) && $_GET['period'] == 'previous' ? 'previous' : 'current',
    'include_off' => isset($_GET['include_off']) && $_GET['include_off'] === '1' ? '1' : '0',
);

// department=MY comes from the My Department tab: the department(s) this HOD heads,
// grouped by department only when there is more than one.
$myDepartments = array();
if ($filter['department'] == 'MY' && isset($_SESSION['id'])) {
    $myDepartmentStmt = $conn->prepare("SELECT name FROM departments WHERE hod_user_id = ? ORDER BY name");
    $hodId = (int) $_SESSION['id'];
    $myDepartmentStmt->bind_param("i", $hodId);
    $myDepartmentStmt->execute();
    $myDepartmentResult = $myDepartmentStmt->get_result();
    while ($myDepartmentRow = $myDepartmentResult->fetch_assoc()) {
        $myDepartments[] = $myDepartmentRow['name'];
    }
}
$departmentLabel = $filter['department'] == 'MY' ? 'My Department (' . implode(', ', $myDepartments) . ')' : $filter['department'];

$byDepartment = $filter['department'] == 'ALL' || $filter['department'] == ''
    || ($filter['department'] == 'MY' && count($myDepartments) > 1);
$groupLabel = $byDepartment ? 'Department' : 'Section';

if ($filter['period'] == 'previous') {
    $periodLabel = 'Previous Quarter (Q' . $previousQuarter . ' ' . $previousYear . ')';
} else {
    $periodLabel = 'Evaluation Quarter (Q' . $currentQuarter . ' ' . $currentYear . ')';
}

// Columns of the breakdown table after "No." and the group name. percent columns
// show a rounded % and are filtered on that rounded figure.
$countTitle = $tileTitles[$tile];
if ($tile == 'total') {
    $columns = array(
        array('key' => 'total', 'title' => 'Total Staff', 'strong' => true),
        array('key' => 'APPROVED', 'title' => 'Approved'),
        array('key' => 'WAITING APPROVAL', 'title' => 'Waiting Approval'),
        array('key' => 'DRAFT', 'title' => 'Draft'),
        array('key' => 'NOT SUBMITTED', 'title' => 'Not Submitted'),
        array('key' => 'rate', 'title' => 'Completion', 'percent' => true),
    );
} else if ($tile == 'completion') {
    $columns = array(
        array('key' => 'APPROVED', 'title' => 'Approved'),
        array('key' => 'total', 'title' => 'Total Staff'),
        array('key' => 'rate', 'title' => 'Completion', 'percent' => true, 'strong' => true),
        array('key' => 'outstanding', 'title' => 'Not Approved Yet'),
    );
} else {
    $columns = array(
        array('key' => 'count', 'title' => $countTitle, 'strong' => true),
        array('key' => 'total', 'title' => 'Total Staff'),
        array('key' => 'share', 'title' => '% of ' . $groupLabel, 'percent' => true),
    );
}

$downloadHint = array(
    'total' => 'all staff',
    'completion' => 'the staff not approved yet',
    'approved' => 'the approved staff',
    'waiting' => 'the staff waiting approval',
    'draft' => 'the staff with a draft',
    'notsubmitted' => 'the staff not submitted',
    'below50' => 'the staff scoring below 50%',
);

function breakdownUrl($tile, $filter)
{
    return 'skill-matrix-breakdown.php?' . http_build_query(array_merge(array('tile' => $tile), $filter));
}

if (isset($_SESSION['fullname']) && canApproveSkillMatrix()) {

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Learning and Development Management System</title>
        <script src="../../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../../asset/css/bootstrap.min.css" />
        <script src="../../../asset/js/bootstrap.min.js"></script>
        <script src="../../../asset/js/skill-matrix-dashboard.js?v=<?php echo filemtime(__DIR__ . "/../../../asset/js/skill-matrix-dashboard.js"); ?>"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/dt-1.10.18/datatables.min.css" />
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.10.18/datatables.min.js"></script>
        <style>
            #breakdown_table {
                width: 100% !important;
            }

            .breakdown-filter-bar {
                margin-bottom: 12px;
            }

            .breakdown-filter-bar label {
                margin: 0 6px 0 0;
            }

            .breakdown-filter-bar select {
                min-width: 260px;
                margin-right: 16px;
            }

            #breakdown_table tbody tr.breakdown-group-row {
                cursor: pointer;
            }

            #breakdown_table tbody tr.breakdown-group-row:hover > td {
                background: #eef4fc;
            }

            #breakdown_table .breakdown-toggle {
                color: #2a78d6;
                width: 14px;
            }

            #breakdown_table tfoot th {
                background: #f5f5f5;
            }

            #breakdown_table tr.breakdown-staff-row > td {
                background: #f7f7f5;
                padding: 4px 10px 12px 34px;
            }

            /* A big group's staff list scrolls on its own, so the other rows stay in reach. */
            .breakdown-staff-list {
                max-height: 420px;
                overflow-y: auto;
            }

            .breakdown-staff-list table {
                margin: 6px 0 0;
                background: #fff;
            }

            .breakdown-tiles > li > a {
                padding: 6px 12px;
            }

            .breakdown-filter-summary .label {
                font-size: 12px;
                font-weight: normal;
                margin-right: 4px;
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

            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-7" style="margin-top: 7px;">
                            <a href="skill-matrix.php">Skill Matrix</a> <i class="fa fa-angle-right"></i>
                            <strong><?php echo htmlspecialchars($tileTitles[$tile] . ' by ' . $groupLabel); ?></strong>
                        </div>
                        <div class="col-md-5" align="right">
                            <a href="skill-matrix.php" class="btn btn-default btn-md"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
                            <a href="#" id="download_all" class="btn btn-success btn-md" title="Excel list of <?php echo $downloadHint[$tile]; ?> in every <?php echo strtolower($groupLabel); ?> below">
                                <i class="fa fa-file-excel"></i> Download All
                            </a>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="breakdown-filter-summary">
                        <span class="label label-primary"><?php echo htmlspecialchars($periodLabel); ?></span>
                        <span class="label label-default">Department: <?php echo htmlspecialchars($departmentLabel); ?></span>
                        <span class="label label-default">Section: <?php echo htmlspecialchars($filter['section']); ?></span>
                        <span class="label label-default">Plant: <?php echo htmlspecialchars($filter['plant']); ?></span>
                        <?php if ($filter['include_off'] === '1') { ?>
                            <span class="label label-warning">Including switched-off departments</span>
                        <?php } ?>
                    </div>
                    <ul class="nav nav-pills breakdown-tiles" style="margin-top: 12px;">
                        <?php foreach ($tileTitles as $key => $title) { ?>
                            <li class="<?php echo $key == $tile ? 'active' : ''; ?>">
                                <a href="<?php echo htmlspecialchars(breakdownUrl($key, $filter)); ?>"><?php echo htmlspecialchars($title); ?></a>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-body">
                    <p id="breakdown_summary" class="text-muted">Loading&hellip;</p>
                    <div class="form-inline breakdown-filter-bar">
                        <label for="filter_group"><?php echo $groupLabel; ?></label>
                        <select id="filter_group" class="form-control input-sm">
                            <option value="">All</option>
                        </select>
                        <?php if ($byDepartment) { ?>
                            <label for="filter_section">Section</label>
                            <select id="filter_section" class="form-control input-sm">
                                <option value="">All</option>
                            </select>
                        <?php } ?>
                        <a href="#" id="clear_filters">Clear filters</a>
                    </div>
                    <div class="table-responsive">
                        <table id="breakdown_table" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:50px;">No.</th>
                                    <th><?php echo $groupLabel; ?></th>
                                    <?php foreach ($columns as $column) { ?>
                                        <th class="text-center"><?php echo htmlspecialchars($column['title']); ?></th>
                                    <?php } ?>
                                    <th class="text-center" style="width:110px;">Name List</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <th></th>
                                    <th>Total</th>
                                    <?php foreach ($columns as $column) { ?>
                                        <th class="text-center"></th>
                                    <?php } ?>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="text-muted" style="font-size:12px;margin-top:8px;">
                        Click a <?php echo strtolower($groupLabel); ?> to see its staff. <strong>Download</strong> gives an Excel list of <?php echo $downloadHint[$tile]; ?> in that <?php echo strtolower($groupLabel); ?>.
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

        var BREAKDOWN = <?php echo json_encode(array(
            'tile' => $tile,
            'title' => $tileTitles[$tile],
            'filter' => $filter,
            'byDepartment' => $byDepartment,
            'groupLabel' => $groupLabel,
            'columns' => $columns,
        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function escapeHtml(text) {
            return $('<div>').text(text === null || text === undefined ? '' : text).html();
        }

        function hasStatus(status) {
            return function (r) { return r.approval_status_raw == status; };
        }

        function isBelow50(r) {
            return r.overall_score !== null && r.overall_score !== undefined && parseFloat(r.overall_score) < 50;
        }

        // count: the staff the tile counts. list: the staff listed and downloaded for a
        // group, if not the counted ones (Completion Rate lists who is still outstanding).
        var TILE_BREAKDOWNS = {
            total: { count: function () { return true; }, showStatus: true },
            completion: { count: hasStatus('APPROVED'), list: function (r) { return r.approval_status_raw != 'APPROVED'; }, showStatus: true },
            approved: { count: hasStatus('APPROVED'), showScore: true },
            waiting: { count: hasStatus('WAITING APPROVAL') },
            draft: { count: hasStatus('DRAFT') },
            notsubmitted: { count: hasStatus('NOT SUBMITTED') },
            below50: { count: isBelow50, showScore: true }
        };
        var tile = TILE_BREAKDOWNS[BREAKDOWN.tile];
        var groupWord = BREAKDOWN.groupLabel.toLowerCase();

        // Excel name list from export_skill_matrix_list.php: one group, or every group when groupName is omitted.
        function downloadUrl(groupName) {
            var params = {
                department: BREAKDOWN.filter.department,
                section: BREAKDOWN.filter.section,
                plant: BREAKDOWN.filter.plant,
                period: BREAKDOWN.filter.period,
                include_off: BREAKDOWN.filter.include_off
            };
            if (BREAKDOWN.tile != 'total') {
                params.tile = BREAKDOWN.tile;
            }
            if (groupName !== undefined) {
                params[BREAKDOWN.byDepartment ? 'group_department' : 'group_section'] = groupName;
            }
            if (sectionFilter) {
                params.group_section = sectionFilter;
            }
            return 'export_skill_matrix_list.php?' + $.param(params);
        }

        // Names as the dashboard shows them, with '(Unspecified)' for a blank.
        function displayName(value) {
            return (value || '').toString().trim() || '(Unspecified)';
        }

        function rowGroupName(r) {
            return displayName(BREAKDOWN.byDepartment ? r.department : r.section);
        }

        function buildGroups(rows) {
            var listFn = tile.list || tile.count;
            var groupsByName = {};
            rows.forEach(function (r) {
                var name = rowGroupName(r);
                if (!groupsByName[name]) {
                    groupsByName[name] = $.extend(emptyStatusCounts(), { name: name, count: 0, staff: [] });
                }
                var group = groupsByName[name];
                group.total++;
                if (group.hasOwnProperty(r.approval_status_raw)) {
                    group[r.approval_status_raw]++;
                }
                if (tile.count(r)) {
                    group.count++;
                }
                if (listFn(r)) {
                    group.staff.push(r);
                }
            });

            // Total and Completion Rate show every group; the others only groups with staff in that count.
            return $.map(groupsByName, function (group) {
                group.rate = group.total > 0 ? group['APPROVED'] / group.total * 100 : 0;
                group.share = group.total > 0 ? group.count / group.total * 100 : 0;
                group.outstanding = group.total - group['APPROVED'];
                return group;
            }).filter(function (group) {
                return BREAKDOWN.tile == 'total' || BREAKDOWN.tile == 'completion' || group.count > 0;
            });
        }

        // ===== Filters: the department (or section) the table is grouped by hides the
        // other rows; the section filter, when grouped by department, recounts every
        // department for that section's staff only =====
        var allRows = [];
        var groupFilter = '';
        var sectionFilter = '';
        var table = null;

        $.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex, group) {
            if (settings.nTable.id !== 'breakdown_table') {
                return true;
            }
            return !groupFilter || group.name === groupFilter;
        });

        function filteredRows() {
            if (!sectionFilter) {
                return allRows;
            }
            return allRows.filter(function (r) { return displayName(r.section) === sectionFilter; });
        }

        // Sections of the picked department, or of every department; a section the
        // new department doesn't have is dropped.
        function fillSectionOptions() {
            var $select = $('#filter_section');
            if (!$select.length) {
                return;
            }
            var sections = {};
            allRows.forEach(function (r) {
                if (!groupFilter || rowGroupName(r) === groupFilter) {
                    sections[displayName(r.section)] = true;
                }
            });
            var names = Object.keys(sections).sort();
            if (sectionFilter && names.indexOf(sectionFilter) < 0) {
                sectionFilter = '';
            }
            $select.find('option:not(:first)').remove();
            $.each(names, function (i, name) {
                $select.append($('<option>').val(name).text(name));
            });
            $select.val(sectionFilter);
        }

        function updateSummary(rows, groups) {
            var countedStaff = 0;
            var listedStaff = 0;
            groups.forEach(function (group) {
                countedStaff += group.count;
                listedStaff += group.staff.length;
            });

            var sectionNote = sectionFilter ? ' (section ' + escapeHtml(sectionFilter) + ')' : '';
            if (BREAKDOWN.tile == 'completion') {
                $('#breakdown_summary').html('<strong>' + countedStaff + '</strong> of <strong>' + rows.length + '</strong> staff' + sectionNote + ' approved (' +
                    (rows.length > 0 ? Math.round(countedStaff / rows.length * 100) : 0) + '%), <strong>' + listedStaff + '</strong> not approved yet. Lowest completion first.');
            } else {
                $('#breakdown_summary').html('<strong>' + countedStaff + '</strong> staff' + sectionNote + ' in <strong>' + groups.length + '</strong> ' +
                    groupWord + (groups.length == 1 ? '' : 's') + '.');
            }
            if (listedStaff === 0) {
                $('#download_all').addClass('disabled').attr('href', '#');
            } else {
                $('#download_all').removeClass('disabled').attr('href', downloadUrl());
            }
        }

        // Recount the groups for the section filter and redraw the table.
        function refreshBreakdown() {
            var rows = filteredRows();
            var groups = buildGroups(rows);
            updateSummary(rows, groups);
            table.clear().rows.add(groups).draw();
        }

        // ===== Staff behind one group, built the first time it is opened =====
        function staffTable(group) {
            if (group.staff.length === 0) {
                return '<div class="text-muted" style="padding-top:8px;">No staff to list.</div>';
            }
            var head = '<th class="text-center" style="width:40px;">No.</th><th class="text-center">Staff No.</th><th>Employee Name</th>' +
                (BREAKDOWN.byDepartment ? '<th>Section</th>' : '') + '<th>Plant</th><th class="text-center">Grade</th>' +
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
                    (BREAKDOWN.byDepartment ? '<td>' + escapeHtml(r.section) + '</td>' : '') +
                    '<td>' + escapeHtml(r.plant) + '</td>' +
                    '<td class="text-center">' + escapeHtml(r.grade) + '</td>';
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

        function renderBreakdown(rows) {
            allRows = rows;
            var groups = buildGroups(rows);
            updateSummary(rows, groups);

            var names = groups.map(function (group) { return group.name; }).sort();
            $.each(names, function (i, name) {
                $('#filter_group').append($('<option>').val(name).text(name));
            });
            fillSectionOptions();

            var columns = [
                { data: null, orderable: false, searchable: false, className: 'text-center', defaultContent: '' },
                {
                    data: 'name',
                    render: function (name, type) {
                        return type == 'display' ? '<i class="fa fa-chevron-right breakdown-toggle"></i> ' + escapeHtml(name) : name;
                    }
                }
            ];
            BREAKDOWN.columns.forEach(function (column) {
                columns.push({
                    data: function (group) { return group[column.key]; },
                    className: 'text-center',
                    type: 'num',
                    render: function (value, type) {
                        if (type != 'display') {
                            return value;
                        }
                        var text = column.percent ? Math.round(value) + '%' : value;
                        return column.strong ? '<strong>' + text + '</strong>' : text;
                    }
                });
            });
            columns.push({
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (value, type, group) {
                    if (group.staff.length === 0) {
                        return '<span class="text-muted" style="font-size:12px;">No staff</span>';
                    }
                    return '<a class="btn btn-success btn-xs" href="' + escapeHtml(downloadUrl(group.name)) + '">' +
                        '<i class="fa fa-file-excel"></i> Download</a>';
                }
            });

            // Completion Rate: lowest first. Total Staff: biggest first. Others: most staff in that count first.
            var keyIndex = function (key) {
                for (var i = 0; i < BREAKDOWN.columns.length; i++) {
                    if (BREAKDOWN.columns[i].key == key) {
                        return i + 2;
                    }
                }
                return 1;
            };
            var order;
            if (BREAKDOWN.tile == 'completion') {
                order = [[keyIndex('rate'), 'asc'], [1, 'asc']];
            } else if (BREAKDOWN.tile == 'total') {
                order = [[keyIndex('total'), 'desc'], [1, 'asc']];
            } else {
                order = [[keyIndex('count'), 'desc'], [1, 'asc']];
            }

            table = $('#breakdown_table').DataTable({
                data: groups,
                columns: columns,
                order: order,
                paging: false,
                searching: true,
                info: true,
                autoWidth: false,
                dom: 'rti',
                language: {
                    emptyTable: 'No staff for the current filter.',
                    zeroRecords: 'No ' + groupWord + ' matches the filters.'
                },
                infoCallback: function (settings, start, end, max, total) {
                    return 'Showing ' + total + ' ' + groupWord + (total == 1 ? '' : 's') +
                        (total != max ? ' (filtered from ' + max + ')' : '');
                },
                createdRow: function (row) {
                    $(row).addClass('breakdown-group-row');
                },
                drawCallback: function () {
                    var api = this.api();
                    api.column(0, { search: 'applied', order: 'applied' }).nodes().each(function (cell, index) {
                        cell.innerHTML = index + 1;
                    });
                },
                // Totals of the groups the filters leave; % columns are worked out from those totals.
                footerCallback: function () {
                    var api = this.api();
                    var shown = api.rows({ search: 'applied' }).data().toArray();
                    var sums = { total: 0, count: 0, APPROVED: 0 };
                    BREAKDOWN.columns.forEach(function (column, i) {
                        var cell = api.column(i + 2).footer();
                        if (column.percent) {
                            return;
                        }
                        var sum = 0;
                        shown.forEach(function (group) { sum += group[column.key]; });
                        $(cell).text(sum);
                    });
                    shown.forEach(function (group) {
                        sums.total += group.total;
                        sums.count += group.count;
                        sums.APPROVED += group['APPROVED'];
                    });
                    BREAKDOWN.columns.forEach(function (column, i) {
                        if (column.percent) {
                            var part = column.key == 'rate' ? sums.APPROVED : sums.count;
                            $(api.column(i + 2).footer()).text(sums.total > 0 ? Math.round(part / sums.total * 100) + '%' : '-');
                        }
                    });
                }
            });

            $('#filter_group').on('change', function () {
                groupFilter = $(this).val();
                var previousSection = sectionFilter;
                fillSectionOptions();
                if (sectionFilter !== previousSection) {
                    refreshBreakdown();
                } else {
                    table.draw();
                }
            });

            $('#filter_section').on('change', function () {
                sectionFilter = $(this).val();
                refreshBreakdown();
            });

            $('#clear_filters').on('click', function (e) {
                e.preventDefault();
                groupFilter = '';
                sectionFilter = '';
                $('#filter_group').val('');
                fillSectionOptions();
                refreshBreakdown();
            });

            $('#breakdown_table tbody').on('click', 'tr.breakdown-group-row', function (e) {
                if ($(e.target).closest('a, button').length) {
                    return;
                }
                var row = table.row(this);
                if (row.child.isShown()) {
                    row.child.hide();
                } else {
                    if (!row.child()) {
                        row.child(staffTable(row.data()), 'breakdown-staff-row');
                    }
                    row.child.show();
                }
                $(this).find('.breakdown-toggle').toggleClass('fa-chevron-right fa-chevron-down');
            });
        }

        $.post('fetch_skill_matrix.php', {
            action: 'load_non_executive_staff',
            period: BREAKDOWN.filter.period,
            department: BREAKDOWN.filter.department,
            section: BREAKDOWN.filter.section,
            plant: BREAKDOWN.filter.plant,
            include_off: BREAKDOWN.filter.include_off
        }, function (rows) {
            renderBreakdown(rows || []);
        }, 'json').fail(function () {
            $('#breakdown_summary').text('Could not load the staff list. Please refresh the page.');
        });
    </script>

    </html>
    <?php
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>
