<?php
session_start();

if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'ADMIN')) {

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
    </head>

    <style>
        .panel-dashboard {
            display: flex;
            align-items: center;
            column-gap: 20px;
        }
        .panel-dashboard-2{
            display: flex;
            align-items: center;
            column-gap: 20px;
        }

        .tna-submission, .tna-summary {
            width: 100%;
            /* overflow-x: hidden; */
        }

        .tna {
            width: 100%;
        }

        .panel-body2 {
            height: 600px;
            /* Adjust the height as needed to match other table heights */
            overflow-y: auto;
            overflow-x: hidden
            /* Add vertical scrolling if content exceeds height */
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
						<li><a href="../tna/tna_summary.php">TNA SUMMARY</a></li>
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
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="col-md-6" align="right">
                                            <select name="department" id="department" class="form-control"></select>
                                        </div>
                                        <div class="col-md-2" align="right">
                                            <button type="button" name="filter_dept" id="filter_dept"
                                                class="btn btn-info btn-md">FILTER <i class="fa fa-search"></i> </button>
                                        </div>
                                        <div class="col-md-4" align="left">
                                            <div class="row">
                                                <form action="export_tnaall.php" align="left" class="form-horizontal"
                                                    method="post" enctype="multipart/form-data">
                                                    <div class="col-md-12">
                                                        <input type="hidden" name="departmentrep" id="departmentrep" />
                                                        <button type="submit" name="export_tna" id="export_tna"
                                                            class="btn btn-success btn-xm" style="width: 150px;"><i
                                                                class="fas fa-file-pdf"> </i> TNA SUMMARY</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="row">
                                        <form action="export_tnaall1.php" align="right" class="form-horizontal"
                                            method="post" enctype="multipart/form-data">
                                            <div class="col-md-12">
                                                <input type="hidden" name="departmentrep" id="departmentrep" />
                                                <button type="submit" name="export_tna1" id="export_tna1"
                                                    class="btn btn-success btn-xm"><i class="fas fa-file-pdf"> </i> TNA LIST
                                                    BY TRAINING</button>
                                            </div>
                                        </form>
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
                            <div class="row">
                                <div class="col-md-8" style="margin-top: 10px;">
                                    <strong>TNA List (by Individual)</strong>
                                </div>
                                <div class="col-md-4" align="right">
                                    <a href="training_options.php" class="btn btn-primary btn-md"><i class="fas fa-list"></i> TRAINING OPTIONS</a>
                                    <button type="button" name="add_tna" id="add_tna" class="btn btn-success btn-md">MY TNA
                                        <i class="far fa-arrow-alt-circle-right"></i> </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <table id="userlist" class="table table-bordered table-striped" width="100%">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Staff No.</th>
                                                <th>Staff Name</th>
                                                <th>Section</th>
                                                <th>Status</th>
                                                <th width="65px">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <br>
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-11" style="margin-top: 10px;">
                                    <strong>TNA List (by Grade)</strong>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <table id="gradelist" class="table table-bordered table-striped" width="100%">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Job Grade</th>
                                                <th>Head Count</th>
                                                <th>Status</th>
                                                <th width="65px">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
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

        var userid = <?php echo $_SESSION['id'] ?>;
        var newdept = '';

        $(function () {
            $.post("fetch_tna.php", {
                action: "load_department"
            }, function (data) {
                $('#department').html(data);
                $('#department').val();
            });
        });

        $(function () {
            $.post("fetch_tna.php", {
                action: "all_department",
            }, function (data) {
                // Parse the returned JSON data
                var parsedData = JSON.parse(data);

                var gradedataTable = $('#submissionlist').DataTable({
                    "paging": true,
                    "lengthChange": true,
                    "searching": true,
                    "ordering": true,
                    "responsive": true,
                    "pageLength": 10,
                    "info": true,
                    "data": parsedData, // Use parsed data
                    "columns": [
                        {
                            "data": "id",
                            render: function (data, type, row, meta) {
                                return meta.row + meta.settings._iDisplayStart + 1;
                            }
                        },
                        {
                            "data": "department"
                        },
                        {
                            "data": "quantity"
                        }
                    ],
                    "columnDefs": [
                        {
                            className: 'text-left',
                            targets: [0, 1]
                        }
                    ]
                });
            });
        });

        $('#department').on('change', function () {
            $('#export_tna').hide();
        });

        $('#export_tna').hide();

        $('#filter_dept').click(function () {
            var department = $('#department').val();
            $('#userlist').DataTable().destroy();
            fetch_data('load_staff', userid, department);
            $('#gradelist').DataTable().destroy();
            fetch_data1('load_grade', userid, department);
            $('#export_tna').show();
        });

        // var dept = sessionStorage.getItem("setdeptnext");
        // console.log("dept: ", dept);
        // if (dept != null) {
        //     newdept = dept;
        //     fetch_data('load_staff', userid, dept);
        //     fetch_data1('load_grade', userid, dept);
        // }

        $('#export_tna').click(function () {
            $('#departmentrep').val($('#department').val());
        });

        $('#add_tna').click(function () {
            localStorage.setItem("setaction", 'addtna');
            localStorage.setItem("setid", userid);
            window.location = "manage_tna.php";
        });

        function fetch_data(action, userid, department) {
            var userdataTable = $('#userlist').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "responsive": true,
                "pageLength": 10,
                "info": true,
                "ajax": {
                    url: "fetch_tna.php",
                    type: "POST",
                    dataSrc: '',
                    data: {
                        action: action,
                        userid: userid,
                        department: department
                    },
                },
                "columns": [{
                    "data": "id",
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    "data": "staffno"
                },
                {
                    "data": "staffname"
                },
                {
                    "data": "section"
                },
                {
                    "data": "status"
                },
                {
                    "data": "btnedit"
                }
                ],
                "columnDefs": [{
                    className: 'text-center',
                    targets: [0, 4, 5]
                }]
            });
        }

        function fetch_data1(action, userid, department) {
            var gradedataTable = $('#gradelist').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "responsive": true,
                "pageLength": 10,
                "info": true,
                "ajax": {
                    url: "fetch_tna.php",
                    type: "POST",
                    dataSrc: '',
                    data: {
                        action: action,
                        userid: userid,
                        department: department
                    },
                    //   success: function (data) {
                    //     console.log("Data fetched successfully:", data);
                    // },
                },
                "columns": [{
                    "data": "id",
                    render: function (data, type, row, meta) {
                        // console.log("data: ", data);
                        // console.log("type: ", type);
                        // console.log("row: ", row);
                        // console.log("meta: ", meta);
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    "data": "grade"
                },
                {
                    "data": "headcount"
                },
                {
                    "data": "status"
                },
                {
                    "data": "btnedit"
                }
                ],
                "columnDefs": [{
                    className: 'text-center',
                    targets: [0, 1, 2, 3, 4]
                }]
            });
        }

        $(document).on('click', '.view', function () {
            var id = $(this).attr("id");
            var department = $('#department').val();
            localStorage.setItem("setaction", 'edittna');
            localStorage.setItem("setid", id);
            localStorage.setItem("setdept", department);
            window.location = "manage_tna.php";
        });

        $(document).on('click', '.viewgrade', function () {
            var id = $(this).attr("id");
            var department = $('#department').val();
            localStorage.setItem("setaction", 'addtnagrade');
            localStorage.setItem("setid", id);
            localStorage.setItem("setdept", department);
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
