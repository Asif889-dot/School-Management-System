<?php

/**
 * Course Management - Teacher View
 * Smart Choice Academy
 * Shows only courses assigned to the logged-in teacher
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
$teacher_name = '';

if ($user_id) {
    $stmt = $db->prepare("SELECT t.teacher_id, t.employee_id, u.full_name 
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
}

// If teacher_id not found, try by reg_id
if (!$teacher_id && isset($_SESSION['reg_id'])) {
    $stmt = $db->prepare("SELECT t.teacher_id, t.employee_id, u.full_name 
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.employee_id = ?");
    $stmt->execute([$_SESSION['reg_id']]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
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

// Build query to get courses taught by this teacher
$query = "
    SELECT DISTINCT 
        c.id,
        c.course_name,
        c.course_code,
        c.class_id,
        cls.class_name,
        cls.numeric_grade,
        sec.id as section_id,
        sec.section_name,
        COUNT(DISTINCT tr.id) as total_periods,
        COUNT(DISTINCT tr.section_id) as total_sections,
        (
            SELECT COUNT(DISTINCT s.id) 
            FROM students s 
            WHERE s.class_id = c.class_id 
            AND s.section_id = tr.section_id 
            AND s.status = 'approved'
        ) as total_students
    FROM courses c
    JOIN classes cls ON c.class_id = cls.id
    JOIN timetable_records tr ON c.id = tr.course_id
    LEFT JOIN sections sec ON tr.section_id = sec.id
    WHERE tr.teacher_id = ? 
        AND tr.academic_year_id = ?
";

$params = [$teacher_id, $academic_year_id];

if ($class_filter > 0) {
    $query .= " AND c.class_id = ?";
    $params[] = $class_filter;
}

if ($section_filter > 0) {
    $query .= " AND tr.section_id = ?";
    $params[] = $section_filter;
}

if (!empty($search)) {
    $query .= " AND (c.course_name LIKE ? OR c.course_code LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " GROUP BY c.id, c.course_name, c.course_code, c.class_id, cls.class_name, cls.numeric_grade, sec.id, sec.section_name
            ORDER BY cls.numeric_grade, sec.section_name, c.course_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get statistics
$total_courses = count($courses);
$total_classes_teaching = count($classes_taught);
$total_periods = 0;
foreach ($courses as $course) {
    $total_periods += $course['total_periods'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Smart Choice Academy</title>
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

        /* Course Cards */
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            height: 100%;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .course-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 15px 20px;
        }

        .course-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .course-code {
            font-size: 11px;
            opacity: 0.8;
        }

        .course-body {
            padding: 15px 20px;
        }

        .course-info {
            margin-bottom: 12px;
        }

        .course-info-label {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .course-info-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .course-stats {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #e9ecef;
            padding-top: 12px;
            margin-top: 10px;
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-item-value {
            font-size: 18px;
            font-weight: 700;
            color: #4e73df;
        }

        .stat-item-label {
            font-size: 9px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .course-actions {
            background: #f8f9fa;
            padding: 12px 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
        }

        .course-actions .btn {
            flex: 1;
            font-size: 12px;
            padding: 6px 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
            color: #6c757d;
        }

        /* Table View (Alternative) */
        .table-view {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .view-toggle {
            margin-bottom: 15px;
            text-align: right;
        }

        .view-toggle .btn {
            margin-left: 10px;
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
                            <i class="bi bi-book me-2"></i>My Courses
                        </h4>
                        <p class="mb-0 opacity-75">
                            <?= htmlspecialchars($teacher_name) ?> |
                            Academic Year: <?= htmlspecialchars($academic_year['year_name'] ?? date('Y')) ?>
                        </p>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-bookmark-check me-1"></i> Total: <?= $total_courses ?> Courses
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #4e73df;">
                        <div class="stat-number"><?= $total_courses ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #1cc88a;">
                        <div class="stat-number"><?= $total_classes_teaching ?></div>
                        <div class="stat-label">Classes Teaching</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #f6c23e;">
                        <div class="stat-number"><?= $total_periods ?></div>
                        <div class="stat-label">Total Periods/Week</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color: #36b9cc;">
                        <div class="stat-number">
                            <?php
                            $unique_sections = array_unique(array_column($courses, 'section_id'));
                            echo count($unique_sections);
                            ?>
                        </div>
                        <div class="stat-label">Sections Teaching</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-2"></i>Filter Courses</h6>
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
                        <input type="text" name="search" class="form-control" placeholder="Course name or code" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                        <?php if ($class_filter > 0 || $section_filter > 0 || !empty($search)): ?>
                            <a href="courses.php" class="btn btn-outline-secondary ms-2 w-100">
                                <i class="bi bi-arrow-repeat me-1"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- View Toggle -->
            <div class="view-toggle">
                <button class="btn btn-sm btn-outline-primary" id="cardViewBtn">
                    <i class="bi bi-grid-3x3-gap-fill"></i> Card View
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="tableViewBtn">
                    <i class="bi bi-table"></i> Table View
                </button>
            </div>

            <!-- Card View -->
            <div id="cardView">
                <?php if (empty($courses)): ?>
                    <div class="empty-state">
                        <i class="bi bi-book-x"></i>
                        <h5>No courses found</h5>
                        <p class="mb-0">You don't have any courses assigned for this academic year.</p>
                        <small class="text-muted">Contact the admin to assign courses to you.</small>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($courses as $course): ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="course-card">
                                    <div class="course-header">
                                        <div class="course-name"><?= htmlspecialchars($course['course_name']) ?></div>
                                        <div class="course-code">
                                            <i class="bi bi-hash"></i> <?= htmlspecialchars($course['course_code'] ?? 'No Code') ?>
                                        </div>
                                    </div>
                                    <div class="course-body">
                                        <div class="course-info">
                                            <div class="course-info-label">Class & Section</div>
                                            <div class="course-info-value">
                                                <?= htmlspecialchars($course['class_name']) ?> -
                                                <?= htmlspecialchars($course['section_name'] ?? 'All Sections') ?>
                                            </div>
                                        </div>
                                        <div class="course-stats">
                                            <div class="stat-item">
                                                <div class="stat-item-value"><?= $course['total_periods'] ?? 0 ?></div>
                                                <div class="stat-item-label">Periods/Week</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-item-value"><?= $course['total_sections'] ?? 1 ?></div>
                                                <div class="stat-item-label">Sections</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-item-value"><?= $course['total_students'] ?? 0 ?></div>
                                                <div class="stat-item-label">Students</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="course-actions">
                                        <a href="marks_entry.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil-square"></i> Marks
                                        </a>
                                        <a href="attendance.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-check2-square"></i> Attendance
                                        </a>
                                        <a href="syllabus.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-journal-bookmark-fill"></i> Syllabus
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Table View (Hidden by default) -->
            <div id="tableView" style="display: none;">
                <div class="table-view">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="coursesTable">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Course Code</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Periods/Week</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($course['course_name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($course['course_code'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($course['class_name']) ?> (Grade <?= $course['numeric_grade'] ?>)</td>
                                        <td><?= htmlspecialchars($course['section_name'] ?? 'All') ?></td>
                                        <td><span class="badge bg-primary"><?= $course['total_periods'] ?? 0 ?></span></td>
                                        <td><?= $course['total_students'] ?? 0 ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="marks_entry.php?course_id=<?= $course['id'] ?>" class="btn btn-outline-primary" title="Enter Marks">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="attendance.php?course_id=<?= $course['id'] ?>" class="btn btn-outline-success" title="Mark Attendance">
                                                    <i class="bi bi-check2-square"></i>
                                                </a>
                                                <a href="syllabus.php?course_id=<?= $course['id'] ?>" class="btn btn-outline-info" title="View Syllabus">
                                                    <i class="bi bi-journal-bookmark-fill"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
            // Initialize DataTable
            $('#coursesTable').DataTable({
                pageLength: 10,
                lengthChange: true,
                ordering: true,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ courses",
                    info: "Showing _START_ to _END_ of _TOTAL_ courses",
                    infoEmpty: "No courses found",
                    zeroRecords: "No matching courses found"
                }
            });

            // View toggle functionality
            $('#cardViewBtn').click(function() {
                $('#cardView').show();
                $('#tableView').hide();
                $(this).removeClass('btn-outline-primary').addClass('btn-primary');
                $('#tableViewBtn').removeClass('btn-primary').addClass('btn-outline-secondary');
            });

            $('#tableViewBtn').click(function() {
                $('#cardView').hide();
                $('#tableView').show();
                $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
                $('#cardViewBtn').removeClass('btn-primary').addClass('btn-outline-primary');

                // Redraw DataTable when switching to table view
                setTimeout(function() {
                    $('#coursesTable').DataTable().columns.adjust().draw();
                }, 100);
            });
        });
    </script>
</body>

</html>