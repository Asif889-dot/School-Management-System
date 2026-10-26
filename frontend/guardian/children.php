<?php

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

// Find children linked to this guardian
$children = [];
$stmt = $db->prepare("
    SELECT s.*, c.class_name, c.numeric_grade, sec.section_name, ay.year_name as academic_year
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.status = 'approved'
      AND (s.guardian_user_id = ? OR s.father_name = ? OR s.mother_name = ?)
    ORDER BY s.id DESC
");
$stmt->execute([$guardian_user_id, $guardian_name, $guardian_name]);
$children = $stmt->fetchAll();

if (empty($children)) {
    $stmt = $db->prepare("
        SELECT s.*, c.class_name, c.numeric_grade, sec.section_name, ay.year_name as academic_year
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
        WHERE s.status = 'approved'
          AND (s.father_phone = ? OR s.mother_phone = ?)
        ORDER BY s.id DESC
    ");
    $stmt->execute([$_SESSION['reg_id'] ?? '', $_SESSION['reg_id'] ?? '']);
    $children = $stmt->fetchAll();
}

// For each child, compute quick stats
foreach ($children as &$child) {
    $child['attendance_pct'] = 0;
    $child['avg_marks'] = 0;
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' OR status = 'late' THEN 1 ELSE 0 END) as present
            FROM attendance WHERE student_id = ?
        ");
        $stmt->execute([$child['id']]);
        $att = $stmt->fetch();
        if (!empty($att) && $att['total'] > 0) {
            $child['attendance_pct'] = round(($att['present'] / $att['total']) * 100);
        }
    } catch (PDOException $e) {
        $child['attendance_pct'] = 0;
    }

    try {
        $stmt = $db->prepare("
            SELECT AVG(obtained_marks / total_marks * 100) as avg_pct 
            FROM exam_marks WHERE student_id = ?
        ");
        $stmt->execute([$child['id']]);
        $child['avg_marks'] = round((float)$stmt->fetchColumn(), 1);
    } catch (PDOException $e) {
        $child['avg_marks'] = 0;
    }
}
unset($child);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .child-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s; margin-bottom: 20px; height: 100%; }
        .child-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .child-header { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; padding: 20px; text-align: center; }
        .child-avatar { width: 80px; height: 80px; border-radius: 50%; border: 3px solid white; object-fit: cover; background: white; margin-bottom: 10px; }
        .child-body { padding: 20px; }
        .metric { text-align: center; padding: 10px; border-right: 1px solid #e9ecef; }
        .metric:last-child { border-right: none; }
        .metric-value { font-size: 20px; font-weight: 700; color: #2c3e50; }
        .metric-label { font-size: 10px; color: #6c757d; text-transform: uppercase; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .info-label { width: 130px; color: #6c757d; font-weight: 600; }
        .info-value { flex: 1; color: #333; }
        .empty-state { text-align: center; padding: 60px; color: #6c757d; }
        .empty-state i { font-size: 64px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/guardian_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-people-fill me-2"></i>My Children</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($guardian_name) ?> | Total: <?= count($children) ?> child(ren)</p>
                    </div>
                </div>
            </div>

            <?php if (empty($children)): ?>
                <div class="empty-state">
                    <i class="bi bi-person-x"></i>
                    <h4>No Children Found</h4>
                    <p class="mb-2">No enrolled children are linked to your account.</p>
                    <small class="text-muted">Please contact the school administrator to link your children.</small>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($children as $child): 
                        $photo = !empty($child['photo']) && file_exists('../../' . $child['photo']) ? '../../' . $child['photo'] : null;
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="child-card">
                                <div class="child-header">
                                    <?php if ($photo): ?>
                                        <img src="<?= $photo ?>" alt="Photo" class="child-avatar">
                                    <?php else: ?>
                                        <div class="child-avatar d-flex align-items-center justify-content-center" style="font-size: 40px;"><i class="bi bi-person-fill"></i></div>
                                    <?php endif; ?>
                                    <div class="fw-bold" style="font-size: 18px;"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
                                    <div class="small opacity-75"><?= htmlspecialchars($child['reg_id']) ?></div>
                                </div>
                                <div class="child-body">
                                    <div class="row no-gutters border-bottom mb-3">
                                        <div class="col-4 metric">
                                            <div class="metric-value"><?= $child['class_name'] ?? '—' ?></div>
                                            <div class="metric-label">Class</div>
                                        </div>
                                        <div class="col-4 metric">
                                            <div class="metric-value"><?= $child['section_name'] ?? '—' ?></div>
                                            <div class="metric-label">Section</div>
                                        </div>
                                        <div class="col-4 metric">
                                            <div class="metric-value"><?= $child['attendance_pct'] ?>%</div>
                                            <div class="metric-label">Attendance</div>
                                        </div>
                                    </div>
                                    <div class="info-row"><div class="info-label">Academic Year</div><div class="info-value"><?= htmlspecialchars($child['academic_year'] ?? '—') ?></div></div>
                                    <div class="info-row"><div class="info-label">Average Marks</div><div class="info-value"><?= $child['avg_marks'] ?>%</div></div>
                                    <div class="d-grid gap-2 mt-3">
                                        <a href="attendance.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-check me-1"></i> Attendance</a>
                                        <a href="marks.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-bar-chart me-1"></i> Marks & Results</a>
                                        <a href="fees.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-cash-coin me-1"></i> Fee Records</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
