<?php
// One department's TNA progress: every staff expected to fill a TNA, filled
// or not. Opened by clicking a department in TNA Submission (by Department)
// on tna_summary.php. Same people and rules as the dashboard counts (see
// tnaDashDepartmentStaff() in tna_dashboard_data.php).
session_start();

if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'ADMIN')) {
    include "../../dbconn.php";
    include_once __DIR__ . '/tna_dashboard_data.php';

    $department = isset($_GET['department']) ? trim((string) $_GET['department']) : '';
    $staff = $department !== '' ? tnaDashDepartmentStaff($conn, $department) : [];

    $counts = ['APPROVED' => 0, 'WAITING APPROVAL' => 0, 'NOT FILLED' => 0];
    foreach ($staff as $s) {
        $counts[$s['status']]++;
    }
    $total = count($staff);
    $filled = $counts['APPROVED'] + $counts['WAITING APPROVAL'];
    $completion = tnaDashPercent($filled, $total);

    // The HOD assigned to the department on the Organization page.
    $hodName = '';
    if ($department !== '') {
        $stmt = $conn->prepare("SELECT u.staffno, u.staffname FROM departments d JOIN user u ON u.id = d.hod_user_id WHERE d.name = ?");
        $stmt->bind_param('s', $department);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $hodName = $row['staffname'] . ' (' . $row['staffno'] . ')';
        }
        $stmt->close();
    }

    $statusLabel = [
        'APPROVED' => 'label-success',
        'WAITING APPROVAL' => 'label-warning',
        'NOT FILLED' => 'label-danger',
    ];
    $year = ldmsPlanningYear();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Learning and Development Management System</title>
        <script src="../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../asset/css/bootstrap.min.css" />
        <script src="../../asset/js/bootstrap.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" type="text/css"
            href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css" />
        <script type="text/javascript"
            src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js">
            </script>
    </head>

    <style>
        .dept-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .dept-head h4 {
            margin: 0;
            font-weight: bold;
        }

        .dept-head small {
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
            border-left: 4px solid #c3c2b7;
            border-radius: 4px;
            padding: 12px 15px;
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

        div.dt-buttons {
            float: right;
            margin-left: 10px;
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
                        <li class="active"><a href="tna_summary.php">TNA SUMMARY</a></li>
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

            <div class="panel panel-default">
                <div class="panel-heading dept-head">
                    <div>
                        <h4><?php echo htmlspecialchars($department !== '' ? $department : 'No department selected'); ?> &ndash; TNA FY <?php echo $year; ?></h4>
                        <small>Head of Department: <?php echo $hodName !== '' ? htmlspecialchars($hodName) : '-'; ?></small>
                    </div>
                    <a href="tna_summary.php" class="btn btn-success">
                        <i class="far fa-arrow-alt-circle-left"></i> BACK TO TNA SUMMARY
                    </a>
                </div>
                <div class="panel-body">
                    <?php if ($total == 0) { ?>
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
                        <p class="text-muted">Click a tile above to filter the list.</p>

                        <div class="table-responsive">
                            <table id="deptStaffTable" class="table table-bordered table-striped" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Staff No.</th>
                                        <th>Staff Name</th>
                                        <th>Designation</th>
                                        <th>Section</th>
                                        <th>Covered By</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staff as $i => $s) { ?>
                                        <tr>
                                            <td class="text-center"><?php echo $i + 1; ?></td>
                                            <td><?php echo htmlspecialchars($s['staffno']); ?></td>
                                            <td><?php echo htmlspecialchars($s['staffname']); ?></td>
                                            <td><?php echo htmlspecialchars($s['designation']); ?></td>
                                            <td><?php echo htmlspecialchars($s['section']); ?></td>
                                            <td><?php echo htmlspecialchars($s['covered_by']); ?></td>
                                            <?php // Kept on one line: the tile filter matches this cell's text exactly. ?>
                                            <td class="text-center"><span class="label label-pill <?php echo $statusLabel[$s['status']]; ?>"><?php echo $s['status']; ?></span></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted" style="font-size:12px;margin:8px 0 0;">
                            Staff expected to fill a TNA, the same people the TNA Summary dashboard counts. Non-executives
                            covered by their grade's TNA count as filled once that grade's TNA is filled. Not filled are listed first.
                        </p>
                    <?php } ?>
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

        $(function () {
            // "order": [] keeps the server order (not filled first).
            var table = $('#deptStaffTable').DataTable({
                "pageLength": 25,
                "order": [],
                "dom": 'Blfrtip',
                "buttons": [{
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    title: <?php echo json_encode(($department !== '' ? $department : 'Department') . ' - TNA FY ' . $year); ?>,
                    exportOptions: { columns: ':visible' }
                }],
                "columnDefs": [
                    { className: 'text-center', targets: [0, 6] },
                    { orderable: false, targets: [0] }
                ],
                // Keep "No." running 1..n whatever the sort, search or filter.
                "drawCallback": function () {
                    var api = this.api();
                    api.column(0, { search: 'applied', order: 'applied' }).nodes().each(function (cell, i) {
                        cell.innerHTML = i + 1;
                    });
                }
            });

            $('.kpi[data-status]').on('click', function () {
                var status = $(this).data('status');
                $('.kpi[data-status]').removeClass('active');
                $(this).addClass('active');
                var pattern = status === 'FILLED' ? '^(WAITING APPROVAL|APPROVED)$' : (status ? '^' + status + '$' : '');
                table.column(6).search(pattern, true, false).draw();
            });
        });
    </script>

    </html>
    <?php
} else {
    header("Location: ../../login.php");
    exit();
}
?>
