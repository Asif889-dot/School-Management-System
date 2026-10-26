<?php
// Student View Form - Requires login
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Get student ID from URL
$studentId = $_GET['id'] ?? '';

// If no ID, redirect to students list
if (empty($studentId)) {
    header('Location: students.php');
    exit;
}

// Fetch student data
$stmt = $pdo->prepare("SELECT s.*, c.class_name, sct.section_name, ay.year_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sct ON s.section_id = sct.id 
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id 
    WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = 'Student not found';
    header('Location: students.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student | SMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-purple: #764ba2;
        }

        .profile-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            background: #fff;
            overflow: hidden;
        }

        .profile-header {
            background: var(--primary-gradient);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            margin-bottom: 15px;
        }

        .photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            border: 5px solid white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            margin-bottom: 15px;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-reg {
            font-size: 1rem;
            opacity: 0.9;
        }

        .info-card {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            height: 100%;
        }

        .info-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--accent-purple);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f1f1;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 0.95rem;
            color: #1f2937;
            font-weight: 500;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.approved {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-badge.pending {
            background: rgba(249, 115, 22, 0.1);
            color: #f97316;
        }

        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .btn-back {
            background: var(--primary-gradient);
            border: none;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 600;
            color: white;
        }

        .btn-back:hover {
            color: white;
            transform: translateY(-2px);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f3f4f6;
            padding: 20px;
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
                <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Student Details</h5>
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
            <div class="mb-3">
                <a href="students.php" class="btn btn-back text-white">
                    <i class="bi bi-arrow-left me-2"></i> Back to Students
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-12">
                    <div class="profile-card">
                        <div class="profile-header">
                            <?php if (!empty($student['photo'])): ?>
                                <img src="../../<?php echo htmlspecialchars($student['photo']); ?>" alt="Photo" class="profile-photo">
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="profile-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                            <div class="profile-reg"><?php echo htmlspecialchars($student['reg_id']); ?></div>
                            <span class="status-badge <?php echo $student['status'] ?? 'pending'; ?> mt-2">
                                <?php echo ucfirst($student['status'] ?? 'pending'); ?>
                            </span>
                        </div>

                        <div class="card-body p-4">
                            <div class="row g-4">
                                <!-- Basic Information -->
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h5 class="info-title"><i class="bi bi-person-circle me-2"></i> Basic Information</h5>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Full Name</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">B-Form / ID Number</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['id_proof']); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Gender</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['gender']); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Religion</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['religion']); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Date of Birth</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['date_of_birth']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Academic Information -->
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h5 class="info-title"><i class="bi bi-book-fill me-2"></i> Academic Information</h5>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Academic Session</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['year_name'] ?? 'Not Assigned'); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Class</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Section</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['section_name'] ?? 'Not Assigned'); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Admission Type</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['admission_type'] ?? 'N/A'); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Previous School</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['previous_school'] ?? 'N/A'); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Percentage / Grade</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['last_exam_percentage'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Parent Information -->
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h5 class="info-title"><i class="bi bi-people-fill me-2"></i> Parent Information</h5>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Father's Name</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['father_name']); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Father's Contact</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['father_phone'] ?? 'N/A'); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Mother's Name</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['mother_name'] ?? 'N/A'); ?></div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-label">Mother's Contact</div>
                                            <div class="info-value"><?php echo htmlspecialchars($student['mother_phone'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="text-center mt-4">
                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-primary me-2">
                                    <i class="bi bi-pencil me-2"></i> Edit Student
                                </a>
                                <a href="students.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
</body>

</html>
