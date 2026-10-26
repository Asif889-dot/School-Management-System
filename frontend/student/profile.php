<?php
// Student - My Profile

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Get student by reg_id
$stmt = $db->prepare("
    SELECT s.*, c.class_name, sec.section_name, ay.year_name as academic_year_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.reg_id = ? AND s.status = 'approved'
    LIMIT 1
");
$stmt->execute([$_SESSION['reg_id']]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: ../../index.php?error=access_denied');
    exit;
}

// Get user info
$user = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE reg_id = ?");
    $stmt->execute([$_SESSION['reg_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {}

$photo_path = !empty($student['photo']) && file_exists('../../' . $student['photo']) ? '../../' . $student['photo'] : null;
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
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; border: 4px solid white; background: white; overflow: hidden; margin: 0 auto 15px; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar .placeholder { font-size: 45px; color: #667eea; display: flex; align-items: center; justify-content: center; height: 100%; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .info-label { width: 160px; color: #6c757d; font-weight: 600; }
        .info-value { flex: 1; color: #333; }
    </style>
</head>

<body>
    <?php include '../../backend/student_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="profile-card">
                <div class="profile-header">
                    <?php if ($photo_path): ?>
                        <div class="profile-avatar"><img src="<?= $photo_path ?>" alt="Student"></div>
                    <?php else: ?>
                        <div class="profile-avatar"><div class="placeholder"><i class="bi bi-person-fill"></i></div></div>
                    <?php endif; ?>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h3>
                    <div class="opacity-75"><i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']) ?></div>
                    <div class="small mt-2"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($student['reg_id']) ?></div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-person-badge text-primary me-2"></i>Personal Information</div>
                        <div class="card-body">
                            <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div></div>
                            <div class="info-row"><div class="info-label">Registration ID</div><div class="info-value"><?= htmlspecialchars($student['reg_id']) ?></div></div>
                            <div class="info-row"><div class="info-label">Gender</div><div class="info-value"><?= htmlspecialchars($student['gender']) ?></div></div>
                            <div class="info-row"><div class="info-label">Date of Birth</div><div class="info-value"><?= !empty($student['date_of_birth']) ? date('d M Y', strtotime($student['date_of_birth'])) : '—' ?></div></div>
                            <div class="info-row"><div class="info-label">Religion</div><div class="info-value"><?= htmlspecialchars($student['religion'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Admission Type</div><div class="info-value"><?= htmlspecialchars($student['admission_type']) ?></div></div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-people text-success me-2"></i>Parent Information</div>
                        <div class="card-body">
                            <div class="info-row"><div class="info-label">Father's Name</div><div class="info-value"><?= htmlspecialchars($student['father_name'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Father's Phone</div><div class="info-value"><?= htmlspecialchars($student['father_phone'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Mother's Name</div><div class="info-value"><?= htmlspecialchars($student['mother_name'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Mother's Phone</div><div class="info-value"><?= htmlspecialchars($student['mother_phone'] ?? '—') ?></div></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-mortarboard text-warning me-2"></i>Academic Information</div>
                        <div class="card-body">
                            <div class="info-row"><div class="info-label">Class</div><div class="info-value"><?= htmlspecialchars($student['class_name']) ?></div></div>
                            <div class="info-row"><div class="info-label">Section</div><div class="info-value"><?= htmlspecialchars($student['section_name'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Academic Year</div><div class="info-value"><?= htmlspecialchars($student['academic_year_name'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Admission Date</div><div class="info-value"><?= date('d M Y', strtotime($student['admission_date'])) ?></div></div>
                            <div class="info-row"><div class="info-label">Previous School</div><div class="info-value"><?= htmlspecialchars($student['previous_school'] ?? '—') ?></div></div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-shield-check text-info me-2"></i>Account Information</div>
                        <div class="card-body">
                            <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($user['email'] ?? '—') ?></div></div>
                            <div class="info-row"><div class="info-label">Status</div><div class="info-value"><span class="badge bg-success">Active</span></div></div>
                            <div class="info-row"><div class="info-label">Account Created</div><div class="info-value"><?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
