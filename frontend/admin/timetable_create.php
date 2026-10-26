<?php
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

requireLogin();
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && !in_array('timetable_manage', $_SESSION['permissions'] ?? [])) {
    header("Location: ../dashboard.php");
    exit();
}

require_once '../../backend/TimetableGenerator.php';

// Dynamically target the current operational Academic Year configuration context
$yearStmt = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY is_current DESC, id DESC LIMIT 1");
$currentYear = $yearStmt->fetch();
$academic_year_id = $currentYear ? $currentYear['id'] : 1;

$message = "";

// 1. Process Core Generator Engine Command
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_generator'])) {
    try {
        $engine = new TimetableGenerator($pdo, $academic_year_id);
        if ($engine->generate()) {
            $message = "
            <div class='alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
                <i class='bi bi-check-circle-fill me-3 fs-4 text-success'></i>
                <div>
                    <strong>Success!</strong> Automated scheduling loop finished allocation without structural teacher conflicts or section bottlenecks.
                </div>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
        } else {
            $message = "
            <div class='alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
                <i class='bi bi-exclamation-triangle-fill me-3 fs-4 text-warning'></i>
                <div>
                    <strong>Notice:</strong> Matrix generation cycle completed, but zero records saved. Please ensure valid entries exist within the <code>teacher_assignments</code> map layer.
                </div>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
        }
    } catch (\Exception $e) {
        $message = "
        <div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
            <i class='bi bi-x-circle-fill me-3 fs-4 text-danger'></i>
            <div>
                <strong>Engine Clashed!</strong> Fallback processing halted. Details: <code>" . htmlspecialchars($e->getMessage()) . "</code>
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}

// 2. Process Manual Direct Matrix Assignment Form (PERMANENTLY SOLVED FK ERROR 1452)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_manual_assign'])) {
    $section_id = filter_var($_POST['section_id'], FILTER_VALIDATE_INT);
    $day_id = filter_var($_POST['day_id'], FILTER_VALIDATE_INT);
    $slot_id = filter_var($_POST['slot_id'], FILTER_VALIDATE_INT);
    $assignment_id = filter_var($_POST['assignment_id'], FILTER_VALIDATE_INT);

    if ($section_id && $day_id && $slot_id && $assignment_id) {
        try {
            // Pull class_id dynamically alongside mapping fields from the assignments relational layer
            $assignStmt = $pdo->prepare("
                SELECT ta.teacher_id, ta.course_id, s.class_id 
                FROM teacher_assignments ta
                INNER JOIN sections s ON ta.section_id = s.id
                WHERE ta.id = ?
            ");
            $assignStmt->execute([$assignment_id]);
            $assignment = $assignStmt->fetch();

            if ($assignment) {
                if (empty($assignment['class_id'])) {
                    throw new \Exception("Structural Configuration Disconnect! The chosen section does not point to a valid parent Class framework ID row mapping.");
                }

                // Check if teacher is already booked on this exact day/slot combo elsewhere
                $chkTeacher = $pdo->prepare("SELECT id FROM timetable_records WHERE day_id = ? AND slot_id = ? AND teacher_id = ? AND academic_year_id = ? LIMIT 1");
                $chkTeacher->execute([$day_id, $slot_id, $assignment['teacher_id'], $academic_year_id]);

                if ($chkTeacher->fetch()) {
                    throw new \Exception("Conflict detected! This instructor is already allocated to another classroom matrix layout segment during this time frame block.");
                }

                // Upsert structural placement context including verified parent class_id parameter link
                $upsertStmt = $pdo->prepare("
                    INSERT INTO timetable_records (academic_year_id, day_id, slot_id, class_id, section_id, course_id, teacher_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE course_id = VALUES(course_id), teacher_id = VALUES(teacher_id)
                ");

                $upsertStmt->execute([
                    $academic_year_id,
                    $day_id,
                    $slot_id,
                    $assignment['class_id'],
                    $section_id,
                    $assignment['course_id'],
                    $assignment['teacher_id']
                ]);

                $message = "<div class='alert alert-success alert-dismissible fade show border-0 shadow-sm' role='alert'>
                    <i class='bi bi-check-circle-fill me-2'></i><strong>Slot Modified Successfully:</strong> Record safely synchronized to active workspace maps.
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
            } else {
                throw new \Exception("Invalid or missing assignment data link context.");
            }
        } catch (\Exception $e) {
            $message = "<div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm' role='alert'>
                <i class='bi bi-shield-slash-fill me-2'></i><strong>Placement Blocked:</strong> " . htmlspecialchars($e->getMessage()) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 3. Process Delete Action Command
if (isset($_GET['clear_day']) && isset($_GET['clear_slot']) && isset($_GET['section_id'])) {
    $cDay = filter_var($_GET['clear_day'], FILTER_VALIDATE_INT);
    $cSlot = filter_var($_GET['clear_slot'], FILTER_VALIDATE_INT);
    $cSec = filter_var($_GET['section_id'], FILTER_VALIDATE_INT);

    if ($cDay && $cSlot && $cSec) {
        $delStmt = $pdo->prepare("DELETE FROM timetable_records WHERE day_id = ? AND slot_id = ? AND section_id = ? AND academic_year_id = ?");
        $delStmt->execute([$cDay, $cSlot, $cSec, $academic_year_id]);
        header("Location: timetable_create.php?section_id=" . $cSec);
        exit();
    }
}

// Fetch structural dropdown datasets
$sections = $pdo->query("SELECT s.id, CONCAT(c.class_name, ' - ', s.section_name) as title FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY c.class_name ASC")->fetchAll();
$selected_section = $_GET['section_id'] ?? ($sections[0]['id'] ?? null);

$days = $pdo->query("SELECT id, day_name FROM school_days WHERE is_working = 1 ORDER BY id")->fetchAll();
$slots = $pdo->query("SELECT id, slot_name, is_break, start_time, end_time FROM time_slots ORDER BY period_number")->fetchAll();

// System Debug Monitor: Total configuration map counter
$debugCountGlobal = $pdo->prepare("SELECT COUNT(*) FROM teacher_assignments WHERE academic_year_id = ?");
$debugCountGlobal->execute([$academic_year_id]);
$globalAssignmentsCount = $debugCountGlobal->fetchColumn();

// Fetch workload mappings available to populate context quick options
$workloadOptions = [];
if ($selected_section) {
    $wlStmt = $pdo->prepare("
        SELECT ta.id, co.course_name, co.course_code, u.full_name as teacher_name
        FROM teacher_assignments ta
        JOIN courses co ON ta.course_id = co.id
        JOIN teachers t ON ta.teacher_id = t.teacher_id
        JOIN users u ON t.user_id = u.id
        WHERE ta.section_id = ? AND ta.academic_year_id = ?
    ");
    $wlStmt->execute([$selected_section, $academic_year_id]);
    $workloadOptions = $wlStmt->fetchAll();
}

// Metrics compilation loops
$countAssigned = 0;
$matrix = [];
if ($selected_section) {
    $matrixQuery = $pdo->prepare("
        SELECT tr.day_id, tr.slot_id, co.course_name, co.course_code, t.employee_id, u.full_name as teacher_name
        FROM timetable_records tr
        JOIN courses co ON tr.course_id = co.id
        JOIN teachers t ON tr.teacher_id = t.teacher_id
        JOIN users u ON t.user_id = u.id
        WHERE tr.section_id = ? AND tr.academic_year_id = ?
    ");
    $matrixQuery->execute([$selected_section, $academic_year_id]);
    while ($r = $matrixQuery->fetch()) {
        $matrix[$r['day_id']][$r['slot_id']] = $r;
        $countAssigned++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCA Timetable Matrix Automation Control Desk</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .page-header-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
        }

        .table-card {
            background: #fff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .timetable-table {
            border-collapse: separate;
            border-spacing: 6px;
        }

        .timetable-header th {
            background-color: #2c3e50 !important;
            color: #fff !important;
            font-weight: 500;
            padding: 14px;
            font-size: 0.9rem;
            border: none !important;
        }

        .day-cell {
            background-color: #eaedf1 !important;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
            width: 130px;
        }

        .break-cell {
            background: repeating-linear-gradient(45deg, #fef5d1, #fef5d1 10px, #fdf1be 10px, #fdf1be 20px) !important;
            font-weight: 600;
            text-align: center;
            color: #b7791f;
            vertical-align: middle;
            font-size: 0.75rem;
            letter-spacing: 2px;
            border: 1px dashed #fbd38d !important;
            text-transform: uppercase;
        }

        .slot-assigned {
            background-color: #f0fdf4 !important;
            border-left: 4px solid #16a34a !important;
            border: 1px solid #bbf7d0;
            position: relative;
        }

        .slot-action-overlay {
            position: absolute;
            top: 4px;
            right: 4px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .slot-assigned:hover .slot-action-overlay {
            opacity: 1;
        }

        .slot-free {
            background-color: #fafafa !important;
            color: #a3a3a3;
            font-size: 0.8rem;
            font-style: italic;
            border: 1px dashed #e5e5e5 !important;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .slot-free:hover {
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #475569;
        }

        .stat-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 400;
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
        <div class="card page-header-card shadow-sm mb-4">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h3 class="m-0 fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-cpu-fill text-info"></i> Scheduler Control Desk
                    </h3>
                    <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                        <span class="stat-badge"><i class="bi bi-calendar3 me-1"></i> Term: <strong><?= htmlspecialchars($currentYear['year_name'] ?? 'Unset Academic Frame') ?></strong></span>
                        <span class="stat-badge"><i class="bi bi-bookmark-check me-1"></i> Section Load: <strong><?= $countAssigned ?> Slots Configured</strong></span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="timetable_print.php?section_id=<?= $selected_section ?>" target="_blank" class="btn btn-dark fw-semibold px-3 py-2 shadow-sm d-flex align-items-center gap-2">
                        <i class="bi bi-print"></i> Print Grid Layout
                    </a>
                    <form method="POST" class="m-0" onsubmit="return confirm('Running the engine recalculation loop resets all saved structural entries for this academic frame. Continue?');">
                        <button type="submit" name="trigger_generator" class="btn btn-info text-white fw-semibold px-4 py-2 shadow-sm d-flex align-items-center gap-2">
                            <i class="bi bi-lightning-charge-fill"></i> Run Engine Solver
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?= $message ?>

        <?php if ($globalAssignmentsCount > 0 && empty($workloadOptions)): ?>
            <div class="alert alert-info border-0 shadow-sm p-3 mb-4 d-flex align-items-start">
                <i class="bi bi-info-circle-fill text-info fs-4 me-3 mt-1"></i>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Diagnostic Engine Report: Scope Boundary Isolated</h6>
                    <small class="text-secondary d-block mb-2">
                        Your system contains <strong><?= $globalAssignmentsCount ?> configuration rules</strong> inside <code>teacher_assignments</code>, but <strong>0 maps</strong> point directly to the section you are currently looking at.
                    </small>
                    <span class="badge bg-dark">Action Required: Change the Scope Workspace Filter below or assign subjects to this section.</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="card table-card p-4">
            <div class="row align-items-center mb-4 g-3">
                <div class="col-md-5 col-lg-4">
                    <label class="form-label fw-semibold text-dark mb-2">
                        <i class="bi bi-funnel-fill text-secondary me-1"></i> Scope Workspace Filter
                    </label>
                    <form method="GET" id="sectionFilterForm" class="m-0">
                        <select name="section_id" class="form-select border-2" onchange="document.getElementById('sectionFilterForm').submit();" style="border-radius: 8px;">
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= $sec['id'] ?>" <?= $selected_section == $sec['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sec['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-7 col-lg-8 text-md-end pt-md-4">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill me-2">
                        <span class="d-inline-block bg-success rounded-circle me-1" style="width:7px; height:7px;"></span> Allocated
                    </span>
                    <span class="badge bg-light text-muted border px-3 py-2 rounded-pill">
                        <span class="d-inline-block bg-secondary-subtle rounded-circle me-1" style="width:7px; height:7px;"></span> Click Empty Slot to Assign
                    </span>
                </div>
            </div>

            <div class="table-responsive rounded-3 border">
                <table class="table table-bordered timetable-table text-center align-middle m-0">
                    <thead class="timetable-header">
                        <tr>
                            <th>Day Pattern</th>
                            <?php foreach ($slots as $slot): ?>
                                <th>
                                    <span class="d-block fw-semibold mb-1"><?= htmlspecialchars($slot['slot_name']) ?></span>
                                    <span class="badge bg-black bg-opacity-25 font-monospace fw-normal p-1" style="font-size: 11px;">
                                        <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $day): ?>
                            <tr>
                                <td class="day-cell align-middle"><?= htmlspecialchars($day['day_name']) ?></td>
                                <?php foreach ($slots as $slot): ?>
                                    <?php if ($slot['is_break']): ?>
                                        <td class="break-cell">Recess Intermission Break</td>
                                    <?php else: ?>
                                        <?php if (isset($matrix[$day['id']][$slot['id']])):
                                            $cell = $matrix[$day['id']][$slot['id']]; ?>
                                            <td class="slot-assigned text-start p-3">
                                                <div class="slot-action-overlay">
                                                    <a href="?section_id=<?= $selected_section ?>&clear_day=<?= $day['id'] ?>&clear_slot=<?= $slot['id'] ?>"
                                                        class="btn btn-danger btn-sm p-0 d-flex align-items-center justify-content-center rounded-circle"
                                                        style="width: 20px; height: 20px; font-size: 10px;"
                                                        onclick="return confirm('Remove this specific course cell entry?');">
                                                        <i class="bi bi-x"></i>
                                                    </a>
                                                </div>
                                                <div class="fw-bold text-dark mb-1" style="font-size: 0.9rem;"><?= htmlspecialchars($cell['course_name']) ?></div>
                                                <div class="text-secondary mb-1" style="font-size: 11px;">
                                                    <i class="bi bi-person-fill text-muted me-1"></i><?= htmlspecialchars($cell['teacher_name']) ?>
                                                </div>
                                                <div class="badge bg-secondary-subtle text-secondary-emphasis font-monospace border" style="font-size: 10px; padding: 3px 6px;">
                                                    <?= htmlspecialchars($cell['employee_id']) ?>
                                                </div>
                                            </td>
                                        <?php else: ?>
                                            <td class="slot-free text-muted"
                                                data-bs-toggle="modal"
                                                data-bs-target="#quickAssignModal"
                                                data-day-id="<?= $day['id'] ?>"
                                                data-day-name="<?= htmlspecialchars($day['day_name']) ?>"
                                                data-slot-id="<?= $slot['id'] ?>"
                                                data-slot-name="<?= htmlspecialchars($slot['slot_name']) ?>">
                                                <div class="d-flex flex-column align-items-center opacity-50 py-2">
                                                    <i class="bi bi-plus-circle mb-1 fs-5"></i>
                                                    <span>Unassigned</span>
                                                </div>
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

    <div class="modal fade" id="quickAssignModal" tabindex="-1" aria-labelledby="quickAssignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="quickAssignModalLabel"><i class="bi bi-bookmark-plus me-2 text-info"></i>Direct Allocation Bridge</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="section_id" value="<?= htmlspecialchars($selected_section) ?>">
                        <input type="hidden" name="day_id" id="modal_day_id">
                        <input type="hidden" name="slot_id" id="modal_slot_id">

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label text-muted small uppercase fw-bold m-0">Target Day</label>
                                <div class="form-control-plaintext fw-semibold fs-6 text-dark" id="modal_day_text">-</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small uppercase fw-bold m-0">Timeline Bound Target</label>
                                <div class="form-control-plaintext fw-semibold fs-6 text-dark" id="modal_slot_text">-</div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold text-dark">Available Workload Modules</label>
                            <?php if (empty($workloadOptions)): ?>
                                <div class="alert alert-warning py-2 small m-0">
                                    <i class="bi bi-info-circle me-1"></i> No workload assignments mapped to this classroom target. Please navigate to the <strong>Assign Subject</strong> panel first.
                                </div>
                            <?php else: ?>
                                <select name="assignment_id" class="form-select border-2" required style="border-radius: 8px;">
                                    <option value="" disabled selected>-- Choose Course/Teacher Profile Map --</option>
                                    <?php foreach ($workloadOptions as $option): ?>
                                        <option value="<?= $option['id'] ?>">
                                            [<?= htmlspecialchars($option['course_code'] ?? 'CODE') ?>] <?= htmlspecialchars($option['course_name']) ?> — Instructor: <?= htmlspecialchars($option['teacher_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-secondary fw-medium" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <?php if (!empty($workloadOptions)): ?>
                            <button type="submit" name="action_manual_assign" class="btn btn-success fw-semibold px-4" style="border-radius: 8px;">
                                <i class="bi bi-save me-1"></i> Apply Insertion Map
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const quickAssignModal = document.getElementById('quickAssignModal');
        if (quickAssignModal) {
            quickAssignModal.addEventListener('show.bs.modal', function(event) {
                const triggerCell = event.relatedTarget;

                const dayId = triggerCell.getAttribute('data-day-id');
                const dayName = triggerCell.getAttribute('data-day-name');
                const slotId = triggerCell.getAttribute('data-slot-id');
                const slotName = triggerCell.getAttribute('data-slot-name');

                document.getElementById('modal_day_id').value = dayId;
                document.getElementById('modal_slot_id').value = slotId;
                document.getElementById('modal_day_text').textContent = dayName;
                document.getElementById('modal_slot_text').textContent = slotName;
            });
        }
    </script>
</body>

</html>