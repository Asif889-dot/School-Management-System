<?php
// Guardian - View Child Marks & Results

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
$term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;

// Validate
$valid_child = false;
foreach ($children as $child) {
    if ($child['id'] == $selected_student) $valid_child = true;
}
if (!$valid_child) $selected_student = count($children) ? $children[0]['id'] : 0;

// Get exam terms
$terms = [];
try { $terms = $db->query("SELECT * FROM exam_terms ORDER BY session_year DESC, id DESC")->fetchAll(); } catch (PDOException $e) { $terms = []; }

// Fetch marks
$marks = [];
$avg_pct = 0;
if ($selected_student > 0) {
    try {
        if ($term_id > 0) {
            $stmt = $db->prepare("
                SELECT em.*, et.term_name, et.session_year
                FROM exam_marks em
                JOIN exam_terms et ON em.term_id = et.id
                WHERE em.student_id = ? AND em.term_id = ?
                ORDER BY em.subject_name
            ");
            $stmt->execute([$selected_student, $term_id]);
        } else {
            $stmt = $db->prepare("
                SELECT em.*, et.term_name, et.session_year
                FROM exam_marks em
                JOIN exam_terms et ON em.term_id = et.id
                WHERE em.student_id = ?
                ORDER BY et.id DESC, em.subject_name
            ");
            $stmt->execute([$selected_student]);
        }
        $marks = $stmt->fetchAll();

        if (!empty($marks)) {
            $total_pct = 0;
            foreach ($marks as $m) {
                if ($m['total_marks'] > 0) $total_pct += ($m['obtained_marks'] / $m['total_marks']) * 100;
            }
            $avg_pct = round($total_pct / count($marks), 1);
        }
    } catch (PDOException $e) {
        $marks = [];
    }
}

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
    <title>Child Marks & Results - Smart Choice Academy</title>
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
        .progress { height: 6px; }
        .pass-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .pass { background: #d4edda; color: #155724; }
        .fail { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state i { font-size: 48px; opacity: 0.5; }
        .avg-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 20px; text-align: center; }
        .avg-number { font-size: 36px; font-weight: 800; }
    </style>
</head>

<body>
    <?php include '../../backend/guardian_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-bar-chart me-2"></i>Marks & Results</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($guardian_name) ?> | View child exam results</p>
                    </div>
                </div>
            </div>

            <?php if (empty($children)): ?>
                <div class="empty-state"><i class="bi bi-person-x"></i><h5>No children linked to your account</h5><p class="mb-0">Please contact the school administrator.</p></div>
            <?php else: ?>
                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Select Child</label>
                            <select name="student_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($children as $child): ?>
                                    <option value="<?= $child['id'] ?>" <?= $selected_student == $child['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Exam Term</label>
                            <select name="term_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Terms</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['id'] ?>" <?= $term_id == $term['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($term['term_name'] . ' (' . $term['session_year'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> View</button>
                        </div>
                    </form>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="avg-card">
                            <div class="avg-number"><?= $avg_pct ?>%</div>
                            <div class="small opacity-75">Average Performance</div>
                        </div>
                    </div>
                </div>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Term</th><th>Subject</th><th>Obtained</th><th>Total</th><th>Percentage</th><th>Grade</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($marks)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No marks recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($marks as $m):
                                        $pct = $m['total_marks'] > 0 ? round(($m['obtained_marks'] / $m['total_marks']) * 100, 1) : 0;
                                        $passed = $pct >= ($m['passing_marks'] ?: 40);
                                        if ($pct >= 80) $grade = 'A+';
                                        elseif ($pct >= 70) $grade = 'A';
                                        elseif ($pct >= 60) $grade = 'B';
                                        elseif ($pct >= 50) $grade = 'C';
                                        elseif ($pct >= 40) $grade = 'D';
                                        else $grade = 'F';
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($m['term_name']) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($m['subject_name']) ?></td>
                                            <td><?= $m['obtained_marks'] ?></td>
                                            <td><?= $m['total_marks'] ?></td>
                                            <td>
                                                <div class="progress" style="width: 100px;"><div class="progress-bar <?= $passed ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $pct ?>%"></div></div>
                                                <small><?= $pct ?>%</small>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= $grade ?></span></td>
                                            <td><span class="pass-badge <?= $passed ? 'pass' : 'fail' ?>"><?= $passed ? 'Pass' : 'Fail' ?></span></td>
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
