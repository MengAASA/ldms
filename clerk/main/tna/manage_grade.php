<?php
    session_start();

    $canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
        isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && $_SESSION['role'] == 'CLERK'
        && $_SESSION['usertype'] == 'MAIN'
    );
    include_once __DIR__ . '/../../../skill_matrix_department.php';
    $canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

    if (isset($_SESSION['fullname']) && ($_SESSION['role'] == 'CLERK') && ($_SESSION['usertype'] == 'MAIN')) {

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Learning and Development Management System</title>
    <script src="../../../asset/js/jquery-1.10.2.min.js"></script>
    <link rel="stylesheet" href="../../../asset/css/bootstrap.min.css" />
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.css" />
    <script src="../../../asset/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.js"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
    <link rel="stylesheet" href="../../../asset/css/datepicker.css">
    <script src="../../../asset/js/bootstrap-datepicker1.js"></script>
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css" />
    <script type="text/javascript"
        src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js">
    </script>
<?php require_once __DIR__ . '/../../../tna_training_options.php'; echo tna_training_options_script($conn); ?>
</head>

<style>
#spinner-div {
    position: fixed;
    display: none;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    text-align: center;
    background-color: rgba(255, 255, 255, 0.8);
    z-index: 2;
}
</style>

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
                    <li><a href="../staff/staff.php">CONTRACT STAFF LIST</a></li>
                    <li><a href="../training/training_ojt.php">ALL TRAINING</a></li>
                    <li><a href="../attendance/training.php">MY TRAINING</a></li>
                    <li><a href="../attendance/pme.php">PME</a></li>
                    <li><a href="tna_list.php">TNA</a></li>
                    <?php if ($canViewSkillMatrix) { ?><li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li><?php } ?>

                    <li><a href="../../../admin/archive/export.php">EXPORT</a></li>
                    <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span
                                class="label label-pill label-danger count"></span> <?php echo $_SESSION['fullname']?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="../../../logout.php">LOGOUT</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <div id="spinner-div">
            <img src="../../../asset/image/loading.gif" id="ajaxSpinnerImage" title="working..."
                style="margin-top: 350px;" />
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-md-2" align="left">
                                <button type="button" name="back_training" id="back_training"
                                    class="btn btn-success btn-md"><i class="far fa-arrow-alt-circle-left"></i> BACK TO
                                    GRADE LIST</button>
                            </div>
                            <div class="col-md-8" style="margin-top:10px" align="center">
                                <strong id="title">Training Need Analysis (TNA) Form - (FY <?php include_once __DIR__ . '/../../../planning_year.php'; echo ldmsPlanningYear(); ?>) - Grade <span
                                        id="grade"></span>
                            </div>
                            <div class="col-md-2" style="margin-top:10px" align="right">
                                <span id="statustna"></span>
                            </div>
                        </div>
                    </div>
                    <div class="panel-body" align="center">
                        <form method="post" id="tna_form">
                            <div class="row">
                                <div class="col-sm-1"></div>
                                <div class="col-sm-10" align="right">
                                    <h5 id="ojthours"></h5>
                                    <h5 id="publichours"></h5>
                                </div>
                                <div class="col-sm-1"></div>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-sm-1"></div>
                                <div class="col-sm-10" align="left">
                                    In this TNA employee needs to complete it in consultation with your IMMEDIATE SUPERIOR. Employee is encourage to reflect on what skills you will need to succeed in the current job scope and what skills you still lack and wish to achieve or improve.
                                </div>
                                <div class="col-sm-1"></div>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-sm-12 table-responsive" align="left">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="2">Level Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1 - Fundamental Awareness</td>
                                                <td>Basic knowledge</td>
                                            </tr>
                                            <tr>
                                                <td>2 - Novice</td>
                                                <td>Little experience or competence in the skill</td>
                                            </tr>
                                            <tr>
                                                <td>3 - Intermediate</td>
                                                <td>Has some competence but remains below level required</td>
                                            </tr>
                                            <tr>
                                                <td>4 - Proficient</td>
                                                <td>Competent and confident in the area</td>
                                            </tr>
                                            <tr>
                                                <td>5 - Expert</td>
                                                <td>An expert in that skill</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <!-- ESG -->
                                    <table id="tnaesglist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="9">
                                                    <div class="row">
                                                        <div class="col-sm-10" align="left" style="margin-top:5px;">
                                                            a. ESG (Environment-Social-Governance)
                                                        </div>
                                                        <div class="col-sm-2" align="right">
                                                            <button type="button" name="add_esg" id="add_esg"
                                                                class="btn btn-info btn-sm">Add Task <i
                                                                    class="fa fa plus"></i> </button>
                                                        </div>
                                                    </div>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th rowspan="2" width="10px">No.</th>
                                                <th rowspan="2" width="420px">Problem Statement</th>
                                                <th rowspan="2" width="420px">Training Required</th>
                                                <th colspan="2">Skills</th>
                                                <th rowspan="2" width="80px">Gap</th>
                                                <th rowspan="2">How will this be achieved?</th>
                                                <th rowspan="2" width="100px">When</th>
                                                <th rowspan="2">Action</th>
                                            </tr>
                                            <tr>
                                                <th width="80px">Target</th>
                                                <th width="80px">Current</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                    <!-- ESG -->
                                    <table id="tnaselflist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="9">
                                                    <div class="row">
                                                        <div class="col-sm-10" align="left" style="margin-top:5px;">
                                                            b. Soft Skill (Based on individual development competencies
                                                            i.e, language)
                                                        </div>
                                                        <div class="col-sm-2" align="right">
                                                            <button type="button" name="add_self" id="add_self"
                                                                class="btn btn-info btn-sm">Add Task <i
                                                                    class="fa fa plus"></i> </button>
                                                        </div>
                                                    </div>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th rowspan="2" width="10px">No.</th>
                                                <th rowspan="2" width="420px">Problem Statement</th>
                                                <th rowspan="2" width="420px">Training Required</th>
                                                <th colspan="2">Skills</th>
                                                <th rowspan="2" width="80px">Gap</th>
                                                <th rowspan="2">How will this be achieved?</th>
                                                <th rowspan="2" width="100px">When</th>
                                                <th rowspan="2">Action</th>
                                            </tr>
                                            <tr>
                                                <th width="80px">Target</th>
                                                <th width="80px">Current</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                    <table id="tnaleadlist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="9">
                                                    <div class="row">
                                                        <div class="col-sm-11" align="left" style="margin-top:5px;">
                                                            c. Leadership Awareness (This area will inculcate the
                                                            nurturing aspect of the talent hence inspiring others
                                                            towards achieving excellence hence inspiring others towards
                                                            achieving excellence)
                                                        </div>
                                                        <div class="col-sm-1" align="right">
                                                            <button type="button" name="add_lead" id="add_lead"
                                                                class="btn btn-info btn-sm">Add Task <i
                                                                    class="fa fa plus"></i> </button>
                                                        </div>
                                                    </div>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th rowspan="2" width="10px">No.</th>
                                                <th rowspan="2" width="420px">Problem Statement</th>
                                                <th rowspan="2" width="420px">Training Required</th>
                                                <th colspan="2">Skills</th>
                                                <th rowspan="2" width="80px">Gap</th>
                                                <th rowspan="2">How will this be achieved?</th>
                                                <th rowspan="2" width="100px">When</th>
                                                <th rowspan="2">Action</th>
                                            </tr>
                                            <tr>
                                                <th width="80px">Target</th>
                                                <th width="80px">Current</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                    <!-- DATA DRIVEN -->
                                    <table id="tnadrivenlist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="9">
                                                    <div class="row">
                                                        <div class="col-sm-11" align="left" style="margin-top:5px;">
                                                            d. Data Driven
                                                        </div>
                                                        <div class="col-sm-1" align="right">
                                                            <button type="button" name="add_driven" id="add_driven"
                                                                class="btn btn-info btn-sm">Add Task <i
                                                                    class="fa fa plus"></i> </button>
                                                        </div>
                                                    </div>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th rowspan="2" width="10px">No.</th>
                                                <th rowspan="2" width="420px">Problem Statement</th>
                                                <th rowspan="2" width="420px">Training Required</th>
                                                <th colspan="2">Skills</th>
                                                <th rowspan="2" width="80px">Gap</th>
                                                <th rowspan="2">How will this be achieved?</th>
                                                <th rowspan="2" width="100px">When</th>
                                                <th rowspan="2">Action</th>
                                            </tr>
                                            <tr>
                                                <th width="80px">Target</th>
                                                <th width="80px">Current</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                    <!-- DATA DRIVEN -->
                                    <table id="tnafunclist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="9">
                                                    <div class="row">
                                                        <div class="col-sm-11" align="left" style="margin-top:5px;">
                                                            e. Functional Awareness (Based on job requirement in terms
                                                            of knowledge and skills required to perform the duties;
                                                            technical skills or knowledge) - Critical Priorities &
                                                            Future Growth
                                                        </div>
                                                        <div class="col-sm-1" align="right">
                                                            <button type="button" name="add_func" id="add_func"
                                                                class="btn btn-info btn-sm">Add Task <i
                                                                    class="fa fa plus"></i> </button>
                                                        </div>
                                                    </div>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th rowspan="2" width="10px">No.</th>
                                                <th rowspan="2" width="420px">Problem Statement</th>
                                                <th rowspan="2" width="420px">Training Required</th>
                                                <th colspan="2">Skills</th>
                                                <th rowspan="2" width="80px">Gap</th>
                                                <th rowspan="2">How will this be achieved?</th>
                                                <th rowspan="2" width="100px">When</th>
                                                <th rowspan="2">Action</th>
                                            </tr>
                                            <tr>
                                                <th width="80px">Target</th>
                                                <th width="80px">Current</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                    <table id="tnabusilist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="9">
                                                    <div class="row">
                                                        <div class="col-sm-11" align="left" style="margin-top:5px;">
                                                            f. Digital Transformation & Innovation (Based on company
                                                            digital objective and expansion requirement for future
                                                            growth)
                                                        </div>
                                                        <div class="col-sm-1" align="right">
                                                            <button type="button" name="add_busi" id="add_busi"
                                                                class="btn btn-info btn-sm">Add Task <i
                                                                    class="fa fa plus"></i> </button>
                                                        </div>
                                                    </div>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th rowspan="2" width="10px">No.</th>
                                                <th rowspan="2" width="420px">Problem Statement</th>
                                                <th rowspan="2" width="420px">Training Required</th>
                                                <th colspan="2">Skills</th>
                                                <th rowspan="2" width="80px">Gap</th>
                                                <th rowspan="2">How will this be achieved?</th>
                                                <th rowspan="2" width="100px">When</th>
                                                <th rowspan="2">Action</th>
                                            </tr>
                                            <tr>
                                                <th width="80px">Target</th>
                                                <th width="80px">Current</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                    <table id="tnaspeclist" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th colspan="9">
                                                    <div class="row">
                                                        <div class="col-sm-11" align="left" style="margin-top:5px;">
                                                            g. Special Project (Short term project either functional or
                                                            cross - functional)
                                                        </div>
                                                        <div class="col-sm-1" align="right">
                                                            <button type="button" name="add_spec" id="add_spec"
                                                                class="btn btn-info btn-sm">Add Task <i
                                                                    class="fa fa plus"></i> </button>
                                                        </div>
                                                    </div>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th rowspan="2" width="10px">No.</th>
                                                <th rowspan="2" width="420px">Problem Statement</th>
                                                <th rowspan="2" width="420px">Training Required</th>
                                                <th colspan="2">Skills</th>
                                                <th rowspan="2" width="80px">Gap</th>
                                                <th rowspan="2">How will this be achieved?</th>
                                                <th rowspan="2" width="100px">When</th>
                                                <th rowspan="2">Action</th>
                                            </tr>
                                            <tr>
                                                <th width="80px">Target</th>
                                                <th width="80px">Current</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-1"></div>
                                <div class="col-md-10">
                                    <div class="form-group" align="right">
                                        <input type="hidden" name="userid" id="userid" />
                                        <input type="hidden" name="btn_action" id="btn_action" />
                                        <input type="hidden" name="esgaware" id="esgaware" />
                                        <input type="hidden" name="selfaware" id="selfaware" />
                                        <input type="hidden" name="leadaware" id="leadaware" />
                                        <input type="hidden" name="dataaware" id="dataaware" />
                                        <input type="hidden" name="functional" id="functional" />
                                        <input type="hidden" name="busiaware" id="busiaware" />
                                        <input type="hidden" name="special" id="special" />
                                        <button type="submit" name="action" id="action" class="btn btn-success btn-xm"
                                            style="width: 150px;"><i class="fa fa-save"></i> Save</button>
                                    </div>
                                </div>
                                <div class="col-md-1"></div>
                            </div>
                        </form>
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
    t = setTimeout(function() {
        startTime()
    }, 500);
}

function checkTime(i) {
    if (i < 10) {
        i = "0" + i;
    }
    return i;
}

$('#back_training').click(function() {
    window.location = "tna_list.php";
});

var userid = localStorage.getItem("setid");
var action = 'gettnagrade';
var tna = 0;
var countesg = 0;
var countself = 0;
var countlead = 0;
var countdriven = 0;
var countfunc = 0;
var countbusi = 0;
var countspec = 0;

var grade = localStorage.getItem("setgrade");
$('#grade').text(grade);

$.ajax({
    url: "fetch_tna.php",
    method: "POST",
    data: {
        userid: userid,
        action: action
    },
    dataType: "json",
    success: function(data) {
        tna = parseInt(data[0].tna);

        if (tna == 0) {
            $('#btn_action').val('addtnagrade');
            $('#userid').val(userid);
        } else if (tna > 0) {
            if (data[0].status == "") {
                $('#statustna').fadeIn().html(
                    '<span class="label label-pill label-warning">WAITING FOR APPROVAL</span>');
            } else if (data[0].status == "APPROVE") {
                $('#statustna').fadeIn().html(
                    '<span class="label label-pill label-success">TNA APPROVED</span>');
            }

            $('#btn_action').val('addtnagrade');
            $('#userid').val(userid);
            var action1 = 'getlisttnagrade';
            var targetskes = [];
            var currentskes = [];
            var trtypees = [];
            var datetres = [];
            var traininges = [];

            var targetskse = [];
            var currentskse = [];
            var trtypese = [];
            var datetrse = [];
            var trainingse = [];

            var targetskle = [];
            var currentskle = [];
            var trtypele = [];
            var datetrle = [];
            var trainingle = [];

            var targetskda = [];
            var currentskda = [];
            var trtypeda = [];
            var datetrda = [];
            var trainingda = [];

            var targetskfu = [];
            var currentskfu = [];
            var trtypefu = [];
            var datetrfu = [];
            var trainingfu = [];

            var targetskbu = [];
            var currentskbu = [];
            var trtypebu = [];
            var datetrbu = [];
            var trainingbu = [];

            var targetsksp = [];
            var currentsksp = [];
            var trtypesp = [];
            var datetrsp = [];
            var trainingsp = [];
            $.ajax({
                url: "fetch_tna.php",
                method: "POST",
                data: {
                    userid: userid,
                    action: action1
                },
                dataType: "json",
                success: function(data) {
                    var trHTMLes = '';
                    var trHTMLse = '';
                    var trHTMLle = '';
                    var trHTMLda = '';
                    var trHTMLfu = '';
                    var trHTMLbu = '';
                    var trHTMLsp = '';
                    var noides = 1;
                    var noidse = 1;
                    var noidle = 1;
                    var noidda = 1;
                    var noidfu = 1;
                    var noidbu = 1;
                    var noidsp = 1;
                    $.each(data, function(i, data) {
                        if (data.section == 'esgaware') {
                            trHTMLes += `
                                    <tr>
                                        <td style="text-align:center;">${noides}</td>
                                        <td>
                                            <textarea class="form-control" name="taskes${noides}" id="taskes${noides}" rows="2">${data.task}</textarea>
                                        </td>
                                        <td>
                                            <select name="traininges${noides}" id="traininges${noides}" class="form-control">
                                                <option selected disabled>-- Select Training --</option>
                                                    ${TNA_TRAINING_OPTIONS.esgaware}
                                            </select>
                                            <br>
                                            <input type="text" name="otres${noides}" id="otres${noides}" class="form-control" value="${data.othertr}" />
                                        </td>
                                        <td>
                                            <select name="targetskes${noides}" id="targetskes${noides}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="currentskes${noides}" id="currentskes${noides}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="text" name="gapes${noides}" id="gapes${noides}" class="form-control" value="${data.gap}" readonly />
                                        </td>
                                        <td>
                                            <select name="trtypees${noides}" id="trtypees${noides}" class="form-control">
                                                <option selected disabled>-- Select Training Type --</option>
                                                <option value="1">1 - On Job Training</option>
                                                <option value="2">2 - Coaching</option>
                                                <option value="3">3 - External / In-house</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="datetres${noides}" id="datetres${noides}" class="form-control">
                                                <option value="Jan">Jan</option>
                                                <option value="Feb">Feb</option>
                                                <option value="Mar">Mar</option>
                                                <option value="Apr">Apr</option>
                                                <option value="May">May</option>
                                                <option value="Jun">Jun</option>
                                                <option value="Jul">Jul</option>
                                                <option value="Aug">Aug</option>
                                                <option value="Sep">Sep</option>
                                                <option value="Oct">Oct</option>
                                                <option value="Nov">Nov</option>
                                                <option value="Dec">Dec</option>
                                            </select>
                                        </td>
                                        <td>
                                            <a href="#" class="remove_esg btn btn-danger">
                                                <i class="fa fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    `;
                            targetskes.push(data.targetskill);
                            currentskes.push(data.currentskill);
                            trtypees.push(data.trainingtype);
                            datetres.push(data.monthapply);
                            traininges.push(data.training);
                            noides++;
                        } else if (data.section == 'selfaware') {
                            trHTMLse += `
                                    <tr>
                                        <td style="text-align:center;">${noidse}</td>
                                        <td>
                                            <textarea class="form-control" name="taskse${noidse}" id="taskse${noidse}" rows="2">${data.task}</textarea>
                                        </td>
                                        <td>
                                            <select name="trainingse${noidse}" id="trainingse${noidse}" class="form-control">
                                                <option selected disabled>-- Select Training --</option>
                                                    ${TNA_TRAINING_OPTIONS.selfaware}
                                            </select>
                                            <br>
                                            <input type="text" name="otrse${noidse}" id="otrse${noidse}" class="form-control" value="${data.othertr}" />
                                        </td>
                                        <td>
                                            <select name="targetskse${noidse}" id="targetskse${noidse}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="currentskse${noidse}" id="currentskse${noidse}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="text" name="gapse${noidse}" id="gapse${noidse}" class="form-control" value="${data.gap}" readonly />
                                        </td>
                                        <td>
                                            <select name="trtypese${noidse}" id="trtypese${noidse}" class="form-control">
                                                <option selected disabled>-- Select Training Type --</option>
                                                <option value="1">1 - On Job Training</option>
                                                <option value="2">2 - Coaching</option>
                                                <option value="3">3 - External / In-house</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="datetrse${noidse}" id="datetrse${noidse}" class="form-control">
                                                <option value="Jan">Jan</option>
                                                <option value="Feb">Feb</option>
                                                <option value="Mar">Mar</option>
                                                <option value="Apr">Apr</option>
                                                <option value="May">May</option>
                                                <option value="Jun">Jun</option>
                                                <option value="Jul">Jul</option>
                                                <option value="Aug">Aug</option>
                                                <option value="Sep">Sep</option>
                                                <option value="Oct">Oct</option>
                                                <option value="Nov">Nov</option>
                                                <option value="Dec">Dec</option>
                                            </select>
                                        </td>
                                        <td>
                                            <a href="#" class="remove_self btn btn-danger">
                                                <i class="fa fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    `;
                            targetskse.push(data.targetskill);
                            currentskse.push(data.currentskill);
                            trtypese.push(data.trainingtype);
                            datetrse.push(data.monthapply);
                            trainingse.push(data.training);
                            noidse++;
                        } else if (data.section == 'leadaware') {
                            trHTMLle += `
                                    <tr>
                                        <td style="text-align:center;">${noidle}</td>
                                        <td>
                                            <textarea class="form-control" name="taskle${noidle}" id="taskle${noidle}" rows="2">${data.task}</textarea>
                                        </td>
                                        <td>
                                            <select name="trainingle${noidle}" id="trainingle${noidle}" class="form-control">
                                                <option selected disabled>-- Select Training --</option>
                                                    ${TNA_TRAINING_OPTIONS.leadaware}
                                            </select>
                                            <br>
                                            <input type="text" name="otrle${noidle}" id="otrle${noidle}" class="form-control" value="${data.othertr}" />
                                        </td>
                                        <td>
                                            <select name="targetskle${noidle}" id="targetskle${noidle}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="currentskle${noidle}" id="currentskle${noidle}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="text" name="gaple${noidle}" id="gaple${noidle}" class="form-control" value="${data.gap}" readonly />
                                        </td>
                                        <td>
                                            <select name="trtypele${noidle}" id="trtypele${noidle}" class="form-control">
                                                <option selected disabled>-- Select Training Type --</option>
                                                <option value="1">1 - On Job Training</option>
                                                <option value="2">2 - Coaching</option>
                                                <option value="3">3 - External / In-house</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="datetrle${noidle}" id="datetrle${noidle}" class="form-control">
                                                <option value="Jan">Jan</option>
                                                <option value="Feb">Feb</option>
                                                <option value="Mar">Mar</option>
                                                <option value="Apr">Apr</option>
                                                <option value="May">May</option>
                                                <option value="Jun">Jun</option>
                                                <option value="Jul">Jul</option>
                                                <option value="Aug">Aug</option>
                                                <option value="Sep">Sep</option>
                                                <option value="Oct">Oct</option>
                                                <option value="Nov">Nov</option>
                                                <option value="Dec">Dec</option>
                                            </select>
                                        </td>
                                        <td>
                                            <a href="#" class="remove_lead btn btn-danger">
                                                <i class="fa fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                `;
                            targetskle.push(data.targetskill);
                            currentskle.push(data.currentskill);
                            trtypele.push(data.trainingtype);
                            datetrle.push(data.monthapply);
                            trainingle.push(data.training);
                            noidle++;
                        } else if (data.section == 'dataaware') {
                            trHTMLda += `
                                    <tr>
                                        <td style="text-align:center;">${noidda}</td>
                                        <td>
                                            <textarea class="form-control" name="taskda${noidda}" id="taskda${noidda}" rows="2">${data.task}</textarea>
                                        </td>
                                        <td>
                                            <select name="trainingda${noidda}" id="trainingda${noidda}" class="form-control">
                                                <option selected disabled>-- Select Training --</option>
                                                    ${TNA_TRAINING_OPTIONS.dataaware}
                                            </select>
                                            <br>
                                            <input type="text" name="otrda${noidda}" id="otrda${noidda}" class="form-control" value="${data.othertr}" />
                                        </td>
                                        <td>
                                            <select name="targetskda${noidda}" id="targetskda${noidda}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="currentskda${noidda}" id="currentskda${noidda}" class="form-control">
                                                <option value="1">1 - Fundamental Awareness</option>
                                                <option value="2">2 - Novice</option>
                                                <option value="3">3 - Intermediate</option>
                                                <option value="4">4 - Proficient</option>
                                                <option value="5">5 - Expert</option>
                                            </select>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="text" name="gapda${noidda}" id="gapda${noidda}" class="form-control" value="${data.gap}" readonly />
                                        </td>
                                        <td>
                                            <select name="trtypeda${noidda}" id="trtypeda${noidda}" class="form-control">
                                                <option selected disabled>-- Select Training Type --</option>
                                                <option value="1">1 - On Job Training</option>
                                                <option value="2">2 - Coaching</option>
                                                <option value="3">3 - External / In-house</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="datetrda${noidda}" id="datetrda${noidda}" class="form-control">
                                                <option value="Jan">Jan</option>
                                                <option value="Feb">Feb</option>
                                                <option value="Mar">Mar</option>
                                                <option value="Apr">Apr</option>
                                                <option value="May">May</option>
                                                <option value="Jun">Jun</option>
                                                <option value="Jul">Jul</option>
                                                <option value="Aug">Aug</option>
                                                <option value="Sep">Sep</option>
                                                <option value="Oct">Oct</option>
                                                <option value="Nov">Nov</option>
                                                <option value="Dec">Dec</option>
                                            </select>
                                        </td>
                                        <td>
                                            <a href="#" class="remove_driven btn btn-danger">
                                                <i class="fa fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                `;
                            targetskda.push(data.targetskill);
                            currentskda.push(data.currentskill);
                            trtypeda.push(data.trainingtype);
                            datetrda.push(data.monthapply);
                            trainingda.push(data.training);
                            noidda++;
                        } else if (data.section == 'functional') {
                            trHTMLfu += `
                                                <tr>
                                                    <td style="text-align:center;">${noidfu}</td>
                                                    <td>
                                                    <textarea class="form-control" name="taskfu${noidfu}" id="taskfu${noidfu}" rows="2">
                                                        ${data.task}
                                                    </textarea>
                                                    </td>
                                                    <td>
                                                    <select name="trainingfu${noidfu}" id="trainingfu${noidfu}" class="form-control">
                                                    <option selected disabled>-- Select Training --</option>
                                                        ${TNA_TRAINING_OPTIONS.functional}
                                                    </select>
                                                    <br>
                                                    <input type="text" name="otrfu${noidfu}" id="otrfu${noidfu}" class="form-control" value="${data.othertr}" />
                                                    </td>
                                                    <td>
                                                    <select name="targetskfu${noidfu}" id="targetskfu${noidfu}" class="form-control">
                                                        <option value="1">1 - Fundamental Awareness</option>
                                                        <option value="2">2 - Novice</option>
                                                        <option value="3">3 - Intermediate</option>
                                                        <option value="4">4 - Proficient</option>
                                                        <option value="5">5 - Expert</option>
                                                    </select>
                                                    </td>
                                                    <td>
                                                    <select name="currentskfu${noidfu}" id="currentskfu${noidfu}" class="form-control">
                                                        <option value="1">1 - Fundamental Awareness</option>
                                                        <option value="2">2 - Novice</option>
                                                        <option value="3">3 - Intermediate</option>
                                                        <option value="4">4 - Proficient</option>
                                                        <option value="5">5 - Expert</option>
                                                    </select>
                                                    </td>
                                                    <td style="text-align:center;">
                                                    <input type="text" name="gapfu${noidfu}" id="gapfu${noidfu}" class="form-control" value="${data.gap}" readonly />
                                                    </td>
                                                    <td>
                                                    <select name="trtypefu${noidfu}" id="trtypefu${noidfu}" class="form-control">
                                                        <option selected disabled>-- Select Training Type --</option>
                                                        <option value="1">1 - On Job Training</option>
                                                        <option value="2">2 - Coaching</option>
                                                        <option value="3">3 - External / In-house</option>
                                                    </select>
                                                    </td>
                                                    <td>
                                                    <select name="datetrfu${noidfu}" id="datetrfu${noidfu}" class="form-control">
                                                        <option value="Jan">Jan</option>
                                                        <option value="Feb">Feb</option>
                                                        <option value="Mar">Mar</option>
                                                        <option value="Apr">Apr</option>
                                                        <option value="May">May</option>
                                                        <option value="Jun">Jun</option>
                                                        <option value="Jul">Jul</option>
                                                        <option value="Aug">Aug</option>
                                                        <option value="Sep">Sep</option>
                                                        <option value="Oct">Oct</option>
                                                        <option value="Nov">Nov</option>
                                                        <option value="Dec">Dec</option>
                                                    </select>
                                                    </td>
                                                    <td>
                                                    <a href="#" class="remove_func btn btn-danger">
                                                        <i class="fa fa-trash"></i> Delete
                                                    </a>
                                                    </td>
                                                </tr>
                                                `;
                            targetskfu.push(data.targetskill);
                            currentskfu.push(data.currentskill);
                            trtypefu.push(data.trainingtype);
                            datetrfu.push(data.monthapply);
                            trainingfu.push(data.training);
                            noidfu++;
                        } else if (data.section == 'busiaware') {
                            trHTMLbu += `
                                            <tr>
                                                <td style="text-align:center;">${noidbu}</td>
                                                <td>
                                                    <textarea class="form-control" name="taskbu${noidbu}" id="taskbu${noidbu}" rows="2">${data.task}</textarea>
                                                </td>
                                                <td>
                                                    <select name="trainingbu${noidbu}" id="trainingbu${noidbu}" class="form-control">
                                                        <option selected disabled>-- Select Training --</option>
                                                            ${TNA_TRAINING_OPTIONS.busiaware}
                                                    </select>
                                                    <br>
                                                    <input type="text" name="otrbu${noidbu}" id="otrbu${noidbu}" class="form-control" value="${data.othertr}" />
                                                </td>
                                                <td>
                                                    <select name="targetskbu${noidbu}" id="targetskbu${noidbu}" class="form-control">
                                                        <option value="1">1 - Fundamental Awareness</option>
                                                        <option value="2">2 - Novice</option>
                                                        <option value="3">3 - Intermediate</option>
                                                        <option value="4">4 - Proficient</option>
                                                        <option value="5">5 - Expert</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="currentskbu${noidbu}" id="currentskbu${noidbu}" class="form-control">
                                                        <option value="1">1 - Fundamental Awareness</option>
                                                        <option value="2">2 - Novice</option>
                                                        <option value="3">3 - Intermediate</option>
                                                        <option value="4">4 - Proficient</option>
                                                        <option value="5">5 - Expert</option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;">
                                                    <input type="text" name="gapbu${noidbu}" id="gapbu${noidbu}" class="form-control" value="${data.gap}" readonly />
                                                </td>
                                                <td>
                                                    <select name="trtypebu${noidbu}" id="trtypebu${noidbu}" class="form-control">
                                                        <option selected disabled>-- Select Training Type --</option>
                                                        <option value="1">1 - On Job Training</option>
                                                        <option value="2">2 - Coaching</option>
                                                        <option value="3">3 - External / In-house</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="datetrbu${noidbu}" id="datetrbu${noidbu}" class="form-control">
                                                        <option value="Jan">Jan</option>
                                                        <option value="Feb">Feb</option>
                                                        <option value="Mar">Mar</option>
                                                        <option value="Apr">Apr</option>
                                                        <option value="May">May</option>
                                                        <option value="Jun">Jun</option>
                                                        <option value="Jul">Jul</option>
                                                        <option value="Aug">Aug</option>
                                                        <option value="Sep">Sep</option>
                                                        <option value="Oct">Oct</option>
                                                        <option value="Nov">Nov</option>
                                                        <option value="Dec">Dec</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <a href="#" class="remove_busi btn btn-danger"><i class="fa fa-trash"></i> Delete</a>
                                                </td>
                                            </tr>`;
                            targetskbu.push(data.targetskill);
                            currentskbu.push(data.currentskill);
                            trtypebu.push(data.trainingtype);
                            datetrbu.push(data.monthapply);
                            trainingbu.push(data.training);
                            noidbu++;
                        } else if (data.section == 'special') {
                            trHTMLsp += '<tr><td style="text-align:center;">' + noidsp +
                                '</td><td><textarea class="form-control" name="tasksp' +
                                noidsp + '" id="tasksp' + noidsp + '" rows="2">' + data
                                .task + '</textarea></td><td><select name="trainingsp' +
                                noidsp + '" id="trainingsp' + noidsp +
                                '" class="form-control"><option selected disabled="disabled">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.special + '</select><br><input type="text" name="otrsp' +
                                noidsp + '" id="otrsp' + noidsp +
                                '" class="form-control" value="' + data.othertr +
                                '"/></td><td><select name="targetsksp' + noidsp +
                                '" id="targetsksp' + noidsp +
                                '" class="form-control"><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td><select name="currentsksp' +
                                noidsp + '" id="currentsksp' + noidsp +
                                '" class="form-control"><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td style="text-align:center;"><input type="text" name="gapsp' +
                                noidsp + '" id="gapsp' + noidsp +
                                '" class="form-control" value="' + data.gap +
                                '" readonly /></td><td><select name="trtypesp' +
                                noidsp + '" id="trtypesp' + noidsp +
                                '" class="form-control"><option selected disabled="disabled">-- Select Training Type --</option><option value="1">1 - On Job Training</option><option value="2">2 - Coaching</option><option value="3">3 - External / In-house</option></select></td><td><select name="datetrsp' +
                                noidsp + '" id="datetrsp' + noidsp +
                                '" class="form-control"><option value="Jan">Jan</option><option value="Feb">Feb</option><option value="Mar">Mar</option><option value="Apr">Apr</option><option value="May">May</option><option value="Jun">Jun</option><option value="Jul">Jul</option><option value="Aug">Aug</option><option value="Sep">Sep</option><option value="Oct">Oct</option><option value="Nov">Nov</option><option value="Dec">Dec</option></select></td><td><a href="#" class="remove_spec btn btn-danger"><i class="fa fa-trash"></i> Delete</a></td></tr>';
                            targetsksp.push(data.targetskill);
                            currentsksp.push(data.currentskill);
                            trtypesp.push(data.trainingtype);
                            datetrsp.push(data.monthapply);
                            trainingsp.push(data.training);
                            noidsp++;
                        }
                    });
                    $('#tnaesglist').append(trHTMLes);
                    $('#tnaselflist').append(trHTMLse);
                    $('#tnaleadlist').append(trHTMLle);
                    $('#tnadrivenlist').append(trHTMLda);
                    $('#tnafunclist').append(trHTMLfu);
                    $('#tnabusilist').append(trHTMLbu);
                    $('#tnaspeclist').append(trHTMLsp);

                     // UPDATE ESG NEW

                     for (t = 1; t < noides; t++) {
                        $('#targetskes' + t).val(targetskes[t - 1]);
                        $('#currentskes' + t).val(currentskes[t - 1]);
                        $('#trtypees' + t).val(trtypees[t - 1]);
                        $('#datetres' + t).val(datetres[t - 1]);
                        $('#traininges' + t).val(traininges[t - 1]);

                        if ($('#traininges' + t).val() != 'OTHERS') {
                            $('#otres' + t).hide();
                        }

                        $('#traininges' + t).on('change', function() {
                            var ides = $(this).attr("id").slice(-1);
                            if ($(this).val() == 'OTHERS') {
                                $('#otres' + ides).show();
                            } else {
                                $('#otres' + ides).hide();
                            }
                        });

                        var targetes = parseInt($('#targetskes' + t).val());
                        var currentes = parseInt($('#currentskes' + t).val());

                        $('#targetskes' + t).on('change', function() {
                            var ides = $(this).attr("id").slice(-1);
                            var targetes = parseInt($('#targetskes' + idse).val());
                            var currentes = parseInt($('#currentskes' + idse).val());

                            var gapes = targetes - currentes;
                            $('#gapes' + ides).val(gapes);
                        });

                        $('#currentskes' + t).on('change', function() {
                            var ides = $(this).attr("id").slice(-1);
                            var targetes = parseInt($('#targetskes' + ides).val());
                            var currentes = parseInt($('#currentskes' + ides).val());

                            var gapes = targetes - currentes;
                            $('#gapes' + ides).val(gapes);
                        });
                    }

                    countesg = noides - 1;

                    $(document).on('click', '.remove_esg', function() {
                        countesg--;
                        $(this).closest('tr').remove();
                        return false;
                    });

                    $('#esgaware').val(countesg);

                    // UPDATE ESG NEW

                    for (t = 1; t < noidse; t++) {
                        $('#targetskse' + t).val(targetskse[t - 1]);
                        $('#currentskse' + t).val(currentskse[t - 1]);
                        $('#trtypese' + t).val(trtypese[t - 1]);
                        $('#datetrse' + t).val(datetrse[t - 1]);
                        $('#trainingse' + t).val(trainingse[t - 1]);

                        if ($('#trainingse' + t).val() != 'OTHERS') {
                            $('#otrse' + t).hide();
                        }

                        $('#trainingse' + t).on('change', function() {
                            var idse = $(this).attr("id").slice(-1);
                            if ($(this).val() == 'OTHERS') {
                                $('#otrse' + idse).show();
                            } else {
                                $('#otrse' + idse).hide();
                            }
                        });

                        var targetse = parseInt($('#targetskse' + t).val());
                        var currentse = parseInt($('#currentskse' + t).val());

                        $('#targetskse' + t).on('change', function() {
                            var idse = $(this).attr("id").slice(-1);
                            var targetse = parseInt($('#targetskse' + idse).val());
                            var currentse = parseInt($('#currentskse' + idse).val());

                            var gapse = targetse - currentse;
                            $('#gapse' + idse).val(gapse);
                        });

                        $('#currentskse' + t).on('change', function() {
                            var idse = $(this).attr("id").slice(-1);
                            var targetse = parseInt($('#targetskse' + idse).val());
                            var currentse = parseInt($('#currentskse' + idse).val());

                            var gapse = targetse - currentse;
                            $('#gapse' + idse).val(gapse);
                        });
                    }

                    countself = noidse - 1;

                    $(document).on('click', '.remove_self', function() {
                        countself--;
                        $(this).closest('tr').remove();
                        return false;
                    });

                    $('#selfaware').val(countself);

                    for (t = 1; t < noidle; t++) {
                        $('#targetskle' + t).val(targetskle[t - 1]);
                        $('#currentskle' + t).val(currentskle[t - 1]);
                        $('#trtypele' + t).val(trtypele[t - 1]);
                        $('#datetrle' + t).val(datetrle[t - 1]);
                        $('#trainingle' + t).val(trainingle[t - 1]);

                        if ($('#trainingle' + t).val() != 'OTHERS') {
                            $('#otrle' + t).hide();
                        }

                        $('#trainingle' + t).on('change', function() {
                            var idle = $(this).attr("id").slice(-1);
                            if ($(this).val() == 'OTHERS') {
                                $('#otrle' + idle).show();
                            } else {
                                $('#otrle' + idle).hide();
                            }
                        });

                        var targetle = parseInt($('#targetskle' + t).val());
                        var currentle = parseInt($('#currentskle' + t).val());

                        $('#targetskle' + t).on('change', function() {
                            var idle = $(this).attr("id").slice(-1);
                            var targetle = parseInt($('#targetskle' + idle).val());
                            var currentle = parseInt($('#currentskle' + idle).val());

                            var gaple = targetle - currentle;
                            $('#gaple' + idle).val(gaple);
                        });

                        $('#currentskle' + t).on('change', function() {
                            var idle = $(this).attr("id").slice(-1);
                            var targetle = parseInt($('#targetskle' + idle).val());
                            var currentle = parseInt($('#currentskle' + idle).val());

                            var gaple = targetle - currentle;
                            $('#gaple' + idle).val(gaple);
                        });
                    }

                    countlead = noidle - 1;

                    $(document).on('click', '.remove_lead', function() {
                        countlead--;
                        $(this).closest('tr').remove();
                        return false;
                    });

                    $('#leadaware').val(countlead);

                     // DATA DRIVEN UPDATE

                     for (t = 1; t < noidda; t++) {
                        $('#targetskda' + t).val(targetskda[t - 1]);
                        $('#currentskda' + t).val(currentskda[t - 1]);
                        $('#trtypeda' + t).val(trtypeda[t - 1]);
                        $('#datetrda' + t).val(datetrda[t - 1]);
                        $('#trainingda' + t).val(trainingda[t - 1]);

                        if ($('#trainingda' + t).val() != 'OTHERS') {
                            $('#otrda' + t).hide();
                        }

                        $('#trainingda' + t).on('change', function() {
                            var idda = $(this).attr("id").slice(-1);
                            if ($(this).val() == 'OTHERS') {
                                $('#otrda' + idda).show();
                            } else {
                                $('#otrda' + idda).hide();
                            }
                        });

                        var targetda = parseInt($('#targetskda' + t).val());
                        var currentda = parseInt($('#currentskda' + t).val());

                        $('#targetskda' + t).on('change', function() {
                            var idda = $(this).attr("id").slice(-1);
                            var targetda = parseInt($('#targetskda' + idda).val());
                            var currentda = parseInt($('#currentskda' + idda).val());

                            var gapda = targetda - currentda;
                            $('#gapda' + idda).val(gapda);
                        });

                        $('#currentskda' + t).on('change', function() {
                            var idda = $(this).attr("id").slice(-1);
                            var targetda = parseInt($('#targetskda' + idda).val());
                            var currentda = parseInt($('#currentskda' + idda).val());

                            var gapda = targetda - currentda;
                            $('#gapda' + idda).val(gapda);
                        });
                    }

                    countdriven = noidda - 1;

                    $(document).on('click', '.remove_driven', function() {
                        countdriven--;
                        $(this).closest('tr').remove();
                        return false;
                    });

                    $('#dataaware').val(countdriven);

                    // DATA DRIVEN UPDATE


                    for (t = 1; t < noidfu; t++) {
                        $('#targetskfu' + t).val(targetskfu[t - 1]);
                        $('#currentskfu' + t).val(currentskfu[t - 1]);
                        $('#trtypefu' + t).val(trtypefu[t - 1]);
                        $('#datetrfu' + t).val(datetrfu[t - 1]);
                        $('#trainingfu' + t).val(trainingfu[t - 1]);

                        if ($('#trainingfu' + t).val() != 'OTHERS') {
                            $('#otrfu' + t).hide();
                        }

                        $('#trainingfu' + t).on('change', function() {
                            var idfu = $(this).attr("id").slice(-1);
                            if ($(this).val() == 'OTHERS') {
                                $('#otrfu' + idfu).show();
                            } else {
                                $('#otrfu' + idfu).hide();
                            }
                        });

                        var targetfu = parseInt($('#targetskfu' + t).val());
                        var currentfu = parseInt($('#currentskfu' + t).val());

                        $('#targetskfu' + t).on('change', function() {
                            var idfu = $(this).attr("id").slice(-1);
                            var targetfu = parseInt($('#targetskfu' + idfu).val());
                            var currentfu = parseInt($('#currentskfu' + idfu).val());

                            var gapfu = targetfu - currentfu;
                            $('#gapfu' + idfu).val(gapfu);
                        });

                        $('#currentskfu' + t).on('change', function() {
                            var idfu = $(this).attr("id").slice(-1);
                            var targetfu = parseInt($('#targetskfu' + idfu).val());
                            var currentfu = parseInt($('#currentskfu' + idfu).val());

                            var gapfu = targetfu - currentfu;
                            $('#gapfu' + idfu).val(gapfu);
                        });
                    }

                    countfunc = noidfu - 1;

                    $(document).on('click', '.remove_func', function() {
                        countfunc--;
                        $(this).closest('tr').remove();
                        return false;
                    });

                    $('#functional').val(countfunc);

                    for (t = 1; t < noidbu; t++) {
                        $('#targetskbu' + t).val(targetskbu[t - 1]);
                        $('#currentskbu' + t).val(currentskbu[t - 1]);
                        $('#trtypebu' + t).val(trtypebu[t - 1]);
                        $('#datetrbu' + t).val(datetrbu[t - 1]);
                        $('#trainingbu' + t).val(trainingbu[t - 1]);

                        if ($('#trainingbu' + t).val() != 'OTHERS') {
                            $('#otrbu' + t).hide();
                        }

                        $('#trainingbu' + t).on('change', function() {
                            var idbu = $(this).attr("id").slice(-1);
                            if ($(this).val() == 'OTHERS') {
                                $('#otrbu' + idbu).show();
                            } else {
                                $('#otrbu' + idbu).hide();
                            }
                        });

                        var targetbu = parseInt($('#targetskbu' + t).val());
                        var currentbu = parseInt($('#currentskbu' + t).val());

                        $('#targetskbu' + t).on('change', function() {
                            var idbu = $(this).attr("id").slice(-1);
                            var targetbu = parseInt($('#targetskbu' + idbu).val());
                            var currentbu = parseInt($('#currentskbu' + idbu).val());

                            var gapbu = targetbu - currentbu;
                            $('#gapbu' + idbu).val(gapbu);
                        });

                        $('#currentskbu' + t).on('change', function() {
                            var idbu = $(this).attr("id").slice(-1);
                            var targetbu = parseInt($('#targetskbu' + idbu).val());
                            var currentbu = parseInt($('#currentskbu' + idbu).val());

                            var gapbu = targetbu - currentbu;
                            $('#gapbu' + idbu).val(gapbu);
                        });
                    }

                    countbusi = noidbu - 1;

                    $(document).on('click', '.remove_busi', function() {
                        countbusi--;
                        $(this).closest('tr').remove();
                        return false;
                    });

                    $('#busiaware').val(countbusi);

                    for (t = 1; t < noidsp; t++) {
                        $('#targetsksp' + t).val(targetsksp[t - 1]);
                        $('#currentsksp' + t).val(currentsksp[t - 1]);
                        $('#trtypesp' + t).val(trtypesp[t - 1]);
                        $('#datetrsp' + t).val(datetrsp[t - 1]);
                        $('#trainingsp' + t).val(trainingsp[t - 1]);

                        if ($('#trainingsp' + t).val() != 'OTHERS') {
                            $('#otrsp' + t).hide();
                        }

                        $('#trainingsp' + t).on('change', function() {
                            var idsp = $(this).attr("id").slice(-1);
                            if ($(this).val() == 'OTHERS') {
                                $('#otrsp' + idsp).show();
                            } else {
                                $('#otrsp' + idsp).hide();
                            }
                        });

                        var targetsp = parseInt($('#targetsksp' + t).val());
                        var currentsp = parseInt($('#currentsksp' + t).val());

                        $('#targetsksp' + t).on('change', function() {
                            var idsp = $(this).attr("id").slice(-1);
                            var targetsp = parseInt($('#targetsksp' + idsp).val());
                            var currentsp = parseInt($('#currentsksp' + idsp).val());

                            var gapsp = targetsp - currentsp;
                            $('#gapsp' + idsp).val(gapsp);
                        });

                        $('#currentsksp' + t).on('change', function() {
                            var idsp = $(this).attr("id").slice(-1);
                            var targetsp = parseInt($('#targetsksp' + idsp).val());
                            var currentsp = parseInt($('#currentsksp' + idsp).val());

                            var gapsp = targetsp - currentsp;
                            $('#gapsp' + idsp).val(gapsp);
                        });
                    }

                    countspec = noidsp - 1;

                    $(document).on('click', '.remove_spec', function() {
                        countspec--;
                        $(this).closest('tr').remove();
                        return false;
                    });

                    $('#special').val(countspec);
                }
            });
        }
    }
});

$('#add_esg').click(function() {
    countesg++;
    if (countesg == 1) {
        $("#tnaesglist").append(`
                <tr>
                    <td style="text-align:center;">${countesg}</td>
                    <td>
                        <textarea class="form-control" name="taskes${countesg}" id="taskes${countesg}" rows="2" placeholder="Insert your problem statement" autocomplete="off" required></textarea>
                    </td>
                    <td>
                        <select name="traininges${countesg}" id="traininges${countesg}" class="form-control" required>
                            <option selected disabled value="">-- Select Training --</option>
                                ${TNA_TRAINING_OPTIONS.esgaware}
                        </select>
                        <br>
                        <input type="text" name="otres${countesg}" id="otres${countesg}" class="form-control" placeholder="Others Training" autocomplete="off"/>
                    </td>
                    <td>
                        <select name="targetskes${countesg}" id="targetskes${countesg}" class="form-control" required>
                            <option value="1">1 - Fundamental Awareness</option>
                            <option value="2">2 - Novice</option>
                            <option value="3">3 - Intermediate</option>
                            <option value="4">4 - Proficient</option>
                            <option value="5">5 - Expert</option>
                        </select>
                    </td>
                    <td>
                        <select name="currentskes${countesg}" id="currentskes${countesg}" class="form-control" required>
                            <option value="1">1 - Fundamental Awareness</option>
                            <option value="2">2 - Novice</option>
                            <option value="3">3 - Intermediate</option>
                            <option value="4">4 - Proficient</option>
                            <option value="5">5 - Expert</option>
                        </select>
                    </td>
                    <td style="text-align:center;">
                        <input type="text" name="gapes${countesg}" id="gapes${countesg}" class="form-control" readonly />
                    </td>
                    <td>
                        <select name="trtypees${countesg}" id="trtypees${countesg}" class="form-control" required>
                            <option selected disabled value="">-- Select Training Type --</option>
                            <option value="1">1 - On Job Training</option>
                            <option value="2">2 - Coaching</option>
                            <option value="3">3 - External / In-house</option>
                        </select>
                    </td>
                    <td>
                        <select name="datetres${countesg}" id="datetres${countesg}" class="form-control" required>
                            <option value="Jan">Jan</option>
                            <option value="Feb">Feb</option>
                            <option value="Mar">Mar</option>
                            <option value="Apr">Apr</option>
                            <option value="May">May</option>
                            <option value="Jun">Jun</option>
                            <option value="Jul">Jul</option>
                            <option value="Aug">Aug</option>
                            <option value="Sep">Sep</option>
                            <option value="Oct">Oct</option>
                            <option value="Nov">Nov</option>
                            <option value="Dec">Dec</option>
                        </select>
                    </td>
                    <td>
                        <a href="#" class="remove_esg btn btn-danger">
                            <i class="fa fa-trash"></i> Delete
                        </a>
                    </td>
                </tr>
                `);
    } else if ($('#taskes' + (countesg - 1)).val() != '' && $('#traininges' + (countesg - 1)).val() != '' &&
        $('#trtypees' + (countesg - 1)).val() != null) {
        $("#tnaesglist").append(`
                <tr>
                    <td style="text-align:center;">${countesg}</td>
                    <td>
                        <textarea class="form-control" name="taskes${countesg}" id="taskes${countesg}" rows="2" placeholder="Insert your problem statement" autocomplete="off" required></textarea>
                    </td>
                    <td>
                        <select name="traininges${countesg}" id="traininges${countesg}" class="form-control" required>
                            <option selected disabled value="">-- Select Training --</option>
                                ${TNA_TRAINING_OPTIONS.esgaware}
                        </select>
                        <br>
                        <input type="text" name="otres${countesg}" id="otres${countesg}" class="form-control" placeholder="Others Training" autocomplete="off"/>
                    </td>
                    <td>
                        <select name="targetskes${countesg}" id="targetskes${countesg}" class="form-control" required>
                            <option value="1">1 - Fundamental Awareness</option>
                            <option value="2">2 - Novice</option>
                            <option value="3">3 - Intermediate</option>
                            <option value="4">4 - Proficient</option>
                            <option value="5">5 - Expert</option>
                        </select>
                    </td>
                    <td>
                        <select name="currentskes${countesg}" id="currentskes${countesg}" class="form-control" required>
                            <option value="1">1 - Fundamental Awareness</option>
                            <option value="2">2 - Novice</option>
                            <option value="3">3 - Intermediate</option>
                            <option value="4">4 - Proficient</option>
                            <option value="5">5 - Expert</option>
                        </select>
                    </td>
                    <td style="text-align:center;">
                        <input type="text" name="gapes${countesg}" id="gapes${countesg}" class="form-control" readonly />
                    </td>
                    <td>
                        <select name="trtypees${countesg}" id="trtypees${countesg}" class="form-control" required>
                            <option selected disabled value="">-- Select Training Type --</option>
                            <option value="1">1 - On Job Training</option>
                            <option value="2">2 - Coaching</option>
                            <option value="3">3 - External / In-house</option>
                        </select>
                    </td>
                    <td>
                        <select name="datetres${countesg}" id="datetres${countesg}" class="form-control" required>
                            <option value="Jan">Jan</option>
                            <option value="Feb">Feb</option>
                            <option value="Mar">Mar</option>
                            <option value="Apr">Apr</option>
                            <option value="May">May</option>
                            <option value="Jun">Jun</option>
                            <option value="Jul">Jul</option>
                            <option value="Aug">Aug</option>
                            <option value="Sep">Sep</option>
                            <option value="Oct">Oct</option>
                            <option value="Nov">Nov</option>
                            <option value="Dec">Dec</option>
                        </select>
                    </td>
                    <td>
                        <a href="#" class="remove_esg btn btn-danger">
                            <i class="fa fa-trash"></i> Delete
                        </a>
                    </td>
                </tr>
                `);
    } else if (countesg > 3) {
        alert('You can add up to 3 task only');
        countesg--;
    } else {
        alert('Please complete previous task details!');
        countesg--;
    }

    $('#gapes' + countesg).val(0);
    $('#otres' + countesg).hide();

    $('#traininges' + countesg).on('change', function() {
        if ($(this).val() == 'OTHERS') {
            $('#otres' + countesg).show();
        } else {
            $('#otres' + countesg).hide();
        }
    });

    $('#targetskes' + countesg).on('change', function() {
        var targetes = parseInt($('#targetskes' + countesg).val());
        var currentes = parseInt($('#currentskes' + countesg).val());

        var gapes = targetes - currentes;
        $('#gapes' + countesg).val(gapes);
    });

    $('#currentskes' + countesg).on('change', function() {
        var targetes = parseInt($('#targetskes' + countesg).val());
        var currentes = parseInt($('#currentskes' + countesg).val());

        var gapes = targetes - currentes;
        $('#gapes' + countesg).val(gapes);
    });

    $('#esgaware').val(countesg);
});

$(document).on('click', '.remove_esg', function() {
    countesg--;
    $(this).closest('tr').remove();
    return false;
});

$('#esgaware').val(countesg);


$('#add_self').click(function() {
    countself++;
    if (countself == 1) {
        $("#tnaselflist").append(
            '<tr>' +
                // Row count
                '<td style="text-align:center;">' + countself + '</td>' +

                // Problem statement
                '<td>' +
                    '<textarea class="form-control" ' +
                        'name="taskse' + countself + '" ' +
                        'id="taskse' + countself + '" ' +
                        'rows="2" ' +
                        'placeholder="Insert your problem statement" ' +
                        'autocomplete="off" ' +
                        'required>' +
                    '</textarea>' +
                '</td>' +

                // Training selection
                '<td>' +
                    '<select name="trainingse' + countself + '" ' +
                        'id="trainingse' + countself + '" ' +
                        'class="form-control" required>' +
                        '<option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.selfaware + '</select>' +
                    '<br>' +
                    '<input type="text" ' +
                        'name="otrse' + countself + '" ' +
                        'id="otrse' + countself + '" ' +
                        'class="form-control" ' +
                        'placeholder="Others Training" ' +
                        'autocomplete="off"/>' +
                '</td>' +

                // Target Skill Level
                '<td>' +
                    '<select name="targetskse' + countself + '" ' +
                        'id="targetskse' + countself + '" ' +
                        'class="form-control" required>' +
                        '<option value="1">1 - Fundamental Awareness</option>' +
                        '<option value="2">2 - Novice</option>' +
                        '<option value="3">3 - Intermediate</option>' +
                        '<option value="4">4 - Proficient</option>' +
                        '<option value="5">5 - Expert</option>' +
                    '</select>' +
                '</td>' +

                // Current Skill Level
                '<td>' +
                    '<select name="currentskse' + countself + '" ' +
                        'id="currentskse' + countself + '" ' +
                        'class="form-control" required>' +
                        '<option value="1">1 - Fundamental Awareness</option>' +
                        '<option value="2">2 - Novice</option>' +
                        '<option value="3">3 - Intermediate</option>' +
                        '<option value="4">4 - Proficient</option>' +
                        '<option value="5">5 - Expert</option>' +
                    '</select>' +
                '</td>' +

                // Gap
                '<td style="text-align:center;">' +
                    '<input type="text" ' +
                        'name="gapse' + countself + '" ' +
                        'id="gapse' + countself + '" ' +
                        'class="form-control" readonly />' +
                '</td>' +

                // Training Type
                '<td>' +
                    '<select name="trtypese' + countself + '" ' +
                        'id="trtypese' + countself + '" ' +
                        'class="form-control" required>' +
                        '<option selected disabled value="">-- Select Training Type --</option>' +
                        '<option value="1">1 - On Job Training</option>' +
                        '<option value="2">2 - Coaching</option>' +
                        '<option value="3">3 - External / In-house</option>' +
                    '</select>' +
                '</td>' +

                // Training Month
                '<td>' +
                    '<select name="datetrse' + countself + '" ' +
                        'id="datetrse' + countself + '" ' +
                        'class="form-control" required>' +
                        '<option value="Jan">Jan</option>' +
                        '<option value="Feb">Feb</option>' +
                        '<option value="Mar">Mar</option>' +
                        '<option value="Apr">Apr</option>' +
                        '<option value="May">May</option>' +
                        '<option value="Jun">Jun</option>' +
                        '<option value="Jul">Jul</option>' +
                        '<option value="Aug">Aug</option>' +
                        '<option value="Sep">Sep</option>' +
                        '<option value="Oct">Oct</option>' +
                        '<option value="Nov">Nov</option>' +
                        '<option value="Dec">Dec</option>' +
                    '</select>' +
                '</td>' +

                // Delete Button
                '<td>' +
                    '<a href="#" class="remove_self btn btn-danger">' +
                        '<i class="fa fa-trash"></i> Delete' +
                    '</a>' +
                '</td>' +
            '</tr>'
        );

    } else if ($('#taskse' + (countself - 1)).val() != '' && $('#trainingse' + (countself - 1)).val() != '' &&
        $('#trtypese' + (countself - 1)).val() != null) {
    $("#tnaselflist").append(
                    '<tr>' +
                        // Counter
                        '<td style="text-align:center;">' + countself + '</td>' +

                        // Problem Statement
                        '<td>' +
                            '<textarea class="form-control" ' +
                                'name="taskse' + countself + '" ' +
                                'id="taskse' + countself + '" ' +
                                'rows="2" ' +
                                'placeholder="Insert your problem statement" ' +
                                'autocomplete="off" ' +
                                'required>' +
                            '</textarea>' +
                        '</td>' +

                        // Training Dropdown + Others
                        '<td>' +
                            '<select name="trainingse' + countself + '" ' +
                                'id="trainingse' + countself + '" ' +
                                'class="form-control">' +
                                '<option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.selfaware + '</select>' +
                            '<br>' +
                            '<input type="text" ' +
                                'name="otrse' + countself + '" ' +
                                'id="otrse' + countself + '" ' +
                                'class="form-control" ' +
                                'placeholder="Others Training" ' +
                                'autocomplete="off"/>' +
                        '</td>' +

                        // Target Skill Level
                        '<td>' +
                            '<select name="targetskse' + countself + '" ' +
                                'id="targetskse' + countself + '" ' +
                                'class="form-control" required>' +
                                '<option value="1">1 - Fundamental Awareness</option>' +
                                '<option value="2">2 - Novice</option>' +
                                '<option value="3">3 - Intermediate</option>' +
                                '<option value="4">4 - Proficient</option>' +
                                '<option value="5">5 - Expert</option>' +
                            '</select>' +
                        '</td>' +

                        // Current Skill Level
                        '<td>' +
                            '<select name="currentskse' + countself + '" ' +
                                'id="currentskse' + countself + '" ' +
                                'class="form-control" required>' +
                                '<option value="1">1 - Fundamental Awareness</option>' +
                                '<option value="2">2 - Novice</option>' +
                                '<option value="3">3 - Intermediate</option>' +
                                '<option value="4">4 - Proficient</option>' +
                                '<option value="5">5 - Expert</option>' +
                            '</select>' +
                        '</td>' +

                        // Gap
                        '<td style="text-align:center;">' +
                            '<input type="text" ' +
                                'name="gapse' + countself + '" ' +
                                'id="gapse' + countself + '" ' +
                                'class="form-control" readonly />' +
                        '</td>' +

                        // Training Type
                        '<td>' +
                            '<select name="trtypese' + countself + '" ' +
                                'id="trtypese' + countself + '" ' +
                                'class="form-control" required>' +
                                '<option selected disabled value="">-- Select Training Type --</option>' +
                                '<option value="1">1 - On Job Training</option>' +
                                '<option value="2">2 - Coaching</option>' +
                                '<option value="3">3 - External / In-house</option>' +
                            '</select>' +
                        '</td>' +

                        // Training Month
                        '<td>' +
                            '<select name="datetrse' + countself + '" ' +
                                'id="datetrse' + countself + '" ' +
                                'class="form-control" required>' +
                                '<option value="Jan">Jan</option>' +
                                '<option value="Feb">Feb</option>' +
                                '<option value="Mar">Mar</option>' +
                                '<option value="Apr">Apr</option>' +
                                '<option value="May">May</option>' +
                                '<option value="Jun">Jun</option>' +
                                '<option value="Jul">Jul</option>' +
                                '<option value="Aug">Aug</option>' +
                                '<option value="Sep">Sep</option>' +
                                '<option value="Oct">Oct</option>' +
                                '<option value="Nov">Nov</option>' +
                                '<option value="Dec">Dec</option>' +
                            '</select>' +
                        '</td>' +

                        // Delete Button
                        '<td>' +
                            '<a href="#" class="remove_self btn btn-danger">' +
                                '<i class="fa fa-trash"></i> Delete' +
                            '</a>' +
                        '</td>' +
                    '</tr>'
                );

    } else if (countself > 3) {
        alert('You can add up to 3 task only');
        countself--;
    } else {
        alert('Please complete previous task details!');
        countself--;
    }

    $('#gapse' + countself).val(0);
    $('#otrse' + countself).hide();

    $('#trainingse' + countself).on('change', function() {
        if ($(this).val() == 'OTHERS') {
            $('#otrse' + countself).show();
        } else {
            $('#otrse' + countself).hide();
        }
    });

    $('#targetskse' + countself).on('change', function() {
        var targetse = parseInt($('#targetskse' + countself).val());
        var currentse = parseInt($('#currentskse' + countself).val());

        var gapse = targetse - currentse;
        $('#gapse' + countself).val(gapse);
    });

    $('#currentskse' + countself).on('change', function() {
        var targetse = parseInt($('#targetskse' + countself).val());
        var currentse = parseInt($('#currentskse' + countself).val());

        var gapse = targetse - currentse;
        $('#gapse' + countself).val(gapse);
    });

    $('#selfaware').val(countself);
});

$(document).on('click', '.remove_self', function() {
    countself--;
    $(this).closest('tr').remove();
    return false;
});

$('#selfaware').val(countself);


$('#add_lead').click(function() {
    countlead++;
    if (countlead == 1) {
    $("#tnaleadlist").append('<tr><td style="text-align:center;">' + countlead +
        '</td><td><textarea class="form-control" name="taskle' + countlead + '" id="taskle' +
        countlead +
        '" rows="2" placeholder="Insert problem statement" required></textarea></td><td><select name="trainingle' +
        countlead + '" id="trainingle' + countlead +
        '" class="form-control" required><option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.leadaware + '</select><br><input type="text" name="otrle' +
        countlead + '" id="otrle' + countlead +
        '" class="form-control" placeholder="Others Training" autocomplete="off"/></td><td><select name="targetskle' +
        countlead + '" id="targetskle' + countlead +
        '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td><select name="currentskle' +
        countlead + '" id="currentskle' + countlead +
        '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td style="text-align:center;"><input type="text" name="gaple' +
        countlead + '" id="gaple' + countlead +
        '" class="form-control" readonly /></td><td><select name="trtypele' + countlead +
        '" id="trtypele' + countlead +
        '" class="form-control" required><option selected disabled value="">-- Select Training Type --</option><option value="1">1 - On Job Training</option><option value="2">2 - Coaching</option><option value="3">3 - External / In-house</option></select></td><td><select name="datetrle' +
        countlead + '" id="datetrle' + countlead +
        '" class="form-control" required><option value="Jan">Jan</option><option value="Feb">Feb</option><option value="Mar">Mar</option><option value="Apr">Apr</option><option value="May">May</option><option value="Jun">Jun</option><option value="Jul">Jul</option><option value="Aug">Aug</option><option value="Sep">Sep</option><option value="Oct">Oct</option><option value="Nov">Nov</option><option value="Dec">Dec</option></select></td><td><a href="#" class="remove_lead btn btn-danger"><i class="fa fa-trash"></i> Delete</a></td></tr>'
    );
    } else if ($('#taskle' + (countlead - 1)).val() != '' && $('#trainingle' + (countlead - 1)).val() != '' &&
        $('#trtypele' + (countlead - 1)).val() != null) {
        $("#tnaleadlist").append('<tr><td style="text-align:center;">' + countlead +
            '</td><td><textarea class="form-control" name="taskle' + countlead + '" id="taskle' +
            countlead +
            '" rows="2" placeholder="Insert problem statement" required></textarea></td><td><select name="trainingle' +
            countlead + '" id="trainingle' + countlead +
            '" class="form-control" required><option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.leadaware + '</select><br><input type="text" name="otrle' +
            countlead + '" id="otrle' + countlead +
            '" class="form-control" placeholder="Others Training" autocomplete="off"/></td><td><select name="targetskle' +
            countlead + '" id="targetskle' + countlead +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td><select name="currentskle' +
            countlead + '" id="currentskle' + countlead +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td style="text-align:center;"><input type="text" name="gaple' +
            countlead + '" id="gaple' + countlead +
            '" class="form-control" readonly /></td><td><select name="trtypele' + countlead +
            '" id="trtypele' + countlead +
            '" class="form-control" required><option selected disabled value="">-- Select Training Type --</option><option value="1">1 - On Job Training</option><option value="2">2 - Coaching</option><option value="3">3 - External / In-house</option></select></td><td><select name="datetrle' +
            countlead + '" id="datetrle' + countlead +
            '" class="form-control" required><option value="Jan">Jan</option><option value="Feb">Feb</option><option value="Mar">Mar</option><option value="Apr">Apr</option><option value="May">May</option><option value="Jun">Jun</option><option value="Jul">Jul</option><option value="Aug">Aug</option><option value="Sep">Sep</option><option value="Oct">Oct</option><option value="Nov">Nov</option><option value="Dec">Dec</option></select></td><td><a href="#" class="remove_lead btn btn-danger"><i class="fa fa-trash"></i> Delete</a></td></tr>'
);

    } else if (countlead > 3) {
        alert('You can add up to 3 task only');
        countlead--;
    } else {
        alert('Please complete previous task details!');
        countlead--;
    }

    $('#gaple' + countlead).val(0);
    $('#otrle' + countlead).hide();

    $('#trainingle' + countlead).on('change', function() {
        if ($(this).val() == 'OTHERS') {
            $('#otrle' + countlead).show();
        } else {
            $('#otrle' + countlead).hide();
        }
    });

    $('#targetskle' + countlead).on('change', function() {
        var targetle = parseInt($('#targetskle' + countlead).val());
        var currentle = parseInt($('#currentskle' + countlead).val());

        var gaple = targetle - currentle;
        $('#gaple' + countlead).val(gaple);
    });

    $('#currentskle' + countlead).on('change', function() {
        var targetle = parseInt($('#targetskle' + countlead).val());
        var currentle = parseInt($('#currentskle' + countlead).val());

        var gaple = targetle - currentle;
        $('#gaple' + countlead).val(gaple);
    });

    $('#leadaware').val(countlead);
});

$(document).on('click', '.remove_lead', function() {
    countself--;
    $(this).closest('tr').remove();
    return false;
});

$('#leadaware').val(countlead);

$('#add_driven').click(function() {
    countdriven++;
    if (countdriven == 1) {
        $("#tnadrivenlist").append(`
                    <tr>
                        <td style="text-align:center;">${countdriven}</td>
                        <td>
                            <textarea class="form-control" name="taskda${countdriven}" id="taskda${countdriven}" rows="2" placeholder="Insert problem statement" required></textarea>
                        </td>
                        <td>
                            <select name="trainingda${countdriven}" id="trainingda${countdriven}" class="form-control" required>
                                                <option selected disabled value="">-- Select Training --</option>
                                                    ${TNA_TRAINING_OPTIONS.dataaware}
                            </select>
                            <br>
                            <input type="text" name="otrda${countdriven}" id="otrda${countdriven}" class="form-control" placeholder="Others Training" autocomplete="off" />
                        </td>
                        <td>
                            <select name="targetskda${countdriven}" id="targetskda${countdriven}" class="form-control" required>
                                <option value="1">1 - Fundamental Awareness</option>
                                <option value="2">2 - Novice</option>
                                <option value="3">3 - Intermediate</option>
                                <option value="4">4 - Proficient</option>
                                <option value="5">5 - Expert</option>
                            </select>
                        </td>
                        <td>
                            <select name="currentskda${countdriven}" id="currentskda${countdriven}" class="form-control" required>
                                <option value="1">1 - Fundamental Awareness</option>
                                <option value="2">2 - Novice</option>
                                <option value="3">3 - Intermediate</option>
                                <option value="4">4 - Proficient</option>
                                <option value="5">5 - Expert</option>
                            </select>
                        </td>
                        <td style="text-align:center;">
                            <input type="text" name="gapda${countdriven}" id="gapda${countdriven}" class="form-control" readonly />
                        </td>
                        <td>
                            <select name="trtypeda${countdriven}" id="trtypeda${countdriven}" class="form-control" required>
                                <option selected disabled value="">-- Select Training Type --</option>
                                <option value="1">1 - On Job Training</option>
                                <option value="2">2 - Coaching</option>
                                <option value="3">3 - External / In-house</option>
                            </select>
                        </td>
                        <td>
                            <select name="datetrda${countdriven}" id="datetrda${countdriven}" class="form-control" required>
                                <option value="Jan">Jan</option>
                                <option value="Feb">Feb</option>
                                <option value="Mar">Mar</option>
                                <option value="Apr">Apr</option>
                                <option value="May">May</option>
                                <option value="Jun">Jun</option>
                                <option value="Jul">Jul</option>
                                <option value="Aug">Aug</option>
                                <option value="Sep">Sep</option>
                                <option value="Oct">Oct</option>
                                <option value="Nov">Nov</option>
                                <option value="Dec">Dec</option>
                            </select>
                        </td>
                        <td>
                            <a href="#" class="remove_driven btn btn-danger">
                                <i class="fa fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                `);
    } else if ($('#taskda' + (countdriven - 1)).val() != '' && $('#trainingda' + (countdriven - 1)).val() !=
        '' &&
        $('#trtypeda' + (countdriven - 1)).val() != null) {
        $("#tnadrivenlist").append(`
                    <tr>
                        <td style="text-align:center;">${countdriven}</td>
                        <td>
                            <textarea class="form-control" name="taskda${countdriven}" id="taskda${countdriven}" rows="2" placeholder="Insert problem statement" required></textarea>
                        </td>
                        <td>
                            <select name="trainingda${countdriven}" id="trainingda${countdriven}" class="form-control" required>
                                                <option selected disabled value="">-- Select Training --</option>
                                                    ${TNA_TRAINING_OPTIONS.dataaware}
                            </select>
                            <br>
                            <input type="text" name="otrda${countdriven}" id="otrda${countdriven}" class="form-control" placeholder="Others Training" autocomplete="off"/>
                        </td>
                        <td>
                            <select name="targetskda${countdriven}" id="targetskda${countdriven}" class="form-control" required>
                                <option value="1">1 - Fundamental Awareness</option>
                                <option value="2">2 - Novice</option>
                                <option value="3">3 - Intermediate</option>
                                <option value="4">4 - Proficient</option>
                                <option value="5">5 - Expert</option>
                            </select>
                        </td>
                        <td>
                            <select name="currentskda${countdriven}" id="currentskda${countdriven}" class="form-control" required>
                                <option value="1">1 - Fundamental Awareness</option>
                                <option value="2">2 - Novice</option>
                                <option value="3">3 - Intermediate</option>
                                <option value="4">4 - Proficient</option>
                                <option value="5">5 - Expert</option>
                            </select>
                        </td>
                        <td style="text-align:center;">
                            <input type="text" name="gapda${countdriven}" id="gapda${countdriven}" class="form-control" readonly />
                        </td>
                        <td>
                            <select name="trtypeda${countdriven}" id="trtypeda${countdriven}" class="form-control" required>
                                <option selected disabled value="">-- Select Training Type --</option>
                                <option value="1">1 - On Job Training</option>
                                <option value="2">2 - Coaching</option>
                                <option value="3">3 - External / In-house</option>
                            </select>
                        </td>
                        <td>
                            <select name="datetrda${countdriven}" id="datetrda${countdriven}" class="form-control" required>
                                <option value="Jan">Jan</option>
                                <option value="Feb">Feb</option>
                                <option value="Mar">Mar</option>
                                <option value="Apr">Apr</option>
                                <option value="May">May</option>
                                <option value="Jun">Jun</option>
                                <option value="Jul">Jul</option>
                                <option value="Aug">Aug</option>
                                <option value="Sep">Sep</option>
                                <option value="Oct">Oct</option>
                                <option value="Nov">Nov</option>
                                <option value="Dec">Dec</option>
                            </select>
                        </td>
                        <td>
                            <a href="#" class="remove_driven btn btn-danger">
                                <i class="fa fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                `);
    } else if (countdriven > 3) {
        alert('You can add up to 3 task only');
        countdriven--;
    } else {
        alert('Please complete previous task details!');
        countdriven--;
    }

    $('#gapda' + countdriven).val(0);
    $('#otrda' + countdriven).hide();

    $('#trainingda' + countdriven).on('change', function() {
        if ($(this).val() == 'OTHERS') {
            $('#otrda' + countdriven).show();
        } else {
            $('#otrda' + countdriven).hide();
        }
    });

    $('#targetskda' + countdriven).on('change', function() {
        var targetda = parseInt($('#targetskda' + countdriven).val());
        var currentda = parseInt($('#currentskda' + countdriven).val());

        var gapda = targetda - currentda;
        $('#gapda' + countdriven).val(gapda);
    });

    $('#currentskda' + countdriven).on('change', function() {
        var targetda = parseInt($('#targetskda' + countdriven).val());
        var currentda = parseInt($('#currentskda' + countdriven).val());

        var gapda = targetda - currentda;
        $('#gapda' + countdriven).val(gapda);
    });

    $('#dataaware').val(countdriven);
});

$(document).on('click', '.remove_driven', function() {
    countdriven--;
    $(this).closest('tr').remove();
    return false;
});

$('#dataaware').val(countdriven);

$('#add_func').click(function() {
    countfunc++;
    if (countfunc == 1) {
        $("#tnafunclist").append(`
                            <tr>
                                <td style="text-align:center;">${countfunc}</td>
                                <td>
                                    <textarea class="form-control" name="taskfu${countfunc}" id="taskfu${countfunc}" rows="2" placeholder="Insert problem statement" required></textarea>
                                </td>
                                <td>
                                    <select name="trainingfu${countfunc}" id="trainingfu${countfunc}" class="form-control" required>
                                        <option selected disabled value="">-- Select Training --</option>
                                            ${TNA_TRAINING_OPTIONS.functional}
                                    </select>
                                    <br>
                                    <input type="text" name="otrfu${countfunc}" id="otrfu${countfunc}" class="form-control" placeholder="Others Training" autocomplete="off"/>
                                </td>
                                <td>
                                    <select name="targetskfu${countfunc}" id="targetskfu${countfunc}" class="form-control" required>
                                        <option value="1">1 - Fundamental Awareness</option>
                                        <option value="2">2 - Novice</option>
                                        <option value="3">3 - Intermediate</option>
                                        <option value="4">4 - Proficient</option>
                                        <option value="5">5 - Expert</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="currentskfu${countfunc}" id="currentskfu${countfunc}" class="form-control" required>
                                        <option value="1">1 - Fundamental Awareness</option>
                                        <option value="2">2 - Novice</option>
                                        <option value="3">3 - Intermediate</option>
                                        <option value="4">4 - Proficient</option>
                                        <option value="5">5 - Expert</option>
                                    </select>
                                </td>
                                <td style="text-align:center;">
                                    <input type="text" name="gapfu${countfunc}" id="gapfu${countfunc}" class="form-control" readonly />
                                </td>
                                <td>
                                    <select name="trtypefu${countfunc}" id="trtypefu${countfunc}" class="form-control" required>
                                        <option selected disabled value="">-- Select Training Type --</option>
                                        <option value="1">1 - On Job Training</option>
                                        <option value="2">2 - Coaching</option>
                                        <option value="3">3 - External / In-house</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="datetrfu${countfunc}" id="datetrfu${countfunc}" class="form-control" required>
                                        <option value="Jan">Jan</option>
                                        <option value="Feb">Feb</option>
                                        <option value="Mar">Mar</option>
                                        <option value="Apr">Apr</option>
                                        <option value="May">May</option>
                                        <option value="Jun">Jun</option>
                                        <option value="Jul">Jul</option>
                                        <option value="Aug">Aug</option>
                                        <option value="Sep">Sep</option>
                                        <option value="Oct">Oct</option>
                                        <option value="Nov">Nov</option>
                                        <option value="Dec">Dec</option>
                                    </select>
                                </td>
                                <td>
                                    <a href="#" class="remove_func btn btn-danger"><i class="fa fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                            `);

    } else if ($('#taskfu' + (countfunc - 1)).val() != '' && $('#trainingfu' + (countfunc - 1)).val() != '' &&
        $('#trtypefu' + (countfunc - 1)).val() != null) {
       $("#tnafunclist").append(`
                <tr>
                    <td style="text-align:center;">${countfunc}</td>

                    <!-- Problem Statement -->
                    <td>
                        <textarea class="form-control" 
                                name="taskfu${countfunc}" 
                                id="taskfu${countfunc}" 
                                rows="2" 
                                placeholder="Insert problem statement" 
                                required></textarea>
                    </td>

                    <!-- Training Selection -->
                    <td>
                        <select name="trainingfu${countfunc}" 
                                id="trainingfu${countfunc}" 
                                class="form-control" 
                                required>
                            <option selected disabled value="">-- Select Training --</option>
                                ${TNA_TRAINING_OPTIONS.functional}
                        </select>
                        <br>
                        <input type="text" 
                            name="otrfu${countfunc}" 
                            id="otrfu${countfunc}" 
                            class="form-control" 
                            placeholder="Others Training" 
                            autocomplete="off"/>
                    </td>

                    <!-- Target Skill Level -->
                    <td>
                        <select name="targetskfu${countfunc}" id="targetskfu${countfunc}" class="form-control" required>
                            <option value="1">1 - Fundamental Awareness</option>
                            <option value="2">2 - Novice</option>
                            <option value="3">3 - Intermediate</option>
                            <option value="4">4 - Proficient</option>
                            <option value="5">5 - Expert</option>
                        </select>
                    </td>

                    <!-- Current Skill Level -->
                    <td>
                        <select name="currentskfu${countfunc}" id="currentskfu${countfunc}" class="form-control" required>
                            <option value="1">1 - Fundamental Awareness</option>
                            <option value="2">2 - Novice</option>
                            <option value="3">3 - Intermediate</option>
                            <option value="4">4 - Proficient</option>
                            <option value="5">5 - Expert</option>
                        </select>
                    </td>

                    <!-- Gap -->
                    <td style="text-align:center;">
                        <input type="text" name="gapfu${countfunc}" id="gapfu${countfunc}" class="form-control" readonly />
                    </td>

                    <!-- Training Type -->
                    <td>
                        <select name="trtypefu${countfunc}" id="trtypefu${countfunc}" class="form-control" required>
                            <option selected disabled value="">-- Select Training Type --</option>
                            <option value="1">1 - On Job Training</option>
                            <option value="2">2 - Coaching</option>
                            <option value="3">3 - External / In-house</option>
                        </select>
                    </td>

                    <!-- Month -->
                    <td>
                        <select name="datetrfu${countfunc}" id="datetrfu${countfunc}" class="form-control" required>
                            <option value="Jan">Jan</option>
                            <option value="Feb">Feb</option>
                            <option value="Mar">Mar</option>
                            <option value="Apr">Apr</option>
                            <option value="May">May</option>
                            <option value="Jun">Jun</option>
                            <option value="Jul">Jul</option>
                            <option value="Aug">Aug</option>
                            <option value="Sep">Sep</option>
                            <option value="Oct">Oct</option>
                            <option value="Nov">Nov</option>
                            <option value="Dec">Dec</option>
                        </select>
                    </td>

                    <!-- Delete Button -->
                    <td>
                        <a href="#" class="remove_func btn btn-danger"><i class="fa fa-trash"></i> Delete</a>
                    </td>
                </tr>
                `);

    } else if (countfunc > 3) {
        alert('You can add up to 3 task only');
        countfunc--;
    } else {
        alert('Please complete previous task details!');
        countfunc--;
    }

    $('#gapfu' + countfunc).val(0);
    $('#otrfu' + countfunc).hide();

    $('#trainingfu' + countfunc).on('change', function() {
        if ($(this).val() == 'OTHERS') {
            $('#otrfu' + countfunc).show();
        } else {
            $('#otrfu' + countfunc).hide();
        }
    });

    $('#targetskfu' + countfunc).on('change', function() {
        var targetfu = parseInt($('#targetskfu' + countfunc).val());
        var currentfu = parseInt($('#currentskfu' + countfunc).val());

        var gapfu = targetfu - currentfu;
        $('#gapfu' + countfunc).val(gapfu);
    });

    $('#currentskfu' + countfunc).on('change', function() {
        var targetfu = parseInt($('#targetskfu' + countfunc).val());
        var currentfu = parseInt($('#currentskfu' + countfunc).val());

        var gapfu = targetfu - currentfu;
        $('#gapfu' + countfunc).val(gapfu);
    });

    $('#functional').val(countfunc);
});

$(document).on('click', '.remove_func', function() {
    countself--;
    $(this).closest('tr').remove();
    return false;
});

$('#functional').val(countfunc);

$('#add_busi').click(function() {
    countbusi++;
    if (countbusi == 1) {
    $("#tnabusilist").append('<tr>' +
        '<td style="text-align:center;">' + countbusi + '</td>' +
        '<td><textarea class="form-control" name="taskbu' + countbusi + '" id="taskbu' + countbusi + '" rows="2" placeholder="Insert problem statement" required></textarea></td>' +
        '<td><select name="trainingbu' + countbusi + '" id="trainingbu' + countbusi + '" class="form-control" required>' +
            '<option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.busiaware + '</select><br><input type="text" name="otrbu' + countbusi + '" id="otrbu' + countbusi + '" class="form-control" placeholder="Others Training" autocomplete="off"/></td>' +
        '<td><select name="targetskbu' + countbusi + '" id="targetskbu' + countbusi + '" class="form-control" required>' +
            '<option value="1">1 - Fundamental Awareness</option>' +
            '<option value="2">2 - Novice</option>' +
            '<option value="3">3 - Intermediate</option>' +
            '<option value="4">4 - Proficient</option>' +
            '<option value="5">5 - Expert</option>' +
        '</select></td>' +
        '<td><select name="currentskbu' + countbusi + '" id="currentskbu' + countbusi + '" class="form-control" required>' +
            '<option value="1">1 - Fundamental Awareness</option>' +
            '<option value="2">2 - Novice</option>' +
            '<option value="3">3 - Intermediate</option>' +
            '<option value="4">4 - Proficient</option>' +
            '<option value="5">5 - Expert</option>' +
        '</select></td>' +
        '<td style="text-align:center;"><input type="text" name="gapbu' + countbusi + '" id="gapbu' + countbusi + '" class="form-control" readonly /></td>' +
        '<td><select name="trtypebu' + countbusi + '" id="trtypebu' + countbusi + '" class="form-control" required>' +
            '<option selected disabled value="">-- Select Training Type --</option>' +
            '<option value="1">1 - On Job Training</option>' +
            '<option value="2">2 - Coaching</option>' +
            '<option value="3">3 - External / In-house</option>' +
        '</select></td>' +
        '<td><select name="datetrbu' + countbusi + '" id="datetrbu' + countbusi + '" class="form-control" required>' +
            '<option value="Jan">Jan</option>' +
            '<option value="Feb">Feb</option>' +
            '<option value="Mar">Mar</option>' +
            '<option value="Apr">Apr</option>' +
            '<option value="May">May</option>' +
            '<option value="Jun">Jun</option>' +
            '<option value="Jul">Jul</option>' +
            '<option value="Aug">Aug</option>' +
            '<option value="Sep">Sep</option>' +
            '<option value="Oct">Oct</option>' +
            '<option value="Nov">Nov</option>' +
            '<option value="Dec">Dec</option>' +
        '</select></td>' +
        '<td><a href="#" class="remove_busi btn btn-danger"><i class="fa fa-trash"></i> Delete</a></td>' +
    '</tr>');
    } else if ($('#taskbu' + (countbusi - 1)).val() != '' && $('#trainingbu' + (countbusi - 1)).val() != '' &&
        $('#trtypebu' + (countbusi - 1)).val() != null) {
        $("#tnabusilist").append('<tr><td style="text-align:center;">' + countbusi +
            '</td><td><textarea class="form-control" name="taskbu' + countbusi + '" id="taskbu' +
            countbusi +
            '" rows="2" placeholder="Insert problem statement" required></textarea></td><td><select name="trainingbu' +
            countbusi + '" id="trainingbu' + countbusi +
            '" class="form-control" required>' +
            '<option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.busiaware + '</select><br><input type="text" name="otrbu' +
            countbusi + '" id="otrbu' + countbusi +
            '" class="form-control" placeholder="Others Training" autocomplete="off"/></td><td><select name="targetskbu' +
            countbusi + '" id="targetskbu' + countbusi +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td><select name="currentskbu' +
            countbusi + '" id="currentskbu' + countbusi +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td style="text-align:center;"><input type="text" name="gapbu' +
            countbusi + '" id="gapbu' + countbusi +
            '" class="form-control" readonly /></td><td><select name="trtypebu' + countbusi +
            '" id="trtypebu' + countbusi +
            '" class="form-control" required><option selected disabled value="">-- Select Training Type --</option><option value="1">1 - On Job Training</option><option value="2">2 - Coaching</option><option value="3">3 - External / In-house</option></select></td><td><select name="datetrbu' +
            countbusi + '" id="datetrbu' + countbusi +
            '" class="form-control" required><option value="Jan">Jan</option><option value="Feb">Feb</option><option value="Mar">Mar</option><option value="Apr">Apr</option><option value="May">May</option><option value="Jun">Jun</option><option value="Jul">Jul</option><option value="Aug">Aug</option><option value="Sep">Sep</option><option value="Oct">Oct</option><option value="Nov">Nov</option><option value="Dec">Dec</option></select></td><td><a href="#" class="remove_busi btn btn-danger"><i class="fa fa-trash"></i> Delete</a></td></tr>'
            );
    } else if (countbusi > 3) {
        alert('You can add up to 3 task only');
        countbusi--;
    } else {
        alert('Please complete previous task details!');
        countbusi--;
    }

    $('#gapbu' + countbusi).val(0);
    $('#otrbu' + countbusi).hide();

    $('#trainingbu' + countbusi).on('change', function() {
        if ($(this).val() == 'OTHERS') {
            $('#otrbu' + countbusi).show();
        } else {
            $('#otrbu' + countbusi).hide();
        }
    });

    $('#targetskbu' + countbusi).on('change', function() {
        var targetbu = parseInt($('#targetskbu' + countbusi).val());
        var currentbu = parseInt($('#currentskbu' + countbusi).val());

        var gapbu = targetbu - currentbu;
        $('#gapbu' + countbusi).val(gapbu);
    });

    $('#currentskbu' + countbusi).on('change', function() {
        var targetbu = parseInt($('#targetskbu' + countbusi).val());
        var currentbu = parseInt($('#currentskbu' + countbusi).val());

        var gapbu = targetbu - currentbu;
        $('#gapbu' + countbusi).val(gapbu);
    });

    $('#busiaware').val(countbusi);
});

$(document).on('click', '.remove_busi', function() {
    countself--;
    $(this).closest('tr').remove();
    return false;
});

$('#busiaware').val(countbusi);

$('#add_spec').click(function() {
    countspec++;
    if (countspec == 1) {
        $("#tnaspeclist").append('<tr><td style="text-align:center;">' + countspec +
            '</td><td><textarea class="form-control" name="tasksp' + countspec + '" id="tasksp' +
            countspec +
            '" rows="2" placeholder="Insert problem statement" required></textarea></td><td><select name="trainingsp' +
            countspec + '" id="trainingsp' + countspec +
            '" class="form-control" required><option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.special + '</select><br><input type="text" name="otrsp' +
            countspec + '" id="otrsp' + countspec +
            '" class="form-control" placeholder="Others Training" autocomplete="off"/></td><td><select name="targetsksp' +
            countspec + '" id="targetsksp' + countspec +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td><select name="currentsksp' +
            countspec + '" id="currentsksp' + countspec +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td style="text-align:center;"><input type="text" name="gapsp' +
            countspec + '" id="gapsp' + countspec +
            '" class="form-control" readonly /></td><td><select name="trtypesp' + countspec +
            '" id="trtypesp' + countspec +
            '" class="form-control" required><option selected disabled value="">-- Select Training Type --</option><option value="1">1 - On Job Training</option><option value="2">2 - Coaching</option><option value="3">3 - External / In-house</option></select></td><td><select name="datetrsp' +
            countspec + '" id="datetrsp' + countspec +
            '" class="form-control" required><option value="Jan">Jan</option><option value="Feb">Feb</option><option value="Mar">Mar</option><option value="Apr">Apr</option><option value="May">May</option><option value="Jun">Jun</option><option value="Jul">Jul</option><option value="Aug">Aug</option><option value="Sep">Sep</option><option value="Oct">Oct</option><option value="Nov">Nov</option><option value="Dec">Dec</option></select></td><td><a href="#" class="remove_spec btn btn-danger"><i class="fa fa-trash"></i> Delete</a></td></tr>'
            );
    } else if ($('#tasksp' + (countspec - 1)).val() != '' && $('#trainingsp' + (countspec - 1)).val() != '' &&
        $('#trtypesp' + (countspec - 1)).val() != null) {
        $("#tnaspeclist").append('<tr><td style="text-align:center;">' + countspec +
            '</td><td><textarea class="form-control" name="tasksp' + countspec + '" id="tasksp' +
            countspec +
            '" rows="2" placeholder="Insert problem statement" required></textarea></td><td><select name="trainingsp' +
            countspec + '" id="trainingsp' + countspec +
            '" class="form-control" required><option selected disabled value="">-- Select Training --</option>' + TNA_TRAINING_OPTIONS.special + '</select><br><input type="text" name="otrsp' +
            countspec + '" id="otrsp' + countspec +
            '" class="form-control" placeholder="Others Training" autocomplete="off"/></td><td><select name="targetsksp' +
            countspec + '" id="targetsksp' + countspec +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td><select name="currentsksp' +
            countspec + '" id="currentsksp' + countspec +
            '" class="form-control" required><option value="1">1 - Fundamental Awareness</option><option value="2">2 - Novice</option><option value="3">3 - Intermediate</option><option value="4">4 - Proficient</option><option value="5">5 - Expert</option></select></td><td style="text-align:center;"><input type="text" name="gapsp' +
            countspec + '" id="gapsp' + countspec +
            '" class="form-control" readonly /></td><td><select name="trtypesp' + countspec +
            '" id="trtypesp' + countspec +
            '" class="form-control" required><option selected disabled value="">-- Select Training Type --</option><option value="1">1 - On Job Training</option><option value="2">2 - Coaching</option><option value="3">3 - External / In-house</option></select></td><td><select name="datetrsp' +
            countspec + '" id="datetrsp' + countspec +
            '" class="form-control" required><option value="Jan">Jan</option><option value="Feb">Feb</option><option value="Mar">Mar</option><option value="Apr">Apr</option><option value="May">May</option><option value="Jun">Jun</option><option value="Jul">Jul</option><option value="Aug">Aug</option><option value="Sep">Sep</option><option value="Oct">Oct</option><option value="Nov">Nov</option><option value="Dec">Dec</option></select></td><td><a href="#" class="remove_spec btn btn-danger"><i class="fa fa-trash"></i> Delete</a></td></tr>'
            );
    } else if (countspec > 3) {
        alert('You can add up to 3 task only');
        countspec--;
    } else {
        alert('Please complete previous task details!');
        countspec--;
    }

    $('#gapsp' + countspec).val(0);
    $('#otrsp' + countspec).hide();

    $('#trainingsp' + countspec).on('change', function() {
        if ($(this).val() == 'OTHERS') {
            $('#otrsp' + countspec).show();
        } else {
            $('#otrsp' + countspec).hide();
        }
    });

    $('#targetsksp' + countspec).on('change', function() {
        var targetsp = parseInt($('#targetsksp' + countspec).val());
        var currentsp = parseInt($('#currentsksp' + countspec).val());

        var gapsp = targetsp - currentsp;
        $('#gapsp' + countspec).val(gapsp);
    });

    $('#currentsksp' + countspec).on('change', function() {
        var targetsp = parseInt($('#targetsksp' + countspec).val());
        var currentsp = parseInt($('#currentsksp' + countspec).val());

        var gapbu = targetsp - currentsp;
        $('#gapsp' + countspec).val(gapbu);
    });

    $('#special').val(countspec);
});

$(document).on('click', '.remove_spec', function() {
    countspec--;
    $(this).closest('tr').remove();
    return false;
});

$('#special').val(countspec);

$(document).on('submit', '#tna_form', function(event) {
    event.preventDefault();
    $("#spinner-div").show();
    $("#userid").val(userid);
    $('#btn_action').val('addtnagrade');

    var form_data = $(this).serialize();
    console.log("form data: ", form_data);
    $.ajax({
        url: "tna_action.php",
        method: "POST",
        data: form_data,
        success: function(data) {
            console.log("json: ", data);
            var response = JSON.parse(data);
            console.log("reponse: ", response);
            if ((response.message) == 'insert') {
                swal(
                    'Added!',
                    'The TNA has been recorded.',
                    'success'
                ).then(function() {
                    window.location = "manage_grade.php";
                })
            } else if ((response.message) == 'error') {
                swal(
                    'Failed!',
                    'The operation cannot be done. Please refer to IT',
                    'error'
                ).then(function() {
                    $('#tna_form')[0].reset();
                })
            }
        },
        complete: function() {
            $("#spinner-div").hide();
        }
    })
});
</script>

</html>
<?php
    }else{
        header("Location: ../../login.php");
        exit();
    }
?>
