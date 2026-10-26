<?php
// Admin - Syllabus & Study Material Management
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    try {
        $db->prepare("DELETE FROM syllabus WHERE id = ?")->execute([(int)$_GET['delete_id']]);
        header('Location: syllabus.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: syllabus.php?error=1');
        exit;
    }
}

// Handle POST
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)$_POST['class_id'];
    $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $term_id = !empty($_POST['term_id']) ? (int)$_POST['term_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $file_path = null;

    if ($class_id <= 0 || empty($title)) {
        $message = 'Class and title are required.';
        $messageType = 'danger';
    } else {
        // Handle file upload
        if (!empty($_FILES['syllabus_file']['name'])) {
            $upload_dir = '../../uploads/syllabus/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['syllabus_file']['name'], PATHINFO_EXTENSION);
            $filename = 'syllabus_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['syllabus_file']['tmp_name'], $upload_dir . $filename)) {
                $file_path = 'uploads/syllabus/' . $filename;
            }
        }

        try {
            $stmt = $db->prepare("INSERT INTO syllabus (class_id, section_id, term_id, title, description, file_path, uploaded_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$class_id, $section_id, $term_id, $title, $description, $file_path, $_SESSION['user_id']]);
            $message = 'Syllabus uploaded successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch data
$classes = [];
try { $classes = $db->query("SELECT * FROM classes ORDER BY numeric_grade")->fetchAll(); } catch (PDOException $e) {}
$terms = [];
try { $terms = $db->query("SELECT * FROM exam_terms ORDER BY id DESC")->fetchAll(); } catch (PDOException $e) {}

// Fetch syllabus entries
$entries = [];
$query = "SELECT sy.*, c.class_name, sec.section_name, et.term_name, u.full_name as uploader
          FROM syllabus sy
          LEFT JOIN classes c ON sy.class_id = c.id
          LEFT JOIN sections sec ON sy.section_id = sec.id
          LEFT JOIN exam_terms et ON sy.term_id = et.id
          LEFT JOIN users u ON sy.uploaded_by = u.id
          ORDER BY sy.created_at DESC";
try { $entries = $db->query($query)->fetchAll(); } catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syllabus Management | Smart Choice Academy</title>
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
        .syllabus-item { border-left: 4px solid #28a745; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
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
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Syllabus</small><div class="stats-number"><?= count($entries) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Files Uploaded</small><div class="stats-number text-success"><?= count(array_filter($entries, fn($e) => !empty($e['file_path']))) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Classes</small><div class="stats-number"><?= count($classes) ?></div></div></div>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Upload Syllabus</div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
                                    <select name="class_id" id="classSelect" class="form-select" required>
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Section</label>
                                    <select name="section_id" id="sectionSelect" class="form-select">
                                        <option value="">All Sections</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Term</label>
                                    <select name="term_id" class="form-select">
                                        <option value="">General / No Term</option>
                                        <?php foreach ($terms as $term): ?><option value="<?= $term['id'] ?>"><?= htmlspecialchars($term['term_name']) ?> (<?= htmlspecialchars($term['session_year']) ?>)</option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g. Mathematics - Term 1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="3" placeholder="Syllabus description..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Syllabus File (PDF/DOC)</label>
                                    <input type="file" name="syllabus_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx">
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i> Upload Syllabus</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-journal-bookmark text-warning me-2"></i>Syllabus List</span><span class="badge bg-primary"><?= count($entries) ?></span></div>
                        <div class="card-body">
                            <?php if (empty($entries)): ?>
                                <div class="empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">No syllabus uploaded yet.</p></div>
                            <?php else: ?>
                                <?php foreach ($entries as $entry): ?>
                                    <div class="syllabus-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= htmlspecialchars($entry['title']) ?></strong>
                                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($entry['class_name']) ?></span>
                                                <?php if (!empty($entry['section_name'])): ?><span class="badge bg-secondary"><?= htmlspecialchars($entry['section_name']) ?></span><?php endif; ?>
                                                <?php if (!empty($entry['term_name'])): ?><span class="badge bg-info"><?= htmlspecialchars($entry['term_name']) ?></span><?php endif; ?>
                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($entry['uploader'] ?? 'Admin') ?>
                                                    | <i class="bi bi-clock me-1"></i><?= date('d M Y', strtotime($entry['created_at'])) ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <?php if (!empty($entry['file_path']) && file_exists('../../' . $entry['file_path'])): ?>
                                                    <a href="../../<?= $entry['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i></a>
                                                <?php endif; ?>
                                                <a href="syllabus.php?delete_id=<?= $entry['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this syllabus?')"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </div>
                                        <?php if (!empty($entry['description'])): ?><div class="small text-muted mt-2"><?= nl2br(htmlspecialchars($entry['description'])) ?></div><?php endif; ?>
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
                    .then(html => { sectionSelect.innerHTML = html; });
            } else {
                sectionSelect.innerHTML = '<option value="">All Sections</option>';
            }
        });
    </script>
</body>

</html>
