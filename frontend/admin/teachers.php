<?php
// Teachers List - Requires login
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Handle actions
$action = $_GET['action'] ?? '';
$teacherId = $_GET['id'] ?? '';

// Delete teacher
if ($action === 'delete' && $teacherId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE teacher_id = ?");
        $stmt->execute([$teacherId]);
        header('Location: teachers.php?message=deleted');
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting teacher: " . $e->getMessage();
    }
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build query based on filters
$query = "
    SELECT t.*, u.full_name, u.email, u.status as user_status
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (t.employee_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR t.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filterStatus) {
    $query .= " AND t.status = ?";
    $params[] = $filterStatus;
}

$query .= " ORDER BY t.teacher_id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// If no results, set empty array
if (!$teachers) {
    $teachers = [];
}

// Get message
$message = $_GET['message'] ?? '';

// Get success/error messages from session
$successMsg = $_SESSION['success'] ?? '';
$sessionError = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Teachers | SMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    
    <style>
        .search-box {
            position: relative;
            max-width: 300px;
        }
        .search-box input {
            padding-left: 40px;
            border-radius: 8px;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        .teacher-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .teacher-photo-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        .badge-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        .badge-inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        .action-btn {
            padding: 4px 8px;
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../../backend/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="group-dropdown">
                <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>All Teachers</h5>
            </div>
            
            <div class="user-menu">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></div>
                    <small class="text-muted">Administrator</small>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'AD', 0, 2)); ?>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="dashboard-content">
            <!-- Alert Messages -->
            <?php if ($message === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Teacher deleted successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($successMsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($sessionError)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($sessionError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value"><?php echo count($teachers); ?></div>
                    <div class="stat-label">Total Teachers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo count(array_filter($teachers, function($t) { return $t['status'] === 'active'; })); ?>
                    </div>
                    <div class="stat-label">Active</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo count(array_filter($teachers, function($t) { return $t['status'] === 'inactive'; })); ?>
                    </div>
                    <div class="stat-label">Inactive</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo count(array_filter($teachers, function($t) { return $t['experience_years'] > 0; })); ?>
                    </div>
                    <div class="stat-label">Experienced</div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="content-card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Search</label>
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" name="search" placeholder="Search by name, employee ID, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Filter by Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                                <a href="teachers.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Teachers Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">Teachers List</h5>
                    <a href="add_teacher.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Add Teacher
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Qualification</th>
                                    <th>Experience</th>
                                    <th>Joining Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teachers)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                        No teachers found
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($teacher['photo'])): ?>
                                                <img src="../../<?php echo htmlspecialchars($teacher['photo']); ?>" alt="Photo" class="teacher-photo">
                                            <?php else: ?>
                                                <div class="teacher-photo-placeholder">
                                                    <?php echo strtoupper(substr($teacher['full_name'] ?? 'TC', 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($teacher['employee_id'] ?? ''); ?></strong></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($teacher['full_name'] ?? 'N/A'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($teacher['gender'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['phone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['qualification'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($teacher['experience_years'] > 0): ?>
                                                <?php echo htmlspecialchars($teacher['experience_years']); ?> years
                                            <?php else: ?>
                                                <span class="text-muted">Fresher</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($teacher['joining_date'])): ?>
                                                <?php echo date('d M Y', strtotime($teacher['joining_date'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $teacher['status'] ?? 'inactive'; ?>">
                                                <?php echo ucfirst($teacher['status'] ?? 'inactive'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view_teacher.php?id=<?php echo $teacher['teacher_id']; ?>" class="btn btn-sm btn-light action-btn" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_teacher.php?id=<?php echo $teacher['teacher_id']; ?>" class="btn btn-sm btn-light action-btn" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="teachers.php?action=delete&id=<?php echo $teacher['teacher_id']; ?>" class="btn btn-sm btn-light action-btn text-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this teacher?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
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
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../../assets/js/admin.js"></script>
</body>

</html>
