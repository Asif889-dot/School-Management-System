<?php
/**
 * Users & Roles - Smart Choice Academy
 * Lists all system login accounts (Admin, Teacher, Parent/Guardian, Student)
 * with "View Details" links and role filtering.
 */

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

// Filters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}
if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $query .= " AND (reg_id LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Counts
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$totalGuardians = $db->query("SELECT COUNT(*) FROM users WHERE role='guardian'")->fetchColumn();
$totalAdmins = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users &amp; Roles - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }

        .page-header { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; }
        .table-card th { background: #f8f9fa; padding: 12px 15px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .table-card td { padding: 12px 15px; font-size: 13px; vertical-align: middle; }

        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; color: white; }
        .role-student { background: #4a6fa5; }
        .role-teacher { background: #28a745; }
        .role-admin { background: #dc3545; }
        .role-guardian { background: #fd7e14; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #72281c; }
        .status-suspended { background: #ffeeba; color: #856404; }

        .avatar-circle { width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: white; }
        .avatar-student { background: #4a6fa5; }
        .avatar-teacher { background: #28a745; }
        .avatar-admin { background: #dc3545; }
        .avatar-guardian { background: #fd7e14; }

        .stat-card { background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid; }
        .stat-number { font-size: 26px; font-weight: 800; }
        .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; }
        .empty-state { text-align: center; padding: 50px; color: #6c757d; }
        .empty-state i { font-size: 60px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="mb-1 text-primary"><i class="bi bi-people-fill"></i> Users &amp; Roles</h2>
                        <p class="text-muted mb-0">Manage all login accounts (Admin, Teacher, Parent, Student)</p>
                    </div>
                    <a href="add_user.php" class="btn btn-primary"><i class="bi bi-person-plus-fill"></i> Add New User</a>
                </div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> The requested user was not found.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-2 col-6 mb-2"><div class="stat-card" style="border-left-color:#1a3a5c;"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">Total Users</div></div></div>
                <div class="col-md-2 col-6 mb-2"><div class="stat-card" style="border-left-color:#4a6fa5;"><div class="stat-number"><?= $totalStudents ?></div><div class="stat-label">Students</div></div></div>
                <div class="col-md-2 col-6 mb-2"><div class="stat-card" style="border-left-color:#28a745;"><div class="stat-number"><?= $totalTeachers ?></div><div class="stat-label">Teachers</div></div></div>
                <div class="col-md-2 col-6 mb-2"><div class="stat-card" style="border-left-color:#fd7e14;"><div class="stat-number"><?= $totalGuardians ?></div><div class="stat-label">Parent/Guardians</div></div></div>
                <div class="col-md-2 col-6 mb-2"><div class="stat-card" style="border-left-color:#dc3545;"><div class="stat-number"><?= $totalAdmins ?></div><div class="stat-label">Admins</div></div></div>
                <div class="col-md-2 col-6 mb-2"><div class="stat-card" style="border-left-color:#6c757d;"><div class="stat-number"><?= count($users) ?></div><div class="stat-label">Showing</div></div></div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Role</label>
                        <select name="role" class="form-select" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="teacher" <?= $role_filter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                            <option value="guardian" <?= $role_filter === 'guardian' ? 'selected' : '' ?>>Parent/Guardian</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, Reg ID, or Email" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
                        <?php if ($role_filter || $status_filter || $search): ?>
                            <a href="users_roles.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-repeat"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Registration ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="bi bi-person-x"></i>
                                        <h5>No users found</h5>
                                        <p class="mb-0">Try adjusting your filters or create a new user account.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                        $role = $user['role'];
                                        $avatarClass = "avatar-$role";
                                        $initials = strtoupper(substr($user['full_name'], 0, 2));
                                        $roleLabel = ucfirst($role);
                                        if ($role === 'guardian') $roleLabel = 'Parent';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar-circle <?= $avatarClass ?>"><?= $initials ?></span>
                                                <div>
                                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><code><?= htmlspecialchars($user['reg_id']) ?></code></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><span class="role-badge role-<?= $role ?>"><?= $roleLabel ?></span></td>
                                        <td><span class="status-badge status-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span></td>
                                        <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                                        <td class="text-center">
                                            <a href="user_details.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i> View
                                            </a>
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#usersTable tbody tr td.empty-state').length === 0) {
                $('#usersTable').DataTable({
                    pageLength: 10,
                    lengthChange: true,
                    ordering: true,
                    order: [[5, 'desc']],
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ users",
                        info: "Showing _START_ to _END_ of _TOTAL_ users",
                        infoEmpty: "No users found",
                        zeroRecords: "No matching users found"
                    },
                    columnDefs: [{ orderable: false, targets: [0, 6] }]
                });
            }
        });
    </script>
</body>

</html>
