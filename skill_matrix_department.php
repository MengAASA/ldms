<?php
// Which departments may use the skill matrix. Admin switches departments on
// or off on Organization > Skill Matrix Config, stored in
// departments.skill_matrix_enabled (added by
// scripts/deploy_skill_matrix_departments_20261020.php).
//
// This is an extra gate on top of each page's own access rule (manager /
// MAIN clerk, whitelist, HOD): a user whose department is switched off loses
// the SKILL MATRIX menu and pages, whitelisted or not. Nothing is deleted -
// admin still sees every matrix, and switching the department back on
// restores access. Admin is never gated.
//
// Needs the session to be started. Uses $conn, connecting through
// dbconn.php itself if the page hasn't (many pages only load data by AJAX).
if (!function_exists('skillMatrixDepartmentEnabled')) {
    function skillMatrixDepartmentEnabled()
    {
        global $conn;
        static $enabled = null;

        if ($enabled !== null) {
            return $enabled;
        }
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') {
            return $enabled = true;
        }
        if (!isset($_SESSION['id'])) {
            return $enabled = false;
        }
        if (!($conn instanceof mysqli)) {
            include __DIR__ . '/dbconn.php';
        }

        // Every department starts switched on, so if the column isn't there
        // yet (deploy script not run) or the user has no department, nothing
        // is blocked.
        $enabled = true;
        try {
            $stmt = $conn->prepare("SELECT d.skill_matrix_enabled
                                    FROM user u
                                    JOIN departments d ON d.id = u.department_id
                                    WHERE u.id = ?");
            if ($stmt) {
                $userId = (int) $_SESSION['id'];
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $enabled = (int) $row['skill_matrix_enabled'] === 1;
                }
            }
        } catch (Throwable $e) {
            // Column missing - see above.
        }

        return $enabled;
    }
}

// True once the deploy script has added departments.skill_matrix_enabled.
if (!function_exists('skillMatrixDepartmentsReady')) {
    function skillMatrixDepartmentsReady()
    {
        global $conn;
        static $ready = null;

        if ($ready === null) {
            if (!($conn instanceof mysqli)) {
                include __DIR__ . '/dbconn.php';
            }
            try {
                $col = $conn->query("SHOW COLUMNS FROM departments LIKE 'skill_matrix_enabled'");
                $ready = $col && $col->num_rows > 0;
            } catch (Throwable $e) {
                $ready = false;
            }
        }
        return $ready;
    }
}

// Condition for the admin skill matrix list, its dashboard, export and matrix
// chart, so they only cover departments that are switched on. Append it to a
// WHERE clause in a query that joins departments AS dp. Empty when
// $includeOff (admin ticked "Include switched-off departments") or before
// the deploy script has run.
if (!function_exists('skillMatrixEnabledDepartmentSql')) {
    function skillMatrixEnabledDepartmentSql($includeOff = false)
    {
        return (!$includeOff && skillMatrixDepartmentsReady())
            ? " AND COALESCE(dp.skill_matrix_enabled, 1) = 1 "
            : "";
    }
}

// Admin's "Include switched-off departments" tick, from the request.
// Only admin can see switched-off departments.
if (!function_exists('skillMatrixIncludeOffDepartments')) {
    function skillMatrixIncludeOffDepartments()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN'
            && isset($_REQUEST['include_off']) && $_REQUEST['include_off'] === '1';
    }
}
