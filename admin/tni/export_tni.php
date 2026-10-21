<?php
// Excel download of the TNI list for the planning year: a summary sheet (one
// row per department, as on tni_list.php) and every TNI row in full.
session_start();
if (!isset($_SESSION['fullname']) || $_SESSION['role'] != 'ADMIN') {
    header("Location: ../../login.php");
    exit();
}

include "../../dbconn.php";
include_once __DIR__ . '/../../planning_year.php';
require '../../asset/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

date_default_timezone_set('Asia/Kuala_Lumpur');
$year = (string) ldmsPlanningYear();

// Same labels as the TNI form (manage_tni.php).
$skillLevels = [
    1 => '1 - Fundamental Awareness',
    2 => '2 - Novice',
    3 => '3 - Intermediate',
    4 => '4 - Proficient',
    5 => '5 - Expert',
];
$methods = [
    '1' => '1 - On Job Training',
    '2' => '2 - Coaching',
    '3' => '3 - External / In-house',
];

// Header row + rows starting at $startRow, styled as a bordered table.
function tniExportTable($sheet, array $headers, array $rows, $startRow = 1)
{
    $sheet->fromArray($headers, null, 'A' . $startRow);
    if ($rows) {
        $sheet->fromArray($rows, null, 'A' . ($startRow + 1), true);
    }
    $lastCol = Coordinate::stringFromColumnIndex(count($headers));
    $lastRow = $startRow + count($rows);

    $head = $sheet->getStyle("A$startRow:$lastCol$startRow");
    $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2A78D6');
    $sheet->getStyle("A$startRow:$lastCol$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A$startRow:$lastCol$lastRow")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $sheet->setAutoFilter("A$startRow:$lastCol$lastRow");
    $sheet->freezePane('A' . ($startRow + 1));
    for ($c = 1; $c <= count($headers); $c++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
    }
}

// HODs per department - the same match tni_list.php uses (a user with
// usertype HOD in that department). A department can have more than one;
// like the on-screen list, the summary then repeats the department once per
// HOD.
$hods = [];
$result = $conn->query("SELECT department, staffname
                        FROM user
                        WHERE usertype = 'HOD' AND UPPER(COALESCE(status, '')) <> 'RESIGN'
                        ORDER BY department, staffname");
while ($row = $result->fetch_assoc()) {
    $hods[$row['department']][] = $row['staffname'];
}

// The HOD assigned to each department on the Organization page - one per
// department, used for the staff columns on the TNI Detail sheet (TNI rows
// themselves are per department and carry no staff).
$assignedHods = [];
$result = $conn->query("SELECT d.name, u.staffno, u.staffname
                        FROM departments d
                        JOIN user u ON u.id = d.hod_user_id");
while ($row = $result->fetch_assoc()) {
    $assignedHods[$row['name']] = $row;
}

$stmt = $conn->prepare("SELECT * FROM tni WHERE year = ? ORDER BY department, id");
$stmt->bind_param('s', $year);
$stmt->execute();
$tniRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$spreadsheet = new Spreadsheet();

// Summary: one row per department and HOD, like the on-screen list.
$summary = [];
foreach ($tniRows as $r) {
    $dept = $r['department'];
    if (!isset($summary[$dept])) {
        $summary[$dept] = ['items' => 0, 'gap' => 0];
    }
    $summary[$dept]['items']++;
    $summary[$dept]['gap'] += (int) $r['gap'];
}
$sheet = $spreadsheet->getSheet(0);
$sheet->setTitle('Summary');
$sheet->getSheetView()->setZoomScale(85);
$sheet->setCellValue('A1', "TNI List - FY $year");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Generated ' . date('d/m/Y H:i'));
$rows = [];
$i = 1;
foreach ($summary as $dept => $s) {
    foreach ($hods[$dept] ?? [''] as $hod) {
        $rows[] = [$i++, $dept, $hod, $s['items'], $s['gap']];
    }
}
// Counted from the TNI rows, so a department listed under two HODs is only
// counted once.
$rows[] = ['', 'TOTAL', '', count($tniRows), array_sum(array_column($summary, 'gap'))];
tniExportTable($sheet, ['No.', 'Department', 'HOD', 'Performance Indicators', 'Total Gap'], $rows, 4);
$sheet->getStyle('A' . (count($rows) + 4) . ':E' . (count($rows) + 4))->getFont()->setBold(true);

// Every TNI row once, with the same columns as the TNI form, plus the
// department's assigned HOD (one per department, so rows are never repeated).
$rows = [];
$i = 1;
foreach ($tniRows as $r) {
    $hod = $assignedHods[$r['department']] ?? ['staffno' => '', 'staffname' => ''];
    $rows[] = [
        $i++,
        $r['department'],
        $hod['staffno'],
        $hod['staffname'],
        $r['training'],
        $skillLevels[(int) $r['expected']] ?? $r['expected'],
        $skillLevels[(int) $r['actual']] ?? $r['actual'],
        $r['gap'],
        $r['cause'],
        $r['ask'],
        $methods[(string) $r['method']] ?? $r['method'],
        $r['evaluation'],
    ];
}
$sheet = $spreadsheet->createSheet();
$sheet->setTitle('TNI Detail');
$sheet->getSheetView()->setZoomScale(85);
tniExportTable($sheet, ['No.', 'Department', 'Staff No. (HOD)', 'Staff Name (HOD)', 'Performance Indicator (Critical to Quality/Process)',
    'Expected', 'Actual', 'Gap', 'Possible Causes', 'Attitude / Skill / Knowledge Required', 'L&D Method', 'Evaluation Method'], $rows);
// Long free text - cap the width and wrap instead of auto-sizing.
foreach (['E' => 50, 'I' => 40, 'J' => 40, 'L' => 35] as $col => $width) {
    $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth($width);
    $sheet->getStyle($col . ':' . $col)->getAlignment()->setWrapText(true);
}

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="TNI_List_FY' . $year . '_' . date('Ymd') . '.xlsx"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit();
