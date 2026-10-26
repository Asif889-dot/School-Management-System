<?php
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

requireLogin();

// Dynamically target the current operational Academic Year configuration context
$yearStmt = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY is_current DESC, id DESC LIMIT 1");
$currentYear = $yearStmt->fetch();
$academic_year_id = $currentYear ? $currentYear['id'] : 1;

$selected_section = filter_var($_GET['section_id'] ?? null, FILTER_VALIDATE_INT);

if (!$selected_section) {
    die("Error: No valid Section ID provided for printing.");
}

// Fetch Section and Class Header Data
$secStmt = $pdo->prepare("SELECT s.section_name, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
$secStmt->execute([$selected_section]);
$sectionData = $secStmt->fetch();

if (!$sectionData) {
    die("Error: Section configuration context not found.");
}

$days = $pdo->query("SELECT id, day_name FROM school_days WHERE is_working = 1 ORDER BY id")->fetchAll();
$slots = $pdo->query("SELECT id, slot_name, is_break, start_time, end_time FROM time_slots ORDER BY period_number")->fetchAll();

// Metrics & Grid Matrix compilation loops
$matrix = [];
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Print - <?= htmlspecialchars($sectionData['class_name'] . ' (' . $sectionData['section_name'] . ')') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            color: #000;
            background-color: #fff;
        }

        .print-header {
            border-bottom: 3px double #2c3e50;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        .academy-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .timetable-table {
            table-layout: fixed;
            width: 100%;
        }

        .timetable-table th,
        .timetable-table td {
            text-align: center;
            vertical-align: middle;
            padding: 8px !important;
            font-size: 0.8rem;
            border: 1px solid #000 !important;
            word-wrap: break-word;
        }

        .timetable-header th {
            background-color: #f1f3f5 !important;
            font-weight: 600;
            color: #000;
        }

        .day-cell {
            background-color: #f8f9fa !important;
            font-weight: 600;
            width: 100px;
        }

        .break-cell {
            background-color: #fffdf0 !important;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #744210;
        }

        .slot-assigned {
            background-color: #fff !important;
        }

        .course-name {
            font-weight: 700;
            color: #000;
            font-size: 0.85rem;
        }

        .teacher-name {
            font-size: 0.75rem;
            color: #333;
            margin-top: 2px;
        }

        .course-code {
            font-family: monospace;
            font-size: 0.7rem;
            color: #555;
        }

        .slot-free {
            color: #ccc;
            font-style: italic;
            font-size: 0.75rem;
        }

        .no-print-zone {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        /* Pure CSS Print Rules Override */
        @media print {
            .no-print-zone {
                display: none !important;
            }

            body {
                padding: 0;
                margin: 0;
            }

            .container-fluid {
                width: 100% !important;
                padding: 0 !important;
            }

            @page {
                size: landscape;
                margin: 0.5cm;
            }

            .timetable-table th {
                background-color: #eaedf1 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .break-cell {
                background-color: #fffde6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body onload="window.print();">

    <div class="no-print-zone p-3 d-flex justify-content-between align-items-center">
        <span class="text-secondary small">
            <strong class="text-dark">Print Mode Engine:</strong> Orientation set automatically to Landscape layout.
        </span>
        <div>
            <a href="timetable_create.php?section_id=<?= $selected_section ?>" class="btn btn-sm btn-secondary me-2">
                Exit Print Mode
            </a>
            <button onclick="window.print();" class="btn btn-sm btn-primary">
                Trigger Print Spool
            </button>
        </div>
    </div>

    <div class="container-fluid p-4">

        <div class="print-header d-flex justify-content-between align-items-end">
            <div>
                <div class="academy-title">Smart Choice Academy</div>
                <div class="text-muted small">Academic Management Information System Layer</div>
            </div>
            <div class="text-end">
                <h4 class="m-0 fw-bold text-dark"><?= htmlspecialchars($sectionData['class_name'] . ' - ' . $sectionData['section_name']) ?></h4>
                <span class="badge bg-dark text-white font-monospace mt-1">Term Frame Cycle: <?= htmlspecialchars($currentYear['year_name'] ?? '2026') ?></span>
            </div>
        </div>

        <table class="table table-bordered timetable-table align-middle m-0">
            <thead class="timetable-header">
                <tr>
                    <th style="width: 10%;">Day Pattern</th>
                    <?php foreach ($slots as $slot): ?>
                        <th>
                            <span class="d-block mb-1"><?= htmlspecialchars($slot['slot_name']) ?></span>
                            <span class="d-block text-muted font-monospace fw-normal" style="font-size: 10px;">
                                <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                            </span>
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
                                <td class="break-cell">RECESS</td>
                            <?php else: ?>
                                <?php if (isset($matrix[$day['id']][$slot['id']])):
                                    $cell = $matrix[$day['id']][$slot['id']]; ?>
                                    <td class="slot-assigned text-center">
                                        <div class="course-name"><?= htmlspecialchars($cell['course_name']) ?></div>
                                        <div class="teacher-name"><?= htmlspecialchars($cell['teacher_name']) ?></div>
                                        <div class="course-code">[<?= htmlspecialchars($cell['course_code']) ?>]</div>
                                    </td>
                                <?php else: ?>
                                    <td class="slot-free">-</td>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center mt-5 pt-3" style="border-top: 1px dashed #ccc; font-size: 11px; color: #555;">
            <div>Generated Systematically: <?= date('Y-m-d H:i:s') ?></div>
            <div style="width: 200px; border-top: 1px solid #000; text-align: center; font-weight: 500;">Authorized Registrar Signature</div>
        </div>

    </div>

</body>

</html>