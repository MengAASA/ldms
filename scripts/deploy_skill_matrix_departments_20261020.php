<?php
// One-shot deploy script for choosing which departments can use the skill
// matrix (2026-10-20). Admin switches departments on/off on Organization >
// Skill Matrix Config; skill_matrix_department.php gates every skill matrix
// page on it.
//
//   1. COLUMN: add departments.skill_matrix_enabled TINYINT(1) NOT NULL
//      DEFAULT 1. Every existing department starts switched ON, so nobody
//      loses access until admin switches a department off. New departments
//      also default to ON.
//
// Until this has run, the pages behave as before (the gate treats a missing
// column as "on"), and the Organization page shows a notice instead of the
// department list.
//
// SAFETY
//   - Dry run by default: reports what it would do, writes nothing. Pass
//     --apply to commit.
//   - Idempotent: a re-run finds the column already there and does nothing.
//   - No data is changed or deleted; it only adds a column.
//
// USAGE
//  - CLI: php scripts/deploy_skill_matrix_departments_20261020.php [--apply]
//  - HTTP, for hosts with no terminal access - upload, then visit with the
//    matching ?key=:
//      https://<your-domain>/ldms/scripts/deploy_skill_matrix_departments_20261020.php?key=...
//      https://<your-domain>/ldms/scripts/deploy_skill_matrix_departments_20261020.php?key=...&apply=1
//    Delete this file from the server once you're done with it.
define('DEPLOY_SM_DEPT_TOKEN', '6a005cb8b4a1e8f412ba9eaa2044fbb8426b7e0702220353');

if (PHP_SAPI !== 'cli') {
    if (!isset($_GET['key']) || !hash_equals(DEPLOY_SM_DEPT_TOKEN, (string) $_GET['key'])) {
        http_response_code(403);
        header('Content-Type: text/plain');
        die('Forbidden');
    }
    header('Content-Type: text/plain');
}

require __DIR__ . '/../dbconn.php';

$apply = (PHP_SAPI === 'cli')
    ? in_array('--apply', $argv, true)
    : (isset($_GET['apply']) && $_GET['apply'] === '1');

echo "==============================================================\n";
echo "Skill matrix departments deploy - " . ($apply ? "APPLYING" : "DRY RUN") . "\n";
echo "==============================================================\n\n";

echo "--- Step 1: departments.skill_matrix_enabled ---\n";
$col = $conn->query("SHOW COLUMNS FROM departments LIKE 'skill_matrix_enabled'");
if ($col && $col->num_rows > 0) {
    echo "Column already exists - nothing to do.\n";
} else {
    $count = (int) $conn->query("SELECT COUNT(*) AS n FROM departments")->fetch_assoc()['n'];
    echo "Column missing. Would add it, with all {$count} departments switched ON.\n";
    if ($apply) {
        $ok = $conn->query("ALTER TABLE departments
                            ADD COLUMN skill_matrix_enabled TINYINT(1) NOT NULL DEFAULT 1");
        echo $ok ? "Column added.\n" : "FAILED to add column: {$conn->error}\n";
    } else {
        echo "Re-run with --apply to add it.\n";
    }
}

$col = $conn->query("SHOW COLUMNS FROM departments LIKE 'skill_matrix_enabled'");
if ($col && $col->num_rows > 0) {
    $r = $conn->query("SELECT SUM(skill_matrix_enabled = 1) AS on_, SUM(skill_matrix_enabled = 0) AS off_ FROM departments")->fetch_assoc();
    echo "\nNow: " . (int) $r['on_'] . " department(s) ON, " . (int) $r['off_'] . " OFF.\n";
}
echo "\nDone.\n";
