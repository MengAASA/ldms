<?php
session_start();
include "../../dbconn.php";
include "../../skill_matrix_period.php";

require '../../asset/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include_once __DIR__ . '/../../skill_matrix_department.php';

function skillMatrixUserCanUse()
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

    return (
        !empty($_SESSION['is_sm_user']) && isset($_SESSION['fullname'])
    ) || (
        isset($_SESSION['fullname'], $_SESSION['role'], $_SESSION['designation'], $_SESSION['usertype'], $_SESSION['hodid'])
        && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
        && (int) $_SESSION['hodid'] != 0
        && (
            ($_SESSION['role'] == '' && $_SESSION['usertype'] == '') ||
            ($_SESSION['role'] == 'CLERK' && $_SESSION['usertype'] == 'MAIN')
        )
    );
}

function skillMatrixBindParams($stmt, $types, $params)
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

if (!isset($_SESSION['fullname']) || !($_SESSION['role'] == 'ADMIN' || skillMatrixUserCanUse())) {
    header("Location: ../../login.php");
    exit();
}

// Mirrors fetch_skill_matrix.php's load_non_executive_staff filter logic exactly,
// so the exported rows always match whatever the on-screen table is showing.
$department = isset($_GET['department']) ? $_GET['department'] : 'ALL';
$section = isset($_GET['section']) ? $_GET['section'] : 'ALL';
$plant = isset($_GET['plant']) ? $_GET['plant'] : 'ALL';
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();

if (skillMatrixUserCanUse()) {
    if (!isset($_SESSION['department']) || $_SESSION['department'] == '') {
        $sessionUserId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
        $departmentStmt = $conn->prepare("SELECT department FROM user WHERE id = ?");
        if ($departmentStmt) {
            $departmentStmt->bind_param("i", $sessionUserId);
            $departmentStmt->execute();
            $departmentRow = $departmentStmt->get_result()->fetch_assoc();
            if ($departmentRow) {
                $_SESSION['department'] = $departmentRow['department'];
            }
        }
    }

    $department = isset($_SESSION['department']) ? $_SESSION['department'] : "";
    if ($department == "") {
        header("Location: skill-matrix.php");
        exit();
    }
}

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

$designation1 = "NON EXECUTIVE";
$designation2 = "CONTRACT";
$inactiveStatus = "RESIGN";

$types = "iisss";
$params = [$currentYear, $currentQuarter, $designation1, $designation2, $inactiveStatus];

if ($department != "" && $department != "ALL") {
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

$rows = [];
while ($row = $result->fetch_assoc()) {
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

    $rows[] = [
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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Skill Matrix Staff List');

$headers = ['No.', 'Staff No.', 'Employee Name', 'Department', 'Section', 'Plant', 'Grade', 'Status', 'Approval Status', 'Evaluated By', 'Verified By', 'Approved By'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:L1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF337AB7');
$sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
    $sheet->setCellValue('J' . $rowNum, $r['evaluated_by']);
    $sheet->setCellValue('K' . $rowNum, $r['verified_by']);
    $sheet->setCellValue('L' . $rowNum, $r['approved_by']);
    $rowNum++;
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filenameParts = ['Skill_Matrix_Staff_List'];
if ($department != '' && $department != 'ALL') $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $department);
if ($section != '' && $section != 'ALL') $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $section);
if ($plant != '' && $plant != 'ALL') $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '_', $plant);
$filenameParts[] = date('Ymd_His');
$filename = implode('_', $filenameParts) . '.xlsx';

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
