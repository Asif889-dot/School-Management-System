<?php
// Student - View School Notices

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Fetch notices
$notices = [];
try {
    $notices = $db->query("
        SELECT * FROM notices 
        WHERE status = 'active' AND (target_audience = 'all' OR target_audience = 'students')
        ORDER BY created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Notices - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .notice-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #4e73df; transition: transform 0.2s; }
        .notice-card:hover { transform: translateY(-3px); }
        .notice-title { font-size: 16px; font-weight: 700; color: #2c3e50; margin: 5px 0; }
        .notice-date { font-size: 12px; color: #6c757d; }
        .notice-content { font-size: 14px; color: #495057; }
        .empty-state { text-align: center; padding: 60px; color: #6c757d; }
        .empty-state i { font-size: 64px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/student_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-megaphone me-2"></i>School Notices</h4>
                        <p class="mb-0 opacity-75">Announcements from the school</p>
                    </div>
                </div>
            </div>

            <?php if (empty($notices)): ?>
                <div class="empty-state"><i class="bi bi-megaphone-fill"></i><h4>No notices yet</h4><p class="mb-0">Check back later for announcements.</p></div>
            <?php else: ?>
                <?php foreach ($notices as $notice): ?>
                    <div class="notice-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="notice-date"><i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y', strtotime($notice['created_at'])) ?></div>
                                <div class="notice-title"><?= htmlspecialchars($notice['title']) ?></div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary">New</span>
                        </div>
                        <?php if (!empty($notice['content'])): ?>
                            <div class="notice-content mt-2"><?= nl2br(htmlspecialchars($notice['content'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
