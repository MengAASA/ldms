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

    $split = tnaDashSplitByCoverage($staff);
    $year = ldmsPlanningYear();

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
                    <?php
                    $viewButtons = false;
                    include __DIR__ . '/tna_department_view.php';
                    ?>
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
    </script>

    </html>
    <?php
} else {
    header("Location: ../../login.php");
    exit();
}
?>
