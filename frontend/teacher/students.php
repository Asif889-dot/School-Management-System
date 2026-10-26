<?php

/**
 * Student Management - Teacher View
 * Smart Choice Academy
 * Shows only students assigned to the logged-in teacher
 */

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

// Check if user is teacher
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Get teacher information
$user_id = $_SESSION['user_id'] ?? null;
$teacher_id = null;

if ($user_id) {
    $stmt = $db->prepare("SELECT teacher_id, employee_id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
}

// If teacher_id not found, try by reg_id
if (!$teacher_id && isset($_SESSION['reg_id'])) {
    $stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE employee_id = ?");
    $stmt->execute([$_SESSION['reg_id']]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
}

// Get current academic year
$stmt = $db->prepare("SELECT id, year_name FROM academic_years WHERE is_current = 'yes' AND status = 'active' LIMIT 1");
$stmt->execute();
$academic_year = $stmt->fetch();
$academic_year_id = $academic_year['id'] ?? 1;

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$section_filter = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

// Fetch classes taught by this teacher for filter dropdown
$classes_taught = [];
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.class_name, c.numeric_grade 
    FROM timetable_records tr
    JOIN classes c ON tr.class_id = c.id
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
    ORDER BY c.numeric_grade
");
$stmt->execute([$teacher_id, $academic_year_id]);
$classes_taught = $stmt->fetchAll();

// Fetch sections based on selected class
$sections = [];
if ($class_filter > 0) {
    $stmt = $db->prepare("
        SELECT DISTINCT sec.id, sec.section_name 
        FROM timetable_records tr
        JOIN sections sec ON tr.section_id = sec.id
        WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND tr.class_id = ?
        ORDER BY sec.section_name
    ");
    $stmt->execute([$teacher_id, $academic_year_id, $class_filter]);
    $sections = $stmt->fetchAll();
}

// Build query to get students taught by this teacher
$query = "
    SELECT DISTINCT 
        s.id,
        s.reg_id,
        s.first_name,
        s.last_name,
        s.gender,
        s.date_of_birth,
        s.father_name,
        s.father_phone,
        s.mother_name,
        s.mother_phone,
        s.photo,
        s.status,
        c.id as class_id,
        c.class_name,
        c.numeric_grade,
        sec.id as section_id,
        sec.section_name,
        COALESCE((
            SELECT AVG(om.obtained_marks / om.total_marks * 100) 
            FROM exam_marks om 
            WHERE om.student_id = s.id 
            LIMIT 1
        ), 0) as average_percentage
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    JOIN timetable_records tr ON s.class_id = tr.class_id AND s.section_id = tr.section_id
    WHERE tr.teacher_id = ? 
        AND tr.academic_year_id = ?
        AND s.status = 'approved'
";

$params = [$teacher_id, $academic_year_id];

if ($class_filter > 0) {
    $query .= " AND s.class_id = ?";
    $params[] = $class_filter;
}

if ($section_filter > 0) {
    $query .= " AND s.section_id = ?";
    $params[] = $section_filter;
}

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.reg_id LIKE ? OR s.father_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY c.numeric_grade, sec.section_name, s.first_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get statistics for teacher
$total_students = count($students);
$male_count = 0;
$female_count = 0;
foreach ($students as $student) {
    if ($student['gender'] == 'Male') $male_count++;
    if ($student['gender'] == 'Female') $female_count++;
}
$other_count = $total_students - $male_count - $female_count;

// Get teacher name for display
$teacher_name = '';
$stmt = $db->prepare("
    SELECT u.full_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$teacher_info = $stmt->fetch();
$teacher_name = $teacher_info['full_name'] ?? 'Teacher';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: 100vh;
            width: 100%;
            transition: all 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            color: white;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Student Table */
        .student-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .student-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .student-table td {
            padding: 12px 15px;
            font-size: 13px;
            vertical-align: middle;
        }

        .student-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }

        .photo-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #adb5bd;
        }

        .gender-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .gender-male {
            background: #cfe2ff;
            color: #084298;
        }

        .gender-female {
            background: #f8d7da;
            color: #85182a;
        }

        .gender-other {
            background: #e2e3e5;
            color: #41464b;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .status-approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .action-buttons .btn {
            padding: 4px 8px;
            font-size: 11px;
            margin: 0 2px;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .student-name {
            font-weight: 600;
            color: #333;
        }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">

            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1">
                            <i class="bi bi-people-fill me-2"></i>My Students
                        </h4>
                        <p class="mb-0 opacity-75">
                            <?= htmlspecialchars($teacher_name) ?> |
                            Academic Year: <?= htmlspecialchars($academic_year['year_name'] ?? date('Y')) ?>
                        </p>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-mortarboard me-1"></i> Total: <?= $total_students ?> Students
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #4e73df;">
                        <div class="stat-number"><?= $total_students ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #1cc88a;">
                        <div class="stat-number"><?= $male_count ?></div>
                        <div class="stat-label">Male Students</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #f6c23e;">
                        <div class="stat-number"><?= $female_count ?></div>
                        <div class="stat-label">Female Students</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #36b9cc;">
                        <div class="stat-number"><?= count($classes_taught) ?></div>
                        <div class="stat-label">Classes Teaching</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-2"></i>Filter Students</h6>
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes_taught as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $class_filter == $class['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['class_name']) ?> (Grade <?= $class['numeric_grade'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Section</label>
                        <select name="section_id" class="form-select" onchange="this.form.submit()" <?= empty($sections) ? 'disabled' : '' ?>>
                            <option value="0">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>" <?= $section_filter == $section['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, Reg ID, Father Name" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                        <?php if ($class_filter > 0 || $section_filter > 0 || !empty($search)): ?>
                            <a href="students.php" class="btn btn-outline-secondary ms-2 w-100">
                                <i class="bi bi-arrow-repeat me-1"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="student-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="studentsTable">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Student Name</th>
                                <th>Reg ID</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Father's Name</th>
                                <th>Contact</th>
                                <th>Gender</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <i class="bi bi-people"></i>
                                        <h5>No students found</h5>
                                        <p class="mb-0">You don't have any students assigned yet.</p>
                                        <small class="text-muted">Contact the admin to assign classes to you.</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student):
                                    $full_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                                    $photo_path = !empty($student['photo']) && file_exists('../../' . $student['photo'])
                                        ? '../../' . $student['photo']
                                        : null;
                                    $gender_class = strtolower($student['gender']) == 'male' ? 'gender-male' : (strtolower($student['gender']) == 'female' ? 'gender-female' : 'gender-other');
                                    $contact = !empty($student['father_phone']) ? $student['father_phone'] : (!empty($student['mother_phone']) ? $student['mother_phone'] : 'N/A');
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($photo_path): ?>
                                                <img src="<?= $photo_path ?>" alt="Photo" class="student-photo">
                                            <?php else: ?>
                                                <div class="photo-placeholder">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="student-name"><?= $full_name ?></div>
                                            <small class="text-muted">DOB: <?= date('d-m-Y', strtotime($student['date_of_birth'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($student['reg_id']) ?></td>
                                        <td><?= htmlspecialchars($student['class_name']) ?></td>
                                        <td><?= htmlspecialchars($student['section_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($student['father_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($contact) ?></td>
                                        <td>
                                            <span class="gender-badge <?= $gender_class ?>">
                                                <?= htmlspecialchars($student['gender']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="student_profile.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Profile">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="marks_entry.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-success" title="Enter Marks">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="attendance.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-info" title="Mark Attendance">
                                                    <i class="bi bi-check2-square"></i>
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
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#studentsTable').DataTable({
                pageLength: 10,
                lengthChange: true,
                ordering: true,
                order: [
                    [2, 'asc']
                ], // Sort by Reg ID
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ students",
                    info: "Showing _START_ to _END_ of _TOTAL_ students",
                    infoEmpty: "No students found",
                    zeroRecords: "No matching students found"
                },
                columnDefs: [{
                        orderable: false,
                        targets: [0, 8]
                    } // Disable sorting on photo and actions columns
                ]
            });
        });
    </script>
</body>

</html>