<?php
// Student - View Marks & Results

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

// Get terms
$terms = [];
try { $terms = $db->query("SELECT * FROM exam_terms ORDER BY session_year DESC, id DESC")->fetchAll(); } catch (PDOException $e) {}

$selected_term = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;

// Get marks
$marks = [];
if (!empty($terms) && $selected_term == 0) $selected_term = $terms[0]['id'];

if ($selected_term > 0) {
    try {
        $stmt = $db->prepare("
            SELECT em.*, et.term_name, et.session_year
            FROM exam_marks em
            JOIN exam_terms et ON em.term_id = et.id
            WHERE em.student_id = ? AND em.term_id = ?
            ORDER BY em.subject_name
        ");
        $stmt->execute([$student_id, $selected_term]);
        $marks = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

// Aggregate
$total_obtained = 0;
$total_marks = 0;
foreach ($marks as $m) {
    $total_obtained += $m['obtained_marks'];
    $total_marks += $m['total_marks'];
}
$percentage = $total_marks > 0 ? round(($total_obtained / $total_marks) * 100, 1) : 0;

// Grade calculation
$grade = 'F';
if ($percentage >= 80) $grade = 'A+';
elseif ($percentage >= 70) $grade = 'A';
elseif ($percentage >= 60) $grade = 'B';
elseif ($percentage >= 50) $grade = 'C';
elseif ($percentage >= 40) $grade = 'D';

$passed = true;
foreach ($marks as $m) {
    if ($m['obtained_marks'] < $m['passing_marks']) $passed = false;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks & Results - Smart Choice Academy</title>
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
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state i { font-size: 48px; opacity: 0.5; }
        .grade-badge { font-size: 20px; font-weight: 800; padding: 8px 20px; border-radius: 30px; }
    </style>
</head>

<body>
    <?php include '../../backend/student_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-bar-chart me-2"></i>Marks & Results</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> | <?= htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']) ?></p>
                    </div>
                </div>
            </div>

            <div class="filter-card">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Exam Term</label>
                        <select name="term_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['id'] ?>" <?= $selected_term == $term['id'] ? 'selected' : '' ?>><?= htmlspecialchars($term['term_name'] . ' (' . $term['session_year'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 mb-2"><div class="summary-card"><div class="summary-number text-primary"><?= $percentage ?>%</div><div class="small text-muted">Overall Percentage</div></div></div>
                <div class="col-md-3 mb-2"><div class="summary-card"><div class="summary-number text-success"><?= $total_obtained ?> / <?= $total_marks ?></div><div class="small text-muted">Total Marks</div></div></div>
                <div class="col-md-3 mb-2"><div class="summary-card"><span class="grade-badge bg-primary text-white"><?= $grade ?></span><div class="small text-muted mt-1">Grade</div></div></div>
                <div class="col-md-3 mb-2"><div class="summary-card"><span class="grade-badge <?= $passed ? 'bg-success text-white' : 'bg-danger text-white' ?>"><?= $passed ? 'PASS' : 'FAIL' ?></span><div class="small text-muted mt-1">Status</div></div></div>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Subject</th><th>Obtained</th><th>Total</th><th>Passing</th><th>Percentage</th><th>Grade</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($marks)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No results published yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($marks as $m):
                                    $p = $m['total_marks'] > 0 ? round(($m['obtained_marks'] / $m['total_marks']) * 100, 1) : 0;
                                    $g = 'F';
                                    if ($p >= 80) $g = 'A+'; elseif ($p >= 70) $g = 'A'; elseif ($p >= 60) $g = 'B'; elseif ($p >= 50) $g = 'C'; elseif ($p >= 40) $g = 'D';
                                    $pass = $m['obtained_marks'] >= $m['passing_marks'];
                                ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($m['subject_name']) ?></td>
                                        <td><?= $m['obtained_marks'] ?></td>
                                        <td><?= $m['total_marks'] ?></td>
                                        <td><?= $m['passing_marks'] ?></td>
                                        <td>
                                            <div class="progress" style="height:6px;width:80px;"><div class="progress-bar <?= $pass ? 'bg-success' : 'bg-danger' ?>" style="width:<?= $p ?>%"></div></div>
                                            <?= $p ?>%
                                        </td>
                                        <td><span class="badge <?= $pass ? 'bg-success' : 'bg-danger' ?>"><?= $g ?></span></td>
                                        <td><span class="badge <?= $pass ? 'bg-success' : 'bg-danger' ?>"><?= $pass ? 'Pass' : 'Fail' ?></span></td>
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
