<?php
// Student - Academic Record

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
    SELECT s.*, c.class_name, sec.section_name, ay.year_name as academic_year_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
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

// Get courses for this class
$courses = [];
try {
    $stmt = $db->prepare("SELECT * FROM courses WHERE class_id = ? ORDER BY course_name");
    $stmt->execute([$student['class_id']]);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {}

// Get all exam results grouped by term
$terms_results = [];
try {
    $stmt = $db->prepare("
        SELECT em.*, et.term_name, et.session_year
        FROM exam_marks em
        JOIN exam_terms et ON em.term_id = et.id
        WHERE em.student_id = ?
        ORDER BY et.session_year DESC, et.id, em.subject_name
    ");
    $stmt->execute([$student_id]);
    $all_marks = $stmt->fetchAll();
    foreach ($all_marks as $m) {
        $key = $m['term_name'] . ' (' . $m['session_year'] . ')';
        $terms_results[$key][] = $m;
    }
} catch (PDOException $e) {}

$photo_path = !empty($student['photo']) && file_exists('../../' . $student['photo']) ? '../../' . $student['photo'] : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Record - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .info-label { width: 160px; color: #6c757d; font-weight: 600; }
        .info-value { flex: 1; color: #333; }
        .table-card th { background: #f8f9fa; padding: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .table-card td { padding: 10px 12px; font-size: 13px; }
        .course-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; background: #e7f1ff; color: #084298; font-size: 12px; font-weight: 600; margin: 3px; }
        .empty-state { text-align: center; padding: 30px; color: #6c757d; }
        .avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid white; }
    </style>
</head>

<body>
    <?php include '../../backend/student_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-book me-2"></i>Academic Record</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> | <?= htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']) ?></p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-5">
                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-person-badge text-primary me-2"></i>Student Information</div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php if ($photo_path): ?>
                                    <img src="<?= $photo_path ?>" class="avatar" alt="Student">
                                <?php else: ?>
                                    <div class="avatar d-inline-flex align-items-center justify-content-center bg-light"><i class="bi bi-person fs-2 text-muted"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="info-row"><div class="info-label">Name</div><div class="info-value"><strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong></div></div>
                            <div class="info-row"><div class="info-label">Reg ID</div><div class="info-value"><?= htmlspecialchars($student['reg_id']) ?></div></div>
                            <div class="info-row"><div class="info-label">Class</div><div class="info-value"><?= htmlspecialchars($student['class_name']) ?></div></div>
                            <div class="info-row"><div class="info-label">Section</div><div class="info-value"><?= htmlspecialchars($student['section_name'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Academic Year</div><div class="info-value"><?= htmlspecialchars($student['academic_year_name'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Admission Date</div><div class="info-value"><?= date('d M Y', strtotime($student['admission_date'])) ?></div></div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-journal-bookmark text-success me-2"></i>My Subjects</div>
                        <div class="card-body">
                            <?php if (empty($courses)): ?>
                                <p class="text-muted mb-0">No subjects assigned.</p>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <span class="course-badge"><i class="bi bi-book me-1"></i><?= htmlspecialchars($course['course_name']) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <?php if (empty($terms_results)): ?>
                        <div class="card"><div class="card-body empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">No academic results published yet.</p></div></div>
                    <?php else: ?>
                        <?php foreach ($terms_results as $term_name => $results):
                            $t_obt = 0; $t_tot = 0;
                            foreach ($results as $r) { $t_obt += $r['obtained_marks']; $t_tot += $r['total_marks']; }
                            $t_pct = $t_tot > 0 ? round(($t_obt / $t_tot) * 100, 1) : 0;
                        ?>
                            <div class="card mb-4">
                                <div class="card-header p-3 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-award text-warning me-2"></i><?= htmlspecialchars($term_name) ?></span>
                                    <span class="badge bg-primary"><?= $t_pct ?>%</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 table-card">
                                        <thead><tr><th>Subject</th><th>Obtained</th><th>Total</th><th>%</th><th>Grade</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($results as $r):
                                                $p = $r['total_marks'] > 0 ? round(($r['obtained_marks'] / $r['total_marks']) * 100, 1) : 0;
                                                $g = 'F';
                                                if ($p >= 80) $g='A+'; elseif ($p >= 70) $g='A'; elseif ($p >= 60) $g='B'; elseif ($p >= 50) $g='C'; elseif ($p >= 40) $g='D';
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['subject_name']) ?></td>
                                                    <td><?= $r['obtained_marks'] ?></td>
                                                    <td><?= $r['total_marks'] ?></td>
                                                    <td><?= $p ?>%</td>
                                                    <td><span class="badge bg-success"><?= $g ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
