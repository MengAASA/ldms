<?php
session_start();
include "../../dbconn.php";

header('Content-Type: application/json; charset=utf-8');

function respond($data)
{
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit();
}

if (!isset($_SESSION['fullname']) || $_SESSION['role'] != 'ADMIN') {
    respond([]);
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'load_divisions') {
    $sql = "SELECT d.id, d.name, d.shortname, d.head_user_id,
                   head.staffname AS head_name, head.staffno AS head_staffno,
                   COUNT(DISTINCT dp.id) AS dept_count,
                   COUNT(DISTINCT u.id) AS user_count
            FROM divisions d
            LEFT JOIN user head ON head.id = d.head_user_id
            LEFT JOIN departments dp ON dp.division_id = d.id
            LEFT JOIN user u ON u.division_id = d.id AND u.status != 'RESIGN'
            GROUP BY d.id
            ORDER BY d.name";
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_departments') {
    $division_id = isset($_POST['division_id']) ? (int) $_POST['division_id'] : 0;
    if (!$division_id) respond([]);

    $sql = "SELECT dp.id, dp.name, dp.shortname, dp.hod_user_id,
                   u.staffname AS hod_name, u.staffno AS hod_staffno,
                   COUNT(DISTINCT s.id) AS section_count,
                   COUNT(DISTINCT staff.id) AS user_count
            FROM departments dp
            LEFT JOIN user u ON u.id = dp.hod_user_id
            LEFT JOIN sections s ON s.department_id = dp.id
            LEFT JOIN user staff ON staff.department_id = dp.id AND staff.status != 'RESIGN'
            WHERE dp.division_id = ?
            GROUP BY dp.id
            ORDER BY dp.name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $division_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_sections') {
    $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
    if (!$department_id) respond([]);

    $sql = "SELECT s.id, s.name, s.shortname,
                   COUNT(u.id) AS user_count
            FROM sections s
            LEFT JOIN user u ON u.section_id = s.id AND u.status != 'RESIGN'
            WHERE s.department_id = ?
            GROUP BY s.id
            ORDER BY s.name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_managers') {
    $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
    if (!$department_id) respond([]);

    $sql = "SELECT id, staffno, staffname
            FROM user
            WHERE department_id = ? AND designation = 'MANAGER (AM/HOS & ABOVE)' AND status != 'RESIGN'
            ORDER BY staffname";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_division_head_candidates') {
    $division_id = isset($_POST['division_id']) ? (int) $_POST['division_id'] : 0;
    if (!$division_id) respond([]);

    $sql = "SELECT id, staffno, staffname
            FROM user
            WHERE division_id = ? AND designation = 'MANAGER (AM/HOS & ABOVE)' AND status != 'RESIGN'
            ORDER BY staffname";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $division_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_staff_in_dept') {
    $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
    if (!$department_id) respond([]);

    $sql = "SELECT u.id, u.staffno, u.staffname, u.designation, u.grade,
                   COALESCE(s.name, u.section) AS section_name
            FROM user u
            LEFT JOIN sections s ON s.id = u.section_id
            WHERE u.department_id = ? AND u.status != 'RESIGN'
            ORDER BY u.staffname";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_all_departments') {
    $sql = "SELECT dp.id, dp.name, dv.name AS division_name
            FROM departments dp
            JOIN divisions dv ON dv.id = dp.division_id
            ORDER BY dv.name, dp.name";
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_hod_health') {
    // user.hodid is a copy of departments.hod_user_id kept in step by
    // trg_departments_hod_update and the staff forms. This lists every place
    // the two disagree, so drift is seen here rather than in a report.
    $notResigned = "UPPER(COALESCE(%s.status, '')) <> 'RESIGN'";

    $where = sprintf($notResigned, 'u') . "
              AND COALESCE(u.hodid, 0) <> IF(d.hod_user_id IS NULL OR d.hod_user_id = u.id, 0, d.hod_user_id)";
    $from = "FROM user u JOIN departments d ON d.id = u.department_id";

    $staffCount = (int) $conn->query("SELECT COUNT(*) AS c $from WHERE $where")->fetch_assoc()['c'];
    $staff = [];
    if ($staffCount > 0) {
        $result = $conn->query(
            "SELECT u.staffno, u.staffname, d.name AS department, (d.hod_user_id = u.id) AS is_dept_hod,
                    cur.staffno AS current_staffno, cur.staffname AS current_name,
                    ex.staffno AS expected_staffno, ex.staffname AS expected_name
             $from
             LEFT JOIN user cur ON cur.id = u.hodid
             LEFT JOIN user ex ON ex.id = d.hod_user_id
             WHERE $where
             ORDER BY d.name, u.staffname LIMIT 50"
        );
        while ($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
    }

    $departments = [];
    $result = $conn->query(
        "SELECT d.name AS department, h.staffno, h.staffname, COALESCE(h.usertype, '') AS hod_usertype,
                (" . sprintf("UPPER(COALESCE(%s.status, '')) = 'RESIGN'", 'h') . ") AS hod_resigned,
                (h.id IS NOT NULL AND NOT (h.department_id <=> d.id)) AS hod_elsewhere,
                (SELECT COUNT(*) FROM user s WHERE s.department_id = d.id AND " . sprintf($notResigned, 's') . ") AS active_staff
         FROM departments d
         LEFT JOIN user h ON h.id = d.hod_user_id
         ORDER BY d.name"
    );
    while ($row = $result->fetch_assoc()) {
        $hod = $row['staffname'] . ' (' . $row['staffno'] . ')';
        if ($row['staffno'] === null) {
            if ((int) $row['active_staff'] > 0) {
                $departments[] = ['department' => $row['department'], 'issue' => 'No HOD assigned, ' . $row['active_staff'] . ' active staff.'];
            }
        } elseif ($row['hod_resigned']) {
            $departments[] = ['department' => $row['department'], 'issue' => "Assigned HOD $hod has resigned."];
        } elseif ($row['hod_elsewhere']) {
            $departments[] = ['department' => $row['department'], 'issue' => "Assigned HOD $hod is no longer in this department."];
        } elseif ($row['hod_usertype'] !== 'HOD') {
            // index.php routes on usertype, so this HOD lands on the staff dashboard.
            $type = $row['hod_usertype'] === '' ? 'blank' : "'" . $row['hod_usertype'] . "'";
            $departments[] = ['department' => $row['department'], 'issue' => "Assigned HOD $hod has no HOD portal access (user type is $type)."];
        }
    }

    respond(['staff_count' => $staffCount, 'staff' => $staff, 'departments' => $departments]);
}

if ($action == 'load_sm_whitelist') {
    // user.staffno and skill_matrix_whitelist.staffno were created with different
    // default collations (general_ci vs 0900_ai_ci) - joining them without an
    // explicit COLLATE throws "Illegal mix of collations".
    $sql = "SELECT w.staffno, u.id AS user_id, u.staffname, u.department, u.designation
            FROM skill_matrix_whitelist w
            LEFT JOIN user u ON u.staffno COLLATE utf8mb4_0900_ai_ci = w.staffno
            ORDER BY u.staffname IS NULL, u.staffname";
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'load_sm_departments') {
    // Which departments can use the skill matrix (skill_matrix_department.php).
    $col = $conn->query("SHOW COLUMNS FROM departments LIKE 'skill_matrix_enabled'");
    if (!$col || $col->num_rows == 0) {
        respond(['ready' => false, 'departments' => []]);
    }
    $sql = "SELECT d.id, d.name, dv.name AS division, d.skill_matrix_enabled,
                   COUNT(u.id) AS user_count
            FROM departments d
            LEFT JOIN divisions dv ON dv.id = d.division_id
            LEFT JOIN user u ON u.department_id = d.id AND UPPER(COALESCE(u.status, '')) <> 'RESIGN'
            GROUP BY d.id, d.name, dv.name, d.skill_matrix_enabled
            ORDER BY d.name";
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['skill_matrix_enabled'] = (int) $row['skill_matrix_enabled'];
        $row['user_count'] = (int) $row['user_count'];
        $rows[] = $row;
    }
    respond(['ready' => true, 'departments' => $rows]);
}

if ($action == 'search_staff') {
    $q = isset($_POST['q']) ? trim($_POST['q']) : '';
    if ($q === '') respond([]);
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT id, staffno, staffname, department, designation
                             FROM user
                             WHERE (staffno LIKE ? OR staffname LIKE ?) AND status != 'RESIGN'
                             ORDER BY staffname LIMIT 20");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($action == 'check_sm_data') {
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    if (!$user_id) respond(['count' => 0]);
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM skill_matrix_evaluations WHERE staffid = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    respond(['count' => (int) $row['c']]);
}

respond([]);
?>
