<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";

require '../../../asset/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$isSmWhitelisted = !empty($_SESSION['is_sm_user']) && isset($_SESSION['fullname']);
$isRegularManager = isset($_SESSION['fullname'], $_SESSION['role'], $_SESSION['usertype'], $_SESSION['designation'], $_SESSION['hodid'])
    && $_SESSION['role'] == ''
    && $_SESSION['usertype'] == ''
    && $_SESSION['designation'] == 'MANAGER (AM/HOS & ABOVE)'
    && (int) $_SESSION['hodid'] != 0;
include_once __DIR__ . '/../../../skill_matrix_department.php';
if (!skillMatrixDepartmentEnabled()) {
    $isSmWhitelisted = $isRegularManager = false;
}

if (!$isSmWhitelisted && !$isRegularManager) {
    header("Location: ../../../login.php");
    exit();
}

// Mirrors fetch_skill_matrix.php's load_non_executive_staff query exactly, so
// the export always matches what the on-screen table is showing.
$department = isset($_SESSION['department']) ? $_SESSION['department'] : '';
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();

if ($department == '' && isset($_SESSION['id'])) {
    $stmtDepartment = $conn->prepare("SELECT department FROM user WHERE id = ?");
    if ($stmtDepartment) {
        $stmtDepartment->bind_param("i", $_SESSION['id']);
        $stmtDepartment->execute();
        $departmentResult = $stmtDepartment->get_result()->fetch_assoc();
        if ($departmentResult) {
            $department = $departmentResult['department'];
        }
    }
}

if ($department == '') {
    header("Location: skill-matrix.php");
    exit();
}

$sql = "SELECT
            u.staffno,
            u.staffname,
            u.grade,
            u.status,
            COALESCE(dp.name, u.department) AS department,
            COALESCE(s.name, u.section) AS section,
            (
                SELECT sme.approval_status
                FROM skill_matrix_evaluations sme
                WHERE sme.staffid = u.id
                AND sme.eval_year = ?
                AND sme.eval_quarter = ?
                ORDER BY sme.evaluation_date DESC, sme.id DESC
                LIMIT 1
            ) AS approval_status,
            EXISTS (
                SELECT 1
                FROM skill_matrix_evaluations sme
                WHERE sme.staffid = u.id
                AND sme.eval_year = ?
                AND sme.eval_quarter = ?
            ) AS has_current_quarter_evaluation
        FROM user u
        LEFT JOIN departments dp ON u.department_id = dp.id
        LEFT JOIN sections s ON u.section_id = s.id
        WHERE u.designation IN (?, ?)
        AND u.status != ?
        AND u.department = ?
        ORDER BY u.staffname";

$designation1 = "NON EXECUTIVE";
$designation2 = "CONTRACT";
$inactiveStatus = "RESIGN";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiissss", $currentYear, $currentQuarter, $currentYear, $currentQuarter, $designation1, $designation2, $inactiveStatus, $department);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
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
        'grade' => $row['grade'],
        'status' => 'ACTIVE',
        'approval_status' => $approvalStatus,
    ];
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Skill Matrix Staff List');

$headers = ['No.', 'Staff No.', 'Employee Name', 'Department', 'Section', 'Grade', 'Status', 'Approval Status'];
$sheet->fromArray($headers, null, 'A1');
$lastCol = 'H';
$sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$sheet->getStyle('A1:' . $lastCol . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF337AB7');
$sheet->getStyle('A1:' . $lastCol . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = 2;
foreach ($rows as $i => $r) {
    $sheet->setCellValueExplicit('A' . $rowNum, $i + 1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValue('B' . $rowNum, $r['staffno']);
    $sheet->setCellValue('C' . $rowNum, $r['staffname']);
    $sheet->setCellValue('D' . $rowNum, $r['department']);
    $sheet->setCellValue('E' . $rowNum, $r['section']);
    $sheet->setCellValue('F' . $rowNum, $r['grade']);
    $sheet->setCellValue('G' . $rowNum, $r['status']);
    $sheet->setCellValue('H' . $rowNum, $r['approval_status']);
    $rowNum++;
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'Skill_Matrix_Staff_List_' . preg_replace('/[^A-Za-z0-9]+/', '_', $department) . '_' . date('Ymd_His') . '.xlsx';

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
