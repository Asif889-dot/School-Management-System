<?php
// add_course.php - Fixed to handle UTF-8 BOM in CSV files
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$success_msg = "";
$error_msg = "";

// Helper function to remove BOM from string
function removeBOM($str)
{
    if (substr($str, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) {
        $str = substr($str, 3);
    }
    return $str;
}

// Helper function to get class_id from numeric_grade
function getClassIdByGrade($pdo, $numeric_grade)
{
    static $grade_cache = [];

    if (isset($grade_cache[$numeric_grade])) {
        return $grade_cache[$numeric_grade];
    }

    $stmt = $pdo->prepare("SELECT id FROM classes WHERE numeric_grade = ?");
    $stmt->execute([$numeric_grade]);
    $result = $stmt->fetch();

    $class_id = $result ? $result['id'] : null;
    $grade_cache[$numeric_grade] = $class_id;

    return $class_id;
}

// Handle Single Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'single') {
        $course_name = trim($_POST['course_name'] ?? '');
        $course_code = trim($_POST['course_code'] ?? '');
        $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;

        if (empty($course_name)) {
            $error_msg = "Course Name is required.";
        } elseif (empty($class_id)) {
            $error_msg = "Assigning a target class is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO courses (course_name, course_code, class_id, created_at) 
                                       VALUES (:course_name, :course_code, :class_id, NOW())");
                $stmt->execute([
                    ':course_name' => $course_name,
                    ':course_code' => $course_code ?: null,
                    ':class_id' => $class_id
                ]);
                $success_msg = "Course '<strong>" . htmlspecialchars($course_name) . "</strong>' created successfully!";
            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }

    // BULK UPLOAD - CSV ONLY with BOM handling
    elseif ($_POST['action'] === 'bulk') {

        if (isset($_FILES['bulk_file']) && $_FILES['bulk_file']['error'] === UPLOAD_ERR_OK) {

            $file_extension = strtolower(pathinfo($_FILES['bulk_file']['name'], PATHINFO_EXTENSION));

            if ($file_extension !== 'csv') {
                $error_msg = "Invalid file type. Please upload CSV files only. You uploaded a ." . strtoupper($file_extension) . " file.";
            } else {

                $courses_data = [];

                try {
                    // Process CSV file with BOM handling
                    $content = file_get_contents($_FILES['bulk_file']['tmp_name']);
                    // Remove BOM if present
                    $content = removeBOM($content);
                    // Split into lines
                    $lines = explode("\n", $content);

                    if (count($lines) < 2) {
                        throw new Exception("CSV file is empty or has no data rows");
                    }

                    // Parse header
                    $header = str_getcsv(trim($lines[0]));
                    if (empty($header)) {
                        throw new Exception("CSV header is empty or invalid");
                    }

                    $header_lower = array_map('strtolower', $header);

                    // Find column indexes
                    $name_index = array_search('course_name', $header_lower);
                    $code_index = array_search('course_code', $header_lower);
                    $grade_index = array_search('numeric_grade', $header_lower);

                    // If numeric_grade not found, try 'grade'
                    if ($grade_index === false) {
                        $grade_index = array_search('grade', $header_lower);
                    }

                    // Debug info (will be shown in error message)
                    $debug_info = "Headers found: " . implode(', ', $header);

                    // Validate required columns exist
                    if ($name_index === false) {
                        throw new Exception("CSV must contain 'course_name' column. $debug_info");
                    }
                    if ($grade_index === false) {
                        throw new Exception("CSV must contain 'numeric_grade' or 'grade' column. $debug_info");
                    }

                    // Process data rows
                    $row_num = 1;
                    for ($i = 1; $i < count($lines); $i++) {
                        $line = trim($lines[$i]);
                        if (empty($line)) continue;

                        $row_num++;
                        $row = str_getcsv($line);

                        // Skip if row has less columns than needed
                        if (count($row) <= max($name_index, $grade_index)) {
                            continue;
                        }

                        $course_name = trim($row[$name_index] ?? '');
                        $course_code = trim($row[$code_index] ?? '');
                        $grade_value = isset($row[$grade_index]) ? intval(trim($row[$grade_index])) : null;

                        if (!empty($course_name) && !empty($grade_value) && $grade_value > 0) {
                            $courses_data[] = [
                                'row_num' => $row_num,
                                'course_name' => $course_name,
                                'course_code' => $course_code,
                                'grade_value' => $grade_value
                            ];
                        }
                    }

                    // Validate and process data
                    if (empty($courses_data)) {
                        $error_msg = "No valid data found in the CSV file. Please ensure you have data rows with course names and grades.";
                    } else {
                        $success_count = 0;
                        $error_count = 0;
                        $errors = [];
                        $warnings = [];

                        // Fetch all available grades for validation
                        $available_grades = $pdo->query("SELECT id, numeric_grade, class_name FROM classes ORDER BY numeric_grade")->fetchAll();
                        $grade_map = [];
                        foreach ($available_grades as $grade) {
                            $grade_map[$grade['numeric_grade']] = [
                                'id' => $grade['id'],
                                'name' => $grade['class_name']
                            ];
                        }

                        $pdo->beginTransaction();

                        try {
                            foreach ($courses_data as $course) {
                                // Map numeric_grade to class_id
                                if (!isset($grade_map[$course['grade_value']])) {
                                    $error_count++;
                                    $available = implode(', ', array_keys($grade_map));
                                    $errors[] = "Row {$course['row_num']}: Grade '{$course['grade_value']}' does not exist. Available grades: $available";
                                    continue;
                                }

                                $class_id = $grade_map[$course['grade_value']]['id'];
                                $class_name = $grade_map[$course['grade_value']]['name'];

                                // Check for duplicate course in same class
                                $check_duplicate = $pdo->prepare("SELECT id FROM courses WHERE course_name = ? AND class_id = ?");
                                $check_duplicate->execute([$course['course_name'], $class_id]);
                                if ($check_duplicate->fetch()) {
                                    $warnings[] = "Row {$course['row_num']}: Course '{$course['course_name']}' already exists in Grade {$course['grade_value']} ($class_name) - skipped";
                                    continue;
                                }

                                // Insert course
                                $stmt = $pdo->prepare("INSERT INTO courses (course_name, course_code, class_id, created_at) 
                                                       VALUES (?, ?, ?, NOW())");
                                $stmt->execute([
                                    $course['course_name'],
                                    $course['course_code'] ?: null,
                                    $class_id
                                ]);

                                $success_count++;
                            }

                            $pdo->commit();

                            if ($success_count > 0) {
                                $success_msg = "Successfully uploaded <strong>$success_count</strong> course(s)";
                                if ($error_count > 0) {
                                    $success_msg .= " with <strong>$error_count</strong> error(s).";
                                    $_SESSION['bulk_errors'] = $errors;
                                }
                                if (!empty($warnings)) {
                                    $success_msg .= "<br><strong>" . count($warnings) . "</strong> warning(s) (duplicates skipped).";
                                    $_SESSION['bulk_warnings'] = $warnings;
                                }
                                if ($error_count > 0 || !empty($warnings)) {
                                    $success_msg .= " <a href='#errorSection' onclick='showErrors()'>View details</a>";
                                }
                            } else {
                                $error_msg = "No courses were uploaded. Please check your CSV file.";
                                if (!empty($errors)) {
                                    $error_msg .= "<br>" . implode("<br>", array_slice($errors, 0, 10));
                                }
                            }
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            $error_msg = "Database Error: " . $e->getMessage();
                        }
                    }
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                }
            }
        } else {
            $error_msg = "Please select a CSV file to upload.";
        }
    }
}

// Fetch all classes for dropdown
$classes = $pdo->query("SELECT id, class_name, numeric_grade FROM classes ORDER BY numeric_grade ASC")->fetchAll();

// Fetch recent courses with grade information
$recent_courses = $pdo->query("
    SELECT c.*, cls.class_name, cls.numeric_grade 
    FROM courses c
    JOIN classes cls ON c.class_id = cls.id
    ORDER BY c.created_at DESC 
    LIMIT 10
")->fetchAll();

// Get bulk errors/warnings from session
$bulk_errors = $_SESSION['bulk_errors'] ?? [];
$bulk_warnings = $_SESSION['bulk_warnings'] ?? [];
unset($_SESSION['bulk_errors']);
unset($_SESSION['bulk_warnings']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Course | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            margin-bottom: 25px;
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

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
            color: white;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 24px;
        }

        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background: transparent;
        }

        .template-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        .error-list,
        .warning-list {
            max-height: 200px;
            overflow-y: auto;
        }

        pre {
            font-size: 12px;
            background: #fff;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin: 0;
        }

        .file-info {
            border-left: 4px solid #28a745;
        }

        .grade-badge {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .step-box {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Course Management</h2>
                    <p class="text-muted">Add courses individually or bulk upload using CSV format</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="courses.php" class="btn btn-outline-secondary shadow-sm">
                        <i class="bi bi-collection-play me-1"></i> View All Courses
                    </a>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                        <div><?= $success_msg ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show card p-3 mb-4 border-start border-5 border-danger" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div><?= $error_msg ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-4" id="courseTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">
                        <i class="bi bi-journal-plus me-2"></i>Single Course
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab">
                        <i class="bi bi-upload me-2"></i>Bulk Upload (CSV)
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- SINGLE COURSE TAB -->
                <div class="tab-pane fade show active" id="single" role="tabpanel">
                    <div class="card p-4 p-md-5 bg-white">
                        <h4 class="fw-bold mb-4 text-primary">
                            <i class="bi bi-journal-plus me-2"></i> Add Single Course
                        </h4>
                        <hr>

                        <form action="" method="POST">
                            <input type="hidden" name="action" value="single">

                            <div class="row g-4">
                                <div class="col-md-12">
                                    <label class="form-label text-uppercase">COURSE NAME <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-book"></i></span>
                                        <input type="text" name="course_name" class="form-control" placeholder="e.g., Advanced Mathematics" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-uppercase">COURSE CODE</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-code-square"></i></span>
                                        <input type="text" name="course_code" class="form-control" placeholder="e.g., MATH301">
                                    </div>
                                    <small class="text-muted">Optional unique identifier for the course</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-uppercase">ASSIGN TO CLASS <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-building"></i></span>
                                        <select name="class_id" class="form-select" required>
                                            <option value="">Select Class...</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?= $class['id'] ?>">
                                                    Grade <?= $class['numeric_grade'] ?> - <?= htmlspecialchars($class['class_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 text-end">
                                    <button type="reset" class="btn btn-light px-4 me-2">Clear</button>
                                    <button type="submit" class="btn btn-submit px-5">
                                        <i class="bi bi-save me-2"></i>Save Course
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- BULK UPLOAD TAB -->
                <div class="tab-pane fade" id="bulk" role="tabpanel">
                    <div class="card p-4 p-md-5 bg-white">
                        <h4 class="fw-bold mb-4 text-primary">
                            <i class="bi bi-cloud-upload me-2"></i> Bulk Upload Courses (CSV Format)
                        </h4>
                        <hr>

                        <div class="alert alert-success file-info">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>CSV Format Only</strong> - Simple, reliable, and error-free!
                            <br>
                            <small>Upload a CSV file with columns: course_name, course_code, numeric_grade</small>
                        </div>

                        <!-- Show current available grades -->
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Available Grades in System:</strong>
                            <div class="mt-2">
                                <?php foreach ($classes as $class): ?>
                                    <span class="grade-badge me-1 mb-1">
                                        Grade <?= $class['numeric_grade'] ?> (<?= htmlspecialchars($class['class_name']) ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="step-box">
                            <h6><span class="step-number">1</span> Download the CSV template</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="downloadCSVTemplate()">
                                <i class="bi bi-download me-2"></i>Download CSV Template
                            </button>
                        </div>

                        <div class="step-box">
                            <h6><span class="step-number">2</span> Add your courses</h6>
                            <p class="mb-2 text-muted small">Fill in your course data. Use one of the available grades above:</p>
                            <pre class="mb-0">course_name,course_code,numeric_grade
Physics,PHY101,1
Mathematics,MATH101,1
English,ENG101,2
Computer Science,CS101,3</pre>
                            <small class="text-danger">⚠️ Note: Your file shows Grade 11, but the system only has certain grades. Please use available grades!</small>
                        </div>

                        <div class="step-box">
                            <h6><span class="step-number">3</span> Upload the CSV file</h6>
                            <form action="" method="POST" enctype="multipart/form-data" id="bulkUploadForm">
                                <input type="hidden" name="action" value="bulk">

                                <div class="mb-3">
                                    <label class="form-label">SELECT CSV FILE</label>
                                    <input type="file" name="bulk_file" id="bulk_file" class="form-control" accept=".csv" required>
                                    <small class="text-muted">Only CSV files are accepted</small>
                                </div>

                                <button type="submit" class="btn btn-submit px-4" id="uploadBtn">
                                    <i class="bi bi-upload me-2"></i>Upload CSV & Process
                                </button>
                            </form>
                        </div>

                        <?php if (!empty($bulk_errors) || !empty($bulk_warnings)): ?>
                            <div class="mt-4" id="errorSection">
                                <?php if (!empty($bulk_errors)): ?>
                                    <div class="alert alert-danger">
                                        <h6><i class="bi bi-exclamation-circle me-2"></i>Upload Errors</h6>
                                        <div class="error-list">
                                            <ul class="mb-0">
                                                <?php foreach ($bulk_errors as $error): ?>
                                                    <li><?= htmlspecialchars($error) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($bulk_warnings)): ?>
                                    <div class="alert alert-warning">
                                        <h6><i class="bi bi-exclamation-triangle me-2"></i>Warnings</h6>
                                        <div class="warning-list">
                                            <ul class="mb-0">
                                                <?php foreach ($bulk_warnings as $warning): ?>
                                                    <li><?= htmlspecialchars($warning) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($recent_courses)): ?>
                <div class="card p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-clock-history me-2"></i>Recently Added Courses
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="coursesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Course Name</th>
                                    <th>Course Code</th>
                                    <th>Grade</th>
                                    <th>Class</th>
                                    <th>Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_courses as $course): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($course['course_name']) ?></td>
                                        <td><?= htmlspecialchars($course['course_code'] ?? '—') ?></td>
                                        <td><span class="grade-badge">Grade <?= $course['numeric_grade'] ?></span></td>
                                        <td><?= htmlspecialchars($course['class_name']) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($course['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#coursesTable').DataTable({
                pageLength: 5,
                lengthChange: false,
                ordering: true,
                order: [
                    [4, 'desc']
                ]
            });
        });

        function downloadCSVTemplate() {
            // Get available grades from PHP
            const availableGrades = <?php echo json_encode(array_column($classes, 'numeric_grade')); ?>;
            const defaultGrade = availableGrades.length > 0 ? availableGrades[0] : 1;

            const csv = "course_name,course_code,numeric_grade\n" +
                "Mathematics,MATH101," + defaultGrade + "\n" +
                "Physics,PHY101," + defaultGrade + "\n" +
                "English,ENG101," + (availableGrades[1] || defaultGrade) + "\n" +
                "Computer Science,CS101," + (availableGrades[2] || defaultGrade);

            const blob = new Blob(["\uFEFF" + csv], {
                type: 'text/csv;charset=utf-8;'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'courses_template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Validate file before upload
        document.getElementById('bulk_file')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const uploadBtn = document.getElementById('uploadBtn');

            if (file) {
                const fileName = file.name.toLowerCase();
                const fileExt = fileName.split('.').pop();

                if (fileExt !== 'csv') {
                    alert('Please upload a CSV file only. You selected a .' + fileExt.toUpperCase() + ' file.');
                    this.value = '';
                }
            }
        });

        function showErrors() {
            const bulkTab = document.getElementById('bulk-tab');
            const tab = new bootstrap.Tab(bulkTab);
            tab.show();
            setTimeout(function() {
                const errorSection = document.getElementById('errorSection');
                if (errorSection) {
                    errorSection.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            }, 100);
        }

        // Persist active tab after form submission
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab === 'bulk') {
            const bulkTabElement = document.getElementById('bulk-tab');
            if (bulkTabElement) {
                const bulkTab = new bootstrap.Tab(bulkTabElement);
                bulkTab.show();
            }
        }

        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(button => {
            button.addEventListener('shown.bs.tab', event => {
                const tabId = event.target.id.replace('-tab', '');
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);
            });
        });
    </script>
</body>

</html>