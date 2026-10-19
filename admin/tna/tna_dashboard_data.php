<?php
// Data behind the TNA Summary dashboard (tna_summary.php) and its Excel
// export (tna_summary_export.php), so the page and the workbook always show
// the same numbers. Everything is for the planning year (see planning_year.php).
//
// A TNA is owned either by one staff (tna.userid) or, for non-executives filled
// in as a group, by a grade within a department (tna.grade + tna.department,
// userid NULL). Status per owner is MAX(status): 'APPROVE' beats '1'/'' so an
// owner counts as approved once any of their rows is approved, matching the
// TNA list.

include_once __DIR__ . '/../../planning_year.php';

const TNA_DASH_SECTIONS = [
    'esgaware' => 'ESG',
    'selfaware' => 'Soft Skill',
    'leadaware' => 'Leadership Awareness',
    'busiaware' => 'Digital Transformation',
    'dataaware' => 'Data Driven',
    'functional' => 'Functional Awareness',
    'special' => 'Special Project',
];

const TNA_DASH_METHODS = [
    '1' => 'On Job Training',
    '2' => 'Coaching',
    '3' => 'External/In-House',
];

const TNA_DASH_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Staff who are expected to fill a TNA - the same people the TNA list shows
// per department (load_staff in fetch_tna.php).
const TNA_DASH_ELIGIBLE = "u.department IS NOT NULL AND u.department <> ''
    AND u.hodid != 0
    AND UPPER(COALESCE(u.status, '')) <> 'RESIGN'
    AND ((u.designation = 'NON EXECUTIVE' AND (u.usertype IS NULL OR u.usertype != ''))
        OR u.designation IN ('MANAGER (AM/HOS & ABOVE)', 'EXECUTIVE'))";

// "OTHERS" in the training dropdown means the title was typed into othertr.
const TNA_DASH_TITLE = "CASE WHEN t.training = 'OTHERS' AND COALESCE(t.othertr, '') <> '' THEN t.othertr ELSE t.training END";

function tnaDashRows($conn, $sql)
{
    $stmt = $conn->prepare($sql);
    $year = (string) ldmsPlanningYear();
    // Every query takes the planning year once per "?".
    $n = substr_count($sql, '?');
    if ($n > 0) {
        $params = array_fill(0, $n, $year);
        $stmt->bind_param(str_repeat('s', $n), ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// TNA Status (by Department): a HOD's group is complete only once every staff
// in it has filled a TNA (filled / staff is e.g. 10 / 10).
function tnaDashGroupComplete($filled, $staff)
{
    return $staff > 0 && $filled >= $staff;
}

function tnaDashPercent($part, $whole)
{
    return $whole > 0 ? round($part * 100 / $whole, 1) : 0;
}

function tnaDashboardData($conn)
{
    // Staff progress per department. Anyone who did fill a TNA (e.g. a HOD,
    // who has hodid 0) counts too, so completion never goes over 100%.
    $staff = tnaDashRows($conn, "
        SELECT u.department,
            COUNT(*) AS eligible,
            SUM(s.userid IS NOT NULL) AS submitted,
            SUM(s.status = 'APPROVE') AS approved
        FROM user u
        LEFT JOIN (
            SELECT userid, MAX(status) AS status
            FROM tna
            WHERE year = ? AND userid IS NOT NULL
            GROUP BY userid
        ) s ON s.userid = u.id
        WHERE (" . TNA_DASH_ELIGIBLE . ")
            OR (s.userid IS NOT NULL AND COALESCE(u.department, '') <> '')
        GROUP BY u.department");

    // Training needs (rows) and grade-group TNAs per department.
    $items = tnaDashRows($conn, "
        SELECT COALESCE(u.department, t.department) AS department,
            COUNT(*) AS items,
            COUNT(DISTINCT CASE WHEN t.userid IS NULL THEN t.grade END) AS grade_groups
        FROM tna t
        LEFT JOIN user u ON u.id = t.userid
        WHERE t.year = ?
        GROUP BY COALESCE(u.department, t.department)");

    $departments = [];
    $blank = ['eligible' => 0, 'submitted' => 0, 'approved' => 0, 'grade_groups' => 0, 'items' => 0];
    foreach ($staff as $r) {
        $departments[$r['department']] = [
            'eligible' => (int) $r['eligible'],
            'submitted' => (int) $r['submitted'],
            'approved' => (int) $r['approved'],
        ] + $blank;
    }
    foreach ($items as $r) {
        $name = $r['department'] ?: '(No Department)';
        $departments[$name] = ($departments[$name] ?? $blank);
        $departments[$name]['items'] = (int) $r['items'];
        $departments[$name]['grade_groups'] = (int) $r['grade_groups'];
    }
    ksort($departments);

    $deptRows = [];
    $kpi = ['eligible' => 0, 'submitted' => 0, 'approved' => 0, 'pending' => 0, 'not_submitted' => 0, 'grade_groups' => 0, 'items' => 0];
    foreach ($departments as $name => $d) {
        $d['pending'] = $d['submitted'] - $d['approved'];
        $d['not_submitted'] = $d['eligible'] - $d['submitted'];
        $d['completion'] = tnaDashPercent($d['submitted'], $d['eligible']);
        foreach ($kpi as $k => $v) {
            $kpi[$k] += $d[$k];
        }
        $deptRows[] = ['department' => $name] + $d;
    }
    $kpi['completion'] = tnaDashPercent($kpi['submitted'], $kpi['eligible']);
    $kpi['approval'] = tnaDashPercent($kpi['approved'], $kpi['submitted']);

    $gap = tnaDashRows($conn, "SELECT AVG(gap) AS avg_gap FROM tna t WHERE t.year = ? AND gap IS NOT NULL");
    $kpi['avg_gap'] = $gap[0]['avg_gap'] === null ? 0 : round($gap[0]['avg_gap'], 2);

    // Planned month of training, in calendar order.
    $monthCounts = array_fill_keys(TNA_DASH_MONTHS, 0);
    foreach (tnaDashRows($conn, "SELECT monthapply, COUNT(*) AS no FROM tna t WHERE t.year = ? GROUP BY monthapply") as $r) {
        $m = ucfirst(strtolower(substr(trim((string) $r['monthapply']), 0, 3)));
        if (isset($monthCounts[$m])) {
            $monthCounts[$m] += (int) $r['no'];
        }
    }
    $months = [];
    foreach ($monthCounts as $m => $no) {
        $months[] = ['month' => $m, 'no' => $no];
    }

    $top = [];
    foreach (tnaDashRows($conn, "
        SELECT " . TNA_DASH_TITLE . " AS training, COUNT(*) AS no
        FROM tna t
        WHERE t.year = ? AND COALESCE(t.training, '') <> ''
        GROUP BY " . TNA_DASH_TITLE . "
        ORDER BY no DESC, training
        LIMIT 10") as $r) {
        $top[] = ['training' => $r['training'], 'no' => (int) $r['no']];
    }

    // Average current vs target skill level per section.
    $bySection = [];
    foreach (tnaDashRows($conn, "
        SELECT section, COUNT(*) AS no,
            ROUND(AVG(currentskill), 2) AS current, ROUND(AVG(targetskill), 2) AS target, ROUND(AVG(gap), 2) AS gap
        FROM tna t
        WHERE t.year = ?
        GROUP BY section") as $r) {
        $bySection[$r['section']] = $r;
    }
    $skills = [];
    foreach (TNA_DASH_SECTIONS as $key => $label) {
        if (isset($bySection[$key])) {
            $r = $bySection[$key];
            $skills[] = [
                'section' => $label,
                'no' => (int) $r['no'],
                'current' => (float) $r['current'],
                'target' => (float) $r['target'],
                'gap' => (float) $r['gap'],
            ];
        }
    }

    $byMethod = [];
    foreach (tnaDashRows($conn, "SELECT trainingtype, COUNT(*) AS no FROM tna t WHERE t.year = ? GROUP BY trainingtype") as $r) {
        $byMethod[(string) $r['trainingtype']] = (int) $r['no'];
    }
    $methods = [];
    foreach (TNA_DASH_METHODS as $key => $label) {
        $methods[] = ['method' => $label, 'no' => $byMethod[$key] ?? 0];
    }

    return [
        'year' => ldmsPlanningYear(),
        'kpi' => $kpi,
        'departments' => $deptRows,
        'methods' => $methods,
        'months' => $months,
        'top_trainings' => $top,
        'skills' => $skills,
    ];
}
