<?php
// Guardian - My Profile

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'guardian') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;
$guardian_user_id = $_SESSION['user_id'] ?? 0;

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$guardian_user_id]);
$user = $stmt->fetch();

$guardian_name = $user['full_name'] ?? $_SESSION['full_name'] ?? 'Parent';

// Get children
$children = [];
$stmt = $db->prepare("
    SELECT s.*, c.class_name, sec.section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.status = 'approved'
      AND (s.guardian_user_id = ? OR s.father_name = ? OR s.mother_name = ?)
    ORDER BY s.id DESC
");
$stmt->execute([$guardian_user_id, $guardian_name, $guardian_name]);
$children = $stmt->fetchAll();

if (empty($children)) {
    $stmt = $db->prepare("
        SELECT s.*, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.status = 'approved'
          AND (s.father_phone = ? OR s.mother_phone = ?)
        ORDER BY s.id DESC
    ");
    $stmt->execute([$_SESSION['reg_id'] ?? '', $_SESSION['reg_id'] ?? '']);
    $children = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .profile-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .profile-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; border: 4px solid white; background: white; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #667eea; margin: 0 auto 15px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .info-label { width: 150px; color: #6c757d; font-weight: 600; }
        .info-value { flex: 1; color: #333; }
        .child-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; background: #e7f1ff; color: #084298; font-size: 12px; font-weight: 600; margin: 3px; }
    </style>
</head>

<body>
    <?php include '../../backend/guardian_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar"><i class="bi bi-person-fill"></i></div>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($guardian_name) ?></h3>
                    <div class="opacity-75">Parent / Guardian</div>
                    <div class="small mt-2"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($user['reg_id'] ?? '') ?></div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-person-badge text-primary me-2"></i>Account Information</div>
                        <div class="card-body">
                            <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($user['full_name'] ?? '') ?></div></div>
                            <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($user['email'] ?? '') ?></div></div>
                            <div class="info-row"><div class="info-label">Registration ID</div><div class="info-value"><?= htmlspecialchars($user['reg_id'] ?? '') ?></div></div>
                            <div class="info-row"><div class="info-label">Role</div><div class="info-value"><span class="badge bg-warning">Guardian</span></div></div>
                            <div class="info-row"><div class="info-label">Status</div><div class="info-value"><span class="badge bg-success">Active</span></div></div>
                            <div class="info-row"><div class="info-label">Account Created</div><div class="info-value"><?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?></div></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-people text-success me-2"></i>Linked Children</div>
                        <div class="card-body">
                            <?php if (empty($children)): ?>
                                <p class="text-muted mb-0">No children linked to your account.</p>
                            <?php else: ?>
                                <?php foreach ($children as $child): ?>
                                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                        <div>
                                            <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($child['reg_id']) ?></div>
                                        </div>
                                        <div class="child-badge"><?= htmlspecialchars($child['class_name'] ?? '—') ?> - <?= htmlspecialchars($child['section_name'] ?? '—') ?></div>
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
</body>

</html>
