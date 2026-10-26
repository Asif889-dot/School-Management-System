<?php
// Students List - Requires login
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Handle actions
$action = $_GET['action'] ?? '';
$studentId = $_GET['id'] ?? '';

// Delete student
if ($action === 'delete' && $studentId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        header('Location: students.php?message=deleted');
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting student: " . $e->getMessage();
    }
}

// Fetch classes and sections for filtering
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_grade ASC")->fetchAll();
$sections = $pdo->query("SELECT s.*, c.class_name FROM sections s LEFT JOIN classes c ON s.class_id = c.id ORDER BY c.numeric_grade, s.section_name")->fetchAll();

// Handle search and filter
$search = $_GET['search'] ?? '';
$filterClass = $_GET['class_id'] ?? '';
$filterSection = $_GET['section_id'] ?? '';

// Build query based on filters
$query = "
    SELECT s.*, 
           c.class_name, 
           sct.section_name,
           ay.year_name as academic_year
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sct ON s.section_id = sct.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.reg_id LIKE ? OR s.father_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filterClass) {
    $query .= " AND s.class_id = ?";
    $params[] = $filterClass;
}

if ($filterSection) {
    $query .= " AND s.section_id = ?";
    $params[] = $filterSection;
}

$query .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// If no results, set empty array
if (!$students) {
    $students = [];
}

// Get message
$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Students | SMS</title>
    
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
        .student-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .student-photo-placeholder {
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
        .badge-pending {
            background: rgba(249, 115, 22, 0.1);
            color: #f97316;
        }
        .badge-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        .badge-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
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
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>All Students</h5>
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
                Student deleted successfully!
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
                    <div class="stat-value"><?php echo count($students); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo count(array_filter($students, function($s) { return $s['status'] === 'approved'; })); ?>
                    </div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo count(array_filter($students, function($s) { return $s['status'] === 'pending'; })); ?>
                    </div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="stat-value"><?php echo count($classes); ?></div>
                    <div class="stat-label">Classes</div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="content-card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Search</label>
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" name="search" placeholder="Search by name, reg no, father..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Filter by Class</label>
                            <select class="form-select" name="class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $filterClass == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Filter by Section</label>
                            <select class="form-select" name="section_id">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>" <?php echo $filterSection == $section['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section['section_name'] . ' - ' . $section['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                                <a href="students.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Students Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">Students List</h5>
                    <a href="admission.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Add Student
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Reg. No</th>
                                    <th>Student Name</th>
                                    <th>Class / Section</th>
                                    <th>Academic Year</th>
                                    <th>Father Name</th>
                                    <th>Gender</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                        No students found
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($student['photo'])): ?>
                                                <img src="../../<?php echo htmlspecialchars($student['photo']); ?>" alt="Photo" class="student-photo">
                                            <?php else: ?>
                                                <div class="student-photo-placeholder">
                                                    <?php echo strtoupper(substr($student['first_name'] ?? 'X', 0, 1) . substr($student['last_name'] ?? 'X', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($student['reg_id'] ?? ''); ?></strong></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['id_proof'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($student['class_name'])): ?>
                                                <?php echo htmlspecialchars($student['class_name']); ?>
                                                <?php if (!empty($student['section_name'])): ?>
                                                    - <?php echo htmlspecialchars($student['section_name']); ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['academic_year'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['father_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($student['gender'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $student['status'] ?? 'pending'; ?>">
                                                <?php echo ucfirst($student['status'] ?? 'pending'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-light action-btn" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-light action-btn" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="students.php?action=delete&id=<?php echo $student['id']; ?>" class="btn btn-sm btn-light action-btn text-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this student?');">
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
