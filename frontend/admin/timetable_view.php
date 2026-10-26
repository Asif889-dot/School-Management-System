<?php
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

// Public view authorization check or role validation
requireLogin();

// Target active operational Academic Year configuration context
$yearStmt = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY is_current DESC, id DESC LIMIT 1");
$currentYear = $yearStmt->fetch();
$academic_year_id = $currentYear ? $currentYear['id'] : 1;

// Fetch sections for layout rendering filters
$sections = $pdo->query("SELECT s.id, CONCAT(c.class_name, ' - ', s.section_name) as title FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY c.class_name ASC")->fetchAll();
$selected_section = $_GET['section_id'] ?? ($sections[0]['id'] ?? null);

// Fetch structurally configured operational days and matching slot profiles
$days = $pdo->query("SELECT id, day_name FROM school_days WHERE is_working = 1 ORDER BY id")->fetchAll();
$slots = $pdo->query("SELECT id, slot_name, is_break, start_time, end_time FROM time_slots ORDER BY period_number")->fetchAll();

// Build operational structural multi-dimensional array mapping matrix
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCA - Timetable Display Console</title>
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
            transition: all 0.3s ease;
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
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .slot-assigned {
            background-color: #eff6ff !important;
            border-left: 4px solid #3b82f6 !important;
            border: 1px solid #bfdbfe;
        }

        /* Clean Print Engine Formatting Definitions */
        @media print {

            .sidebar,
            .no-print,
            form,
            label,
            .btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .table-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }

            table {
                width: 100% !important;
                font-size: 11px !important;
            }

            .slot-assigned {
                background-color: #f4f4f5 !important;
                border-left: 4px solid #71717a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4 no-print">
            <div>
                <h4 class="m-0 fw-bold text-dark">Schedule Matrix Calendar View</h4>
                <small class="text-muted">Active Session: <strong><?= htmlspecialchars($currentYear['year_name'] ?? 'Default Year Matrix') ?></strong></small>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print();" class="btn btn-outline-primary fw-semibold d-flex align-items-center gap-2" style="border-radius: 8px;">
                    <i class="bi bi-printer-fill"></i> Print Timetable Sheet
                </button>
            </div>
        </div>

        <div class="card table-card p-4">
            <div class="row align-items-center mb-4 g-3 no-print">
                <div class="col-md-5 col-lg-4">
                    <label class="form-label fw-semibold text-secondary">Switch Classroom Context View:</label>
                    <form method="GET" id="sectionSelectorForm" class="m-0">
                        <select name="section_id" class="form-select border-2" onchange="document.getElementById('sectionSelectorForm').submit();" style="border-radius: 8px;">
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= $sec['id'] ?>" <?= $selected_section == $sec['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sec['title']) ?>
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
                            <th>Day Period</th>
                            <?php foreach ($slots as $slot): ?>
                                <th>
                                    <span class="d-block fw-semibold"><?= htmlspecialchars($slot['slot_name']) ?></span>
                                    <small class="text-muted font-monospace" style="font-size: 11px; opacity: 0.8;">
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
                                            <td class="slot-assigned text-start p-3">
                                                <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;"><?= htmlspecialchars($cell['course_name']) ?></div>
                                                <div class="text-muted mb-1" style="font-size: 11px;">
                                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($cell['teacher_name']) ?>
                                                </div>
                                                <div class="text-primary font-monospace" style="font-size: 10px; font-weight: 500;">
                                                    <?= htmlspecialchars($cell['employee_id']) ?>
                                                </div>
                                            </td>
                                        <?php else: ?>
                                            <td class="text-muted table-light py-3" style="font-size: 0.8rem; font-style: italic; opacity: 0.6;">
                                                Free Period
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