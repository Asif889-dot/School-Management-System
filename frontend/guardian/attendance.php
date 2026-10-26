<?php
// Guardian - View Child Attendance

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'guardian') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;
$guardian_user_id = $_SESSION['user_id'] ?? 0;
$guardian_name = $_SESSION['full_name'] ?? 'Parent';

// Get children
$children = [];
$stmt = $db->prepare("
    SELECT s.*, c.class_name, sec.section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.status = 'approved'
      AND (s.guardian_user_id = ? OR s.father_name = ? OR s.mother_name = ?)
    ORDER BY s.id DESC
");
$stmt->execute([$guardian_user_id, $guardian_name, $guardian_name]);
$children = $stmt->fetchAll();

if (empty($children)) {
    $stmt = $db->prepare("
        SELECT s.*, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.status = 'approved'
          AND (s.father_phone = ? OR s.mother_phone = ?)
        ORDER BY s.id DESC
    ");
    $stmt->execute([$_SESSION['reg_id'] ?? '', $_SESSION['reg_id'] ?? '']);
    $children = $stmt->fetchAll();
}

$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (count($children) ? $children[0]['id'] : 0);
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Validate selected student belongs to this guardian
$valid_child = false;
foreach ($children as $child) {
    if ($child['id'] == $selected_student) {
        $valid_child = true;
        break;
    }
}
if (!$valid_child) {
    $selected_student = count($children) ? $children[0]['id'] : 0;
}

// Fetch attendance for selected student + month
$attendance_records = [];
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];
if ($selected_student > 0) {
    try {
        $stmt = $db->prepare("
            SELECT status, attendance_date, course_id, period_id, remarks, marked_time
            FROM attendance
            WHERE student_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
            ORDER BY attendance_date DESC
        ");
        $stmt->execute([$selected_student, $month]);
        $attendance_records = $stmt->fetchAll();

        foreach ($attendance_records as $rec) {
            $summary['total']++;
            if (isset($summary[$rec['status']])) $summary[$rec['status']]++;
        }
    } catch (PDOException $e) {
        $attendance_records = [];
    }
}

// Get selected child info
$selected_child = null;
foreach ($children as $child) {
    if ($child['id'] == $selected_student) $selected_child = $child;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Attendance - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .filter-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-card { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-number { font-size: 24px; font-weight: 700; }
        .table-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table-card th { background: #f8f9fa; padding: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .table-card td { padding: 10px 12px; font-size: 13px; vertical-align: middle; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .present { background: #d4edda; color: #155724; }
        .absent { background: #f8d7da; color: #721c24; }
        .late { background: #fff3cd; color: #856404; }
        .excused { background: #e2e3e5; color: #41464b; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state i { font-size: 48px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/guardian_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-calendar-check me-2"></i>Child Attendance</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($guardian_name) ?> | View attendance records</p>
                    </div>
                </div>
            </div>

            <?php if (empty($children)): ?>
                <div class="empty-state">
                    <i class="bi bi-person-x"></i>
                    <h5>No children linked to your account</h5>
                    <p class="mb-0">Please contact the school administrator.</p>
                </div>
            <?php else: ?>
                <!-- Filter -->
                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Select Child</label>
                            <select name="student_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($children as $child): ?>
                                    <option value="<?= $child['id'] ?>" <?= $selected_student == $child['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (' . $child['reg_id'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Month</label>
                            <input type="month" name="month" class="form-control" value="<?= $month ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> View</button>
                        </div>
                    </form>
                </div>

                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-3 col-6 mb-2"><div class="stat-card"><div class="stat-number text-primary"><?= $summary['total'] ?></div><div class="small text-muted">Total Records</div></div></div>
                    <div class="col-md-3 col-6 mb-2"><div class="stat-card"><div class="stat-number text-success"><?= $summary['present'] ?></div><div class="small text-muted">Present</div></div></div>
                    <div class="col-md-3 col-6 mb-2"><div class="stat-card"><div class="stat-number text-warning"><?= $summary['late'] ?></div><div class="small text-muted">Late</div></div></div>
                    <div class="col-md-3 col-6 mb-2"><div class="stat-card"><div class="stat-number text-danger"><?= $summary['absent'] ?></div><div class="small text-muted">Absent</div></div></div>
                </div>

                <!-- Records -->
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Date</th><th>Status</th><th>Marked Time</th><th>Remarks</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendance_records)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No attendance records for this month.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($attendance_records as $rec): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($rec['attendance_date'])) ?></td>
                                            <td>
                                                <span class="status-badge <?= $rec['status'] ?>">
                                                    <i class="bi bi-<?= $rec['status'] == 'present' ? 'check-circle' : ($rec['status'] == 'late' ? 'clock' : ($rec['status'] == 'excused' ? 'shield-check' : 'x-circle')) ?> me-1"></i>
                                                    <?= ucfirst($rec['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= !empty($rec['marked_time']) ? date('h:i A', strtotime($rec['marked_time'])) : '—' ?></td>
                                            <td><?= htmlspecialchars($rec['remarks'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
