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
$teacher_id = null;
$teacher_name = '';

if ($user_id) {
    $stmt = $db->prepare("SELECT t.teacher_id, t.employee_id, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
}

if (!$teacher_id && isset($_SESSION['reg_id'])) {
    $stmt = $db->prepare("SELECT t.teacher_id, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.employee_id = ?");
    $stmt->execute([$_SESSION['reg_id']]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
}

// Get current academic year
$stmt = $db->prepare("SELECT id, year_name FROM academic_years WHERE is_current = 'yes' AND status = 'active' LIMIT 1");
$stmt->execute();
$academic_year = $stmt->fetch();
$academic_year_id = $academic_year['id'] ?? 1;

// Get teacher's sections
$my_sections = [];
$stmt = $db->prepare("
    SELECT DISTINCT c.id as class_id, c.class_name, sec.id as section_id, sec.section_name
    FROM timetable_records tr
    JOIN classes c ON tr.class_id = c.id
    JOIN sections sec ON tr.section_id = sec.id
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
    ORDER BY c.numeric_grade, sec.section_name
");
$stmt->execute([$teacher_id, $academic_year_id]);
$my_sections = $stmt->fetchAll();

// Get teacher's courses (subjects)
$my_subjects = [];
$stmt = $db->prepare("
    SELECT DISTINCT c.id as course_id, c.course_name
    FROM timetable_records tr
    JOIN courses c ON tr.course_id = c.id
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
    ORDER BY c.course_name
");
$stmt->execute([$teacher_id, $academic_year_id]);
$my_subjects = $stmt->fetchAll();

$message = '';
$messageType = '';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $class_id = (int)$_POST['class_id'];
    $section_id = (int)$_POST['section_id'];
    $entry_date = $_POST['entry_date'] ?? date('Y-m-d');
    $title = trim($_POST['title'] ?? '');
    $homework = trim($_POST['homework'] ?? '');
    $activities = trim($_POST['activities'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($class_id <= 0 || $section_id <= 0) {
        $message = 'Select class and section.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO daily_diary (class_id, section_id, entry_date, title, homework, activities, notes, teacher_id) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$class_id, $section_id, $entry_date, $title, $homework, $activities, $notes, $teacher_id]);
            $message = 'Daily diary entry saved successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    try {
        $db->prepare("DELETE FROM daily_diary WHERE id = ? AND teacher_id = ?")->execute([(int)$_GET['delete_id'], $teacher_id]);
        header('Location: upload_diary.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: upload_diary.php?error=1');
        exit;
    }
}

// Fetch my diary entries
$diary_entries = [];
try {
    $stmt = $db->prepare("
        SELECT d.*, c.class_name, sec.section_name
        FROM daily_diary d
        LEFT JOIN classes c ON d.class_id = c.id
        LEFT JOIN sections sec ON d.section_id = sec.id
        WHERE d.teacher_id = ?
        ORDER BY d.entry_date DESC LIMIT 30
    ");
    $stmt->execute([$teacher_id]);
    $diary_entries = $stmt->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Daily Diary - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; }
        .table td { font-size: 13px; vertical-align: middle; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .diary-item { border-left: 4px solid #11998e; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-journal-text me-2"></i>Upload Daily Diary</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($teacher_name) ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"><i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-pencil-square text-success me-2"></i>New Diary Entry</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="upload">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
                                        <select name="class_id" id="classSelect" class="form-select" required>
                                            <option value="">Select Class</option>
                                            <?php $seen = []; foreach ($my_sections as $sec): ?>
                                                <?php if (!in_array($sec['class_id'], $seen)): ?>
                                                    <option value="<?= $sec['class_id'] ?>"><?= htmlspecialchars($sec['class_name']) ?></option>
                                                <?php $seen[] = $sec['class_id']; endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Section <span class="text-danger">*</span></label>
                                        <select name="section_id" id="sectionSelect" class="form-select" required>
                                            <option value="">Select Section</option>
                                            <?php foreach ($my_sections as $sec): ?>
                                                <option value="<?= $sec['section_id'] ?>" data-class="<?= $sec['class_id'] ?>"><?= htmlspecialchars($sec['section_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Date</label>
                                    <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title</label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 5 - Fractions">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Homework</label>
                                    <textarea name="homework" class="form-control" rows="2" placeholder="Homework assigned today"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Activities</label>
                                    <textarea name="activities" class="form-control" rows="2" placeholder="Class activities conducted"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-journal-plus me-1"></i>Save Diary Entry</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-journal-text text-warning me-2"></i>My Diary Entries</span><span class="badge bg-success"><?= count($diary_entries) ?></span></div>
                        <div class="card-body">
                            <?php if (empty($diary_entries)): ?>
                                <div class="empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">No diary entries yet.</p></div>
                            <?php else: ?>
                                <?php foreach ($diary_entries as $entry): ?>
                                    <div class="diary-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="d-flex align-items-center flex-wrap gap-2">
                                                    <strong><?= htmlspecialchars($entry['title'] ?? 'Diary Entry') ?></strong>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($entry['class_name'] ?? '—') ?> - <?= htmlspecialchars($entry['section_name'] ?? '—') ?></span>
                                                </div>
                                                <div class="small text-muted mt-1"><i class="bi bi-calendar me-1"></i><?= date('d M Y', strtotime($entry['entry_date'])) ?></div>
                                            </div>
                                            <a href="upload_diary.php?delete_id=<?= $entry['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this entry?')"><i class="bi bi-trash"></i></a>
                                        </div>
                                        <?php if (!empty($entry['homework'])): ?><div class="small mt-2"><i class="bi bi-pencil text-primary me-1"></i><strong>Homework:</strong> <?= htmlspecialchars($entry['homework']) ?></div><?php endif; ?>
                                        <?php if (!empty($entry['activities'])): ?><div class="small mt-1"><i class="bi bi-balloon text-success me-1"></i><strong>Activities:</strong> <?= htmlspecialchars($entry['activities']) ?></div><?php endif; ?>
                                        <?php if (!empty($entry['notes'])): ?><div class="small mt-1 text-muted"><i class="bi bi-sticky me-1"></i><?= htmlspecialchars($entry['notes']) ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const classSelect = document.getElementById('classSelect');
        const sectionSelect = document.getElementById('sectionSelect');
        function filterSections() {
            const classId = classSelect.value;
            sectionSelect.querySelectorAll('option').forEach(opt => {
                if (opt.value === '') { return; }
                opt.style.display = (opt.dataset.class === classId || !classId) ? '' : 'none';
            });
            if (classId) {
                const visible = [...sectionSelect.options].filter(o => o.value && o.style.display !== 'none');
                if (visible.length === 1) sectionSelect.value = visible[0].value;
            }
        }
        classSelect.addEventListener('change', filterSections);
        filterSections();
    </script>
</body>

</html>
