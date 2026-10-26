<?php
// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../index.php?error=unauthorized');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php?error=access_denied');
    exit();
}

require_once '../../backend/config.php';

$db = $pdo;
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId == 0) {
    header('Location: users_roles.php?error=user_not_found');
    exit();
}

// Fetch user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users_roles.php?error=user_not_found');
    exit();
}

// Determine linked record (student/teacher/guardian)
$linked_student = null;
$linked_teacher = null;
$linked_guardian = null;

if ($user['role'] === 'student') {
    $stmt = $db->prepare("
        SELECT s.*, c.class_name, sec.section_name, ay.year_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
        WHERE s.reg_id = ? 
    ");
    $stmt->execute([$user['reg_id']]);
    $linked_student = $stmt->fetch();
}

if ($user['role'] === 'teacher') {
    $stmt = $db->prepare("
        SELECT t.*, u.full_name, u.email, u.reg_id
        FROM teachers t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ? OR t.employee_id = ?
    ");
    $stmt->execute([$userId, $user['reg_id']]);
    $linked_teacher = $stmt->fetch();
}

if ($user['role'] === 'guardian') {
    $stmt = $db->prepare("
        SELECT s.id as student_id, s.reg_id as student_reg_id, s.first_name, s.last_name, c.class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.guardian_user_id = ?
    ");
    $stmt->execute([$userId]);
    $linked_guardian = $stmt->fetchAll();
}

// Handle password reset
$message = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters long.';
        $msgType = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $msgType = 'danger';
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hashed, $userId]);
            $message = 'Password reset successfully! The user can now log in with the new password.';
            $msgType = 'success';
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Decode permissions
$permissions = json_decode($user['permissions'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?= htmlspecialchars($user['full_name']) ?> | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }

        .page-header { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-card { background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 25px; }
        .user-hero { background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%); color: white; padding: 30px; text-align: center; }
        .user-avatar { width: 90px; height: 90px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #1a3a5c; margin: 0 auto 15px; font-weight: 700; }
        .user-name { font-size: 22px; font-weight: 700; }
        .user-role { opacity: 0.9; font-size: 13px; }

        .role-badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; }
        .role-student { background: #4a6fa5; }
        .role-teacher { background: #28a745; }
        .role-admin { background: #dc3545; }
        .role-guardian { background: #fd7e14; }

        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #72281c; }
        .status-suspended { background: #ffeeba; color: #856404; }

        .info-section { padding: 25px; border-bottom: 1px solid #e9ecef; }
        .section-title { font-size: 16px; font-weight: 700; color: #1a3a5c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 180px; font-weight: 600; color: #6c757d; font-size: 13px; }
        .info-value { flex: 1; color: #333; font-size: 14px; }
        .perm-chip { display: inline-block; background: #e9ecef; padding: 4px 10px; border-radius: 20px; font-size: 11px; margin: 3px; }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1 text-primary"><i class="bi bi-person-lines-fill"></i> User Details</h2>
                        <p class="text-muted mb-0">View account information and manage password</p>
                    </div>
                    <div>
                        <a href="users_roles.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Users</a>
                        <a href="add_user.php" class="btn btn-primary ms-2"><i class="bi bi-person-plus"></i> Add User</a>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?= $msgType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- User Profile Card -->
                <div class="col-lg-4">
                    <div class="user-card">
                        <div class="user-hero">
                            <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 2)) ?></div>
                            <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                            <div class="user-role">
                                <span class="role-badge role-<?= $user['role'] ?>">
                                    <i class="bi bi-<?= $user['role'] === 'guardian' ? 'people-fill' : ($user['role'] === 'teacher' ? 'person-badge-fill' : ($user['role'] === 'admin' ? 'shield-lock-fill' : 'person-fill')) ?>"></i>
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </div>
                            <div class="mt-3">
                                <span class="status-badge status-<?= $user['status'] ?>">
                                    <i class="bi bi-<?= $user['status'] === 'active' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-section">
                            <div class="section-title"><i class="bi bi-person-badge"></i> Account Summary</div>
                            <div class="info-row"><div class="info-label">Registration ID</div><div class="info-value"><?= htmlspecialchars($user['reg_id']) ?></div></div>
                            <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($user['email']) ?></div></div>
                            <div class="info-row"><div class="info-label">Role</div><div class="info-value"><?= ucfirst($user['role']) ?></div></div>
                            <div class="info-row"><div class="info-label">Status</div><div class="info-value"><?= ucfirst($user['status']) ?></div></div>
                            <div class="info-row"><div class="info-label">Created</div><div class="info-value"><?= date('d M, Y h:i A', strtotime($user['created_at'])) ?></div></div>
                            <div class="info-row"><div class="info-label">Last Updated</div><div class="info-value"><?= date('d M, Y h:i A', strtotime($user['updated_at'])) ?></div></div>
                        </div>
                    </div>
                </div>

                <!-- Details & Password -->
                <div class="col-lg-8">
                    <!-- Linked Record Info -->
                    <div class="user-card">
                        <div class="info-section">
                            <div class="section-title"><i class="bi bi-link-45deg"></i> Linked System Record</div>

                            <?php if ($user['role'] === 'student' && $linked_student): ?>
                                <div class="info-row"><div class="info-label">Student Name</div><div class="info-value"><?= htmlspecialchars($linked_student['first_name'] . ' ' . $linked_student['last_name']) ?></div></div>
                                <div class="info-row"><div class="info-label">Student Reg ID</div><div class="info-value"><?= htmlspecialchars($linked_student['reg_id']) ?></div></div>
                                <div class="info-row"><div class="info-label">Class</div><div class="info-value"><?= htmlspecialchars($linked_student['class_name'] ?? 'Not Assigned') ?> <?= $linked_student['section_name'] ? '- ' . htmlspecialchars($linked_student['section_name']) : '' ?></div></div>
                                <div class="info-row"><div class="info-label">Academic Year</div><div class="info-value"><?= htmlspecialchars($linked_student['year_name'] ?? 'Current') ?></div></div>
                                <div class="info-row"><div class="info-label">Father Name</div><div class="info-value"><?= htmlspecialchars($linked_student['father_name'] ?? 'N/A') ?></div></div>
                                <div class="info-row"><div class="info-label">Father Phone</div><div class="info-value"><?= htmlspecialchars($linked_student['father_phone'] ?? 'N/A') ?></div></div>
                            <?php elseif ($user['role'] === 'teacher' && $linked_teacher): ?>
                                <div class="info-row"><div class="info-label">Employee ID</div><div class="info-value"><?= htmlspecialchars($linked_teacher['employee_id'] ?? 'N/A') ?></div></div>
                                <div class="info-row"><div class="info-label">Qualification</div><div class="info-value"><?= htmlspecialchars($linked_teacher['qualification'] ?? 'N/A') ?></div></div>
                                <div class="info-row"><div class="info-label">Experience</div><div class="info-value"><?= (int)($linked_teacher['experience_years'] ?? 0) ?> year(s)</div></div>
                                <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?= htmlspecialchars($linked_teacher['phone'] ?? 'N/A') ?></div></div>
                                <div class="info-row"><div class="info-label">Joining Date</div><div class="info-value"><?= !empty($linked_teacher['joining_date']) ? date('d M, Y', strtotime($linked_teacher['joining_date'])) : 'N/A' ?></div></div>
                                <div class="info-row"><div class="info-label">Salary</div><div class="info-value">Rs. <?= number_format((float)($linked_teacher['salary'] ?? 0), 2) ?></div></div>
                            <?php elseif ($user['role'] === 'guardian' && !empty($linked_guardian)): ?>
                                <?php foreach ($linked_guardian as $child): ?>
                                    <div class="info-row"><div class="info-label">Child</div><div class="info-value"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?> (<?= htmlspecialchars($child['student_reg_id']) ?> - <?= htmlspecialchars($child['class_name'] ?? 'N/C') ?>)</div></div>
                                <?php endforeach; ?>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i> Admin account - no linked student/teacher/guardian record. This is a system administrator login.</div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i> No linked record found for this account.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Password Section -->
                    <div class="user-card">
                        <div class="info-section">
                            <div class="section-title"><i class="bi bi-key-fill"></i> Password Management</div>
                            <div class="alert alert-warning mb-3">
                                <i class="bi bi-shield-exclamation me-2"></i>
                                <strong>Note:</strong> Passwords are stored using one-way hashing ($2y$ bcrypt) and cannot be retrieved or displayed. Use the form below to <strong>reset</strong> the password to a new value.
                            </div>
                            <form method="POST" action="" id="resetPwdForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">New Password</label>
                                        <input type="password" class="form-control" name="new_password" id="new_password" placeholder="Enter new password" required>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required>
                                    </div>
                                </div>
                                <button type="submit" name="reset_password" class="btn btn-warning text-white">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div class="user-card">
                        <div class="info-section">
                            <div class="section-title"><i class="bi bi-shield-check"></i> Assigned Permissions</div>
                            <?php if (empty($permissions)): ?>
                                <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i> Using system default permissions for <?= ucfirst($user['role']) ?> role.</div>
                            <?php else: ?>
                                <?php foreach ($permissions as $perm): ?>
                                    <span class="perm-chip"><i class="bi bi-check-circle text-success me-1"></i><?= htmlspecialchars($perm) ?></span>
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
        document.getElementById('resetPwdForm').addEventListener('submit', function(e) {
            const pwd = document.getElementById('new_password').value;
            const conf = this.querySelector('input[name="confirm_password"]').value;
            if (pwd !== conf) { e.preventDefault(); alert('Passwords do not match!'); return false; }
            if (pwd.length < 6) { e.preventDefault(); alert('Password must be at least 6 characters!'); return false; }
        });
    </script>
</body>

</html>
