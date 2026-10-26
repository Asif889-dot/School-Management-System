<?php

/**
 * Attendance Management - Teacher View
 * Smart Choice Academy
 * Teachers can mark attendance based on their timetable schedule
 * Once marked, attendance cannot be modified
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

// Get parameters
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selected_period_id = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;

// Create attendance table if not exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        section_id INT NOT NULL,
        course_id INT NOT NULL,
        teacher_id INT NOT NULL,
        academic_year_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        period_id INT,
        status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'absent',
        remarks TEXT,
        marked_by INT,
        is_locked TINYINT DEFAULT 0,
        locked_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student_date (student_id, attendance_date),
        INDEX idx_class_date (class_id, attendance_date),
        INDEX idx_course_date (course_id, attendance_date),
        UNIQUE KEY unique_attendance (student_id, attendance_date, course_id, period_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check if columns exist and add them if not
    $columns = $db->query("SHOW COLUMNS FROM attendance");
    $existing_columns = [];
    while ($col = $columns->fetch()) {
        $existing_columns[] = $col['Field'];
    }

    if (!in_array('marked_time', $existing_columns)) {
        $db->exec("ALTER TABLE attendance ADD COLUMN marked_time TIME DEFAULT NULL");
    }
    if (!in_array('is_late_marked', $existing_columns)) {
        $db->exec("ALTER TABLE attendance ADD COLUMN is_late_marked TINYINT DEFAULT 0");
    }
    if (!in_array('is_locked', $existing_columns)) {
        $db->exec("ALTER TABLE attendance ADD COLUMN is_locked TINYINT DEFAULT 0");
    }
    if (!in_array('locked_at', $existing_columns)) {
        $db->exec("ALTER TABLE attendance ADD COLUMN locked_at TIMESTAMP NULL");
    }
} catch (PDOException $e) {
    error_log("Attendance table error: " . $e->getMessage());
}

// Check if attendance is already marked for this period
function isAttendanceLocked($db, $class_id, $section_id, $course_id, $period_id, $attendance_date)
{
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, MAX(is_locked) as is_locked 
        FROM attendance 
        WHERE class_id = ? AND section_id = ? AND course_id = ? AND period_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$class_id, $section_id, $course_id, $period_id, $attendance_date]);
    $result = $stmt->fetch();
    return $result['count'] > 0 && $result['is_locked'] == 1;
}

// Handle attendance submission - ONLY IF NOT ALREADY MARKED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $attendance_date = $_POST['attendance_date'];
    $class_id = (int)$_POST['class_id'];
    $section_id = (int)$_POST['section_id'];
    $course_id = (int)$_POST['course_id'];
    $period_id = (int)$_POST['period_id'];
    $present_students = isset($_POST['present']) ? $_POST['present'] : [];
    $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';

    // Check if attendance is already locked
    if (isAttendanceLocked($db, $class_id, $section_id, $course_id, $period_id, $attendance_date)) {
        $error_msg = "Attendance has already been marked for this period and cannot be modified!";
    } else {
        // Get period times to validate
        $stmt = $db->prepare("SELECT start_time, end_time FROM time_slots WHERE id = ?");
        $stmt->execute([$period_id]);
        $period = $stmt->fetch();

        if ($period) {
            $can_mark = canMarkAttendance($period['start_time'], $period['end_time'], $attendance_date);

            if (!$can_mark['can_mark']) {
                $error_msg = "Cannot save attendance: " . $can_mark['message'];
            } else {
                try {
                    $db->beginTransaction();

                    // Get all students
                    $stmt = $db->prepare("
                        SELECT id, first_name, last_name, reg_id 
                        FROM students 
                        WHERE class_id = ? AND section_id = ? AND status = 'approved'
                    ");
                    $stmt->execute([$class_id, $section_id]);
                    $students = $stmt->fetchAll();

                    $current_time = date('H:i:s');
                    $is_late = ($can_mark['reason'] == 'period_over') ? 1 : 0;

                    $saved_count = 0;
                    $updated_count = 0;

                    foreach ($students as $student) {
                        $student_id = $student['id'];
                        $status = in_array($student_id, $present_students) ? 'present' : 'absent';

                        if ($status == 'present' && $is_late) {
                            $status = 'late';
                        }

                        // Check if attendance already exists
                        $check_stmt = $db->prepare("
                            SELECT id FROM attendance 
                            WHERE student_id = ? AND attendance_date = ? AND course_id = ? AND period_id = ?
                        ");
                        $check_stmt->execute([$student_id, $attendance_date, $course_id, $period_id]);

                        if ($check_stmt->fetch()) {
                            // Update existing but don't allow if locked
                            $update_stmt = $db->prepare("
                                UPDATE attendance 
                                SET status = ?, remarks = ?, marked_time = ?, is_late_marked = ?, updated_at = NOW()
                                WHERE student_id = ? AND attendance_date = ? AND course_id = ? AND period_id = ? AND is_locked = 0
                            ");
                            $update_stmt->execute([$status, $remarks, $current_time, $is_late, $student_id, $attendance_date, $course_id, $period_id]);
                            $updated_count++;
                        } else {
                            // Insert new
                            $insert_stmt = $db->prepare("
                                INSERT INTO attendance (student_id, class_id, section_id, course_id, teacher_id, academic_year_id, attendance_date, period_id, status, marked_time, is_late_marked, remarks, marked_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $insert_stmt->execute([
                                $student_id,
                                $class_id,
                                $section_id,
                                $course_id,
                                $teacher_id,
                                $academic_year_id,
                                $attendance_date,
                                $period_id,
                                $status,
                                $current_time,
                                $is_late,
                                $remarks,
                                $user_id
                            ]);
                            $saved_count++;
                        }
                    }

                    // Lock the attendance for this period (prevent future modifications)
                    $lock_stmt = $db->prepare("
                        UPDATE attendance 
                        SET is_locked = 1, locked_at = NOW() 
                        WHERE class_id = ? AND section_id = ? AND course_id = ? AND period_id = ? AND attendance_date = ?
                    ");
                    $lock_stmt->execute([$class_id, $section_id, $course_id, $period_id, $attendance_date]);

                    $db->commit();
                    $late_msg = $is_late ? " (Marked as LATE - Period was over)" : "";
                    $success_msg = "Attendance saved successfully and locked! ($saved_count new, $updated_count updated)$late_msg";

                    // Refresh the page to show locked state
                    echo "<script>setTimeout(function() { window.location.href = window.location.href; }, 1500);</script>";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error_msg = "Error saving attendance: " . $e->getMessage();
                }
            }
        }
    }
}

// Helper function to check if attendance can be marked
function canMarkAttendance($period_start_time, $period_end_time, $selected_date)
{
    $current_datetime = new DateTime();
    $current_time = $current_datetime->format('H:i:s');
    $selected_datetime = new DateTime($selected_date);

    // If selected date is in the future, cannot mark attendance
    if ($selected_datetime > $current_datetime) {
        return ['can_mark' => false, 'reason' => 'future_date', 'message' => 'Cannot mark attendance for future dates'];
    }

    // If selected date is in the past, can mark
    if ($selected_datetime < $current_datetime) {
        return ['can_mark' => true, 'reason' => 'past_date', 'message' => 'Marking attendance for a past date'];
    }

    // For current date, check period time
    if ($period_end_time < $current_time) {
        return ['can_mark' => true, 'reason' => 'period_over', 'message' => 'Period is over. You are marking late attendance.'];
    }

    if ($period_start_time <= $current_time && $current_time <= $period_end_time) {
        return ['can_mark' => true, 'reason' => 'within_period', 'message' => 'Within period time'];
    }

    if ($current_time < $period_start_time) {
        return ['can_mark' => false, 'reason' => 'period_not_started', 'message' => 'Period has not started yet'];
    }

    return ['can_mark' => true, 'reason' => 'allowed', 'message' => 'Attendance can be marked'];
}

// Fetch teacher's timetable for the selected date
$day_name = date('l', strtotime($selected_date));
$stmt = $db->prepare("
    SELECT sd.id as day_id 
    FROM school_days sd 
    WHERE sd.day_name = ? AND sd.is_working = 1
");
$stmt->execute([$day_name]);
$day = $stmt->fetch();
$day_id = $day['day_id'] ?? 0;

// Get teacher's schedule with period times and attendance status
$schedule_query = "
    SELECT 
        tr.id as schedule_id,
        tr.class_id,
        tr.section_id,
        tr.course_id,
        tr.slot_id,
        ts.period_number,
        ts.slot_name,
        ts.start_time,
        ts.end_time,
        c.class_name,
        c.numeric_grade,
        sec.section_name,
        cr.course_name,
        cr.course_code,
        CASE 
            WHEN a.is_locked = 1 THEN 'locked'
            WHEN CURTIME() < ts.start_time THEN 'upcoming'
            WHEN CURTIME() BETWEEN ts.start_time AND ts.end_time THEN 'ongoing'
            WHEN CURTIME() > ts.end_time THEN 'completed'
            ELSE 'unknown'
        END as period_status,
        a.is_locked as attendance_locked,
        COUNT(DISTINCT a.id) as attendance_count
    FROM timetable_records tr
    JOIN classes c ON tr.class_id = c.id
    JOIN sections sec ON tr.section_id = sec.id
    JOIN courses cr ON tr.course_id = cr.id
    JOIN time_slots ts ON tr.slot_id = ts.id
    LEFT JOIN attendance a ON tr.class_id = a.class_id 
        AND tr.section_id = a.section_id 
        AND tr.course_id = a.course_id 
        AND tr.slot_id = a.period_id 
        AND a.attendance_date = ?
    WHERE tr.teacher_id = ? 
        AND tr.academic_year_id = ?
        AND tr.day_id = ?
    GROUP BY tr.id
";

$params = [$selected_date, $teacher_id, $academic_year_id, $day_id];

if ($selected_class_id > 0) {
    $schedule_query .= " AND tr.class_id = ?";
    $params[] = $selected_class_id;
}
if ($selected_section_id > 0) {
    $schedule_query .= " AND tr.section_id = ?";
    $params[] = $selected_section_id;
}
if ($selected_course_id > 0) {
    $schedule_query .= " AND tr.course_id = ?";
    $params[] = $selected_course_id;
}

$schedule_query .= " ORDER BY ts.period_number ASC";

$stmt = $db->prepare($schedule_query);
$stmt->execute($params);
$schedule = $stmt->fetchAll();

// Get classes for filters
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

// Get students for selected period
$students = [];
$period_info = null;
$can_mark_attendance = ['can_mark' => true, 'reason' => '', 'message' => ''];
$period_status = '';
$is_already_locked = false;

if ($selected_course_id > 0 && $selected_period_id > 0) {
    // Check if attendance is already locked for this period
    $is_already_locked = isAttendanceLocked($db, $selected_class_id, $selected_section_id, $selected_course_id, $selected_period_id, $selected_date);

    // Get period info
    $stmt = $db->prepare("SELECT start_time, end_time FROM time_slots WHERE id = ?");
    $stmt->execute([$selected_period_id]);
    $period_info = $stmt->fetch();

    if ($period_info) {
        $can_mark_attendance = canMarkAttendance($period_info['start_time'], $period_info['end_time'], $selected_date);

        // Determine period status for display
        $current_time = date('H:i:s');
        if ($is_already_locked) {
            $period_status = 'locked';
        } elseif ($selected_date == date('Y-m-d')) {
            if ($current_time < $period_info['start_time']) {
                $period_status = 'upcoming';
            } elseif ($current_time >= $period_info['start_time'] && $current_time <= $period_info['end_time']) {
                $period_status = 'ongoing';
            } else {
                $period_status = 'completed';
            }
        } else {
            $period_status = 'past';
        }
    }

    // Get students with attendance status
    $stmt = $db->prepare("
        SELECT s.id, s.reg_id, s.first_name, s.last_name, s.gender, s.photo,
               a.status as attendance_status, a.remarks as attendance_remarks,
               a.marked_time, a.is_late_marked, a.is_locked
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id 
            AND a.attendance_date = ? 
            AND a.course_id = ? 
            AND a.period_id = ?
        WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'approved'
        ORDER BY s.first_name
    ");
    $stmt->execute([$selected_date, $selected_course_id, $selected_period_id, $selected_class_id, $selected_section_id]);
    $students = $stmt->fetchAll();
}

// Get existing attendance statistics
$attendance_stats = [];
if ($selected_course_id > 0 && $selected_period_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused,
            MAX(is_locked) as is_locked
        FROM attendance 
        WHERE attendance_date = ? AND course_id = ? AND period_id = ?
    ");
    $stmt->execute([$selected_date, $selected_course_id, $selected_period_id]);
    $attendance_stats = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
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

        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            color: white;
        }

        .schedule-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .schedule-header {
            background: #f8f9fa;
            padding: 12px 15px;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
        }

        .schedule-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background 0.2s;
        }

        .schedule-item:hover {
            background: #f8f9fa;
        }

        .schedule-item.active {
            background: #e7f1ff;
            border-left: 3px solid #4e73df;
        }

        .schedule-item.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f5f5f5;
        }

        .schedule-item.locked {
            background: #d4edda;
            opacity: 0.8;
            cursor: default;
        }

        .time-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .time-badge.upcoming {
            background: #ffc107;
            color: #856404;
        }

        .time-badge.ongoing {
            background: #28a745;
            color: white;
        }

        .time-badge.completed {
            background: #6c757d;
            color: white;
        }

        .time-badge.locked {
            background: #198754;
            color: white;
        }

        .warning-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .danger-banner {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .info-banner {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .success-banner {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .attendance-table th {
            background: #f8f9fa;
            padding: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .attendance-table td {
            padding: 10px 12px;
            vertical-align: middle;
        }

        .student-photo {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .attendance-switch {
            width: 60px;
            height: 30px;
            background: #dc3545;
            border-radius: 30px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
        }

        .attendance-switch.present {
            background: #28a745;
        }

        .attendance-switch.late {
            background: #ffc107;
        }

        .attendance-switch .slider {
            width: 26px;
            height: 26px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }

        .attendance-switch.present .slider {
            transform: translateX(30px);
        }

        .attendance-switch.late .slider {
            transform: translateX(30px);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-present {
            background: #d4edda;
            color: #155724;
        }

        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .status-late {
            background: #fff3cd;
            color: #856404;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .stats-number {
            font-size: 24px;
            font-weight: 700;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .disabled-overlay {
            position: relative;
            pointer-events: none;
            opacity: 0.6;
        }

        .locked-badge {
            background: #198754;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
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
                        <h4 class="fw-bold mb-1"><i class="bi bi-check2-square me-2"></i>Mark Attendance</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($teacher_name) ?> | Date: <?= date('l, F j, Y', strtotime($selected_date)) ?></p>
                    </div>
                    <div>
                        <input type="date" id="datePicker" class="form-control" value="<?= $selected_date ?>" onchange="changeDate()" max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Select Class</label>
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes_taught as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $selected_class_id == $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="date" value="<?= $selected_date ?>">
                    <input type="hidden" name="course_id" id="hidden_course_id" value="<?= $selected_course_id ?>">
                    <input type="hidden" name="period_id" id="hidden_period_id" value="<?= $selected_period_id ?>">
                    <div class="col-md-2 d-flex align-items-end">
                        <?php if ($selected_class_id > 0): ?>
                            <a href="attendance.php?date=<?= $selected_date ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-repeat me-1"></i> Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= $success_msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= $error_msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Warning Messages -->
            <?php if ($is_already_locked): ?>
                <div class="success-banner">
                    <i class="bi bi-lock-fill me-2"></i>
                    <strong>Attendance Already Marked!</strong> Attendance has been recorded for this period and cannot be modified.
                </div>
            <?php elseif ($selected_date == date('Y-m-d') && $period_status == 'upcoming'): ?>
                <div class="warning-banner"><i class="bi bi-clock-history me-2"></i><strong>Period Not Started Yet!</strong> You can mark attendance only when the period begins.</div>
            <?php elseif ($selected_date == date('Y-m-d') && $period_status == 'completed' && $can_mark_attendance['can_mark']): ?>
                <div class="warning-banner"><i class="bi bi-exclamation-triangle me-2"></i><strong>Period is Over!</strong> Attendance will be marked as <strong>LATE</strong>.</div>
            <?php elseif ($selected_date < date('Y-m-d')): ?>
                <div class="info-banner"><i class="bi bi-calendar-date me-2"></i><strong>Past Date Attendance</strong> You are marking attendance for a previous date.</div>
            <?php endif; ?>

            <div class="row">
                <!-- Left Column: Schedule -->
                <div class="col-lg-4 mb-4">
                    <div class="schedule-card">
                        <div class="schedule-header"><i class="bi bi-calendar-week me-2"></i> Today's Schedule</div>
                        <?php if (empty($schedule)): ?>
                            <div class="text-center py-5 text-muted"><i class="bi bi-calendar-x display-6 d-block mb-3"></i>
                                <p>No classes scheduled for today</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($schedule as $schedule_item):
                                $status_class = '';
                                $status_text = '';
                                $disabled = false;
                                $is_locked = $schedule_item['attendance_locked'] == 1;

                                if ($is_locked) {
                                    $status_class = 'locked';
                                    $status_text = 'Attendance Marked';
                                    $disabled = true;
                                } elseif ($selected_date == date('Y-m-d')) {
                                    if ($schedule_item['period_status'] == 'upcoming') {
                                        $status_class = 'upcoming';
                                        $status_text = 'Upcoming';
                                        $disabled = true;
                                    } elseif ($schedule_item['period_status'] == 'ongoing') {
                                        $status_class = 'ongoing';
                                        $status_text = 'Ongoing';
                                    } else {
                                        $status_class = 'completed';
                                        $status_text = 'Completed';
                                    }
                                } else {
                                    $status_class = 'completed';
                                    $status_text = 'Past Date';
                                }

                                $disabled_class = ($disabled || $is_locked) ? 'disabled' : '';
                                $locked_class = $is_locked ? 'locked' : '';
                            ?>
                                <div class="schedule-item <?= ($selected_course_id == $schedule_item['course_id'] && $selected_period_id == $schedule_item['slot_id']) ? 'active' : '' ?> <?= $disabled_class ?> <?= $locked_class ?>"
                                    onclick="<?= (!$disabled && !$is_locked) ? "selectPeriod({$schedule_item['course_id']}, {$schedule_item['slot_id']}, {$schedule_item['class_id']}, {$schedule_item['section_id']})" : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="time-badge <?= $status_class ?>">
                                                <?= date('h:i A', strtotime($schedule_item['start_time'])) ?> - <?= date('h:i A', strtotime($schedule_item['end_time'])) ?>
                                            </span>
                                            <?php if ($is_locked): ?>
                                                <span class="locked-badge ms-2"><i class="bi bi-lock-fill me-1"></i> Locked</span>
                                            <?php endif; ?>
                                            <div class="fw-bold mt-2"><?= htmlspecialchars($schedule_item['course_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($schedule_item['class_name']) ?> - <?= htmlspecialchars($schedule_item['section_name']) ?></div>
                                            <div class="period-status text-<?= $status_class == 'ongoing' ? 'success' : ($status_class == 'upcoming' ? 'warning' : 'secondary') ?>">
                                                <i class="bi bi-<?= $status_class == 'ongoing' ? 'play-circle' : ($status_class == 'upcoming' ? 'clock' : ($is_locked ? 'lock-fill' : 'check-circle')) ?> me-1"></i><?= $status_text ?>
                                            </div>
                                        </div>
                                        <i class="bi bi-chevron-right fs-4 text-muted"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Attendance Form -->
                <div class="col-lg-8">
                    <?php if ($selected_course_id > 0 && $selected_period_id > 0 && !empty($students)): ?>
                        <!-- Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number text-primary"><?= count($students) ?></div>
                                    <div class="small text-muted">Total Students</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number text-success"><?= $attendance_stats['present'] ?? 0 ?></div>
                                    <div class="small text-muted">Present</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number text-warning"><?= $attendance_stats['late'] ?? 0 ?></div>
                                    <div class="small text-muted">Late</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number text-danger"><?= $attendance_stats['absent'] ?? 0 ?></div>
                                    <div class="small text-muted">Absent</div>
                                </div>
                            </div>
                        </div>

                        <!-- Period Info Banner -->
                        <div class="info-banner mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><i class="bi bi-info-circle me-2"></i><strong><?= htmlspecialchars($period_info ? date('h:i A', strtotime($period_info['start_time'])) . ' - ' . date('h:i A', strtotime($period_info['end_time'])) : 'Period Time') ?></strong></div>
                                <div>
                                    <?php if ($is_already_locked): ?>
                                        <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i> Attendance Locked - Cannot Modify</span>
                                    <?php elseif ($selected_date == date('Y-m-d') && $period_status == 'ongoing'): ?>
                                        <span class="badge bg-success">Period Active - Can Mark Attendance</span>
                                    <?php elseif ($selected_date == date('Y-m-d') && $period_status == 'completed'): ?>
                                        <span class="badge bg-warning">Period Ended - Will Mark as LATE</span>
                                    <?php elseif ($selected_date < date('Y-m-d')): ?>
                                        <span class="badge bg-info">Past Date - Can Mark Attendance</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Period Not Started</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Form -->
                        <div class="schedule-card <?= ($is_already_locked || (!$can_mark_attendance['can_mark'] && $selected_date == date('Y-m-d'))) ? 'disabled-overlay' : '' ?>">
                            <div class="schedule-header">
                                <i class="bi bi-people me-2"></i>
                                <?= $is_already_locked ? 'Attendance Record (Locked)' : 'Mark Attendance' ?>
                            </div>

                            <?php if (!$is_already_locked && ($can_mark_attendance['can_mark'] || $selected_date < date('Y-m-d'))): ?>
                                <form method="POST" id="attendanceForm">
                                    <input type="hidden" name="action" value="save_attendance">
                                    <input type="hidden" name="attendance_date" value="<?= $selected_date ?>">
                                    <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
                                    <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
                                    <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
                                    <input type="hidden" name="period_id" value="<?= $selected_period_id ?>">

                                    <div class="table-responsive">
                                        <table class="table attendance-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Photo</th>
                                                    <th>Reg ID</th>
                                                    <th>Student Name</th>
                                                    <th>Gender</th>
                                                    <th>Attendance</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students as $student):
                                                    $is_present = $student['attendance_status'] == 'present';
                                                    $is_late = $student['attendance_status'] == 'late';
                                                    $switch_class = $is_present ? 'present' : ($is_late ? 'late' : '');
                                                ?>
                                                    <tr>
                                                        <td><?php if (!empty($student['photo']) && file_exists('../../' . $student['photo'])): ?><img src="../../<?= $student['photo'] ?>" class="student-photo"><?php else: ?><div class="student-photo bg-light d-flex align-items-center justify-content-center"><i class="bi bi-person text-muted"></i></div><?php endif; ?></td>
                                                        <td><?= htmlspecialchars($student['reg_id']) ?></td>
                                                        <td><strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong><?php if ($student['marked_time']): ?><br><small class="text-muted">Marked: <?= date('h:i A', strtotime($student['marked_time'])) ?></small><?php endif; ?></td>
                                                        <td><span class="badge <?= $student['gender'] == 'Male' ? 'bg-primary' : 'bg-danger' ?>"><?= $student['gender'] ?></span></td>
                                                        <td>
                                                            <div class="attendance-switch <?= $switch_class ?>" onclick="toggleAttendance(this, <?= $student['id'] ?>)">
                                                                <div class="slider"></div>
                                                            </div>
                                                            <input type="hidden" name="present[]" value="<?= $student['id'] ?>" class="attendance-input" <?= !$is_present && !$is_late ? 'disabled' : '' ?>>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge <?= $is_present ? 'status-present' : ($is_late ? 'status-late' : 'status-absent') ?>">
                                                                <?= $is_present ? 'Present' : ($is_late ? 'Late' : 'Absent') ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="p-3 bg-light border-top">
                                        <div class="row">
                                            <div class="col-md-6"><label class="form-label">Remarks (Optional)</label><textarea name="remarks" class="form-control" rows="2" placeholder="Any notes about today's attendance..."></textarea></div>
                                            <div class="col-md-6 d-flex align-items-end">
                                                <div class="btn-group w-100"><button type="button" class="btn btn-outline-success" onclick="markAllPresent()"><i class="bi bi-check-all me-1"></i> Mark All Present</button><button type="button" class="btn btn-outline-danger" onclick="markAllAbsent()"><i class="bi bi-x-circle me-1"></i> Mark All Absent</button></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-3 text-end border-top"><button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i> Save & Lock Attendance</button></div>
                                </form>
                            <?php elseif ($is_already_locked): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-lock-fill display-4 d-block mb-3 text-success"></i>
                                    <h5>Attendance Already Marked</h5>
                                    <p class="text-muted">Attendance has already been recorded for this period and cannot be modified.</p>
                                    <small>Locked on: <?= date('d-m-Y h:i A', strtotime($attendance_stats['locked_at'] ?? 'now')) ?></small>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-clock-history display-4 d-block mb-3 text-muted"></i>
                                    <h5>Attendance Not Available Yet</h5>
                                    <p class="text-muted"><?= $can_mark_attendance['message'] ?></p>
                                    <small>You can mark attendance when the period starts.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($selected_course_id > 0 && empty($students)): ?>
                        <div class="schedule-card">
                            <div class="text-center py-5 text-muted"><i class="bi bi-people display-6 d-block mb-3"></i>
                                <h5>No students found</h5>
                                <p>No students are enrolled in this class/section.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="schedule-card">
                            <div class="text-center py-5 text-muted"><i class="bi bi-calendar-check display-6 d-block mb-3"></i>
                                <h5>Select a Class and Period</h5>
                                <p>Choose a class from the schedule on the left to mark attendance.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function changeDate() {
            const date = document.getElementById('datePicker').value;
            window.location.href = `attendance.php?date=${date}`;
        }

        function selectPeriod(courseId, periodId, classId, sectionId) {
            const currentDate = document.getElementById('datePicker').value;
            window.location.href = `attendance.php?date=${currentDate}&class_id=${classId}&section_id=${sectionId}&course_id=${courseId}&period_id=${periodId}`;
        }

        function toggleAttendance(element, studentId) {
            if (element.closest('.disabled-overlay')) return;
            const periodStatus = '<?= $period_status ?>';
            const isPastDate = '<?= $selected_date < date("Y-m-d") ?>';
            const isLocked = '<?= $is_already_locked ?>';

            if (isLocked === '1') {
                alert('Attendance is already locked for this period. Cannot modify.');
                return;
            }

            if (element.classList.contains('present') || element.classList.contains('late')) {
                element.classList.remove('present', 'late');
                const input = element.nextElementSibling;
                if (input) {
                    input.disabled = true;
                    input.value = '';
                }
                const statusSpan = element.closest('tr').querySelector('.status-badge');
                if (statusSpan) {
                    statusSpan.textContent = 'Absent';
                    statusSpan.classList.remove('status-present', 'status-late');
                    statusSpan.classList.add('status-absent');
                }
            } else {
                if (periodStatus === 'completed' && !isPastDate) {
                    element.classList.add('late');
                    const input = element.nextElementSibling;
                    if (input) {
                        input.disabled = false;
                        input.value = studentId;
                    }
                    const statusSpan = element.closest('tr').querySelector('.status-badge');
                    if (statusSpan) {
                        statusSpan.textContent = 'Late';
                        statusSpan.classList.remove('status-present', 'status-absent');
                        statusSpan.classList.add('status-late');
                    }
                } else {
                    element.classList.add('present');
                    const input = element.nextElementSibling;
                    if (input) {
                        input.disabled = false;
                        input.value = studentId;
                    }
                    const statusSpan = element.closest('tr').querySelector('.status-badge');
                    if (statusSpan) {
                        statusSpan.textContent = 'Present';
                        statusSpan.classList.remove('status-absent', 'status-late');
                        statusSpan.classList.add('status-present');
                    }
                }
            }
        }

        function markAllPresent() {
            const isLocked = '<?= $is_already_locked ?>';
            if (isLocked === '1') {
                alert('Attendance is already locked for this period. Cannot modify.');
                return;
            }

            const switches = document.querySelectorAll('.attendance-switch');
            const periodStatus = '<?= $period_status ?>';
            const isPastDate = '<?= $selected_date < date("Y-m-d") ?>';

            switches.forEach(sw => {
                if (!sw.classList.contains('present') && !sw.classList.contains('late')) {
                    if (periodStatus === 'completed' && !isPastDate) {
                        sw.classList.add('late');
                        const input = sw.nextElementSibling;
                        if (input) {
                            input.disabled = false;
                            input.value = 'all';
                        }
                        const statusSpan = sw.closest('tr').querySelector('.status-badge');
                        if (statusSpan) {
                            statusSpan.textContent = 'Late';
                            statusSpan.classList.remove('status-present', 'status-absent');
                            statusSpan.classList.add('status-late');
                        }
                    } else {
                        sw.classList.add('present');
                        const input = sw.nextElementSibling;
                        if (input) {
                            input.disabled = false;
                            input.value = 'all';
                        }
                        const statusSpan = sw.closest('tr').querySelector('.status-badge');
                        if (statusSpan) {
                            statusSpan.textContent = 'Present';
                            statusSpan.classList.remove('status-absent', 'status-late');
                            statusSpan.classList.add('status-present');
                        }
                    }
                }
            });
        }

        function markAllAbsent() {
            const isLocked = '<?= $is_already_locked ?>';
            if (isLocked === '1') {
                alert('Attendance is already locked for this period. Cannot modify.');
                return;
            }

            const switches = document.querySelectorAll('.attendance-switch');
            switches.forEach(sw => {
                if (sw.classList.contains('present') || sw.classList.contains('late')) {
                    sw.classList.remove('present', 'late');
                    const input = sw.nextElementSibling;
                    if (input) {
                        input.disabled = true;
                        input.value = '';
                    }
                    const statusSpan = sw.closest('tr').querySelector('.status-badge');
                    if (statusSpan) {
                        statusSpan.textContent = 'Absent';
                        statusSpan.classList.remove('status-present', 'status-late');
                        statusSpan.classList.add('status-absent');
                    }
                }
            });
        }

        let formChanged = false;
        document.querySelectorAll('.attendance-switch').forEach(sw => {
            sw.addEventListener('click', () => {
                formChanged = true;
            });
        });
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved attendance changes.';
                return e.returnValue;
            }
        });
        document.getElementById('attendanceForm')?.addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>

</html>