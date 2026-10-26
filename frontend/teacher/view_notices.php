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

// Fetch notices for teachers (target_audience = 'all' or 'teachers')
$notices = [];
try {
    $stmt = $db->query("SELECT * FROM notices WHERE status = 'active' AND target_audience IN ('all', 'teachers', 'admin') ORDER BY created_at DESC LIMIT 30");
    $notices = $stmt->fetchAll();
} catch (PDOException $e) {}

// Calculate unread-based stats
$total_notices = count($notices);
$recent_count = 0;
foreach ($notices as $n) {
    if (strtotime($n['created_at']) > strtotime('-7 days')) $recent_count++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices & Announcements - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .stat-card { background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid; margin-bottom: 20px; }
        .stat-number { font-size: 28px; font-weight: 800; }
        .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; }
        .notice-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 15px; overflow: hidden; transition: transform 0.2s; }
        .notice-card:hover { transform: translateY(-2px); }
        .notice-header { padding: 15px 20px; border-left: 4px solid #f5576c; }
        .notice-title { font-weight: 700; font-size: 16px; color: #2c3e50; }
        .notice-meta { font-size: 12px; color: #6c757d; }
        .notice-body { padding: 0 20px 15px 20px; }
        .audience-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .empty-state { text-align: center; padding: 60px; color: #6c757d; }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-megaphone me-2"></i>Notices & Announcements</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($teacher_name) ?> | View all school notices</p>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4"><div class="stat-card" style="border-left-color:#f5576c;"><div class="stat-number"><?= $total_notices ?></div><div class="stat-label">Total Notices</div></div></div>
                <div class="col-md-4"><div class="stat-card" style="border-left-color:#f093fb;"><div class="stat-number text-warning"><?= $recent_count ?></div><div class="stat-label">New This Week</div></div></div>
                <div class="col-md-4"><div class="stat-card" style="border-left-color:#38ef7d;"><div class="stat-number text-success"><?= $total_notices ?></div><div class="stat-label">Active</div></div></div>
            </div>

            <?php if (empty($notices)): ?>
                <div class="empty-state"><i class="bi bi-megaphone"></i><h5>No notices available</h5><p class="mb-0">There are no notices for you right now.</p></div>
            <?php else: ?>
                <?php foreach ($notices as $notice): ?>
                    <?php $audience_class = $notice['target_audience'] == 'all' ? 'bg-primary' : 'bg-info'; ?>
                    <div class="notice-card">
                        <div class="notice-header">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div class="notice-title"><?= htmlspecialchars($notice['title']) ?></div>
                                <span class="audience-badge <?= $audience_class ?> text-white"><?= ucfirst(htmlspecialchars($notice['target_audience'])) ?></span>
                            </div>
                            <div class="notice-meta mt-1">
                                <i class="bi bi-calendar me-1"></i><?= date('d M Y h:i A', strtotime($notice['created_at'])) ?>
                                <?php if (strtotime($notice['created_at']) > strtotime('-7 days')): ?><span class="badge bg-danger ms-2">New</span><?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($notice['content'])): ?>
                            <div class="notice-body"><p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($notice['content'])) ?></p></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
