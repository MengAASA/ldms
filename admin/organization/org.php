<?php
session_start();

if (!isset($_SESSION['fullname']) || $_SESSION['role'] != 'ADMIN') {
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Learning and Development Management System</title>
    <script src="../../asset/js/jquery-1.10.2.min.js"></script>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css" />
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
    <style>
        .org-panel-body {
            padding: 0;
            max-height: 620px;
            overflow-y: auto;
        }
        .org-list-item {
            padding: 10px 14px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.15s;
        }
        .org-list-item:last-child { border-bottom: none; }
        .org-list-item:hover { background: #f0f4ff; }
        .org-list-item.active { background: #d9e8ff; border-left: 4px solid #337ab7; }
        .org-item-name { font-weight: 600; font-size: 13px; color: #333; }
        .org-item-meta { font-size: 11px; color: #888; margin-top: 2px; }
        .org-item-hod { font-size: 11px; color: #5a7; margin-top: 1px; }
        .org-item-head { font-size: 11px; color: #337ab7; margin-top: 1px; }
        .org-item-actions { float: right; display: flex; gap: 4px; }
        .panel-heading-actions { float: right; margin-top: -2px; }
        .panel-title-text { display: inline-block; margin-top: 2px; font-weight: 700; }
        .panel-empty { padding: 20px; text-align: center; color: #aaa; font-size: 13px; }
        .panel-loading { padding: 20px; text-align: center; color: #aaa; font-size: 13px; }
        #spinner-div {
            position: fixed; display: none; width: 100%; height: 100%;
            top: 0; left: 0; text-align: center;
            background-color: rgba(255,255,255,0.8); z-index: 9999;
        }
    </style>
</head>

<body onload="startTime()" style="background-image:url('../../asset/image/bg-try.png');zoom: 75%;">
<br>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-10">
            <img src="../../asset/image/lndlogo.gif" height="50" width="290">
        </div>
        <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;"></div>
    </div>

    <nav class="navbar navbar-inverse">
        <div class="container-fluid">
            <ul class="nav navbar-nav">
                <li><a href="../dashboard.php">HOME</a></li>
                <li><a href="../staff/staff.php">STAFF LIST</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">ALL TRAINING </a>
                    <ul class="dropdown-menu">
                        <li><a href="../training/public/training.php">PUBLIC/INHOUSE</a></li>
                        <li><a href="../training/ojt/training_ojt.php">OJT</a></li>
                        <li><a href="../training/departmental/training_dept.php">DEPARTMENTAL</a></li>
                    </ul>
                </li>
                <li><a href="../attendance/training.php">MY TRAINING</a></li>
                <li><a href="../tna/tna_list.php">TNA</a></li>
                <li><a href="../tni/tni_list.php">TNI</a></li>
                <li><a href="../tna/tna_summary.php">TNA SUMMARY</a></li>
                <li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li>
                <li class="active"><a href="org.php">ORGANIZATION</a></li>
                <li><a href="../archive/export.php">EXPORT</a></li>
                <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <?php echo htmlspecialchars($_SESSION['fullname']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="../../logout.php">LOGOUT</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div id="spinner-div">
        <img src="../../asset/image/loading.gif" title="working..." style="margin-top:350px;" />
    </div>

    <div class="panel panel-default" style="margin-bottom:20px;">
        <div class="panel-heading"><strong><i class="fa fa-sitemap"></i> ORGANIZATION STRUCTURE</strong></div>
        <div class="panel-body">
            <div id="hod-health" class="alert alert-warning" style="display:none;"></div>
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-body" style="padding:12px 14px;">
                            <strong style="margin-right:10px;"><i class="fa fa-file-excel"></i> Bulk Rename via Excel</strong>
                            <a href="export_org_template.php" class="btn btn-default btn-sm">
                                <i class="fa fa-download"></i> Download Template
                            </a>
                            <span style="margin-left:10px;">
                                <input type="file" id="import-file-input" accept=".xls,.xlsx" style="display:inline-block;width:auto;">
                                <button class="btn btn-primary btn-sm" onclick="uploadOrgTemplate()">
                                    <i class="fa fa-upload"></i> Upload &amp; Update
                                </button>
                            </span>
                            <div style="margin-top:8px;">
                                <label style="font-weight:normal;font-size:12px;color:#a94442;">
                                    <input type="checkbox" id="delete-missing-chk">
                                    Also delete rows removed from the sheet
                                    <span class="text-muted">(only if no staff are assigned to them)</span>
                                </label>
                            </div>
                            <div class="text-muted" style="font-size:11px;margin-top:6px;">
                                Download the template first, edit the Name / Short Name columns only (do not change the ID columns), then upload it here to bulk-update division, department, and section names.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- DIVISIONS -->
                <div class="col-md-4">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <span class="panel-title-text"><i class="fa fa-sitemap"></i> DIVISIONS</span>
                            <div class="panel-heading-actions">
                                <button class="btn btn-success btn-xs" onclick="openAddDivision()">
                                    <i class="fa fa-plus"></i> ADD
                                </button>
                            </div>
                        </div>
                        <div class="org-panel-body" id="division-list">
                            <div class="panel-loading"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- DEPARTMENTS -->
                <div class="col-md-4">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <span class="panel-title-text"><i class="fa fa-building"></i> DEPARTMENTS</span>
                            <span id="dept-division-label" style="font-size:11px;margin-left:8px;opacity:0.8;"></span>
                            <div class="panel-heading-actions">
                                <button class="btn btn-success btn-xs" id="btn-add-dept" onclick="openAddDepartment()" disabled>
                                    <i class="fa fa-plus"></i> ADD
                                </button>
                            </div>
                        </div>
                        <div class="org-panel-body" id="department-list">
                            <div class="panel-empty">Select a division to view its departments.</div>
                        </div>
                    </div>
                </div>

                <!-- SECTIONS -->
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <span class="panel-title-text"><i class="fa fa-layer-group"></i> SECTIONS</span>
                            <span id="sect-dept-label" style="font-size:11px;margin-left:8px;opacity:0.7;"></span>
                            <div class="panel-heading-actions">
                                <button class="btn btn-success btn-xs" id="btn-add-sect" onclick="openAddSection()" disabled>
                                    <i class="fa fa-plus"></i> ADD
                                </button>
                            </div>
                        </div>
                        <div class="org-panel-body" id="section-list">
                            <div class="panel-empty">Select a department to view its sections.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-default" style="margin-bottom:20px;">
        <div class="panel-heading"><strong><i class="fa fa-user-check"></i> SKILL MATRIX CONFIG</strong></div>
        <div class="panel-body">
            <div class="row">
                <!-- SKILL MATRIX WHITELIST -->
                <div class="col-md-6">
                    <div class="panel panel-warning">
                        <div class="panel-heading">
                            <span class="panel-title-text"><i class="fa fa-user-check"></i> WHITELIST</span>
                            <div class="panel-heading-actions">
                                <button class="btn btn-success btn-xs" onclick="openAddSmWhitelist()">
                                    <i class="fa fa-plus"></i> ADD
                                </button>
                            </div>
                        </div>
                        <div style="padding:8px 10px;border-bottom:1px solid #eee;">
                            <input type="text" id="sm-whitelist-search" class="form-control input-sm" placeholder="Search whitelist by name or staff no...">
                        </div>
                        <div class="org-panel-body" id="sm-whitelist-list">
                            <div class="panel-loading"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- DELETE SKILL MATRIX DATA -->
                <div class="col-md-6">
                    <div class="panel panel-danger">
                        <div class="panel-heading">
                            <span class="panel-title-text"><i class="fa fa-eraser"></i> DELETE DATA</span>
                        </div>
                        <div class="panel-body" style="padding:14px;">
                            <div class="form-group" style="position:relative;margin-bottom:6px;">
                                <label>Search Staff</label>
                                <input type="text" id="sm-delete-search" class="form-control" placeholder="Search by staff no or name..." autocomplete="off">
                                <input type="hidden" id="sm-delete-user-id">
                                <div id="sm-delete-results" class="list-group" style="display:none;position:absolute;z-index:1000;width:100%;max-height:220px;overflow-y:auto;"></div>
                            </div>
                            <div id="sm-delete-selected" style="display:none;">
                                <p style="margin-bottom:4px;"><strong id="sm-delete-selected-name"></strong> <span class="text-muted" id="sm-delete-selected-meta"></span></p>
                                <p id="sm-delete-count-label" class="text-muted"></p>
                                <button class="btn btn-danger btn-sm" id="sm-delete-btn" onclick="deleteSmData()">
                                    <i class="fa fa-trash"></i> Delete Skill Matrix Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SKILL MATRIX WHITELIST MODAL -->
<div class="modal fade" id="modalSmWhitelist" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add to Skill Matrix Whitelist</h4>
            </div>
            <div class="modal-body">
                <div class="form-group" style="position:relative;">
                    <label>Search Staff <span class="text-danger">*</span></label>
                    <input type="text" id="sm-add-search" class="form-control" placeholder="Search by staff no or name..." autocomplete="off">
                    <input type="hidden" id="sm-add-staffno">
                    <div id="sm-add-results" class="list-group" style="display:none;position:absolute;z-index:1000;width:100%;max-height:220px;overflow-y:auto;"></div>
                </div>
                <p id="sm-add-selected-label" class="text-muted"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveSmWhitelist()">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== DIVISION MODALS ===== -->
<div class="modal fade" id="modalDivision" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modalDivisionTitle">Division</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="div-id">
                <div class="form-group">
                    <label>Division Name <span class="text-danger">*</span></label>
                    <input type="text" id="div-name" class="form-control" placeholder="e.g. HUMAN CAPITAL & ESG">
                </div>
                <div class="form-group">
                    <label>Short Name</label>
                    <input type="text" id="div-shortname" class="form-control" placeholder="e.g. HCESG" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="btn-save-division" onclick="saveDivision()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ASSIGN DIVISION HEAD MODAL -->
<div class="modal fade" id="modalAssignDivisionHead" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Assign Head of Division</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="division-head-div-id">
                <p>Division: <strong id="division-head-div-name-label"></strong></p>
                <div class="form-group">
                    <label>Select Head of Division</label>
                    <select id="division-head-user-select" class="form-control">
                        <option value="">— No Head of Division —</option>
                    </select>
                </div>
                <div id="division-head-no-managers-msg" class="text-warning" style="display:none;font-size:12px;">
                    <i class="fa fa-exclamation-triangle"></i> No managers found in this division. Add a staff member with designation "MANAGER (AM/HOS & ABOVE)" first.
                </div>
                <p class="text-muted" style="font-size:11px;margin-top:6px;">
                    The Head of Division sits above department HODs in this division - use this to identify who a department HOD reports to.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveDivisionHead()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== DEPARTMENT MODALS ===== -->
<div class="modal fade" id="modalDepartment" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modalDeptTitle">Department</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="dept-id">
                <input type="hidden" id="dept-division-id">
                <div class="form-group">
                    <label>Department Name <span class="text-danger">*</span></label>
                    <input type="text" id="dept-name" class="form-control" placeholder="e.g. FINANCE">
                </div>
                <div class="form-group">
                    <label>Short Name</label>
                    <input type="text" id="dept-shortname" class="form-control" placeholder="e.g. FIN" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveDepartment()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- MOVE DEPARTMENT MODAL -->
<div class="modal fade" id="modalMoveDept" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Move Department to Another Division</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="move-dept-id">
                <p>Moving: <strong id="move-dept-name-label"></strong></p>
                <div class="form-group">
                    <label>Target Division <span class="text-danger">*</span></label>
                    <select id="move-dept-division-select" class="form-control"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="moveDepartment()">Move</button>
            </div>
        </div>
    </div>
</div>

<!-- ASSIGN HOD MODAL -->
<div class="modal fade" id="modalAssignHod" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Assign Head of Department</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="hod-dept-id">
                <p>Department: <strong id="hod-dept-name-label"></strong></p>
                <div class="form-group">
                    <label>Select HOD (Manager)</label>
                    <select id="hod-user-select" class="form-control">
                        <option value="">— No HOD —</option>
                    </select>
                </div>
                <div id="hod-no-managers-msg" class="text-warning" style="display:none;font-size:12px;">
                    <i class="fa fa-exclamation-triangle"></i> No managers found in this department. Add a staff member with designation "MANAGER (AM/HOS & ABOVE)" first.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveHod()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== SECTION MODALS ===== -->
<div class="modal fade" id="modalSection" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modalSectTitle">Section</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sect-id">
                <input type="hidden" id="sect-department-id">
                <div class="form-group">
                    <label>Section Name <span class="text-danger">*</span></label>
                    <input type="text" id="sect-name" class="form-control" placeholder="e.g. LEARNING & DEVELOPMENT">
                </div>
                <div class="form-group">
                    <label>Short Name</label>
                    <input type="text" id="sect-shortname" class="form-control" placeholder="e.g. L&D" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveSection()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- TRANSFER STAFF MODAL -->
<div class="modal fade" id="modalTransferStaff" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Transfer Staff &mdash; <span id="transfer-dept-name-label"></span></h4>
            </div>
            <div class="modal-body" style="padding-bottom:0;">
                <input type="hidden" id="transfer-from-dept-id">
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-6">
                        <input type="text" id="transfer-staff-search" class="form-control input-sm" placeholder="Search by name or staff no...">
                    </div>
                    <div class="col-md-6" style="text-align:right;padding-top:4px;">
                        <button class="btn btn-xs btn-default" onclick="toggleSelectAll(true)">Select All</button>
                        <button class="btn btn-xs btn-default" onclick="toggleSelectAll(false)">Deselect All</button>
                        <span id="selected-count-label" class="text-muted" style="font-size:12px;margin-left:8px;">0 selected</span>
                    </div>
                </div>
                <div style="max-height:280px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;margin-bottom:12px;">
                    <table class="table table-condensed table-hover" style="margin-bottom:0;">
                        <thead style="background:#f5f5f5;position:sticky;top:0;">
                            <tr>
                                <th style="width:36px;"></th>
                                <th>Staff No.</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Current Section</th>
                            </tr>
                        </thead>
                        <tbody id="transfer-staff-tbody">
                            <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <hr style="margin:8px 0 12px;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" style="margin-bottom:8px;">
                            <label style="font-size:13px;">Target Department <span class="text-danger">*</span></label>
                            <select id="transfer-target-dept" class="form-control input-sm" onchange="onTransferDeptChange()">
                                <option value="">— Select target department —</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" style="margin-bottom:8px;">
                            <label style="font-size:13px;">Target Section <small class="text-muted">(optional)</small></label>
                            <select id="transfer-target-section" class="form-control input-sm">
                                <option value="">— No specific section —</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="executeTransfer()"><i class="fa fa-share-alt"></i> Transfer Selected</button>
            </div>
        </div>
    </div>
</div>

<!-- MOVE SECTION MODAL -->
<div class="modal fade" id="modalMoveSect" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Move Section to Another Department</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="move-sect-id">
                <p>Moving: <strong id="move-sect-name-label"></strong></p>
                <div class="form-group">
                    <label>Target Department <span class="text-danger">*</span></label>
                    <select id="move-sect-dept-select" class="form-control"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="moveSection()">Move</button>
            </div>
        </div>
    </div>
</div>

<script>
    var selectedDivisionId = null;
    var selectedDivisionName = '';
    var selectedDepartmentId = null;
    var selectedDepartmentName = '';

    function startTime() {
        var today = new Date();
        var h = today.getHours(), m = today.getMinutes(), s = today.getSeconds();
        m = m < 10 ? '0' + m : m;
        s = s < 10 ? '0' + s : s;
        document.getElementById('txt').innerHTML = h + ':' + m + ':' + s;
        setTimeout(startTime, 500);
    }

    function showSpinner() { $('#spinner-div').show(); }
    function hideSpinner() { $('#spinner-div').hide(); }

    function alertError(msg) { swal('Error', msg || 'An error occurred.', 'error'); }
    function alertSuccess(msg) { swal('Success', msg, 'success'); }

    // ===== HOD LINK CHECK =====

    // Staff records carry their own copy of the department's HOD. Shown only
    // when the two disagree; refreshed with the division list.
    function loadHodHealth() {
        $.post('fetch_org.php', { action: 'load_hod_health' }, function (data) {
            var box = $('#hod-health');
            if (!data || (!data.staff_count && (!data.departments || data.departments.length === 0))) {
                box.hide().empty();
                return;
            }
            var html = '<strong><i class="fa fa-exclamation-triangle"></i> HOD links need attention</strong>';
            if (data.departments && data.departments.length) {
                html += '<ul style="margin:6px 0 0;">';
                $.each(data.departments, function (i, row) {
                    html += '<li><strong>' + escHtml(row.department) + '</strong>: ' + escHtml(row.issue) + '</li>';
                });
                html += '</ul>';
            }
            if (data.staff_count) {
                html += '<div style="margin-top:8px;">' + data.staff_count + ' active staff do not report to their department\'s HOD. '
                    + 'To correct one, open it in Staff List and save it; to correct a whole department, re-assign its HOD here.</div>';
                html += '<table class="table table-condensed" style="margin:6px 0 0;background:transparent;"><thead><tr>'
                    + '<th>Staff</th><th>Department</th><th>Reports to now</th><th>Should report to</th></tr></thead><tbody>';
                $.each(data.staff, function (i, row) {
                    var current = row.current_staffno ? escHtml(row.current_name) + ' (' + escHtml(row.current_staffno) + ')' : 'Nobody';
                    var expected = (row.is_dept_hod == 1) ? 'Nobody (is the HOD)'
                        : (row.expected_staffno ? escHtml(row.expected_name) + ' (' + escHtml(row.expected_staffno) + ')' : 'Nobody (no HOD assigned)');
                    html += '<tr><td>' + escHtml(row.staffname) + ' (' + escHtml(row.staffno) + ')</td><td>' + escHtml(row.department)
                        + '</td><td>' + current + '</td><td>' + expected + '</td></tr>';
                });
                html += '</tbody></table>';
                if (data.staff_count > data.staff.length) {
                    html += '<div class="text-muted">Showing the first ' + data.staff.length + '.</div>';
                }
            }
            box.html(html).show();
        }, 'json');
    }

    // ===== DIVISIONS =====

    function loadDivisions() {
        loadHodHealth();
        $.post('fetch_org.php', { action: 'load_divisions' }, function (data) {
            var html = '';
            if (!data || data.length === 0) {
                html = '<div class="panel-empty">No divisions found. Click ADD to create one.</div>';
            } else {
                $.each(data, function (i, row) {
                    var activeClass = (row.id == selectedDivisionId) ? ' active' : '';
                    html += '<div class="org-list-item' + activeClass + '" onclick="selectDivision(' + row.id + ', \'' + escJs(row.name) + '\')">';
                    html += '<div class="org-item-actions">';
                    html += '<button class="btn btn-xs btn-default" title="Edit" onclick="event.stopPropagation();openEditDivision(' + row.id + ',\'' + escJs(row.name) + '\',\'' + escJs(row.shortname || '') + '\')"><i class="fa fa-pencil-alt"></i></button>';
                    html += '<button class="btn btn-xs btn-info" title="Assign Head of Division" onclick="event.stopPropagation();openAssignDivisionHead(' + row.id + ',\'' + escJs(row.name) + '\',' + (row.head_user_id || 'null') + ')"><i class="fa fa-user-tie"></i></button>';
                    html += '<button class="btn btn-xs btn-danger" title="Delete" onclick="event.stopPropagation();deleteDivision(' + row.id + ',\'' + escJs(row.name) + '\')"><i class="fa fa-trash"></i></button>';
                    html += '</div>';
                    html += '<div class="org-item-name">' + escHtml(row.name) + (row.shortname ? ' <small class="text-muted">(' + escHtml(row.shortname) + ')</small>' : '') + '</div>';
                    var headLabel = row.head_name ? '<i class="fa fa-user-tie"></i> ' + escHtml(row.head_name) + (row.head_staffno ? ' (' + escHtml(row.head_staffno) + ')' : '') : '<span class="text-muted">No Head of Division assigned</span>';
                    html += '<div class="org-item-head">' + headLabel + '</div>';
                    html += '<div class="org-item-meta">' + row.dept_count + ' dept(s) &middot; ' + row.user_count + ' active staff</div>';
                    html += '</div>';
                });
            }
            $('#division-list').html(html);
        }, 'json');
    }

    function selectDivision(id, name) {
        selectedDivisionId = id;
        selectedDivisionName = name;
        selectedDepartmentId = null;
        selectedDepartmentName = '';
        $('#btn-add-dept').prop('disabled', false);
        $('#dept-division-label').text('— ' + name);
        $('#btn-add-sect').prop('disabled', true);
        $('#sect-dept-label').text('');
        $('#section-list').html('<div class="panel-empty">Select a department to view its sections.</div>');
        loadDivisions();
        loadDepartments();
    }

    function openAddDivision() {
        $('#modalDivisionTitle').text('Add Division');
        $('#div-id').val('');
        $('#div-name').val('');
        $('#div-shortname').val('');
        $('#modalDivision').modal('show');
    }

    function openEditDivision(id, name, shortname) {
        $('#modalDivisionTitle').text('Edit Division');
        $('#div-id').val(id);
        $('#div-name').val(name);
        $('#div-shortname').val(shortname);
        $('#modalDivision').modal('show');
    }

    function saveDivision() {
        var id = $('#div-id').val();
        var name = $.trim($('#div-name').val());
        var shortname = $.trim($('#div-shortname').val());
        if (!name) { alertError('Division name is required.'); return; }
        var btnAction = id ? 'rename_division' : 'add_division';
        var payload = { btn_action: btnAction, name: name, shortname: shortname };
        if (id) payload.id = id;
        showSpinner();
        $.post('org_action.php', payload, function (res) {
            hideSpinner();
            if (res.message === 'insert' || res.message === 'update') {
                $('#modalDivision').modal('hide');
                loadDivisions();
                if (selectedDivisionId && id == selectedDivisionId) loadDepartments();
            } else {
                alertError(res.detail || 'Failed to save division.');
            }
        }, 'json');
    }

    function deleteDivision(id, name) {
        swal({ title: 'Delete Division?', text: '"' + name + '" and all its departments and sections will be removed. This cannot be undone.', icon: 'warning', buttons: ['Cancel', 'Delete'], dangerMode: true })
            .then(function (confirmed) {
                if (!confirmed) return;
                showSpinner();
                $.post('org_action.php', { btn_action: 'delete_division', id: id }, function (res) {
                    hideSpinner();
                    if (res.message === 'delete') {
                        if (id == selectedDivisionId) {
                            selectedDivisionId = null;
                            selectedDivisionName = '';
                            selectedDepartmentId = null;
                            $('#dept-division-label').text('');
                            $('#btn-add-dept').prop('disabled', true);
                            $('#department-list').html('<div class="panel-empty">Select a division to view its departments.</div>');
                            $('#section-list').html('<div class="panel-empty">Select a department to view its sections.</div>');
                        }
                        loadDivisions();
                    } else {
                        alertError(res.detail || 'Cannot delete division.');
                    }
                }, 'json');
            });
    }

    function openAssignDivisionHead(divId, divName, currentHeadId) {
        $('#division-head-div-id').val(divId);
        $('#division-head-div-name-label').text(divName);
        var sel = $('#division-head-user-select');
        sel.html('<option value="">Loading managers...</option>');
        $('#division-head-no-managers-msg').hide();
        $.post('fetch_org.php', { action: 'load_division_head_candidates', division_id: divId }, function (data) {
            var opts = '<option value="">— No Head of Division —</option>';
            if (!data || data.length === 0) {
                $('#division-head-no-managers-msg').show();
            } else {
                $.each(data, function (i, row) {
                    var selected = (row.id == currentHeadId) ? ' selected' : '';
                    opts += '<option value="' + row.id + '"' + selected + '>' + escHtml(row.staffname) + ' (' + escHtml(row.staffno) + ')</option>';
                });
            }
            sel.html(opts);
        }, 'json');
        $('#modalAssignDivisionHead').modal('show');
    }

    function saveDivisionHead() {
        var divId = $('#division-head-div-id').val();
        var headUserId = $('#division-head-user-select').val();
        showSpinner();
        $.post('org_action.php', { btn_action: 'assign_division_head', division_id: divId, head_user_id: headUserId }, function (res) {
            hideSpinner();
            if (res.message === 'update') {
                $('#modalAssignDivisionHead').modal('hide');
                loadDivisions();
                swal('Head of Division Updated', 'Head of Division has been assigned.', 'success');
            } else {
                alertError(res.detail || 'Failed to assign Head of Division.');
            }
        }, 'json');
    }

    // ===== DEPARTMENTS =====

    function loadDepartments() {
        if (!selectedDivisionId) return;
        $('#department-list').html('<div class="panel-loading"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
        $.post('fetch_org.php', { action: 'load_departments', division_id: selectedDivisionId }, function (data) {
            var html = '';
            if (!data || data.length === 0) {
                html = '<div class="panel-empty">No departments in this division. Click ADD to create one.</div>';
            } else {
                $.each(data, function (i, row) {
                    var activeClass = (row.id == selectedDepartmentId) ? ' active' : '';
                    html += '<div class="org-list-item' + activeClass + '" onclick="selectDepartment(' + row.id + ', \'' + escJs(row.name) + '\')">';
                    html += '<div class="org-item-actions">';
                    html += '<button class="btn btn-xs btn-default" title="Edit" onclick="event.stopPropagation();openEditDepartment(' + row.id + ',\'' + escJs(row.name) + '\',\'' + escJs(row.shortname || '') + '\')"><i class="fa fa-pencil-alt"></i></button>';
                    html += '<button class="btn btn-xs btn-warning" title="Move to another division" onclick="event.stopPropagation();openMoveDepartment(' + row.id + ',\'' + escJs(row.name) + '\')"><i class="fa fa-exchange-alt"></i></button>';
                    html += '<button class="btn btn-xs btn-info" title="Assign HOD" onclick="event.stopPropagation();openAssignHod(' + row.id + ',\'' + escJs(row.name) + '\',' + (row.hod_user_id || 'null') + ')"><i class="fa fa-id-badge"></i></button>';
                    html += '<button class="btn btn-xs btn-success" title="Transfer Staff" onclick="event.stopPropagation();openTransferStaff(' + row.id + ',\'' + escJs(row.name) + '\')"><i class="fa fa-share-alt"></i></button>';
                    html += '<button class="btn btn-xs btn-danger" title="Delete" onclick="event.stopPropagation();deleteDepartment(' + row.id + ',\'' + escJs(row.name) + '\')"><i class="fa fa-trash"></i></button>';
                    html += '</div>';
                    html += '<div class="org-item-name">' + escHtml(row.name) + (row.shortname ? ' <small class="text-muted">(' + escHtml(row.shortname) + ')</small>' : '') + '</div>';
                    var hodLabel = row.hod_name ? '<i class="fa fa-id-badge" style="color:#5a7;"></i> ' + escHtml(row.hod_name) + (row.hod_staffno ? ' (' + escHtml(row.hod_staffno) + ')' : '') : '<span style="color:#c00;font-size:11px;"><i class="fa fa-exclamation-circle"></i> No HOD assigned</span>';
                    html += '<div class="org-item-hod">' + hodLabel + '</div>';
                    html += '<div class="org-item-meta">' + row.section_count + ' section(s) &middot; ' + row.user_count + ' active staff</div>';
                    html += '</div>';
                });
            }
            $('#department-list').html(html);
        }, 'json');
    }

    function selectDepartment(id, name) {
        selectedDepartmentId = id;
        selectedDepartmentName = name;
        $('#btn-add-sect').prop('disabled', false);
        $('#sect-dept-label').text('— ' + name);
        loadDepartments();
        loadSections();
    }

    function openAddDepartment() {
        if (!selectedDivisionId) return;
        $('#modalDeptTitle').text('Add Department');
        $('#dept-id').val('');
        $('#dept-division-id').val(selectedDivisionId);
        $('#dept-name').val('');
        $('#dept-shortname').val('');
        $('#modalDepartment').modal('show');
    }

    function openEditDepartment(id, name, shortname) {
        $('#modalDeptTitle').text('Edit Department');
        $('#dept-id').val(id);
        $('#dept-division-id').val(selectedDivisionId);
        $('#dept-name').val(name);
        $('#dept-shortname').val(shortname);
        $('#modalDepartment').modal('show');
    }

    function saveDepartment() {
        var id = $('#dept-id').val();
        var divisionId = $('#dept-division-id').val();
        var name = $.trim($('#dept-name').val());
        var shortname = $.trim($('#dept-shortname').val());
        if (!name) { alertError('Department name is required.'); return; }
        var btnAction = id ? 'rename_department' : 'add_department';
        var payload = { btn_action: btnAction, name: name, shortname: shortname, division_id: divisionId };
        if (id) payload.id = id;
        showSpinner();
        $.post('org_action.php', payload, function (res) {
            hideSpinner();
            if (res.message === 'insert' || res.message === 'update') {
                $('#modalDepartment').modal('hide');
                loadDepartments();
            } else {
                alertError(res.detail || 'Failed to save department.');
            }
        }, 'json');
    }

    function openMoveDepartment(id, name) {
        $('#move-dept-id').val(id);
        $('#move-dept-name-label').text(name);
        var sel = $('#move-dept-division-select');
        sel.html('<option value="">Loading...</option>');
        $.post('fetch_org.php', { action: 'load_divisions' }, function (data) {
            var opts = '';
            $.each(data, function (i, row) {
                if (row.id != selectedDivisionId) {
                    opts += '<option value="' + row.id + '">' + escHtml(row.name) + '</option>';
                }
            });
            sel.html(opts || '<option value="">No other divisions available</option>');
        }, 'json');
        $('#modalMoveDept').modal('show');
    }

    function moveDepartment() {
        var id = $('#move-dept-id').val();
        var divisionId = $('#move-dept-division-select').val();
        if (!divisionId) { alertError('Please select a target division.'); return; }
        showSpinner();
        $.post('org_action.php', { btn_action: 'move_department', id: id, division_id: divisionId }, function (res) {
            hideSpinner();
            if (res.message === 'update') {
                $('#modalMoveDept').modal('hide');
                loadDivisions();
                loadDepartments();
                if (id == selectedDepartmentId) {
                    selectedDepartmentId = null;
                    $('#section-list').html('<div class="panel-empty">Select a department to view its sections.</div>');
                }
            } else {
                alertError(res.detail || 'Failed to move department.');
            }
        }, 'json');
    }

    function deleteDepartment(id, name) {
        swal({ title: 'Delete Department?', text: '"' + name + '" and all its sections will be removed. Staff must be reassigned first.', icon: 'warning', buttons: ['Cancel', 'Delete'], dangerMode: true })
            .then(function (confirmed) {
                if (!confirmed) return;
                showSpinner();
                $.post('org_action.php', { btn_action: 'delete_department', id: id }, function (res) {
                    hideSpinner();
                    if (res.message === 'delete') {
                        if (id == selectedDepartmentId) {
                            selectedDepartmentId = null;
                            $('#btn-add-sect').prop('disabled', true);
                            $('#sect-dept-label').text('');
                            $('#section-list').html('<div class="panel-empty">Select a department to view its sections.</div>');
                        }
                        loadDepartments();
                    } else {
                        alertError(res.detail || 'Cannot delete department.');
                    }
                }, 'json');
            });
    }

    function openAssignHod(deptId, deptName, currentHodId) {
        $('#hod-dept-id').val(deptId);
        $('#hod-dept-name-label').text(deptName);
        var sel = $('#hod-user-select');
        sel.html('<option value="">Loading managers...</option>');
        $('#hod-no-managers-msg').hide();
        $.post('fetch_org.php', { action: 'load_managers', department_id: deptId }, function (data) {
            var opts = '<option value="">— No HOD —</option>';
            if (!data || data.length === 0) {
                $('#hod-no-managers-msg').show();
            } else {
                $.each(data, function (i, row) {
                    var selected = (row.id == currentHodId) ? ' selected' : '';
                    opts += '<option value="' + row.id + '"' + selected + '>' + escHtml(row.staffname) + ' (' + escHtml(row.staffno) + ')</option>';
                });
            }
            sel.html(opts);
        }, 'json');
        $('#modalAssignHod').modal('show');
    }

    function saveHod() {
        var deptId = $('#hod-dept-id').val();
        var hodUserId = $('#hod-user-select').val();
        showSpinner();
        $.post('org_action.php', { btn_action: 'assign_hod', department_id: deptId, hod_user_id: hodUserId }, function (res) {
            hideSpinner();
            if (res.message === 'update') {
                $('#modalAssignHod').modal('hide');
                loadDepartments();
                loadHodHealth();
                swal('HOD Updated', 'Head of Department has been assigned and all staff in this department have been updated.', 'success');
            } else {
                alertError(res.detail || 'Failed to assign HOD.');
            }
        }, 'json');
    }

    // ===== SECTIONS =====

    function loadSections() {
        if (!selectedDepartmentId) return;
        $('#section-list').html('<div class="panel-loading"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
        $.post('fetch_org.php', { action: 'load_sections', department_id: selectedDepartmentId }, function (data) {
            var html = '';
            if (!data || data.length === 0) {
                html = '<div class="panel-empty">No sections in this department. Click ADD to create one.</div>';
            } else {
                $.each(data, function (i, row) {
                    html += '<div class="org-list-item">';
                    html += '<div class="org-item-actions">';
                    html += '<button class="btn btn-xs btn-default" title="Edit" onclick="openEditSection(' + row.id + ',\'' + escJs(row.name) + '\',\'' + escJs(row.shortname || '') + '\')"><i class="fa fa-pencil-alt"></i></button>';
                    html += '<button class="btn btn-xs btn-warning" title="Move to another department" onclick="openMoveSection(' + row.id + ',\'' + escJs(row.name) + '\')"><i class="fa fa-exchange-alt"></i></button>';
                    html += '<button class="btn btn-xs btn-danger" title="Delete" onclick="deleteSection(' + row.id + ',\'' + escJs(row.name) + '\')"><i class="fa fa-trash"></i></button>';
                    html += '</div>';
                    html += '<div class="org-item-name">' + escHtml(row.name) + (row.shortname ? ' <small class="text-muted">(' + escHtml(row.shortname) + ')</small>' : '') + '</div>';
                    html += '<div class="org-item-meta">' + row.user_count + ' active staff</div>';
                    html += '</div>';
                });
            }
            $('#section-list').html(html);
        }, 'json');
    }

    function openAddSection() {
        if (!selectedDepartmentId) return;
        $('#modalSectTitle').text('Add Section');
        $('#sect-id').val('');
        $('#sect-department-id').val(selectedDepartmentId);
        $('#sect-name').val('');
        $('#sect-shortname').val('');
        $('#modalSection').modal('show');
    }

    function openEditSection(id, name, shortname) {
        $('#modalSectTitle').text('Edit Section');
        $('#sect-id').val(id);
        $('#sect-department-id').val(selectedDepartmentId);
        $('#sect-name').val(name);
        $('#sect-shortname').val(shortname);
        $('#modalSection').modal('show');
    }

    function saveSection() {
        var id = $('#sect-id').val();
        var deptId = $('#sect-department-id').val();
        var name = $.trim($('#sect-name').val());
        var shortname = $.trim($('#sect-shortname').val());
        if (!name) { alertError('Section name is required.'); return; }
        var btnAction = id ? 'rename_section' : 'add_section';
        var payload = { btn_action: btnAction, name: name, shortname: shortname, department_id: deptId };
        if (id) payload.id = id;
        showSpinner();
        $.post('org_action.php', payload, function (res) {
            hideSpinner();
            if (res.message === 'insert' || res.message === 'update') {
                $('#modalSection').modal('hide');
                loadSections();
            } else {
                alertError(res.detail || 'Failed to save section.');
            }
        }, 'json');
    }

    function openMoveSection(id, name) {
        $('#move-sect-id').val(id);
        $('#move-sect-name-label').text(name);
        var sel = $('#move-sect-dept-select');
        sel.html('<option value="">Loading...</option>');
        $.post('fetch_org.php', { action: 'load_all_departments' }, function (data) {
            var opts = '';
            $.each(data, function (i, row) {
                if (row.id != selectedDepartmentId) {
                    opts += '<option value="' + row.id + '">' + escHtml(row.division_name) + ' &raquo; ' + escHtml(row.name) + '</option>';
                }
            });
            sel.html(opts || '<option value="">No other departments available</option>');
        }, 'json');
        $('#modalMoveSect').modal('show');
    }

    function moveSection() {
        var id = $('#move-sect-id').val();
        var deptId = $('#move-sect-dept-select').val();
        if (!deptId) { alertError('Please select a target department.'); return; }
        showSpinner();
        $.post('org_action.php', { btn_action: 'move_section', id: id, department_id: deptId }, function (res) {
            hideSpinner();
            if (res.message === 'update') {
                $('#modalMoveSect').modal('hide');
                loadSections();
                loadHodHealth();
            } else {
                alertError(res.detail || 'Failed to move section.');
            }
        }, 'json');
    }

    function deleteSection(id, name) {
        swal({ title: 'Delete Section?', text: '"' + name + '" will be permanently removed. Staff must be reassigned first.', icon: 'warning', buttons: ['Cancel', 'Delete'], dangerMode: true })
            .then(function (confirmed) {
                if (!confirmed) return;
                showSpinner();
                $.post('org_action.php', { btn_action: 'delete_section', id: id }, function (res) {
                    hideSpinner();
                    if (res.message === 'delete') {
                        loadSections();
                    } else {
                        alertError(res.detail || 'Cannot delete section.');
                    }
                }, 'json');
            });
    }

    // ===== TRANSFER STAFF =====

    var transferStaffData = [];

    function openTransferStaff(deptId, deptName) {
        $('#transfer-from-dept-id').val(deptId);
        $('#transfer-dept-name-label').text(deptName);
        $('#transfer-staff-search').val('');
        $('#selected-count-label').text('0 selected');
        $('#transfer-staff-tbody').html('<tr><td colspan="5" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading staff...</td></tr>');
        $('#transfer-target-section').html('<option value="">— No specific section —</option>');

        $.post('fetch_org.php', { action: 'load_staff_in_dept', department_id: deptId }, function (data) {
            transferStaffData = data || [];
            renderTransferStaff(transferStaffData);
        }, 'json');

        var deptSel = $('#transfer-target-dept');
        deptSel.html('<option value="">Loading...</option>');
        $.post('fetch_org.php', { action: 'load_all_departments' }, function (data) {
            var opts = '<option value="">— Select target department —</option>';
            $.each(data, function (i, row) {
                if (row.id != deptId) {
                    opts += '<option value="' + row.id + '">' + escHtml(row.division_name) + ' &raquo; ' + escHtml(row.name) + '</option>';
                }
            });
            deptSel.html(opts);
        }, 'json');

        $('#modalTransferStaff').modal('show');
    }

    function renderTransferStaff(data) {
        if (!data || data.length === 0) {
            $('#transfer-staff-tbody').html('<tr><td colspan="5" class="text-center text-muted">No active staff in this department.</td></tr>');
            return;
        }
        var html = '';
        $.each(data, function (i, row) {
            html += '<tr class="transfer-staff-row" data-name="' + escHtml((row.staffname || '').toLowerCase()) + '" data-staffno="' + escHtml((row.staffno || '').toLowerCase()) + '">';
            html += '<td><input type="checkbox" class="transfer-chk" value="' + row.id + '" onchange="updateSelectedCount()"></td>';
            html += '<td>' + escHtml(row.staffno) + '</td>';
            html += '<td>' + escHtml(row.staffname) + '</td>';
            html += '<td><small>' + escHtml(row.designation) + '</small></td>';
            html += '<td><small>' + escHtml(row.section_name || '—') + '</small></td>';
            html += '</tr>';
        });
        $('#transfer-staff-tbody').html(html);
    }

    function toggleSelectAll(checked) {
        $('#transfer-staff-tbody .transfer-chk:visible').prop('checked', checked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        var count = $('#transfer-staff-tbody .transfer-chk:checked').length;
        $('#selected-count-label').text(count + ' selected');
    }

    $('#transfer-staff-search').on('keyup', function () {
        var q = $.trim($(this).val()).toLowerCase();
        $('.transfer-staff-row').each(function () {
            var name = $(this).data('name') || '';
            var staffno = $(this).data('staffno') || '';
            $(this).toggle(!q || name.indexOf(q) > -1 || staffno.indexOf(q) > -1);
        });
    });

    function onTransferDeptChange() {
        var deptId = $('#transfer-target-dept').val();
        var sect = $('#transfer-target-section');
        sect.html('<option value="">— No specific section —</option>');
        if (!deptId) return;
        $.post('fetch_org.php', { action: 'load_sections', department_id: deptId }, function (data) {
            $.each(data || [], function (i, row) {
                sect.append('<option value="' + row.id + '">' + escHtml(row.name) + '</option>');
            });
        }, 'json');
    }

    function executeTransfer() {
        var selectedIds = [];
        $('#transfer-staff-tbody .transfer-chk:checked').each(function () {
            selectedIds.push($(this).val());
        });
        if (selectedIds.length === 0) { alertError('Please select at least one staff member.'); return; }
        var targetDept = $('#transfer-target-dept').val();
        if (!targetDept) { alertError('Please select a target department.'); return; }
        var targetSection = $('#transfer-target-section').val();
        var targetDeptText = $('#transfer-target-dept option:selected').text();

        swal({
            title: 'Confirm Transfer',
            text: 'Transfer ' + selectedIds.length + ' staff member(s) to "' + targetDeptText + '"?\nTheir department, division, section, and HOD will be updated automatically.',
            icon: 'warning',
            buttons: ['Cancel', 'Transfer'],
            dangerMode: false
        }).then(function (confirmed) {
            if (!confirmed) return;
            showSpinner();
            $.post('org_action.php', {
                btn_action: 'transfer_staff',
                staff_ids: selectedIds,
                department_id: targetDept,
                section_id: targetSection
            }, function (res) {
                hideSpinner();
                if (res.message === 'transfer') {
                    $('#modalTransferStaff').modal('hide');
                    loadDepartments();
                    loadHodHealth();
                    swal('Transfer Complete', res.count + ' staff member(s) successfully transferred.', 'success');
                } else {
                    alertError(res.detail || 'Transfer failed.');
                }
            }, 'json');
        });
    }

    // ===== SKILL MATRIX CONFIG - WHITELIST =====

    function loadSmWhitelist() {
        $.post('fetch_org.php', { action: 'load_sm_whitelist' }, function (data) {
            var html = '';
            if (!data || data.length === 0) {
                html = '<div class="panel-empty">No staff whitelisted yet. Click ADD to whitelist someone.</div>';
            } else {
                $.each(data, function (i, row) {
                    var searchName = ((row.staffname || '') + ' ' + row.staffno).toLowerCase();
                    html += '<div class="org-list-item sm-whitelist-row" style="cursor:default;" data-search="' + escHtml(searchName) + '">';
                    html += '<div class="org-item-actions">';
                    html += '<button class="btn btn-xs btn-danger" title="Remove" onclick="removeSmWhitelist(\'' + escJs(row.staffno) + '\',\'' + escJs(row.staffname || row.staffno) + '\')"><i class="fa fa-trash"></i></button>';
                    html += '</div>';
                    if (row.user_id) {
                        html += '<div class="org-item-name">' + escHtml(row.staffname) + ' <small class="text-muted">(' + escHtml(row.staffno) + ')</small></div>';
                        html += '<div class="org-item-meta">' + escHtml(row.department || '') + (row.designation ? ' &middot; ' + escHtml(row.designation) : '') + '</div>';
                    } else {
                        html += '<div class="org-item-name">' + escHtml(row.staffno) + '</div>';
                        html += '<div class="org-item-meta" style="color:#c00;"><i class="fa fa-exclamation-triangle"></i> No matching staff record found</div>';
                    }
                    html += '</div>';
                });
                html += '<div class="panel-empty" id="sm-whitelist-no-match" style="display:none;">No whitelisted staff match your search.</div>';
            }
            $('#sm-whitelist-list').html(html);
            filterSmWhitelist();
        }, 'json');
    }

    function filterSmWhitelist() {
        var q = $.trim($('#sm-whitelist-search').val()).toLowerCase();
        var matched = 0;
        $('.sm-whitelist-row').each(function () {
            var isMatch = !q || ($(this).data('search') || '').indexOf(q) > -1;
            $(this).toggle(isMatch);
            if (isMatch) matched++;
        });
        $('#sm-whitelist-no-match').toggle(matched === 0 && $('.sm-whitelist-row').length > 0);
    }

    $(document).on('keyup', '#sm-whitelist-search', filterSmWhitelist);

    function removeSmWhitelist(staffno, label) {
        swal({ title: 'Remove from Whitelist?', text: '"' + label + '" will lose skill matrix access.', icon: 'warning', buttons: ['Cancel', 'Remove'], dangerMode: true })
            .then(function (confirmed) {
                if (!confirmed) return;
                showSpinner();
                $.post('org_action.php', { btn_action: 'remove_sm_whitelist', staffno: staffno }, function (res) {
                    hideSpinner();
                    if (res.message === 'delete') {
                        loadSmWhitelist();
                    } else {
                        alertError(res.detail || 'Failed to remove from whitelist.');
                    }
                }, 'json');
            });
    }

    var smAddSearchTimer = null;

    function openAddSmWhitelist() {
        $('#sm-add-search').val('');
        $('#sm-add-staffno').val('');
        $('#sm-add-selected-label').text('');
        $('#sm-add-results').hide().empty();
        $('#modalSmWhitelist').modal('show');
    }

    $('#sm-add-search').on('input', function () {
        var q = $.trim($(this).val());
        $('#sm-add-staffno').val('');
        $('#sm-add-selected-label').text('');
        clearTimeout(smAddSearchTimer);
        if (q.length < 2) { $('#sm-add-results').hide().empty(); return; }
        smAddSearchTimer = setTimeout(function () {
            $.post('fetch_org.php', { action: 'search_staff', q: q }, function (data) {
                if (!data || data.length === 0) {
                    $('#sm-add-results').html('<div class="list-group-item text-muted">No match</div>').show();
                    return;
                }
                var html = '';
                $.each(data, function (i, row) {
                    html += '<a href="#" class="list-group-item sm-add-option" data-staffno="' + escHtml(row.staffno) + '" data-label="' + escHtml(row.staffname + ' (' + row.staffno + ')') + '">' + escHtml(row.staffname) + ' (' + escHtml(row.staffno) + ') &mdash; <small class="text-muted">' + escHtml(row.department || '') + '</small></a>';
                });
                $('#sm-add-results').html(html).show();
            }, 'json');
        }, 250);
    });

    $(document).on('click', '.sm-add-option', function (e) {
        e.preventDefault();
        $('#sm-add-staffno').val($(this).data('staffno'));
        $('#sm-add-search').val($(this).data('label'));
        $('#sm-add-selected-label').text('Selected: ' + $(this).data('label'));
        $('#sm-add-results').hide().empty();
    });

    function saveSmWhitelist() {
        var staffno = $('#sm-add-staffno').val();
        if (!staffno) { alertError('Please search and select a staff member.'); return; }
        showSpinner();
        $.post('org_action.php', { btn_action: 'add_sm_whitelist', staffno: staffno }, function (res) {
            hideSpinner();
            if (res.message === 'insert') {
                $('#modalSmWhitelist').modal('hide');
                loadSmWhitelist();
            } else {
                alertError(res.detail || 'Failed to add to whitelist.');
            }
        }, 'json');
    }

    // ===== SKILL MATRIX CONFIG - DELETE DATA =====

    var smDeleteSearchTimer = null;

    $('#sm-delete-search').on('input', function () {
        var q = $.trim($(this).val());
        $('#sm-delete-user-id').val('');
        $('#sm-delete-selected').hide();
        clearTimeout(smDeleteSearchTimer);
        if (q.length < 2) { $('#sm-delete-results').hide().empty(); return; }
        smDeleteSearchTimer = setTimeout(function () {
            $.post('fetch_org.php', { action: 'search_staff', q: q }, function (data) {
                if (!data || data.length === 0) {
                    $('#sm-delete-results').html('<div class="list-group-item text-muted">No match</div>').show();
                    return;
                }
                var html = '';
                $.each(data, function (i, row) {
                    html += '<a href="#" class="list-group-item sm-delete-option" data-id="' + row.id + '" data-name="' + escHtml(row.staffname) + '" data-meta="' + escHtml(row.staffno + ' · ' + (row.department || '')) + '">' + escHtml(row.staffname) + ' (' + escHtml(row.staffno) + ') &mdash; <small class="text-muted">' + escHtml(row.department || '') + '</small></a>';
                });
                $('#sm-delete-results').html(html).show();
            }, 'json');
        }, 250);
    });

    $(document).on('click', '.sm-delete-option', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var name = $(this).data('name');
        var meta = $(this).data('meta');
        $('#sm-delete-user-id').val(id);
        $('#sm-delete-search').val(name);
        $('#sm-delete-results').hide().empty();
        $('#sm-delete-selected-name').text(name);
        $('#sm-delete-selected-meta').text('(' + meta + ')');
        $('#sm-delete-count-label').html('<i class="fa fa-spinner fa-spin"></i> Checking skill matrix data...');
        $('#sm-delete-btn').prop('disabled', true);
        $('#sm-delete-selected').show();
        $.post('fetch_org.php', { action: 'check_sm_data', user_id: id }, function (res) {
            var count = res.count || 0;
            if (count === 0) {
                $('#sm-delete-count-label').text('No skill matrix evaluations found for this staff member.');
                $('#sm-delete-btn').prop('disabled', true);
            } else {
                $('#sm-delete-count-label').text(count + ' skill matrix evaluation(s) found.');
                $('#sm-delete-btn').prop('disabled', false);
            }
        }, 'json');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#sm-add-search').length && !$(e.target).closest('#sm-add-results').length) {
            $('#sm-add-results').hide();
        }
        if (!$(e.target).closest('#sm-delete-search').length && !$(e.target).closest('#sm-delete-results').length) {
            $('#sm-delete-results').hide();
        }
    });

    function deleteSmData() {
        var userId = $('#sm-delete-user-id').val();
        var name = $('#sm-delete-selected-name').text();
        if (!userId) return;
        swal({ title: 'Delete Skill Matrix Data?', text: 'All skill matrix evaluations for "' + name + '" will be permanently deleted. This cannot be undone.', icon: 'warning', buttons: ['Cancel', 'Delete'], dangerMode: true })
            .then(function (confirmed) {
                if (!confirmed) return;
                showSpinner();
                $.post('org_action.php', { btn_action: 'delete_sm_data', user_id: userId }, function (res) {
                    hideSpinner();
                    if (res.message === 'delete') {
                        swal('Deleted', res.count + ' evaluation(s) deleted for "' + name + '".', 'success');
                        $('#sm-delete-search').val('');
                        $('#sm-delete-user-id').val('');
                        $('#sm-delete-selected').hide();
                    } else {
                        alertError(res.detail || 'Failed to delete skill matrix data.');
                    }
                }, 'json');
            });
    }

    // ===== BULK RENAME VIA EXCEL =====

    function uploadOrgTemplate() {
        var fileInput = document.getElementById('import-file-input');
        if (!fileInput.files || fileInput.files.length === 0) {
            alertError('Please choose a file to upload first.');
            return;
        }
        var deleteMissing = $('#delete-missing-chk').is(':checked');

        if (deleteMissing) {
            swal({
                title: 'Delete removed rows?',
                text: 'Any division, department, or section that is missing from the uploaded sheet will be permanently deleted (skipped automatically if staff are still assigned to it). This cannot be undone. Continue?',
                icon: 'warning',
                buttons: ['Cancel', 'Delete'],
                dangerMode: true
            }).then(function (confirmed) {
                if (confirmed) doUploadOrgTemplate(fileInput, true);
            });
        } else {
            doUploadOrgTemplate(fileInput, false);
        }
    }

    function doUploadOrgTemplate(fileInput, deleteMissing) {
        var formData = new FormData();
        formData.append('import_file', fileInput.files[0]);
        formData.append('delete_missing', deleteMissing ? '1' : '0');

        showSpinner();
        $.ajax({
            url: 'org_import.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (res) {
                hideSpinner();
                fileInput.value = '';
                $('#delete-missing-chk').prop('checked', false);
                if (res.message !== 'done') {
                    alertError(res.detail || 'Import failed.');
                    return;
                }
                var summaryText = res.divisions_updated + ' division(s), ' +
                    res.departments_updated + ' department(s), ' +
                    res.sections_updated + ' section(s) updated.';
                if (deleteMissing) {
                    summaryText += '\n' + res.divisions_deleted + ' division(s), ' +
                        res.departments_deleted + ' department(s), ' +
                        res.sections_deleted + ' section(s) deleted.';
                }
                if (res.errors && res.errors.length > 0) {
                    summaryText += '\n\n' + res.errors.length + ' issue(s):\n' + res.errors.join('\n');
                }
                swal('Import Complete', summaryText, (res.errors && res.errors.length > 0) ? 'warning' : 'success');
                loadDivisions();
                if (selectedDivisionId) loadDepartments();
                if (selectedDepartmentId) loadSections();
            },
            error: function () {
                hideSpinner();
                alertError('Upload failed. Please try again.');
            }
        });
    }

    // ===== HELPERS =====

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function escJs(str) {
        if (!str) return '';
        return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"').replace(/\n/g,'\\n');
    }

    $(document).ready(function () {
        loadDivisions();
        loadSmWhitelist();
    });
</script>
</body>
</html>
