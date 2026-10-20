<?php
    session_start();

    $canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
        isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && $_SESSION['role'] == 'CLERK'
        && $_SESSION['usertype'] == 'MAIN'
    );
    include_once __DIR__ . '/../../../../skill_matrix_department.php';
    $canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'CLERK') && ($_SESSION['usertype'] == 'MAIN')) {

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Learning and Development Management System</title>

        <script src="../../../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../../../asset/css/bootstrap.min.css" />
        <script src="../../../../asset/js/bootstrap.min.js"></script>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>
        <style>
            #participantlist, #contractlist {
                width: 100% !important;
            }
        </style>
    </head>

    <body onload="startTime()" style="background-image:url('../../../../asset/image/bg-try.png');zoom: 75%;">
        <br>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10">
                    <img src="../../../../asset/image/lndlogo.gif" height="50" width="290">
                </div>
                <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;">

                </div>
            </div>
            <nav class="navbar navbar-inverse">
                <div class="container-fluid">
                    <ul class="nav navbar-nav">
                        <li><a href="../../dashboard.php">HOME</a></li>
                        <li><a href="../../staff/staff.php">CONTRACT STAFF LIST</a></li>
                        <li class="active"><a href="../training_ojt.php">ALL TRAINING</a></li>
                        <li><a href="../../attendance/training.php">MY TRAINING</a></li>
                        <li><a href="../../attendance/pme.php">PME</a></li>
                        <li><a href="../../tna/tna_list.php">TNA</a></li>
                        <?php if ($canViewSkillMatrix) { ?><li><a href="../../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li><?php } ?>
                        <li><a href="../../../../admin/archive/export.php">EXPORT</a></li>
                        <li><a href="../../password/password.php">CHANGE PASSWORD</a></li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right">
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="label label-pill label-danger count"></span> <?php echo $_SESSION['fullname'] ?> </a>
                            <ul class="dropdown-menu">
                                <li><a href="../../../../logout.php">LOGOUT</a></li>
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
                                <div class="col-md-2">
                                    <button type="button" id="back_training" class="btn btn-success btn-md"><i class="far fa-arrow-alt-circle-left"></i> Back to Training List</button>
                                </div>
                                <div class="col-md-10">
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
                                <div class="col-md-12" style="margin-top: 10px;">
                                    <strong>Participant List (<span id="title"></span>)</strong>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body" align="center">
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <legend>List of Permanent Staff</legend>
                                    <table id="participantlist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Staff Number</th>
                                                <th>Staff Name</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                                <th width="180px">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <legend>List of Contract Staff</legend>
                                    <table id="contractlist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Staff Number</th>
                                                <th>Staff Name</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                                <th width="180px">Action</th>
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
                    </br>All Rights Reserved.  |  Web design by PHN IT Department
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
            h = checkTime(h);
            m = checkTime(m);
            s = checkTime(s);
            document.getElementById('txt').innerHTML = h + ":" + m + ":" + s;
            t = setTimeout(function(){ startTime() }, 500);
        }

        function checkTime(i) {
            if (i < 10) { i = "0" + i; }
            return i;
        }

        $('#back_training').click(function() {
            window.location = "../training_ojt.php";
        });

        var trainingid = localStorage.getItem("setid");

        getTitle();

        function getTitle() {
            $.ajax({
                url: "fetch_participant.php",
                type: "POST",
                data: { action: 'fetch_trainingtitle', trainingid: trainingid },
                dataType: "JSON",
                success: function(data) {
                    $('#title').text(data[0].title);
                }
            });
        }

        fetch_data('load_participant', trainingid);

        function fetch_data(action, trainingid) {
            var participantdataTable = $('#participantlist').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "responsive": true,
                "autoWidth": false,
                "pageLength": 10,
                "info": true,
                "ajax": {
                    url: "fetch_participant.php",
                    type: "POST",
                    dataSrc: '',
                    data: { action: action, trainingid: trainingid }
                },
                "columns": [
                    {
                        "data": "id",
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    { "data": "staffno" },
                    { "data": "staffname" },
                    { "data": "department" },
                    { "data": "status" },
                    { "data": "btnedit" }
                ],
                "columnDefs": [
                    { className: 'text-center', targets: [0, 4, 5] }
                ]
            });
        }

        fetch_contract('load_contract', trainingid);

        function fetch_contract(action, trainingid) {
            var contractdataTable = $('#contractlist').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "responsive": true,
                "autoWidth": false,
                "pageLength": 10,
                "info": true,
                "ajax": {
                    url: "fetch_participant.php",
                    type: "POST",
                    dataSrc: '',
                    data: { action: action, trainingid: trainingid }
                },
                "columns": [
                    {
                        "data": "id",
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    { "data": "staffno" },
                    { "data": "staffname" },
                    { "data": "department" },
                    { "data": "status" },
                    { "data": "btnedit" }
                ],
                "columnDefs": [
                    { className: 'text-center', targets: [0, 4, 5] }
                ]
            });
        }

        $(document).on('click', '.delete', function() {
            var id = $(this).attr("id");
            swal({
                title: "Delete Participant?",
                text: "Are you sure?",
                icon: "warning",
                buttons: ["Cancel", "Confirm"],
                dangerMode: true
            })
            .then(function(isConfirm) {
                if (isConfirm) {
                    $.ajax({
                        url: "participant_action.php",
                        method: "POST",
                        data: { id: id, btn_action: 'deleteparticipant' },
                        success: function(data) {
                            var response = JSON.parse(data);
                            if (response.message == 'delete') {
                                swal('Deleted!', 'The participant has been deleted.', 'success')
                                .then(function() {
                                    $('#participantlist').DataTable().destroy();
                                    fetch_data('load_participant', trainingid);
                                    $('#contractlist').DataTable().destroy();
                                    fetch_contract('load_contract', trainingid);
                                });
                            } else {
                                swal('Not Deleted!', 'The participant cannot be deleted. Please refer the IT.', 'error')
                                .then(function() {
                                    $('#participantlist').DataTable().destroy();
                                    fetch_data('load_participant', trainingid);
                                    $('#contractlist').DataTable().destroy();
                                    fetch_contract('load_contract', trainingid);
                                });
                            }
                        }
                    });
                } else {
                    swal("Cancelled", "The participant has not been deleted", "error");
                }
            });
        });

        $(document).on('click', '.absent', function() {
            var id = $(this).attr("id");
            swal({
                title: "Change status to absent?",
                text: "Are you sure?",
                icon: "warning",
                buttons: ["Cancel", "Confirm"],
                dangerMode: true
            })
            .then(function(isConfirm) {
                if (isConfirm) {
                    $.ajax({
                        url: "participant_action.php",
                        method: "POST",
                        data: { id: id, btn_action: 'absent' },
                        success: function(data) {
                            var response = JSON.parse(data);
                            if (response.message == 'delete') {
                                swal('Changed!', 'The status has been changed.', 'success')
                                .then(function() {
                                    $('#participantlist').DataTable().destroy();
                                    fetch_data('load_participant', trainingid);
                                    $('#contractlist').DataTable().destroy();
                                    fetch_contract('load_contract', trainingid);
                                });
                            } else {
                                swal('Not Changed!', 'The status cannot be changed. Please refer the IT.', 'error')
                                .then(function() {
                                    $('#participantlist').DataTable().destroy();
                                    fetch_data('load_participant', trainingid);
                                    $('#contractlist').DataTable().destroy();
                                    fetch_contract('load_contract', trainingid);
                                });
                            }
                        }
                    });
                } else {
                    swal("Cancelled", "The status has not been changed", "error");
                }
            });
        });
    </script>
</html>
<?php
    } else {
        header("Location: ../../../../login.php");
        exit();
    }
?>
