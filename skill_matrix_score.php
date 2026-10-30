<?php
// Skill matrix scores for the dashboards (admin/skill-matrix and staff/hod/skill-matrix).
// Requires skill_matrix_period.php.

// Level name for an overall score, rounding down to the bands on matrix-chart.php.
function skillMatrixScoreLevel($score)
{
    if ($score >= 100) {
        return 'Highly Skilled';
    }
    if ($score >= 75) {
        return 'Competent';
    }
    if ($score >= 50) {
        return 'Medium Competency';
    }
    if ($score >= 25) {
        return 'Novice';
    }
    return 'Minimal Competency';
}

// Overall score of each evaluation: the average of its topic percentages, worked out
// the same way as the TOTAL on matrix-chart.php. Returns array(evaluation_id => score).
function skillMatrixOverallScores($conn, $evaluationIds)
{
    $scores = array();
    if (count($evaluationIds) == 0) {
        return $scores;
    }

    $placeholders = implode(',', array_fill(0, count($evaluationIds), '?'));
    $topicSql = "SELECT
                    t.evaluation_id,
                    SUM(i.rating) AS total_rating,
                    COUNT(i.id) AS item_count
                FROM skill_matrix_topics t
                INNER JOIN skill_matrix_items i ON i.topic_id = t.id
                WHERE t.evaluation_id IN ($placeholders)
                GROUP BY t.evaluation_id, t.section_type, t.topic_name";
    $topicStmt = $conn->prepare($topicSql);
    $topicStmt->bind_param(str_repeat('i', count($evaluationIds)), ...$evaluationIds);
    $topicStmt->execute();
    $topicResult = $topicStmt->get_result();

    $scoreTotals = array();
    while ($topicRow = $topicResult->fetch_assoc()) {
        $evaluationId = (int) $topicRow['evaluation_id'];
        if (!isset($scoreTotals[$evaluationId])) {
            $scoreTotals[$evaluationId] = array('total' => 0, 'count' => 0);
        }
        $scoreTotals[$evaluationId]['total'] += $topicRow['item_count'] > 0 ? ($topicRow['total_rating'] / ($topicRow['item_count'] * 5)) * 100 : 0;
        $scoreTotals[$evaluationId]['count']++;
    }

    foreach ($scoreTotals as $evaluationId => $scoreTotal) {
        $scores[$evaluationId] = round($scoreTotal['total'] / $scoreTotal['count'], 2);
    }

    return $scores;
}

// "Average Skill Score by Quarter": the average overall score of approved matrices for
// each of the last 8 quarters up to the evaluation quarter. $filterSql / $filterTypes /
// $filterParams are WHERE conditions on user u, departments dp and sections s picking
// the staff to count. Each staff's latest matrix in a quarter is the one that counts,
// as in the staff lists. Returns array(array('label', 'average', 'count')) in time
// order, from the first quarter with a result; a quarter with nothing approved has a
// null average.
function skillMatrixQuarterScores($conn, $filterSql, $filterTypes, $filterParams)
{
    list($fillYear, $fillQuarter) = skillMatrixFillPeriod();
    $lastPeriodIndex = $fillYear * 4 + $fillQuarter - 1;
    $firstPeriodIndex = $lastPeriodIndex - 7;

    $sql = "SELECT
                sme.id,
                sme.eval_year,
                sme.eval_quarter
            FROM skill_matrix_evaluations sme
            INNER JOIN user u ON u.id = sme.staffid
            LEFT JOIN departments dp ON u.department_id = dp.id
            LEFT JOIN sections s ON u.section_id = s.id
            WHERE sme.approval_status = 'APPROVED'
            AND sme.eval_year * 4 + sme.eval_quarter - 1 BETWEEN ? AND ?
            AND sme.id = (
                SELECT latest.id
                FROM skill_matrix_evaluations latest
                WHERE latest.staffid = sme.staffid
                AND latest.eval_year = sme.eval_year
                AND latest.eval_quarter = sme.eval_quarter
                ORDER BY latest.evaluation_date DESC, latest.id DESC
                LIMIT 1
            )
            AND " . $filterSql;

    $params = array_merge([$firstPeriodIndex, $lastPeriodIndex], $filterParams);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii" . $filterTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $evaluationPeriods = array();
    while ($row = $result->fetch_assoc()) {
        $evaluationPeriods[(int) $row['id']] = (int) $row['eval_year'] * 4 + (int) $row['eval_quarter'] - 1;
    }

    $periodTotals = array();
    foreach (skillMatrixOverallScores($conn, array_keys($evaluationPeriods)) as $evaluationId => $score) {
        $periodIndex = $evaluationPeriods[$evaluationId];
        if (!isset($periodTotals[$periodIndex])) {
            $periodTotals[$periodIndex] = array('total' => 0, 'count' => 0);
        }
        $periodTotals[$periodIndex]['total'] += $score;
        $periodTotals[$periodIndex]['count']++;
    }

    $quarters = array();
    if (count($periodTotals) > 0) {
        for ($periodIndex = min(array_keys($periodTotals)); $periodIndex <= $lastPeriodIndex; $periodIndex++) {
            $hasScore = isset($periodTotals[$periodIndex]);
            $quarters[] = array(
                'label' => 'Q' . ($periodIndex % 4 + 1) . ' ' . intdiv($periodIndex, 4),
                'average' => $hasScore ? round($periodTotals[$periodIndex]['total'] / $periodTotals[$periodIndex]['count'], 2) : null,
                'count' => $hasScore ? $periodTotals[$periodIndex]['count'] : 0,
            );
        }
    }

    return $quarters;
}
?>
