<?php
// Manage Session/Class/Section - Requires login
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Handle form submissions
$message = '';
$messageType = '';

// Add Academic Year
if (isset($_POST['add_academic_year'])) {
    $year_name = trim($_POST['year_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_current = $_POST['is_current'] ?? 'no';
    $status = $_POST['status'] ?? 'active';
    
    // If setting as current, unset other current years first
    if ($is_current === 'yes') {
        $stmt = $pdo->prepare("UPDATE academic_years SET is_current = 'no' WHERE is_current = 'yes'");
        $stmt->execute();
    }
    
    $stmt = $pdo->prepare("INSERT INTO academic_years (year_name, start_date, end_date, is_current, status) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$year_name, $start_date, $end_date, $is_current, $status])) {
        $message = 'Academic year added successfully!';
        $messageType = 'success';
    } else {
        $message = 'Error adding academic year.';
        $messageType = 'danger';
    }
}

// Add Class
if (isset($_POST['add_class'])) {
    $class_name = trim($_POST['class_name']);
    $numeric_grade = (int)$_POST['numeric_grade'];
    $capacity = (int)$_POST['capacity'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO classes (class_name, numeric_grade, capacity) VALUES (?, ?, ?)");
        if ($stmt->execute([$class_name, $numeric_grade, $capacity])) {
            $message = 'Class added successfully!';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'unique_grade') !== false) {
            $message = 'A class with this grade level already exists.';
            $messageType = 'danger';
        } else {
            $message = 'Error adding class.';
            $messageType = 'danger';
        }
    }
}

// Add Section
if (isset($_POST['add_section'])) {
    $section_name = trim($_POST['section_name']);
    $class_id = (int)$_POST['class_id'];
    $capacity = (int)$_POST['section_capacity'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO sections (section_name, class_id, capacity) VALUES (?, ?, ?)");
        if ($stmt->execute([$section_name, $class_id, $capacity])) {
            $message = 'Section added successfully!';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'unique_section_class') !== false) {
            $message = 'A section with this name already exists for the selected class.';
            $messageType = 'danger';
        } else {
            $message = 'Error adding section.';
            $messageType = 'danger';
        }
    }
}

// Delete Academic Year
if (isset($_POST['delete_academic_year'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM academic_years WHERE id = ?");
    if ($stmt->execute([$id])) {
        $message = 'Academic year deleted successfully!';
        $messageType = 'success';
    }
}

// Delete Class
if (isset($_POST['delete_class'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    if ($stmt->execute([$id])) {
        $message = 'Class deleted successfully!';
        $messageType = 'success';
    }
}

// Delete Section
if (isset($_POST['delete_section'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
    if ($stmt->execute([$id])) {
        $message = 'Section deleted successfully!';
        $messageType = 'success';
    }
}

// Fetch data for display
$academicYears = $pdo->query("SELECT * FROM academic_years ORDER BY year_name DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_grade ASC")->fetchAll();
$sections = $pdo->query("SELECT s.*, c.class_name FROM sections s LEFT JOIN classes c ON s.class_id = c.id ORDER BY c.numeric_grade, s.section_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sessions | SMS</title>
    
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
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .nav-tabs .nav-link {
            color: #6b7280;
            border: none;
            padding: 12px 20px;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #4f46e5;
            background: transparent;
            border-bottom: 2px solid #4f46e5;
        }
        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: #4f46e5;
        }
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
        .form-card h5 {
            color: #1f2937;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
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
                <div class="dropdown">
                    <button class="btn dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-building"></i>
                        Manage Sessions
                    </button>
                </div>
            </div>
            
            <div class="user-menu">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <small class="text-muted">Administrator</small>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="dashboard-content">
            <!-- Message Alert -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="manageTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button">
                        <i class="bi bi-calendar-range me-2"></i>Academic Years
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="class-tab" data-bs-toggle="tab" data-bs-target="#class" type="button">
                        <i class="bi bi-collection-fill me-2"></i>Classes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="section-tab" data-bs-toggle="tab" data-bs-target="#section" type="button">
                        <i class="bi bi-diagram-3-fill me-2"></i>Sections
                    </button>
                </li>
            </ul>

            <div class="tab-content active" id="academic">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-card">
                            <h5><i class="bi bi-plus-circle me-2"></i>Add Academic Year</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Year Name</label>
                                    <input type="text" class="form-control" name="year_name" placeholder="e.g., 2024-2025" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Set as Current</label>
                                    <select class="form-select" name="is_current">
                                        <option value="no">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                                <button type="submit" name="add_academic_year" class="btn btn-primary w-100">
                                    <i class="bi bi-check-lg me-2"></i>Add Academic Year
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="content-card">
                            <div class="card-header">
                                <h5 class="card-title">Academic Years</h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Year Name</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Current</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($academicYears)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No academic years found</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($academicYears as $year): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($year['year_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($year['start_date']); ?></td>
                                                <td><?php echo htmlspecialchars($year['end_date']); ?></td>
                                                <td>
                                                    <?php if ($year['is_current'] === 'yes'): ?>
                                                        <span class="badge bg-success">Current</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $year['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                                        <?php echo ucfirst($year['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="edit_session.php?type=academic_year&id=<?php echo $year['id']; ?>" class="btn btn-sm btn-light action-btn text-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this academic year?');">
                                                        <input type="hidden" name="id" value="<?php echo $year['id']; ?>">
                                                        <button type="submit" name="delete_academic_year" class="btn btn-sm btn-light action-btn text-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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
            </div>

            <div class="tab-content" id="class">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-card">
                            <h5><i class="bi bi-plus-circle me-2"></i>Add Class</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Class Name</label>
                                    <input type="text" class="form-control" name="class_name" placeholder="e.g., Class 1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Numeric Grade</label>
                                    <input type="number" class="form-control" name="numeric_grade" placeholder="e.g., 1" min="1" max="12" required>
                                    <small class="text-muted">Grade level (1-12)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" class="form-control" name="capacity" placeholder="e.g., 40" min="1" value="40" required>
                                </div>
                                <button type="submit" name="add_class" class="btn btn-primary w-100">
                                    <i class="bi bi-check-lg me-2"></i>Add Class
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="content-card">
                            <div class="card-header">
                                <h5 class="card-title">Classes</h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Class Name</th>
                                            <th>Numeric Grade</th>
                                            <th>Capacity</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($classes)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No classes found</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($classes as $class): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($class['numeric_grade']); ?></td>
                                                <td><?php echo htmlspecialchars($class['capacity']); ?> students</td>
                                                <td>
                                                    <a href="edit_session.php?type=class&id=<?php echo $class['id']; ?>" class="btn btn-sm btn-light action-btn text-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this class? All associated sections will also be deleted.');">
                                                        <input type="hidden" name="id" value="<?php echo $class['id']; ?>">
                                                        <button type="submit" name="delete_class" class="btn btn-sm btn-light action-btn text-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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
            </div>

            <div class="tab-content" id="section">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-card">
                            <h5><i class="bi bi-plus-circle me-2"></i>Add Section</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Section Name</label>
                                    <input type="text" class="form-control" name="section_name" placeholder="e.g., A" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Select Class</label>
                                    <select class="form-select" name="class_id" required>
                                        <option value="">Select a class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" class="form-control" name="section_capacity" placeholder="e.g., 40" min="1" value="40" required>
                                </div>
                                <button type="submit" name="add_section" class="btn btn-primary w-100">
                                    <i class="bi bi-check-lg me-2"></i>Add Section
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="content-card">
                            <div class="card-header">
                                <h5 class="card-title">Sections</h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Section Name</th>
                                            <th>Class</th>
                                            <th>Capacity</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($sections)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No sections found</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($sections as $section): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($section['section_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($section['class_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($section['capacity']); ?> students</td>
                                                <td>
                                                    <a href="edit_session.php?type=section&id=<?php echo $section['id']; ?>" class="btn btn-sm btn-light action-btn text-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this section?');">
                                                        <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
                                                        <button type="submit" name="delete_section" class="btn btn-sm btn-light action-btn text-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../../assets/js/admin.js"></script>
    
    <script>
        // Handle tab persistence
        document.querySelectorAll('#manageTabs .nav-link').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                const targetId = e.target.getAttribute('data-bs-target');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.querySelector(targetId).classList.add('active');
            });
        });
    </script>
</body>

</html>
