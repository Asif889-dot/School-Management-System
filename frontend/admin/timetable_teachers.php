<?php
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

requireLogin();

// Target active operational Academic Year configuration context
$yearStmt = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY is_current DESC, id DESC LIMIT 1");
$currentYear = $yearStmt->fetch();
$academic_year_id = $currentYear ? $currentYear['id'] : 1;

// Fetch all registered instructors for the dropdown filter selector matrix
$teachers = $pdo->query("
    SELECT t.teacher_id, u.full_name, t.employee_id 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name ASC
")->fetchAll();

$selected_teacher = $_GET['teacher_id'] ?? ($teachers[0]['teacher_id'] ?? null);

// Fetch structural time coordinate bounds
$days = $pdo->query("SELECT id, day_name FROM school_days WHERE is_working = 1 ORDER BY id")->fetchAll();
$slots = $pdo->query("SELECT id, slot_name, is_break, start_time, end_time FROM time_slots ORDER BY period_number")->fetchAll();

// Compile multi-dimensional mapping data matched to the teacher constraint lane
$matrix = [];
$totalWeeklyLectures = 0;

if ($selected_teacher) {
    $matrixQuery = $pdo->prepare("
        SELECT tr.day_id, tr.slot_id, co.course_name, co.course_code,
               CONCAT(c.class_name, ' - ', s.section_name) as target_section
        FROM timetable_records tr
        JOIN courses co ON tr.course_id = co.id
        JOIN sections s ON tr.section_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
    ");
    $matrixQuery->execute([$selected_teacher, $academic_year_id]);
    while ($r = $matrixQuery->fetch()) {
        $matrix[$r['day_id']][$r['slot_id']] = $r;
        $totalWeeklyLectures++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCA Timetable - Instructor Deployment Matrix</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        :root {
            --sca-primary: #2c3e50;
            --sca-bg: #f8f9fa;
        }

        body {
            background-color: var(--sca-bg);
            font-family: 'Poppins', sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .table-card {
            background: #fff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .timetable-header th {
            background-color: var(--sca-primary) !important;
            color: #fff !important;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .day-cell {
            background-color: #f1f5f9 !important;
            color: #334155;
            font-weight: 600;
            width: 130px;
        }

        .break-cell {
            background-color: #fef3c7 !important;
            color: #d97706;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .slot-occupied {
            background-color: #f0f5ff !important;
            border-left: 4px solid #2563eb !important;
            border: 1px solid #dae6ff;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h4 class="m-0 fw-bold text-dark">Instructor Utilization Schedule</h4>
                <small class="text-muted">Active Term: <strong><?= htmlspecialchars($currentYear['year_name'] ?? 'Operational Term') ?></strong></small>
            </div>
            <div>
                <span class="badge bg-dark px-3 py-2 fs-6 shadow-sm">
                    <i class="bi bi-clock-history text-info me-2"></i>Weekly Load: <?= $totalWeeklyLectures ?> Periods Allocated
                </span>
            </div>
        </div>

        <div class="card table-card p-4">
            <div class="row align-items-center mb-4">
                <div class="col-md-5 col-lg-4">
                    <label class="form-label fw-semibold text-secondary">Target Instructor Profile Lookup:</label>
                    <form method="GET" id="teacherFilterForm" class="m-0">
                        <select name="teacher_id" class="form-select border-2" onchange="document.getElementById('teacherFilterForm').submit();" style="border-radius: 8px;">
                            <?php foreach ($teachers as $tch): ?>
                                <option value="<?= $tch['teacher_id'] ?>" <?= $selected_teacher == $tch['teacher_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tch['full_name']) ?> (<?= htmlspecialchars($tch['employee_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <div class="table-responsive rounded-3 border">
                <table class="table table-bordered text-center align-middle m-0">
                    <thead class="timetable-header">
                        <tr>
                            <th>Operational Day</th>
                            <?php foreach ($slots as $slot): ?>
                                <th>
                                    <span class="d-block fw-semibold"><?= htmlspecialchars($slot['slot_name']) ?></span>
                                    <small class="text-muted font-monospace" style="font-size: 11px;">
                                        <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                    </small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $day): ?>
                            <tr>
                                <td class="day-cell"><?= $day['day_name'] ?></td>
                                <?php foreach ($slots as $slot): ?>
                                    <?php if ($slot['is_break']): ?>
                                        <td class="break-cell">Recess Break</td>
                                    <?php else: ?>
                                        <?php if (isset($matrix[$day['id']][$slot['id']])):
                                            $cell = $matrix[$day['id']][$slot['id']]; ?>
                                            <td class="slot-occupied text-start p-3">
                                                <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;"><?= htmlspecialchars($cell['course_name']) ?></div>
                                                <div class="text-primary fw-medium" style="font-size: 11px;">
                                                    <i class="bi bi-building me-1"></i><?= htmlspecialchars($cell['target_section']) ?>
                                                </div>
                                                <small class="text-muted font-monospace" style="font-size: 10px;"><?= htmlspecialchars($cell['course_code']) ?></small>
                                            </td>
                                        <?php else: ?>
                                            <td class="text-muted table-light py-3 opacity-50" style="font-size: 0.8rem; font-style: italic;">
                                                Off Duty / Available
                                            </td>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>