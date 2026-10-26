<?php
// Admin - Automatic Experience Letters for Teachers
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    try {
        $db->prepare("DELETE FROM experience_letters WHERE id = ?")->execute([(int)$_GET['delete_id']]);
        header('Location: experience_letters.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: experience_letters.php?error=1');
        exit;
    }
}

// Handle POST (generate letter)
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $teacher_id = (int)$_POST['teacher_id'];
    $letter_no = trim($_POST['letter_no'] ?? '');
    $issued_date = $_POST['issued_date'] ?? date('Y-m-d');

    if ($teacher_id <= 0) {
        $message = 'Select a teacher.';
        $messageType = 'danger';
    } else {
        try {
            // Get teacher info to auto-generate content
            $stmt = $db->prepare("SELECT t.*, u.full_name, u.email
                                  FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.teacher_id = ?");
            $stmt->execute([$teacher_id]);
            $teacher = $stmt->fetch();

            if (!$teacher) {
                $message = 'Teacher not found.';
                $messageType = 'danger';
            } else {
                $exp = (int)$teacher['experience_years'];
                $content = "This is to certify that Mr./Ms. " . $teacher['full_name'] . ", Employee ID " . $teacher['employee_id'] . ", served as a faculty member at Smart Choice Academy. " .
                           "During their tenure, they performed their duties with dedication and professionalism. " .
                           "Qualification: " . ($teacher['qualification'] ?? 'N/A') . ". Gender: " . $teacher['gender'] . ". " .
                           "We wish them the very best in their future endeavors.";

                $stmt = $db->prepare("INSERT INTO experience_letters (teacher_id, letter_no, issued_date, content, generated_by) VALUES (?,?,?,?,?)");
                $stmt->execute([$teacher_id, $letter_no, $issued_date, $content, $_SESSION['user_id']]);
                $message = 'Experience letter generated successfully!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch teachers
$teachers = [];
try { $teachers = $db->query("SELECT t.teacher_id, t.employee_id, t.experience_years, t.gender, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.status='active' ORDER BY u.full_name")->fetchAll(); } catch (PDOException $e) {}

// Fetch letters
$letters = [];
try {
    $letters = $db->query("SELECT el.*, t.employee_id, u.full_name
                           FROM experience_letters el
                           JOIN teachers t ON el.teacher_id = t.teacher_id
                           JOIN users u ON t.user_id = u.id
                           ORDER BY el.generated_at DESC")->fetchAll();
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Experience Letters | Smart Choice Academy</title>
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
        .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; }
        .table td { font-size: 13px; vertical-align: middle; }
        .letter-item { border-left: 4px solid #6f42c1; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
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
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Letters</small><div class="stats-number"><?= count($letters) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Active Teachers</small><div class="stats-number text-success"><?= count($teachers) ?></div></div></div>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-award text-primary me-2"></i>Generate Letter</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="generate">
                                <div class="mb-3"><label class="form-label fw-semibold">Teacher <span class="text-danger">*</span></label>
                                    <select name="teacher_id" class="form-select" required>
                                        <option value="">Select Teacher</option>
                                        <?php foreach ($teachers as $t): ?><option value="<?= $t['teacher_id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['employee_id']) ?>)</option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3"><label class="form-label fw-semibold">Letter Number</label><input type="text" name="letter_no" class="form-control" placeholder="e.g. SCA/EXP/2025/001"></div>
                                <div class="mb-3"><label class="form-label fw-semibold">Issue Date</label><input type="date" name="issued_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-magic me-1"></i>Generate Letter</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-file-earmark-text text-warning me-2"></i>Generated Letters</span><span class="badge bg-primary"><?= count($letters) ?></span></div>
                        <div class="card-body">
                            <?php if (empty($letters)): ?>
                                <div class="empty-state"><i class="bi bi-file-earmark-x"></i><p class="mb-0">No experience letters generated yet.</p></div>
                            <?php else: ?>
                                <?php foreach ($letters as $letter): ?>
                                    <div class="letter-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= htmlspecialchars($letter['full_name']) ?></strong>
                                                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($letter['employee_id']) ?></span>
                                                <?php if (!empty($letter['letter_no'])): ?><span class="badge bg-info"><?= htmlspecialchars($letter['letter_no']) ?></span><?php endif; ?>
                                                <div class="small text-muted mt-1"><i class="bi bi-calendar me-1"></i>Issued: <?= date('d M Y', strtotime($letter['issued_date'] ?? $letter['generated_at'])) ?></div>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <a href="experience_letter_print.php?id=<?= $letter['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-printer"></i></a>
                                                <a href="experience_letters.php?delete_id=<?= $letter['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this letter?')"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-2"><?= nl2br(htmlspecialchars(substr($letter['content'], 0, 150))) ?>...</div>
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
</body>

</html>
