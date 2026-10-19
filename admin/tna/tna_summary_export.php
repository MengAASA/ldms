<?php
// Excel download of the TNA Summary dashboard: one sheet per dashboard view,
// plus every TNA row for the planning year.
session_start();
if (!isset($_SESSION['fullname']) || $_SESSION['role'] != 'ADMIN') {
    header("Location: ../../login.php");
    exit();
}

include "../../dbconn.php";
include_once __DIR__ . '/tna_dashboard_data.php';
require '../../asset/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

date_default_timezone_set('Asia/Kuala_Lumpur');
$data = tnaDashboardData($conn);
$year = $data['year'];

// Writes a header row + rows starting at $startRow, styled as a bordered table.
function tnaExportTable($sheet, array $headers, array $rows, $startRow = 1)
{
    $sheet->fromArray($headers, null, 'A' . $startRow);
    if ($rows) {
        $sheet->fromArray($rows, null, 'A' . ($startRow + 1), true);
    }
    $lastCol = Coordinate::stringFromColumnIndex(count($headers));
    $lastRow = $startRow + max(count($rows), 0);

    $head = $sheet->getStyle("A$startRow:$lastCol$startRow");
    $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2A78D6');
    $sheet->getStyle("A$startRow:$lastCol$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->setAutoFilter("A$startRow:$lastCol$lastRow");
    $sheet->freezePane('A' . ($startRow + 1));
    for ($c = 1; $c <= count($headers); $c++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
    }
}

function tnaExportSheet($spreadsheet, $title)
{
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($title);
    $sheet->getSheetView()->setZoomScale(85);
    return $sheet;
}

$spreadsheet = new Spreadsheet();

// Summary
$sheet = $spreadsheet->getSheet(0);
$sheet->setTitle('Summary');
$sheet->getSheetView()->setZoomScale(85);
$sheet->setCellValue('A1', "TNA Summary - FY $year");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Generated ' . date('d/m/Y H:i'));
$k = $data['kpi'];
tnaExportTable($sheet, ['Measure', 'Value'], [
    ['Staff expected to fill TNA', $k['eligible']],
    ['Staff TNA submitted', $k['submitted']],
    ['Completion (%)', $k['completion']],
    ['Approved', $k['approved']],
    ['Pending approval', $k['pending']],
    ['Not submitted', $k['not_submitted']],
    ['Approval rate of submitted (%)', $k['approval']],
    ['Grade TNA (non-executive groups)', $k['grade_groups']],
    ['Training needs identified', $k['items']],
    ['Average skill gap', $k['avg_gap']],
], 4);

// By department
$rows = [];
$i = 1;
foreach ($data['departments'] as $d) {
    $rows[] = [$i++, $d['department'], $d['eligible'], $d['submitted'], $d['completion'], $d['approved'], $d['pending'], $d['not_submitted'], $d['grade_groups'], $d['items']];
}
$rows[] = ['', 'TOTAL', $k['eligible'], $k['submitted'], $k['completion'], $k['approved'], $k['pending'], $k['not_submitted'], $k['grade_groups'], $k['items']];
$sheet = tnaExportSheet($spreadsheet, 'By Department');
tnaExportTable($sheet, ['No.', 'Department', 'Staff', 'TNA Submitted', 'Completion (%)', 'Approved', 'Pending Approval', 'Not Submitted', 'Grade TNA', 'Training Needs'], $rows);
$sheet->getStyle('A' . (count($rows) + 1) . ':J' . (count($rows) + 1))->getFont()->setBold(true);

// HOD status - same rule as the TNA Status table on the page.
$stmt = $conn->prepare("
    SELECT u.department, hod.staffname AS hod_name,
        COUNT(DISTINCT t.userid) AS filled,
        COUNT(DISTINCT CASE WHEN (" . TNA_DASH_ELIGIBLE . ") OR t.userid IS NOT NULL THEN u.id END) AS staff
    FROM user u
    LEFT JOIN user hod ON u.hodid = hod.id
    LEFT JOIN tna t ON u.id = t.userid AND t.year = ?
    WHERE hod.staffname IS NOT NULL
        AND UPPER(COALESCE(u.status, '')) <> 'RESIGN'
        AND u.id NOT IN (SELECT hodid FROM user WHERE hodid IS NOT NULL)
    GROUP BY u.department, u.hodid, hod.staffname
    ORDER BY u.department, hod.staffname");
$yearStr = (string) $year;
$stmt->bind_param('s', $yearStr);
$stmt->execute();
$rows = [];
$i = 1;
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $rows[] = [$i++, $r['department'], $r['hod_name'], (int) $r['filled'], (int) $r['staff'], tnaDashGroupComplete((int) $r['filled'], (int) $r['staff']) ? 'COMPLETED' : 'PENDING'];
}
$stmt->close();
$sheet = tnaExportSheet($spreadsheet, 'HOD Status');
tnaExportTable($sheet, ['No.', 'Department', 'Head of Department', 'Filled', 'Staff', 'Status'], $rows);

// Section & skill gap
$total = array_sum(array_column($data['skills'], 'no'));
$rows = [];
foreach ($data['skills'] as $s) {
    $rows[] = [$s['section'], $s['no'], tnaDashPercent($s['no'], $total), $s['current'], $s['target'], $s['gap']];
}
$sheet = tnaExportSheet($spreadsheet, 'Section & Skill Gap');
tnaExportTable($sheet, ['Section', 'Training Needs', 'Share (%)', 'Avg Current Skill', 'Avg Target Skill', 'Avg Gap'], $rows);

// Training method
$total = array_sum(array_column($data['methods'], 'no'));
$rows = [];
foreach ($data['methods'] as $m) {
    $rows[] = [$m['method'], $m['no'], tnaDashPercent($m['no'], $total)];
}
$sheet = tnaExportSheet($spreadsheet, 'Training Method');
tnaExportTable($sheet, ['Training Method', 'Training Needs', 'Share (%)'], $rows);

// Monthly plan
$rows = [];
foreach ($data['months'] as $m) {
    $rows[] = [$m['month'], $m['no']];
}
$sheet = tnaExportSheet($spreadsheet, 'Monthly Plan');
tnaExportTable($sheet, ['Planned Month', 'Training Needs'], $rows);

// Top trainings
$rows = [];
$i = 1;
foreach ($data['top_trainings'] as $t) {
    $rows[] = [$i++, $t['training'], $t['no']];
}
$sheet = tnaExportSheet($spreadsheet, 'Top Trainings');
tnaExportTable($sheet, ['Rank', 'Training', 'Requests'], $rows);

// Every TNA row
$stmt = $conn->prepare("
    SELECT COALESCE(u.department, t.department) AS department, u.staffno, u.staffname, u.designation, t.grade,
        t.section, t.task, " . TNA_DASH_TITLE . " AS training, t.trainingtype, t.monthapply,
        t.currentskill, t.targetskill, t.gap, t.status, t.dateapprove
    FROM tna t
    LEFT JOIN user u ON u.id = t.userid
    WHERE t.year = ?
    ORDER BY department, u.staffname, t.grade, t.id");
$stmt->bind_param('s', $yearStr);
$stmt->execute();
$rows = [];
$i = 1;
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $rows[] = [
        $i++,
        $r['department'],
        $r['staffno'],
        $r['staffname'] ?? ($r['grade'] !== null ? 'GRADE ' . $r['grade'] : ''),
        $r['designation'],
        TNA_DASH_SECTIONS[$r['section']] ?? $r['section'],
        $r['task'],
        $r['training'],
        TNA_DASH_METHODS[(string) $r['trainingtype']] ?? $r['trainingtype'],
        $r['monthapply'],
        $r['currentskill'],
        $r['targetskill'],
        $r['gap'],
        $r['status'] === 'APPROVE' ? 'APPROVED' : 'WAITING APPROVAL',
        $r['dateapprove'] ? date('d/m/Y', strtotime($r['dateapprove'])) : '',
    ];
}
$stmt->close();
$sheet = tnaExportSheet($spreadsheet, 'TNA Detail');
tnaExportTable($sheet, ['No.', 'Department', 'Staff No', 'Staff Name / Grade', 'Designation', 'Section', 'Task / Problem', 'Training', 'Training Method', 'Planned Month', 'Current Skill', 'Target Skill', 'Gap', 'Status', 'Date Approved'], $rows);
// Long free text - cap the width and wrap instead of auto-sizing.
foreach (['G' => 50, 'H' => 45] as $col => $width) {
    $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth($width);
    $sheet->getStyle($col . ':' . $col)->getAlignment()->setWrapText(true);
}

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="TNA_Summary_FY' . $year . '_' . date('Ymd') . '.xlsx"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit();
