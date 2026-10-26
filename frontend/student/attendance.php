<?php
// Student - View Attendance Record

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Get student by reg_id
$stmt = $db->prepare("
    SELECT s.*, c.class_name, sec.section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.reg_id = ? AND s.status = 'approved'
    LIMIT 1
");
$stmt->execute([$_SESSION['reg_id']]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: ../../index.php?error=access_denied');
    exit;
}

$student_id = $student['id'];

// Month filter
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Fetch attendance records
$records = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, cr.course_name
        FROM attendance a
        LEFT JOIN courses cr ON a.course_id = cr.id
        WHERE a.student_id = ? AND MONTH(a.attendance_date) = ? AND YEAR(a.attendance_date) = ?
        ORDER BY a.attendance_date DESC
    ");
    $stmt->execute([$student_id, $selected_month, $selected_year]);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {}

// Stats
$total = count($records);
$present = 0; $absent = 0; $late = 0; $excused = 0;
foreach ($records as $r) {
    if ($r['status'] == 'present') $present++;
    elseif ($r['status'] == 'late') $late++;
    elseif ($r['status'] == 'excused') $excused++;
    else $absent++;
}
$pct = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0;

// Month dropdown
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[] = date('F', mktime(0, 0, 0, $m, 1));
}
$years = range(date('Y') - 2, date('Y'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Smart Choice Academy</title>
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
        .table-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table-card th { background: #f8f9fa; padding: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .table-card td { padding: 10px 12px; font-size: 13px; vertical-align: middle; }
        .summary-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .summary-number { font-size: 28px; font-weight: 800; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .present { background: #d4edda; color: #155724; }
        .absent { background: #f8d7da; color: #721c24; }
        .late { background: #fff3cd; color: #856404; }
        .excused { background: #d1ecf1; color: #0c5460; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state i { font-size: 48px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/student_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-calendar-check me-2"></i>Attendance Record</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> | <?= $months[$selected_month - 1] ?> <?= $selected_year ?></p>
                    </div>
                </div>
            </div>

            <div class="filter-card">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Month</label>
                        <select name="month" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($months as $idx => $name): ?>
                                <option value="<?= $idx + 1 ?>" <?= $selected_month == $idx + 1 ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Year</label>
                        <select name="year" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 mb-2"><div class="summary-card"><div class="summary-number text-primary"><?= $pct ?>%</div><div class="small text-muted">Attendance %</div></div></div>
                <div class="col-md-3 mb-2"><div class="summary-card"><div class="summary-number text-success"><?= $present ?></div><div class="small text-muted">Present</div></div></div>
                <div class="col-md-3 mb-2"><div class="summary-card"><div class="summary-number text-warning"><?= $late + $excused ?></div><div class="small text-muted">Late/Excused</div></div></div>
                <div class="col-md-3 mb-2"><div class="summary-card"><div class="summary-number text-danger"><?= $absent ?></div><div class="small text-muted">Absent</div></div></div>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Date</th><th>Subject</th><th>Status</th><th>Marked Time</th><th>Remarks</th></tr></thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No attendance records for this month.</td></tr>
                            <?php else: ?>
                                <?php foreach ($records as $r): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
                                        <td><?= htmlspecialchars($r['course_name'] ?? '—') ?></td>
                                        <td><span class="status-badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                        <td><?= !empty($r['marked_time']) ? date('h:i A', strtotime($r['marked_time'])) : '—' ?></td>
                                        <td><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
