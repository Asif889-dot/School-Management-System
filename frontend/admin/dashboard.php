<?php
// Admin Dashboard - Fully synchronized with sms_db structural mapping
require_once '../../backend/auth.php';
requireLogin();
requireRole('admin');

// Database configuration link
require_once '../../backend/config.php'; 

try {
    // 1. DYNAMIC METRIC CARDS CALCULATION
    
    // Total Approved Students
    $studentCountQuery = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'approved'");
    $totalStudents = $studentCountQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Active Academic Teachers
    $teacherCountQuery = $pdo->query("SELECT COUNT(*) as total FROM teachers WHERE status = 'active'");
    $totalTeachers = $teacherCountQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Live Financial Realization Metrics (Sum of total successful fee payments)
    $collectionQuery = $pdo->query("SELECT SUM(amount_paid) as total_collected FROM fee_payments WHERE status = 'paid'");
    $totalCollected = $collectionQuery->fetch(PDO::FETCH_ASSOC)['total_collected'] ?? 0.00;
    
    // Total New Pending Admissions Awaiting Review Matrix
    $pendingQuery = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'pending'");
    $pendingAdmissions = $pendingQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. FETCH CLASSES FOR THE DROPDOWN MANAGEMENT FILTER
    $classesQuery = $pdo->query("SELECT id, class_name FROM classes ORDER BY numeric_grade ASC");
    $classes = $classesQuery->fetchAll(PDO::FETCH_ASSOC);

    // 3. FETCH RECENT STUDENTS USING CONCATENATED FIRST & LAST NAME FIELDS
    $recentStudentsQuery = $pdo->query("
        SELECT s.reg_id, s.first_name, s.last_name, c.class_name, s.father_name, s.status, s.id
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        ORDER BY s.id DESC 
        LIMIT 5
    ");
    $recentStudents = $recentStudentsQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Graceful error capture logging for systems engineering validation
    error_log("Dashboard Runtime Exception: " . $e->getMessage());
    die("Critical Error: Core accounting metadata streams failed to run safely. Details: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Smart Choice Academy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div class="group-dropdown">
                <div class="dropdown">
                    <button class="btn dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-building"></i>
                        Select Group/Class
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="admin_dashboard.php">All Groups / Classes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php foreach ($classes as $class): ?>
                            <li>
                                <a class="dropdown-item" href="admin_dashboard.php?class_id=<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../classes/add_class.php">+ Add New Group</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="user-menu">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?></div>
                    <small class="text-muted">Administrator</small>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'AD', 0, 2)); ?>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <h4 class="mb-4">Dashboard Overview</h4>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalStudents); ?></div>
                    <div class="stat-label">Approved Students</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalTeachers); ?></div>
                    <div class="stat-label">Active Teachers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div class="stat-value">Rs. <?php echo number_format($totalCollected, 2); ?></div>
                    <div class="stat-label">Total Fees Realized</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($pendingAdmissions); ?></div>
                    <div class="stat-label">Pending Admissions</div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">Recent Student Admission Pipeline</h5>
                    <a href="admission.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Add New Student
                    </a>
                </div>
                <div class="card-body p-0">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reg. ID</th>
                                <th>Student Name</th>
                                <th>Class Designation</th>
                                <th>Guardian / Father</th>
                                <th>Status Badge</th>
                                <th>Action Panel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentStudents) > 0): ?>
                                <?php foreach ($recentStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['reg_id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['class_name'] ?? 'Not Formatted'); ?></td>
                                        <td><?php echo htmlspecialchars($student['father_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = 'inactive';
                                                if ($student['status'] === 'approved') $statusClass = 'active';
                                                if ($student['status'] === 'pending') $statusClass = 'orange'; // Fallback color rule for styling
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../students/view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-light action-btn"><i class="bi bi-eye"></i></a>
                                            <a href="../students/edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-light action-btn"><i class="bi bi-pencil"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center p-4 text-muted">No student data records discovered inside this database instance.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
</body>

</html>