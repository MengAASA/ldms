<?php
// One department's TNA progress: KPI tiles, the Own TNA table (one row per
// staff) and the Covered by Grade TNA table (one row per grade and section,
// same grade merged). Shared by the admin department page
// (admin/tna/tna_department.php) and the HOD's TNA page
// (staff/hod/tna/staff_list.php). Needs jQuery and DataTables (with the
// Buttons/HTML5 export extension) already loaded.
//
// Expects:
//   $split       tnaDashSplitByCoverage() result
//   $department  department name, for the export titles
//   $year        planning year
//   $viewButtons true adds a VIEW TNA column: .view buttons (id = user id) on
//                staff rows and .viewgrade buttons (id = "grade/department")
//                on grade rows; the page handles the clicks.

$statusLabel = [
    'APPROVED' => 'label-success',
    'WAITING APPROVAL' => 'label-warning',
    'NOT FILLED' => 'label-danger',
];
$counts = $split['counts'];
$total = array_sum($counts);
$filled = $counts['APPROVED'] + $counts['WAITING APPROVAL'];
$completion = tnaDashPercent($filled, $total);
$gradeCount = count($split['gradeStatus']);
$gradesFilled = count(array_diff($split['gradeStatus'], ['NOT FILLED']));
?>
<style>
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
        border-left: 4px solid #c3c2b7;
        border-radius: 4px;
        padding: 12px 15px;
        text-align: left;
    }

    .kpi[data-status] {
        cursor: pointer;
    }

    .kpi.active {
        background: #eef4fb;
        border-color: #337ab7;
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

    .kpi-good { border-left-color: #0ca30c; }
    .kpi-good i { color: #0ca30c; }
    .kpi-warning { border-left-color: #fab219; }
    .kpi-warning i { color: #c98500; }
    .kpi-critical { border-left-color: #d03b3b; }
    .kpi-critical i { color: #d03b3b; }

    .staff-group {
        margin-bottom: 25px;
        text-align: left;
    }

    .group-title {
        font-weight: bold;
        border-bottom: 2px solid #337ab7;
        padding-bottom: 6px;
    }

    .group-title small {
        font-weight: normal;
        color: #52514e;
        margin-left: 6px;
    }

    /* No horizontal scroll: the tables fit the panel and long text wraps. */
    .dept-staff-table {
        table-layout: fixed;
    }

    .dept-staff-table th,
    .dept-staff-table td {
        white-space: normal;
        word-wrap: break-word;
    }

    .dept-staff-table td.grade-cell,
    .dept-staff-table td.grade-no {
        font-weight: bold;
    }

    .dept-staff-table td.grade-cell,
    .dept-staff-table td.grade-no,
    .dept-staff-table td.grade-status,
    .dept-staff-table td.grade-action {
        vertical-align: middle;
        background: #fff;
    }

    .dept-staff-table th:first-child {
        width: 50px;
    }

    .dept-staff-table th.status-col {
        width: 150px;
    }

    .dept-staff-table th.action-col {
        width: 120px;
    }

    div.dt-buttons {
        float: right;
        margin-left: 10px;
    }
</style>

<?php if ($total == 0 && empty($split['gradeRows'])) { ?>
    <div class="alert alert-info">No staff in this department are expected to fill a TNA.</div>
<?php } else { ?>
    <div class="kpi-row">
        <div class="kpi active" data-status="">
            <div class="kpi-label"><i class="fas fa-users"></i> Staff</div>
            <div class="kpi-value"><?php echo $total; ?></div>
            <div class="kpi-sub">expected to fill TNA</div>
        </div>
        <div class="kpi kpi-good" data-status="FILLED">
            <div class="kpi-label"><i class="fas fa-file-signature"></i> Filled</div>
            <div class="kpi-value"><?php echo $filled; ?></div>
            <div class="kpi-sub"><?php echo $completion; ?>% completion</div>
        </div>
        <div class="kpi kpi-good" data-status="APPROVED">
            <div class="kpi-label"><i class="fas fa-check-circle"></i> Approved</div>
            <div class="kpi-value"><?php echo $counts['APPROVED']; ?></div>
            <div class="kpi-sub">approved by HOD</div>
        </div>
        <div class="kpi kpi-warning" data-status="WAITING APPROVAL">
            <div class="kpi-label"><i class="fas fa-hourglass-half"></i> Waiting Approval</div>
            <div class="kpi-value"><?php echo $counts['WAITING APPROVAL']; ?></div>
            <div class="kpi-sub">waiting for HOD</div>
        </div>
        <div class="kpi kpi-critical" data-status="NOT FILLED">
            <div class="kpi-label"><i class="fas fa-minus-circle"></i> Not Filled</div>
            <div class="kpi-value"><?php echo $counts['NOT FILLED']; ?></div>
            <div class="kpi-sub">no TNA yet</div>
        </div>
    </div>
    <p class="text-muted" style="text-align:left;">Click a tile above to filter the list.</p>

    <div class="staff-group">
        <h5 class="group-title">
            Own TNA
            <span class="badge"><?php echo count($split['own']); ?></span>
            <small><?php echo $split['ownFilled']; ?> filled &middot; Staff who fill their own TNA.</small>
        </h5>
        <?php if (empty($split['own'])) { ?>
            <p class="text-muted">None in this department.</p>
        <?php } else { ?>
            <table id="ownStaffTable" class="table table-bordered table-striped dept-staff-table"
                data-title="<?php echo htmlspecialchars($department . ' - Own TNA - TNA FY ' . $year); ?>"
                style="width:100%;">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Staff No.</th>
                        <th>Staff Name</th>
                        <th>Designation</th>
                        <th>Section</th>
                        <th class="status-col">Status</th>
                        <?php if ($viewButtons) { ?>
                            <th class="action-col no-export">Action</th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($split['own'] as $i => $s) { ?>
                        <tr>
                            <td class="text-center"><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($s['staffno']); ?></td>
                            <td><?php echo htmlspecialchars($s['staffname']); ?></td>
                            <td><?php echo htmlspecialchars($s['designation']); ?></td>
                            <td><?php echo htmlspecialchars($s['section']); ?></td>
                            <?php // Kept on one line: the tile filter matches this cell's text exactly. ?>
                            <td class="text-center"><span class="label label-pill <?php echo $statusLabel[$s['status']]; ?>"><?php echo $s['status']; ?></span></td>
                            <?php if ($viewButtons) { ?>
                                <td class="text-center">
                                    <button type="button" id="<?php echo (int) $s['id']; ?>" class="btn btn-info btn-sm view"><i class="fa fa-edit"></i> VIEW TNA</button>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>

    <div class="staff-group">
        <h5 class="group-title">
            Covered by Grade TNA
            <span class="badge"><?php echo $gradeCount; ?></span>
            <small><?php echo $gradesFilled; ?> of <?php echo $gradeCount; ?> grades filled &middot;
                <?php echo $split['gradeStaff']; ?> non-executives, counted as done once their grade's TNA is filled.
                <?php if ($split['ungraded'] > 0) { ?>
                    <?php echo $split['ungraded']; ?> have no grade yet and stay not filled until HR gives them one.
                <?php } ?></small>
        </h5>
        <?php if (empty($split['gradeRows'])) { ?>
            <p class="text-muted">None in this department.</p>
        <?php } else { ?>
            <table id="gradeTnaTable" class="table table-bordered table-striped dept-staff-table"
                data-title="<?php echo htmlspecialchars($department . ' - Grade TNA - TNA FY ' . $year); ?>"
                style="width:100%;">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th class="grade-col">Grade</th>
                        <th>Section</th>
                        <th>Staff</th>
                        <th>Done</th>
                        <th class="status-col">Status</th>
                        <?php if ($viewButtons) { ?>
                            <th class="action-col no-export">Action</th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $gradeRows = $split['gradeRows'];
                    $gradeNo = 0;
                    foreach ($gradeRows as $i => $g) {
                        if ($i === 0 || $g['grade'] !== $gradeRows[$i - 1]['grade']) {
                            $gradeNo++;
                        }
                        ?>
                        <tr>
                            <td class="text-center grade-no"><?php echo $gradeNo; ?></td>
                            <td class="grade-cell"><?php echo htmlspecialchars($g['label']); ?></td>
                            <td><?php echo htmlspecialchars($g['section'] !== '' ? $g['section'] : '-'); ?></td>
                            <td class="text-center"><?php echo $g['staff']; ?></td>
                            <td class="text-center"><?php echo $g['done']; ?></td>
                            <?php // Kept on one line: the tile filter matches this cell's text exactly. ?>
                            <td class="text-center grade-status"><span class="label label-pill <?php echo $statusLabel[$g['status']]; ?>"><?php echo $g['status']; ?></span></td>
                            <?php if ($viewButtons) { ?>
                                <td class="text-center grade-action">
                                    <?php // No grade: there is no grade TNA to open. ?>
                                    <?php if ($g['label'] !== 'No grade') { ?>
                                        <button type="button" id="<?php echo htmlspecialchars($g['grade'] . '/' . $department); ?>" class="btn btn-info btn-sm viewgrade"><i class="fa fa-edit"></i> VIEW TNA</button>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>
    <p class="text-muted" style="font-size:12px;margin:8px 0 0;text-align:left;">
        Staff expected to fill a TNA, the same people the TNA Summary dashboard counts. Non-executives
        covered by their grade's TNA count as filled once that grade's TNA is filled. Own TNA lists not filled first; grades are listed in order.
    </p>
<?php } ?>

<script>
    $(function () {
        // Merge each run of consecutive cells with the same key into the
        // run's first cell (rowspan) and hide the rest.
        function mergeRuns(cells, keys) {
            var first = -1;
            cells.each(function (i) {
                if (first >= 0 && keys[i] === keys[first]) {
                    var top = cells.eq(first);
                    top.attr('rowspan', (parseInt(top.attr('rowspan'), 10) || 1) + 1);
                    $(this).hide();
                } else {
                    first = i;
                }
            });
        }

        // "order": [] keeps the server order (Own TNA: not filled first;
        // grades: by grade, then section).
        var tables = $('.dept-staff-table').map(function () {
            return $(this).DataTable({
                "pageLength": 25,
                "autoWidth": false,
                "order": [],
                "dom": 'Blfrtip',
                "buttons": [{
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    title: $(this).data('title'),
                    exportOptions: { columns: ':visible:not(.no-export)' }
                }],
                "columnDefs": [
                    { className: 'text-center', targets: [0, 'status-col'] },
                    { orderable: false, targets: [0, 'action-col'] }
                ],
                // Keep "No." running 1..n whatever the sort, search or filter.
                "drawCallback": function () {
                    var api = this.api();
                    var applied = { search: 'applied', order: 'applied' };
                    var noCells = api.column(0, applied).nodes().to$();
                    if (!$(this).find('th.grade-col').length) {
                        noCells.each(function (i) {
                            this.innerHTML = i + 1;
                        });
                        return;
                    }
                    // Grade table: "No." counts grades, not rows, and the
                    // No., Grade and Action cells of the same grade on
                    // consecutive rows merge into one (rowspan), redone on
                    // every draw.
                    var gradeCells = api.column('.grade-col', applied).nodes().to$();
                    var n = 0;
                    gradeCells.each(function (i) {
                        if (i === 0 || $(this).text() !== gradeCells.eq(i - 1).text()) {
                            n++;
                        }
                        noCells.eq(i).html(n);
                    });
                    var page = { page: 'current' };
                    var pageNo = api.column(0, page).nodes().to$();
                    var pageGrade = api.column('.grade-col', page).nodes().to$();
                    var pageStatus = api.column('.status-col', page).nodes().to$();
                    // Only the HOD page has an Action column; column() on a
                    // missing one returns no nodes() at all.
                    var hasAction = $(this).find('th.action-col').length > 0;
                    var pageAction = hasAction ? api.column('.action-col', page).nodes().to$() : $();
                    pageNo.add(pageGrade).add(pageStatus).add(pageAction).removeAttr('rowspan').show();
                    // Status merges only within a grade and only when it matches.
                    var gradeKeys = pageGrade.map(function () { return $(this).text(); }).get();
                    var statusKeys = pageStatus.map(function (i) { return gradeKeys[i] + '|' + $(this).text(); }).get();
                    mergeRuns(pageNo, gradeKeys);
                    mergeRuns(pageGrade, gradeKeys);
                    mergeRuns(pageStatus, statusKeys);
                    mergeRuns(pageAction, gradeKeys);
                }
            });
        }).get();

        // The tiles filter both tables.
        $('.kpi[data-status]').on('click', function () {
            var status = $(this).data('status');
            $('.kpi[data-status]').removeClass('active');
            $(this).addClass('active');
            var pattern = status === 'FILLED' ? '^(WAITING APPROVAL|APPROVED)$' : (status ? '^' + status + '$' : '');
            $.each(tables, function (i, table) {
                table.column('.status-col').search(pattern, true, false).draw();
            });
        });
    });
</script>
