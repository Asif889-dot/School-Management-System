<?php
// Student Dashboard

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Get student by reg_id
$stmt = $db->prepare("
    SELECT s.*, c.class_name, sec.section_name, ay.year_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.reg_id = ? AND s.status = 'approved'
    LIMIT 1
");
$stmt->execute([$_SESSION['reg_id']]);
$student = $stmt->fetch();

$student_id = $student['id'] ?? 0;
$student_name = isset($student['first_name'])
    ? $student['first_name'] . ' ' . ($student['last_name'] ?? '')
    : ($_SESSION['full_name'] ?? 'Student');

if (!$student) {
    header('Location: ../../index.php?error=access_denied');
    exit;
}

// Stats
$attendance_count = 0;
$attendance_present = 0;
$attendance_pct = 0;
$marks_count = 0;
$avg_pct = 0;
$total_fee = 0;
$total_paid = 0;

try {
    $stmt = $db->prepare("
        SELECT COUNT(*) total,
               SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) present
        FROM attendance WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $att = $stmt->fetch();
    $attendance_count = $att['total'] ?? 0;
    $attendance_present = $att['present'] ?? 0;
    if ($attendance_count > 0) $attendance_pct = round(($attendance_present / $attendance_count) * 100);
} catch (PDOException $e) {}

try {
    $stmt = $db->prepare("SELECT COUNT(*) c, AVG(obtained_marks/total_marks*100) a FROM exam_marks WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $mr = $stmt->fetch();
    $marks_count = $mr['c'] ?? 0;
    $avg_pct = round($mr['a'] ?? 0, 1);
} catch (PDOException $e) {}

try {
    $stmt = $db->prepare("SELECT SUM(amount) t FROM fee_structures WHERE class_id = ?");
    $stmt->execute([$student['class_id']]);
    $total_fee = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {}

try {
    $stmt = $db->prepare("SELECT SUM(amount_paid) t FROM fee_payments WHERE student_id = ? AND status = 'paid'");
    $stmt->execute([$student_id]);
    $total_paid = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {}

// Recent notices
$notices = [];
try {
    $notices = $db->query("SELECT * FROM notices WHERE status='active' AND (target_audience='all' OR target_audience='students') ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {}

// Upcoming events
$events = [];
try {
    $events = $db->query("SELECT * FROM academic_calendar WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5")->fetchAll();
} catch (PDOException $e) {}

// Today's schedule for student's class
$today_schedule = [];
try {
    $stmt = $db->prepare("
        SELECT tr.*, c.class_name, sec.section_name, cr.course_name, ts.start_time, ts.end_time
        FROM timetable_records tr
        JOIN classes c ON tr.class_id = c.id
        JOIN sections sec ON tr.section_id = sec.id
        JOIN courses cr ON tr.course_id = cr.id
        JOIN time_slots ts ON tr.slot_id = ts.id
        JOIN school_days sd ON tr.day_id = sd.id
        WHERE tr.class_id = ? AND tr.section_id = ? AND sd.day_name = DAYNAME(CURDATE())
        ORDER BY ts.start_time ASC
    ");
    $stmt->execute([$student['class_id'], $student['section_id']]);
    $today_schedule = $stmt->fetchAll();
} catch (PDOException $e) {}

$greeting = date('H') < 12 ? 'Good Morning' : (date('H') < 17 ? 'Good Afternoon' : 'Good Evening');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 25px 30px; margin-bottom: 25px; color: white; }
        .welcome-title { font-size: 24px; font-weight: 700; }
        .welcome-subtitle { font-size: 14px; opacity: 0.9; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; border-left: 4px solid; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-number { font-size: 32px; font-weight: 800; }
        .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .schedule-table th { background: #f8f9fa; padding: 10px; font-size: 11px; text-transform: uppercase; }
        .schedule-table td { padding: 10px; font-size: 13px; }
        .time-badge { background: #e9ecef; padding: 4px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .event-item { border-left: 3px solid #667eea; padding: 10px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
        .notice-item { padding: 12px 15px; border-bottom: 1px solid #e9ecef; }
        .empty-state { text-align: center; padding: 30px; color: #6c757d; }
    </style>
</head>

<body>
    <?php include '../../backend/student_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="welcome-title"><?= $greeting ?>, <?= htmlspecialchars($student_name) ?>!</div>
                        <div class="welcome-subtitle">
                            <i class="bi bi-calendar3 me-1"></i> <?= date('l, F j, Y') ?> |
                            <i class="bi bi-mortarboard ms-2 me-1"></i> <?= htmlspecialchars($student['class_name'] ?? '') ?> - <?= htmlspecialchars($student['section_name'] ?? '') ?>
                        </div>
                    </div>
                    <div><i class="bi bi-person-circle display-4 opacity-50"></i></div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color:#4e73df;">
                        <div class="stat-number"><?= $attendance_pct ?>%</div>
                        <div class="stat-label">Attendance</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color:#1cc88a;">
                        <div class="stat-number"><?= $avg_pct ?>%</div>
                        <div class="stat-label">Average Marks</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color:#36b9cc;">
                        <div class="stat-number"><?= $marks_count ?></div>
                        <div class="stat-label">Results</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="border-left-color:#f6c23e;">
                        <div class="stat-number">Rs. <?= number_format(max(0, $total_fee - $total_paid), 0) ?></div>
                        <div class="stat-label">Fee Balance</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-calendar-day text-primary me-2"></i>Today's Schedule</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table schedule-table mb-0">
                                    <thead><tr><th>Time</th><th>Subject</th><th>Class</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($today_schedule)): ?>
                                            <tr><td colspan="3" class="empty-state"><i class="bi bi-calendar-x"></i><p class="mb-0">No classes today</p></td></tr>
                                        <?php else: ?>
                                            <?php foreach ($today_schedule as $s): ?>
                                                <tr>
                                                    <td><span class="time-badge"><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?></span></td>
                                                    <td><strong><?= htmlspecialchars($s['course_name']) ?></strong></td>
                                                    <td><?= htmlspecialchars($s['class_name']) ?> - <?= htmlspecialchars($s['section_name']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-4">
                    <div class="card mb-4">
                        <div class="card-header p-3"><i class="bi bi-calendar-event text-danger me-2"></i>Upcoming Events</div>
                        <div class="card-body p-0">
                            <?php if (empty($events)): ?>
                                <div class="empty-state"><i class="bi bi-calendar2-week"></i><p class="mb-0">No upcoming events</p></div>
                            <?php else: ?>
                                <?php foreach ($events as $e): ?>
                                    <div class="event-item">
                                        <div class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y', strtotime($e['event_date'])) ?></div>
                                        <div class="fw-semibold"><?= htmlspecialchars($e['event_title']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-megaphone text-info me-2"></i>Notices</div>
                        <div class="card-body p-0">
                            <?php if (empty($notices)): ?>
                                <div class="empty-state"><i class="bi bi-megaphone"></i><p class="mb-0">No notices</p></div>
                            <?php else: ?>
                                <?php foreach ($notices as $n): ?>
                                    <div class="notice-item">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-semibold"><?= htmlspecialchars($n['title']) ?></span>
                                            <small class="text-muted"><?= date('d M', strtotime($n['created_at'])) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
