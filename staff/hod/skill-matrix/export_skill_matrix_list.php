<?php
session_start();
include "../../../dbconn.php";
include "../../../skill_matrix_period.php";

require '../../../asset/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include_once __DIR__ . '/../../../skill_matrix_department.php';

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

if (!isset($_SESSION['fullname']) || !canApproveSkillMatrix()) {
    header("Location: ../dashboard.php");
    exit();
}

// First sheet mirrors skill-matrix.php's approval-queue query exactly; the
// second sheet (further down) mirrors its department staff status list.
$hodId = (int) $_SESSION['id'];
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
// ?period=previous exports the quarter before the evaluation quarter instead.
if (isset($_GET['period']) && $_GET['period'] == 'previous') {
    list($currentYear, $currentQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);
}

$stmt = $conn->prepare("SELECT
                            sme.approval_status,
                            target.staffno AS target_staffno,
                            target.staffname AS target_staffname,
                            target.department AS target_department,
                            target.section AS target_section,
                            creator.staffname AS created_by_name
                        FROM skill_matrix_evaluations sme
                        INNER JOIN user target ON target.id = sme.staffid
                        INNER JOIN user creator ON creator.id = sme.created_by
                        WHERE creator.hodid = ?
                        AND (
                            (
                                creator.designation = ?
                                AND (
                                    (creator.roletype = '' AND creator.usertype = '')
                                    OR (creator.roletype = 'CLERK' AND creator.usertype = 'MAIN')
                                )
                            )
                            OR EXISTS (SELECT 1 FROM skill_matrix_whitelist w WHERE w.staffno = creator.staffno COLLATE utf8mb4_0900_ai_ci)
                        )
                        AND sme.approval_status IS NOT NULL
                        AND sme.eval_year = ?
                        AND sme.eval_quarter = ?
                        ORDER BY FIELD(sme.approval_status, 'PENDING', 'APPROVED'), sme.evaluation_date DESC, target.staffname");
$creatorDesignation = "MANAGER (AM/HOS & ABOVE)";
$stmt->bind_param("isii", $hodId, $creatorDesignation, $currentYear, $currentQuarter);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Skill Matrix Approvals');

$headers = ['No.', 'Staff No.', 'Staff Name', 'Department', 'Section', 'Filled By', 'Approval Status'];
$sheet->fromArray($headers, null, 'A1');
$lastCol = 'G';
$sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$sheet->getStyle('A1:' . $lastCol . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF337AB7');
$sheet->getStyle('A1:' . $lastCol . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = 2;
foreach ($rows as $i => $r) {
    $sheet->setCellValueExplicit('A' . $rowNum, $i + 1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValue('B' . $rowNum, $r['target_staffno']);
    $sheet->setCellValue('C' . $rowNum, $r['target_staffname']);
    $sheet->setCellValue('D' . $rowNum, $r['target_department']);
    $sheet->setCellValue('E' . $rowNum, $r['target_section']);
    $sheet->setCellValue('F' . $rowNum, $r['created_by_name']);
    $sheet->setCellValue('G' . $rowNum, $r['approval_status'] == 'APPROVED' ? 'APPROVED' : 'WAITING APPROVAL');
    $rowNum++;
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Second sheet: every staff in the department(s) this HOD heads with their
// status this quarter - mirrors the staff list under skill-matrix.php's
// overview tiles (same query, same status derivation, same order).
$hodDepartments = [];
$deptNameStmt = $conn->prepare("SELECT name FROM departments WHERE hod_user_id = ?");
$deptNameStmt->bind_param("i", $hodId);
$deptNameStmt->execute();
$deptNameResult = $deptNameStmt->get_result();
while ($deptRow = $deptNameResult->fetch_assoc()) {
    $hodDepartments[] = $deptRow['name'];
}

$staffStatus = [];
if (count($hodDepartments) > 0) {
    $placeholders = implode(',', array_fill(0, count($hodDepartments), '?'));
    $staffSql = "SELECT
                     u.staffno,
                     u.staffname,
                     COALESCE(dp.name, u.department) AS department,
                     COALESCE(s.name, u.section) AS section,
                     sme.id AS evaluation_id,
                     sme.approval_status,
                     creator.staffname AS created_by_name
                 FROM user u
                 LEFT JOIN departments dp ON u.department_id = dp.id
                 LEFT JOIN sections s ON u.section_id = s.id
                 LEFT JOIN skill_matrix_evaluations sme ON sme.id = (
                     SELECT latest.id
                     FROM skill_matrix_evaluations latest
                     WHERE latest.staffid = u.id
                     AND latest.eval_year = ?
                     AND latest.eval_quarter = ?
                     ORDER BY latest.evaluation_date DESC, latest.id DESC
                     LIMIT 1
                 )
                 LEFT JOIN user creator ON creator.id = sme.created_by
                 WHERE u.designation IN ('NON EXECUTIVE', 'CONTRACT')
                 AND u.status != 'RESIGN'
                 AND (dp.name IN ($placeholders) OR u.department IN ($placeholders))";
    $staffTypes = 'ii' . str_repeat('s', count($hodDepartments) * 2);
    $staffParams = array_merge([$currentYear, $currentQuarter], $hodDepartments, $hodDepartments);
    $staffStmt = $conn->prepare($staffSql);
    $staffStmt->bind_param($staffTypes, ...$staffParams);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();

    while ($srow = $staffResult->fetch_assoc()) {
        if ($srow['approval_status'] == 'APPROVED') {
            $srow['status'] = 'APPROVED';
        } else if ($srow['approval_status'] == 'PENDING') {
            $srow['status'] = 'WAITING APPROVAL';
        } else if ($srow['evaluation_id'] !== null) {
            $srow['status'] = 'DRAFT';
        } else {
            $srow['status'] = 'NOT SUBMITTED';
        }
        $staffStatus[] = $srow;
    }

    $statusOrder = ['NOT SUBMITTED' => 0, 'DRAFT' => 1, 'WAITING APPROVAL' => 2, 'APPROVED' => 3];
    usort($staffStatus, function ($a, $b) use ($statusOrder) {
        if ($statusOrder[$a['status']] != $statusOrder[$b['status']]) {
            return $statusOrder[$a['status']] - $statusOrder[$b['status']];
        }
        return strcasecmp($a['staffname'], $b['staffname']);
    });
}

$statusSheet = $spreadsheet->createSheet();
$statusSheet->setTitle('Staff Status');
$statusSheet->fromArray(['No.', 'Staff No.', 'Staff Name', 'Department', 'Section', 'Filled By', 'Status'], null, 'A1');
$statusSheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$statusSheet->getStyle('A1:' . $lastCol . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF337AB7');
$statusSheet->getStyle('A1:' . $lastCol . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = 2;
foreach ($staffStatus as $i => $r) {
    $statusSheet->setCellValueExplicit('A' . $rowNum, $i + 1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $statusSheet->setCellValue('B' . $rowNum, $r['staffno']);
    $statusSheet->setCellValue('C' . $rowNum, $r['staffname']);
    $statusSheet->setCellValue('D' . $rowNum, $r['department']);
    $statusSheet->setCellValue('E' . $rowNum, $r['section']);
    $statusSheet->setCellValue('F' . $rowNum, $r['created_by_name'] !== null ? $r['created_by_name'] : '-');
    $statusSheet->setCellValue('G' . $rowNum, $r['status']);
    $rowNum++;
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
    $statusSheet->getColumnDimension($col)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'Skill_Matrix_Approvals_Q' . $currentQuarter . '_' . $currentYear . '_' . date('Ymd_His') . '.xlsx';

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
