<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";
include "../../../skill_matrix_score.php";

require '../../../asset/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include_once __DIR__ . '/../../../skill_matrix_department.php';

// HOD copy of admin/skill-matrix/export_skill_matrix_list.php: the same staff list for
// every department, with the filters and tiles of skill-matrix.php and skill-matrix-breakdown.php.
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

function skillMatrixBindParams($stmt, $types, $params)
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

if (!isset($_SESSION['fullname']) || !canApproveSkillMatrix()) {
    header("Location: ../dashboard.php");
    exit();
}

// Mirrors fetch_skill_matrix.php's load_non_executive_staff filter logic exactly,
// so the exported rows always match whatever the on-screen table is showing.
$department = isset($_GET['department']) ? $_GET['department'] : 'ALL';
$section = isset($_GET['section']) ? $_GET['section'] : 'ALL';
$plant = isset($_GET['plant']) ? $_GET['plant'] : 'ALL';
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();

// From skill-matrix-breakdown.php: period=previous exports the quarter before the
// evaluation quarter (staff with a matrix for it only, like the on-screen tab);
// tile keeps only the staff behind that overview tile; group_department or
// group_section keeps one group of the breakdown table.
$viewingPrevious = isset($_GET['period']) && $_GET['period'] == 'previous';
if ($viewingPrevious) {
    list($currentYear, $currentQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);
}
$tileFilters = [
    'approved' => 'Approved',
    'waiting' => 'Waiting Approval',
    'draft' => 'Draft',
    'notsubmitted' => 'Not Submitted',
    'completion' => 'Not Approved',
    'below50' => 'Score Below 50',
];
$tile = isset($_GET['tile'], $tileFilters[$_GET['tile']]) ? $_GET['tile'] : '';
$groupDepartment = isset($_GET['group_department']) ? trim($_GET['group_department']) : null;
$groupSection = isset($_GET['group_section']) ? trim($_GET['group_section']) : null;


// sme is the latest skill_matrix_evaluations row for this staff in the
// current quarter (mirrors evaluation-matrix.php's own lookup).
// verified_by is the CREATOR's hodid, not the evaluated staff's - see
// evaluation-matrix.php's sign-off query and the "Skill matrix HOD routing"
// note: the verifier is whoever manages the person who filled the form in.
$sql = "SELECT
            u.staffno,
            u.staffname,
            u.designation,
            u.grade,
            u.status,
            u.plant,
            COALESCE(dp.name, u.department) AS department,
            COALESCE(s.name, u.section) AS section,
            sme.id AS evaluation_id,
            sme.id IS NOT NULL AS has_current_quarter_evaluation,
            sme.approval_status AS approval_status,
            creator.staffname AS evaluated_by,
            verifier.staffname AS verified_by,
            approver.staffname AS approved_by
        FROM user u
        LEFT JOIN departments dp ON u.department_id = dp.id
        LEFT JOIN sections s ON u.section_id = s.id
        LEFT JOIN skill_matrix_evaluations sme ON sme.id = (
            SELECT sme2.id
            FROM skill_matrix_evaluations sme2
            WHERE sme2.staffid = u.id
            AND sme2.eval_year = ?
            AND sme2.eval_quarter = ?
            ORDER BY sme2.evaluation_date DESC, sme2.id DESC
            LIMIT 1
        )
        LEFT JOIN user creator ON creator.id = sme.created_by
        LEFT JOIN user verifier ON verifier.id = creator.hodid
        LEFT JOIN user approver ON approver.id = sme.approved_by
        WHERE u.designation IN (?, ?)
        AND u.status != ?";

// Same departments as the on-screen list (Organization > Skill Matrix Config).
$sql .= skillMatrixEnabledDepartmentSql(skillMatrixIncludeOffDepartments());

$designation1 = "NON EXECUTIVE";
$designation2 = "CONTRACT";
$inactiveStatus = "RESIGN";

$types = "iisss";
$params = [$currentYear, $currentQuarter, $designation1, $designation2, $inactiveStatus];

if ($department == "MY") {
    // My Department tab: the department(s) this HOD heads.
    $myDepartments = array();
    $myDepartmentStmt = $conn->prepare("SELECT name FROM departments WHERE hod_user_id = ? ORDER BY name");
    $hodId = (int) $_SESSION['id'];
    $myDepartmentStmt->bind_param("i", $hodId);
    $myDepartmentStmt->execute();
    $myDepartmentResult = $myDepartmentStmt->get_result();
    while ($myDepartmentRow = $myDepartmentResult->fetch_assoc()) {
        $myDepartments[] = $myDepartmentRow['name'];
    }
    if (count($myDepartments) == 0) {
        $myDepartments[] = '';
    }
    $placeholders = implode(',', array_fill(0, count($myDepartments), '?'));
    $sql .= " AND (dp.name IN ($placeholders) OR u.department IN ($placeholders))";
    $types .= str_repeat('s', count($myDepartments) * 2);
    $params = array_merge($params, $myDepartments, $myDepartments);
    // File name shows the department(s), not "MY".
    $department = implode(' ', $myDepartments);
} else if ($department != "" && $department != "ALL") {
    $sql .= " AND (dp.name = ? OR u.department = ?)";
    $types .= "ss";
    $params[] = $department;
    $params[] = $department;
}

if ($section != "" && $section != "ALL") {
    $sql .= " AND (s.name = ? OR u.section = ?)";
    $types .= "ss";
    $params[] = $section;
    $params[] = $section;
}

if ($plant != "" && $plant != "ALL") {
    $sql .= " AND u.plant = ?";
    $types .= "s";
    $params[] = $plant;
}

$sql .= " ORDER BY department, u.staffname";

$stmt = $conn->prepare($sql);
skillMatrixBindParams($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

// Breakdown groups are named like the dashboard's, with '(Unspecified)' for a blank.
$groupName = function ($value) {
    $value = trim((string) $value);
    return $value === '' ? '(Unspecified)' : $value;
};

$rows = [];
while ($row = $result->fetch_assoc()) {
    if ($viewingPrevious && !$row['has_current_quarter_evaluation']) {
        continue;
    }
    if ($groupDepartment !== null && $groupName($row['department']) !== $groupDepartment) {
        continue;
    }
    if ($groupSection !== null && $groupName($row['section']) !== $groupSection) {
        continue;
    }

    $status = $row['status'] == 'RESIGN' ? 'NOT ACTIVE' : 'ACTIVE';

    if ($row['approval_status'] == 'APPROVED') {
        $approvalStatus = 'APPROVED';
    } else if ($row['approval_status'] == 'PENDING') {
        $approvalStatus = 'WAITING APPROVAL';
    } else if ($row['has_current_quarter_evaluation']) {
        $approvalStatus = 'DRAFT';
    } else {
        $approvalStatus = 'NOT SUBMITTED';
    }

    if (($tile == 'approved' || $tile == 'below50') && $approvalStatus != 'APPROVED'
        || $tile == 'waiting' && $approvalStatus != 'WAITING APPROVAL'
        || $tile == 'draft' && $approvalStatus != 'DRAFT'
        || $tile == 'notsubmitted' && $approvalStatus != 'NOT SUBMITTED'
        || $tile == 'completion' && $approvalStatus == 'APPROVED') {
        continue;
    }

    $rows[] = [
        'evaluation_id' => $approvalStatus == 'APPROVED' ? (int) $row['evaluation_id'] : null,
        'score' => null,
        'staffno' => $row['staffno'],
        'staffname' => $row['staffname'],
        'department' => $row['department'],
        'section' => $row['section'],
        'plant' => $row['plant'],
        'grade' => $row['grade'],
        'status' => $status,
        'approval_status' => $approvalStatus,
        'evaluated_by' => $row['evaluated_by'] ? $row['evaluated_by'] : '',
        'verified_by' => $row['verified_by'] ? $row['verified_by'] : '',
        'approved_by' => $row['approved_by'] ? $row['approved_by'] : '',
    ];
}

// Skill Score, approved matrices only (same figure as the on-screen Skill Score column).
$scores = skillMatrixOverallScores($conn, array_values(array_filter(array_column($rows, 'evaluation_id'))));
foreach ($rows as $i => $r) {
    if ($r['evaluation_id'] !== null && isset($scores[$r['evaluation_id']])) {
        $rows[$i]['score'] = $scores[$r['evaluation_id']];
    }
}
if ($tile == 'below50') {
    $rows = array_values(array_filter($rows, function ($r) {
        return $r['score'] !== null && $r['score'] < 50;
    }));
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Skill Matrix Staff List');

$headers = ['No.', 'Staff No.', 'Employee Name', 'Department', 'Section', 'Plant', 'Grade', 'Status', 'Approval Status', 'Skill Score (%)', 'Score Level', 'Evaluated By', 'Verified By', 'Approved By'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:N1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$sheet->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF337AB7');
$sheet->getStyle('A1:N1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = 2;
foreach ($rows as $i => $r) {
    $sheet->setCellValueExplicit('A' . $rowNum, $i + 1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValue('B' . $rowNum, $r['staffno']);
    $sheet->setCellValue('C' . $rowNum, $r['staffname']);
    $sheet->setCellValue('D' . $rowNum, $r['department']);
    $sheet->setCellValue('E' . $rowNum, $r['section']);
    $sheet->setCellValue('F' . $rowNum, $r['plant']);
    $sheet->setCellValue('G' . $rowNum, $r['grade']);
    $sheet->setCellValue('H' . $rowNum, $r['status']);
    $sheet->setCellValue('I' . $rowNum, $r['approval_status']);
    if ($r['score'] !== null) {
        $sheet->setCellValueExplicit('J' . $rowNum, $r['score'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValue('K' . $rowNum, skillMatrixScoreLevel($r['score']));
    }
    $sheet->setCellValue('L' . $rowNum, $r['evaluated_by']);
    $sheet->setCellValue('M' . $rowNum, $r['verified_by']);
    $sheet->setCellValue('N' . $rowNum, $r['approved_by']);
    $rowNum++;
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filenameParts = ['Skill_Matrix_Staff_List'];
if ($tile != '') $filenameParts[] = str_replace(' ', '_', $tileFilters[$tile]);
if ($department != '' && $department != 'ALL') $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $department);
if ($section != '' && $section != 'ALL') $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $section);
if ($groupDepartment !== null) $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $groupDepartment);
if ($groupSection !== null) $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $groupSection);
if ($plant != '' && $plant != 'ALL') $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $plant);
$filenameParts[] = 'Q' . $currentQuarter . '_' . $currentYear;
$filenameParts[] = date('Ymd_His');
$filename = implode('_', $filenameParts) . '.xlsx';

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
