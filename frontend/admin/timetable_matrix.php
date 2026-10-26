<?php

/**
 * Smart Choice Academy - Timetable Workload Analytics View with Overload Management
 * Reference Context: Control Desk Portal Frame
 */

// 1. Session Initialization & Safety Guards
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Connections & Framework Requirements
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

// Access Control Protection Layer
requireLogin();

// For diagnostic context tracking, assuming active year is 1 if not explicitly set in session
$academic_year_id = isset($_SESSION['academic_year_id']) ? (int)$_SESSION['academic_year_id'] : 1;

// Default overload threshold (periods per week)
$default_overload_threshold = 20;
$overload_threshold = isset($_POST['overload_threshold']) ? (int)$_POST['overload_threshold'] : $default_overload_threshold;

// Handle overload threshold update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_threshold'])) {
    $overload_threshold = (int)$_POST['overload_threshold'];
    $_SESSION['overload_threshold'] = $overload_threshold;
    $success_msg = "Overload threshold updated to <strong>{$overload_threshold}</strong> periods per week";
} else if (isset($_SESSION['overload_threshold'])) {
    $overload_threshold = (int)$_SESSION['overload_threshold'];
}

// Handle teacher assignment adjustments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reassign_periods' && isset($_POST['teacher_id'])) {
        $teacher_id = (int)$_POST['teacher_id'];
        $reduction_amount = (int)$_POST['reduction_amount'];

        try {
            // Get the teacher's overloaded periods
            $stmt = $pdo->prepare("
                SELECT COUNT(tr.id) as period_count 
                FROM timetable_records tr
                WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
            ");
            $stmt->execute([$teacher_id, $academic_year_id]);
            $current_load = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($current_load && $current_load['period_count'] > 0) {
                // Find periods to reassign (get the last N periods)
                $stmt = $pdo->prepare("
                    SELECT tr.id, tr.course_id, tr.section_id, tr.day_id, tr.slot_id
                    FROM timetable_records tr
                    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
                    ORDER BY tr.id DESC
                    LIMIT ?
                ");
                $stmt->execute([$teacher_id, $academic_year_id, $reduction_amount]);
                $periods_to_reassign = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($periods_to_reassign)) {
                    $pdo->beginTransaction();

                    foreach ($periods_to_reassign as $period) {
                        // Delete the period (or mark as unassigned)
                        $delete_stmt = $pdo->prepare("DELETE FROM timetable_records WHERE id = ?");
                        $delete_stmt->execute([$period['id']]);
                    }

                    $pdo->commit();
                    $success_msg = "Successfully removed <strong>{$reduction_amount}</strong> period(s) from teacher workload";
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error reassigning periods: " . $e->getMessage();
        }
    }
}

try {
    // Fetch aggregated matrix metrics grouping by individual teacher workloads
    $query = "
        SELECT 
            t.teacher_id as teacher_id,
            t.employee_id,
            u.full_name as teacher_name,
            COUNT(tr.id) as total_periods_allocated,
            COUNT(DISTINCT tr.section_id) as total_sections_managed,
            COUNT(DISTINCT tr.class_id) as total_classes_managed,
            GROUP_CONCAT(DISTINCT c.course_name SEPARATOR ', ') as assigned_subjects,
            GROUP_CONCAT(DISTINCT CONCAT(cls.class_name, ' - ', s.section_name) SEPARATOR ' | ') as assigned_sections
        FROM teachers t
        INNER JOIN users u ON t.user_id = u.id
        LEFT JOIN timetable_records tr ON t.teacher_id = tr.teacher_id AND tr.academic_year_id = ?
        LEFT JOIN courses c ON tr.course_id = c.id
        LEFT JOIN classes cls ON tr.class_id = cls.id
        LEFT JOIN sections s ON tr.section_id = s.id
        GROUP BY t.teacher_id, u.full_name, t.employee_id
        ORDER BY total_periods_allocated DESC, u.full_name ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$academic_year_id]);
    $workload_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $total_periods = 0;
    $overloaded_teachers = 0;
    $underloaded_teachers = 0;
    $balanced_teachers = 0;

    foreach ($workload_records as $record) {
        $periods = (int)$record['total_periods_allocated'];
        $total_periods += $periods;

        if ($periods > $overload_threshold) {
            $overloaded_teachers++;
        } elseif ($periods < $overload_threshold / 2) {
            $underloaded_teachers++;
        } else {
            $balanced_teachers++;
        }
    }
} catch (\Exception $e) {
    die("Analytics Layer Error: " . $e->getMessage());
}

$success_msg = $success_msg ?? '';
$error_msg = $error_msg ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Workload Analytics - Smart Choice Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="stylesheet" href="../../assets/css/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        :root {
            --sca-bg: #f8f9fa;
            --overload-color: #dc3545;
            --warning-color: #ffc107;
            --balanced-color: #198754;
            --underload-color: #0dcaf0;
        }

        body {
            background-color: var(--sca-bg);
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .metric-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .metric-card:hover {
            transform: translateY(-2px);
        }

        .badge-workload {
            font-size: 0.9rem;
            padding: 0.5em 0.75em;
        }

        .overload-row {
            background-color: rgba(220, 53, 69, 0.05);
            border-left: 3px solid #dc3545;
        }

        .warning-row {
            background-color: rgba(255, 193, 7, 0.05);
            border-left: 3px solid #ffc107;
        }

        .teacher-card {
            transition: all 0.3s ease;
        }

        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .action-buttons {
            display: none;
            position: absolute;
            right: 20px;
            top: 10px;
        }

        .teacher-card:hover .action-buttons {
            display: block;
        }

        .threshold-slider {
            width: 200px;
            display: inline-block;
            margin: 0 10px;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .overload-badge {
            animation: pulse 2s infinite;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        @media print {
            .main-content {
                margin-left: 0;
                padding: 0;
            }

            .btn,
            #sidebar-wrapper,
            .sidebar,
            .action-buttons,
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4 gap-3">
                <div>
                    <h1 class="h3 mb-1 text-gray-800">
                        <i class="fa-solid fa-chart-pie text-primary me-2"></i>
                        Teacher Workload & Overload Management
                    </h1>
                    <p class="text-muted small mb-0">
                        Monitor teacher workload distribution and manage overload situations
                    </p>
                </div>
                <div class="no-print">
                    <a href="timetable_create.php" class="btn btn-outline-secondary btn-sm me-2 fw-medium">
                        <i class="fa-solid fa-arrow-left me-1"></i> Control Desk
                    </a>
                    <button onclick="window.print();" class="btn btn-secondary btn-sm fw-medium shadow-sm me-2">
                        <i class="fa-solid fa-print me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-primary btn-sm fw-medium shadow-sm" data-bs-toggle="modal" data-bs-target="#thresholdModal">
                        <i class="fa-solid fa-sliders-h me-1"></i> Set Threshold
                    </button>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-check-circle me-2"></i> <?= $success_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-exclamation-circle me-2"></i> <?= $error_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="card metric-card shadow-sm p-3 bg-white border-0">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-clock me-1"></i> Total Weekly Periods
                        </div>
                        <div class="h3 my-1 fw-bold text-dark"><?= $total_periods ?></div>
                        <div class="text-success small">Across all teachers</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="card metric-card shadow-sm p-3 bg-white border-0" style="border-left-color: #dc3545;">
                        <div class="text-muted small text-uppercase fw-bold">
                            <i class="fa-solid fa-exclamation-triangle me-1"></i> Overloaded Teachers
                        </div>
                        <div class="h3 my-1 fw-bold text-danger"><?= $overloaded_teachers ?></div>
                        <div class="text-muted small">Above <?= $overload_threshold ?> periods</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="card metric-card shadow-sm p-3 bg-white border-0" style="border-left-color: #ffc107;">
                        <div class="text-muted small text-uppercase fw-bold">
                            <i class="fa-solid fa-chart-line me-1"></i> Balanced
                        </div>
                        <div class="h3 my-1 fw-bold text-warning"><?= $balanced_teachers ?></div>
                        <div class="text-muted small">Optimal workload</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="card metric-card shadow-sm p-3 bg-white border-0" style="border-left-color: #0dcaf0;">
                        <div class="text-muted small text-uppercase fw-bold">
                            <i class="fa-solid fa-arrow-down me-1"></i> Underloaded
                        </div>
                        <div class="h3 my-1 fw-bold text-info"><?= $underloaded_teachers ?></div>
                        <div class="text-muted small">Below <?= round($overload_threshold / 2) ?> periods</div>
                    </div>
                </div>
            </div>

            <!-- Workload Distribution Chart (Simple Visualization) -->
            <div class="card shadow-sm border-0 rounded-3 mb-4 no-print">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-secondary">
                        <i class="fa-solid fa-chart-bar me-2"></i>Workload Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <div class="progress" style="height: 30px;">
                        <?php
                        $total_teachers = count($workload_records);
                        $overload_percent = $total_teachers > 0 ? ($overloaded_teachers / $total_teachers) * 100 : 0;
                        $balanced_percent = $total_teachers > 0 ? ($balanced_teachers / $total_teachers) * 100 : 0;
                        $underload_percent = $total_teachers > 0 ? ($underloaded_teachers / $total_teachers) * 100 : 0;
                        ?>
                        <div class="progress-bar bg-danger" style="width: <?= $overload_percent ?>%"
                            title="Overloaded: <?= $overloaded_teachers ?> teachers">
                            <?= $overload_percent > 10 ? round($overload_percent) . '%' : '' ?>
                        </div>
                        <div class="progress-bar bg-warning" style="width: <?= $balanced_percent ?>%"
                            title="Balanced: <?= $balanced_teachers ?> teachers">
                            <?= $balanced_percent > 10 ? round($balanced_percent) . '%' : '' ?>
                        </div>
                        <div class="progress-bar bg-info" style="width: <?= $underload_percent ?>%"
                            title="Underloaded: <?= $underloaded_teachers ?> teachers">
                            <?= $underload_percent > 10 ? round($underload_percent) . '%' : '' ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 small">
                        <span><span class="badge bg-danger me-1"></span> Overloaded</span>
                        <span><span class="badge bg-warning me-1"></span> Balanced</span>
                        <span><span class="badge bg-info me-1"></span> Underloaded</span>
                    </div>
                </div>
            </div>

            <!-- Teacher Workload Table -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-secondary text-uppercase" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-chalkboard-user me-2"></i>Teacher Workload Grid
                    </h5>
                    <span class="badge bg-secondary">
                        Threshold: <?= $overload_threshold ?> periods/week
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="workloadTable">
                        <thead class="table-light text-uppercase small font-monospace" style="font-size: 0.75rem;">
                            <tr>
                                <th style="width: 20%; padding-left: 1.25rem;">Teacher</th>
                                <th style="width: 25%;">Assigned Subjects</th>
                                <th class="text-center" style="width: 10%;">Sections</th>
                                <th class="text-center" style="width: 10%;">Classes</th>
                                <th class="text-center" style="width: 15%;">Weekly Periods</th>
                                <th class="text-center" style="width: 20%;">Status</th>
                                <th class="text-center no-print" style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($workload_records)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-folder-open display-6 mb-3 d-block text-secondary opacity-50"></i>
                                        No structural assignment data links found in the current system state context.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($workload_records as $record):
                                    $periods = (int)$record['total_periods_allocated'];
                                    $is_overloaded = $periods > $overload_threshold;
                                    $is_warning = $periods > $overload_threshold * 0.8 && $periods <= $overload_threshold;
                                    $is_underloaded = $periods < $overload_threshold / 2;

                                    // Determine status
                                    if ($periods === 0) {
                                        $status_badge = '<span class="badge bg-secondary text-white badge-workload">Unassigned</span>';
                                        $status_icon = '<i class="fa-solid fa-ban text-secondary"></i>';
                                        $progress_color = 'bg-secondary';
                                        $percentage = 0;
                                    } elseif ($is_overloaded) {
                                        $status_badge = '<span class="badge bg-danger text-white badge-workload overload-badge"><i class="fa-solid fa-exclamation-triangle me-1"></i>Overloaded</span>';
                                        $status_icon = '<i class="fa-solid fa-bell text-danger"></i>';
                                        $progress_color = 'bg-danger';
                                        $percentage = min(($periods / $overload_threshold) * 100, 100);
                                    } elseif ($is_warning) {
                                        $status_badge = '<span class="badge bg-warning text-dark badge-workload"><i class="fa-solid fa-clock me-1"></i>Near Limit</span>';
                                        $status_icon = '<i class="fa-solid fa-hourglass-half text-warning"></i>';
                                        $progress_color = 'bg-warning';
                                        $percentage = min(($periods / $overload_threshold) * 100, 100);
                                    } elseif ($is_underloaded && $periods > 0) {
                                        $status_badge = '<span class="badge bg-info text-white badge-workload"><i class="fa-solid fa-arrow-down me-1"></i>Underloaded</span>';
                                        $status_icon = '<i class="fa-solid fa-arrow-trend-down text-info"></i>';
                                        $progress_color = 'bg-info';
                                        $percentage = min(($periods / $overload_threshold) * 100, 100);
                                    } else {
                                        $status_badge = '<span class="badge bg-success text-white badge-workload"><i class="fa-solid fa-check-circle me-1"></i>Balanced</span>';
                                        $status_icon = '<i class="fa-solid fa-circle-check text-success"></i>';
                                        $progress_color = 'bg-success';
                                        $percentage = min(($periods / $overload_threshold) * 100, 100);
                                    }

                                    $row_class = $is_overloaded ? 'overload-row' : ($is_warning ? 'warning-row' : '');
                                ?>
                                    <tr class="<?= $row_class ?>">
                                        <td style="padding-left: 1.25rem;">
                                            <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                                                <?= htmlspecialchars($record['teacher_name']) ?>
                                            </div>
                                            <span class="text-muted small font-monospace">
                                                ID: #<?= $record['teacher_id'] ?> | <?= htmlspecialchars($record['employee_id'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="small text-muted text-wrap" style="font-size: 0.85rem;">
                                                <?= $record['assigned_subjects'] ? htmlspecialchars($record['assigned_subjects']) : '<em class="text-danger fw-normal">No subjects assigned</em>' ?>
                                            </span>
                                            <?php if ($record['assigned_sections']): ?>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        <i class="fa-solid fa-users me-1"></i>
                                                        <?= htmlspecialchars(substr($record['assigned_sections'], 0, 50)) ?>
                                                        <?= strlen($record['assigned_sections']) > 50 ? '...' : '' ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold text-secondary">
                                            <?= $record['total_sections_managed'] ?: 0 ?>
                                        </td>
                                        <td class="text-center fw-bold text-secondary">
                                            <?= $record['total_classes_managed'] ?: 0 ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="h6 mb-0 fw-bold <?= $is_overloaded ? 'text-danger' : ($is_warning ? 'text-warning' : 'text-dark') ?>">
                                                <?= $periods ?>
                                            </span>
                                            <span class="text-muted small d-block" style="font-size: 11px;">periods/week</span>
                                        </td>
                                        <td style="min-width: 150px;">
                                            <div class="d-flex align-items-center justify-content-between mb-1 small">
                                                <span><?= $status_badge ?></span>
                                                <span class="fw-bold <?= $is_overloaded ? 'text-danger' : ($is_warning ? 'text-warning' : 'text-muted') ?>">
                                                    <?= round($percentage) ?>%
                                                </span>
                                            </div>
                                            <div class="progress" style="height: 6px; border-radius: 4px; background-color: #e9ecef;">
                                                <div class="progress-bar <?= $progress_color ?>" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                            <?php if ($is_overloaded): ?>
                                                <small class="text-danger mt-1 d-block">
                                                    <i class="fa-solid fa-calculator me-1"></i>
                                                    Over by <?= $periods - $overload_threshold ?> periods
                                                </small>
                                            <?php elseif ($is_underloaded && $periods > 0): ?>
                                                <small class="text-info mt-1 d-block">
                                                    <i class="fa-solid fa-plus-circle me-1"></i>
                                                    Can take <?= round(($overload_threshold / 2) - $periods) ?> more periods
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center no-print">
                                            <?php if ($periods > 0): ?>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        onclick="viewTeacherSchedule(<?= $record['teacher_id'] ?>)"
                                                        title="View Schedule">
                                                        <i class="fa-solid fa-calendar-week"></i>
                                                    </button>
                                                    <?php if ($is_overloaded): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            onclick="showReassignModal(<?= $record['teacher_id'] ?>, '<?= htmlspecialchars($record['teacher_name']) ?>', <?= $periods ?>, <?= $periods - $overload_threshold ?>)"
                                                            title="Reassign Periods">
                                                            <i class="fa-solid fa-people-arrows"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="assignTeacher(<?= $record['teacher_id'] ?>)"
                                                    title="Assign Periods">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notes and Recommendations -->
            <div class="card shadow-sm border-0 rounded-3 mt-4 no-print">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">
                        <i class="fa-solid fa-lightbulb text-warning me-2"></i>Recommendations
                    </h6>
                    <ul class="small text-muted mb-0">
                        <li>Ideal workload range: <?= round($overload_threshold / 2) ?> - <?= $overload_threshold ?> periods per week</li>
                        <li>Consider redistributing periods from overloaded teachers to underloaded ones</li>
                        <li>Review subject combinations for teachers handling multiple sections</li>
                        <li>Use the "Reassign" button to automatically reduce overloaded periods</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Threshold Settings Modal -->
    <div class="modal fade" id="thresholdModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-sliders-h me-2"></i>Set Overload Threshold
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            Define the maximum number of weekly periods a teacher should handle before being flagged as overloaded.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Maximum Periods Per Week</label>
                            <div class="d-flex align-items-center">
                                <input type="range" name="overload_threshold" class="form-range threshold-slider"
                                    min="5" max="40" value="<?= $overload_threshold ?>"
                                    oninput="this.nextElementSibling.value = this.value">
                                <input type="number" class="form-control" style="width: 80px;"
                                    value="<?= $overload_threshold ?>"
                                    oninput="this.previousElementSibling.value = this.value">
                            </div>
                            <div class="form-text">Recommended: 18-22 periods per week</div>
                        </div>
                        <div class="alert alert-info small">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Current setting: Teachers with > <?= $overload_threshold ?> periods will be flagged as overloaded.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_threshold" class="btn btn-primary">Apply Threshold</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reassign Periods Modal -->
    <div class="modal fade" id="reassignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="reassign_periods">
                    <input type="hidden" name="teacher_id" id="reassign_teacher_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-people-arrows me-2"></i>Reassign Teacher Periods
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Teacher: <strong id="reassign_teacher_name"></strong></p>
                        <p>Current workload: <strong id="reassign_current_load"></strong> periods/week</p>
                        <p>Overload amount: <strong id="reassign_overload_amount" class="text-danger"></strong> periods</p>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Number of periods to reassign</label>
                            <input type="number" name="reduction_amount" id="reduction_amount" class="form-control" required min="1">
                            <div class="form-text">These periods will be removed from this teacher's schedule</div>
                        </div>

                        <div class="alert alert-warning small">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                            <strong>Note:</strong> Removed periods will need to be reassigned to another teacher manually.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reassign Periods</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        // Initialize DataTable for sorting
        $(document).ready(function() {
            $('#workloadTable').DataTable({
                pageLength: 10,
                lengthChange: true,
                ordering: true,
                order: [
                    [4, 'desc']
                ], // Sort by periods column descending
                language: {
                    search: "Search teachers:",
                    lengthMenu: "Show _MENU_ teachers",
                    info: "Showing _START_ to _END_ of _TOTAL_ teachers"
                }
            });
        });

        function showReassignModal(teacherId, teacherName, currentLoad, overloadAmount) {
            document.getElementById('reassign_teacher_id').value = teacherId;
            document.getElementById('reassign_teacher_name').textContent = teacherName;
            document.getElementById('reassign_current_load').textContent = currentLoad;
            document.getElementById('reassign_overload_amount').textContent = overloadAmount;
            document.getElementById('reduction_amount').value = overloadAmount;
            document.getElementById('reduction_amount').max = currentLoad;
            document.getElementById('reduction_amount').min = 1;

            new bootstrap.Modal(document.getElementById('reassignModal')).show();
        }

        function viewTeacherSchedule(teacherId) {
            window.location.href = `teacher_schedule.php?teacher_id=${teacherId}`;
        }

        function assignTeacher(teacherId) {
            window.location.href = `timetable_create.php?teacher_id=${teacherId}`;
        }

        // Real-time threshold slider update
        document.querySelectorAll('.threshold-slider').forEach(slider => {
            slider.addEventListener('input', function() {
                const numberInput = this.nextElementSibling;
                if (numberInput) numberInput.value = this.value;
            });
        });

        document.querySelectorAll('input[type="number"][oninput]').forEach(input => {
            input.addEventListener('input', function() {
                const slider = this.previousElementSibling;
                if (slider && slider.classList.contains('threshold-slider')) {
                    slider.value = this.value;
                }
            });
        });
    </script>

    <!-- Add DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
</body>

</html>