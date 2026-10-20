<?php
session_start();
include "../../dbconn.php";
include "../../skill_matrix_period.php";

require '../../asset/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
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

if (!isset($_SESSION['fullname']) || !($_SESSION['role'] == 'ADMIN' || skillMatrixUserCanUse())) {
    header("Location: ../../login.php");
    exit();
}

$staffid = isset($_GET['staffid']) ? (int) $_GET['staffid'] : 0;
list($currentYear, $currentQuarter) = skillMatrixFillPeriod();
if (isset($_GET['period']) && $_GET['period'] == 'previous') {
    list($currentYear, $currentQuarter) = skillMatrixPreviousPeriod($currentYear, $currentQuarter);
}

$stmt = $conn->prepare("SELECT
                            u.staffno,
                            u.staffname,
                            u.designation,
                            u.grade,
                            u.department AS department_value,
                            COALESCE(dp.name, u.department) AS department,
                            COALESCE(s.name, u.section) AS section
                        FROM user u
                        LEFT JOIN departments dp ON u.department_id = dp.id
                        LEFT JOIN sections s ON u.section_id = s.id
                        WHERE u.id = ?");
$stmt->bind_param("i", $staffid);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();

if (!$staff) {
    header("Location: skill-matrix.php");
    exit();
}

if (skillMatrixUserCanUse() && $_SESSION['role'] != 'ADMIN') {
    if (!isset($_SESSION['department']) || $_SESSION['department'] == '' || $staff['department_value'] != $_SESSION['department']) {
        header("Location: skill-matrix.php");
        exit();
    }
}

$quarterStmt = $conn->prepare("SELECT id, evaluation_date FROM skill_matrix_evaluations WHERE staffid = ? AND eval_year = ? AND eval_quarter = ? ORDER BY evaluation_date DESC, id DESC LIMIT 1");
$quarterStmt->bind_param("iii", $staffid, $currentYear, $currentQuarter);
$quarterStmt->execute();
$quarterResult = $quarterStmt->get_result()->fetch_assoc();

if (!$quarterResult) {
    header("Location: skill-matrix.php");
    exit();
}

$viewEvaluationId = (int) $quarterResult['id'];
$displayEvaluationDate = date('d/m/Y', strtotime($quarterResult['evaluation_date']));

$signOffStmt = $conn->prepare("SELECT
                                    creator.staffname AS evaluated_by,
                                    verifier.staffname AS verified_by,
                                    approver.staffname AS approved_by
                                FROM skill_matrix_evaluations sme
                                LEFT JOIN user creator ON creator.id = sme.created_by
                                LEFT JOIN user verifier ON verifier.id = creator.hodid
                                LEFT JOIN user approver ON approver.id = sme.approved_by
                                WHERE sme.id = ?");
$signOffStmt->bind_param("i", $viewEvaluationId);
$signOffStmt->execute();
$signOffRow = $signOffStmt->get_result()->fetch_assoc();
$signOff = array(
    'evaluated_by' => $signOffRow && $signOffRow['evaluated_by'] ? $signOffRow['evaluated_by'] : '',
    'verified_by' => $signOffRow && $signOffRow['verified_by'] ? $signOffRow['verified_by'] : '',
    'approved_by' => $signOffRow && $signOffRow['approved_by'] ? $signOffRow['approved_by'] : ''
);

$viewTopics = array('knowledge' => array(), 'skill' => array(), 'ability' => array());

$viewStmt = $conn->prepare("SELECT
                                t.id AS topic_id,
                                t.section_type,
                                t.topic_name,
                                t.sort_order AS topic_sort_order,
                                i.evaluation_text,
                                i.rating,
                                i.sort_order AS item_sort_order
                            FROM skill_matrix_topics t
                            LEFT JOIN skill_matrix_items i ON i.topic_id = t.id
                            WHERE t.evaluation_id = ?
                            ORDER BY FIELD(t.section_type, 'knowledge', 'skill', 'ability'), t.sort_order, i.sort_order");
$viewStmt->bind_param("i", $viewEvaluationId);
$viewStmt->execute();
$viewResult = $viewStmt->get_result();

while ($viewRow = $viewResult->fetch_assoc()) {
    $sectionType = $viewRow['section_type'];
    $topicId = $viewRow['topic_id'];

    if (!isset($viewTopics[$sectionType][$topicId])) {
        $viewTopics[$sectionType][$topicId] = array(
            'topic_name' => $viewRow['topic_name'],
            'items' => array()
        );
    }

    if ($viewRow['evaluation_text'] !== null) {
        $viewTopics[$sectionType][$topicId]['items'][] = array(
            'evaluation_text' => $viewRow['evaluation_text'],
            'rating' => (int) $viewRow['rating']
        );
    }
}

$sectionGroups = array(
    'knowledge' => array('title' => 'KNOWLEDGE', 'startcol' => 1),
    'skill' => array('title' => 'SKILLS', 'startcol' => 5),
    'ability' => array('title' => 'ABILITIES', 'startcol' => 9)
);

$maxTopics = 0;
foreach ($sectionGroups as $sectionKey => $group) {
    $maxTopics = max($maxTopics, count($viewTopics[$sectionKey]));
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Competencies Evaluation Matrix');

$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11);

$sheet->mergeCells('A1:' . $lastCol . '1')->setCellValue('A1', 'COMPETENCIES EVALUATION MATRIX');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Name:');
$sheet->setCellValue('B3', $staff['staffname']);
$sheet->setCellValue('E3', 'Department:');
$sheet->setCellValue('F3', $staff['department']);

$sheet->setCellValue('A4', 'Emp. No:');
$sheet->setCellValue('B4', $staff['staffno']);
$sheet->setCellValue('E4', 'Section:');
$sheet->setCellValue('F4', $staff['section']);

$sheet->setCellValue('A5', 'Designation:');
$sheet->setCellValue('B5', $staff['designation'] . ' / ' . $staff['grade']);
$sheet->setCellValue('E5', 'Evaluation Date:');
$sheet->setCellValue('F5', $displayEvaluationDate);

$sheet->getStyle('A3:A5')->getFont()->setBold(true);
$sheet->getStyle('E3:E5')->getFont()->setBold(true);

$sheet->setCellValue('A7', "Note: Criteria's to be ranked with scores from 1 to 5 as per scoring description described below:");
$sheet->getStyle('A7')->getFont()->setItalic(true);

$headerRow = 9;
foreach ($sectionGroups as $sectionKey => $group) {
    $startColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['startcol']);
    $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['startcol'] + 2);
    $sheet->mergeCells($startColLetter . $headerRow . ':' . $endColLetter . $headerRow)
        ->setCellValue($startColLetter . $headerRow, $group['title']);
}
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF337AB7');

$topicBandHeight = 9;
$blockStartRow = $headerRow + 2;

foreach ($sectionGroups as $sectionKey => $group) {
    $noCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['startcol']);
    $textCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['startcol'] + 1);
    $ratingCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['startcol'] + 2);

    $topics = array_values($viewTopics[$sectionKey]);
    $topicIndex = 0;

    foreach ($topics as $topic) {
        $bandStart = $blockStartRow + ($topicIndex * $topicBandHeight);
        $letter = chr(65 + $topicIndex);

        $sheet->setCellValue($noCol . $bandStart, $letter);
        $sheet->setCellValue($textCol . $bandStart, $topic['topic_name']);
        $sheet->getStyle($noCol . $bandStart . ':' . $ratingCol . $bandStart)->getFont()->setBold(true);

        $itemTotal = 0;
        $itemCount = count($topic['items']);

        foreach ($topic['items'] as $itemIndex => $item) {
            if ($itemIndex >= 5) {
                break;
            }
            $itemRow = $bandStart + 1 + $itemIndex;
            $sheet->setCellValueExplicit($noCol . $itemRow, ($itemIndex + 1) . '.', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue($textCol . $itemRow, $item['evaluation_text']);
            $sheet->setCellValueExplicit($ratingCol . $itemRow, $item['rating'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $itemTotal += $item['rating'];
        }

        $average = $itemCount > 0 ? $itemTotal / $itemCount : 0;
        $percentage = $itemCount > 0 ? ($itemTotal / ($itemCount * 5)) * 100 : 0;

        $avgRow = $bandStart + 6;
        $pctRow = $bandStart + 7;
        $sheet->setCellValue($textCol . $avgRow, 'Average Total:');
        $sheet->setCellValue($ratingCol . $avgRow, round($average, 2));
        $sheet->setCellValue($textCol . $pctRow, 'Percentage:');
        $sheet->setCellValue($ratingCol . $pctRow, round($percentage) . '%');
        $sheet->getStyle($textCol . $avgRow . ':' . $ratingCol . $pctRow)->getFont()->setBold(true);

        $topicIndex++;
    }
}

$lastBlockRow = $blockStartRow + ($maxTopics * $topicBandHeight);
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . max($headerRow, $lastBlockRow - 2))
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . max($headerRow, $lastBlockRow - 2))
    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

$signOffRowNo = $lastBlockRow + 1;
$sheet->setCellValue('A' . $signOffRowNo, 'Evaluated By:');
$sheet->setCellValue('B' . $signOffRowNo, $signOff['evaluated_by']);
$sheet->setCellValue('E' . $signOffRowNo, 'Verified By:');
$sheet->setCellValue('F' . $signOffRowNo, $signOff['verified_by']);
$sheet->setCellValue('I' . $signOffRowNo, 'Approved By:');
$sheet->setCellValue('J' . $signOffRowNo, $signOff['approved_by']);
$sheet->getStyle('A' . $signOffRowNo . ':I' . $signOffRowNo)->getFont()->setBold(true);

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(38);
$sheet->getColumnDimension('C')->setWidth(9);
$sheet->getColumnDimension('D')->setWidth(3);
$sheet->getColumnDimension('E')->setWidth(6);
$sheet->getColumnDimension('F')->setWidth(38);
$sheet->getColumnDimension('G')->setWidth(9);
$sheet->getColumnDimension('H')->setWidth(3);
$sheet->getColumnDimension('I')->setWidth(6);
$sheet->getColumnDimension('J')->setWidth(38);
$sheet->getColumnDimension('K')->setWidth(9);

$filename = 'Competencies Matrix - ' . $staff['staffname'] . ' - Q' . $currentQuarter . '_' . $currentYear . '.xlsx';

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
