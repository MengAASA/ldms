<?php
// One-shot deploy script for the HOD link fixes built on 2026-10-12. Like
// deploy_hod_and_status_fix_20260904.php, the trigger and the data only
// exist in whatever database they were applied to - a fresh export/import
// from production wipes the local copy - so this applies both directly to
// the database dbconn.php points at.
//
// The two steps, in order:
//
//   1. TRIGGER: trg_departments_hod_update is replaced so that assigning a
//      HOD also clears the new HOD's own hodid. The 2026-09-04 version
//      skipped the HOD's row to avoid a self-loop, which left the new HOD
//      still reporting to whoever they reported to before - usually the HOD
//      they just replaced. Standalone version: scripts/hod_trigger.sql
//
//   2. HODID BACKFILL, for non-resigned staff only:
//        a. a department's assigned HOD whose own hodid is not 0 -> 0
//        b. staff with no hodid (NULL or 0) whose department has a HOD
//           -> that HOD
//      Staff who point at a DIFFERENT person than their department's HOD
//      are reported but NOT changed: those can be deliberate exceptions
//      set through the CSV import's HOD column.
//
// SAFETY
//   - Dry run by default: reports what each step would change, writes
//     nothing. Pass --apply to commit.
//   - Every row the backfill touches is written to an undo-map TSV in this
//     directory BEFORE any UPDATE runs.
//
// USAGE
//  - CLI: php scripts/deploy_hod_sync_20261012.php [--apply]
//  - HTTP, for hosts with no terminal access - upload, then visit with the
//    matching ?key=:
//      https://<your-domain>/ldms/scripts/deploy_hod_sync_20261012.php?key=...
//      https://<your-domain>/ldms/scripts/deploy_hod_sync_20261012.php?key=...&apply=1
//    Delete this file from the server once you're done with it; it is a
//    one-time fix, not something that needs to stay reachable.
define('DEPLOY_FIX_TOKEN', '6e9b13c3c2dd1fcf1eabed5d1780768398d011a7442cf782');

if (PHP_SAPI !== 'cli') {
    if (!isset($_GET['key']) || !hash_equals(DEPLOY_FIX_TOKEN, (string) $_GET['key'])) {
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
echo "HOD sync deploy - " . ($apply ? "APPLYING" : "DRY RUN") . "\n";
echo "==============================================================\n\n";

// ===== STEP 1: trigger =====
echo "--- Step 1: trg_departments_hod_update (clear the new HOD's own hodid) ---\n";
$existing = $conn->query("SHOW TRIGGERS WHERE `Table` = 'departments' AND `Trigger` = 'trg_departments_hod_update'");
$isCurrent = false;
if ($existing && $row = $existing->fetch_assoc()) {
    $isCurrent = (strpos($row['Statement'], 'IF(id = NEW.hod_user_id, 0') !== false);
}
echo $isCurrent ? "Already the current version - nothing to do.\n" : "Not the current version.\n";

if (!$isCurrent) {
    if ($apply) {
        $conn->query("DROP TRIGGER IF EXISTS trg_departments_hod_update");
        $ok = $conn->query(
            "CREATE TRIGGER trg_departments_hod_update
             AFTER UPDATE ON departments
             FOR EACH ROW
             BEGIN
                 IF NOT (NEW.hod_user_id <=> OLD.hod_user_id) THEN
                     UPDATE user SET hodid = IF(id = NEW.hod_user_id, 0, COALESCE(NEW.hod_user_id, 0))
                     WHERE department_id = NEW.id;
                 END IF;
             END"
        );
        echo $ok ? "Trigger recreated.\n" : "FAILED to recreate trigger: {$conn->error}\n";
    } else {
        echo "Would recreate it (re-run with --apply).\n";
    }
}
echo "\n";

// ===== STEP 2: hodid backfill =====
echo "--- Step 2: hodid backfill (non-resigned staff) ---\n";
$res = $conn->query(
    "SELECT u.id, u.staffno, u.staffname, u.department, u.hodid AS old_hodid, d.hod_user_id
     FROM user u JOIN departments d ON d.id = u.department_id
     WHERE UPPER(COALESCE(u.status, '')) <> 'RESIGN'"
);
$hodChanges = [];
$exceptions = [];
while ($row = $res->fetch_assoc()) {
    $id = (int) $row['id'];
    $deptHod = $row['hod_user_id'] !== null ? (int) $row['hod_user_id'] : 0;
    $oldHodid = $row['old_hodid'] !== null ? (int) $row['old_hodid'] : 0;
    $isOwnHod = ($deptHod !== 0 && $deptHod === $id);
    $expected = $isOwnHod ? 0 : $deptHod;
    if ($oldHodid === $expected) continue;

    if ($isOwnHod || $oldHodid === 0) {
        $hodChanges[] = ['id' => $id, 'staffno' => $row['staffno'], 'staffname' => $row['staffname'],
                          'department' => $row['department'], 'old_hodid' => $oldHodid, 'new_hodid' => $expected,
                          'why' => $isOwnHod ? 'is the department HOD' : 'had no HOD'];
    } else {
        $exceptions[] = "{$row['staffno']} {$row['staffname']} ({$row['department']}): hodid {$oldHodid}, department HOD " . ($deptHod ?: 'none');
    }
}
echo "Rows that would change: " . count($hodChanges) . "\n";
foreach ($hodChanges as $c) {
    echo "  {$c['staffno']}\t{$c['old_hodid']} -> {$c['new_hodid']}\t({$c['why']})\t{$c['department']}\n";
}
echo "Left alone - point at someone other than their department's HOD: " . count($exceptions) . "\n";
foreach ($exceptions as $line) {
    echo "  {$line}\n";
}

if (!empty($hodChanges)) {
    if ($apply) {
        $backupPath = __DIR__ . '/backup_hod_sync_20261012_undo_map.tsv';
        $fh = fopen($backupPath, 'w');
        fwrite($fh, "id\tstaffno\tstaffname\tdepartment\told_hodid\tnew_hodid\n");
        foreach ($hodChanges as $c) {
            fwrite($fh, "{$c['id']}\t{$c['staffno']}\t{$c['staffname']}\t{$c['department']}\t{$c['old_hodid']}\t{$c['new_hodid']}\n");
        }
        fclose($fh);
        echo "Backup written: {$backupPath}\n";

        $stmt = $conn->prepare("UPDATE user SET hodid = ? WHERE id = ?");
        $conn->begin_transaction();
        try {
            foreach ($hodChanges as $c) {
                $stmt->bind_param('ii', $c['new_hodid'], $c['id']);
                if (!$stmt->execute()) throw new \Exception("id {$c['id']}: {$conn->error}");
            }
            $conn->commit();
            echo "Applied " . count($hodChanges) . " hodid updates.\n";
        } catch (\Throwable $e) {
            $conn->rollback();
            echo "Step 2 ABORTED and rolled back: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Would apply " . count($hodChanges) . " updates (re-run with --apply).\n";
    }
}
echo "\n";

echo "==============================================================\n";
echo $apply ? "Done.\n" : "Dry run complete - nothing written. Re-run with --apply to commit.\n";
echo "==============================================================\n";
?>
