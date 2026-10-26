<?php
// Admin - Daily Diary Management
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    try {
        $db->prepare("DELETE FROM daily_diary WHERE id = ?")->execute([(int)$_GET['delete_id']]);
        header('Location: daily_diary.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: daily_diary.php?error=1');
        exit;
    }
}

// Handle POST
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)$_POST['class_id'];
    $section_id = (int)$_POST['section_id'];
    $entry_date = $_POST['entry_date'] ?? date('Y-m-d');
    $title = trim($_POST['title'] ?? '');
    $homework = trim($_POST['homework'] ?? '');
    $activities = trim($_POST['activities'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($class_id <= 0 || $section_id <= 0) {
        $message = 'Please select class and section.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO daily_diary (class_id, section_id, entry_date, title, homework, activities, notes) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$class_id, $section_id, $entry_date, $title, $homework, $activities, $notes]);
            $message = 'Diary entry added successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch classes/sections
$classes = [];
try { $classes = $db->query("SELECT * FROM classes ORDER BY numeric_grade")->fetchAll(); } catch (PDOException $e) {}
$sections = [];
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($selected_class_id > 0) {
    try { $sections = $db->query("SELECT * FROM sections WHERE class_id = $selected_class_id ORDER BY section_name")->fetchAll(); } catch (PDOException $e) {}
}

// Fetch diary entries
$entries = [];
$query = "SELECT dd.*, c.class_name, sec.section_name, u.full_name as teacher_name
          FROM daily_diary dd
          LEFT JOIN classes c ON dd.class_id = c.id
          LEFT JOIN sections sec ON dd.section_id = sec.id
          LEFT JOIN teachers t ON dd.teacher_id = t.teacher_id
          LEFT JOIN users u ON t.user_id = u.id";
if ($selected_class_id > 0) $query .= " WHERE dd.class_id = $selected_class_id";
$query .= " ORDER BY dd.entry_date DESC, dd.id DESC";
try { $entries = $db->query($query)->fetchAll(); } catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Diary | Smart Choice Academy</title>
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
        .diary-item { border-left: 4px solid #4a6fa5; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
        .homework-text { color: #856404; background: #fff3cd; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
        .activities-text { color: #084298; background: #e7f1ff; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
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
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Entries</small><div class="stats-number"><?= count($entries) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Classes</small><div class="stats-number"><?= count($classes) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Today's Entries</small><div class="stats-number text-success"><?= count(array_filter($entries, fn($e) => $e['entry_date'] == date('Y-m-d'))) ?></div></div></div>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-journal-plus text-primary me-2"></i>Add Diary Entry</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
                                    <select name="class_id" id="classSelect" class="form-select" required>
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Section <span class="text-danger">*</span></label>
                                    <select name="section_id" id="sectionSelect" class="form-select" required>
                                        <option value="">Select Class first</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Date</label>
                                    <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title</label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g. Mathematics Homework">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Homework</label>
                                    <textarea name="homework" class="form-control" rows="2" placeholder="Homework details..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Activities</label>
                                    <textarea name="activities" class="form-control" rows="2" placeholder="Class activities..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i> Save Entry</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header p-3"><i class="bi bi-funnel text-success me-2"></i>Filter by Class</div>
                        <div class="card-body py-2">
                            <form method="GET" class="row g-2">
                                <div class="col-md-6">
                                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                                        <option value="0">All Classes</option>
                                        <?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>" <?= $selected_class_id == $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-journal-bookmark text-warning me-2"></i>Diary Entries</span><span class="badge bg-primary"><?= count($entries) ?></span></div>
                        <div class="card-body">
                            <?php if (empty($entries)): ?>
                                <div class="empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">No diary entries found.</p></div>
                            <?php else: ?>
                                <?php foreach ($entries as $entry): ?>
                                    <div class="diary-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= date('l, d M Y', strtotime($entry['entry_date'])) ?></strong>
                                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($entry['class_name']) ?> - <?= htmlspecialchars($entry['section_name']) ?></span>
                                                <?php if (!empty($entry['title'])): ?><span class="badge bg-secondary ms-1"><?= htmlspecialchars($entry['title']) ?></span><?php endif; ?>
                                                <?php if (!empty($entry['teacher_name'])): ?><div class="small text-muted mt-1">By: <?= htmlspecialchars($entry['teacher_name']) ?></div><?php endif; ?>
                                            </div>
                                            <a href="daily_diary.php?delete_id=<?= $entry['id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('Delete this entry?')"><i class="bi bi-trash"></i></a>
                                        </div>
                                        <?php if (!empty($entry['homework'])): ?><div class="homework-text mt-2"><i class="bi bi-pencil-square me-1"></i><strong>Homework:</strong> <?= nl2br(htmlspecialchars($entry['homework'])) ?></div><?php endif; ?>
                                        <?php if (!empty($entry['activities'])): ?><div class="activities-text mt-2"><i class="bi bi-stars me-1"></i><strong>Activities:</strong> <?= nl2br(htmlspecialchars($entry['activities'])) ?></div><?php endif; ?>
                                        <?php if (!empty($entry['notes'])): ?><div class="small text-muted mt-2"><?= nl2br(htmlspecialchars($entry['notes'])) ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const classSelect = document.getElementById('classSelect');
        const sectionSelect = document.getElementById('sectionSelect');
        classSelect.addEventListener('change', function() {
            const classId = this.value;
            if (classId) {
                fetch('../../backend/get_sections_by_class.php?class_id=' + classId)
                    .then(r => r.text())
                    .then(html => {
                        sectionSelect.innerHTML = html;
                    });
            } else {
                sectionSelect.innerHTML = '<option value="">Select Class first</option>';
            }
        });
    </script>
</body>

</html>
