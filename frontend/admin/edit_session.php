<?php
// Edit Session/Class/Section - Requires login
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Get the type and ID from URL
$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$record = null;
$message = '';
$messageType = '';

// Fetch the record based on type
if ($id > 0) {
    if ($type === 'academic_year') {
        $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
    } elseif ($type === 'class') {
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
    } elseif ($type === 'section') {
        $stmt = $pdo->prepare("SELECT s.*, c.class_name FROM sections s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
    }
}

// Handle form submission for updating
if (isset($_POST['update_record'])) {
    $updateType = $_POST['record_type'];
    
    if ($updateType === 'academic_year') {
        $year_name = trim($_POST['year_name']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $is_current = $_POST['is_current'] ?? 'no';
        $status = $_POST['status'] ?? 'active';
        
        // If setting as current, unset other current years first
        if ($is_current === 'yes') {
            $stmt = $pdo->prepare("UPDATE academic_years SET is_current = 'no' WHERE is_current = 'yes' AND id != ?");
            $stmt->execute([$id]);
        }
        
        $stmt = $pdo->prepare("UPDATE academic_years SET year_name = ?, start_date = ?, end_date = ?, is_current = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$year_name, $start_date, $end_date, $is_current, $status, $id])) {
            $message = 'Academic year updated successfully!';
            $messageType = 'success';
            // Refresh the record
            $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
        }
        
    } elseif ($updateType === 'class') {
        $class_name = trim($_POST['class_name']);
        $numeric_grade = (int)$_POST['numeric_grade'];
        $capacity = (int)$_POST['capacity'];
        
        try {
            $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, numeric_grade = ?, capacity = ? WHERE id = ?");
            if ($stmt->execute([$class_name, $numeric_grade, $capacity, $id])) {
                $message = 'Class updated successfully!';
                $messageType = 'success';
                $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
                $stmt->execute([$id]);
                $record = $stmt->fetch();
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_grade') !== false) {
                $message = 'A class with this grade level already exists.';
                $messageType = 'danger';
            } else {
                $message = 'Error updating class.';
                $messageType = 'danger';
            }
        }
        
    } elseif ($updateType === 'section') {
        $section_name = trim($_POST['section_name']);
        $class_id = (int)$_POST['class_id'];
        $capacity = (int)$_POST['section_capacity'];
        
        try {
            $stmt = $pdo->prepare("UPDATE sections SET section_name = ?, class_id = ?, capacity = ? WHERE id = ?");
            if ($stmt->execute([$section_name, $class_id, $capacity, $id])) {
                $message = 'Section updated successfully!';
                $messageType = 'success';
                $stmt = $pdo->prepare("SELECT s.*, c.class_name FROM sections s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
                $stmt->execute([$id]);
                $record = $stmt->fetch();
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_section_class') !== false) {
                $message = 'A section with this name already exists for the selected class.';
                $messageType = 'danger';
            } else {
                $message = 'Error updating section.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch classes for section dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_grade ASC")->fetchAll();

// If record not found, redirect back
if (!$record && $id > 0) {
    header('Location: manage_session.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo ucfirst(str_replace('_', ' ', $type)); ?> | SMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
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
                        <i class="bi bi-pencil-square"></i>
                        Edit <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
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
            <!-- Back Button -->
            <div class="mb-4">
                <a href="manage_session.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Manage Sessions
                </a>
            </div>

            <!-- Message Alert -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($record): ?>
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="form-card">
                        <h5><i class="bi bi-pencil-circle me-2"></i>Edit <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?></h5>
                        
                        <form method="POST">
                            <input type="hidden" name="record_type" value="<?php echo htmlspecialchars($type); ?>">
                            
                            <?php if ($type === 'academic_year'): ?>
                            <div class="mb-3">
                                <label class="form-label">Year Name</label>
                                <input type="text" class="form-control" name="year_name" value="<?php echo htmlspecialchars($record['year_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($record['start_date']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($record['end_date']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Set as Current</label>
                                <select class="form-select" name="is_current">
                                    <option value="no" <?php echo $record['is_current'] === 'no' ? 'selected' : ''; ?>>No</option>
                                    <option value="yes" <?php echo $record['is_current'] === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo $record['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $record['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="archived" <?php echo $record['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                            
                            <?php elseif ($type === 'class'): ?>
                            <div class="mb-3">
                                <label class="form-label">Class Name</label>
                                <input type="text" class="form-control" name="class_name" value="<?php echo htmlspecialchars($record['class_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Numeric Grade</label>
                                <input type="number" class="form-control" name="numeric_grade" value="<?php echo htmlspecialchars($record['numeric_grade']); ?>" min="1" max="12" required>
                                <small class="text-muted">Grade level (1-12)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Capacity</label>
                                <input type="number" class="form-control" name="capacity" value="<?php echo htmlspecialchars($record['capacity']); ?>" min="1" required>
                            </div>
                            
                            <?php elseif ($type === 'section'): ?>
                            <div class="mb-3">
                                <label class="form-label">Section Name</label>
                                <input type="text" class="form-control" name="section_name" value="<?php echo htmlspecialchars($record['section_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select Class</label>
                                <select class="form-select" name="class_id" required>
                                    <option value="">Select a class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo $record['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Capacity</label>
                                <input type="number" class="form-control" name="section_capacity" value="<?php echo htmlspecialchars($record['capacity']); ?>" min="1" required>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="update_record" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-check-lg me-2"></i>Update <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
                                </button>
                                <a href="manage_session.php" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>Record not found.
                <a href="manage_session.php" class="alert-link">Return to Manage Sessions</a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../../assets/js/admin.js"></script>
</body>

</html>
