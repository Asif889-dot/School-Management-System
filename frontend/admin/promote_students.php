<?php
// Admin - Student Promotion
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

$message = '';
$messageType = '';

// Fetch classes
$classes = [];
try { $classes = $db->query("SELECT * FROM classes ORDER BY numeric_grade")->fetchAll(); } catch (PDOException $e) {}

// Fetch academic years
$years = [];
try { $years = $db->query("SELECT * FROM academic_years ORDER BY year_name DESC")->fetchAll(); } catch (PDOException $e) {}

// Handle promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'promote') {
    $student_ids = $_POST['student_ids'] ?? [];
    $from_class_id = (int)$_POST['from_class_id'];
    $to_class_id = (int)$_POST['to_class_id'];
    $to_section_id = !empty($_POST['to_section_id']) ? (int)$_POST['to_section_id'] : null;
    $academic_year_id = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;

    if (empty($student_ids) || $from_class_id <= 0 || $to_class_id <= 0) {
        $message = 'Select students, from class and to class.';
        $messageType = 'danger';
    } else {
        try {
            $db->beginTransaction();
            $count = 0;
            foreach ($student_ids as $sid) {
                $sid = (int)$sid;
                // Insert promotion record
                $stmt = $db->prepare("INSERT INTO student_promotions (student_id, from_class_id, to_class_id, to_section_id, academic_year_id, promoted_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$sid, $from_class_id, $to_class_id, $to_section_id, $academic_year_id, $_SESSION['user_id']]);
                // Update student class
                $upd = $db->prepare("UPDATE students SET class_id = ?, section_id = ? WHERE id = ?");
                $upd->execute([$to_class_id, $to_section_id, $sid]);
                $count++;
            }
            $db->commit();
            $message = "$count student(s) promoted successfully!";
            $messageType = 'success';
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get students for selected from_class
$from_class_id = isset($_GET['from_class_id']) ? (int)$_GET['from_class_id'] : 0;
$students = [];
if ($from_class_id > 0) {
    try {
        $stmt = $db->prepare("SELECT s.id, s.reg_id, s.first_name, s.last_name, s.gender, sec.section_name
                              FROM students s
                              LEFT JOIN sections sec ON s.section_id = sec.id
                              WHERE s.class_id = ? AND s.status = 'approved' ORDER BY s.first_name");
        $stmt->execute([$from_class_id]);
        $students = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

// Fetch sections for selected to_class
$to_sections = [];
$to_class_for_sections = isset($_POST['to_class_id']) ? (int)$_POST['to_class_id'] : (isset($_GET['to_class_id']) ? (int)$_GET['to_class_id'] : 0);
if ($to_class_for_sections > 0) {
    try { $to_sections = $db->query("SELECT * FROM sections WHERE class_id = $to_class_for_sections ORDER BY section_name")->fetchAll(); } catch (PDOException $e) {}
}

// Fetch promotion history
$history = [];
try {
    $history = $db->query("SELECT sp.*, s.reg_id, s.first_name, s.last_name, fc.class_name as from_class, tc.class_name as to_class
                           FROM student_promotions sp
                           JOIN students s ON sp.student_id = s.id
                           LEFT JOIN classes fc ON sp.from_class_id = fc.id
                           LEFT JOIN classes tc ON sp.to_class_id = tc.id
                           ORDER BY sp.promoted_at DESC LIMIT 20")->fetchAll();
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Promotion | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; }
        .table td { font-size: 13px; vertical-align: middle; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .arrow-flow { color: #28a745; font-size: 1.2rem; }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header p-3"><i class="bi bi-arrow-up-circle text-success me-2"></i>Promote Students</div>
                <div class="card-body">
                    <form method="GET" class="row g-3 mb-3">
                        <div class="col-md-4"><label class="form-label fw-semibold">Current Class</label>
                            <select name="from_class_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">Select Current Class</option>
                                <?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>" <?= $from_class_id == $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if (!empty($students)): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="promote">
                        <input type="hidden" name="from_class_id" value="<?= $from_class_id ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label fw-semibold">Promote To Class</label>
                                <select name="to_class_id" id="toClassSelect" class="form-select" required>
                                    <option value="">Select Target Class</option>
                                    <?php foreach ($classes as $class): if ($class['id'] != $from_class_id): ?><option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option><?php endif; endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-semibold">New Section</label>
                                <select name="to_section_id" id="toSectionSelect" class="form-select"><option value="">Auto</option></select>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Academic Year</label>
                                <select name="academic_year_id" class="form-select"><option value="">Current</option>
                                <?php foreach ($years as $y): ?><option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['year_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-semibold mb-0">Select Students (<?= count($students) ?> found)</label>
                                <div><button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll(true)">Select All</button> <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(false)">Clear</button></div>
                            </div>
                            <div class="border rounded p-3" style="max-height:300px;overflow-y:auto;">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Select</th><th>Reg ID</th><th>Name</th><th>Section</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><input type="checkbox" class="student-check" name="student_ids[]" value="<?= $student['id'] ?>"></td>
                                            <td><?= htmlspecialchars($student['reg_id']) ?></td>
                                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= htmlspecialchars($student['section_name'] ?? '—') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="bi bi-arrow-up-circle me-1"></i>Promote Selected Students</button>
                    </form>
                    <?php else: ?>
                        <div class="empty-state"><i class="bi bi-arrow-up-circle"></i><p class="mb-0">Select a class to view students for promotion.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header p-3"><i class="bi bi-clock-history text-primary me-2"></i>Recent Promotions</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Student</th><th>Reg ID</th><th>From</th><th>To</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (empty($history)): ?><tr><td colspan="5" class="text-center py-4 text-muted">No promotions yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($h['reg_id']) ?></td>
                                    <td><?= htmlspecialchars($h['from_class'] ?? '—') ?></td>
                                    <td><span class="arrow-flow bi bi-arrow-right me-1"></span><?= htmlspecialchars($h['to_class'] ?? '—') ?></td>
                                    <td><?= date('d M Y', strtotime($h['promoted_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toClassSelect = document.getElementById('toClassSelect');
        const toSectionSelect = document.getElementById('toSectionSelect');
        toClassSelect.addEventListener('change', function() {
            const classId = this.value;
            if (classId) {
                fetch('../../backend/get_sections_by_class.php?class_id=' + classId)
                    .then(r => r.text())
                    .then(html => { toSectionSelect.innerHTML = '<option value="">Auto</option>' + html; });
            } else {
                toSectionSelect.innerHTML = '<option value="">Auto</option>';
            }
        });
        function selectAll(select) {
            document.querySelectorAll('.student-check').forEach(cb => { cb.checked = select; });
        }
    </script>
</body>

</html>
