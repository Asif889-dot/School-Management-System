<?php
// Student - View Exam Datesheet

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

$term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;

// Get terms
$terms = [];
try { $terms = $db->query("SELECT * FROM exam_terms ORDER BY session_year DESC, id DESC")->fetchAll(); } catch (PDOException $e) {}

// Get datesheets
$datesheets = [];
try {
    if ($term_id > 0) {
        $stmt = $db->prepare("
            SELECT ed.*, et.term_name, et.session_year
            FROM exam_datesheets ed
            JOIN exam_terms et ON ed.term_id = et.id
            WHERE ed.class_id = ? AND ed.term_id = ?
            ORDER BY ed.exam_date ASC, ed.start_time ASC
        ");
        $stmt->execute([$student['class_id'], $term_id]);
    } else {
        $stmt = $db->prepare("
            SELECT ed.*, et.term_name, et.session_year
            FROM exam_datesheets ed
            JOIN exam_terms et ON ed.term_id = et.id
            WHERE ed.class_id = ?
            ORDER BY ed.exam_date ASC, ed.start_time ASC
        ");
        $stmt->execute([$student['class_id']]);
    }
    $datesheets = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Datesheet - Smart Choice Academy</title>
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
                        <h4 class="fw-bold mb-1"><i class="bi bi-calendar-event me-2"></i>Exam Datesheet</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> | Class <?= htmlspecialchars($student['class_name']) ?></p>
                    </div>
                </div>
            </div>

            <div class="filter-card">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Exam Term</label>
                        <select name="term_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Terms</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['id'] ?>" <?= $term_id == $term['id'] ? 'selected' : '' ?>><?= htmlspecialchars($term['term_name'] . ' (' . $term['session_year'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Term</th><th>Date</th><th>Subject</th><th>Time</th><th>Room</th><th>Total Marks</th><th>Passing</th></tr></thead>
                        <tbody>
                            <?php if (empty($datesheets)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No datesheet published for your class yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($datesheets as $ds): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($ds['term_name']) ?></span></td>
                                        <td><strong><?= date('d M Y', strtotime($ds['exam_date'])) ?></strong></td>
                                        <td><?= htmlspecialchars($ds['subject_name']) ?></td>
                                        <td><?= date('h:i A', strtotime($ds['start_time'])) ?> - <?= date('h:i A', strtotime($ds['end_time'])) ?></td>
                                        <td><?= htmlspecialchars($ds['room_number'] ?? '—') ?></td>
                                        <td><?= $ds['total_marks'] ?></td>
                                        <td><?= $ds['passing_marks'] ?></td>
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
