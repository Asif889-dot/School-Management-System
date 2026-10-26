<?php

/**
 * Student Profile Page - Smart Choice Academy (Teacher View)
 * Teachers can view student details, marks, and attendance
 */

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

// Check if user has teacher role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id == 0) {
    header('Location: students_list.php');
    exit;
}

// Get teacher's assigned classes/sections
$teacher_id = $_SESSION['teacher_id'] ?? 0;
$assigned_classes = [];

if ($teacher_id > 0) {
    $class_query = "
        SELECT DISTINCT 
            s.class_id,
            s.section_id,
            c.class_name,
            sec.section_name
        FROM teacher_assignments ta
        JOIN sections sec ON ta.section_id = sec.id
        JOIN classes c ON sec.class_id = c.id
        JOIN students s ON s.class_id = c.id AND s.section_id = sec.id
        WHERE ta.teacher_id = ? AND ta.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 'yes' LIMIT 1)
    ";
    $class_stmt = $pdo->prepare($class_query);
    $class_stmt->execute([$teacher_id]);
    $assigned_classes = $class_stmt->fetchAll(PDO::FETCH_ASSOC);

    $assigned_class_ids = array_unique(array_column($assigned_classes, 'class_id'));
    $assigned_section_ids = array_unique(array_column($assigned_classes, 'section_id'));
}

// Fetch student details with joins - only if teacher has access
$query = "
    SELECT 
        s.*, 
        c.class_name, 
        c.numeric_grade,
        sec.section_name,
        ay.year_name as academic_year_name,
        ay.start_date as academic_start,
        ay.end_date as academic_end
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.id = ? AND s.status = 'approved'
";

$stmt = $pdo->prepare($query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: students_list.php?error=Student not found');
    exit;
}

// Verify teacher has access to this student
if (!empty($assigned_class_ids) && !in_array($student['class_id'], $assigned_class_ids)) {
    header('Location: students_list.php?error=You do not have permission to view this student');
    exit;
}

// Fetch exam results (only subjects taught by this teacher)
$exam_results = [];
try {
    $exam_query = "
        SELECT 
            em.*,
            et.term_name,
            et.session_year,
            c.course_name
        FROM exam_marks em
        JOIN exam_terms et ON em.term_id = et.id
        JOIN courses c ON em.subject_name = c.course_name
        WHERE em.student_id = ? AND em.class_id = ?
        ORDER BY em.created_at DESC
        LIMIT 20
    ";
    $exam_stmt = $pdo->prepare($exam_query);
    $exam_stmt->execute([$student_id, $student['class_id']]);
    $exam_results = $exam_stmt->fetchAll();
} catch (PDOException $e) {
    $exam_results = [];
}

// Fetch attendance summary for current month
$attendance = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'late_days' => 0];
$attendance_percentage = 0;
$monthly_attendance = [];

try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_records'");
    if ($table_check->rowCount() > 0) {
        // Get current month attendance
        $attendance_query = "
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
            FROM attendance_records
            WHERE student_id = ? AND MONTH(attendance_date) = MONTH(CURDATE()) AND YEAR(attendance_date) = YEAR(CURDATE())
        ";
        $attendance_stmt = $pdo->prepare($attendance_query);
        $attendance_stmt->execute([$student_id]);
        $attendance_data = $attendance_stmt->fetch(PDO::FETCH_ASSOC);

        if ($attendance_data) {
            $attendance = $attendance_data;
            if ($attendance['total_days'] > 0) {
                $attendance_percentage = round(($attendance['present_days'] / $attendance['total_days']) * 100, 1);
            }
        }

        // Get daily attendance for the month (for chart display)
        $daily_query = "
            SELECT 
                attendance_date,
                status,
                check_in_time,
                check_out_time
            FROM attendance_records
            WHERE student_id = ? AND MONTH(attendance_date) = MONTH(CURDATE())
            ORDER BY attendance_date DESC
            LIMIT 30
        ";
        $daily_stmt = $pdo->prepare($daily_query);
        $daily_stmt->execute([$student_id]);
        $monthly_attendance = $daily_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $attendance = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'late_days' => 0];
    $attendance_percentage = 0;
}

// Get student photo path
function getDocumentPath($db_path)
{
    if (empty($db_path)) {
        return null;
    }
    $full_path = '../../' . $db_path;
    if (file_exists($full_path)) {
        return $full_path;
    }
    return null;
}

function getStudentPhoto($photo_path)
{
    if (empty($photo_path)) {
        return null;
    }
    $full_path = '../../' . $photo_path;
    if (file_exists($full_path)) {
        return $full_path;
    }
    return null;
}

$photo_path = getStudentPhoto($student['photo']);
$full_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);

// Calculate average marks
$avg_percentage = 0;
if (!empty($exam_results)) {
    $total_percentage = 0;
    foreach ($exam_results as $result) {
        $total_percentage += ($result['obtained_marks'] / $result['total_marks']) * 100;
    }
    $avg_percentage = round($total_percentage / count($exam_results), 1);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - <?= $full_name ?> | Smart Choice Academy (Teacher View)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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

        .profile-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            color: white;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar .placeholder {
            font-size: 60px;
            color: #28a745;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: #28a745;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .info-title {
            font-size: 16px;
            font-weight: 700;
            color: #28a745;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: #555;
        }

        .info-value {
            flex: 1;
            color: #333;
        }

        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .attendance-badge-present {
            background: #d4edda;
            color: #155724;
        }

        .attendance-badge-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .attendance-badge-late {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .profile-header {
                background: #28a745 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        .performance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .performance-number {
            font-size: 36px;
            font-weight: 800;
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">

            <!-- Header with Actions -->
            <div class="no-print d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">
                        <i class="bi bi-person-badge text-success me-2"></i>Student Profile
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="students_list.php">My Students</a></li>
                            <li class="breadcrumb-item active"><?= $full_name ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="add_marks.php?student_id=<?= $student_id ?>" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle me-2"></i>Add Marks
                    </a>
                    <a href="mark_attendance.php?student_id=<?= $student_id ?>" class="btn btn-info me-2">
                        <i class="bi bi-calendar-check me-2"></i>Mark Attendance
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="bi bi-printer me-2"></i>Print Profile
                    </button>
                </div>
            </div>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="profile-avatar mx-auto">
                            <?php if ($photo_path): ?>
                                <img src="<?= $photo_path ?>" alt="Student Photo">
                            <?php else: ?>
                                <div class="placeholder">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h2 class="mb-1"><?= $full_name ?></h2>
                        <p class="mb-2">
                            <i class="bi bi-mortarboard me-2"></i>
                            <?= htmlspecialchars($student['class_name']) ?> - <?= htmlspecialchars($student['section_name'] ?? 'No Section') ?>
                            | Reg ID: <?= htmlspecialchars($student['reg_id']) ?>
                        </p>
                        <p class="mb-0">
                            <span class="badge-status badge-approved">
                                <i class="bi bi-check-circle me-1"></i>Active Student
                            </span>
                        </p>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <div class="performance-card p-3 rounded">
                            <small>Academic Performance</small>
                            <div class="performance-number"><?= $avg_percentage ?>%</div>
                            <small>Average Marks</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $attendance_percentage ?>%</div>
                        <div class="stat-label">Attendance (This Month)</div>
                        <small class="text-muted">Present: <?= $attendance['present_days'] ?? 0 ?> / Total: <?= $attendance['total_days'] ?? 0 ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count($exam_results) ?></div>
                        <div class="stat-label">Exam Results</div>
                        <small class="text-muted">Total examinations</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $monthly_attendance ? count($monthly_attendance) : 0 ?></div>
                        <div class="stat-label">Days Recorded</div>
                        <small class="text-muted">This month</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= date('Y') - date('Y', strtotime($student['admission_date'])) ?></div>
                        <div class="stat-label">Years at School</div>
                        <small class="text-muted">Since <?= date('M Y', strtotime($student['admission_date'])) ?></small>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Personal Information (Limited view for teachers) -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="info-title">
                            <i class="bi bi-person-circle me-2"></i>Student Information
                        </h5>
                        <div class="info-row">
                            <div class="info-label">Full Name:</div>
                            <div class="info-value"><?= $full_name ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Registration ID:</div>
                            <div class="info-value"><?= htmlspecialchars($student['reg_id']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Gender:</div>
                            <div class="info-value"><?= htmlspecialchars($student['gender']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Birth:</div>
                            <div class="info-value"><?= date('d-m-Y', strtotime($student['date_of_birth'])) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Religion:</div>
                            <div class="info-value"><?= htmlspecialchars($student['religion']) ?></div>
                        </div>
                    </div>

                    <!-- Parent Information (Limited view) -->
                    <div class="info-card">
                        <h5 class="info-title">
                            <i class="bi bi-people me-2"></i>Emergency Contact
                        </h5>
                        <div class="info-row">
                            <div class="info-label">Father's Name:</div>
                            <div class="info-value"><?= htmlspecialchars($student['father_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Father's Phone:</div>
                            <div class="info-value"><?= htmlspecialchars($student['father_phone'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Mother's Name:</div>
                            <div class="info-value"><?= htmlspecialchars($student['mother_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Mother's Phone:</div>
                            <div class="info-value"><?= htmlspecialchars($student['mother_phone'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="info-title">
                            <i class="bi bi-mortarboard me-2"></i>Academic Information
                        </h5>
                        <div class="info-row">
                            <div class="info-label">Class:</div>
                            <div class="info-value"><?= htmlspecialchars($student['class_name']) ?> (Grade <?= $student['numeric_grade'] ?>)</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Section:</div>
                            <div class="info-value"><?= htmlspecialchars($student['section_name'] ?? 'Not Assigned') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Academic Year:</div>
                            <div class="info-value"><?= htmlspecialchars($student['academic_year_name'] ?? 'Current Year') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Admission Date:</div>
                            <div class="info-value"><?= date('d-m-Y', strtotime($student['admission_date'])) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Admission Type:</div>
                            <div class="info-value"><?= htmlspecialchars($student['admission_type']) ?></div>
                        </div>
                    </div>

                    <!-- Quick Actions for Teacher -->
                    <div class="info-card">
                        <h5 class="info-title">
                            <i class="bi bi-gear me-2"></i>Quick Actions
                        </h5>
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="add_marks.php?student_id=<?= $student_id ?>" class="btn btn-outline-success w-100">
                                    <i class="bi bi-pencil-square me-2"></i>Enter Marks
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="mark_attendance.php?student_id=<?= $student_id ?>" class="btn btn-outline-info w-100">
                                    <i class="bi bi-calendar-check me-2"></i>Mark Attendance
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="write_comment.php?student_id=<?= $student_id ?>" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-chat-dots me-2"></i>Add Comment
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="view_reports.php?student_id=<?= $student_id ?>" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-graph-up me-2"></i>View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance -->
            <?php if (!empty($monthly_attendance)): ?>
                <div class="info-card">
                    <h5 class="info-title">
                        <i class="bi bi-calendar-week me-2"></i>Recent Attendance (Last 10 Days)
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($monthly_attendance, 0, 10) as $record):
                                    $status_class = $record['status'] == 'present' ? 'attendance-badge-present' : ($record['status'] == 'late' ? 'attendance-badge-late' : 'attendance-badge-absent');
                                    $status_icon = $record['status'] == 'present' ? 'check-circle' : ($record['status'] == 'late' ? 'clock' : 'x-circle');
                                ?>
                                    <tr>
                                        <td><?= date('d-m-Y', strtotime($record['attendance_date'])) ?></td>
                                        <td>
                                            <span class="badge <?= $status_class ?>">
                                                <i class="bi bi-<?= $status_icon ?> me-1"></i><?= ucfirst($record['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '—' ?> </td>
                                        <td><?= $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '—' ?> </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Exam Results Table -->
            <?php if (!empty($exam_results)): ?>
                <div class="info-card">
                    <h5 class="info-title">
                        <i class="bi bi-bar-chart-steps me-2"></i>Exam Results
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>Term</th>
                                    <th>Subject</th>
                                    <th>Obtained Marks</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exam_results as $result):
                                    $percentage = ($result['obtained_marks'] / $result['total_marks']) * 100;
                                    $status_class = $percentage >= 40 ? 'success' : 'danger';
                                    $status_text = $percentage >= 40 ? 'Pass' : 'Fail';

                                    // Calculate grade
                                    if ($percentage >= 80) $grade = 'A+';
                                    elseif ($percentage >= 70) $grade = 'A';
                                    elseif ($percentage >= 60) $grade = 'B';
                                    elseif ($percentage >= 50) $grade = 'C';
                                    elseif ($percentage >= 40) $grade = 'D';
                                    else $grade = 'F';
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($result['term_name']) ?></td>
                                        <td><?= htmlspecialchars($result['subject_name']) ?></td>
                                        <td><?= $result['obtained_marks'] ?></td>
                                        <td><?= $result['total_marks'] ?></td>
                                        <td>
                                            <div class="progress" style="height: 6px; width: 80px;">
                                                <div class="progress-bar bg-<?= $status_class ?>" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                            <?= round($percentage, 1) ?>%
                                        </td>
                                        <td><span class="badge bg-secondary"><?= $grade ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Teacher Notes Section -->
            <div class="info-card">
                <h5 class="info-title">
                    <i class="bi bi-journal-text me-2"></i>Teacher's Notes
                </h5>
                <div class="row">
                    <div class="col-12">
                        <form action="save_note.php" method="POST" class="mb-3">
                            <input type="hidden" name="student_id" value="<?= $student_id ?>">
                            <div class="mb-2">
                                <label class="form-label">Add a note about this student</label>
                                <textarea name="note" class="form-control" rows="3" placeholder="Write your observations, recommendations, or notes here..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-save me-1"></i>Save Note
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Attendance Note if table doesn't exist -->
            <?php if ($attendance['total_days'] == 0 && $attendance_percentage == 0 && empty($monthly_attendance)): ?>
                <div class="alert alert-info no-print">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Attendance Module:</strong> No attendance records found for this month.
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
            $('#resultsTable').DataTable({
                pageLength: 10,
                lengthChange: true,
                ordering: true,
                order: [
                    [0, 'desc']
                ],
                language: {
                    search: "Search results:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries"
                }
            });
        });
    </script>

</body>

</html>