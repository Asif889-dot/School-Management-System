<?php
// Admin - Award Lists Management
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

// Handle award deletion
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    try {
        $db->prepare("DELETE FROM awards WHERE id = ?")->execute([(int)$_GET['delete_id']]);
        header('Location: awards.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: awards.php?error=1');
        exit;
    }
}

// Handle award_student removal
if (isset($_GET['remove_student']) && is_numeric($_GET['remove_student']) && isset($_GET['award_id']) && is_numeric($_GET['award_id'])) {
    try {
        $db->prepare("DELETE FROM award_students WHERE id = ? AND award_id = ?")->execute([(int)$_GET['remove_student'], (int)$_GET['award_id']]);
        header('Location: awards.php?removed=1');
        exit;
    } catch (PDOException $e) {
        header('Location: awards.php?error=1');
        exit;
    }
}

// Handle POST (create award)
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_award') {
        $award_name = trim($_POST['award_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $academic_year_id = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;

        if (empty($award_name)) {
            $message = 'Award name is required.';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO awards (award_name, description, academic_year_id) VALUES (?,?,?)");
                $stmt->execute([$award_name, $description, $academic_year_id]);
                $message = 'Award created successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'add_student') {
        $award_id = (int)$_POST['award_id'];
        $student_ids = $_POST['student_ids'] ?? [];
        $position = trim($_POST['position'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($award_id <= 0 || empty($student_ids)) {
            $message = 'Select an award and at least one student.';
            $messageType = 'danger';
        } else {
            try {
                $count = 0;
                $stmt = $db->prepare("INSERT INTO award_students (award_id, student_id, position, remarks) VALUES (?,?,?,?)
                                      ON DUPLICATE KEY UPDATE position=?, remarks=?");
                foreach ($student_ids as $sid) {
                    $stmt->execute([$award_id, (int)$sid, $position, $remarks, $position, $remarks]);
                    $count++;
                }
                $message = "$count student(s) added to award!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Fetch data
$academic_years = [];
try { $academic_years = $db->query("SELECT * FROM academic_years ORDER BY year_name DESC")->fetchAll(); } catch (PDOException $e) {}

$awards = [];
try {
    $awards = $db->query("SELECT a.*, ay.year_name, COUNT(as2.id) as student_count
                          FROM awards a
                          LEFT JOIN academic_years ay ON a.academic_year_id = ay.id
                          LEFT JOIN award_students as2 ON a.id = as2.award_id
                          GROUP BY a.id
                          ORDER BY a.created_at DESC")->fetchAll();
} catch (PDOException $e) {}

// Get classes for student filter
$classes = [];
try { $classes = $db->query("SELECT * FROM classes ORDER BY numeric_grade")->fetchAll(); } catch (PDOException $e) {}

// Get students for adding to award
$students = [];
try { $students = $db->query("SELECT id, first_name, last_name, reg_id, class_id FROM students WHERE status='approved' ORDER BY first_name")->fetchAll(); } catch (PDOException $e) {}

// Get award details for student add
$selected_award = null;
$selected_award_id = isset($_GET['award_id']) ? (int)$_GET['award_id'] : 0;
if ($selected_award_id > 0) {
    $stmt = $db->prepare("SELECT * FROM awards WHERE id = ?");
    $stmt->execute([$selected_award_id]);
    $selected_award = $stmt->fetch();
}

// Get students in selected award
$award_students = [];
if ($selected_award_id > 0) {
    $stmt = $db->prepare("SELECT as2.*, s.first_name, s.last_name, s.reg_id, c.class_name
                          FROM award_students as2
                          JOIN students s ON as2.student_id = s.id
                          LEFT JOIN classes c ON s.class_id = c.id
                          WHERE as2.award_id = ?
                          ORDER BY as2.position, s.first_name");
    $stmt->execute([$selected_award_id]);
    $award_students = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Lists | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        .stats-card { background: white; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stats-number { font-size: 1.8rem; font-weight: bold; color: #1a3a5c; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .award-card { border-left: 4px solid #28a745; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .award-card:hover { background: #e9ecef; transform: translateX(3px); }
        .award-card.active { border-left-color: #f4a261; background: #fff9e6; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; }
        .position-badge { padding: 3px 10px; border-radius: 20px; background: #f4a261; color: white; font-size: 11px; font-weight: 600; }
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

            <div class="row mb-3">
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Awards</small><div class="stats-number"><?= count($awards) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Awardees</small><div class="stats-number text-success"><?= array_sum(array_column($awards, 'student_count')) ?></div></div></div>
                <div class="col-md-6"><div class="text-end"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAwardModal"><i class="bi bi-plus-lg me-1"></i>Create Award</button></div></div>
            </div>

            <div class="row">
                <!-- Award List -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-trophy text-warning me-2"></i>All Awards</div>
                        <div class="card-body">
                            <?php if (empty($awards)): ?>
                                <div class="empty-state"><i class="bi bi-trophy"></i><p class="mb-0">No awards created yet.</p></div>
                            <?php else: ?>
                                <?php foreach ($awards as $award): ?>
                                    <a href="awards.php?award_id=<?= $award['id'] ?>" class="text-decoration-none">
                                        <div class="award-card <?= $selected_award_id == $award['id'] ? 'active' : '' ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?= htmlspecialchars($award['award_name']) ?></strong>
                                                    <div class="small text-muted"><i class="bi bi-people me-1"></i><?= $award['student_count'] ?> students</div>
                                                    <?php if (!empty($award['year_name'])): ?><div class="small text-muted"><i class="bi bi-calendar me-1"></i><?= htmlspecialchars($award['year_name']) ?></div><?php endif; ?>
                                                </div>
                                                <a href="awards.php?delete_id=<?= $award['id'] ?>" class="btn btn-sm text-danger" onclick="event.stopPropagation(); return confirm('Delete this award?')"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Award Students -->
                <div class="col-lg-8">
                    <?php if ($selected_award): ?>
                        <div class="card mb-3">
                            <div class="card-header p-3 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-trophy text-warning me-2"></i><?= htmlspecialchars($selected_award['award_name']) ?> - Awardees</span>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-person-plus me-1"></i>Add Students</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>#</th><th>Reg ID</th><th>Student</th><th>Class</th><th>Position</th><th>Remarks</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($award_students)): ?>
                                            <tr><td colspan="7" class="text-center py-4 text-muted">No students added to this award yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($award_students as $i => $stu): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td><?= htmlspecialchars($stu['reg_id']) ?></td>
                                                    <td><strong><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></strong></td>
                                                    <td><?= htmlspecialchars($stu['class_name'] ?? '—') ?></td>
                                                    <td><?= !empty($stu['position']) ? '<span class="position-badge">' . htmlspecialchars($stu['position']) . '</span>' : '—' ?></td>
                                                    <td><?= htmlspecialchars($stu['remarks'] ?? '—') ?></td>
                                                    <td><a href="awards.php?award_id=<?= $selected_award_id ?>&remove_student=<?= $stu['id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('Remove this student?')"><i class="bi bi-x-circle"></i></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="empty-state py-5">
                                <i class="bi bi-trophy display-4 text-warning"></i>
                                <h5 class="mt-3">Select an Award</h5>
                                <p class="text-muted">Click on an award from the left to view and manage its students.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Award Modal -->
    <div class="modal fade" id="createAwardModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_award">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-trophy me-2"></i>Create Award</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-semibold">Award Name <span class="text-danger">*</span></label><input type="text" name="award_name" class="form-control" placeholder="e.g. Best Student of the Year" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Academic Year</label>
                        <select name="academic_year_id" class="form-select"><option value="">Select Year</option>
                        <?php foreach ($academic_years as $year): ?><option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['year_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Award</button></div>
            </form>
        </div></div>
    </div>

    <!-- Add Student Modal -->
    <?php if ($selected_award): ?>
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_student">
                <input type="hidden" name="award_id" value="<?= $selected_award_id ?>">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Students to <?= htmlspecialchars($selected_award['award_name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold">Position (Optional)</label><input type="text" name="position" class="form-control" placeholder="e.g. 1st, Gold, Topper"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Remarks</label><input type="text" name="remarks" class="form-control" placeholder="Optional"></div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Select Students</label>
                        <div class="border rounded p-3" style="max-height:300px;overflow-y:auto;">
                            <?php foreach ($students as $student): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="student_ids[]" value="<?= $student['id'] ?>" id="stu_<?= $student['id'] ?>">
                                    <label class="form-check-label" for="stu_<?= $student['id'] ?>"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> (<?= htmlspecialchars($student['reg_id']) ?>)</label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add Selected</button></div>
            </form>
        </div></div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
