<?php
session_start();

if (isset($_SESSION['fullname']) && ($_SESSION['usertype'] == 'HOD')) {

    include_once "../../../dbconn.php";
    include_once __DIR__ . "/../includes/skill_matrix_access.php";
    $showSkillMatrixNav = canApproveSkillMatrix();
    include_once __DIR__ . "/../../../admin/tna/tna_dashboard_data.php";

    // The HOD's own department: the one they head on the Organization page,
    // else the department on their own record.
    $hodId = (int) $_SESSION['id'];
    $stmt = $conn->prepare("SELECT COALESCE(
            (SELECT name FROM departments WHERE hod_user_id = ? ORDER BY id LIMIT 1),
            (SELECT department FROM user WHERE id = ?)) AS department");
    $stmt->bind_param('ii', $hodId, $hodId);
    $stmt->execute();
    $department = (string) $stmt->get_result()->fetch_assoc()['department'];
    $stmt->close();
    $year = ldmsPlanningYear();

    // Same people and rules as the admin department page, less the HOD
    // themself (their own TNA is under MY TNA).
    $staff = [];
    if ($department !== '') {
        foreach (tnaDashDepartmentStaff($conn, $department) as $s) {
            if ($s['id'] !== $hodId) {
                $staff[] = $s;
            }
        }
    }
    $split = tnaDashSplitByCoverage($staff);

    // A grade TNA filled for a grade nobody is covered by any more still
    // needs a row, so the HOD can open and approve it.
    if ($department !== '') {
        $yearStr = (string) $year;
        $stmt = $conn->prepare("SELECT grade, MAX(status) AS status FROM tna
            WHERE department = ? AND year = ? AND userid IS NULL AND grade IS NOT NULL
            GROUP BY grade");
        $stmt->bind_param('ss', $department, $yearStr);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $grade = (int) $r['grade'];
            if (isset($split['gradeStatus'][$grade])) {
                continue;
            }
            $status = $r['status'] === 'APPROVE' ? 'APPROVED' : 'WAITING APPROVAL';
            $split['gradeStatus'][$grade] = $status;
            $split['gradeRows'][] = ['grade' => $grade, 'label' => 'Grade ' . $grade, 'section' => '', 'staff' => 0, 'done' => 0, 'status' => $status];
        }
        $stmt->close();
        usort($split['gradeRows'], 'tnaDashCompareGradeRows');
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
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" href="../../../asset/css/datepicker.css">
        <script src="../../../asset/js/bootstrap-datepicker1.js"></script>
        <link rel="stylesheet" type="text/css"
            href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css" />
        <script type="text/javascript"
            src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
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
                        <?php if ($showSkillMatrixNav) { ?>
                        <li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li>
                        <?php } ?>
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
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-10" style="margin-top: 10px;">
                                    <strong><?php echo htmlspecialchars($department !== '' ? $department : 'No department'); ?> &ndash; TNA FY <?php echo $year; ?></strong>
                                </div>
                                <div class="col-md-2" align="right">
                                    <button type="button" name="add_tna" id="add_tna" class="btn btn-success btn-md">MY TNA
                                        <i class="far fa-arrow-alt-circle-right"></i> </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php
                            $viewButtons = true;
                            include __DIR__ . '/../../../admin/tna/tna_department_view.php';
                            ?>
                        </div>
                    </div>
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
            t = setTimeout(function () { startTime() }, 500);
        }

        function checkTime(i) {
            if (i < 10) {
                i = "0" + i;
            }
            return i;
        }

        var userid = <?php echo $_SESSION['id'] ?>;

        $('#add_tna').click(function () {
            localStorage.setItem("setaction", 'addtna');
            localStorage.setItem("setid", userid);
            window.location = "manage_tna.php";
        });

        $(document).on('click', '.view', function () {
            console.log("view tna");

            var id = $(this).attr("id");
            localStorage.setItem("setaction", 'edittna');
            localStorage.setItem("setid", id);
            window.location = "manage_tna.php";
        });

        $(document).on('click', '.viewgrade', function () {
            console.log("view grade");
            var id = $(this).attr("id");
            console.log("id: ", id);
            localStorage.setItem("setaction", 'addtnagrade');
            localStorage.setItem("setid", id);
            window.location = "manage_grade.php";
        });
    </script>

    </html>
    <?php
} else {
    header("Location: ../../login.php");
    exit();
}
?>