<?php
// One-shot production deploy script for the "absent participant still
// pending PME" fix built and verified locally on 2026-10-29.
//
// Marking a participant ABSENT (admin/training/public/participant/
// participant_action.php) used to only set participation.attendance - the
// PME row the after_participation_insert trigger created stayed 'pending',
// so the HOD kept seeing it as "Pending" on staff/hod/pme/pme.php and kept
// getting reminders for it (found via M0729 / CULTURE MASTERCLASS 2,
// marked absent but pending for HOD M0107).
//
// participant_action.php now closes the PME as 'not_applicable' at the
// moment of marking absent. This script fixes the rows marked absent BEFORE
// that change: every PME still 'pending' whose participation is ABSENT
// moves to 'not_applicable'. Rows a HOD already approved/completed/verified
// are left exactly as they are.
//
// SAFETY
//   - Dry run by default: lists the rows it would change, writes nothing.
//     Pass --apply to commit.
//   - The exact row list is written to an undo-map TSV in this directory
//     BEFORE the UPDATE runs.
//   - Idempotent: only ever touches rows still at 'pending', so re-running
//     after a successful apply finds nothing to do.
//   - Undo for a single participant is the "UNDO ABSENT" button on the
//     participant list, not this script.
//
// USAGE
//  - CLI (php scripts/deploy_pme_absent_not_applicable_20261029.php [--apply]) -
//    no token needed, CLI access already implies server access.
//  - HTTP, for cPanel hosts with no terminal/SSH access - upload this file
//    normally, then visit it in a browser with the matching ?key=:
//      https://<your-domain>/ldms/scripts/deploy_pme_absent_not_applicable_20261029.php?key=c352a48300e7b222bee368888f370e4b110a43c447a7dffa
//      https://<your-domain>/ldms/scripts/deploy_pme_absent_not_applicable_20261029.php?key=...&apply=1
//    Delete this file from the server once you're done with it; it is a
//    one-time fix, not something that needs to stay reachable.
define('DEPLOY_PME_TOKEN', 'c352a48300e7b222bee368888f370e4b110a43c447a7dffa');

if (PHP_SAPI !== 'cli') {
    if (!isset($_GET['key']) || !hash_equals(DEPLOY_PME_TOKEN, (string) $_GET['key'])) {
        http_response_code(403);
        header('Content-Type: text/plain');
        die('Forbidden');
    }
    header('Content-Type: text/plain');
    @set_time_limit(0);
    @ignore_user_abort(true);
}

require __DIR__ . '/../dbconn.php';

$apply = (PHP_SAPI === 'cli')
    ? in_array('--apply', $argv, true)
    : (isset($_GET['apply']) && $_GET['apply'] === '1');

echo "==============================================================\n";
echo "PME absent -> not_applicable deploy - " . ($apply ? "APPLYING" : "DRY RUN") . "\n";
echo "==============================================================\n\n";

// 'not_applicable' must already be in the enum (added by
// deploy_pme_not_applicable_20260915.php) or the UPDATE below is rejected.
$col = $conn->query("SHOW COLUMNS FROM pme WHERE Field = 'status'")->fetch_assoc();
if (strpos($col['Type'], "'not_applicable'") === false) {
    echo "pme.status has no 'not_applicable' value - run deploy_pme_not_applicable_20260915.php first.\n";
    exit(1);
}

// Read and write in one transaction so the UPDATE applies to exactly the
// rows listed and backed up, even if admins are marking attendance meanwhile.
$conn->begin_transaction();

$res = $conn->query("
    SELECT p.id, p.participationid, p.staffname, p.staffno, p.training_title, p.hodid, p.from_date, p.to_date
    FROM pme p
    JOIN participation pa ON pa.id = p.participationid
    WHERE p.status = 'pending' AND pa.attendance = 'ABSENT'
    ORDER BY p.hodid, p.staffno
    FOR UPDATE
");
$toFix = [];
while ($row = $res->fetch_assoc()) { $toFix[] = $row; }

echo "Absent participants with a pending PME: " . count($toFix) . "\n\n";
foreach ($toFix as $r) {
    echo "  pme {$r['id']}  hod {$r['hodid']}  {$r['staffno']}  {$r['training_title']}  ({$r['from_date']} - {$r['to_date']})\n";
}
echo "\n";

if (empty($toFix)) {
    $conn->rollback(); // nothing to update
    echo "Nothing to do.\n";
} else {
    $backupPath = __DIR__ . '/backup_pme_absent_not_applicable_' . date('Ymd_His') . '_undo_map.tsv';
    $fh = fopen($backupPath, 'w');
    fwrite($fh, "pme_id\tparticipationid\tstaffname\tstaffno\ttraining_title\thodid\told_status\tnew_status\n");
    foreach ($toFix as $r) {
        fwrite($fh, "{$r['id']}\t{$r['participationid']}\t{$r['staffname']}\t{$r['staffno']}\t{$r['training_title']}\t{$r['hodid']}\tpending\tnot_applicable\n");
    }
    fclose($fh);
    echo "Backup written: {$backupPath}\n";

    if ($apply) {
        $idList = implode(',', array_map(fn($r) => (int) $r['id'], $toFix));
        $ok = $conn->query("UPDATE pme SET status = 'not_applicable' WHERE id IN ($idList) AND status = 'pending'");
        if ($ok) {
            $applied = $conn->affected_rows;
            $conn->commit();
            echo "Applied {$applied} updates.\n";
        } else {
            $conn->rollback();
            echo "FAILED: {$conn->error}\n";
        }
    } else {
        $conn->rollback(); // read-only dry run, nothing to keep
        echo "Would apply " . count($toFix) . " updates (re-run with --apply).\n";
    }
}
echo "\n";

echo "==============================================================\n";
echo $apply ? "Done.\n" : "Dry run complete - nothing written. Re-run with --apply to commit.\n";
echo "==============================================================\n";
?>
