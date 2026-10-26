<?php
// Admin - Notices & Announcements Management
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM notices WHERE id = ?");
        $stmt->execute([(int)$_GET['delete_id']]);
        header('Location: notices.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: notices.php?error=1');
        exit;
    }
}

// Handle POST (create/update)
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target_audience = $_POST['target_audience'] ?? 'all';
    $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $status = $_POST['status'] ?? 'active';
    $notice_id = isset($_POST['notice_id']) ? (int)$_POST['notice_id'] : 0;

    if (empty($title)) {
        $message = 'Notice title is required.';
        $messageType = 'danger';
    } else {
        try {
            if ($notice_id > 0) {
                $stmt = $db->prepare("UPDATE notices SET title=?, content=?, target_audience=?, class_id=?, status=? WHERE id=?");
                $stmt->execute([$title, $content, $target_audience, $class_id, $status, $notice_id]);
                $message = 'Notice updated successfully!';
            } else {
                $stmt = $db->prepare("INSERT INTO notices (title, content, target_audience, class_id, status, created_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$title, $content, $target_audience, $class_id, $status, $_SESSION['user_id']]);
                $message = 'Notice published successfully!';
            }
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error saving notice: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch notices
$notices = [];
try {
    // Collect summary counts
    $notices = $db->query("SELECT n.*, u.full_name as creator FROM notices n LEFT JOIN users u ON n.created_by = u.id ORDER BY n.created_at DESC")->fetchAll();
} catch (PDOException $e) {}

// Stats
$total = count($notices);
$active = count(array_filter($notices, fn($n) => $n['status'] == 'active'));
$inactive = $total - $active;

// Classes for filter
$classes = [];
try { $classes = $db->query("SELECT * FROM classes ORDER BY numeric_grade")->fetchAll(); } catch (PDOException $e) {}

// Get edit data
$edit_notice = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM notices WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit_notice = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices & Announcements | Smart Choice Academy</title>
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
        .table th { background: #f8f9fa; padding: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .table td { padding: 10px 12px; font-size: 13px; vertical-align: middle; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .active { background: #d4edda; color: #155724; }
        .inactive { background: #f8d7da; color: #721c24; }
        .audience-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; background: #e7f1ff; color: #084298; }
        .notice-content-preview { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">Notice deleted successfully!</div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Notices</small><div class="stats-number"><?= $total ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Active</small><div class="stats-number text-success"><?= $active ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Inactive</small><div class="stats-number text-danger"><?= $inactive ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Audience Groups</small><div class="stats-number text-primary">5</div></div></div>
            </div>

            <div class="row">
                <!-- Form Column -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header p-3">
                            <i class="bi bi-<?= $edit_notice ? 'pencil-square' : 'megaphone' ?> text-primary me-2"></i>
                            <?= $edit_notice ? 'Edit Notice' : 'Publish New Notice' ?>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($edit_notice): ?><input type="hidden" name="notice_id" value="<?= $edit_notice['id'] ?>"><?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($edit_notice['title'] ?? '') ?>" placeholder="Notice title" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Content</label>
                                    <textarea name="content" class="form-control" rows="4" placeholder="Notice content..."><?= htmlspecialchars($edit_notice['content'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Target Audience</label>
                                    <select name="target_audience" class="form-select">
                                        <?php foreach (['all' => 'Everyone', 'students' => 'Students', 'teachers' => 'Teachers', 'parents' => 'Parents', 'admin' => 'Admin'] as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= ($edit_notice['target_audience'] ?? 'all') == $key ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Specific Class (Optional)</label>
                                    <select name="class_id" class="form-select">
                                        <option value="">All Classes</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?= $class['id'] ?>" <?= ($edit_notice['class_id'] ?? '') == $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?= ($edit_notice['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= ($edit_notice['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i><?= $edit_notice ? 'Update Notice' : 'Publish Notice' ?></button>
                                    <?php if ($edit_notice): ?><a href="notices.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- List Column -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-list-ul text-success me-2"></i>All Notices</span>
                            <span class="badge bg-primary"><?= $total ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Title</th><th>Audience</th><th>Class</th><th>Status</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if (empty($notices)): ?>
                                        <tr><td colspan="7" class="empty-state"><i class="bi bi-megaphone"></i><p class="mb-0">No notices published yet.</p></td></tr>
                                    <?php else: ?>
                                        <?php foreach ($notices as $notice): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($notice['title']) ?></div>
                                                    <?php if (!empty($notice['content'])): ?><div class="notice-content-preview small text-muted"><?= htmlspecialchars($notice['content']) ?></div><?php endif; ?>
                                                </td>
                                                <td><span class="audience-badge"><?= ucfirst(htmlspecialchars($notice['target_audience'])) ?></span></td>
                                                <td><?= $notice['class_id'] ? 'Class #' . $notice['class_id'] : 'All' ?></td>
                                                <td><span class="status-badge <?= $notice['status'] ?>"><?= ucfirst($notice['status']) ?></span></td>
                                                <td><?= htmlspecialchars($notice['creator'] ?? 'System') ?></td>
                                                <td><?= date('d M Y', strtotime($notice['created_at'])) ?></td>
                                                <td>
                                                    <a href="notices.php?edit_id=<?= $notice['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                                    <a href="notices.php?delete_id=<?= $notice['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this notice?')"><i class="bi bi-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
