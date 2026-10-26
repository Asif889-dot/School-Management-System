<?php
// Student Edit Form - Requires login
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    try {
        $pdo->beginTransaction();
        
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $idProof = trim($_POST['id_proof']);
        $gender = $_POST['gender'];
        $religion = $_POST['religion'];
        $dateOfBirth = $_POST['date_of_birth'];
        $fatherName = trim($_POST['father_name']);
        $fatherPhone = trim($_POST['father_phone']);
        $motherName = trim($_POST['mother_name']);
        $motherPhone = trim($_POST['mother_phone']);
        $classId = $_POST['class_id'];
        $sectionId = $_POST['section_id'];
        $academicYearId = $_POST['academic_year_id'];
        $previousSchool = trim($_POST['previous_school']);
        $percentage = trim($_POST['last_exam_percentage']);
        $admissionType = $_POST['admission_type'];
        
        // Handle photo upload if new photo is provided
        $photoPath = $student['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/uploads/students/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['photo']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                $photoPath = 'assets/uploads/students/' . $fileName;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE students SET 
            first_name = ?, last_name = ?, id_proof = ?, gender = ?, religion = ?, 
            date_of_birth = ?, father_name = ?, father_phone_no = ?, mother_name = ?, 
            mother_phone_no = ?, class_id = ?, section_id = ?, academic_year_id = ?,
            previous_school = ?, percentage = ?, admission_type = ?, photo = ?, updated_at = NOW()
            WHERE id = ?");
        
        $stmt->execute([
            $firstName, $lastName, $idProof, $gender, $religion, $dateOfBirth,
            $fatherName, $fatherPhone, $motherName, $motherPhone, $classId, $sectionId,
            $academicYearId, $previousSchool, $percentage, $admissionType, $photoPath, $studentId
        ]);
        
        $pdo->commit();
        $_SESSION['message'] = 'Student updated successfully!';
        $_SESSION['message_type'] = 'success';
        header('Location: students.php?message=updated');
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating student: " . $e->getMessage();
    }
}

// Fetch dropdowns
$academicYears = $pdo->query("SELECT * FROM academic_years ORDER BY year_name DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_grade ASC")->fetchAll();
$sections = $pdo->query("SELECT s.*, c.class_name FROM sections s LEFT JOIN classes c ON s.class_id = c.id ORDER BY c.numeric_grade, s.section_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | SMS</title>

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

        .admission-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            background: #fff;
            overflow: hidden;
        }

        .form-header {
            background: var(--primary-gradient);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--accent-purple);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 8px;
            margin-bottom: 25px;
            margin-top: 20px;
        }

        .required-star {
            color: #e63946;
            margin-left: 2px;
        }

        .optional-badge {
            font-size: 0.65rem;
            padding: 2px 8px;
            border-radius: 50px;
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        .form-control, .form-select {
            border: 2px solid #edf2f7;
            border-radius: 12px;
            padding: 12px 15px;
            transition: 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-purple);
            box-shadow: none;
        }

        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-purple);
            margin-bottom: 10px;
        }

        .photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            padding: 15px 60px;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            font-size: 1rem;
            transition: 0.4s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(118, 75, 162, 0.3);
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
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Student</h5>
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
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-xl-11">
                    <div class="admission-card">
                        <div class="form-header">
                            <h4 class="fw-bold mb-1">Edit Student Information</h4>
                            <p class="mb-0 opacity-75">Reg. No: <?php echo htmlspecialchars($student['reg_id']); ?></p>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="p-4 p-md-5">

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-person-circle me-2"></i> Student Basic Info</h5>
                                </div>

                                <div class="col-md-3 text-center">
                                    <label class="form-label small fw-bold">Photo</label>
                                    <?php if (!empty($student['photo'])): ?>
                                        <img src="../../<?php echo htmlspecialchars($student['photo']); ?>" alt="Photo" class="photo-preview">
                                    <?php else: ?>
                                        <div class="photo-placeholder">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="photo" accept="image/*">
                                    <small class="text-muted">Leave blank to keep current photo</small>
                                </div>

                                <div class="col-md-9">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">First Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Last Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">B-Form / ID Number<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="id_proof" value="<?php echo htmlspecialchars($student['id_proof']); ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Gender<span class="required-star">*</span></label>
                                            <select class="form-select" name="gender" required>
                                                <option value="Male" <?php echo $student['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo $student['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Religion<span class="required-star">*</span></label>
                                            <select class="form-select" name="religion" required>
                                                <option value="Muslim" <?php echo $student['religion'] === 'Muslim' ? 'selected' : ''; ?>>Muslim</option>
                                                <option value="Christian" <?php echo $student['religion'] === 'Christian' ? 'selected' : ''; ?>>Christian</option>
                                                <option value="Other" <?php echo $student['religion'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Date of Birth<span class="required-star">*</span></label>
                                            <input type="date" class="form-control" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Info Section -->
                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-calendar-range me-2"></i> Academic Information</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Academic Session<span class="required-star">*</span></label>
                                    <select class="form-select" name="academic_year_id" required>
                                        <option value="">Select Session</option>
                                        <?php foreach ($academicYears as $year): ?>
                                            <option value="<?php echo $year['id']; ?>" <?php echo $student['academic_year_id'] == $year['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year['year_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Class<span class="required-star">*</span></label>
                                    <select class="form-select" name="class_id" id="classSelect" required onchange="loadSections()">
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $student['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Section<span class="required-star">*</span></label>
                                    <select class="form-select" name="section_id" id="sectionSelect" required>
                                        <option value="">Select Section</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo $section['id']; ?>" data-class-id="<?php echo $section['class_id']; ?>" class="section-option" 
                                                <?php echo $student['section_id'] == $section['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($section['section_name'] . ' - ' . $section['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-mortarboard me-2"></i> Previous School History</h5>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Last School Attended</label>
                                    <input type="text" class="form-control" name="previous_school" value="<?php echo htmlspecialchars($student['previous_school'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Percentage / Grade</label>
                                    <input type="text" class="form-control" name="percentage" value="<?php echo htmlspecialchars($student['last_exam_percentage'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Admission Type<span class="required-star">*</span></label>
                                    <select class="form-select" name="admission_type" required>
                                        <option value="New Admission" <?php echo ($student['admission_type'] ?? '') === 'New Admission' ? 'selected' : ''; ?>>New Admission</option>
                                        <option value="Transfer" <?php echo ($student['admission_type'] ?? '') === 'Transfer' ? 'selected' : ''; ?>>Transfer Case</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-people me-2"></i> Parental Info</h5>
                                </div>
                                <div class="col-md-6">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Father's Full Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="father_name" value="<?php echo htmlspecialchars($student['father_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Father's Contact<span class="required-star">*</span></label>
                                            <input type="tel" class="form-control" name="father_phone" value="<?php echo htmlspecialchars($student['father_phone']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Mother's Full Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="mother_name" value="<?php echo htmlspecialchars($student['mother_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Mother's Contact<span class="required-star">*</span></label>
                                            <input type="tel" class="form-control" name="mother_phone" value="<?php echo htmlspecialchars($student['mother_phone']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center pt-4">
                                <button type="submit" name="update_student" class="btn btn-submit shadow">Update Student</button>
                                <a href="students.php" class="btn btn-secondary ms-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
    
    <script>
        function loadSections() {
            var classId = document.getElementById('classSelect').value;
            var sectionOptions = document.querySelectorAll('.section-option');
            
            for (var i = 0; i < sectionOptions.length; i++) {
                var option = sectionOptions[i];
                if (classId) {
                    if (option.getAttribute('data-class-id') === classId) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                } else {
                    option.style.display = '';
                }
            }
        }
    </script>
</body>

</html>
