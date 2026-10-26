<?php

/**
 * Teacher Dashboard - Smart Choice Academy
 * Comprehensive dashboard for teachers with statistics, schedules, and quick actions
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
$teacher_id = $_SESSION['reg_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Defensive fallback
if (empty($teacher_id) && empty($user_id)) {
    header('Location: ../../index.php?error=access_denied');
    exit;
}

// Get teacher details from teachers table using user_id or reg_id
$teacher = null;
if ($user_id) {
    $stmt = $db->prepare("SELECT t.*, u.full_name, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
} elseif ($teacher_id) {
    $stmt = $db->prepare("SELECT t.*, u.full_name, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.employee_id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
}

if (!$teacher) {
    // Try to get teacher by reg_id from session
    $stmt = $db->prepare("SELECT t.*, u.full_name, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.reg_id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
}

$teacher_id_db = $teacher['teacher_id'] ?? 0;
$teacher_name = $teacher['full_name'] ?? 'Teacher';
$teacher_email = $teacher['email'] ?? '';

// Get current academic year
$current_academic_year = date('Y');
$stmt = $db->prepare("SELECT id, year_name FROM academic_years WHERE is_current = 'yes' AND status = 'active' LIMIT 1");
$stmt->execute();
$academic_year = $stmt->fetch();
$academic_year_id = $academic_year['id'] ?? 1;

// Fetch teacher's statistics
$stats = [];

// Total classes taught by this teacher
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT tr.class_id) as total_classes 
    FROM timetable_records tr 
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
");
$stmt->execute([$teacher_id_db, $academic_year_id]);
$stats['total_classes'] = $stmt->fetchColumn() ?: 0;

// Total students taught
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT s.id) as total_students 
    FROM students s 
    JOIN timetable_records tr ON s.class_id = tr.class_id AND s.section_id = tr.section_id
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND s.status = 'approved'
");
$stmt->execute([$teacher_id_db, $academic_year_id]);
$stats['total_students'] = $stmt->fetchColumn() ?: 0;

// Total subjects taught
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT c.id) as total_subjects 
    FROM courses c
    JOIN timetable_records tr ON c.id = tr.course_id
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
");
$stmt->execute([$teacher_id_db, $academic_year_id]);
$stats['total_subjects'] = $stmt->fetchColumn() ?: 0;

// Total periods per week
$stmt = $db->prepare("
    SELECT COUNT(*) as total_periods 
    FROM timetable_records tr 
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
");
$stmt->execute([$teacher_id_db, $academic_year_id]);
$stats['total_periods'] = $stmt->fetchColumn() ?: 0;

// Today's schedule
$today = date('N'); // 1 (Monday) to 7 (Sunday)
$stmt = $db->prepare("
    SELECT tr.*, c.class_name, sec.section_name, cou.course_name, ts.start_time, ts.end_time, ts.slot_name
    FROM timetable_records tr
    JOIN classes c ON tr.class_id = c.id
    JOIN sections sec ON tr.section_id = sec.id
    JOIN courses cou ON tr.course_id = cou.id
    JOIN time_slots ts ON tr.slot_id = ts.id
    JOIN school_days sd ON tr.day_id = sd.id
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND sd.day_name = DAYNAME(CURDATE())
    ORDER BY ts.start_time ASC
");
$stmt->execute([$teacher_id_db, $academic_year_id]);
$today_schedule = $stmt->fetchAll();

// Upcoming events from calendar
$events = $db->query("SELECT * FROM academic_calendar WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5")->fetchAll();

// Recent announcements (if you have an announcements table)
$announcements = [];
try {
    $stmt = $db->query("SELECT * FROM announcements WHERE status = 'active' AND (target_audience = 'teachers' OR target_audience = 'all') ORDER BY created_at DESC LIMIT 5");
    $announcements = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
    $announcements = [];
}

// Get current date and time
$current_time = date('H');
$greeting = '';
if ($current_time < 12) {
    $greeting = 'Good Morning';
} elseif ($current_time < 17) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Smart Choice Academy</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
        }

        /* Main Content Area with Sidebar */
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
            padding: 25px 30px;
            margin-bottom: 25px;
            color: white;
        }

        .welcome-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .welcome-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            font-size: 40px;
            opacity: 0.3;
            position: absolute;
            right: 15px;
            top: 15px;
        }

        /* Schedule Table */
        .schedule-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .schedule-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #495057;
        }

        .schedule-table td {
            padding: 10px 15px;
            font-size: 13px;
            vertical-align: middle;
        }

        .time-badge {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #495057;
        }

        /* Quick Actions */
        .quick-action-btn {
            padding: 12px 20px;
            border-radius: 10px;
            transition: all 0.2s;
            text-align: center;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
        }

        /* Calendar Events */
        .event-item {
            border-left: 3px solid #667eea;
            padding: 10px 15px;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 8px;
        }

        .event-date {
            font-size: 11px;
            color: #6c757d;
        }

        .event-title {
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }

        /* Announcements */
        .announcement-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-title {
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }

        .announcement-date {
            font-size: 10px;
            color: #adb5bd;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
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
                        <div class="welcome-title"><?= $greeting ?>, <?= htmlspecialchars($teacher_name) ?>!</div>
                        <div class="welcome-subtitle">
                            <i class="bi bi-calendar3 me-1"></i> <?= date('l, F j, Y') ?> |
                            <i class="bi bi-clock ms-2 me-1"></i> <?= date('h:i A') ?>
                        </div>
                        <?php if ($teacher_email): ?>
                            <div class="welcome-subtitle mt-2">
                                <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($teacher_email) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <i class="bi bi-person-circle display-4 opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #4e73df;">
                        <div class="stat-number"><?= $stats['total_classes'] ?></div>
                        <div class="stat-label">Classes Assigned</div>
                        <i class="bi bi-building stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #1cc88a;">
                        <div class="stat-number"><?= $stats['total_students'] ?></div>
                        <div class="stat-label">Total Students</div>
                        <i class="bi bi-people stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #36b9cc;">
                        <div class="stat-number"><?= $stats['total_subjects'] ?></div>
                        <div class="stat-label">Subjects Teaching</div>
                        <i class="bi bi-book stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #f6c23e;">
                        <div class="stat-number"><?= $stats['total_periods'] ?></div>
                        <div class="stat-label">Periods/Week</div>
                        <i class="bi bi-clock-history stat-icon"></i>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Today's Schedule Column -->
                <div class="col-lg-7 mb-4">
                    <div class="schedule-table">
                        <div class="bg-white p-3 border-bottom">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-calendar-day text-primary me-2"></i>Today's Schedule
                            </h5>
                            <small class="text-muted"><?= date('l, F j, Y') ?></small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Section</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($today_schedule)): ?>
                                        <tr>
                                            <td colspan="4" class="empty-state">
                                                <i class="bi bi-calendar-x"></i>
                                                <p class="mb-0">No classes scheduled for today</p>
                                                <small class="text-muted">Enjoy your day!</small>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($today_schedule as $schedule): ?>
                                            <tr>
                                                <td>
                                                    <span class="time-badge">
                                                        <?= date('h:i A', strtotime($schedule['start_time'])) ?> -
                                                        <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($schedule['course_name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($schedule['class_name']) ?></td>
                                                <td><?= htmlspecialchars($schedule['section_name']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-5 mb-4">
                    <!-- Quick Actions -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-lightning-charge text-warning me-2"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="marks_entry.php" class="btn btn-outline-primary w-100 quick-action-btn">
                                        <i class="bi bi-pencil-square d-block fs-4 mb-2"></i>
                                        Enter Marks
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="attendance.php" class="btn btn-outline-success w-100 quick-action-btn">
                                        <i class="bi bi-check2-square d-block fs-4 mb-2"></i>
                                        Mark Attendance
                                    </a>
                                </div>
<div class="col-6">
                                    <a href="timetable.php" class="btn btn-outline-info w-100 quick-action-btn">
                                        <i class="bi bi-table d-block fs-4 mb-2"></i>
                                        View Timetable
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="students.php" class="btn btn-outline-secondary w-100 quick-action-btn">
                                        <i class="bi bi-people d-block fs-4 mb-2"></i>
                                        My Students
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Calendar -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-calendar-event text-danger me-2"></i>Upcoming Events
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($events)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-calendar2-week"></i>
                                    <p class="mb-0">No upcoming events</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($events as $event): ?>
                                    <div class="event-item">
                                        <div class="event-date">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= date('l, d M Y', strtotime($event['event_date'])) ?>
                                        </div>
                                        <div class="event-title mt-1">
                                            <?= htmlspecialchars($event['event_title']) ?>
                                        </div>
                                        <?php if (!empty($event['description'])): ?>
                                            <div class="event-description mt-1 small text-muted">
                                                <?= htmlspecialchars(substr($event['description'], 0, 100)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Announcements -->
                    <?php if (!empty($announcements)): ?>
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-bottom">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-megaphone text-info me-2"></i>Announcements
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($announcements as $announcement): ?>
                                    <div class="announcement-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="announcement-title flex-grow-1">
                                                <?= htmlspecialchars($announcement['title']) ?>
                                            </div>
                                            <div class="announcement-date ms-2">
                                                <?= date('d M', strtotime($announcement['created_at'])) ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($announcement['content'])): ?>
                                            <div class="announcement-content small text-muted mt-1">
                                                <?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity / Performance Tips -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-lightbulb text-warning me-2"></i>Teaching Tips & Reminders
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                                        <div>
                                            <strong>Mark Attendance Daily</strong>
                                            <div class="small text-muted">Complete attendance before 9:00 AM</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="bi bi-journal-bookmark-fill text-primary fs-4 me-3"></i>
                                        <div>
                                            <strong>Lesson Planning</strong>
                                            <div class="small text-muted">Prepare weekly lesson plans in advance</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="bi bi-envelope-paper-fill text-info fs-4 me-3"></i>
                                        <div>
                                            <strong>Parent Communication</strong>
                                            <div class="small text-muted">Update parents about student progress</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>