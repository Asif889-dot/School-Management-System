<?php

/**
 * Upload Study Plan - Teacher View
 * Smart Choice Academy
 * Teachers can upload weekly study plans / syllabus for their classes
 */

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

// Get teacher's classes/sections
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

// Message handling
$message = '';
$messageType = '';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $class_id = (int)$_POST['class_id'];
    $section_id = (int)$_POST['section_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $week_start = $_POST['week_start'] ?? null;
    $week_end = $_POST['week_end'] ?? null;

    if ($class_id <= 0 || empty($title)) {
        $message = 'Class and title are required.';
        $messageType = 'danger';
    } else {
        try {
            // Handle file upload
            $file_path = null;
            if (isset($_FILES['study_file']) && $_FILES['study_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/study_plans/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $ext = pathinfo($_FILES['study_file']['name'], PATHINFO_EXTENSION);
                $filename = 'sp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['study_file']['tmp_name'], $target)) {
                    $file_path = 'uploads/study_plans/' . $filename;
                }
            }

            $stmt = $db->prepare("INSERT INTO study_plans (teacher_id, class_id, section_id, title, description, file_path, week_start, week_end) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$teacher_id, $class_id, $section_id, $title, $description, $file_path, $week_start, $week_end]);
            $message = 'Study plan uploaded successfully!';
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
        $db->prepare("DELETE FROM study_plans WHERE id = ? AND teacher_id = ?")->execute([(int)$_GET['delete_id'], $teacher_id]);
        header('Location: upload_study_plan.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: upload_study_plan.php?error=1');
        exit;
    }
}

// Fetch my study plans
$study_plans = [];
try {
    $stmt = $db->prepare("
        SELECT sp.*, c.class_name, sec.section_name
        FROM study_plans sp
        LEFT JOIN classes c ON sp.class_id = c.id
        LEFT JOIN sections sec ON sp.section_id = sec.id
        WHERE sp.teacher_id = ?
        ORDER BY sp.created_at DESC LIMIT 20
    ");
    $stmt->execute([$teacher_id]);
    $study_plans = $stmt->fetchAll();
} catch (PDOException $e) {}

// Fetch subjects for this teacher's courses
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Study Plan - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; }
        .table td { font-size: 13px; vertical-align: middle; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .plan-item { border-left: 4px solid #667eea; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-journal-bookmark me-2"></i>Upload Study Plan</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($teacher_name) ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-upload text-primary me-2"></i>New Study Plan</div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload">
<div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
                                        <select name="class_id" id="classSelect" class="form-select" required>
                                            <option value="">Select Class</option>
                                            <?php $seen = []; foreach ($my_sections as $sec): ?>
                                                <?php if (!in_array($sec['class_id'], $seen)): $seen[] = $sec['class_id']; ?>
                                                    <option value="<?= $sec['class_id'] ?>"><?= htmlspecialchars($sec['class_name']) ?></option>
                                                <?php endif; ?>
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
                                    <label class="form-label fw-semibold">Subject</label>
                                    <select name="subject_id" class="form-select">
                                        <option value="">Select Subject (Optional)</option>
                                        <?php foreach ($my_subjects as $sub): ?>
                                            <option value="<?= $sub['course_id'] ?>"><?= htmlspecialchars($sub['course_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g. Week 1 - Chapter 1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the plan"></textarea>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label class="form-label fw-semibold">Week Start</label><input type="date" name="week_start" class="form-control"></div>
                                    <div class="col-6"><label class="form-label fw-semibold">Week End</label><input type="date" name="week_end" class="form-control"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Attach File (Optional)</label>
                                    <input type="file" name="study_file" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-upload me-1"></i>Upload Study Plan</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-list-check text-warning me-2"></i>My Study Plans</span><span class="badge bg-primary"><?= count($study_plans) ?></span></div>
                        <div class="card-body">
                            <?php if (empty($study_plans)): ?>
                                <div class="empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">No study plans uploaded yet.</p></div>
                            <?php else: ?>
                                <?php foreach ($study_plans as $plan): ?>
                                    <div class="plan-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= htmlspecialchars($plan['title']) ?></strong>
                                                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($plan['class_name'] ?? '—') ?><?= !empty($plan['section_name']) ? ' - ' . htmlspecialchars($plan['section_name']) : '' ?></span>
                                                <div class="small text-muted mt-1"><i class="bi bi-calendar me-1"></i><?= !empty($plan['week_start']) ? date('d M', strtotime($plan['week_start'])) . ' - ' . date('d M Y', strtotime($plan['week_end'])) : date('d M Y', strtotime($plan['created_at'])) ?></div>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <?php if (!empty($plan['file_path'])): ?><a href="../../<?= $plan['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-download"></i></a><?php endif; ?>
                                                <a href="upload_study_plan.php?delete_id=<?= $plan['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this plan?')"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </div>
                                        <?php if (!empty($plan['description'])): ?><div class="small text-muted mt-2"><?= htmlspecialchars(substr($plan['description'], 0, 120)) ?></div><?php endif; ?>
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
            sectionSelect.value = '';
        }
        classSelect.addEventListener('change', filterSections);
        filterSections();
    </script>
</body>

</html>
