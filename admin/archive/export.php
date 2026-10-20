<?php
session_start();
include "archive_config.php";

if (archiveUserCanAccess()) {
    $isAdminNav = isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN';
    $canViewSkillMatrix = !empty($_SESSION['is_sm_user']) || (
        isset($_SESSION['designation'], $_SESSION['hodid'], $_SESSION['role'], $_SESSION['usertype'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && $_SESSION['role'] == 'CLERK'
        && $_SESSION['usertype'] == 'MAIN'
    );
    include_once __DIR__ . '/../../skill_matrix_department.php';
    $canViewSkillMatrix = $canViewSkillMatrix && skillMatrixDepartmentEnabled();

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Learning and Development Management System</title>
        <script src="../../asset/js/jquery-1.10.2.min.js"></script>
        <link rel="stylesheet" href="../../asset/css/bootstrap.min.css" />
        <script src="../../asset/js/bootstrap.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">
        <link rel="stylesheet" href="../../asset/css/datepicker.css">
        <script src="../../asset/js/bootstrap-datepicker1.js"></script>
        <link rel="stylesheet" type="text/css"
            href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css" />
        <script type="text/javascript"
            src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-flash-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js">
            </script>
        <style>
            #archivelist {
                width: 100% !important;
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
                <div id="txt" align="right" class="col-md-2" style="margin-top:43px;color:white;">

                </div>
            </div>
            <nav class="navbar navbar-inverse">
                <div class="container-fluid ">
                    <ul class="nav navbar-nav">
                        <?php if ($isAdminNav) { ?>
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
                        <li><a href="../tna/tna_list.php">TNA</a></li>
                        <li><a href="../tni/tni_list.php">TNI</a></li>
                        <li><a href="../tna/tna_summary.php">TNA SUMMARY</a></li>
                        <li><a href="../skill-matrix/skill-matrix.php">SKILL MATRIX</a></li>
                        <li><a href="../organization/org.php">ORGANIZATION</a></li>
                        <li class="active"><a href="export.php">EXPORT</a></li>
                        <li><a href="../password/password.php">CHANGE PASSWORD</a></li>
                        <?php } else { ?>
                        <li><a href="../../clerk/main/dashboard.php">HOME</a></li>
                        <li><a href="../../clerk/main/staff/staff.php">CONTRACT STAFF LIST</a></li>
                        <li><a href="../../clerk/main/training/training_ojt.php">ALL TRAINING</a></li>
                        <li><a href="../../clerk/main/attendance/training.php">MY TRAINING</a></li>
                        <li><a href="../../clerk/main/attendance/pme.php">PME</a></li>
                        <li><a href="../../clerk/main/tna/tna_list.php">TNA</a></li>
                        <?php if ($canViewSkillMatrix) { ?><li><a href="../../clerk/main/skill-matrix/skill-matrix.php">SKILL MATRIX</a></li><?php } ?>
                        <li class="active"><a href="export.php">EXPORT</a></li>
                        <li><a href="../../clerk/main/password/password.php">CHANGE PASSWORD</a></li>
                        <?php } ?>
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
                            <strong>Export Filter</strong>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Export Type</label>
                                    <select id="entity" class="form-control">
                                        <?php foreach ($ARCHIVE_ENTITIES as $key => $info): ?>
                                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($info['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Date From</label>
                                    <input type="text" id="date_from" class="form-control" placeholder="YYYY-MM-DD" autocomplete="off" />
                                </div>
                                <div class="col-md-2">
                                    <label>Date To</label>
                                    <input type="text" id="date_to" class="form-control" placeholder="YYYY-MM-DD" autocomplete="off" />
                                </div>
                                <div class="col-md-3" id="keyword_field">
                                    <label>Keyword</label>
                                    <input type="text" id="keyword" class="form-control" placeholder="Search name, title, etc." />
                                </div>
                                <div class="col-md-3" id="participant_field" style="display:none;position:relative;">
                                    <label>Participant</label>
                                    <input type="text" id="participant_search" class="form-control" placeholder="Search staff no or name" autocomplete="off" />
                                    <input type="hidden" id="participant_id" value="" />
                                    <div id="participant_results" class="list-group" style="display:none;position:absolute;z-index:1000;width:100%;max-height:220px;overflow-y:auto;"></div>
                                </div>
                                <div class="col-md-2" style="margin-top:24px;white-space:nowrap;">
                                    <button type="button" id="filter_archive" class="btn btn-info btn-md" style="margin-right:8px;">FILTER <i class="fa fa-search"></i></button>
                                    <button type="button" id="reset_archive" class="btn btn-default btn-md">RESET</button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12" style="margin-top:15px;">
                                    <a href="#" id="download_archive" class="btn btn-success btn-md">
                                        <i class="fa fa-download"></i> DOWNLOAD CSV (current filter)
                                    </a>
                                    <span id="archive_missing_msg" class="text-muted" style="display:none;margin-left:10px;">
                                        No archive table found for this type yet - it is created the first time
                                        <code>scripts/archive_2025_and_below.php --execute</code> runs.
                                    </span>
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
                            <strong>Exported Records</strong>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-sm-12 table-responsive">
                                    <table id="archivelist" class="table table-bordered table-striped">
                                        <thead id="archivelist_head"></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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

        $('#date_from').datepicker({ format: "yyyy-mm-dd", autoclose: true });
        $('#date_to').datepicker({ format: "yyyy-mm-dd", autoclose: true });

        var archiveTable = null;

        function currentFilters() {
            return {
                entity: $('#entity').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val(),
                keyword: $('#keyword').val(),
                participant_id: $('#participant_id').val()
            };
        }

        function buildDownloadUrl() {
            var f = currentFilters();
            return 'export_archive.php?entity=' + encodeURIComponent(f.entity) +
                '&date_from=' + encodeURIComponent(f.date_from) +
                '&date_to=' + encodeURIComponent(f.date_to) +
                '&keyword=' + encodeURIComponent(f.keyword) +
                '&participant_id=' + encodeURIComponent(f.participant_id);
        }

        function updateDownloadLink() {
            $('#download_archive').attr('href', buildDownloadUrl());
        }

        function loadTableForEntity(entity) {
            $.post('fetch_archive.php', { action: 'get_columns', entity: entity }, function (resp) {
                if (archiveTable) {
                    archiveTable.destroy();
                    archiveTable = null;
                    $('#archivelist tbody').empty();
                }

                if (!resp.exists || !resp.columns || resp.columns.length === 0) {
                    $('#archivelist_head').html('<tr><th>No data</th></tr>');
                    $('#archive_missing_msg').show();
                    return;
                }
                $('#archive_missing_msg').hide();

                var headHtml = '<tr><th>No.</th>';
                $.each(resp.columns, function (i, col) {
                    headHtml += '<th>' + col + '</th>';
                });
                headHtml += '</tr>';
                $('#archivelist_head').html(headHtml);

                var dtColumns = [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    defaultContent: '',
                    render: function (data, type, row, meta) {
                        return meta.settings._iDisplayStart + meta.row + 1;
                    }
                }];
                dtColumns = dtColumns.concat($.map(resp.columns, function (col) {
                    return { data: col, defaultContent: '' };
                }));

                archiveTable = $('#archivelist').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "searching": false,
                    "lengthChange": true,
                    "pageLength": 25,
                    "ajax": {
                        url: "fetch_archive.php",
                        type: "POST",
                        data: function (data) {
                            var f = currentFilters();
                            data.action = "list";
                            data.entity = f.entity;
                            data.date_from = f.date_from;
                            data.date_to = f.date_to;
                            data.keyword = f.keyword;
                            data.participant_id = f.participant_id;
                        }
                    },
                    "columns": dtColumns,
                    "language": {
                        "emptyTable": "No archived records found for the selected filter."
                    }
                });
            }, 'json');
        }

        $('#entity').change(function () {
            var val = $(this).val();
            if (val === 'participant') {
                $('#keyword_field').hide();
                $('#participant_field').show();
            } else {
                $('#keyword_field').show();
                $('#participant_field').hide();
                $('#participant_search').val('');
                $('#participant_id').val('');
                $('#participant_results').hide().empty();
            }
            loadTableForEntity(val);
            updateDownloadLink();
        });

        var participantSearchTimer = null;

        $('#participant_search').on('input', function () {
            var q = $(this).val();
            $('#participant_id').val('');
            clearTimeout(participantSearchTimer);
            if (q.length < 2) {
                $('#participant_results').hide().empty();
                return;
            }
            participantSearchTimer = setTimeout(function () {
                $.post('fetch_archive.php', { action: 'search_participants', entity: 'participant', q: q }, function (resp) {
                    var items = resp.results || [];
                    if (items.length === 0) {
                        $('#participant_results').html('<div class="list-group-item text-muted">No match</div>').show();
                        return;
                    }
                    var $list = $('<div></div>');
                    $.each(items, function (i, u) {
                        $('<a href="#" class="list-group-item participant-option"></a>')
                            .attr('data-id', u.id)
                            .text(u.staffno + ' - ' + u.staffname)
                            .appendTo($list);
                    });
                    $('#participant_results').html($list.html()).show();
                }, 'json');
            }, 250);
        });

        $(document).on('click', '.participant-option', function (e) {
            e.preventDefault();
            $('#participant_id').val($(this).data('id'));
            $('#participant_search').val($(this).text());
            $('#participant_results').hide().empty();
            if (archiveTable) {
                archiveTable.ajax.reload();
            }
            updateDownloadLink();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#participant_field').length) {
                $('#participant_results').hide();
            }
        });

        $('#filter_archive').click(function () {
            if (archiveTable) {
                archiveTable.ajax.reload();
            }
            updateDownloadLink();
        });

        $('#reset_archive').click(function () {
            $('#date_from').val('');
            $('#date_to').val('');
            $('#keyword').val('');
            $('#participant_search').val('');
            $('#participant_id').val('');
            $('#participant_results').hide().empty();
            if (archiveTable) {
                archiveTable.ajax.reload();
            }
            updateDownloadLink();
        });

        $('#download_archive').click(function () {
            updateDownloadLink();
        });

        $(function () {
            loadTableForEntity($('#entity').val());
            updateDownloadLink();
        });
    </script>

    </html>
    <?php
} else {
    header("Location: ../../login.php");
    exit();
}
?>
