<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$success_msg = "";
$error_msg = "";
$course = null;

// 1. Validate and Fetch the existing Course Record
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $course_id = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
        $stmt->execute([':id' => $course_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            $error_msg = "The requested course record could not be found in the system registry.";
        }
    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect or block if access is directly attempted without an explicit parameter context
    header("Location: courses.php");
    exit();
}

// 2. Handle the Form Update processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id   = intval($_POST['course_id'] ?? 0);
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $class_id    = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;

    if (empty($course_name)) {
        $error_msg = "Course Name / Title cannot be saved empty.";
    } elseif (empty($class_id)) {
        $error_msg = "Please assign this course configuration to a class layer.";
    } else {
        try {
            $update_stmt = $pdo->prepare("UPDATE courses 
                                          SET course_name = :course_name, 
                                              course_code = :course_code, 
                                              class_id = :class_id,
                                              updated_at = NOW() 
                                          WHERE id = :id");
            
            $update_stmt->execute([
                ':course_name' => $course_name,
                ':course_code' => $course_code,
                ':class_id'   => $class_id,
                ':id'         => $course_id
            ]);

            $success_msg = "Course parameters updated successfully!";
            
            // Refresh local variable state layer to echo the new updates back cleanly
            $course['course_name'] = $course_name;
            $course['course_code'] = $course_code;
            $course['class_id']    = $class_id;
            
        } catch (PDOException $e) {
            $error_msg = "Database Update Operations Failure: " . $e->getMessage();
        }
    }
}

// 3. Extract Classes Dataset for Dropdown Options
try {
    $classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error updating fallback class indices: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Course | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Inter', sans-serif;
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            width: 100%;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #495057;
            letter-spacing: 0.5px;
        }

        .input-group-text {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
            color: white;
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Modify Course Definition</h2>
                    <p class="text-muted">Edit structural assignments or track system schema alterations for targeted syllabi.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="courses.php" class="btn btn-outline-secondary shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Course Matrix
                    </a>
                </div>
            </div>

            <!-- Validation Feedback Alerts -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-patch-check-fill fs-4 me-3 text-success"></i>
                        <div><?= $success_msg ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show card p-3 mb-4 border-start border-5 border-danger" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div><?= $error_msg ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Form Content Box Component Container -->
            <?php if ($course): ?>
                <div class="card p-4 p-md-5 bg-white">
                    <h4 class="fw-bold mb-4 text-primary d-flex align-items-center">
                        <i class="bi bi-pencil-square me-2"></i> Alter Curriculum Node Details
                    </h4>
                    <hr class="text-muted mb-4">

                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $course['id']) ?>" method="POST" autocomplete="off">
                        <!-- Implicit Hidden ID Key Payload -->
                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">

                        <div class="row g-4">
                            
                            <!-- Course Title Field -->
                            <div class="col-md-12">
                                <label class="form-label text-uppercase">Course Name / Title <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-book-half"></i></span>
                                    <input type="text" name="course_name" class="form-control bg-light" placeholder="e.g. Computer Architecture" value="<?= htmlspecialchars($course['course_name']) ?>" required>
                                </div>
                            </div>

                            <!-- Course Code Field -->
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Course Code / Identifier</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" name="course_code" class="form-control bg-light" placeholder="e.g. CS-202" value="<?= htmlspecialchars($course['course_code'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Structural Class Mappings dropdown selection -->
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Class Level Target Allocation <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                                    <select name="class_id" class="form-select bg-light" required>
                                        <option value="">Select Target Class Module...</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $course['class_id'] == $c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['class_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Submission Actions row -->
                            <div class="col-12 mt-5 text-end">
                                <a href="courses.php" class="btn btn-light px-4 me-2">Cancel</a>
                                <button type="submit" class="btn btn-update px-5 py-2 shadow-sm">
                                    <i class="bi bi-save me-2"></i> Save Course Modifications
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>