<?php

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Get teacher info
$user_id = $_SESSION['user_id'] ?? null;
$teacher_name = '';
if ($user_id) {
    $stmt = $db->prepare("SELECT u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    $teacher_name = $stmt->fetchColumn() ?: 'Teacher';
}

// Fetch awards
$awards = [];
try {
    $awards = $db->query("SELECT a.*, ay.year_name,
                          (SELECT COUNT(*) FROM award_students ws WHERE ws.award_id = a.id) as student_count
                          FROM awards a
                          LEFT JOIN academic_years ay ON a.academic_year_id = ay.id
                          ORDER BY a.created_at DESC")->fetchAll();
} catch (PDOException $e) {}

// Get selected award detail
$selected_award = null;
$selected_students = [];
$selected_id = isset($_GET['award_id']) ? (int)$_GET['award_id'] : 0;
if ($selected_id > 0) {
    foreach ($awards as $a) {
        if ($a['id'] == $selected_id) { $selected_award = $a; break; }
    }
    try {
        $stmt = $db->prepare("
            SELECT s.reg_id, s.first_name, s.last_name, c.class_name, sec.section_name, ws.position, ws.remarks
            FROM award_students ws
            JOIN students s ON ws.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE ws.award_id = ?
            ORDER BY ws.position, s.first_name
        ");
        $stmt->execute([$selected_id]);
        $selected_students = $stmt->fetchAll();
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Lists - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; }
        .table td { font-size: 13px; vertical-align: middle; }
        .award-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 15px; cursor: pointer; transition: transform 0.2s; }
        .award-card:hover { transform: translateY(-3px); }
        .award-header { background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); color: white; padding: 15px 20px; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .position-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        @media print {
            .no-print { display: none !important; }
            .main-content { margin-left: 0; padding: 0; }
        }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section no-print">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-trophy me-2"></i>Award Lists</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($teacher_name) ?> | View and download award lists</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4 no-print">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-trophy text-warning me-2"></i>Available Awards</div>
                        <div class="card-body p-0">
                            <?php if (empty($awards)): ?>
                                <div class="empty-state"><i class="bi bi-trophy"></i><p class="mb-0">No awards available.</p></div>
                            <?php else: ?>
                                <?php foreach ($awards as $award): ?>
                                    <a href="?award_id=<?= $award['id'] ?>" class="text-decoration-none">
                                        <div class="award-card mx-2 mt-2">
                                            <div class="award-header"><strong><?= htmlspecialchars($award['award_name']) ?></strong></div>
                                            <div class="p-3 bg-white">
                                                <div class="small text-muted"><i class="bi bi-people me-1"></i><?= $award['student_count'] ?> awardees</div>
                                                <div class="small text-muted"><i class="bi bi-calendar me-1"></i><?= htmlspecialchars($award['year_name'] ?? 'N/A') ?></div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span><i class="bi bi-list-check text-warning me-2"></i><?= $selected_award ? htmlspecialchars($selected_award['award_name']) . ' - Awardees' : 'Select an Award' ?></span>
                            <?php if ($selected_award): ?>
                                <button class="btn btn-sm btn-primary no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save PDF</button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($selected_award && !empty($selected_students)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead><tr><th>#</th><th>Reg ID</th><th>Student Name</th><th>Class</th><th>Section</th><th>Position</th><th>Remarks</th></tr></thead>
                                        <tbody>
                                            <?php $i = 1; foreach ($selected_students as $st): ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>
                                                    <td><?= htmlspecialchars($st['reg_id']) ?></td>
                                                    <td><strong><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong></td>
                                                    <td><?= htmlspecialchars($st['class_name'] ?? '—') ?></td>
                                                    <td><?= htmlspecialchars($st['section_name'] ?? '—') ?></td>
                                                    <td><?= !empty($st['position']) ? '<span class="position-badge bg-warning text-dark">' . htmlspecialchars($st['position']) . '</span>' : '—' ?></td>
                                                    <td><?= htmlspecialchars($st['remarks'] ?? '—') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($selected_award): ?>
                                <div class="empty-state"><i class="bi bi-people"></i><p class="mb-0">No students assigned to this award yet.</p></div>
                            <?php else: ?>
                                <div class="empty-state"><i class="bi bi-trophy"></i><p class="mb-0">Select an award from the left to view its recipients.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
