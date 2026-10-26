<?php
/**
 * Add User - Smart Choice Academy
 * Create login accounts for Parent (Guardian), Teacher, Student & Admin
 * Automatically assigns role-based default permissions.
 */

// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../index.php?error=unauthorized');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php?error=access_denied');
    exit();
}

require_once '../../backend/config.php';
require_once '../../backend/auth.php';

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_id      = trim($_POST['reg_id'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $full_name   = trim($_POST['full_name'] ?? '');
    $role        = $_POST['role'] ?? 'student';
    $status      = $_POST['status'] ?? 'active';
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
$link_student = (int)($_POST['link_student'] ?? 0); // optional guardian <-> student linking
    $link_teacher = (int)($_POST['link_teacher'] ?? 0); // optional teacher record linking

    // Validation
    if (empty($reg_id) || empty($email) || empty($full_name) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if reg_id or email already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE reg_id = ? OR email = ?");
            $checkStmt->execute([$reg_id, $email]);

            if ($checkStmt->fetch()) {
                $error = 'Registration ID or Email already exists.';
            } else {
                // Hash password
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                // Assign default permissions based on role
                $permissions = json_encode(getDefaultPermissionsForRole($role));

                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (reg_id, email, password, full_name, role, status, permissions)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$reg_id, $email, $hashed, $full_name, $role, $status, $permissions]);
                $new_user_id = $pdo->lastInsertId();

// If guardian selected, optionally link to a student via guardian_user_id
                if ($role === 'guardian' && $link_student > 0) {
                    $upd = $pdo->prepare("UPDATE students SET guardian_user_id = ? WHERE id = ?");
                    $upd->execute([$new_user_id, $link_student]);
                }

                // If teacher selected and a teacher record is chosen, link the new user
                if ($role === 'teacher' && $link_teacher > 0) {
                    $upd = $pdo->prepare("UPDATE teachers SET user_id = ? WHERE teacher_id = ?");
                    $upd->execute([$new_user_id, $link_teacher]);
                }

                $success = 'User account created successfully! Role: ' . ucfirst($role) . ' | Reg ID: ' . $reg_id;
                // Clear form
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch list of approved students for guardian linking
$studentsList = [];
try {
    $studentsList = $pdo->query("
        SELECT s.id, s.reg_id, s.first_name, s.last_name, c.class_name, s.guardian_user_id
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.status = 'approved'
        ORDER BY s.first_name
    ")->fetchAll();
} catch (PDOException $e) {
    $studentsList = [];
}

// Fetch teachers for potential linking (teacher role usually maps to teachers table via user_id)
$teachersList = [];
try {
$teachersList = $pdo->query("
        SELECT t.teacher_id, t.employee_id, t.user_id, u.full_name, u.email
        FROM teachers t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.employee_id
    ")->fetchAll();
} catch (PDOException $e) {
    $teachersList = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Smart Choice Academy</title>
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
        .form-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-label { font-weight: 600; margin-bottom: 8px; color: #333; }
        .form-control, .form-select { border-radius: 10px; padding: 10px 15px; border: 1px solid #ddd; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { border-color: #1a3a5c; box-shadow: 0 0 0 0.2rem rgba(26,58,92,0.25); }
        .btn-submit { background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%); border: none; padding: 12px 30px; border-radius: 10px; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(26,58,92,0.4); }
        .btn-reset { background: #6c757d; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 600; }
        .btn-reset:hover { background: #5a6268; }
        .required:after { content: " *"; color: red; }
        .password-hint { font-size: 12px; color: #6c757d; margin-top: 5px; }

        .role-card { cursor: pointer; border: 2px solid #e0e0e0; border-radius: 12px; padding: 18px; text-align: center; transition: all 0.2s; background: #fafafa; }
        .role-card:hover { border-color: #1a3a5c; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .role-card.active { border-color: #1a3a5c; background: #eef4fb; }
        .role-card i { font-size: 28px; margin-bottom: 8px; }
        .role-card .role-title { font-weight: 700; font-size: 14px; }
        .role-card .role-desc { font-size: 11px; color: #6c757d; }

        .role-student i { color: #4a6fa5; }
        .role-teacher i { color: #28a745; }
        .role-admin i   { color: #dc3545; }
        .role-guardian i{ color: #fd7e14; }

        .info-card { background: #f8f9fa; border-left: 4px solid #1a3a5c; border-radius: 8px; padding: 15px; margin-top: 20px; }
        .permission-preview { background: #f8f9fa; border-radius: 8px; padding: 12px; margin-top: 10px; }
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
                        <h2 class="mb-1 text-primary"><i class="bi bi-person-plus-fill"></i> Add New User</h2>
                        <p class="text-muted mb-0">Create login accounts for Parent, Teacher, Student &amp; Admin</p>
                    </div>
                    <a href="users_roles.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Users &amp; Roles</a>
                </div>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="addUserForm">
                    <!-- Role Selector -->
                    <div class="mb-4">
                        <label class="form-label">Select Account Type</label>
                        <div class="row g-3">
                            <div class="col-md-3 col-6">
                                <div class="role-card role-student <?= (!isset($_POST['role']) || $_POST['role']=='student') ? 'active' : '' ?>" data-role="student">
                                    <i class="bi bi-person-fill"></i>
                                    <div class="role-title">Student</div>
                                    <div class="role-desc">Student portal access</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="role-card role-teacher <?= (isset($_POST['role']) && $_POST['role']=='teacher') ? 'active' : '' ?>" data-role="teacher">
                                    <i class="bi bi-person-badge-fill"></i>
                                    <div class="role-title">Teacher</div>
                                    <div class="role-desc">Teacher portal access</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="role-card role-guardian <?= (isset($_POST['role']) && $_POST['role']=='guardian') ? 'active' : '' ?>" data-role="guardian">
                                    <i class="bi bi-people-fill"></i>
                                    <div class="role-title">Parent/Guardian</div>
                                    <div class="role-desc">Parent portal access</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="role-card role-admin <?= (isset($_POST['role']) && $_POST['role']=='admin') ? 'active' : '' ?>" data-role="admin">
                                    <i class="bi bi-shield-lock-fill"></i>
                                    <div class="role-title">Admin</div>
                                    <div class="role-desc">Admin portal access</div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role'] ?? 'student') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Registration ID</label>
                            <input type="text" class="form-control" name="reg_id" id="regId"
                                value="<?= htmlspecialchars($_POST['reg_id'] ?? '') ?>"
                                placeholder="e.g., STU2024001 / TCH-2026-001 / GDR-STU2024001" required>
                            <small class="text-muted">Used for login. Unique per user.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Email Address</label>
                            <input type="email" class="form-control" name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                placeholder="user@example.com" required>
                            <small class="text-muted">Also accepted as login identifier.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Full Name</label>
                            <input type="text" class="form-control" name="full_name"
                                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                placeholder="Enter full name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <small class="text-muted">Inactive/suspended users cannot log in.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Password</label>
                            <input type="password" class="form-control" name="password" id="password"
                                placeholder="Enter password" required>
                            <div class="password-hint"><i class="bi bi-info-circle"></i> Minimum 6 characters</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password"
                                placeholder="Confirm password" required>
                        </div>
                    </div>

                    <!-- Guardian: optional link to student -->
                    <div class="row" id="linkStudentRow" style="display:none;">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Link to Student (Optional)</label>
                            <select class="form-select" name="link_student">
                                <option value="0">-- No Student --</option>
                                <?php foreach ($studentsList as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= !empty($s['guardian_user_id']) ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?> (<?= htmlspecialchars($s['reg_id']) ?> - <?= htmlspecialchars($s['class_name'] ?? 'N/C') ?>)
                                        <?= !empty($s['guardian_user_id']) ? ' [Linked]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Links this guardian account to the selected student's parent portal.</small>
                        </div>
                    </div>

                    <!-- Teacher: optional link to existing teacher record -->
                    <div class="row" id="linkTeacherRow" style="display:none;">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Link to Teacher Record (Optional)</label>
                            <select class="form-select" name="link_teacher">
                                <option value="0">-- No Teacher Record --</option>
                                <?php foreach ($teachersList as $t): ?>
                                    <option value="<?= $t['teacher_id'] ?>" <?= !empty($t['user_id']) ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($t['employee_id'] . ' - ' . ($t['full_name'] ?? 'Unlinked User')) ?>
                                        <?= !empty($t['user_id']) ? ' [Linked]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Links this login to an existing teachers table record.</small>
                        </div>
                    </div>

                    <!-- Permission Preview -->
                    <div class="permission-preview" id="permPreview">
                        <strong class="small"><i class="bi bi-key"></i> Default permissions for this role:</strong>
                        <div id="permChips" class="mt-2"></div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-submit text-white"><i class="bi bi-save"></i> Create User</button>
                        <button type="reset" class="btn btn-reset text-white ms-2"><i class="bi bi-arrow-repeat"></i> Reset</button>
                    </div>
                </form>
            </div>

            <!-- Quick Info Cards -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm"><div class="card-body text-center">
                        <i class="bi bi-people-fill text-primary" style="font-size:32px;"></i>
                        <h3 class="mt-2 mb-0"><?= $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?></h3>
                        <p class="text-muted mb-0">Total Users</p>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm"><div class="card-body text-center">
                        <i class="bi bi-person-fill text-primary" style="font-size:32px;"></i>
                        <h3 class="mt-2 mb-0"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(); ?></h3>
                        <p class="text-muted mb-0">Students</p>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm"><div class="card-body text-center">
                        <i class="bi bi-person-badge-fill text-success" style="font-size:32px;"></i>
                        <h3 class="mt-2 mb-0"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn(); ?></h3>
                        <p class="text-muted mb-0">Teachers</p>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm"><div class="card-body text-center">
                        <i class="bi bi-people-fill text-warning" style="font-size:32px;"></i>
                        <h3 class="mt-2 mb-0"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='guardian'")->fetchColumn(); ?></h3>
                        <p class="text-muted mb-0">Guardians</p>
                    </div></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role cards
        const roleCards = document.querySelectorAll('.role-card');
        const roleInput = document.getElementById('roleInput');

        roleCards.forEach(card => {
            card.addEventListener('click', function() {
                roleCards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                roleInput.value = this.dataset.role;
                updateRoleUI(this.dataset.role);
            });
        });

        // Permission preview data
        const rolePerms = {
            student: ['courses_view', 'attendance_view', 'marks_view', 'fee_view'],
            teacher: ['students_view', 'exams_view', 'marks_entry', 'marks_view', 'courses_view', 'attendance_view', 'attendance_mark', 'timetable_view'],
            guardian: ['students_view', 'fee_view', 'attendance_view', 'marks_view'],
            admin: ['students_view','students_add','students_edit','students_delete','students_approve','teachers_view','fee_view','fee_collect','exams_manage','marks_entry','attendance_mark','timetable_manage','reports_generate','users_manage','settings_edit']
        };

        function updateRoleUI(role) {
            // Show/hide link rows
            document.getElementById('linkStudentRow').style.display = role === 'guardian' ? '' : 'none';
            document.getElementById('linkTeacherRow').style.display = role === 'teacher' ? '' : 'none';

            // Update reg id placeholder
            const regId = document.getElementById('regId');
            const prefixes = { student: 'STU', teacher: 'TCH', guardian: 'GDR', admin: 'ADM' };
            const year = new Date().getFullYear();
            if (!regId.value) regId.placeholder = prefixes[role] + year + '0001';

            // Update permission chips
            const chips = document.getElementById('permChips');
            chips.innerHTML = '';
            (rolePerms[role] || []).forEach(p => {
                const span = document.createElement('span');
                span.className = 'perm-chip';
                span.textContent = p;
                chips.appendChild(span);
            });
        }

        // Init UI
        updateRoleUI(roleInput.value);

        // Form validation
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const pass = document.querySelector('input[name="password"]').value;
            const conf = document.querySelector('input[name="confirm_password"]').value;
            if (pass !== conf) { e.preventDefault(); alert('Passwords do not match!'); return false; }
            if (pass.length < 6) { e.preventDefault(); alert('Password must be at least 6 characters!'); return false; }
        });
    </script>
</body>

</html>
