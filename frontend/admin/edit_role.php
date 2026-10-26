<?php
// Start output buffering
ob_start();

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../backend/config.php';

$db = $pdo;
$roleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch role details
$role = null;
if ($roleId > 0) {
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
}

if (!$role) {
    header('Location: users_roles.php?error=role_not_found');
    exit();
}

// Define all available permissions
$allPermissions = [
    // Student Management
    ['key' => 'students_view', 'category' => 'Students', 'label' => 'View Students', 'description' => 'View list of all students'],
    ['key' => 'students_add', 'category' => 'Students', 'label' => 'Add Students', 'description' => 'Add new students to the system'],
    ['key' => 'students_edit', 'category' => 'Students', 'label' => 'Edit Students', 'description' => 'Edit existing student information'],
    ['key' => 'students_delete', 'category' => 'Students', 'label' => 'Delete Students', 'description' => 'Remove students from the system'],
    ['key' => 'students_approve', 'category' => 'Students', 'label' => 'Approve Students', 'description' => 'Approve student registrations'],

    // Teacher Management
    ['key' => 'teachers_view', 'category' => 'Teachers', 'label' => 'View Teachers', 'description' => 'View list of all teachers'],
    ['key' => 'teachers_add', 'category' => 'Teachers', 'label' => 'Add Teachers', 'description' => 'Add new teachers to the system'],
    ['key' => 'teachers_edit', 'category' => 'Teachers', 'label' => 'Edit Teachers', 'description' => 'Edit existing teacher information'],
    ['key' => 'teachers_delete', 'category' => 'Teachers', 'label' => 'Delete Teachers', 'description' => 'Remove teachers from the system'],

    // Guardian Management
    ['key' => 'guardians_view', 'category' => 'Guardians', 'label' => 'View Guardians', 'description' => 'View list of all guardians'],
    ['key' => 'guardians_add', 'category' => 'Guardians', 'label' => 'Add Guardians', 'description' => 'Add new guardians to the system'],
    ['key' => 'guardians_edit', 'category' => 'Guardians', 'label' => 'Edit Guardians', 'description' => 'Edit existing guardian information'],
    ['key' => 'guardians_delete', 'category' => 'Guardians', 'label' => 'Delete Guardians', 'description' => 'Remove guardians from the system'],

    // Fee Management
    ['key' => 'fee_view', 'category' => 'Fee Management', 'label' => 'View Fees', 'description' => 'View fee structures and collections'],
    ['key' => 'fee_add', 'category' => 'Fee Management', 'label' => 'Add Fees', 'description' => 'Create new fee structures'],
    ['key' => 'fee_edit', 'category' => 'Fee Management', 'label' => 'Edit Fees', 'description' => 'Modify existing fee structures'],
    ['key' => 'fee_delete', 'category' => 'Fee Management', 'label' => 'Delete Fees', 'description' => 'Remove fee structures'],
    ['key' => 'fee_collect', 'category' => 'Fee Management', 'label' => 'Collect Fees', 'description' => 'Process fee collections'],

    // Exam Management
    ['key' => 'exams_view', 'category' => 'Examinations', 'label' => 'View Exams', 'description' => 'View exam schedules and results'],
    ['key' => 'exams_manage', 'category' => 'Examinations', 'label' => 'Manage Exams', 'description' => 'Create and edit exam schedules'],
    ['key' => 'marks_entry', 'category' => 'Examinations', 'label' => 'Marks Entry', 'description' => 'Enter and edit student marks'],
    ['key' => 'marks_view', 'category' => 'Examinations', 'label' => 'View Marks', 'description' => 'View student marks and results'],

    // Attendance Management
    ['key' => 'attendance_view', 'category' => 'Attendance', 'label' => 'View Attendance', 'description' => 'View attendance records'],
    ['key' => 'attendance_mark', 'category' => 'Attendance', 'label' => 'Mark Attendance', 'description' => 'Mark student attendance'],
    ['key' => 'attendance_report', 'category' => 'Attendance', 'label' => 'Attendance Reports', 'description' => 'Generate attendance reports'],

    // Course Management
    ['key' => 'courses_view', 'category' => 'Courses', 'label' => 'View Courses', 'description' => 'View course list'],
    ['key' => 'courses_add', 'category' => 'Courses', 'label' => 'Add Courses', 'description' => 'Create new courses'],
    ['key' => 'courses_edit', 'category' => 'Courses', 'label' => 'Edit Courses', 'description' => 'Modify course details'],
    ['key' => 'courses_delete', 'category' => 'Courses', 'label' => 'Delete Courses', 'description' => 'Remove courses'],

    // Session Management
    ['key' => 'sessions_view', 'category' => 'Academic Sessions', 'label' => 'View Sessions', 'description' => 'View academic sessions'],
    ['key' => 'sessions_manage', 'category' => 'Academic Sessions', 'label' => 'Manage Sessions', 'description' => 'Create and edit sessions'],

    // Timetable Management
    ['key' => 'timetable_view', 'category' => 'Timetable', 'label' => 'View Timetable', 'description' => 'View class timetables'],
    ['key' => 'timetable_manage', 'category' => 'Timetable', 'label' => 'Manage Timetable', 'description' => 'Create and edit timetables'],

    // Reports
    ['key' => 'reports_view', 'category' => 'Reports', 'label' => 'View Reports', 'description' => 'Access all reports'],
    ['key' => 'reports_generate', 'category' => 'Reports', 'label' => 'Generate Reports', 'description' => 'Generate various reports'],

    // System Settings
    ['key' => 'settings_view', 'category' => 'Settings', 'label' => 'View Settings', 'description' => 'View system settings'],
    ['key' => 'settings_edit', 'category' => 'Settings', 'label' => 'Edit Settings', 'description' => 'Modify system settings'],
    ['key' => 'users_view', 'category' => 'Users', 'label' => 'View Users', 'description' => 'View system users'],
    ['key' => 'users_manage', 'category' => 'Users', 'label' => 'Manage Users', 'description' => 'Create and edit users'],
];

// Group permissions by category
$permissionsByCategory = [];
foreach ($allPermissions as $permission) {
    $category = $permission['category'];
    if (!isset($permissionsByCategory[$category])) {
        $permissionsByCategory[$category] = [];
    }
    $permissionsByCategory[$category][] = $permission;
}

// Handle form submission to update permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $selectedPermissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    // Update role permissions (assuming you have a role_permissions table)
    try {
        // First, delete existing permissions for this role
        $deleteStmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $deleteStmt->execute([$roleId]);

        // Insert new permissions
        $insertStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_key) VALUES (?, ?)");
        foreach ($selectedPermissions as $permission) {
            $insertStmt->execute([$roleId, $permission]);
        }

        header('Location: edit_role.php?id=' . $roleId . '&success=1');
        exit();
    } catch (PDOException $e) {
        $error = "Failed to update permissions: " . $e->getMessage();
    }
}

// Get current permissions for this role
$currentPermissions = [];
try {
    $stmt = $db->prepare("SELECT permission_key FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $currentPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Table might not exist yet
    $currentPermissions = [];
}

// Handle role name update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role_name'])) {
    $newRoleName = trim($_POST['role_name']);
    if (!empty($newRoleName) && $newRoleName != $role['role_name']) {
        $updateStmt = $db->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
        $updateStmt->execute([$newRoleName, $roleId]);
        $role['role_name'] = $newRoleName;
        $success = "Role name updated successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Role Permissions | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        .permission-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .permission-header {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 16px;
        }

        .permission-header i {
            margin-right: 10px;
        }

        .permission-body {
            padding: 20px;
        }

        .permission-item {
            display: inline-block;
            width: calc(33.33% - 15px);
            margin: 0 8px 12px 0;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4a6fa5;
            transition: all 0.2s;
        }

        .permission-item:hover {
            background: #e9ecef;
            transform: translateX(3px);
        }

        .permission-item .form-check {
            margin-bottom: 0;
        }

        .permission-item .form-check-label {
            font-weight: 500;
            cursor: pointer;
        }

        .permission-item .form-check-input {
            cursor: pointer;
            margin-right: 10px;
        }

        .permission-description {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
            margin-left: 28px;
        }

        .role-info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            padding: 20px;
        }

        .btn-update {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            border: none;
            padding: 12px 30px;
        }

        .btn-update:hover {
            background: linear-gradient(135deg, #234a6e 0%, #123550 100%);
        }

        .select-all-btn {
            margin-bottom: 15px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: inline-block;
            cursor: pointer;
        }

        .select-all-btn:hover {
            background: #e9ecef;
        }

        @media (max-width: 992px) {
            .permission-item {
                width: calc(50% - 15px);
            }
        }

        @media (max-width: 768px) {
            .permission-item {
                width: 100%;
                margin-right: 0;
            }
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="users_roles.php">Users & Roles</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Role: <?= htmlspecialchars($role['role_name']) ?></li>
                </ol>
            </nav>

            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> Permissions updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Role Info Card -->
            <div class="role-info-card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-1 text-primary">
                            <i class="bi bi-shield-lock-fill"></i>
                            Role: <span class="badge bg-primary"><?= htmlspecialchars($role['role_name']) ?></span>
                        </h4>
                        <p class="text-muted small mt-2 mb-0">
                            <i class="bi bi-info-circle"></i> Configure what users with this role can access
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <button type="button" class="btn btn-outline-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#editRoleNameModal">
                            <i class="bi bi-pencil"></i> Edit Role Name
                        </button>
                        <a href="users_roles.php" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Roles
                        </a>
                    </div>
                </div>
            </div>

            <!-- Permissions Form -->
            <form action="" method="POST">
                <div class="permission-card">
                    <div class="permission-header">
                        <i class="bi bi-key-fill"></i> Role Permissions
                        <div class="float-end">
                            <button type="button" class="btn btn-sm btn-light" onclick="selectAll()">
                                <i class="bi bi-check-all"></i> Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-light ms-2" onclick="deselectAll()">
                                <i class="bi bi-x-circle"></i> Deselect All
                            </button>
                        </div>
                    </div>
                    <div class="permission-body">
                        <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                            <div class="mb-4">
                                <h6 class="mb-3 text-secondary">
                                    <i class="bi bi-folder"></i> <?= htmlspecialchars($category) ?>
                                </h6>
                                <div class="row">
                                    <?php foreach ($permissions as $permission): ?>
                                        <div class="col-md-4 col-lg-3 mb-2">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="permissions[]"
                                                        value="<?= $permission['key'] ?>"
                                                        id="perm_<?= $permission['key'] ?>"
                                                        <?= in_array($permission['key'], $currentPermissions) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="perm_<?= $permission['key'] ?>">
                                                        <?= htmlspecialchars($permission['label']) ?>
                                                    </label>
                                                    <div class="permission-description">
                                                        <small><?= htmlspecialchars($permission['description']) ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php if ($category != array_key_last($permissionsByCategory)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" name="update_permissions" class="btn btn-primary btn-update">
                        <i class="bi bi-save"></i> Save Permissions
                    </button>
                </div>
            </form>

            <!-- Database Setup Information -->
            <div class="alert alert-info mt-4 small">
                <i class="bi bi-database"></i> <strong>Note:</strong>
                The following tables are required for full permission management:
                <ul class="mb-0 mt-2">
                    <li><code>roles</code> - Stores role names</li>
                    <li><code>role_permissions</code> - Stores role-permission relationships (permission_key, role_id)</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Edit Role Name Modal -->
    <div class="modal fade" id="editRoleNameModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Role Name</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Role Name</label>
                            <input type="text" name="role_name" class="form-control" value="<?= htmlspecialchars($role['role_name']) ?>" required>
                            <div class="form-text">Enter a descriptive name for this role.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_role_name" class="btn btn-primary">Update Role Name</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAll() {
            document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function deselectAll() {
            document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        }
    </script>
</body>

</html>
<?php
ob_end_flush();
?>