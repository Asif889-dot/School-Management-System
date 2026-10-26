<?php
// Approve Student - Requires login
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Handle approve/reject actions
$action = $_GET['action'] ?? '';
$studentId = $_GET['id'] ?? '';

if ($action && $studentId) {
    try {
        if ($action === 'approve') {
            // Fetch student details for account creation
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$studentId]);
            $stu = $stmt->fetch();

            $stmt = $pdo->prepare("UPDATE students SET status = 'approved' WHERE id = ?");
            $stmt->execute([$studentId]);

            // Auto-create guardian account if not exists
            if ($stu) {
                $guardian_user_id = null;
                try {
                    $guardian_name = trim(($stu['father_name'] ?? '') ?: ($stu['mother_name'] ?? 'Parent'));
                    $guardian_email = !empty($stu['father_phone']) ? 'parent_' . $stu['reg_id'] . '@sca.edu.pk' : 'parent_' . $stu['reg_id'] . '@sca.edu.pk';
                    $guardian_unique = $studentId . '_' . $stu['reg_id'] . '_guardian';
                    $guardian_reg = 'GDR-' . $stu['reg_id'];

                    // Check if guardian account already exists
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE reg_id = ? OR email = ? LIMIT 1");
                    $checkStmt->execute([$guardian_reg, $guardian_email]);
                    $existingGuardian = $checkStmt->fetch();

                    if ($existingGuardian) {
                        $guardian_user_id = $existingGuardian['id'];
                    } else {
                        $defaultPass = password_hash('123456', PASSWORD_DEFAULT);
                        $perm = json_encode(getDefaultPermissionsForRole('guardian'));
                        $ins = $pdo->prepare("INSERT INTO users (reg_id, email, password, full_name, role, status, permissions) VALUES (?,?,?,?,'guardian','active',?)");
                        $ins->execute([$guardian_reg, $guardian_email, $defaultPass, $guardian_name, $perm]);
                        $guardian_user_id = $pdo->lastInsertId();

                        // Link guardian to student
                        $updGuard = $pdo->prepare("UPDATE students SET guardian_user_id = ? WHERE id = ?");
                        $updGuard->execute([$guardian_user_id, $studentId]);
                    }
                } catch (PDOException $e) {
                    error_log("Guardian account creation error: " . $e->getMessage());
                }

                // Auto-create student account if not exists
                try {
                    $student_reg = $stu['reg_id'];
                    $student_email = 'student_' . $stu['reg_id'] . '@sca.edu.pk';

                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE reg_id = ? OR email = ? LIMIT 1");
                    $checkStmt->execute([$student_reg, $student_email]);
                    $existingStudent = $checkStmt->fetch();

                    if (!$existingStudent) {
                        $defaultPass = password_hash('123456', PASSWORD_DEFAULT);
                        $perm = json_encode(getDefaultPermissionsForRole('student'));
                        $fullName = trim(($stu['first_name'] ?? '') . ' ' . ($stu['last_name'] ?? ''));
                        $ins = $pdo->prepare("INSERT INTO users (reg_id, email, password, full_name, role, status, permissions) VALUES (?,?,?,?,'student','active',?)");
                        $ins->execute([$student_reg, $student_email, $defaultPass, $fullName, $perm]);
                    }
                } catch (PDOException $e) {
                    error_log("Student account creation error: " . $e->getMessage());
                }
            }

            header('Location: approve_student.php?message=approved');
            exit;
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE students SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$studentId]);
            header('Location: approve_student.php?message=rejected');
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error updating student: " . $e->getMessage();
    }
}

// Fetch all pending students
$pendingStudents = $pdo->query("
    SELECT s.*, c.class_name, sct.section_name, ay.year_name as academic_year
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sct ON s.section_id = sct.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.status = 'pending'
    ORDER BY s.id DESC
")->fetchAll();

// Fetch recent approved students
$approvedStudents = $pdo->query("
    SELECT s.*, c.class_name, sct.section_name, ay.year_name as academic_year
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sct ON s.section_id = sct.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.status = 'approved'
    ORDER BY s.id DESC
    LIMIT 10
")->fetchAll();

// Fetch recent rejected students
$rejectedStudents = $pdo->query("
    SELECT s.*, c.class_name, sct.section_name, ay.year_name as academic_year
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sct ON s.section_id = sct.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.status = 'rejected'
    ORDER BY s.id DESC
    LIMIT 10
")->fetchAll();

// Get message
$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Students | SMS</title>
    
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
        .student-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .student-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5e7eb;
        }
        .student-photo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
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
        .info-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 0.9rem;
            color: #1f2937;
            font-weight: 500;
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
                <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Approve Students</h5>
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
            <?php if ($message === 'approved'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Student approved successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($message === 'rejected'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                Student rejected!
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
                    <div class="stat-icon orange">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-value"><?php echo count($pendingStudents); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo count($approvedStudents); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo count($rejectedStudents); ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="approvalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">
                        <i class="bi bi-clock-history me-2"></i>Pending 
                        <?php if (count($pendingStudents) > 0): ?>
                            <span class="badge bg-warning ms-1"><?php echo count($pendingStudents); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button">
                        <i class="bi bi-check-circle me-2"></i>Approved
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button">
                        <i class="bi bi-x-circle me-2"></i>Rejected
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="approvalTabsContent">
                <!-- Pending Students -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (empty($pendingStudents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">No Pending Students</h4>
                            <p class="text-muted">All students have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pendingStudents as $student): ?>
                            <div class="col-lg-6">
                                <div class="student-card">
                                    <div class="d-flex align-items-start">
                                        <?php if (!empty($student['photo'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($student['photo']); ?>" alt="Photo" class="student-photo me-3">
                                        <?php else: ?>
                                            <div class="student-photo-placeholder me-3">
                                                <?php echo strtoupper(substr($student['first_name'] ?? 'X', 0, 1) . substr($student['last_name'] ?? 'X', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['reg_id'] ?? ''); ?></small>
                                                </div>
                                                <span class="badge badge-pending">Pending</span>
                                            </div>
                                            
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="info-label">Class</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Section</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['section_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Father's Name</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['father_name'] ?? 'N/A'); ?></div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Phone</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['father_phone_no'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <a href="approve_student.php?action=approve&id=<?php echo $student['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this student?');">
                                                    <i class="bi bi-check-lg me-1"></i> Approve
                                                </a>
                                                <a href="approve_student.php?action=reject&id=<?php echo $student['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this student?');">
                                                    <i class="bi bi-x-lg me-1"></i> Reject
                                                </a>
                                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Approved Students -->
                <div class="tab-pane fade" id="approved" role="tabpanel">
                    <?php if (empty($approvedStudents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">No Approved Students</h4>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($approvedStudents as $student): ?>
                            <div class="col-lg-6">
                                <div class="student-card">
                                    <div class="d-flex align-items-start">
                                        <?php if (!empty($student['photo'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($student['photo']); ?>" alt="Photo" class="student-photo me-3">
                                        <?php else: ?>
                                            <div class="student-photo-placeholder me-3">
                                                <?php echo strtoupper(substr($student['first_name'] ?? 'X', 0, 1) . substr($student['last_name'] ?? 'X', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['reg_id'] ?? ''); ?></small>
                                                </div>
                                                <span class="badge badge-approved">Approved</span>
                                            </div>
                                            
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="info-label">Class</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Section</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['section_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </a>
                                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Rejected Students -->
                <div class="tab-pane fade" id="rejected" role="tabpanel">
                    <?php if (empty($rejectedStudents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">No Rejected Students</h4>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($rejectedStudents as $student): ?>
                            <div class="col-lg-6">
                                <div class="student-card">
                                    <div class="d-flex align-items-start">
                                        <?php if (!empty($student['photo'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($student['photo']); ?>" alt="Photo" class="student-photo me-3">
                                        <?php else: ?>
                                            <div class="student-photo-placeholder me-3">
                                                <?php echo strtoupper(substr($student['first_name'] ?? 'X', 0, 1) . substr($student['last_name'] ?? 'X', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['reg_id'] ?? ''); ?></small>
                                                </div>
                                                <span class="badge badge-rejected">Rejected</span>
                                            </div>
                                            
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="info-label">Class</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Section</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($student['section_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <a href="approve_student.php?action=approve&id=<?php echo $student['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Re-approve this student?');">
                                                    <i class="bi bi-check-lg me-1"></i> Re-approve
                                                </a>
                                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
