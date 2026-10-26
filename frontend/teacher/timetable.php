<?php

/**
 * Timetable View - Teacher View
 * Smart Choice Academy
 * Professional dashboard-style weekly schedule (Monday - Saturday)
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
$teacher_employee_id = '';

if ($user_id) {
    $stmt = $db->prepare("SELECT t.teacher_id, t.employee_id, u.full_name, u.email 
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
    $teacher_employee_id = $teacher['employee_id'] ?? '';
}

// If teacher_id not found, try by reg_id
if (!$teacher_id && isset($_SESSION['reg_id'])) {
    $stmt = $db->prepare("SELECT t.teacher_id, t.employee_id, u.full_name, u.email 
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.employee_id = ?");
    $stmt->execute([$_SESSION['reg_id']]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
    $teacher_employee_id = $teacher['employee_id'] ?? '';
}

// Get current academic year
$stmt = $db->prepare("SELECT id, year_name FROM academic_years WHERE is_current = 'yes' AND status = 'active' LIMIT 1");
$stmt->execute();
$academic_year = $stmt->fetch();
$academic_year_id = $academic_year['id'] ?? 1;
$academic_year_name = $academic_year['year_name'] ?? date('Y');

// Get parameters
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Get week range (Monday to Saturday)
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
$week_end = date('Y-m-d', strtotime('saturday this week', strtotime($selected_date)));

// Days of week - Monday to Saturday (6 days)
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get school days mapping
$school_days = [];
$stmt = $db->query("SELECT id, day_name, is_working FROM school_days");
while ($row = $stmt->fetch()) {
    $school_days[$row['day_name']] = ['id' => $row['id'], 'is_working' => $row['is_working']];
}

// Get time slots
$time_slots = [];
$stmt = $db->query("SELECT id, period_number, slot_name, start_time, end_time, is_break FROM time_slots WHERE is_break = 0 ORDER BY period_number");
$time_slots = $stmt->fetchAll();

// Build timetable query for all days
$timetable_query = "
    SELECT 
        tr.id,
        tr.day_id,
        tr.slot_id,
        tr.class_id,
        tr.section_id,
        tr.course_id,
        c.class_name,
        c.numeric_grade,
        sec.section_name,
        cr.course_name,
        cr.course_code,
        ts.period_number,
        ts.slot_name,
        ts.start_time,
        ts.end_time,
        sd.day_name,
        CASE 
            WHEN a.is_locked = 1 THEN 'locked'
            ELSE 'pending'
        END as attendance_status
    FROM timetable_records tr
    JOIN classes c ON tr.class_id = c.id
    JOIN sections sec ON tr.section_id = sec.id
    JOIN courses cr ON tr.course_id = cr.id
    JOIN time_slots ts ON tr.slot_id = ts.id
    JOIN school_days sd ON tr.day_id = sd.id
    LEFT JOIN attendance a ON tr.class_id = a.class_id 
        AND tr.section_id = a.section_id 
        AND tr.course_id = a.course_id 
        AND tr.slot_id = a.period_id 
        AND a.attendance_date = CURDATE()
    WHERE tr.teacher_id = ? 
        AND tr.academic_year_id = ?
";

$params = [$teacher_id, $academic_year_id];

if ($selected_class_id > 0) {
    $timetable_query .= " AND tr.class_id = ?";
    $params[] = $selected_class_id;
}

$timetable_query .= " ORDER BY sd.id, ts.period_number";

$stmt = $db->prepare($timetable_query);
$stmt->execute($params);
$timetable_records = $stmt->fetchAll();

// Organize timetable by day and slot
$timetable_grid = [];
foreach ($days_of_week as $day) {
    $timetable_grid[$day] = [];
    foreach ($time_slots as $slot) {
        $timetable_grid[$day][$slot['id']] = null;
    }
}

foreach ($timetable_records as $record) {
    $day_name = $record['day_name'];
    $slot_id = $record['slot_id'];
    if (in_array($day_name, $days_of_week)) {
        $timetable_grid[$day_name][$slot_id] = $record;
    }
}

// Get classes taught by teacher for filter
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

// Get statistics
$total_classes = count($classes_taught);
$total_periods = count($timetable_records);
$completed_attendance = 0;
$pending_attendance = 0;

foreach ($timetable_records as $record) {
    if ($record['attendance_status'] == 'locked') {
        $completed_attendance++;
    } else {
        $pending_attendance++;
    }
}

// Get today's date info
$today_name = date('l');
$today_date = date('Y-m-d');
$current_time = date('H:i:s');

// Helper function
function isCurrentPeriod($start_time, $end_time, $day_name, $today_name, $current_time)
{
    return ($day_name == $today_name && $start_time <= $current_time && $end_time >= $current_time);
}

// Get date for each day of the week
$day_dates = [];
foreach ($days_of_week as $day) {
    $day_dates[$day] = date('d M', strtotime($day . ' this week', strtotime($week_start)));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Timetable - Smart Choice Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #224abe;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --dark: #5a5c69;
            --light: #f8f9fc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fb;
            font-family: 'Inter', sans-serif;
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 25px 35px;
            min-height: 100vh;
            width: 100%;
            transition: all 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        /* Professional Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #6c757d;
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
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
            letter-spacing: 0.5px;
        }

        .stat-icon {
            font-size: 32px;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .week-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .week-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f0f2f5;
            color: #4e73df;
            transition: all 0.2s;
            text-decoration: none;
        }

        .week-btn:hover {
            background: #4e73df;
            color: white;
        }

        .week-range {
            font-weight: 600;
            color: #2c3e50;
            background: #f0f2f5;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
        }

        /* Timetable Container */
        .timetable-wrapper {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .timetable {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .timetable th {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 14px 12px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            border: none;
        }

        .timetable td {
            border: 1px solid #e9ecef;
            padding: 0;
            vertical-align: top;
        }

        .time-column {
            background: #f8f9fc;
            width: 100px;
            text-align: center;
            vertical-align: middle !important;
        }

        .time-slot {
            font-weight: 600;
            font-size: 12px;
            color: #4e73df;
        }

        .period-number {
            font-size: 10px;
            color: #858796;
            margin-top: 3px;
        }

        /* Period Cell */
        .period-cell {
            padding: 12px;
            min-height: 100px;
            transition: background 0.2s;
        }

        .period-cell.empty {
            background: #fefefe;
            text-align: center;
            color: #adb5bd;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .period-cell.current {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            position: relative;
        }

        .period-cell.current::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #4caf50;
        }

        .course-name {
            font-weight: 700;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .class-info {
            font-size: 10px;
            color: #6c757d;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .class-info i {
            width: 14px;
            font-size: 10px;
        }

        .attendance-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 600;
            margin-top: 8px;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .mark-btn {
            margin-top: 8px;
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 6px;
            width: 100%;
        }

        /* Today Column Highlight */
        .today-col {
            background: #f0f7ff;
            position: relative;
        }

        .today-col th {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        }

        /* Saturday Column */
        .saturday-col {
            background: #fff8f0;
        }

        .saturday-col th {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
        }

        /* Holiday Cell */
        .holiday-cell {
            background: #f8d7da;
            text-align: center;
            color: #721c24;
            padding: 12px;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stat-card .stat-number {
                font-size: 22px;
            }
        }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
            }

            .timetable-wrapper {
                box-shadow: none;
            }

            .timetable th {
                background: #1e3c72;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <!-- Professional Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-calendar4-week me-2" style="color: #4e73df;"></i>
                My Weekly Schedule
            </h1>
            <p class="page-subtitle">Monday - Saturday | View and manage your class schedule</p>
        </div>

        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card position-relative" style="border-left-color: #4e73df;">
                    <div class="stat-number"><?= $total_classes ?></div>
                    <div class="stat-label">Classes Teaching</div>
                    <i class="bi bi-building stat-icon"></i>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card position-relative" style="border-left-color: #1cc88a;">
                    <div class="stat-number"><?= $total_periods ?></div>
                    <div class="stat-label">Total Periods/Week</div>
                    <i class="bi bi-clock-history stat-icon"></i>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card position-relative" style="border-left-color: #36b9cc;">
                    <div class="stat-number"><?= $completed_attendance ?></div>
                    <div class="stat-label">Attendance Completed</div>
                    <i class="bi bi-check-circle stat-icon"></i>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card position-relative" style="border-left-color: #f6c23e;">
                    <div class="stat-number"><?= $pending_attendance ?></div>
                    <div class="stat-label">Pending Attendance</div>
                    <i class="bi bi-clock stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar no-print">
            <div class="week-controls">
                <a href="?date=<?= date('Y-m-d', strtotime($selected_date . ' -7 days')) ?>&class_id=<?= $selected_class_id ?>" class="week-btn">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <span class="week-range">
                    <i class="bi bi-calendar-range me-1"></i>
                    <?= date('d M', strtotime($week_start)) ?> - <?= date('d M, Y', strtotime($week_end)) ?>
                </span>
                <a href="?date=<?= date('Y-m-d', strtotime($selected_date . ' +7 days')) ?>&class_id=<?= $selected_class_id ?>" class="week-btn">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="?date=<?= date('Y-m-d') ?>&class_id=<?= $selected_class_id ?>" class="btn btn-sm btn-outline-primary ms-2">
                    <i class="bi bi-calendar2-week me-1"></i> Current
                </a>
            </div>

            <div class="d-flex gap-2">
                <select name="class_id" class="form-select form-select-sm" style="width: 180px;" onchange="window.location.href='?date=<?= $selected_date ?>&class_id='+this.value">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes_taught as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $selected_class_id == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>

        <!-- Timetable Grid -->
        <div class="timetable-wrapper">
            <table class="timetable">
                <thead>
                    <tr>
                        <th>Time / Period</th>
                        <?php foreach ($days_of_week as $day):
                            $is_today = ($day == $today_name);
                            $is_saturday = ($day == 'Saturday');
                            $day_class = $is_today ? 'today-col' : ($is_saturday ? 'saturday-col' : '');
                            $day_date = $day_dates[$day];
                        ?>
                            <th class="<?= $day_class ?>">
                                <?= substr($day, 0, 3) ?>
                                <div style="font-size: 10px; font-weight: 400; opacity: 0.8;"><?= $day_date ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($time_slots as $slot): ?>
                        <tr>
                            <td class="time-column">
                                <div class="time-slot">
                                    <?= date('h:i A', strtotime($slot['start_time'])) ?> -
                                    <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                </div>
                                <div class="period-number">Period <?= $slot['period_number'] ?></div>
                            </td>

                            <?php foreach ($days_of_week as $day):
                                $is_today = ($day == $today_name);
                                $is_saturday = ($day == 'Saturday');
                                $cell_data = $timetable_grid[$day][$slot['id']] ?? null;
                                $is_current = $cell_data && isCurrentPeriod($cell_data['start_time'], $cell_data['end_time'], $day, $today_name, $current_time);
                                $is_working = isset($school_days[$day]) ? $school_days[$day]['is_working'] : true;
                            ?>
                                <td class="<?= $is_today ? 'today-col' : ($is_saturday ? 'saturday-col' : '') ?>">
                                    <?php if (!$is_working): ?>
                                        <div class="holiday-cell">
                                            <i class="bi bi-brightness-alt-high" style="font-size: 20px;"></i>
                                            <span style="font-size: 11px; margin-top: 5px;">Holiday</span>
                                        </div>
                                    <?php elseif ($cell_data): ?>
                                        <div class="period-cell <?= $is_current ? 'current' : '' ?>">
                                            <div class="course-name"><?= htmlspecialchars($cell_data['course_name']) ?></div>
                                            <div class="class-info">
                                                <span><i class="bi bi-building"></i> <?= htmlspecialchars($cell_data['class_name']) ?></span>
                                                <span><i class="bi bi-people"></i> <?= htmlspecialchars($cell_data['section_name']) ?></span>
                                            </div>
                                            <div class="attendance-badge <?= $cell_data['attendance_status'] == 'locked' ? 'badge-completed' : 'badge-pending' ?>">
                                                <i class="bi <?= $cell_data['attendance_status'] == 'locked' ? 'bi-check-circle-fill' : 'bi-clock-history' ?>"></i>
                                                <?= $cell_data['attendance_status'] == 'locked' ? 'Completed' : 'Pending' ?>
                                            </div>
                                            <a href="attendance.php?class_id=<?= $cell_data['class_id'] ?>&section_id=<?= $cell_data['section_id'] ?>&course_id=<?= $cell_data['course_id'] ?>&period_id=<?= $cell_data['slot_id'] ?>&date=<?= $today_date ?>"
                                                class="btn btn-sm btn-primary mark-btn no-print">
                                                <i class="bi bi-check2-square"></i> Mark
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="period-cell empty">
                                            <i class="bi bi-dash-circle" style="font-size: 18px;"></i>
                                            <span style="font-size: 10px;">Free</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div class="row mt-4 no-print">
            <div class="col-12">
                <div class="bg-white rounded-3 p-3 shadow-sm">
                    <div class="d-flex flex-wrap align-items-center gap-4">
                        <span class="fw-semibold small text-uppercase text-muted">Legend:</span>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 16px; height: 16px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 4px;"></div>
                            <span class="small">Current Period</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="attendance-badge badge-completed" style="padding: 2px 8px;">Completed</div>
                            <span class="small">Attendance Marked</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="attendance-badge badge-pending" style="padding: 2px 8px;">Pending</div>
                            <span class="small">Attendance Pending</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-dash-circle text-muted"></i>
                            <span class="small">Free Period</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 16px; height: 16px; background: #f0f7ff; border-radius: 4px;"></div>
                            <span class="small">Today's Column</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 16px; height: 16px; background: #fff8f0; border-radius: 4px;"></div>
                            <span class="small">Saturday</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 16px; height: 16px; background: #f8d7da; border-radius: 4px;"></div>
                            <span class="small">Holiday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-refresh every minute to update current period highlighting
        setTimeout(function() {
            location.reload();
        }, 60000);

        // Highlight current period on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentPeriod = document.querySelector('.period-cell.current');
            if (currentPeriod) {
                currentPeriod.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    </script>
</body>

</html>